<?php
/**
 * Light Box Builder — stretched-fabric (SEG) light box configurator.
 *
 * Code-only page at /lightbox-builder/ (no CMS page needed) + shortcode
 * [ricoman_lightbox_builder] to drop the tool onto any other page.
 *
 * The visitor picks type (ultra-slim LGP / SEG backlit / double-sided), shape
 * and size, frame depth, lighting (white / tunable / RGBW / pixel), graphic,
 * mounting and frame finish; a live to-scale preview + spec summary updates as
 * they go. "Request a quote" logs a lead into the shared pipeline and emails
 * the spec to ricoman_lightbox_inbox(). NO PRICING — quote only.
 *
 * Product data (profiles, weights, size limits, light engines) comes from the
 * supplier SEG light box brochure — see assets/js/lightbox-builder.js DATA.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where light box quote requests are sent. Filterable. */
function ricoman_lightbox_inbox() {
	return apply_filters( 'ricoman_lightbox_inbox', 'sales@ricoman.com' );
}

/** The public URL path of the builder page. */
function ricoman_lightbox_path() {
	return 'lightbox-builder';
}

/** Is the current request the builder page? */
function ricoman_lightbox_is_route() {
	$path = strtolower( trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ) );
	return ricoman_lightbox_path() === $path;
}

/** Enqueue the builder's CSS + JS (only where the tool is shown). */
function ricoman_lightbox_assets() {
	$css = get_theme_file_path( 'assets/css/lightbox-builder.css' );
	$js  = get_theme_file_path( 'assets/js/lightbox-builder.js' );
	wp_enqueue_style( 'ricoman-lightbox', get_theme_file_uri( 'assets/css/lightbox-builder.css' ), array(), file_exists( $css ) ? (string) filemtime( $css ) : '1' );
	wp_enqueue_script( 'ricoman-lightbox', get_theme_file_uri( 'assets/js/lightbox-builder.js' ), array(), file_exists( $js ) ? (string) filemtime( $js ) : '1', true );
}

/** [ricoman_lightbox_builder] — the tool markup. JS builds the controls. */
function ricoman_lightbox_sc() {
	ricoman_lightbox_assets();
	$three = get_theme_file_path( 'assets/js/lightbox-3d.js' );
	ob_start();
	?>
	<div class="rm-lbx" id="rm-lbx"
		data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		data-ts="<?php echo (int) time(); ?>"
		data-three="<?php echo esc_url( add_query_arg( 'v', file_exists( $three ) ? (string) filemtime( $three ) : '1', get_theme_file_uri( 'assets/js/lightbox-3d.js' ) ) ); ?>"
		data-img="<?php echo esc_url( get_theme_file_uri( 'assets/images/lightbox/' ) ); ?>">
		<noscript><p>The Light Box Builder needs JavaScript. Please call us on 0161 877 1399 or email <a href="mailto:sales@ricoman.com">sales@ricoman.com</a> and we&rsquo;ll spec your light box with you.</p></noscript>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'ricoman_lightbox_builder', 'ricoman_lightbox_sc' );

/* --------------------------------------------------------------------------
 * The /lightbox-builder/ page (code-only route, rendered in the site chrome).
 * ------------------------------------------------------------------------ */

