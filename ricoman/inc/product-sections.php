<?php
/**
 * Composable product-page sections.
 *
 * The whole product page is built by ricoman_pf_sections() (in acf-product.php)
 * as a map of named fragments. This file exposes each fragment as its own
 * shortcode AND as an insertable block pattern, plus a "Ricoman: Product page"
 * pattern that lays the default order out as separate section blocks.
 *
 * Result: an admin can open a product, insert the "Product page" pattern, then
 * reorder the sections or drop ANY of their own patterns into the gaps between
 * them — full control over the layout — while products left with empty content
 * keep auto-rendering the default page (see the_content filter in acf-product).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emit one named section of the current product. Safe to call anywhere: returns
 * empty unless we're on a product and that section produced output.
 */
function ricoman_section_render( $key ) {
	$pid = get_the_ID();
	if ( ! $pid || 'product' !== get_post_type( $pid ) || ! function_exists( 'ricoman_pf_sections' ) ) {
		return '';
	}
	$s = ricoman_pf_sections( $pid );
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

/** The section shortcodes (one per fragment). */
add_shortcode( 'ricoman_section_hero', function () {
	return ricoman_section_render( 'hero' );
} );
add_shortcode( 'ricoman_section_specs', function () {
	return ricoman_section_render( 'specs' );
} );
add_shortcode( 'ricoman_section_configure', function () {
	return ricoman_section_render( 'configure' );
} );
add_shortcode( 'ricoman_section_accessories', function () {
	return ricoman_section_render( 'accessories' );
} );
add_shortcode( 'ricoman_section_related', function () {
	return ricoman_section_render( 'related' );
} );
add_shortcode( 'ricoman_section_cta', function () {
	return ricoman_section_render( 'cta' );
} );

/**
 * Register the section blocks + the full "Product page" pattern.
 *
 * Each section is a one-line core/shortcode block so it shows as a tidy,
 * reorderable block in the editor; admins drop patterns into the spaces between.
 */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	register_block_pattern_category(
		'ricoman-product',
		array(
			'label'       => __( 'Ricoman: Product page', 'ricoman' ),
			'description' => __( 'Drop-in sections for building a product page.', 'ricoman' ),
		)
	);

	$sc = function ( $tag ) {
		return "<!-- wp:shortcode -->\n[$tag]\n<!-- /wp:shortcode -->\n";
	};

	$sections = array(
		'hero'        => __( 'Product: Hero (gallery + panel)', 'ricoman' ),
		'specs'       => __( 'Product: Specification & details', 'ricoman' ),
		'configure'   => __( 'Product: Configure & order codes', 'ricoman' ),
		'accessories' => __( 'Product: Accessories', 'ricoman' ),
		'related'     => __( 'Product: You may also like', 'ricoman' ),
		'cta'         => __( 'Product: Specify call-to-action', 'ricoman' ),
	);
	foreach ( $sections as $key => $label ) {
		register_block_pattern(
			'ricoman/product-' . $key,
			array(
				'title'      => $label,
				'categories' => array( 'ricoman-product' ),
				'postTypes'  => array( 'product' ),
				'content'    => $sc( 'ricoman_section_' . $key ),
			)
		);
	}

	// The whole page, laid out as separate section blocks you can rearrange.
	register_block_pattern(
		'ricoman/product-page',
		array(
			'title'      => __( 'Ricoman: Product page (full layout)', 'ricoman' ),
			'categories' => array( 'ricoman-product' ),
			'postTypes'  => array( 'product' ),
			'content'    => $sc( 'ricoman_section_hero' )
				. $sc( 'ricoman_section_specs' )
				. $sc( 'ricoman_section_configure' )
				. $sc( 'ricoman_section_accessories' )
				. $sc( 'ricoman_section_related' )
				. $sc( 'ricoman_section_cta' ),
		)
	);
} );

/**
 * Offer the layout as a one-click starting point: on an empty product, show a
 * notice in the editor explaining how to take control of the layout. (Products
 * with no content keep auto-rendering, so this is purely opt-in.)
 */
add_action( 'admin_notices', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product' !== $screen->post_type || 'post' !== $screen->base ) {
		return;
	}
	echo '<div class="notice notice-info"><p><strong>Ricoman product layout:</strong> '
		. 'this product renders its sections automatically. To rearrange them or drop '
		. 'patterns between sections, add the <em>“Ricoman: Product page (full layout)”</em> '
		. 'pattern (➕ &rarr; Patterns &rarr; “Ricoman: Product page”), then move the section '
		. 'blocks or insert your own patterns into the gaps.</p></div>';
} );
