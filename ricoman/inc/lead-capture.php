<?php
/**
 * Lead capture + Google Sheets automation.
 *
 * Provides a [ricoman_lead_form] shortcode (use it via the Shortcode block or
 * the bundled "Lead form" pattern). Submissions are validated, stored as `lead`
 * posts, emailed to the admin, and pushed to a Google Sheets webhook.
 *
 * To enable the Sheets sync, define the webhook URL of a Google Apps Script Web
 * App (deployed as "Anyone") in wp-config.php:
 *
 *     define( 'RICOMAN_SHEETS_WEBHOOK', 'https://script.google.com/macros/s/XXXX/exec' );
 *
 * or save it under Settings → General → "Leads webhook URL" (option
 * `ricoman_sheets_webhook`). The script receives a JSON POST of the lead.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send a plain-text "we've received your enquiry" confirmation to the person who
 * submitted a form. Skips silently if there's no valid email. Reply-To routes to
 * the team so a customer's reply reaches Ricoman. Only ever called for genuine
 * (non-spam) submissions — the capture functions bail on spam before this runs.
 *
 * @param string $email Submitter's email address.
 * @param string $name  Submitter's name.
 * @param array  $args  Optional overrides: subject, intro, reply_to.
 */
function ricoman_send_lead_confirmation( $email, $name, $args = array() ) {
	$email = sanitize_email( (string) $email );
	if ( ! is_email( $email ) ) {
		return;
	}
	$site = get_bloginfo( 'name' );
	$a    = wp_parse_args( $args, array(
		'subject'  => sprintf( __( 'We’ve received your enquiry — %s', 'ricoman' ), $site ),
		'intro'    => __( 'Thanks for getting in touch. We’ve received your enquiry and a member of our team will be in touch shortly.', 'ricoman' ),
		'reply_to' => get_option( 'admin_email' ),
	) );
	$name    = trim( (string) $name );
	$body    = sprintf(
		"Hi %s,\n\n%s\n\nIf you need anything in the meantime, just reply to this email.\n\n— %s",
		'' !== $name ? $name : __( 'there', 'ricoman' ),
		$a['intro'],
		$site
	);
	$headers = ! empty( $a['reply_to'] ) ? array( 'Reply-To: ' . $a['reply_to'] ) : array();
	wp_mail( $email, $a['subject'], $body, $headers );
}

/**
 * The visitor's IP (best-effort), used only for short-lived rate-limiting.
 */
