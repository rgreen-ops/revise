<?php
/**
 * "Tell us about your project" — multi-step enquiry wizard.
 *
 * Shortcode [ricoman_project_wizard] renders a 5-step form (lighting solution →
 * priorities → mounting → extra info + optional file → your details). It submits
 * via AJAX into the SHARED lead pipeline, so every enquiry:
 *   - stores a `lead` post (visible in Ricoman → Leads),
 *   - fires `ricoman_lead_captured` (Sheets sync + CRM auto-fill),
 *   - emails the team, and
 *   - passes the same honeypot + spam screen as the other capture points.
 *
 * Steps 1–3 and the name/email are required (they qualify the enquiry); step 4
 * (extra info + attachment) is optional.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Choice options for the two image-card steps + the priorities checklist. Filterable. */
function ricoman_wizard_config() {
	$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
	return apply_filters( 'ricoman_wizard_config', array(
		'solution'   => array(
			array( 'v' => 'Gym Lighting',    'img' => $img( 'rico-astrowave-banner.webp' ) ),
			array( 'v' => 'Office Lighting', 'img' => $img( 'office2.webp' ) ),
			array( 'v' => 'Other',           'img' => $img( 'rico-soundslikelight.webp' ) ),
		),
		'mounting'   => array(
			array( 'v' => 'Suspended',       'img' => $img( 'rico-betfred-flow.webp' ) ),
			array( 'v' => 'Surface mounted', 'img' => $img( 'rico-acoustic-corridor.webp' ) ),
			array( 'v' => 'Recessed',        'img' => $img( 'ceiling.webp' ) ),
		),
		'priorities' => array(
			'Energy savings', 'Colour changing solutions', 'Dimming options',
			'Creative lighting solutions', 'Functional lighting solutions',
			'Fast lead times', 'Smart lighting solutions', 'Custom solutions',
		),
	) );
}

/** Allowed attachment types (ext => mime) + size cap. */
function ricoman_wizard_mimes() {
	return apply_filters( 'ricoman_wizard_mimes', array(
		'pdf'      => 'application/pdf',
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
	) );
}
function ricoman_wizard_max_bytes() {
	return (int) apply_filters( 'ricoman_wizard_max_bytes', 10 * 1024 * 1024 );
}

