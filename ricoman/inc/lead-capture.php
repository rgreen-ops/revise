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
		echo '<div class="ricoman-lead-success"><strong>' . esc_html__( 'Thanks — your enquiry is on its way.', 'ricoman' ) . '</strong><br>' . esc_html__( 'Our lighting team will be in touch within one working day.', 'ricoman' ) . '</div>';
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

		<button type="submit" class="ricoman-lead-submit"><?php esc_html_e( 'Send enquiry', 'ricoman' ); ?></button>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'ricoman_lead_form', 'ricoman_lead_form' );

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

	// 1. Store as a private `lead` post.
	$lead_id = wp_insert_post(
		array(
			'post_type'   => 'lead',
			'post_status' => 'private',
			'post_title'  => sprintf( '%s — %s', $name, $company ? $company : $role ),
			'meta_input'  => array(
				'_lead_name'    => $name,
				'_lead_email'   => $email,
				'_lead_company' => $company,
				'_lead_phone'   => $phone,
				'_lead_role'    => $role,
				'_lead_message' => $message,
				'_lead_source'  => $source,
				'_lead_items'   => $project_items,
			),
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

/**
 * Show captured lead details in the admin list / editor (read-only).
 */
function ricoman_lead_columns( $columns ) {
	$columns = array(
		'cb'         => $columns['cb'],
		'title'      => __( 'Lead', 'ricoman' ),
		'lead_email' => __( 'Email', 'ricoman' ),
		'lead_role'  => __( 'Role', 'ricoman' ),
		'lead_src'   => __( 'Source', 'ricoman' ),
		'date'       => __( 'Received', 'ricoman' ),
	);
	return $columns;
}
add_filter( 'manage_lead_posts_columns', 'ricoman_lead_columns' );

/**
 * Fill the custom lead columns.
 */
function ricoman_lead_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'lead_email':
			$email = get_post_meta( $post_id, '_lead_email', true );
			echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—';
			break;
		case 'lead_role':
			echo esc_html( get_post_meta( $post_id, '_lead_role', true ) ?: '—' );
			break;
		case 'lead_src':
			echo esc_html( get_post_meta( $post_id, '_lead_source', true ) ?: '—' );
			break;
	}
}
add_action( 'manage_lead_posts_custom_column', 'ricoman_lead_column_content', 10, 2 );