add_action( 'template_redirect', function () {
	if ( ! ricoman_lightbox_is_route() ) {
		return;
	}
	global $wp_query;
	if ( $wp_query ) {
		$wp_query->is_404 = false;
	}
	status_header( 200 );
	ricoman_lightbox_assets();

	$title = 'Light Box Builder — Stretched Fabric SEG Light Boxes | Ricoman';
	$desc  = 'Design a stretched-fabric SEG light box online: shape, size, depth, white or RGB pixel lighting, printed graphic and mounting. See it to scale and request a quote.';
	$url   = home_url( '/' . ricoman_lightbox_path() . '/' );

	add_filter( 'pre_get_document_title', function () use ( $title ) { return $title; }, 999 );
	add_filter( 'wpseo_title', function () use ( $title ) { return $title; }, 999 );
	add_filter( 'wpseo_metadesc', function () use ( $desc ) { return $desc; }, 999 );
	add_filter( 'wpseo_canonical', function () use ( $url ) { return $url; }, 999 );
	add_filter( 'wpseo_opengraph_url', function () use ( $url ) { return $url; }, 999 );
	add_filter( 'wpseo_opengraph_title', function () use ( $title ) { return $title; }, 999 );
	add_filter( 'wpseo_opengraph_desc', function () use ( $desc ) { return $desc; }, 999 );
	add_filter( 'body_class', function ( $c ) { $c[] = 'rm-lbx-page'; return $c; } );
	$yoast = defined( 'WPSEO_VERSION' );
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ( ! $yoast ) : ?>
<meta name="description" content="<?php echo esc_attr( $desc ); ?>">
<link rel="canonical" href="<?php echo esc_url( $url ); ?>">
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
<?php block_template_part( 'header' ); ?>
<main class="rm-lbx-main">
	<section class="rm-lbx-hero">
		<p class="kick">Stretched fabric · SEG light boxes</p>
		<h1>Light Box Builder</h1>
		<p class="rm-lbx-lede">Design a seamless, frameless-look fabric light box &mdash; any size, shape or colour. Pick your options, see it to scale, and we&rsquo;ll come back with a quote and drawings.</p>
	</section>
	<?php echo ricoman_lightbox_sc(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</main>
<?php block_template_part( 'footer' ); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
	<?php
	exit;
}, 1 );

/* --------------------------------------------------------------------------
 * Quote request → lead + team email + confirmation.
 * ------------------------------------------------------------------------ */

function ricoman_lightbox_capture() {
	$f = function ( $k, $max = 200 ) {
		$v = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return mb_substr( $v, 0, $max );
	};
	$name     = $f( 'reqname' );
	$email    = sanitize_email( $f( 'email' ) );
	$phone    = $f( 'phone', 40 );
	$company  = $f( 'company' );
	$project  = $f( 'project' );
	$timeline = $f( 'timeline', 80 );
	$code     = $f( 'code', 120 );
	$notes    = isset( $_POST['notes'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ), 0, 2000 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$spec     = isset( $_POST['spec'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['spec'] ) ), 0, 3000 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$link     = isset( $_POST['link'] ) ? esc_url_raw( wp_unslash( $_POST['link'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$ts       = isset( $_POST['ts'] ) ? (int) $_POST['ts'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$hp       = isset( $_POST['rm_hp'] ) ? (string) wp_unslash( $_POST['rm_hp'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	if ( '' === $name || ! is_email( $email ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please enter your name and a valid email.', 'ricoman' ) ) );
	}
	// Only keep a link that points back at this site.
	if ( '' !== $link && 0 !== strpos( $link, home_url( '/' ) ) ) {
		$link = '';
	}
	// Honeypot + shared spam screen — silently acknowledge, store nothing, no number burned.
	if ( '' !== $hp || ( function_exists( 'ricoman_lead_is_spam' ) && ricoman_lead_is_spam( array( 'ts' => $ts, 'fields' => array( $name, $notes, $company ) ) ) ) ) {
		wp_send_json_success( array( 'ref' => '' ) );
	}

	$n   = 1 + (int) get_option( 'ricoman_lightbox_no', 0 );
	update_option( 'ricoman_lightbox_no', $n, false );
	$ref = sprintf( 'LB-%04d', $n );

	$lines = array();
	if ( '' !== $phone ) {
		$lines[] = 'Phone: ' . $phone;
	}
	if ( '' !== $company ) {
		$lines[] = 'Company: ' . $company;
	}
	if ( '' !== $project ) {
		$lines[] = 'Project / location: ' . $project;
	}
	if ( '' !== $timeline ) {
		$lines[] = 'Timeline: ' . $timeline;
	}
	$lines[] = 'Build code: ' . $code;
	$lines[] = '';
	$lines[] = $spec;
	if ( '' !== $notes ) {
		$lines[] = '';
		$lines[] = 'Notes: ' . $notes;
	}
	if ( '' !== $link ) {
		$lines[] = '';
		$lines[] = 'Open this design: ' . $link;
	}
	$lines[] = 'Request ref: ' . $ref;
	$message = implode( "\n", $lines );
	$source  = 'Light Box Builder';

	$attr    = function_exists( 'ricoman_lead_attribution' ) ? ricoman_lead_attribution() : array();
	$lead_id = wp_insert_post( array(
		'post_type'   => 'lead',
		'post_status' => 'private',
		'post_title'  => sprintf( '%s — Light box %s', $name, $ref ),
		'meta_input'  => array_merge( array(
			'_lead_name'    => $name,
			'_lead_email'   => $email,
			'_lead_phone'   => $phone,
			'_lead_company' => $company,
			'_lead_type'    => __( 'Light box quote request', 'ricoman' ),
			'_lead_message' => $message,
			'_lead_source'  => $source,
			'_lead_ref'     => $ref,
		), $attr ),
	) );

	$headers = array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>' );
	wp_mail(
		ricoman_lightbox_inbox(),
		sprintf( 'Light box quote request %s — %s', $ref, '' !== $company ? $company : $name ),
		"New light box quote request {$ref}\n\nName: {$name}\nEmail: {$email}\n" . $message . "\n",
		$headers
	);

	if ( function_exists( 'ricoman_send_lead_confirmation' ) ) {
		ricoman_send_lead_confirmation( $email, $name, array(
			'subject'  => sprintf( __( 'Your light box design %1$s — %2$s', 'ricoman' ), $ref, get_bloginfo( 'name' ) ),
			'intro'    => __( 'Thanks for designing your light box with us. Our team will review your spec and come back with a quote and drawings shortly.', 'ricoman' ) . "\n\n" . $spec,
			'reply_to' => ricoman_lightbox_inbox(),
		) );
	}

	do_action( 'ricoman_lead_captured', array(
		'name'      => $name,
		'email'     => $email,
		'company'   => $company,
		'phone'     => $phone,
		'role'      => '',
		'message'   => $message,
		'source'    => $source,
		'items'     => $code,
		'submitted' => current_time( 'mysql' ),
	), is_wp_error( $lead_id ) ? 0 : (int) $lead_id );

	wp_send_json_success( array( 'ref' => $ref ) );
}
add_action( 'wp_ajax_nopriv_rm_lightbox', 'ricoman_lightbox_capture' );
add_action( 'wp_ajax_rm_lightbox', 'ricoman_lightbox_capture' );

/** Editable pattern so the team can drop the builder onto any page. */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern( 'ricoman/lightbox-builder', array(
		'title'       => 'Light Box Builder',
		'description' => 'Stretched-fabric SEG light box configurator with live preview + quote request (feeds the Leads CRM).',
		'categories'  => array( 'ricoman-page' ),
		'content'     => '<!-- wp:shortcode -->[ricoman_lightbox_builder]<!-- /wp:shortcode -->',
	) );
}, 14 );
