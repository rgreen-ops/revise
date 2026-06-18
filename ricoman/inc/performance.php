<?php
/**
 * Performance module for the Ricoman theme.
 *
 * Front-end speed without a caching plugin doing the heavy lifting:
 *  - Self-hosted fonts + preload (no Google Fonts round-trip).
 *  - Strips WordPress front-end bloat (emoji, embeds, jQuery Migrate, dashicons,
 *    generator/RSD/WLW/shortlink, classic-theme styles).
 *  - Loads only the block CSS each page actually uses.
 *  - Defers theme JavaScript.
 *  - Speculative prefetch of internal links for near-instant navigation.
 *  - Preconnect to RICOBOT (when configured) for fast live datasheets.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---- Only inline the block CSS a page actually uses ---- */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/* ---- Preload the two key font weights (body + semibold) ---- */
add_action( 'wp_head', function () {
	$weights = array( '400', '600' );
	foreach ( $weights as $w ) {
		$url = get_theme_file_uri( "assets/fonts/poppins-{$w}.woff2" );
		echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( $url ) . '" crossorigin>' . "\n";
	}
}, 1 );

/* ---- Prioritise the LCP hero image without forcing a fixed size ----
 * The first Cover / image on a page is the LCP element. Preloading one fixed
 * 2000px size overrode the responsive srcset and made phones download the huge
 * hero (9s+ LCP). Instead, mark the actual rendered hero image
 * fetchpriority="high" + eager so the browser still picks the right size from
 * srcset but fetches it first. Applies to every page automatically. */
add_filter( 'render_block', function ( $content, $block ) {
	static $done = false;
	if ( $done || is_admin() || is_feed() ) {
		return $content;
	}
	if ( ! is_singular() && ! is_front_page() && ! is_post_type_archive() && ! is_home() ) {
		return $content;
	}
	$name = isset( $block['blockName'] ) ? $block['blockName'] : '';
	if ( in_array( $name, array( 'core/cover', 'core/post-featured-image' ), true ) && false !== strpos( $content, '<img' ) ) {
		$content = str_replace( ' loading="lazy"', '', $content );
		$content = preg_replace( '/<img (?![^>]*fetchpriority)/', '<img fetchpriority="high" decoding="async" ', $content, 1 );
		$done    = true;
	}
	return $content;
}, 9, 2 );

/* ---- Remove front-end bloat ---- */
add_action( 'init', function () {
	// Emoji.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );

	// Head clutter.
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
} );

/* ---- Drop jQuery Migrate on the front end ---- */
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
		return;
	}
	$deps = $scripts->registered['jquery']->deps;
	$scripts->registered['jquery']->deps = array_values( array_diff( $deps, array( 'jquery-migrate' ) ) );
} );

/* ---- Dequeue unused front-end styles/scripts ---- */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}
	// Block-library "classic" compat sheet (a block theme doesn't need it).
	wp_dequeue_style( 'classic-theme-styles' );
	// oEmbed front-end script.
	wp_dequeue_script( 'wp-embed' );
	// Dashicons for logged-out visitors.
	if ( ! is_user_logged_in() ) {
		wp_dequeue_style( 'dashicons' );
	}
}, 100 );

/* ---- Defer theme JavaScript ---- */
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( is_admin() ) {
		return $tag;
	}
	$defer = array( 'ricoman-anim', 'ricoman-configurator', 'ricoman-my-project' );
	if ( in_array( $handle, $defer, true ) && false === strpos( $tag, ' defer' ) ) {
		$tag = str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}, 10, 2 );

/* ---- Speculative prefetch for near-instant internal navigation ---- */
add_action( 'wp_footer', function () {
	if ( is_admin_bar_showing() && is_user_logged_in() ) {
		// Avoid prefetching admin/edit contexts while logged in.
		return;
	}
	$rules = array(
		'prefetch' => array(
			array(
				'source'    => 'document',
				'where'     => array(
					'and' => array(
						array( 'href_matches' => '/*' ),
						array( 'not' => array( 'href_matches' => array( '/wp-admin/*', '/wp-login.php', '/*\\?*', '/cart/*', '/checkout/*' ) ) ),
						array( 'not' => array( 'selector_matches' => '[rel~="nofollow"]' ) ),
					),
				),
				'eagerness' => 'moderate',
			),
		),
	);
	echo '<script type="speculationrules">' . wp_json_encode( $rules ) . '</script>' . "\n";
} );

/* ---- Preconnect to RICOBOT when configured (fast live datasheets) ---- */
add_filter( 'wp_resource_hints', function ( $urls, $relation_type ) {
	if ( 'preconnect' !== $relation_type ) {
		return $urls;
	}
	$base = function_exists( 'ricoman_ricobot_opt' ) ? ricoman_ricobot_opt( 'url' ) : ( defined( 'RICOMAN_RICOBOT_URL' ) ? RICOMAN_RICOBOT_URL : '' );
	if ( $base ) {
		$host = wp_parse_url( $base, PHP_URL_SCHEME ) . '://' . wp_parse_url( $base, PHP_URL_HOST );
		$urls[] = array(
			'href'        => $host,
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}, 10, 2 );
