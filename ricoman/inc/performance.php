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
	// Preload the products archive hero (LCP). A lazy-load plugin was deferring
	// it (data-src), so it wasn't discoverable in the initial HTML — preloading
	// makes the LCP image fetch immediately regardless.
	if ( is_post_type_archive( 'product' ) ) {
		$hero = get_theme_file_uri( 'assets/images/arch-line.webp' );
		echo '<link rel="preload" as="image" href="' . esc_url( $hero ) . '" fetchpriority="high">' . "\n";
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

/* ---- Lazy-load every image except the marked hero/LCP ----
 * The homepage and other pages are FSE templates, so their Cover section
 * backgrounds skip WordPress' native lazy-loading — every section image
 * (retail/workshop/office, ~600KB) was downloading eagerly and competing with
 * the hero. Defer anything not explicitly kept eager (the hero filter above
 * marks the LCP skip-lazy/fetchpriority; product heroes are marked too).
 * Idempotent: once loading= is present the image is left alone. */
add_filter( 'render_block', function ( $content ) {
	if ( is_admin() || is_feed() || false === strpos( $content, '<img' ) ) {
		return $content;
	}
	return preg_replace_callback( '/<img\b[^>]*>/i', function ( $m ) {
		static $hero_set = false;
		$tag = $m[0];
		if ( preg_match( '/\bloading=|\bfetchpriority=|data-no-lazy|data-skip-lazy|skip-lazy|no-lazy/i', $tag ) ) {
			$hero_set = true; // an explicitly-marked hero already exists this render.
			return $tag;
		}
		// LCP: the first SIZEABLE image is the largest-contentful-paint candidate,
		// so load it eagerly + high-priority instead of lazily. Lazy-loading the
		// LCP image is a top cause of slow mobile LCP — pages that don't use an FSE
		// "cover" hero (e.g. news articles) had their lead image lazied. Small
		// chrome (logo/icons) never qualifies, so it stays lazy.
		if ( ! $hero_set ) {
			$w = 0; $h = 0;
			if ( preg_match( '/\bwidth=["\']?(\d+)/i', $tag, $wm ) ) { $w = (int) $wm[1]; }
			if ( preg_match( '/\bheight=["\']?(\d+)/i', $tag, $hm ) ) { $h = (int) $hm[1]; }
			if ( 0 === $w && preg_match( '/-(\d{2,4})x(\d{2,4})\.(?:jpe?g|png|webp|gif|avif)/i', $tag, $dm ) ) {
				$w = (int) $dm[1]; $h = (int) $dm[2];
			}
			if ( $w >= 300 || $h >= 300 ) {
				$hero_set = true;
				$add = ' fetchpriority="high" loading="eager"';
				if ( false === stripos( $tag, 'decoding=' ) ) { $add .= ' decoding="async"'; }
				return str_replace( '<img', '<img' . $add, $tag );
			}
		}
		$add = ' loading="lazy"';
		if ( false === stripos( $tag, 'decoding=' ) ) {
			$add .= ' decoding="async"';
		}
		return str_replace( '<img', '<img' . $add, $tag );
	}, $content );
}, 11 );

/* ---- Stop layout shift (CLS): reserve image space up front ----
 * Google flagged CLS > 0.1 on the homepage + news articles — images that
 * arrive without intrinsic dimensions reflow the page as they load. WordPress
 * names its scaled files name-WIDTHxHEIGHT.ext, so we can restore width/height
 * on any <img> missing them (content images, card thumbnails, featured images)
 * and the browser reserves the box before the pixels arrive. Paired with the
 * `img{height:auto}` rule below so the reserved box scales with the layout
 * instead of distorting. Idempotent; images that already carry a dimension,
 * or whose file name has no size suffix, are left untouched. */
function ricoman_img_reserve_space( $html ) {
	if ( ! is_string( $html ) || false === strpos( $html, '<img' ) ) {
		return $html;
	}
	return preg_replace_callback( '/<img\b[^>]*>/i', function ( $m ) {
		$tag = $m[0];
		if ( preg_match( '/\b(width|height)=/i', $tag ) ) {
			return $tag; // already dimensioned — leave as-is.
		}
		$src = '';
		if ( preg_match( '/\ssrc="([^"]+)"/i', $tag, $s ) ) {
			$src = $s[1];
		}
		// Lazy markup can park a placeholder in src and the real file in data-src.
		if ( ( '' === $src || 0 === strpos( $src, 'data:' ) ) && preg_match( '/\sdata-src="([^"]+)"/i', $tag, $d ) ) {
			$src = $d[1];
		}
		if ( preg_match( '/-(\d{2,4})x(\d{2,4})\.(?:jpe?g|png|webp|gif|avif)/i', $src, $wh ) ) {
			return str_replace( '<img', '<img width="' . (int) $wh[1] . '" height="' . (int) $wh[2] . '"', $tag );
		}
		// Full-size uploads with no -WxH suffix (theme-rendered product/banner
		// images): look up the attachment's real dimensions once (cached, capped).
		$dims = ricoman_img_dims_for_url( $src );
		if ( $dims ) {
			return str_replace( '<img', '<img width="' . $dims[0] . '" height="' . $dims[1] . '"', $tag );
		}
		return $tag;
	}, $html );
}

/**
 * Real pixel dimensions for an uploads image URL, cached and budget-capped so a
 * large grid never hammers the database. Returns array( width, height ) or null.
 */
function ricoman_img_dims_for_url( $url ) {
	static $cache  = array();
	static $budget = 80;
	$key = strtok( (string) $url, '?' );
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	if ( $budget <= 0 || false === strpos( $key, '/wp-content/uploads/' ) ) {
		return $cache[ $key ] = null;
	}
	$budget--;
	$out = null;
	$id  = attachment_url_to_postid( $key );
	if ( $id ) {
		$meta = wp_get_attachment_metadata( $id );
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			$out = array( (int) $meta['width'], (int) $meta['height'] );
		}
	}
	// Fallback for images not tracked as a clean attachment (e.g. oddly-named
	// imports): read the real dimensions from the local file header. Cheap.
	if ( ! $out ) {
		$up = wp_get_upload_dir();
		if ( ! empty( $up['baseurl'] ) && ! empty( $up['basedir'] ) && 0 === strpos( $key, $up['baseurl'] ) ) {
			$path = $up['basedir'] . substr( $key, strlen( $up['baseurl'] ) );
			if ( is_readable( $path ) ) {
				$sz = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( $sz && ! empty( $sz[0] ) && ! empty( $sz[1] ) ) {
					$out = array( (int) $sz[0], (int) $sz[1] );
				}
			}
		}
	}
	return $cache[ $key ] = $out;
}
add_filter( 'render_block', 'ricoman_img_reserve_space', 12 );
add_filter( 'the_content', 'ricoman_img_reserve_space', 12 );
add_filter( 'post_thumbnail_html', 'ricoman_img_reserve_space', 12 );
add_filter( 'get_avatar', 'ricoman_img_reserve_space', 12 );
// Low-specificity default so a width/height'd image scales to its column and
// keeps its ratio (reserving the box) rather than rendering at a fixed height.
// Theme rules with real selectors still win. Emitted very early.
add_action( 'wp_head', function () {
	echo '<style id="rm-cls-guard">img{max-width:100%;height:auto}</style>' . "\n";
}, 1 );

