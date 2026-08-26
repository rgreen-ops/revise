<?php
/**
 * Download lead-gate.
 *
 * Turns document downloads (datasheets, BIM, IES/LDT, brochures, project packs)
 * into lead-capture points: a logged-in user downloads instantly; everyone else
 * gets a short popup — name + email + customer type, or sign in / create an
 * account — before the file opens. Once captured we set a cookie so the visitor
 * isn't asked again (per the "once per visitor" choice). Captured details flow
 * into the same `lead` system + Google-Sheets sync used by the enquiry form.
 *
 * The gating itself is done client-side (assets/js/lead-gate.js) so it covers
 * every download link without touching each template.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared customer-type list — used by the gate, signup and enquiry form. */
function ricoman_customer_types() {
	return apply_filters( 'ricoman_customer_types', array(
		'Architect',
		'Specifier / Consultant',
		'Lighting Designer',
		'Interior Designer',
		'Contractor',
		'Electrician / Installer',
		'Wholesaler / Distributor',
		'End user / Other',
	) );
}

/**
 * First-touch traffic attribution for the current visitor, from the rm_attr cookie
 * (set by lead-gate.js) + the request. Returns source (Organic / Direct / PPC /
 * Social / Referral / Campaign), the page they acted on, the referrer and any UTM.
 */
function ricoman_lead_attribution() {
	$raw = isset( $_COOKIE['rm_attr'] ) ? wp_unslash( $_COOKIE['rm_attr'] ) : '';
	$a   = $raw ? json_decode( $raw, true ) : array();
	if ( ! is_array( $a ) ) {
		$a = array();
	}
	$ref  = isset( $a['ref'] ) ? (string) $a['ref'] : '';
	$us   = isset( $a['us'] ) ? sanitize_text_field( $a['us'] ) : '';
	$um   = strtolower( isset( $a['um'] ) ? sanitize_text_field( $a['um'] ) : '' );
	$uc   = isset( $a['uc'] ) ? sanitize_text_field( $a['uc'] ) : '';
	$paid = ! empty( $a['g'] ) || ! empty( $a['f'] ) || ! empty( $a['msc'] ) || in_array( $um, array( 'cpc', 'ppc', 'paid', 'paidsearch', 'paid-search', 'paid_social' ), true );
	$rh   = $ref ? strtolower( (string) wp_parse_url( $ref, PHP_URL_HOST ) ) : '';
	$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

	if ( $paid ) {
		$source = 'PPC';
	} elseif ( $us || $um ) {
		$source = 'Campaign' . ( $us ? ' · ' . $us : '' );
	} elseif ( '' === $rh ) {
		$source = 'Direct';
	} elseif ( preg_match( '/google|bing|yahoo|duckduckgo|ecosia|baidu|yandex|search/', $rh ) ) {
		$source = 'Organic';
	} elseif ( preg_match( '/facebook|fb\.|instagram|linkedin|twitter|t\.co|x\.com|pinterest|youtube|tiktok|reddit/', $rh ) ) {
		$source = 'Social';
	} elseif ( $home && false !== strpos( $rh, $home ) ) {
		$source = 'Direct';
	} else {
		$source = 'Referral';
	}

	$page = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	if ( '' === $page && isset( $a['land'] ) ) {
		$page = esc_url_raw( $a['land'] );
	}
	return array(
		'_lead_attr_source' => $source,
		'_lead_page'        => $page,
		'_lead_referrer'    => esc_url_raw( $ref ),
		'_lead_utm'         => trim( $us . ( $um ? ' / ' . $um : '' ) . ( $uc ? ' / ' . $uc : '' ), ' /' ),
		'_lead_landing'     => isset( $a['land'] ) ? esc_url_raw( $a['land'] ) : '',
	);
}

