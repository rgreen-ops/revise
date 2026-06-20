<?php
/**
 * Ricoman theme functions.
 *
 * A block (Full Site Editing) theme, so most setup is handled by theme.json.
 * This file wires up the few things that still need PHP: assets, fonts,
 * editor support and the block pattern categories used by the bundled patterns.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

if ( ! defined( 'RICOMAN_VERSION' ) ) {
	define( 'RICOMAN_VERSION', '1.1.0' );
}

/**
 * Release any stray PHP session lock. A plugin (e.g. a form plugin) can call
 * session_start() and leave the session open; the lock then blocks WordPress's
 * REST API loopback request, which times out (Site Health flags both an "active
 * PHP session" and a "REST API error"). WordPress recommends session_write_close()
 * before HTTP requests, so we release it once init has fully run. Reads still work.
 */
add_action( 'init', function () {
	if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
		session_write_close();
	}
}, PHP_INT_MAX );

/**
 * Load feature modules. Each file is self-contained and hooks itself in.
 */
require_once get_theme_file_path( 'inc/image-fallback.php' ); // Missing-media fallback to live origin (staging).
require_once get_theme_file_path( 'inc/post-types.php' );    // Products, Projects, Leads.
require_once get_theme_file_path( 'inc/acf-fields.php' );     // Original ricoman.com ACF field groups (editing + repeater reads).
require_once get_theme_file_path( 'inc/meta.php' );          // Product specs & variants.
require_once get_theme_file_path( 'inc/performance.php' );   // Speed: fonts, bloat removal, prefetch.
require_once get_theme_file_path( 'inc/images.php' );        // Auto web-ready images (AVIF/WebP, alt text).
require_once get_theme_file_path( 'inc/media-cleanup.php' );  // Media library de-duplication toolkit.
require_once get_theme_file_path( 'inc/gallery-classify.php' );// Bulk Studio / In-situ image sorter.
require_once get_theme_file_path( 'inc/error-page.php' );     // Friendly branded fatal-error page.
require_once get_theme_file_path( 'inc/login.php' );         // Ricoman-branded wp-login screen.
require_once get_theme_file_path( 'inc/lead-gate.php' );     // Download lead-gate (gate docs behind name/email/type).
require_once get_theme_file_path( 'inc/accounts.php' );      // Customer accounts: name + customer type on register/profile.
require_once get_theme_file_path( 'inc/project-lists.php' ); // Saved multi-project lists + project-pack ZIP (logged-in).
require_once get_theme_file_path( 'inc/seo.php' );           // JSON-LD schema & breadcrumbs.
require_once get_theme_file_path( 'inc/seo-score.php' );     // SEO scoring + back-office dashboard.
require_once get_theme_file_path( 'inc/geo.php' );           // Generative SEO (AI search): FAQ, llms.txt.
require_once get_theme_file_path( 'inc/transporter.php' );   // Content migration tool.
require_once get_theme_file_path( 'inc/ricobot.php' );       // RICOBOT API settings + client.
require_once get_theme_file_path( 'inc/datasheet.php' );     // Printable / PDF datasheets.
require_once get_theme_file_path( 'inc/lead-capture.php' );  // Lead form + Sheets webhook.
require_once get_theme_file_path( 'inc/shortcodes.php' );    // Product spec/variant/datasheet output.
require_once get_theme_file_path( 'inc/product-builder.php' );// Product Builder: RICOBOT sync + field shortcodes.
require_once get_theme_file_path( 'inc/product-fields.php' ); // Structured product content (variants/zigzag/paragraphs) as fields.
require_once get_theme_file_path( 'inc/acf-product.php' );    // Render existing ACF products (empty content) in the new design.
require_once get_theme_file_path( 'inc/product-sections.php' );// Composable product-page section blocks/patterns.
require_once get_theme_file_path( 'inc/product-editor.php' );  // Custom Product Page Editor (edit + live preview).
require_once get_theme_file_path( 'inc/product-templates.php' );// Product page templates (Standard/Flow/Estrella) + inheritance.
require_once get_theme_file_path( 'inc/variant-csv.php' );    // Variant CSV import / export (Import/Export Variable Product).
require_once get_theme_file_path( 'inc/variant-specs.php' );  // Variant Specifications hub (manage axis taxonomies).
require_once get_theme_file_path( 'inc/acf-pages.php' );      // Render migrated ACF content pages natively (no Elementor).
require_once get_theme_file_path( 'inc/news.php' );           // News master listing + single article.
require_once get_theme_file_path( 'inc/sector.php' );         // Sector landing pages (SEO + conversion + lead-gen).
require_once get_theme_file_path( 'inc/redirects.php' );      // Links & Redirects manager + 404 watch.
require_once get_theme_file_path( 'inc/projects.php' );       // Project single (short/long) from ACF.
require_once get_theme_file_path( 'inc/product-filter.php' ); // Category archive grid + faceted filters (lumens/watts/features).
require_once get_theme_file_path( 'inc/configurator.php' );   // Live variant configurator (RICOBOT price/options).
require_once get_theme_file_path( 'inc/product-patterns.php' );// Product page blocks (Hero/Specs/Configurator/…).
require_once get_theme_file_path( 'inc/family.php' );         // Family filter page (facets -> pick -> configure).
require_once get_theme_file_path( 'inc/specs-live.php' );     // Configurator-aware Specification + Accessories blocks.
require_once get_theme_file_path( 'inc/site-options.php' );   // Ricoman admin: mega menu, footer, tracking code.
require_once get_theme_file_path( 'inc/header-footer.php' );  // [ricoman_header] / [ricoman_footer] renderers.
require_once get_theme_file_path( 'inc/flow-patterns.php' );  // Flow+ page sections as editable blocks.
require_once get_theme_file_path( 'inc/page-patterns.php' );  // Home/page sections as editable blocks.
require_once get_theme_file_path( 'inc/my-project.php' );    // "My Project" specification list (Toolbox).
require_once get_theme_file_path( 'inc/demo-setup.php' );    // One-time: create linked pages + pretty links.
require_once get_theme_file_path( 'inc/pattern-library.php' );// 25+ ready-made section patterns.
require_once get_theme_file_path( 'inc/admin.php' );         // Branded admin: Control Center, widget, login.