function ricoman_lead_client_ip() {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $k ) {
		if ( ! empty( $_SERVER[ $k ] ) ) {
			$ip = trim( explode( ',', wp_unslash( $_SERVER[ $k ] ) )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	return '';
}

/**
 * Shared spam screen for every lead-capture point, layered on top of the
 * per-form nonce + honeypot. Returns true when a submission looks like spam and
 * should be dropped. Catches: too-many-from-one-IP (rate limit), instant
 * (sub-second) submits via a time-trap, link-stuffing, and obvious spam terms.
 * No third-party captcha needed at this volume; all thresholds are filterable.
 *
 * @param array $args [ 'ts' => render unix time (0 to skip), 'fields' => string[] to scan ].
 * @return bool
 */
function ricoman_lead_is_spam( $args = array() ) {
	// 0. Cloudflare Turnstile — when configured and this form opted in, a failed
	// or missing token means it wasn't a real browser challenge → spam.
	if ( ! empty( $args['turnstile'] ) && ricoman_turnstile_enabled() && ! empty( $_POST['rm_ts'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$tok = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! ricoman_turnstile_verify( $tok ) ) {
			return true;
		}
	}

	// 1. Per-IP rate limit over a short window.
	$ip = ricoman_lead_client_ip();
	if ( $ip ) {
		$key   = 'rm_lead_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		$max   = (int) apply_filters( 'ricoman_lead_rate_limit', 6 );
		$win   = (int) apply_filters( 'ricoman_lead_rate_window', 10 * MINUTE_IN_SECONDS );
		if ( $count >= $max ) {
			return true;
		}
		set_transient( $key, $count + 1, $win );
	}

	// 2. Time-trap — humans don't submit in under a couple of seconds. Only the
	// lower bound is enforced so full-page caching can't cause false positives.
	if ( ! empty( $args['ts'] ) ) {
		$elapsed = time() - (int) $args['ts'];
		$min     = (int) apply_filters( 'ricoman_lead_min_seconds', 3 );
		if ( $elapsed >= 0 && $elapsed < $min ) {
			return true;
		}
	}

	// 3. Link-stuffing + obvious spam terms in the free-text fields.
	$blob = strtolower( implode( ' ', array_map( 'strval', (array) ( isset( $args['fields'] ) ? $args['fields'] : array() ) ) ) );
	if ( '' !== $blob ) {
		if ( preg_match_all( '#https?://|www\.#i', $blob ) >= (int) apply_filters( 'ricoman_lead_max_links', 3 ) ) {
			return true;
		}
		if ( preg_match( '/\b(viagra|cialis|casino|porn|crypto\s*airdrop|seo\s*service|backlinks?|loan offer)\b/i', $blob ) ) {
			return true;
		}
	}

	// 4. Identity blocklist — known spam names / terms (filterable; seeded with
	// the current "RobertWaics" bot). Matched with whitespace removed so
	// "Robert Waics", "RobertWaics" and "robertwaics" all match.
	$squashed = preg_replace( '/\s+/', '', $blob );
	if ( '' !== $squashed ) {
		foreach ( (array) apply_filters( 'ricoman_lead_blocklist', array( 'robertwaics' ) ) as $needle ) {
			$needle = preg_replace( '/\s+/', '', strtolower( (string) $needle ) );
			if ( '' !== $needle && false !== strpos( $squashed, $needle ) ) {
				return true;
			}
		}
	}

	// 5. Same-identity flood — a bot hammering the form under one name with many
	// different emails. The first field is the submitter's name at every call
	// site; if the same (normalised) name submits more than a few times within
	// the window, treat further ones as spam. Legit exact-name duplicates in a
	// day are rare; a run of ~100 is unmistakable. All thresholds filterable.
	$fields = isset( $args['fields'] ) ? array_values( (array) $args['fields'] ) : array();
	$name   = isset( $fields[0] ) ? trim( (string) $fields[0] ) : '';
	if ( '' !== $name ) {
		$nkey   = 'rm_lead_nm_' . md5( strtolower( preg_replace( '/\s+/', ' ', $name ) ) );
		$ncount = (int) get_transient( $nkey );
		$nmax   = (int) apply_filters( 'ricoman_lead_name_limit', 4 );
		$nwin   = (int) apply_filters( 'ricoman_lead_name_window', DAY_IN_SECONDS );
		if ( $ncount >= $nmax ) {
			return true;
		}
		set_transient( $nkey, $ncount + 1, $nwin );
	}

	return false;
}

/* -------------------------------------------------- Cloudflare Turnstile ---- */

/** Turnstile is active only when BOTH keys are configured (Ricoman → Header & Menu). */
function ricoman_turnstile_enabled() {
	return '' !== trim( (string) ricoman_opt( 'turnstile_site' ) ) && '' !== trim( (string) ricoman_opt( 'turnstile_secret' ) );
}

/** Widget markup + loader script for a lead form ('' when not configured). */
function ricoman_turnstile_widget() {
	if ( ! ricoman_turnstile_enabled() ) {
		return '';
	}
	$site = esc_attr( trim( (string) ricoman_opt( 'turnstile_site' ) ) );
	// rm_ts marks that this form actually rendered the widget, so the server only
	// ENFORCES the token when it was really offered — a stale-cached or
	// failed-to-load form (no widget, no rm_ts) falls back to the other spam
	// checks instead of silently dropping a genuine enquiry.
	return '<div class="cf-turnstile rm-turnstile" data-sitekey="' . $site . '" data-theme="auto" style="margin:14px 0"></div>'
		. '<input type="hidden" name="rm_ts" value="1">'
		. '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

/**
 * Verify a Turnstile token server-side. true = passed (human). Returns false for
 * a missing/failed token. Fails OPEN (true) when Turnstile isn't configured or
 * Cloudflare is unreachable, so a network blip never locks real visitors out —
 * the other spam checks still run in that case.
 */
function ricoman_turnstile_verify( $token ) {
	if ( ! ricoman_turnstile_enabled() ) {
		return true;
	}
	$token = trim( (string) $token );
	if ( '' === $token ) {
		return false;
	}
	$resp = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
		'timeout' => 8,
		'body'    => array(
			'secret'   => trim( (string) ricoman_opt( 'turnstile_secret' ) ),
			'response' => $token,
			'remoteip' => ricoman_lead_client_ip(),
		),
	) );
	if ( is_wp_error( $resp ) ) {
		return true;
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
	return ! empty( $data['success'] );
}

/**
 * Render the lead capture form.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function ricoman_lead_form( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'title'  => __( 'Request a quote or scheme design', 'ricoman' ),
			'source' => '',
		),
		$atts,
		'ricoman_lead_form'
	);

	$sent   = isset( $_GET['lead'] ) ? sanitize_key( wp_unslash( $_GET['lead'] ) ) : '';
	$source = $atts['source'] ? $atts['source'] : ( is_singular() ? get_the_title() : get_bloginfo( 'name' ) );

	ob_start();

	if ( 'sent' === $sent ) {
		echo '<div id="ricoman-lead-sent" class="ricoman-lead-success" tabindex="-1"><strong>' . esc_html__( 'Thanks — your enquiry is on its way.', 'ricoman' ) . '</strong><br>' . esc_html__( 'Our lighting team will be in touch within one working day.', 'ricoman' ) . '</div>';
		// The post-submit redirect lands at the top of the page; bring the
		// confirmation into view so it isn't missed.
		echo '<script>(function(){var el=document.getElementById("ricoman-lead-sent");if(!el)return;requestAnimationFrame(function(){el.scrollIntoView({behavior:"smooth",block:"center"});el.focus({preventScroll:true});});})();</script>';
		return (string) ob_get_clean();
	}
	if ( 'error' === $sent ) {
		echo '<div class="ricoman-lead-error">' . esc_html__( 'Sorry, something went wrong. Please check the form and try again.', 'ricoman' ) . '</div>';
	}
	?>
	<form class="ricoman-lead-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ricoman_lead">
		<input type="hidden" name="lead_source" value="<?php echo esc_attr( $source ); ?>">
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ?: home_url( '/' ) ); ?>">
		<input type="hidden" name="rm_t" value="<?php echo (int) time(); ?>">
		<?php wp_nonce_field( 'ricoman_lead', 'ricoman_lead_nonce' ); ?>

		<?php if ( $atts['title'] ) : ?>
			<p class="ricoman-lead-title"><?php echo esc_html( $atts['title'] ); ?></p>
		<?php endif; ?>

		<!-- Honeypot: hidden from humans, tempting to bots. -->
		<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px"><label>Website<input type="text" name="ricoman_hp" tabindex="-1" autocomplete="off"></label></div>

		<div class="ricoman-field-row">
			<label>
				<span><?php esc_html_e( 'Name', 'ricoman' ); ?> *</span>
				<input type="text" name="lead_name" required>
			</label>
			<label>
				<span><?php esc_html_e( 'Company', 'ricoman' ); ?></span>
				<input type="text" name="lead_company">
			</label>
		</div>
		<div class="ricoman-field-row">
			<label>
				<span><?php esc_html_e( 'Email', 'ricoman' ); ?> *</span>
				<input type="email" name="lead_email" required>
			</label>
			<label>
				<span><?php esc_html_e( 'Phone', 'ricoman' ); ?></span>
				<input type="tel" name="lead_phone">
			</label>
		</div>
		<label>
			<span><?php esc_html_e( 'I am a…', 'ricoman' ); ?></span>
			<select name="lead_role">
				<option value=""><?php esc_html_e( 'Please choose', 'ricoman' ); ?></option>
				<option><?php esc_html_e( 'Architect', 'ricoman' ); ?></option>
				<option><?php esc_html_e( 'Interior designer', 'ricoman' ); ?></option>
				<option><?php esc_html_e( 'Design &amp; build', 'ricoman' ); ?></option>
				<option><?php esc_html_e( 'Electrical contractor', 'ricoman' ); ?></option>
				<option><?php esc_html_e( 'Other', 'ricoman' ); ?></option>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'Tell us about your project', 'ricoman' ); ?></span>
			<textarea name="lead_message" rows="4"></textarea>
		</label>

		<?php echo ricoman_turnstile_widget(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<button type="submit" class="ricoman-lead-submit"><?php esc_html_e( 'Send enquiry', 'ricoman' ); ?></button>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'ricoman_lead_form', 'ricoman_lead_form' );

/**
 * Native Contact page: company details (address / phone / email, reused from the
 * footer settings) beside the enquiry form. Mapped to the `contact` slug by
 * acf-pages.php so the Elementor contact page renders natively.
 */
function ricoman_contact_page() {
	$opt     = function ( $k, $d = '' ) { return function_exists( 'ricoman_opt' ) ? ricoman_opt( $k, $d ) : $d; };
	$address = (string) $opt( 'foot_address', "Metroplex Business Park,\n520, Broadway, M50 2UE\nManchester, UK." );
	$phone   = (string) $opt( 'foot_phone', '0161 451 5913' );
	$email   = (string) $opt( 'foot_email', 'sales@ricoman.com' );
	$telhref = preg_replace( '/[^0-9+]/', '', $phone );

	$details = '<div class="rm-contact-info">'
		. '<h2 class="rm-shead">Get in touch</h2>'
		. '<p class="rm-cfg-desc">Tell us about your project and our lighting team will be in touch within one working day.</p>'
		. '<ul class="rm-contact-list">';
	if ( $address ) {
		$details .= '<li><span class="rm-contact-lbl">Visit</span><span>' . nl2br( esc_html( $address ) ) . '</span></li>';
	}
	if ( $phone ) {
		$details .= '<li><span class="rm-contact-lbl">Call</span><a href="tel:' . esc_attr( $telhref ) . '">' . esc_html( $phone ) . '</a></li>';
	}
	if ( $email ) {
		$details .= '<li><span class="rm-contact-lbl">Email</span><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></li>';
	}
	$details .= '</ul></div>';

	$form = '<div class="rm-contact-form">' . ricoman_lead_form( array( 'title' => '', 'source' => 'Contact page' ) ) . '</div>';

	$map = '';
	if ( $address ) {
		$q   = rawurlencode( 'Ricoman Lighting, ' . preg_replace( '/\s+/', ' ', $address ) );
		$map = '<div class="rm-contact-map"><iframe title="Map to Ricoman" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=' . esc_attr( $q ) . '&output=embed"></iframe></div>';
	}

	return '<div class="rm-section rm-contact-head"><div class="rm-pp-wrap"><p class="rm-eyebrow">Contact</p><h1 class="rm-apage-title">Talk to our lighting team</h1></div></div>'
		. '<div class="rm-section"><div class="rm-pp-wrap"><div class="rm-contact-grid">' . $details . $form . '</div></div></div>'
		. ( $map ? '<div class="rm-section rm-contact-mapwrap"><div class="rm-pp-wrap">' . $map . '</div></div>' : '' );
}
add_shortcode( 'ricoman_contact', 'ricoman_contact_page' );

/**
 * Handle the submitted lead.
 */
function ricoman_handle_lead() {
	$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );

	// Nonce + honeypot checks.
	if (
		! isset( $_POST['ricoman_lead_nonce'] ) ||
		! wp_verify_nonce( sanitize_key( $_POST['ricoman_lead_nonce'] ), 'ricoman_lead' ) ||
		! empty( $_POST['ricoman_hp'] )
	) {
		wp_safe_redirect( add_query_arg( 'lead', 'error', $redirect ) );
		exit;
	}

	$name    = isset( $_POST['lead_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_name'] ) ) : '';
	$email   = isset( $_POST['lead_email'] ) ? sanitize_email( wp_unslash( $_POST['lead_email'] ) ) : '';
	$company = isset( $_POST['lead_company'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_company'] ) ) : '';
	$phone   = isset( $_POST['lead_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_phone'] ) ) : '';
	$role    = isset( $_POST['lead_role'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_role'] ) ) : '';
	$message = isset( $_POST['lead_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lead_message'] ) ) : '';
	$source  = isset( $_POST['lead_source'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_source'] ) ) : '';

	if ( '' === $name || ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'lead', 'error', $redirect ) );
		exit;
	}

	// Spam screen (rate limit / time-trap / link-stuffing). Silently accept so we
	// don't train bots, but store nothing and fire no integrations.
	$ts = isset( $_POST['rm_t'] ) ? (int) $_POST['rm_t'] : 0;
	if ( ricoman_lead_is_spam( array( 'ts' => $ts, 'fields' => array( $name, $company, $message ), 'turnstile' => true ) ) ) {
		wp_safe_redirect( add_query_arg( 'lead', 'sent', $redirect ) );
		exit;
	}

	// Optional "My Project" specification list (JSON from the browser).
	$project_items = ricoman_parse_project_items( isset( $_POST['project_items'] ) ? wp_unslash( $_POST['project_items'] ) : '' );

	$data = array(
		'name'      => $name,
		'email'     => $email,
		'company'   => $company,
		'phone'     => $phone,
		'role'      => $role,
		'message'   => $message,
		'source'    => $source,
		'items'     => $project_items,
		'submitted' => current_time( 'mysql' ),
	);

	// Lead type from the source (project list vs general enquiry).
	$lead_type = ( false !== stripos( $source, 'project' ) ) ? __( 'Project enquiry', 'ricoman' ) : __( 'Enquiry', 'ricoman' );
	$attr      = function_exists( 'ricoman_lead_attribution' ) ? ricoman_lead_attribution() : array();

	// 1. Store as a private `lead` post.
	$lead_id = wp_insert_post(
		array(
			'post_type'   => 'lead',
			'post_status' => 'private',
			'post_title'  => sprintf( '%s — %s', $name, $company ? $company : $role ),
			'meta_input'  => array_merge( array(
				'_lead_name'    => $name,
				'_lead_email'   => $email,
				'_lead_company' => $company,
				'_lead_phone'   => $phone,
				'_lead_role'    => $role,
				'_lead_type'    => $lead_type,
				'_lead_message' => $message,
				'_lead_source'  => $source,
				'_lead_items'   => $project_items,
			), $attr ),
		)
	);

	// 2. Email the site admin.
	$admin = get_option( 'admin_email' );
	$body  = sprintf(
		"New enquiry via %s\n\nName: %s\nCompany: %s\nEmail: %s\nPhone: %s\nRole: %s\nSource: %s\n\nMessage:\n%s\n",
		get_bloginfo( 'name' ),
		$name,
		$company,
		$email,
		$phone,
		$role,
		$source,
		$message
	);
	if ( '' !== $project_items ) {
		$body .= "\nProject list:\n" . $project_items . "\n";
	}
	wp_mail(
		$admin,
		sprintf( '[%s] New lead: %s', get_bloginfo( 'name' ), $name ),
		$body,
		array( 'Reply-To: ' . $name . ' <' . $email . '>' )
	);

	// Confirmation to the person who enquired.
	ricoman_send_lead_confirmation( $email, $name, array( 'reply_to' => $admin ) );

	/**
	 * Fires after a lead is captured. The Sheets sync below hooks here; other
	 * integrations (CRM, Slack…) can too.
	 *
	 * @param array $data    Lead data.
	 * @param int   $lead_id Stored lead post ID (0 on failure).
	 */
	do_action( 'ricoman_lead_captured', $data, is_wp_error( $lead_id ) ? 0 : $lead_id );

	wp_safe_redirect( add_query_arg( 'lead', 'sent', $redirect ) );
	exit;
}
add_action( 'admin_post_nopriv_ricoman_lead', 'ricoman_handle_lead' );
add_action( 'admin_post_ricoman_lead', 'ricoman_handle_lead' );

/**
 * Push the lead to a Google Sheets webhook (Apps Script Web App).
 *
 * @param array $data Lead data.
 */
function ricoman_lead_to_sheets( $data ) {
	$webhook = defined( 'RICOMAN_SHEETS_WEBHOOK' ) ? RICOMAN_SHEETS_WEBHOOK : get_option( 'ricoman_sheets_webhook' );
	if ( empty( $webhook ) ) {
		return;
	}

	wp_remote_post(
		$webhook,
		array(
			'timeout'  => 8,
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( $data ),
		)
	);
}
add_action( 'ricoman_lead_captured', 'ricoman_lead_to_sheets', 10, 1 );

/**
 * Register the webhook URL setting under Settings → General.
 */
function ricoman_register_settings() {
	register_setting(
		'general',
		'ricoman_sheets_webhook',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		)
	);

	add_settings_field(
		'ricoman_sheets_webhook',
		__( 'Leads webhook URL', 'ricoman' ),
		function () {
			$val = get_option( 'ricoman_sheets_webhook', '' );
			echo '<input type="url" name="ricoman_sheets_webhook" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="https://script.google.com/macros/s/…/exec">';
			echo '<p class="description">' . esc_html__( 'Google Apps Script Web App URL that appends leads to a Sheet. Leave blank to disable.', 'ricoman' ) . '</p>';
		},
		'general'
	);
}
add_action( 'admin_init', 'ricoman_register_settings' );

/*
 * The Leads admin list (columns, inline editing, filters, dashboard, CSV
 * import/export) lives in inc/leads-admin.php — the single source of truth so the
 * captured fields and the editable CRM fields stay in one place.
 */

/* ============================================================ Newsletter ====
 * A low-friction, email-only capture point (footer + [ricoman_newsletter]).
 * Signups are logged as "Newsletter" leads so they flow into the same tracker,
 * CRM dashboard and Sheets sync as every other lead. AJAX, nonce-protected,
 * spam-screened, and de-duplicated per email.
 * ------------------------------------------------------------------------- */

/**
 * Render the newsletter signup form.
 *
 * @param array $atts [ title, sub, source, compact ].
 * @return string
 */
function ricoman_newsletter_form( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'title'   => __( 'Lighting insights, now and then', 'ricoman' ),
			'sub'     => __( 'Product launches, project stories and specifier know-how. No spam — unsubscribe anytime.', 'ricoman' ),
			'source'  => '',
			'compact' => '0',
		),
		$atts,
		'ricoman_newsletter'
	);
	$source = $atts['source'] ? $atts['source'] : ( is_singular() ? get_the_title() : get_bloginfo( 'name' ) );
	$nonce  = wp_create_nonce( 'rm_newsletter' );
	$ajax   = esc_url( admin_url( 'admin-ajax.php' ) );
	$cls    = '1' === (string) $atts['compact'] ? ' rm-news--compact' : '';

	ob_start();
	?>
	<div class="rm-news<?php echo esc_attr( $cls ); ?>">
		<?php if ( $atts['title'] ) : ?><h2 class="rm-news-h"><?php echo esc_html( $atts['title'] ); ?></h2><?php endif; ?>
		<?php if ( $atts['sub'] ) : ?><p class="rm-news-sub"><?php echo esc_html( $atts['sub'] ); ?></p><?php endif; ?>
		<form class="rm-news-form" data-ajax="<?php echo $ajax; // phpcs:ignore WordPress.Security.EscapeOutput ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-source="<?php echo esc_attr( $source ); ?>" data-ts="<?php echo (int) time(); ?>">
			<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px"><label>Website<input type="text" name="rm_hp" tabindex="-1" autocomplete="off"></label></div>
			<label class="screen-reader-text" for="rm-news-email-<?php echo (int) get_the_ID(); ?>"><?php esc_html_e( 'Email address', 'ricoman' ); ?></label>
			<input type="email" id="rm-news-email-<?php echo (int) get_the_ID(); ?>" name="email" placeholder="<?php esc_attr_e( 'you@company.com', 'ricoman' ); ?>" required>
			<button type="submit" class="btn btn-solid rm-news-go"><?php esc_html_e( 'Subscribe', 'ricoman' ); ?></button>
			<p class="rm-news-msg" role="status" hidden></p>
		</form>
	</div>
	<?php
	ricoman_newsletter_script();
	return (string) ob_get_clean();
}
add_shortcode( 'ricoman_newsletter', 'ricoman_newsletter_form' );