/** [ricoman_project_wizard thankyou="/lighting-design/thank-you/"] */
function ricoman_project_wizard_sc( $atts ) {
	$a     = shortcode_atts( array( 'thankyou' => '' ), $atts, 'ricoman_project_wizard' ); // optional success redirect (ad tracking).
	$cfg   = ricoman_wizard_config();
	$nonce = wp_create_nonce( 'rm_wizard' );

	$src = get_theme_file_path( 'assets/js/project-wizard.js' );
	wp_enqueue_script( 'ricoman-wizard', get_theme_file_uri( 'assets/js/project-wizard.js' ), array(), file_exists( $src ) ? (string) filemtime( $src ) : '1', true );

	$card = function ( $group, $item ) {
		return '<label class="rm-wiz-card"><input type="checkbox" name="' . esc_attr( $group ) . '[]" value="' . esc_attr( $item['v'] ) . '">'
			. '<span class="rm-wiz-card-img" style="background-image:url(' . $item['img'] . ')"></span>'
			. '<span class="rm-wiz-card-tick" aria-hidden="true"></span>'
			. '<span class="rm-wiz-card-cap">' . esc_html( $item['v'] ) . '</span></label>';
	};

	$prog = function ( $n ) { return '<p class="rm-wiz-prog">Step ' . (int) $n . ' of 5</p>'; };

	ob_start();
	?>
	<form class="rm-wiz" method="post" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ts="<?php echo (int) time(); ?>" data-redirect="<?php echo esc_url( $a['thankyou'] ); ?>" novalidate>
		<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px"><label>Website<input type="text" name="rm_hp" tabindex="-1" autocomplete="off"></label></div>

		<div class="rm-wiz-step" data-step="1">
			<?php echo $prog( 1 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h2 class="rm-wiz-q">What lighting solution are you looking for?</h2>
			<div class="rm-wiz-cards"><?php foreach ( $cfg['solution'] as $it ) { echo $card( 'solution', $it ); } // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<p class="rm-wiz-err" hidden>Please choose at least one option to continue.</p>
			<div class="rm-wiz-nav"><span></span><button type="button" class="btn btn-solid rm-wiz-next">Next →</button></div>
		</div>

		<div class="rm-wiz-step" data-step="2" hidden>
			<?php echo $prog( 2 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h2 class="rm-wiz-q">What is important to you in this project?</h2>
			<div class="rm-wiz-checks">
				<?php foreach ( $cfg['priorities'] as $p ) : ?>
					<label class="rm-wiz-check"><input type="checkbox" name="priorities[]" value="<?php echo esc_attr( $p ); ?>"><span><?php echo esc_html( $p ); ?></span></label>
				<?php endforeach; ?>
				<label class="rm-wiz-check rm-wiz-check--other"><span>Other:</span><input type="text" name="priorities_other" class="rm-wiz-otherfield" placeholder="Specify…"></label>
			</div>
			<p class="rm-wiz-err" hidden>Please choose at least one option to continue.</p>
			<div class="rm-wiz-nav"><button type="button" class="btn btn-line rm-wiz-back">← Back</button><button type="button" class="btn btn-solid rm-wiz-next">Next →</button></div>
		</div>

		<div class="rm-wiz-step" data-step="3" hidden>
			<?php echo $prog( 3 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h2 class="rm-wiz-q">What mounting options are you looking for?</h2>
			<div class="rm-wiz-cards"><?php foreach ( $cfg['mounting'] as $it ) { echo $card( 'mounting', $it ); } // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<p class="rm-wiz-err" hidden>Please choose at least one option to continue.</p>
			<div class="rm-wiz-nav"><button type="button" class="btn btn-line rm-wiz-back">← Back</button><button type="button" class="btn btn-solid rm-wiz-next">Next →</button></div>
		</div>

		<div class="rm-wiz-step" data-step="4" hidden>
			<?php echo $prog( 4 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h2 class="rm-wiz-q">Additional information</h2>
			<label class="rm-wiz-label">Additional information (optional)
				<textarea name="info" rows="4" class="rm-wiz-field"></textarea>
			</label>
			<label class="rm-wiz-label">Additional attachment (optional)
				<span class="rm-wiz-file">
					<span class="rm-wiz-file-cta">Drag &amp; drop a file here, or <strong>click to browse</strong></span>
					<span class="rm-wiz-file-hint">A floor / layout plan or a reference image — PDF, JPG or PNG (max 10&nbsp;MB).</span>
					<span class="rm-wiz-filename" aria-live="polite"></span>
					<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
				</span>
			</label>
			<div class="rm-wiz-nav"><button type="button" class="btn btn-line rm-wiz-back">← Back</button><button type="button" class="btn btn-solid rm-wiz-next">Next →</button></div>
		</div>

		<div class="rm-wiz-step" data-step="5" hidden>
			<?php echo $prog( 5 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h2 class="rm-wiz-q">Your details</h2>
			<label class="rm-wiz-label">Your name *<input type="text" name="name" class="rm-wiz-field" autocomplete="name"></label>
			<label class="rm-wiz-label">Email *<input type="email" name="email" class="rm-wiz-field" autocomplete="email"></label>
			<p class="rm-wiz-err" hidden>Please enter your name and a valid email address.</p>
			<p class="rm-wiz-submsg" role="status" hidden></p>
			<div class="rm-wiz-nav"><button type="button" class="btn btn-line rm-wiz-back">← Back</button><button type="submit" class="btn btn-solid rm-wiz-submit">Submit →</button></div>
		</div>

		<div class="rm-wiz-done" hidden>
			<span class="rm-wiz-done-tick" aria-hidden="true"></span>
			<h2 class="rm-wiz-q">Your request has been submitted successfully.</h2>
			<p>Thank you for your request — our team will get in touch with you soon.</p>
		</div>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'ricoman_project_wizard', 'ricoman_project_wizard_sc' );

/** AJAX: capture a wizard enquiry as a lead (shared pipeline). */
function ricoman_wizard_capture() {
	if ( ! check_ajax_referer( 'rm_wizard', 'nonce', false ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please refresh the page and try again.', 'ricoman' ) ) );
	}

	$hp = isset( $_POST['rm_hp'] ) ? (string) wp_unslash( $_POST['rm_hp'] ) : '';
	$ts = isset( $_POST['ts'] ) ? (int) $_POST['ts'] : 0;

	$arr = function ( $k ) {
		$v = isset( $_POST[ $k ] ) ? (array) wp_unslash( $_POST[ $k ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$v = array_map( 'sanitize_text_field', $v );
		return array_values( array_filter( array_map( 'trim', $v ) ) );
	};
	$solution   = $arr( 'solution' );
	$priorities = $arr( 'priorities' );
	$mounting   = $arr( 'mounting' );
	$pri_other  = isset( $_POST['priorities_other'] ) ? sanitize_text_field( wp_unslash( $_POST['priorities_other'] ) ) : '';
	$info       = isset( $_POST['info'] ) ? sanitize_textarea_field( wp_unslash( $_POST['info'] ) ) : '';
	$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( '' !== $pri_other ) {
		$priorities[] = 'Other: ' . $pri_other;
	}

	// Required steps: solution, priorities, mounting, name + valid email.
	if ( ! $solution || ! $priorities || ! $mounting || '' === $name || ! is_email( $email ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please complete all the required steps.', 'ricoman' ) ) );
	}

	// Honeypot + shared spam screen — silently acknowledge, store nothing.
	if ( '' !== $hp || ( function_exists( 'ricoman_lead_is_spam' ) && ricoman_lead_is_spam( array( 'ts' => $ts, 'fields' => array( $name, $info ) ) ) ) ) {
		wp_send_json_success( array( 'msg' => __( 'Your request has been submitted successfully.', 'ricoman' ) ) );
	}

	// Optional attachment (validated: type whitelist + size cap).
	$attach_url = '';
	$attach_id  = 0;
	if ( ! empty( $_FILES['attachment']['name'] ) && empty( $_FILES['attachment']['error'] ) ) {
		if ( (int) $_FILES['attachment']['size'] > ricoman_wizard_max_bytes() ) {
			wp_send_json_error( array( 'msg' => __( 'That file is too large — please keep it under 10 MB.', 'ricoman' ) ) );
		}
		$fname = sanitize_file_name( (string) $_FILES['attachment']['name'] );
		$ck    = wp_check_filetype( $fname, ricoman_wizard_mimes() );
		if ( empty( $ck['ext'] ) ) {
			wp_send_json_error( array( 'msg' => __( 'Please upload a PDF, JPG or PNG.', 'ricoman' ) ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_id = media_handle_upload( 'attachment', 0, array(), array( 'test_form' => false, 'mimes' => ricoman_wizard_mimes() ) );
		if ( is_wp_error( $attach_id ) ) {
			wp_send_json_error( array( 'msg' => __( 'Sorry, that file could not be uploaded.', 'ricoman' ) ) );
		}
		$attach_url = (string) wp_get_attachment_url( $attach_id );
	}

	// Readable summary for the lead + email.
	$lines   = array();
	$lines[] = 'Lighting solution: ' . implode( ', ', $solution );
	$lines[] = 'Priorities: ' . implode( ', ', $priorities );
	$lines[] = 'Mounting: ' . implode( ', ', $mounting );
	if ( '' !== $info ) {
		$lines[] = 'Additional info: ' . $info;
	}
	if ( '' !== $attach_url ) {
		$lines[] = 'Attachment: ' . $attach_url;
	}
	$message = implode( "\n", $lines );

	$source = 'Lighting design wizard';
	$data   = array(
		'name'      => $name,
		'email'     => $email,
		'company'   => '',
		'phone'     => '',
		'role'      => '',
		'message'   => $message,
		'source'    => $source,
		'items'     => '',
		'submitted' => current_time( 'mysql' ),
	);
	$attr    = function_exists( 'ricoman_lead_attribution' ) ? ricoman_lead_attribution() : array();
	$lead_id = wp_insert_post( array(
		'post_type'   => 'lead',
		'post_status' => 'private',
		'post_title'  => sprintf( '%s — %s', $name, __( 'Lighting design', 'ricoman' ) ),
		'meta_input'  => array_merge( array(
			'_lead_name'           => $name,
			'_lead_email'          => $email,
			'_lead_type'           => __( 'Project enquiry', 'ricoman' ),
			'_lead_message'        => $message,
			'_lead_source'         => $source,
			'_lead_wiz_solution'   => implode( ', ', $solution ),
			'_lead_wiz_priorities' => implode( ', ', $priorities ),
			'_lead_wiz_mounting'   => implode( ', ', $mounting ),
			'_lead_attachment'     => $attach_url,
		), $attr ),
	) );

	// Attach the upload to the lead so the team can find it from the lead screen.
	if ( $attach_id && ! is_wp_error( $lead_id ) ) {
		wp_update_post( array( 'ID' => (int) $attach_id, 'post_parent' => (int) $lead_id ) );
	}

	// Notify the team (design enquiries can be routed via the filter).
	$to   = apply_filters( 'ricoman_design_inbox', get_option( 'admin_email' ) );
	$body = sprintf( "New lighting design enquiry\n\nName: %s\nEmail: %s\n\n%s\n", $name, $email, $message );
	wp_mail(
		$to,
		sprintf( '[%s] Lighting design enquiry: %s', get_bloginfo( 'name' ), $name ),
		$body,
		array( 'Reply-To: ' . $name . ' <' . $email . '>' )
	);

	// Confirmation to the person who enquired.
	if ( function_exists( 'ricoman_send_lead_confirmation' ) ) {
		ricoman_send_lead_confirmation( $email, $name, array(
			'subject'  => sprintf( __( 'We’ve received your project enquiry — %s', 'ricoman' ), get_bloginfo( 'name' ) ),
			'intro'    => __( 'Thanks for telling us about your project. Our in-house lighting designers will review your details and be in touch shortly — usually within 3–5 working days.', 'ricoman' ),
			'reply_to' => $to,
		) );
	}

	/** Same hook every capture point fires (Sheets sync, CRM auto-fill…). */
	do_action( 'ricoman_lead_captured', $data, is_wp_error( $lead_id ) ? 0 : (int) $lead_id );

	wp_send_json_success( array( 'msg' => __( 'Your request has been submitted successfully.', 'ricoman' ) ) );
}
add_action( 'wp_ajax_nopriv_rm_wizard', 'ricoman_wizard_capture' );
add_action( 'wp_ajax_rm_wizard', 'ricoman_wizard_capture' );

/** Editable pattern so the team can drop the wizard onto any page. */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern( 'ricoman/project-wizard', array(
		'title'       => 'Tell us about your project (wizard)',
		'description' => 'Multi-step enquiry form — feeds the Leads CRM. Drop it on the Lighting Design page.',
		'categories'  => array( 'ricoman-page' ),
		'content'     => '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section">'
			. '<!-- wp:shortcode -->[ricoman_project_wizard]<!-- /wp:shortcode -->'
			. '</div><!-- /wp:group -->',
	) );
}, 14 );