/**
 * Theme setup.
 */
function ricoman_setup() {
	// Let WordPress manage the document title.
	add_theme_support( 'title-tag' );

	// Featured images.
	add_theme_support( 'post-thumbnails' );

	// Responsive embeds and editor styles.
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );

	// Make the front-end style sheets available inside the block editor too, so
	// templates/pages render in the editor exactly as they do on the live site.
	add_editor_style( array( 'assets/css/fonts.css', 'assets/css/shared.css', 'assets/css/ricoman.css' ) );

	// Translations.
	load_theme_textdomain( 'ricoman', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'ricoman_setup' );

/**
 * Site favicon — use the bundled icon unless an admin has set one in the Customizer.
 */
add_action( 'wp_head', function () {
	if ( get_option( 'site_icon' ) ) {
		return;
	}
	$f = esc_url( get_theme_file_uri( 'assets/images/favicon.png' ) );
	echo '<link rel="icon" href="' . $f . '" sizes="any">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . $f . '">' . "\n";
}, 2 );

/**
 * Flag pages that open with a dark hero so the header overlays transparently;
 * everything else gets a solid dark header bar (see ricoman.css).
 */
add_filter( 'body_class', function ( $classes ) {
	// NB: product single pages open with a light split hero (not a dark cover),
	// so they keep the solid header (not added to rm-hero). Project singles and
	// the listing archives do open on dark imagery, so they overlay.
	if ( is_front_page() || is_post_type_archive( array( 'product', 'project' ) ) || is_singular( 'project' ) || is_tax( 'project-cat' ) ) {
		$classes[] = 'rm-hero';
	} elseif ( is_singular() || is_page() ) {
		$post = get_post();
		if ( $post ) {
			$c    = ltrim( $post->post_content );
			$top  = substr( $c, 0, 400 );
			// Bespoke hero classes, or a native full-bleed Cover as the opening block.
			if ( false !== strpos( $post->post_content, 'phero' ) || false !== strpos( $post->post_content, 'chero' ) || false !== strpos( $post->post_content, 'class="hero"' )
				|| 0 === strpos( $c, '<!-- wp:cover' ) || ( false !== strpos( $top, '<!-- wp:cover' ) && false !== strpos( $top, 'alignfull' ) ) ) {
				$classes[] = 'rm-hero';
			}
		}
	}
	return $classes;
} );

/**
 * Cache-busting asset version: the file's modification time, so every CSS/JS
 * change is fetched fresh by browsers (a fixed version string was causing stale
 * styles to persist across deploys).
 */
function ricoman_asset_ver( $rel ) {
	$f = get_theme_file_path( $rel );
	return file_exists( $f ) ? (string) filemtime( $f ) : RICOMAN_VERSION;
}