/** Print the tiny vanilla-JS handler once per page (covers footer + shortcode). */
function ricoman_newsletter_script() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<script>
	(function(){
		document.addEventListener('submit', function(e){
			var f = e.target.closest && e.target.closest('.rm-news-form');
			if (!f) return;
			e.preventDefault();
			if (f.querySelector('[name=rm_hp]') && f.querySelector('[name=rm_hp]').value) return;
			var msg = f.querySelector('.rm-news-msg'), btn = f.querySelector('button');
			var body = new URLSearchParams();
			body.set('action','rm_newsletter');
			body.set('nonce', f.dataset.nonce);
			body.set('source', f.dataset.source || '');
			body.set('ts', f.dataset.ts || '');
			body.set('email', (f.querySelector('[name=email]')||{}).value || '');
			if (btn) btn.disabled = true;
			fetch(f.dataset.ajax, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString()})
				.then(function(r){return r.json();})
				.then(function(r){
					if (btn) btn.disabled = false;
					if (msg){ msg.hidden=false; msg.textContent = (r && r.data && r.data.msg) ? r.data.msg : (r && r.success ? 'Thanks — you’re subscribed.' : 'Sorry, please try again.'); }
					if (r && r.success){ f.reset(); }
				})
				.catch(function(){ if(btn)btn.disabled=false; if(msg){msg.hidden=false; msg.textContent='Sorry, please try again.';} });
		});
	})();
	</script>
	<?php
}

