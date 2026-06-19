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

/** One section as a core/shortcode block. */
function ricoman_section_block( $tag ) {
	return "<!-- wp:shortcode -->\n[$tag]\n<!-- /wp:shortcode -->\n";
}

/** The default product layout, as editable section blocks (in order). */
function ricoman_product_layout_blocks() {
	return ricoman_section_block( 'ricoman_section_hero' )
		. ricoman_section_block( 'ricoman_section_specs' )
		. ricoman_section_block( 'ricoman_section_configure' )
		. ricoman_section_block( 'ricoman_section_accessories' )
		. ricoman_section_block( 'ricoman_section_related' )
		. ricoman_section_block( 'ricoman_section_cta' );
}

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

	$sc = 'ricoman_section_block';

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
			'content'    => ricoman_product_layout_blocks(),
		)
	);
} );

/**
 * Per-product layout control. A side meta box on every product editor lets the
 * admin convert THAT product (and only that product) from the automatic layout
 * to editable section blocks — one click — so they can rearrange the sections
 * and drop their own patterns between them for that product alone. Other
 * products are untouched and keep auto-rendering.
 */
add_action( 'add_meta_boxes_product', function ( $post ) {
	add_meta_box(
		'ricoman_product_layout',
		__( 'Page layout', 'ricoman' ),
		'ricoman_product_layout_box',
		'product',
		'side',
		'high'
	);
} );

function ricoman_product_layout_box( $post ) {
	$has_blocks = '' !== trim( wp_strip_all_tags( (string) $post->post_content ) );
	wp_nonce_field( 'ricoman_product_layout', 'ricoman_product_layout_nonce' );
	if ( $has_blocks ) {
		echo '<p><strong>' . esc_html__( '✓ Editable layout', 'ricoman' ) . '</strong></p>';
		echo '<p class="description">' . esc_html__( 'This product is built from editable section blocks. Rearrange them in the editor, or drop your own patterns between sections — changes apply to this product only.', 'ricoman' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'To return to the automatic layout, delete all blocks and update.', 'ricoman' ) . '</p>';
		return;
	}
	echo '<p class="description">' . esc_html__( 'This product renders its sections automatically.', 'ricoman' ) . '</p>';
	echo '<p><label><input type="checkbox" name="ricoman_make_editable" value="1"> <strong>' . esc_html__( 'Make this layout editable', 'ricoman' ) . '</strong></label></p>';
	echo '<p class="description">' . esc_html__( 'Fills the editor with the default sections (Hero, Specification, Configure, Accessories, You may also like, CTA) as separate blocks so you can reorder them and add patterns. Save, then reload the editor.', 'ricoman' ) . '</p>';
}

add_action( 'save_post_product', function ( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( empty( $_POST['ricoman_product_layout_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ricoman_product_layout_nonce'] ), 'ricoman_product_layout' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) || empty( $_POST['ricoman_make_editable'] ) ) {
		return;
	}
	// Only seed when the product is still on the automatic (empty) layout, so we
	// never overwrite content the admin has already built.
	if ( '' !== trim( wp_strip_all_tags( (string) $post->post_content ) ) ) {
		return;
	}
	static $busy = false;
	if ( $busy ) {
		return;
	}
	$busy = true;
	wp_update_post( array(
		'ID'           => $post_id,
		'post_content' => ricoman_product_layout_blocks(),
	) );
	$busy = false;
}, 10, 2 );