/** Enqueue the gate script + state for non-logged-in visitors. */
add_action( 'wp_enqueue_scripts', function () {
	$src = get_theme_file_path( 'assets/js/lead-gate.js' );
	wp_enqueue_script(
		'ricoman-lead-gate',
		get_theme_file_uri( 'assets/js/lead-gate.js' ),
		array(),
		file_exists( $src ) ? (string) filemtime( $src ) : ( defined( 'RICOMAN_VERSION' ) ? RICOMAN_VERSION : '1' ),
		true
	);
	$u = wp_get_current_user();
	wp_localize_script( 'ricoman-lead-gate', 'rmGate', array(
		'in'       => is_user_logged_in() ? 1 : 0,
		'ajax'     => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'rm_gate' ),
		'login'    => wp_login_url(),
		'register' => wp_registration_url(),
		'name'     => $u && $u->exists() ? $u->display_name : '',
		'email'    => $u && $u->exists() ? $u->user_email : '',
	) );
} );

/** The download-gate + BIM-request modals. */
add_action( 'wp_footer', function () {
	// BIM / Revit request modal — shown to everyone (even logged-in users, since
	// BIM files are made to order). Pre-filled for signed-in users via JS.
	?>
	<div class="rm-gate rm-bimgate" hidden aria-hidden="true">
		<div class="rm-gate-box" role="dialog" aria-modal="true" aria-labelledby="rm-bim-h">
			<button type="button" class="rm-gate-x" aria-label="Close">&times;</button>
			<h3 id="rm-bim-h" class="rm-gate-title"><?php esc_html_e( 'Request a BIM / Revit file', 'ricoman' ); ?></h3>
			<p class="rm-gate-sub"><?php esc_html_e( 'BIM files are produced on request by our lighting team. Leave your details and we’ll email the file for this product to you.', 'ricoman' ); ?></p>
			<form class="rm-bim-form">
				<input type="hidden" name="product" value="">
				<label><span><?php esc_html_e( 'Name', 'ricoman' ); ?> *</span><input type="text" name="name" required></label>
				<label><span><?php esc_html_e( 'Email', 'ricoman' ); ?> *</span><input type="email" name="email" required></label>
				<label><span><?php esc_html_e( 'Company', 'ricoman' ); ?></span><input type="text" name="company"></label>
				<button type="submit" class="btn btn-solid rm-bim-go"><?php esc_html_e( 'Request BIM file', 'ricoman' ); ?></button>
				<p class="rm-gate-msg" hidden></p>
			</form>
		</div>
	</div>
	<?php
	if ( is_user_logged_in() ) {
		return;
	}
	// Auth prompt — shown when a guest tries to add a product to a project.
	?>
	<div class="rm-gate rm-authgate" hidden aria-hidden="true">
		<div class="rm-gate-box" role="dialog" aria-modal="true" aria-labelledby="rm-auth-h">
			<button type="button" class="rm-gate-x" aria-label="Close">&times;</button>
			<h3 id="rm-auth-h" class="rm-gate-title"><?php esc_html_e( 'Save it to your project', 'ricoman' ); ?></h3>
			<p class="rm-gate-sub"><?php esc_html_e( 'Sign in or create a free account to add products to a project, keep multiple projects and download project packs. We’ll add this product as soon as you’re in.', 'ricoman' ); ?></p>
			<div class="rm-auth-acts">
				<a class="btn btn-solid rm-auth-login" href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Sign in', 'ricoman' ); ?></a>
				<a class="btn btn-line-d rm-auth-reg" href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Create an account', 'ricoman' ); ?></a>
			</div>
		</div>
	</div>
	<?php
	$opts = '<option value="">' . esc_html__( 'Select customer type…', 'ricoman' ) . '</option>';
	foreach ( ricoman_customer_types() as $t ) {
		$opts .= '<option value="' . esc_attr( $t ) . '">' . esc_html( $t ) . '</option>';
	}
	?>
	<div class="rm-gate rm-dlgate" hidden aria-hidden="true">
		<div class="rm-gate-box" role="dialog" aria-modal="true" aria-labelledby="rm-gate-h">
			<button type="button" class="rm-gate-x" aria-label="Close">&times;</button>
			<h3 id="rm-gate-h" class="rm-gate-title"><?php esc_html_e( 'Download this resource', 'ricoman' ); ?></h3>
			<p class="rm-gate-sub"><?php esc_html_e( 'Tell us who you are and the file will open. We’ll only use this to help with your projects.', 'ricoman' ); ?></p>
			<form class="rm-gate-form">
				<label><span><?php esc_html_e( 'Name', 'ricoman' ); ?> *</span><input type="text" name="name" required></label>
				<label><span><?php esc_html_e( 'Email', 'ricoman' ); ?> *</span><input type="email" name="email" required></label>
				<label><span><?php esc_html_e( 'I am a…', 'ricoman' ); ?> *</span><select name="ctype" required><?php echo $opts; // phpcs:ignore WordPress.Security.EscapeOutput ?></select></label>
				<label class="rm-gate-optin" style="display:flex;gap:8px;align-items:flex-start;font-weight:400;font-size:.85rem;margin-top:2px"><input type="checkbox" name="optin" value="1" style="width:auto;margin-top:3px"><span><?php esc_html_e( 'Email me occasional new products & lighting guides. You can unsubscribe anytime.', 'ricoman' ); ?></span></label>
				<button type="submit" class="btn btn-solid rm-gate-go"><?php esc_html_e( 'Get the download', 'ricoman' ); ?> &darr;</button>
				<p class="rm-gate-msg" hidden></p>
			</form>
			<p class="rm-gate-alt"><?php esc_html_e( 'Already have an account?', 'ricoman' ); ?>
				<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Sign in', 'ricoman' ); ?></a> ·
				<a href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Create an account', 'ricoman' ); ?></a>
				<?php esc_html_e( '— then you can download without filling this in.', 'ricoman' ); ?>
			</p>
		</div>
	</div>
	<?php
} );

