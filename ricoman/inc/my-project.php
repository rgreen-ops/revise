<?php
/**
 * "My Project" — a specification list / Toolbox.
 *
 * Specifiers add products to a personal list (stored in the browser), review it
 * on the My Project page and submit it as an enquiry. The submission reuses the
 * lead-capture handler, so a project enquiry lands as a Lead (and in the Google
 * Sheets sync) with the full item list attached.
 *
 * Shortcodes:
 *   [ricoman_add_to_project]   Add-to-list button (used on product templates)
 *   [ricoman_my_project]       The list + enquiry form (put on a "My Project" page)
 *   [ricoman_project_count]    A live count badge (used in the header)
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the My Project script on the front end.
 */
function ricoman_my_project_assets() {
	wp_enqueue_script(
		'ricoman-my-project',
		get_theme_file_uri( 'assets/js/my-project.js' ),
		array(),
		RICOMAN_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'ricoman_my_project_assets' );

/**
 * Add-to-project button.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function ricoman_sc_add_to_project( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'    => 0,
			'label' => __( '＋ Add to My Project', 'ricoman' ),
		),
		$atts,
		'ricoman_add_to_project'
	);

	$id = $atts['id'] ? (int) $atts['id'] : (int) get_the_ID();
	if ( ! $id ) {
		return '';
	}

	$label = esc_html( $atts['label'] );

	return sprintf(
		'<button type="button" class="ricoman-add-project wp-element-button" data-add-to-project data-id="%1$d" data-title="%2$s" data-sku="%3$s" data-url="%4$s" data-label="%5$s">%5$s</button>',
		$id,
		esc_attr( get_the_title( $id ) ),
		esc_attr( (string) get_post_meta( $id, '_ricoman_sku', true ) ),
		esc_attr( get_permalink( $id ) ),
		$label
	);
}
add_shortcode( 'ricoman_add_to_project', 'ricoman_sc_add_to_project' );

/**
 * Header count badge: "My Project (n)".
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function ricoman_sc_project_count( $atts ) {
	$atts = shortcode_atts(
		array(
			'label' => __( 'My Project', 'ricoman' ),
			'url'   => ricoman_my_project_url(),
		),
		$atts,
		'ricoman_project_count'
	);

	return sprintf(
		'<a class="ricoman-mp-link" href="%1$s">%2$s <span class="ricoman-mp-count" data-mp-count data-empty="true">0</span></a>',
		esc_url( $atts['url'] ),
		esc_html( $atts['label'] )
	);
}
add_shortcode( 'ricoman_project_count', 'ricoman_sc_project_count' );

/**
 * Resolve the URL of the "My Project" page (by path, falling back to a query).
 *
 * @return string
 */
function ricoman_my_project_url() {
	$page = get_page_by_path( 'my-project' );
	if ( $page ) {
		return get_permalink( $page );
	}
	return home_url( '/my-project/' );
}

/**
 * The My Project list + enquiry form.
 *
 * @return string
 */
function ricoman_sc_my_project() {
	$sent = isset( $_GET['lead'] ) ? sanitize_key( wp_unslash( $_GET['lead'] ) ) : '';

	ob_start();
	?>
	<div class="ricoman-my-project">
		<?php if ( 'sent' === $sent ) : ?>
			<div class="ricoman-lead-success"><strong><?php esc_html_e( 'Thanks — your project list is on its way.', 'ricoman' ); ?></strong><br><?php esc_html_e( 'Our lighting team will be in touch within one working day.', 'ricoman' ); ?></div>
		<?php endif; ?>

		<p data-mp-empty hidden class="ricoman-mp-empty">
			<?php esc_html_e( 'Your project list is empty. Browse products and choose “Add to My Project” to build a specification.', 'ricoman' ); ?>
		</p>

		<div data-mp-list></div>

		<div data-mp-panel hidden class="ricoman-mp-panel">
			<div class="ricoman-mp-actions">
				<button type="button" class="ricoman-mp-clear" data-mp-clear><?php esc_html_e( 'Clear list', 'ricoman' ); ?></button>
			</div>

			<form class="ricoman-lead-form ricoman-mp-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ricoman_lead">
				<input type="hidden" name="lead_source" value="My Project list">
				<input type="hidden" name="project_items" data-mp-items value="">
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( ricoman_my_project_url() ); ?>">
				<?php wp_nonce_field( 'ricoman_lead', 'ricoman_lead_nonce' ); ?>

				<p class="ricoman-lead-title"><?php esc_html_e( 'Request a quote for this project', 'ricoman' ); ?></p>

				<div aria-hidden="true" style="position:absolute;left:-9999px"><label>Website<input type="text" name="ricoman_hp" tabindex="-1" autocomplete="off"></label></div>

				<div class="ricoman-field-row">
					<label><span><?php esc_html_e( 'Name', 'ricoman' ); ?> *</span><input type="text" name="lead_name" required></label>
					<label><span><?php esc_html_e( 'Company', 'ricoman' ); ?></span><input type="text" name="lead_company"></label>
				</div>
				<div class="ricoman-field-row">
					<label><span><?php esc_html_e( 'Email', 'ricoman' ); ?> *</span><input type="email" name="lead_email" required></label>
					<label><span><?php esc_html_e( 'Phone', 'ricoman' ); ?></span><input type="tel" name="lead_phone"></label>
				</div>
				<label><span><?php esc_html_e( 'Notes for our design team', 'ricoman' ); ?></span><textarea name="lead_message" rows="3"></textarea></label>

				<button type="submit" class="ricoman-lead-submit"><?php esc_html_e( 'Send project enquiry', 'ricoman' ); ?></button>
			</form>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'ricoman_my_project', 'ricoman_sc_my_project' );

/**
 * Parse the submitted project items JSON into a clean, human-readable list.
 *
 * @param string $raw JSON string from the browser.
 * @return string One item per line, e.g. "2 x Flow+ (FLW-192-TW)".
 */
function ricoman_parse_project_items( $raw ) {
	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return '';
	}
	$items = json_decode( $raw, true );
	if ( ! is_array( $items ) ) {
		return '';
	}

	$lines = array();
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
		if ( '' === $title ) {
			continue;
		}
		$qty = isset( $item['qty'] ) ? max( 1, (int) $item['qty'] ) : 1;
		$sku = isset( $item['sku'] ) ? sanitize_text_field( $item['sku'] ) : '';

		$line = $qty . ' x ' . $title;
		if ( '' !== $sku ) {
			$line .= ' (' . $sku . ')';
		}
		$lines[] = $line;

		if ( count( $lines ) >= 100 ) {
			break; // Guard against oversized payloads.
		}
	}

	return implode( "\n", $lines );
}
