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
		'Contractor / Installer',
		'Wholesaler / Distributor',
		'Interior Designer',
		'End user / Other',
	) );
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
	wp_localize_script( 'ricoman-lead-gate', 'rmGate', array(
		'in'       => is_user_logged_in() ? 1 : 0,
		'ajax'     => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'rm_gate' ),
		'login'    => wp_login_url(),
		'register' => wp_registration_url(),
	) );
} );

/** The gate modal markup (only needed for logged-out visitors). */
add_action( 'wp_footer', function () {
	if ( is_user_logged_in() ) {
		return;
	}
	$opts = '<option value="">' . esc_html__( 'Select customer type…', 'ricoman' ) . '</option>';
	foreach ( ricoman_customer_types() as $t ) {
		$opts .= '<option value="' . esc_attr( $t ) . '">' . esc_html( $t ) . '</option>';
	}
	?>
	<div class="rm-gate" hidden aria-hidden="true">
		<div class="rm-gate-box" role="dialog" aria-modal="true" aria-labelledby="rm-gate-h">
			<button type="button" class="rm-gate-x" aria-label="Close">&times;</button>
			<h3 id="rm-gate-h" class="rm-gate-title"><?php esc_html_e( 'Download this resource', 'ricoman' ); ?></h3>
			<p class="rm-gate-sub"><?php esc_html_e( 'Tell us who you are and the file will open. We’ll only use this to help with your projects.', 'ricoman' ); ?></p>
			<form class="rm-gate-form">
				<label><span><?php esc_html_e( 'Name', 'ricoman' ); ?> *</span><input type="text" name="name" required></label>
				<label><span><?php esc_html_e( 'Email', 'ricoman' ); ?> *</span><input type="email" name="email" required></label>
				<label><span><?php esc_html_e( 'I am a…', 'ricoman' ); ?> *</span><select name="ctype" required><?php echo $opts; // phpcs:ignore WordPress.Security.EscapeOutput ?></select></label>
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
	if ( '' === $name || ! is_email( $email ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please enter your name and a valid email.', 'ricoman' ) ) );
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
		'meta_input'  => array(
			'_lead_name'   => $name,
			'_lead_email'  => $email,
			'_lead_role'   => $type,
			'_lead_source' => $src,
		),
	) );

	/** Reuse the same hook the enquiry form fires (Sheets sync, CRM, etc.). */
	do_action( 'ricoman_lead_captured', $data, is_wp_error( $lead_id ) ? 0 : $lead_id );

	// Remember this visitor so they're not gated again (once-per-visitor).
	setcookie( 'rm_dl_gate', '1', time() + 30 * DAY_IN_SECONDS, defined( 'COOKIEPATH' ) ? COOKIEPATH : '/', defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '' );

	wp_send_json_success();
}
add_action( 'wp_ajax_nopriv_rm_lead_gate', 'ricoman_gate_capture' );
add_action( 'wp_ajax_rm_lead_gate', 'ricoman_gate_capture' );
