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
	define( 'RICOMAN_VERSION', '1.0.0' );
}

/**
 * Load feature modules. Each file is self-contained and hooks itself in.
 */
require_once get_theme_file_path( 'inc/post-types.php' );    // Products, Projects, Leads.
require_once get_theme_file_path( 'inc/meta.php' );          // Product specs & variants.
require_once get_theme_file_path( 'inc/seo.php' );           // JSON-LD schema & breadcrumbs.
require_once get_theme_file_path( 'inc/datasheet.php' );     // Printable / PDF datasheets.
require_once get_theme_file_path( 'inc/lead-capture.php' );  // Lead form + Sheets webhook.
require_once get_theme_file_path( 'inc/shortcodes.php' );    // Product spec/variant/datasheet output.

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

	// Make the front-end style sheet available inside the block editor too.
	add_editor_style( 'assets/css/shared.css' );

	// Translations.
	load_theme_textdomain( 'ricoman', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'ricoman_setup' );

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
		RICOMAN_VERSION
	);

	// Shared utility styles used on the front end and in the editor.
	wp_enqueue_style(
		'ricoman-shared',
		get_theme_file_uri( 'assets/css/shared.css' ),
		array( 'ricoman-style' ),
		RICOMAN_VERSION
	);

	// Brand web fonts.
	wp_enqueue_style(
		'ricoman-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Inter+Tight:wght@600;700;800;900&display=swap',
		array(),
		null
	);
}
add_action( 'wp_enqueue_scripts', 'ricoman_enqueue_assets' );

/**
 * Preconnect to the Google Fonts hosts so the brand fonts paint quickly.
 *
 * @param array  $urls          URLs to print for resource hints.
 * @param string $relation_type The relation type the URLs are printed for.
 * @return array
 */
function ricoman_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type && wp_style_is( 'ricoman-fonts', 'enqueued' ) ) {
		$urls[] = array(
			'href' => 'https://fonts.googleapis.com',
		);
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'ricoman_resource_hints', 10, 2 );

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