/* ---- Long-cache uploaded media (repeat visits + PageSpeed) ----
 * wp-content/uploads is served straight by Apache with NO Cache-Control, so
 * every visit re-downloads every product/content image. Mirror the theme's
 * own proven bundled-asset .htaccess (which already caches for a year on this
 * server) into the uploads folder. IfModule-guarded, so it's a safe no-op where
 * mod_expires/mod_headers are absent. Written once, idempotently, on admin load.
 * (No "immutable" here — unlike versioned theme assets, an image could be
 * replaced at the same URL, so allow revalidation.) */
add_action( 'admin_init', function () {
	$up   = wp_get_upload_dir();
	$base = isset( $up['basedir'] ) ? $up['basedir'] : '';
	if ( ! $base || ! is_dir( $base ) ) {
		return;
	}
	$file     = trailingslashit( $base ) . '.htaccess';
	$marker   = '# BEGIN Ricoman media cache';
	$existing = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
	if ( false !== strpos( $existing, $marker ) || ! wp_is_writable( $base ) ) {
		return; // already in place, or can't write — leave it.
	}
	$rules = $marker . "\n"
		. "<IfModule mod_expires.c>\n"
		. "  ExpiresActive On\n"
		. "  ExpiresByType image/jpeg \"access plus 1 year\"\n"
		. "  ExpiresByType image/png \"access plus 1 year\"\n"
		. "  ExpiresByType image/webp \"access plus 1 year\"\n"
		. "  ExpiresByType image/avif \"access plus 1 year\"\n"
		. "  ExpiresByType image/gif \"access plus 1 year\"\n"
		. "  ExpiresByType image/svg+xml \"access plus 1 year\"\n"
		. "</IfModule>\n"
		. "<IfModule mod_headers.c>\n"
		. "  <FilesMatch \"\\.(jpe?g|png|webp|avif|gif|svg)$\">\n"
		. "    Header set Cache-Control \"public, max-age=31536000\"\n"
		. "  </FilesMatch>\n"
		. "</IfModule>\n"
		. "# END Ricoman media cache\n";
	@file_put_contents( $file, ( '' !== $existing ? rtrim( $existing ) . "\n\n" : '' ) . $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions
} );

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
function ricoman_inline_css_raw( $css, $base ) {
	$css = preg_replace_callback(
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
		(string) $css
	);
	// Conservative minify: strip comments and collapse whitespace runs to a
	// single space (keeps spaces inside calc() etc. intact, so nothing breaks).
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	$css = preg_replace( '/\s+/', ' ', $css );
	$css = str_replace( array( ' { ', '; }', ' }', '{ ', '; ', ': ', ', ' ), array( '{', '}', '}', '{', ';', ':', ',' ), $css );
	return trim( $css );
}

function ricoman_inline_css_file( $rel ) {
	$path = get_theme_file_path( $rel );
	if ( ! is_readable( $path ) ) {
		return '';
	}
	$base = trailingslashit( dirname( get_theme_file_uri( $rel ) ) );
	return ricoman_inline_css_raw( (string) file_get_contents( $path ), $base );
}

/* ---- Inline WordPress core block stylesheets too (e.g. cover/style.min.css) ----
 * With separate-block-assets on, small core block CSS files load as individual
 * render-blocking requests in <head>. Inline their content (and dequeue them)
 * so they no longer block the first paint. Runs before wp_print_styles (8). */
add_action( 'wp_head', function () {
	if ( is_admin() ) {
		return;
	}
	$styles = wp_styles();
	if ( empty( $styles->queue ) ) {
		return;
	}
	$inc = includes_url();
	$out = '';
	foreach ( (array) $styles->queue as $h ) {
		if ( empty( $styles->registered[ $h ] ) ) {
			continue;
		}
		$src = (string) $styles->registered[ $h ]->src;
		if ( '' === $src ) {
			continue;
		}
		$clean = preg_replace( '/\?.*$/', '', $src );
		// Core block styles only, served from wp-includes/blocks/.
		if ( 0 !== strpos( $clean, $inc ) || false === strpos( $clean, '/blocks/' ) ) {
			continue;
		}
		$path = ABSPATH . WPINC . '/' . substr( $clean, strlen( $inc ) );
		if ( ! is_readable( $path ) ) {
			continue;
		}
		$base = trailingslashit( dirname( $clean ) );
		$out .= ricoman_inline_css_raw( (string) file_get_contents( $path ), $base );
		if ( ! empty( $styles->registered[ $h ]->extra['after'] ) ) {
			foreach ( (array) $styles->registered[ $h ]->extra['after'] as $after ) {
				$out .= (string) $after;
			}
		}
		wp_dequeue_style( $h );
		$styles->done[] = $h;
	}
	if ( '' !== trim( $out ) ) {
		echo "<style id=\"ricoman-core-blocks-css\">" . $out . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}, 7 );

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
	$files = array( 'assets/css/fonts.css', 'style.css', 'assets/css/shared.css', 'assets/css/ricoman.css' );
	// Cache the combined+minified CSS so we don't re-read and re-minify every
	// request (cuts server response time). Keyed by the files' modification times,
	// so editing any stylesheet rebuilds it automatically.
	$ver = '';
	foreach ( $files as $rel ) {
		$p    = get_theme_file_path( $rel );
		$ver .= $rel . ( file_exists( $p ) ? (string) filemtime( $p ) : '0' );
	}
	$key = 'ricoman_inline_css_' . md5( $ver );
	$css = get_transient( $key );

	// A healthy combined build is ~150KB. Rebuild if the transient is missing OR
	// looks truncated — the latter happens when a request lands mid-deploy while
	// a stylesheet is still being uploaded and file_get_contents returns empty.
	// Crucially, only CACHE a build where every file read cleanly, so a mid-write
	// read can never poison the transient for a week.
	if ( false === $css || strlen( $css ) < 50000 ) {
		$css     = '';
		$healthy = true;
		foreach ( $files as $rel ) {
			$chunk = ricoman_inline_css_file( $rel );
			if ( '' === trim( $chunk ) ) {
				$healthy = false; // a stylesheet was unreadable/empty (mid-upload)
			}
			$css .= $chunk;
		}
		if ( $healthy && strlen( $css ) >= 50000 ) {
			set_transient( $key, $css, WEEK_IN_SECONDS );
		}
	}

	if ( strlen( $css ) >= 50000 ) {
		echo "<style id=\"ricoman-inline-css\">" . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	// Safety net: the inline build wasn't healthy this request (mid-deploy). NEVER
	// ship an unstyled page — load the real stylesheets as ordinary <link>s. This
	// path does not cache, so the next request retries the inline build once the
	// upload has finished.
	$fk = substr( md5( $ver ), 0, 10 );
	foreach ( $files as $rel ) {
		echo '<link rel="stylesheet" class="ricoman-css-fallback" href="' . esc_url( get_theme_file_uri( $rel ) . '?v=' . $fk ) . '" media="all">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}, 9 );

/* ---- Defer + poster the hero background video ----
 * The homepage hero is a full-bleed autoplay background video. Untouched it
 * eagerly downloads the whole file on load (originally 12.6MB from the old
 * production domain) and dominates the payload. Rewrite the cover video so it:
 *   - points at the compressed, theme-hosted copy (1.9MB, same-origin, cached),
 *   - shows a poster still immediately (instant LCP),
 *   - carries no src (moved to data-src) + preload="none" so it never blocks the
 *     first paint; the small loader script swaps it in after window load.
 * Applies to the already-built page content too (it matches the rendered tag). */
add_filter( 'the_content', function ( $html ) {
	if ( is_admin() || false === strpos( $html, 'wp-block-cover__video-background' ) ) {
		return $html;
	}
	$poster = esc_url( get_theme_file_uri( 'assets/images/hero-poster.webp' ) );
	$local  = esc_url( get_theme_file_uri( 'assets/videos/hero-banner.mp4' ) );
	return preg_replace_callback(
		'#<video\b[^>]*\bwp-block-cover__video-background\b[^>]*></video>#i',
		function ( $m ) use ( $poster, $local ) {
			$tag = $m[0];
			if ( preg_match( '/\ssrc="[^"]*"/', $tag ) ) {
				$tag = preg_replace( '/\ssrc="[^"]*"/', ' data-src="' . $local . '"', $tag, 1 );
			} else {
				$tag = str_replace( '<video', '<video data-src="' . $local . '"', $tag );
			}
			if ( false === stripos( $tag, ' preload=' ) ) {
				$tag = preg_replace( '/<video\b/', '<video preload="none"', $tag, 1 );
			}
			if ( false === stripos( $tag, ' poster=' ) ) {
				$tag = preg_replace( '/<video\b/', '<video poster="' . $poster . '"', $tag, 1 );
			}
			// A11y: even a silent, decorative hero video should carry a captions
			// track (empty here — there is no dialogue) to satisfy the check.
			if ( false === stripos( $tag, '<track' ) ) {
				$vtt = esc_url( get_theme_file_uri( 'assets/no-captions.vtt' ) );
				$tag = str_replace( '></video>', '><track kind="captions" src="' . $vtt . '" label="No dialogue"></video>', $tag );
			}
			return $tag;
		},
		$html
	);
}, 20 );

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

/* ---- Preconnect to RICOBOT only when the live product API is in use ----
 * While RICOBOT is parked (ricoman_use_product_api false) the page never
 * requests that origin, so the preconnect is "unused" (a PageSpeed ding). */
add_filter( 'wp_resource_hints', function ( $urls, $relation_type ) {
	if ( 'preconnect' !== $relation_type ) {
		return $urls;
	}
	if ( ! apply_filters( 'ricoman_use_product_api', false ) ) {
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
