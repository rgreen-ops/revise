<?php
/**
 * CPD (Continuing Professional Development) booking form.
 *
 * Shortcode [ricoman_cpd_form] — lets architects / specifiers book one of our
 * CPD sessions, either at Ricoman Lighting HQ or at their own premises. Logs a
 * lead into the shared pipeline (Leads CRM + Sheets) AND emails the marketing
 * team. Each booking gets a sequential reference; the notification subject is
 * "CPD booking request #NNNN" and goes to ricoman_cpd_inbox().
 *
 * Mirrors inc/design-call.php (same .rm-tradeform-* styling + AJAX pattern).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where CPD booking notifications are sent. Filterable. */
function ricoman_cpd_inbox() {
	return apply_filters( 'ricoman_cpd_inbox', 'marketing@ricoman.com' );
}

/** The CPD sessions on offer. Filterable so more can be added without code edits. */
function ricoman_cpd_sessions() {
	return apply_filters( 'ricoman_cpd_sessions', array( 'The Lexicon Of Light' ) );
}

/**
 * [ricoman_cpd_form title="…" sessions="One|Two" thankyou="/url/"]
 *
 * `sessions` overrides ricoman_cpd_sessions() with a pipe-separated list.
 */
function ricoman_cpd_form_sc( $atts ) {
	$a = shortcode_atts( array(
		'title'    => 'Book a CPD with Ricoman',
		'sessions' => '',
		'thankyou' => '', // optional success redirect (ad tracking).
	), $atts, 'ricoman_cpd_form' );

	$sessions = '' !== trim( (string) $a['sessions'] )
		? array_filter( array_map( 'trim', explode( '|', $a['sessions'] ) ) )
		: ricoman_cpd_sessions();

	$src = get_theme_file_path( 'assets/js/cpd-form.js' );
	wp_enqueue_script( 'ricoman-cpd-form', get_theme_file_uri( 'assets/js/cpd-form.js' ), array(), file_exists( $src ) ? (string) filemtime( $src ) : '1', true );

	ob_start();
	?>
	<div id="book-cpd" style="scroll-margin-top:100px">
	<form class="rm-cpdform rm-tradeform" method="post" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'rm_cpd' ) ); ?>" data-ts="<?php echo (int) time(); ?>" data-redirect="<?php echo esc_url( $a['thankyou'] ); ?>" novalidate>
		<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px"><label>Website<input type="text" name="rm_hp" tabindex="-1" autocomplete="off"></label></div>
		<?php if ( $a['title'] ) : ?><h2 class="rm-tradeform-h"><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>

		<div class="rm-tradeform-row">
			<label class="rm-tradeform-label">Select CPD session
				<select name="session">
					<?php foreach ( $sessions as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $s ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="rm-tradeform-label">Select CPD location
				<select name="location" data-cpd-location>
					<option value="Ricoman Lighting HQ">Ricoman Lighting HQ</option>
					<option value="Your location">Your location (we come to you)</option>
					<option value="Virtual seminar">Virtual seminar</option>
				</select>
			</label>
		</div>

		<label class="rm-tradeform-label" data-cpd-address hidden>Your address
			<textarea name="address" rows="2" placeholder="Where should we deliver the CPD? Building, street, town, postcode"></textarea>
		</label>

		<label class="rm-tradeform-label" data-cpd-tour hidden>Include a guided tour of our manufacturing facilities?
			<select name="tour">
				<option value="Yes">Yes</option>
				<option value="No">No</option>
			</select>
		</label>

		<div class="rm-tradeform-row">
			<label class="rm-tradeform-label">First name *
				<input type="text" name="firstname" autocomplete="given-name">
			</label>
			<label class="rm-tradeform-label">Last name *
				<input type="text" name="lastname" autocomplete="family-name">
			</label>
		</div>
		<div class="rm-tradeform-row">
			<label class="rm-tradeform-label">Company name *
				<input type="text" name="company" autocomplete="organization">
			</label>
			<label class="rm-tradeform-label">Job role *
				<input type="text" name="jobrole" autocomplete="organization-title">
			</label>
		</div>
		<div class="rm-tradeform-row">
			<label class="rm-tradeform-label">Email *
				<input type="email" name="email" autocomplete="email">
			</label>
			<label class="rm-tradeform-label">Phone
				<input type="tel" name="phone" autocomplete="tel">
			</label>
		</div>
		<label class="rm-tradeform-label">Comments <span style="font-weight:400;color:var(--muted)">(optional)</span>
			<textarea name="message" rows="3" placeholder="Preferred dates, number of attendees, anything else we should know"></textarea>
		</label>

		<p class="rm-tradeform-msg" role="status" hidden></p>
		<div class="rm-tradeform-actions"><button type="submit" class="btn btn-solid rm-tradeform-go">Submit your enquiry →</button></div>
	</form>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'ricoman_cpd_form', 'ricoman_cpd_form_sc' );

