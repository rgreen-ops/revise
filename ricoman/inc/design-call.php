<?php
/**
 * "Request a 30-minute call with our lighting designers" form.
 *
 * Shortcode [ricoman_design_call] — a lead-gen form (like the callback) that logs
 * a lead into the shared pipeline AND emails the lighting design team. Each request
 * gets a sequential number; the notification subject is
 * "Lighting Design 30 minute call request #NNNN" and goes to the address returned
 * by ricoman_design_call_inbox().
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where the design-call notification is sent. Filterable. */
function ricoman_design_call_inbox() {
	return apply_filters( 'ricoman_design_call_inbox', 'mparker@ricoman.com' );
}

/** [ricoman_design_call title="…" thankyou="/url/"] */
function ricoman_design_call_sc( $atts ) {
	$a = shortcode_atts( array(
		'title'    => 'Request a 30-minute call with our lighting designers',
		'thankyou' => '', // optional success redirect (ad tracking).
	), $atts, 'ricoman_design_call' );

	$src = get_theme_file_path( 'assets/js/design-call.js' );
	wp_enqueue_script( 'ricoman-design-call', get_theme_file_uri( 'assets/js/design-call.js' ), array(), file_exists( $src ) ? (string) filemtime( $src ) : '1', true );

	ob_start();
	?>
	<form class="rm-callform" method="post" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'rm_designcall' ) ); ?>" data-ts="<?php echo (int) time(); ?>" data-redirect="<?php echo esc_url( $a['thankyou'] ); ?>" novalidate>
		<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px"><label>Website<input type="text" name="rm_hp" tabindex="-1" autocomplete="off"></label></div>
		<?php if ( $a['title'] ) : ?><h2 class="rm-tradeform-h"><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
		<label class="rm-tradeform-label">Name *
			<input type="text" name="reqname" autocomplete="name">
		</label>
		<div class="rm-tradeform-row">
			<label class="rm-tradeform-label">Email *
				<input type="email" name="email" autocomplete="email">
			</label>
			<label class="rm-tradeform-label">Phone *
				<input type="tel" name="phone" autocomplete="tel">
			</label>
		</div>
		<label class="rm-tradeform-label">Preferred day &amp; time
			<input type="text" name="preferred" placeholder="e.g. weekday mornings">
		</label>
		<label class="rm-tradeform-label">Anything you&rsquo;d like us to prepare? <span style="font-weight:400;color:var(--muted)">(optional)</span>
			<textarea name="message" rows="3"></textarea>
		</label>
		<p class="rm-tradeform-msg" role="status" hidden></p>
		<div class="rm-tradeform-actions"><button type="submit" class="btn btn-solid rm-tradeform-go">Request a call →</button></div>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'ricoman_design_call', 'ricoman_design_call_sc' );

/** AJAX: capture a design-call request as a lead + numbered team notification. */
function ricoman_design_call_capture() {
	if ( ! check_ajax_referer( 'rm_designcall', 'nonce', false ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please refresh the page and try again.', 'ricoman' ) ) );
	}
	$name  = isset( $_POST['reqname'] ) ? sanitize_text_field( wp_unslash( $_POST['reqname'] ) ) : '';
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$pref  = isset( $_POST['preferred'] ) ? sanitize_text_field( wp_unslash( $_POST['preferred'] ) ) : '';
	$msg   = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	$ts    = isset( $_POST['ts'] ) ? (int) $_POST['ts'] : 0;
	$hp    = isset( $_POST['rm_hp'] ) ? (string) wp_unslash( $_POST['rm_hp'] ) : '';

	if ( '' === $name || ! is_email( $email ) || preg_match_all( '/\d/', $phone ) < 7 ) {
		wp_send_json_error( array( 'msg' => __( 'Please enter your name, a valid email and phone number.', 'ricoman' ) ) );
	}
	// Honeypot + shared spam screen — silently acknowledge, store nothing, no number burned.
	if ( '' !== $hp || ( function_exists( 'ricoman_lead_is_spam' ) && ricoman_lead_is_spam( array( 'ts' => $ts, 'fields' => array( $name, $msg ) ) ) ) ) {
		wp_send_json_success( array( 'msg' => __( 'Thanks — your call request has been received.', 'ricoman' ) ) );
	}

	// Sequential request number (only real submissions consume one).
	$n   = 1 + (int) get_option( 'ricoman_design_call_no', 0 );
	update_option( 'ricoman_design_call_no', $n, false );
	$ref = sprintf( '#%04d', $n );

	$source  = 'Lighting design call request';
	$lines   = array( 'Phone: ' . $phone );
	if ( '' !== $pref ) {
		$lines[] = 'Preferred day/time: ' . $pref;
	}
	if ( '' !== $msg ) {
		$lines[] = 'Notes: ' . $msg;
	}
	$lines[] = 'Request ref: ' . $ref;
	$message = implode( "\n", $lines );

	$data    = array(
		'name'      => $name,
		'email'     => $email,
		'company'   => '',
		'phone'     => $phone,
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
		'post_title'  => sprintf( '%s — Design call %s', $name, $ref ),
		'meta_input'  => array_merge( array(
			'_lead_name'    => $name,
			'_lead_email'   => $email,
			'_lead_phone'   => $phone,
			'_lead_type'    => __( 'Lighting design call request', 'ricoman' ),
			'_lead_message' => $message,
			'_lead_source'  => $source,
			'_lead_ref'     => $ref,
		), $attr ),
	) );

	// Notify the lighting design team — numbered subject.
	$subject = sprintf( 'Lighting Design 30 minute call request %s', $ref );
	$body    = sprintf(
		"New 30-minute call request %s\n\nName: %s\nEmail: %s\nPhone: %s\nPreferred day/time: %s\n%s\n",
		$ref,
		$name,
		$email,
		$phone,
		( '' !== $pref ? $pref : 'No preference given' ),
		( '' !== $msg ? "\nNotes:\n" . $msg : '' )
	);
	wp_mail( ricoman_design_call_inbox(), $subject, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );

	do_action( 'ricoman_lead_captured', $data, is_wp_error( $lead_id ) ? 0 : (int) $lead_id );

	wp_send_json_success( array( 'msg' => __( 'Thanks — your call request has been received. Our lighting design team will be in touch.', 'ricoman' ) ) );
}
add_action( 'wp_ajax_nopriv_rm_designcall', 'ricoman_design_call_capture' );
add_action( 'wp_ajax_rm_designcall', 'ricoman_design_call_capture' );

/** Editable pattern so the team can drop the form onto any page. */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern( 'ricoman/design-call', array(
		'title'       => 'Book a design call (30 min)',
		'description' => 'Request a 30-minute call with the lighting designers — feeds the Leads CRM; numbered notification to the design team.',
		'categories'  => array( 'ricoman-page' ),
		'content'     => '<!-- wp:group {"align":"full","className":"rm-section","backgroundColor":"surface","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section has-surface-background-color has-background">'
			. '<!-- wp:shortcode -->[ricoman_design_call]<!-- /wp:shortcode -->'
			. '</div><!-- /wp:group -->',
	) );
}, 14 );