/** AJAX: capture a newsletter signup as a lead. */
function ricoman_newsletter_capture() {
	if ( ! check_ajax_referer( 'rm_newsletter', 'nonce', false ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please refresh and try again.', 'ricoman' ) ) );
	}
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$src   = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
	$ts    = isset( $_POST['ts'] ) ? (int) $_POST['ts'] : 0;
	$hp    = isset( $_POST['rm_hp'] ) ? (string) wp_unslash( $_POST['rm_hp'] ) : '';
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please enter a valid email address.', 'ricoman' ) ) );
	}
	// Honeypot + shared spam screen — silently acknowledge, store nothing.
	if ( '' !== $hp || ricoman_lead_is_spam( array( 'ts' => $ts, 'fields' => array( $email ) ) ) ) {
		wp_send_json_success( array( 'msg' => __( 'Thanks — you’re subscribed.', 'ricoman' ) ) );
	}

	$source = $src ? 'Newsletter · ' . $src : 'Newsletter';

	// De-dupe: don't log the same email twice as a newsletter lead.
	$existing = get_posts( array(
		'post_type'      => 'lead',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => '_lead_email', 'value' => $email ),
			array( 'key' => '_lead_type', 'value' => 'Newsletter' ),
		),
	) );
	if ( $existing ) {
		wp_send_json_success( array( 'msg' => __( 'You’re already on the list — thank you.', 'ricoman' ) ) );
	}

	$data = array(
		'name'      => '',
		'email'     => $email,
		'company'   => '',
		'phone'     => '',
		'role'      => '',
		'message'   => '',
		'source'    => $source,
		'items'     => '',
		'submitted' => current_time( 'mysql' ),
	);
	$lead_id = wp_insert_post( array(
		'post_type'   => 'lead',
		'post_status' => 'private',
		'post_title'  => sprintf( '%s — %s', $email, __( 'Newsletter', 'ricoman' ) ),
		'meta_input'  => array_merge( array(
			'_lead_email'  => $email,
			'_lead_type'   => __( 'Newsletter', 'ricoman' ),
			'_lead_source' => $source,
		), function_exists( 'ricoman_lead_attribution' ) ? ricoman_lead_attribution() : array() ),
	) );

	/** Same hook the other capture points fire (Sheets sync, CRM…). */
	do_action( 'ricoman_lead_captured', $data, is_wp_error( $lead_id ) ? 0 : $lead_id );

	wp_send_json_success( array( 'msg' => __( 'Thanks — you’re subscribed.', 'ricoman' ) ) );
}
add_action( 'wp_ajax_nopriv_rm_newsletter', 'ricoman_newsletter_capture' );
add_action( 'wp_ajax_rm_newsletter', 'ricoman_newsletter_capture' );