/** AJAX: capture a CPD booking as a lead + numbered marketing notification. */
function ricoman_cpd_capture() {
	if ( ! check_ajax_referer( 'rm_cpd', 'nonce', false ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please refresh the page and try again.', 'ricoman' ) ) );
	}

	$first   = isset( $_POST['firstname'] ) ? sanitize_text_field( wp_unslash( $_POST['firstname'] ) ) : '';
	$last    = isset( $_POST['lastname'] ) ? sanitize_text_field( wp_unslash( $_POST['lastname'] ) ) : '';
	$company = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$role    = isset( $_POST['jobrole'] ) ? sanitize_text_field( wp_unslash( $_POST['jobrole'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$session = isset( $_POST['session'] ) ? sanitize_text_field( wp_unslash( $_POST['session'] ) ) : '';
	$loc     = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
	$address = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';
	$tour    = isset( $_POST['tour'] ) ? sanitize_text_field( wp_unslash( $_POST['tour'] ) ) : '';
	$msg     = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	$ts      = isset( $_POST['ts'] ) ? (int) $_POST['ts'] : 0;
	$hp      = isset( $_POST['rm_hp'] ) ? (string) wp_unslash( $_POST['rm_hp'] ) : '';

	$name = trim( $first . ' ' . $last );

	if ( '' === $first || '' === $last || '' === $company || '' === $role || ! is_email( $email ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please enter your name, company, job role and a valid email.', 'ricoman' ) ) );
	}
	// Honeypot + shared spam screen — silently acknowledge, store nothing.
	if ( '' !== $hp || ( function_exists( 'ricoman_lead_is_spam' ) && ricoman_lead_is_spam( array( 'ts' => $ts, 'fields' => array( $name, $company, $msg ) ) ) ) ) {
		wp_send_json_success( array( 'msg' => __( 'Thanks — your CPD enquiry has been received.', 'ricoman' ) ) );
	}

	// Address applies only when they host; the manufacturing tour only at HQ;
	// a virtual seminar has neither.
	$own = ( false !== stripos( $loc, 'your location' ) );
	$hq  = ( false !== stripos( $loc, 'hq' ) );
	if ( ! $own ) {
		$address = '';
	}
	if ( ! $hq ) {
		$tour = '';
	}
	$location = $loc;
	if ( $own && '' !== $address ) {
		$location = $loc . ' — ' . preg_replace( '/\s+/', ' ', $address );
	}

	// Sequential booking number (only real submissions consume one).
	$n   = 1 + (int) get_option( 'ricoman_cpd_no', 0 );
	update_option( 'ricoman_cpd_no', $n, false );
	$ref = sprintf( '#%04d', $n );

	$source = 'CPD booking' . ( '' !== $session ? ' — ' . $session : '' );
	$lines  = array();
	$lines[] = 'CPD session: ' . ( '' !== $session ? $session : 'Not specified' );
	$lines[] = 'Location: ' . ( '' !== $loc ? $loc : 'Not specified' );
	if ( '' !== $address ) {
		$lines[] = 'Address: ' . $address;
	}
	if ( '' !== $tour ) {
		$lines[] = 'Guided tour of manufacturing: ' . $tour;
	}
	if ( '' !== $role ) {
		$lines[] = 'Job role: ' . $role;
	}
	if ( '' !== $msg ) {
		$lines[] = 'Comments: ' . $msg;
	}
	$lines[] = 'Booking ref: ' . $ref;
	$message = implode( "\n", $lines );

	$data = array(
		'name'      => $name,
		'email'     => $email,
		'company'   => $company,
		'phone'     => $phone,
		'role'      => $role,
		'message'   => $message,
		'source'    => $source,
		'items'     => '',
		'submitted' => current_time( 'mysql' ),
	);
	$attr    = function_exists( 'ricoman_lead_attribution' ) ? ricoman_lead_attribution() : array();
	$lead_id = wp_insert_post( array(
		'post_type'   => 'lead',
		'post_status' => 'private',
		'post_title'  => sprintf( '%s — CPD %s', $name, $ref ),
		'meta_input'  => array_merge( array(
			'_lead_name'     => $name,
			'_lead_email'    => $email,
			'_lead_company'  => $company,
			'_lead_phone'    => $phone,
			'_lead_role'     => $role,
			'_lead_type'     => __( 'CPD booking', 'ricoman' ),
			'_lead_message'  => $message,
			'_lead_source'   => $source,
			'_lead_ref'      => $ref,
			'_lead_location' => $location,
		), $attr ),
	) );

	// Notify the marketing team — numbered subject.
	$subject = sprintf( 'CPD booking request %s', $ref );
	$body    = sprintf(
		"New CPD booking request %s\n\nName: %s\nCompany: %s\nJob role: %s\nEmail: %s\nPhone: %s\n\nCPD session: %s\nLocation: %s\n%s%s%s\n",
		$ref,
		$name,
		( '' !== $company ? $company : '—' ),
		( '' !== $role ? $role : '—' ),
		$email,
		( '' !== $phone ? $phone : '—' ),
		( '' !== $session ? $session : '—' ),
		( '' !== $loc ? $loc : '—' ),
		( '' !== $tour ? 'Guided tour of manufacturing: ' . $tour . "\n" : '' ),
		( '' !== $address ? "\nAddress:\n" . $address . "\n" : '' ),
		( '' !== $msg ? "\nComments:\n" . $msg . "\n" : '' )
	);
	wp_mail( ricoman_cpd_inbox(), $subject, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );

	// Confirmation to the person who booked.
	if ( function_exists( 'ricoman_send_lead_confirmation' ) ) {
		ricoman_send_lead_confirmation( $email, $name, array(
			'subject'  => sprintf( __( 'We’ve received your CPD enquiry — %s', 'ricoman' ), get_bloginfo( 'name' ) ),
			'intro'    => sprintf(
				/* translators: %s: CPD session name. */
				__( 'Thanks for your interest in our %s CPD. Our marketing team will be in touch to confirm a date and the details.', 'ricoman' ),
				( '' !== $session ? $session : __( 'lighting', 'ricoman' ) )
			),
			'reply_to' => ricoman_cpd_inbox(),
		) );
	}

	do_action( 'ricoman_lead_captured', $data, is_wp_error( $lead_id ) ? 0 : (int) $lead_id );

	wp_send_json_success( array( 'msg' => __( 'Thanks — your CPD enquiry has been received. Our team will be in touch to confirm the details.', 'ricoman' ) ) );
}
add_action( 'wp_ajax_nopriv_rm_cpd', 'ricoman_cpd_capture' );
add_action( 'wp_ajax_rm_cpd', 'ricoman_cpd_capture' );

/** Editable pattern so the team can drop the CPD form onto any page. */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern( 'ricoman/cpd-form', array(
		'title'       => 'CPD · Booking form',
		'description' => 'Book a CPD session at Ricoman HQ or your own premises — feeds the Leads CRM; numbered notification to the marketing team.',
		'categories'  => array( 'ricoman-page' ),
		'content'     => '<!-- wp:group {"align":"full","className":"rm-section","backgroundColor":"surface","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section has-surface-background-color has-background">'
			. '<!-- wp:shortcode -->[ricoman_cpd_form]<!-- /wp:shortcode -->'
			. '</div><!-- /wp:group -->',
	) );
}, 14 );