/**
 * Enqueue front-end assets: the theme stylesheet, a small shared stylesheet
 * and the Inter / Inter Tight web fonts used across the design.
 */
function ricoman_enqueue_assets() {
	// Main theme stylesheet (the header comment + small tweaks in style.css).
	wp_enqueue_style(
		'ricoman-style',
		get_stylesheet_uri(),
		array(),
		ricoman_asset_ver( 'style.css' )
	);

	// Shared utility styles used on the front end and in the editor.
	wp_enqueue_style(
		'ricoman-shared',
		get_theme_file_uri( 'assets/css/shared.css' ),
		array( 'ricoman-style' ),
		ricoman_asset_ver( 'assets/css/shared.css' )
	);

	// Ported design-system stylesheet (chrome, heroes, sections, cards, footer).
	wp_enqueue_style(
		'ricoman-design',
		get_theme_file_uri( 'assets/css/ricoman.css' ),
		array( 'ricoman-shared' ),
		ricoman_asset_ver( 'assets/css/ricoman.css' )
	);

	// Subtle motion layer (count-up stats + reveal-on-scroll); deferred.
	wp_enqueue_script(
		'ricoman-anim',
		get_theme_file_uri( 'assets/js/ricoman-motion.js' ),
		array(),
		ricoman_asset_ver( 'assets/js/ricoman-motion.js' ),
		true
	);

	// Product gallery interactions (thumbnails, Studio/In-situ tabs, lightbox).
	// Delegated on document, so it works wherever the gallery markup renders.
	wp_enqueue_script(
		'ricoman-product-gallery',
		get_theme_file_uri( 'assets/js/product-gallery.js' ),
		array(),
		ricoman_asset_ver( 'assets/js/product-gallery.js' ),
		true
	);

	// Brand web font — self-hosted Poppins (see inc/performance.php for preload).
	wp_enqueue_style(
		'ricoman-fonts',
		get_theme_file_uri( 'assets/css/fonts.css' ),
		array(),
		RICOMAN_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ricoman_enqueue_assets' );

/**
 * Register the block pattern categories that the bundled patterns slot into.
 */
function ricoman_register_pattern_categories() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}

	register_block_pattern_category(
		'ricoman',
		array(
			'label'       => __( 'Ricoman', 'ricoman' ),
			'description' => __( 'Sections designed for the Ricoman theme.', 'ricoman' ),
		)
	);

	register_block_pattern_category(
		'ricoman-pages',
		array(
			'label'       => __( 'Ricoman: Page sections', 'ricoman' ),
			'description' => __( 'Ready-made hero, feature and call-to-action sections.', 'ricoman' ),
		)
	);
}
add_action( 'init', 'ricoman_register_pattern_categories' );

/**
 * Enqueue the editor-only stylesheet so block styles such as `.is-style-card`
 * preview correctly inside the Site Editor.
 */
function ricoman_editor_assets() {
	wp_enqueue_style(
		'ricoman-editor',
		get_theme_file_uri( 'assets/css/shared.css' ),
		array(),
		RICOMAN_VERSION
	);
}
add_action( 'enqueue_block_editor_assets', 'ricoman_editor_assets' );

/**
 * Register custom block styles (a "Card" group style and a "Pill" button style).
 */
function ricoman_register_block_styles() {
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}

	register_block_style(
		'core/group',
		array(
			'name'  => 'card',
			'label' => __( 'Card', 'ricoman' ),
		)
	);

	register_block_style(
		'core/button',
		array(
			'name'  => 'pill',
			'label' => __( 'Pill', 'ricoman' ),
		)
	);

	register_block_style(
		'core/button',
		array(
			'name'  => 'outline-light',
			'label' => __( 'Outline (light)', 'ricoman' ),
		)
	);
}
add_action( 'init', 'ricoman_register_block_styles' );

/**
 * Safety net for theme shortcodes used inside block patterns / FSE templates.
 *
 * In some block-template render paths (archives, pattern blocks rendered outside
 * the_content) the core/shortcode block can leak its raw text instead of being
 * processed — e.g. "[ricoman_projects_grid count="12"]" printing literally on
 * /projects/. If a rendered block's output still contains one of the theme's own
 * [ricoman_*] tags, run it through do_shortcode so it always renders. The strpos
 * guard keeps this near-free for the vast majority of blocks that don't use one.
 */
add_filter( 'render_block', function ( $html, $block ) {
	if ( is_string( $html ) && false !== strpos( $html, '[ricoman_' ) ) {
		$html = do_shortcode( $html );
	}
	return $html;
}, 20, 2 );
