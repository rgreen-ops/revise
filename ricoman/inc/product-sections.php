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

/** The product sections, in default order: key => editor label. */
function ricoman_section_defs() {
	return array(
		'hero'        => __( 'Product: Hero (gallery + panel)', 'ricoman' ),
		'specs'       => __( 'Product: Specification & details', 'ricoman' ),
		'configure'   => __( 'Product: Configure & order codes', 'ricoman' ),
		'accessories' => __( 'Product: Accessories', 'ricoman' ),
		'related'     => __( 'Product: You may also like', 'ricoman' ),
		'cta'         => __( 'Product: Specify call-to-action', 'ricoman' ),
	);
}

/** The default product layout, as live-preview section blocks (in order). */
function ricoman_product_layout_blocks() {
	$out = '';
	foreach ( array_keys( ricoman_section_defs() ) as $key ) {
		$out .= '<!-- wp:ricoman/product-' . $key . ' /-->' . "\n";
	}
	return $out;
}

/**
 * Server render for a section block. On the front end the product is the current
 * post; inside the editor it renders through the REST block-renderer, which sets
 * up the post from the `post_id` query arg (we also read it as a fallback) so the
 * preview shows the real product.
 */
function ricoman_section_block_render( $key ) {
	$pid = get_the_ID();
	if ( ( ! $pid || 'product' !== get_post_type( $pid ) ) && ! empty( $_REQUEST['post_id'] ) ) {
		$pid = (int) $_REQUEST['post_id'];
	}
	$labels = ricoman_section_defs();
	if ( ! $pid || 'product' !== get_post_type( $pid ) || ! function_exists( 'ricoman_pf_sections' ) ) {
		return '<div class="rm-block-ph" style="padding:22px;border:1px dashed #c9ccd1;border-radius:10px;color:#6b7280;font:14px/1.5 system-ui,sans-serif">'
			. esc_html( isset( $labels[ $key ] ) ? $labels[ $key ] : $key ) . ' — ' . esc_html__( 'shows on the live product page.', 'ricoman' ) . '</div>';
	}
	$s    = ricoman_pf_sections( $pid );
	$html = isset( $s[ $key ] ) ? $s[ $key ] : '';
	if ( '' === trim( (string) $html ) ) {
		return '<div class="rm-block-ph" style="padding:16px;border:1px dashed #c9ccd1;border-radius:10px;color:#9096a0;font:13px/1.5 system-ui,sans-serif">'
			. esc_html( isset( $labels[ $key ] ) ? $labels[ $key ] : $key ) . ' — ' . esc_html__( 'no content for this product yet.', 'ricoman' ) . '</div>';
	}
	return $html;
}

/**
 * Register the live-preview section blocks + the full "Product page" pattern.
 * Each section is a dynamic block that renders server-side, so the editor canvas
 * shows a real preview and the ➕ between blocks inserts patterns anywhere.
 */
add_action( 'init', function () {
	register_block_pattern_category(
		'ricoman-product',
		array(
			'label'       => __( 'Ricoman: Product page', 'ricoman' ),
			'description' => __( 'Drop-in sections for building a product page.', 'ricoman' ),
		)
	);

	if ( function_exists( 'register_block_type' ) ) {
		foreach ( array_keys( ricoman_section_defs() ) as $key ) {
			register_block_type( 'ricoman/product-' . $key, array(
				'api_version'     => 2,
				'category'        => 'ricoman-product',
				'render_callback' => function () use ( $key ) {
					return ricoman_section_block_render( $key );
				},
				'supports'        => array( 'html' => false, 'reusable' => false, 'multiple' => false ),
			) );
		}
	}

	// The whole page, laid out as separate section blocks you can rearrange.
	register_block_pattern(
		'ricoman/product-page',
		array(
			'title'      => __( 'Ricoman: Product page (full layout)', 'ricoman' ),
			'categories' => array( 'ricoman-product' ),
			'postTypes'  => array( 'product' ),
			'content'    => ricoman_product_layout_blocks(),
		)
	);
} );

/** Give the section blocks their own editor category. */
add_filter( 'block_categories_all', function ( $cats ) {
	foreach ( $cats as $c ) {
		if ( isset( $c['slug'] ) && 'ricoman-product' === $c['slug'] ) {
			return $cats;
		}
	}
	$cats[] = array(
		'slug'  => 'ricoman-product',
		'title' => __( 'Ricoman: Product page', 'ricoman' ),
	);
	return $cats;
} );

/** Editor script that registers the blocks with a live ServerSideRender preview. */
add_action( 'enqueue_block_editor_assets', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_script(
		'ricoman-product-blocks',
		get_theme_file_uri( 'assets/js/product-blocks.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-data', 'wp-i18n' ),
		defined( 'RICOMAN_VERSION' ) ? RICOMAN_VERSION : false,
		true
	);
} );

/**
 * Open every product straight into the live layout. Setting a block-editor
 * template on the product post type means an empty product's editor is
 * pre-filled with the section blocks — each rendering a real preview — so the
 * admin immediately sees the page and can click ➕ between sections to drop in
 * their own patterns. Nothing is saved until they hit Save, and the template is
 * ignored for products that already have their own block content. Products never
 * opened/saved keep auto-rendering (empty content) exactly as before.
 */
add_filter( 'register_post_type_args', function ( $args, $pt ) {
	if ( 'product' !== $pt ) {
		return $args;
	}
	$tpl = array();
	foreach ( array_keys( ricoman_section_defs() ) as $key ) {
		$tpl[] = array( 'ricoman/product-' . $key );
	}
	$args['template'] = $tpl;
	// Leave it unlocked so sections can be reordered, removed, or have patterns
	// and other blocks inserted between them.
	$args['template_lock'] = false;
	if ( empty( $args['show_in_rest'] ) ) {
		$args['show_in_rest'] = true; // required for the block editor + previews.
	}
	return $args;
}, 20, 2 );

/**
 * A short note in the product editor sidebar explaining the live layout, so the
 * preview blocks aren't mistaken for stray content.
 */
add_action( 'add_meta_boxes_product', function () {
	add_meta_box(
		'ricoman_product_layout',
		__( 'Page layout', 'ricoman' ),
		'ricoman_product_layout_box',
		'product',
		'side',
		'high'
	);
} );

function ricoman_product_layout_box() {
	echo '<p class="description">' . esc_html__( 'The page above is built from live-preview section blocks — Hero, Specification, Configure, Accessories, You may also like, CTA.', 'ricoman' ) . '</p>';
	echo '<p class="description">' . esc_html__( 'Drag to reorder them, delete any you don’t want, or click the ➕ between sections to drop in your own pattern. Then Save.', 'ricoman' ) . '</p>';
	echo '<p class="description">' . esc_html__( 'Leave it untouched to keep the standard automatic layout.', 'ricoman' ) . '</p>';
}