/* ====================================================== Request a callback ===
 * A two-field (name + phone) capture point for visitors who'd rather be called
 * than fill in the full enquiry form. Logs a "Callback request" lead, emails the
 * team, and flows into the same tracker / CRM / Sheets pipeline.
 * ------------------------------------------------------------------------- */

/**
 * Render the callback request form. Drop in with [ricoman_callback].
 *
 * @param array $atts [ title, sub, source ].
 * @return string
 */
function ricoman_callback_form( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'title'  => __( 'Prefer a call back?', 'ricoman' ),
			'sub'    => __( 'Leave your number and our lighting team will call you back — usually the same working day.', 'ricoman' ),
			'source' => '',
		),
		$atts,
		'ricoman_callback'
	);
	$source = $atts['source'] ? $atts['source'] : ( is_singular() ? get_the_title() : get_bloginfo( 'name' ) );
	$nonce  = wp_create_nonce( 'rm_callback' );
	$ajax   = esc_url( admin_url( 'admin-ajax.php' ) );
	$id     = 'rm-cb-' . wp_rand( 1000, 9999 );

	ob_start();
	?>
	<div class="rm-callback">
		<form class="ricoman-lead-form rm-callback-form" data-ajax="<?php echo $ajax; // phpcs:ignore WordPress.Security.EscapeOutput ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-source="<?php echo esc_attr( $source ); ?>" data-ts="<?php echo (int) time(); ?>">
			<?php if ( $atts['title'] ) : ?><p class="ricoman-lead-title"><?php echo esc_html( $atts['title'] ); ?></p><?php endif; ?>
			<?php if ( $atts['sub'] ) : ?><p class="rm-callback-sub"><?php echo esc_html( $atts['sub'] ); ?></p><?php endif; ?>
			<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px"><label>Website<input type="text" name="rm_hp" tabindex="-1" autocomplete="off"></label></div>
			<div class="ricoman-field-row">
				<label><span><?php esc_html_e( 'Name', 'ricoman' ); ?> *</span><input type="text" name="name" required></label>
				<label><span><?php esc_html_e( 'Phone', 'ricoman' ); ?> *</span><input type="tel" name="phone" required></label>
			</div>
			<label><span><?php esc_html_e( 'Best time to call', 'ricoman' ); ?></span>
				<select name="when">
					<option value=""><?php esc_html_e( 'Anytime', 'ricoman' ); ?></option>
					<option><?php esc_html_e( 'Morning', 'ricoman' ); ?></option>
					<option><?php esc_html_e( 'Afternoon', 'ricoman' ); ?></option>
				</select>
			</label>
			<button type="submit" class="ricoman-lead-submit rm-cb-go"><?php esc_html_e( 'Request a callback', 'ricoman' ); ?></button>
			<p class="rm-news-msg" role="status" hidden></p>
		</form>
	</div>
	<?php
	ricoman_callback_script();
	return (string) ob_get_clean();
}
add_shortcode( 'ricoman_callback', 'ricoman_callback_form' );