/** AJAX: capture a gate lead, then let the browser proceed to the file. */
function ricoman_gate_capture() {
	if ( ! check_ajax_referer( 'rm_gate', 'nonce', false ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please refresh and try again.', 'ricoman' ) ) );
	}
	$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$type  = isset( $_POST['ctype'] ) ? sanitize_text_field( wp_unslash( $_POST['ctype'] ) ) : '';
	$src   = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : 'Download gate';
	$optin = ( isset( $_POST['optin'] ) && '1' === (string) wp_unslash( $_POST['optin'] ) ) ? '1' : ''; // newsletter opt-in checkbox.
	if ( '' === $name || ! is_email( $email ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please enter your name and a valid email.', 'ricoman' ) ) );
	}
	// Spam screen — silently let the download proceed but log nothing.
	if ( function_exists( 'ricoman_lead_is_spam' ) && ricoman_lead_is_spam( array( 'fields' => array( $name ) ) ) ) {
		wp_send_json_success();
	}

	$data = array(
		'name'      => $name,
		'email'     => $email,
		'company'   => '',
		'phone'     => '',
		'role'      => $type,
		'message'   => '',
		'source'    => $src,
		'items'     => '',
		'submitted' => current_time( 'mysql' ),
	);
	$lead_id = wp_insert_post( array(
		'post_type'   => 'lead',
		'post_status' => 'private',
		'post_title'  => sprintf( '%s — %s', $name, $type ? $type : __( 'Download', 'ricoman' ) ),
		'meta_input'  => array_merge( array(
			'_lead_name'   => $name,
			'_lead_email'  => $email,
			'_lead_role'   => $type,
			'_lead_type'   => __( 'Download', 'ricoman' ),
			'_lead_source' => $src,
			'_lead_optin'  => $optin,
		), ricoman_lead_attribution() ),
	) );

	/** Reuse the same hook the enquiry form fires (Sheets sync, CRM, etc.). */
	do_action( 'ricoman_lead_captured', $data, is_wp_error( $lead_id ) ? 0 : $lead_id );

	// Remember this visitor so they're not gated again (once-per-visitor).
	setcookie( 'rm_dl_gate', '1', time() + 30 * DAY_IN_SECONDS, defined( 'COOKIEPATH' ) ? COOKIEPATH : '/', defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '' );

	wp_send_json_success();
}
add_action( 'wp_ajax_nopriv_rm_lead_gate', 'ricoman_gate_capture' );
add_action( 'wp_ajax_rm_lead_gate', 'ricoman_gate_capture' );

/** Where BIM requests are sent. */
function ricoman_bim_inbox() {
	return apply_filters( 'ricoman_bim_inbox', 'lightingdesign@ricoman.com' );
}

/**
 * AJAX: a BIM / Revit file request. BIM files are made to order, so this logs a
 * lead, emails the requester a thank-you, and emails the lighting team the
 * product + the requester's details.
 */
function ricoman_bim_request() {
	if ( ! check_ajax_referer( 'rm_gate', 'nonce', false ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please refresh and try again.', 'ricoman' ) ) );
	}
	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$company = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$product = isset( $_POST['product'] ) ? sanitize_text_field( wp_unslash( $_POST['product'] ) ) : '';
	if ( '' === $name || ! is_email( $email ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please enter your name and a valid email.', 'ricoman' ) ) );
	}
	// Spam screen — silently acknowledge but log/notify nothing.
	if ( function_exists( 'ricoman_lead_is_spam' ) && ricoman_lead_is_spam( array( 'fields' => array( $name, $company ) ) ) ) {
		wp_send_json_success();
	}

	$site = get_bloginfo( 'name' );

	// 1. Log it as a lead (shows in the Leads back end).
	$data = array(
		'name'      => $name,
		'email'     => $email,
		'company'   => $company,
		'phone'     => '',
		'role'      => '',
		'message'   => 'BIM / Revit file requested',
		'source'    => 'BIM request',
		'items'     => $product,
		'submitted' => current_time( 'mysql' ),
	);
	$lead_id = wp_insert_post( array(
		'post_type'   => 'lead',
		'post_status' => 'private',
		'post_title'  => sprintf( '%s — BIM: %s', $name, $product ? $product : __( 'product', 'ricoman' ) ),
		'meta_input'  => array_merge( array(
			'_lead_name'    => $name,
			'_lead_email'   => $email,
			'_lead_company' => $company,
			'_lead_type'    => 'BIM request',
			'_lead_product' => $product,
			'_lead_source'  => 'BIM request',
		), ricoman_lead_attribution() ),
	) );

	// 2. Thank-you to the requester.
	wp_mail(
		$email,
		sprintf( __( 'Your BIM file request — %s', 'ricoman' ), $site ),
		sprintf(
			"Hi %s,\n\nThanks for requesting the BIM / Revit file for %s.\n\nOur lighting team produces these on request and will email it to you shortly. If you need anything else in the meantime, just reply to this email.\n\n— %s",
			$name,
			$product ? $product : __( 'your selected product', 'ricoman' ),
			$site
		),
		array( 'Reply-To: ' . ricoman_bim_inbox() )
	);

	// 3. Notify the lighting team with the product + requester details.
	wp_mail(
		ricoman_bim_inbox(),
		sprintf( '[%s] BIM request: %s', $site, $product ? $product : __( 'product', 'ricoman' ) ),
		sprintf(
			"New BIM / Revit file request.\n\nProduct: %s\n\nName: %s\nEmail: %s\nCompany: %s\n\nLead logged in the back end (#%s).",
			$product,
			$name,
			$email,
			$company,
			is_wp_error( $lead_id ) ? '—' : (int) $lead_id
		),
		array( 'Reply-To: ' . $name . ' <' . $email . '>' )
	);

	/** Same hook the other capture points use (Sheets sync, CRM…). */
	do_action( 'ricoman_lead_captured', $data, is_wp_error( $lead_id ) ? 0 : $lead_id );

	wp_send_json_success();
}
add_action( 'wp_ajax_nopriv_rm_bim_request', 'ricoman_bim_request' );
add_action( 'wp_ajax_rm_bim_request', 'ricoman_bim_request' );
