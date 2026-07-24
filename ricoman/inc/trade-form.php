<?php
/**
 * "Apply for a trade account" form.
 *
 * Shortcode [ricoman_trade_form] renders a working single-page form (Name,
 * Company, Job title, Email, Phone) and submits via AJAX into the shared lead
 * pipeline — stores a `lead` post, fires ricoman_lead_captured (Sheets + CRM),
 * emails the team, and passes the same honeypot + spam screen as every other
 * capture point. Name / Company / Email / Phone are required; Job title optional.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** [ricoman_trade_form title="Apply for a trade account"] */
function ricoman_trade_form_sc( $atts ) {
	$a = shortcode_atts( array(
		'title'    => 'Apply for a trade account',
		'thankyou' => '', // optional URL to redirect to on success (good for ad conversion tracking).
	), $atts, 'ricoman_trade_form' );

	$src = get_theme_file_path( 'assets/js/trade-form.js' );
	wp_enqueue_script( 'ricoman-trade-form', get_theme_file_uri( 'assets/js/trade-form.js' ), array(), file_exists( $src ) ? (string) filemtime( $src ) : '1', true );

	ob_start();
	?>
	<form class="rm-tradeform" method="post" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'rm_trade' ) ); ?>" data-ts="<?php echo (int) time(); ?>" data-redirect="<?php echo esc_url( $a['thankyou'] ); ?>" novalidate>
		<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px"><label>Website<input type="text" name="rm_hp" tabindex="-1" autocomplete="off"></label></div>
		<?php if ( $a['title'] ) : ?><h2 class="rm-tradeform-h"><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
		<label class="rm-tradeform-label">Name *
			<input type="text" name="applicant_name" autocomplete="name">
		</label>
		<div class="rm-tradeform-row">
			<label class="rm-tradeform-label">Company *
				<input type="text" name="company" autocomplete="organization">
			</label>
			<label class="rm-tradeform-label">Job title
				<input type="text" name="job" autocomplete="organization-title">
			</label>
		</div>
		<div class="rm-tradeform-row">
			<label class="rm-tradeform-label">Email *
				<input type="email" name="email" autocomplete="email">
			</label>
			<label class="rm-tradeform-label">Phone number *
				<input type="tel" name="phone" autocomplete="tel">
			</label>
		</div>
		<p class="rm-tradeform-msg" role="status" hidden></p>
		<div class="rm-tradeform-actions"><button type="submit" class="btn btn-solid rm-tradeform-go">Send →</button></div>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'ricoman_trade_form', 'ricoman_trade_form_sc' );

/** AJAX: capture a trade-account application as a lead. */
function ricoman_trade_capture() {
	if ( ! check_ajax_referer( 'rm_trade', 'nonce', false ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please refresh the page and try again.', 'ricoman' ) ) );
	}
	$name    = isset( $_POST['applicant_name'] ) ? sanitize_text_field( wp_unslash( $_POST['applicant_name'] ) ) : '';
	$company = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$job     = isset( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$ts      = isset( $_POST['ts'] ) ? (int) $_POST['ts'] : 0;
	$hp      = isset( $_POST['rm_hp'] ) ? (string) wp_unslash( $_POST['rm_hp'] ) : '';

	// Required: name, company, valid email, plausible phone (>=7 digits).
	if ( '' === $name || '' === $company || ! is_email( $email ) || preg_match_all( '/\d/', $phone ) < 7 ) {
		wp_send_json_error( array( 'msg' => __( 'Please fill in your name, company, a valid email and phone number.', 'ricoman' ) ) );
	}
	// Honeypot + shared spam screen — silently acknowledge, store nothing.
	if ( '' !== $hp || ( function_exists( 'ricoman_lead_is_spam' ) && ricoman_lead_is_spam( array( 'ts' => $ts, 'fields' => array( $name, $company, $job ) ) ) ) ) {
		wp_send_json_success( array( 'msg' => __( 'Thank you — your application has been received.', 'ricoman' ) ) );
	}

	$source  = 'Trade account form';
	$message = sprintf( "Company: %s\nJob title: %s\nPhone: %s", $company, ( '' !== $job ? $job : '—' ), $phone );
	$data    = array(
		'name'      => $name,
		'email'     => $email,
		'company'   => $company,
		'phone'     => $phone,
		'role'      => $job,
		'message'   => $message,
		'source'    => $source,
		'items'     => '',
		'submitted' => current_time( 'mysql' ),
	);
	$attr    = function_exists( 'ricoman_lead_attribution' ) ? ricoman_lead_attribution() : array();
	$lead_id = wp_insert_post( array(
		'post_type'   => 'lead',
		'post_status' => 'private',
		'post_title'  => sprintf( '%s — %s', $name, $company ? $company : __( 'Trade account', 'ricoman' ) ),
		'meta_input'  => array_merge( array(
			'_lead_name'    => $name,
			'_lead_email'   => $email,
			'_lead_company' => $company,
			'_lead_phone'   => $phone,
			'_lead_role'    => $job,
			'_lead_type'    => __( 'Trade account application', 'ricoman' ),
			'_lead_message' => $message,
			'_lead_source'  => $source,
		), $attr ),
	) );

	$to   = apply_filters( 'ricoman_trade_inbox', get_option( 'admin_email' ) );
	$body = sprintf( "New trade account application\n\nName: %s\nCompany: %s\nJob title: %s\nEmail: %s\nPhone: %s\n", $name, $company, ( '' !== $job ? $job : '—' ), $email, $phone );
	wp_mail(
		$to,
		sprintf( '[%s] Trade account application: %s', get_bloginfo( 'name' ), $company ? $company : $name ),
		$body,
		array( 'Reply-To: ' . $name . ' <' . $email . '>' )
	);

	do_action( 'ricoman_lead_captured', $data, is_wp_error( $lead_id ) ? 0 : (int) $lead_id );

	wp_send_json_success( array( 'msg' => __( 'Thank you — your application has been received.', 'ricoman' ) ) );
}
add_action( 'wp_ajax_nopriv_rm_trade', 'ricoman_trade_capture' );
add_action( 'wp_ajax_rm_trade', 'ricoman_trade_capture' );

/** Editable pattern so the team can drop the working form onto any page. */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern( 'ricoman/trade-form', array(
		'title'       => 'Apply for a trade account (form)',
		'description' => 'Working trade-account application form — feeds the Leads CRM.',
		'categories'  => array( 'ricoman-page' ),
		'content'     => '<!-- wp:group {"align":"full","className":"rm-section","backgroundColor":"surface","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section has-surface-background-color has-background">'
			. '<!-- wp:shortcode -->[ricoman_trade_form]<!-- /wp:shortcode -->'
			. '</div><!-- /wp:group -->',
	) );
}, 14 );