/** Print the callback AJAX handler once per page. */
function ricoman_callback_script() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<script>
	(function(){
		document.addEventListener('submit', function(e){
			var f = e.target.closest && e.target.closest('.rm-callback-form');
			if (!f) return;
			e.preventDefault();
			if (f.querySelector('[name=rm_hp]') && f.querySelector('[name=rm_hp]').value) return;
			var msg = f.querySelector('.rm-news-msg'), btn = f.querySelector('button');
			var g = function(n){ return (f.querySelector('[name='+n+']')||{}).value || ''; };
			var body = new URLSearchParams();
			body.set('action','rm_callback');
			body.set('nonce', f.dataset.nonce);
			body.set('source', f.dataset.source || '');
			body.set('ts', f.dataset.ts || '');
			body.set('name', g('name')); body.set('phone', g('phone')); body.set('when', g('when'));
			if (btn) btn.disabled = true;
			fetch(f.dataset.ajax, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString()})
				.then(function(r){return r.json();})
				.then(function(r){
					if (btn) btn.disabled = false;
					if (msg){ msg.hidden=false; msg.textContent = (r && r.data && r.data.msg) ? r.data.msg : (r && r.success ? 'Thanks — we’ll call you back shortly.' : 'Sorry, please try again.'); }
					if (r && r.success){ f.reset(); }
				})
				.catch(function(){ if(btn)btn.disabled=false; if(msg){msg.hidden=false; msg.textContent='Sorry, please try again.';} });
		});
	})();
	</script>
	<?php
}

