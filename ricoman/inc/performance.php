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
	$weights = array( '400', '500', '600' );
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
		// Drop native lazy-loading from the hero and prioritise its fetch.
		$content = str_replace( ' loading="lazy"', '', $content );
		$content = preg_replace(
			'/<img (?![^>]*fetchpriority)/',
			'<img fetchpriority="high" decoding="async" loading="eager" data-no-lazy="1" data-skip-lazy ',
			$content,
			1
		);
		// Add the class names common lazy-load optimisers honour, so caching
		// plugins (e.g. WPSpeedster) auto-exclude the hero from lazy loading —
		// no filename list to maintain when the hero image changes. Marks only
		// the first <img> in the block.
		if ( false === strpos( $content, 'rm-hero-img' ) ) {
			if ( preg_match( '/<img[^>]*\sclass="/', $content ) ) {
				$content = preg_replace( '/(<img[^>]*\sclass=")/', '$1skip-lazy no-lazy rm-hero-img ', $content, 1 );
			} else {
				$content = preg_replace( '/<img /', '<img class="skip-lazy no-lazy rm-hero-img" ', $content, 1 );
			}
		}
		$done = true;
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

/* ---- Defer theme JavaScript (all ricoman-* scripts are non-critical) ---- */
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( is_admin() ) {
		return $tag;
	}
	$defer = ( 0 === strpos( $handle, 'ricoman' ) );
	if ( $defer && false === strpos( $tag, ' defer' ) && false === strpos( $tag, ' async' ) ) {
		$tag = str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}, 10, 2 );

/* ---- Drop form-plugin CSS/JS on pages that don't contain a form ----
 * Contact Form 7 / WPForms load their assets site-wide by default. Only load
 * them where a form shortcode/block is actually present. */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}
	$needs_forms = false;
	if ( is_singular() ) {
		$p = get_post();
		if ( $p instanceof WP_Post ) {
			$c = (string) $p->post_content;
			if ( has_shortcode( $c, 'contact-form-7' ) || has_shortcode( $c, 'wpforms' )
				|| false !== strpos( $c, 'wp:contact-form-7' ) || false !== strpos( $c, 'wp:wpforms' )
				|| false !== strpos( $c, 'wpforms-' ) ) {
				$needs_forms = true;
			}
		}
	}
	$needs_forms = apply_filters( 'ricoman_page_needs_forms', $needs_forms );
	if ( $needs_forms ) {
		return;
	}
	foreach ( array( 'contact-form-7', 'wpcf7-recaptcha', 'wpforms-full', 'wpforms-base', 'wpforms-modern-full' ) as $h ) {
		wp_dequeue_style( $h );
		wp_dequeue_script( $h );
	}
}, 200 );

/* ---- Inline the theme's stylesheets to kill render-blocking CSS ----
 * The hero heading is the LCP element and was delayed ~2.7s waiting on the CSS
 * request chain (style.css, shared.css, ricoman.css, fonts.css). Inlining them
 * into <head> removes those blocking requests so the hero paints immediately.
 * Combined size is small (~5KB brotli) and the HTML is page-cached, so there's
 * no real per-request cost. Relative url() paths are rewritten to absolute so
 * fonts/background images still resolve once the CSS lives in the document. */
function ricoman_inline_css_file( $rel ) {
	$path = get_theme_file_path( $rel );
	if ( ! is_readable( $path ) ) {
		return '';
	}
	$css  = (string) file_get_contents( $path );
	$base = trailingslashit( dirname( get_theme_file_uri( $rel ) ) );
	$css  = preg_replace_callback(
		'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
		function ( $m ) use ( $base ) {
			$u = trim( $m[2] );
			if ( '' === $u || preg_match( '#^(data:|https?:|//|/|\#)#i', $u ) ) {
				return $m[0];
			}
			$abs = $base . $u;
			// Collapse "/segment/../" so the URL matches the clean preload path
			// (otherwise the font preload doesn't match and the file downloads twice).
			do {
				$abs = preg_replace( '#/[^/]+/\.\./#', '/', $abs, -1, $count );
			} while ( $count );
			return 'url(' . esc_url( $abs ) . ')';
		},
		$css
	);
	// Conservative minify: strip comments and collapse whitespace runs to a
	// single space (keeps spaces inside calc() etc. intact, so nothing breaks).
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	$css = preg_replace( '/\s+/', ' ', $css );
	$css = str_replace( array( ' { ', '; }', ' }', '{ ', '; ', ': ', ', ' ), array( '{', '}', '}', '{', ';', ':', ',' ), $css );
	return trim( $css );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}
	// Drop the linked versions; they're inlined in wp_head instead.
	foreach ( array( 'ricoman-style', 'ricoman-shared', 'ricoman-design', 'ricoman-fonts' ) as $h ) {
		wp_dequeue_style( $h );
	}
}, 999 );

add_action( 'wp_head', function () {
	if ( is_admin() ) {
		return;
	}
	// Same cascade order as the original enqueue (style → shared → design),
	// with fonts first so @font-face is declared before use. Printed after core
	// block styles so the theme still overrides defaults.
	$css = '';
	foreach ( array( 'assets/css/fonts.css', 'style.css', 'assets/css/shared.css', 'assets/css/ricoman.css' ) as $rel ) {
		$css .= ricoman_inline_css_file( $rel );
	}
	if ( '' !== trim( $css ) ) {
		echo "<style id=\"ricoman-inline-css\">" . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}, 9 );

/* ---- TEMP: request-phase timing on the product archive to find the 40s ---- */
foreach ( array( 'plugins_loaded', 'init', 'wp_loaded', 'wp', 'template_redirect', 'wp_head', 'loop_start', 'loop_end', 'wp_footer' ) as $rm_h ) {
	add_action( $rm_h, function () use ( $rm_h ) {
		if ( ! isset( $GLOBALS['rm_perf_marks'][ $rm_h ] ) ) {
			$GLOBALS['rm_perf_marks'][ $rm_h ] = microtime( true );
		}
	}, 1 );
}
add_action( 'wp_footer', function () {
	if ( ! is_post_type_archive( 'product' ) ) {
		return;
	}
	$start = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : 0;
	$out   = 'REQ=' . round( ( microtime( true ) - $start ) * 1000 ) . 'ms';
	foreach ( (array) ( $GLOBALS['rm_perf_marks'] ?? array() ) as $k => $t ) {
		$out .= " {$k}=" . round( ( $t - $start ) * 1000 ) . 'ms';
	}
	echo "\n<!-- rm-perf " . esc_html( $out ) . " -->\n";
}, 99 );

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