/** AJAX: capture a callback request as a lead + email the team. */
function ricoman_callback_capture() {
	if ( ! check_ajax_referer( 'rm_callback', 'nonce', false ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please refresh and try again.', 'ricoman' ) ) );
	}
	$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$when  = isset( $_POST['when'] ) ? sanitize_text_field( wp_unslash( $_POST['when'] ) ) : '';
	$src   = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
	$ts    = isset( $_POST['ts'] ) ? (int) $_POST['ts'] : 0;
	$hp    = isset( $_POST['rm_hp'] ) ? (string) wp_unslash( $_POST['rm_hp'] ) : '';

	// Need a name and a plausible phone number (>=7 digits).
	if ( '' === $name || preg_match_all( '/\d/', $phone ) < 7 ) {
		wp_send_json_error( array( 'msg' => __( 'Please enter your name and a valid phone number.', 'ricoman' ) ) );
	}
	// Honeypot + shared spam screen — silently acknowledge, store nothing.
	if ( '' !== $hp || ricoman_lead_is_spam( array( 'ts' => $ts, 'fields' => array( $name ) ) ) ) {
		wp_send_json_success( array( 'msg' => __( 'Thanks — we’ll call you back shortly.', 'ricoman' ) ) );
	}

	$source = $src ? 'Callback · ' . $src : 'Callback request';
	$data   = array(
		'name'      => $name,
		'email'     => '',
		'company'   => '',
		'phone'     => $phone,
		'role'      => '',
		'message'   => $when ? sprintf( 'Best time to call: %s', $when ) : '',
		'source'    => $source,
		'items'     => '',
		'submitted' => current_time( 'mysql' ),
	);
	$lead_id = wp_insert_post( array(
		'post_type'   => 'lead',
		'post_status' => 'private',
		'post_title'  => sprintf( '%s — %s', $name, __( 'Callback', 'ricoman' ) ),
		'meta_input'  => array_merge( array(
			'_lead_name'    => $name,
			'_lead_phone'   => $phone,
			'_lead_type'    => __( 'Callback request', 'ricoman' ),
			'_lead_message' => $data['message'],
			'_lead_source'  => $source,
		), function_exists( 'ricoman_lead_attribution' ) ? ricoman_lead_attribution() : array() ),
	) );

	// Notify the team — a callback is time-sensitive.
	wp_mail(
		get_option( 'admin_email' ),
		sprintf( '[%s] Callback request: %s', get_bloginfo( 'name' ), $name ),
		sprintf( "A visitor has requested a callback.\n\nName: %s\nPhone: %s\nBest time: %s\nSource: %s\n", $name, $phone, $when ? $when : __( 'Anytime', 'ricoman' ), $source )
	);

	/** Same hook the other capture points fire (Sheets sync, CRM…). */
	do_action( 'ricoman_lead_captured', $data, is_wp_error( $lead_id ) ? 0 : $lead_id );

	wp_send_json_success( array( 'msg' => __( 'Thanks — we’ll call you back shortly.', 'ricoman' ) ) );
}
add_action( 'wp_ajax_nopriv_rm_callback', 'ricoman_callback_capture' );
add_action( 'wp_ajax_rm_callback', 'ricoman_callback_capture' );
