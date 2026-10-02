<?php
/**
 * Stop the core Shortcode block wrecking shortcode HTML with wpautop.
 *
 * WordPress renders every <!-- wp:shortcode --> block through
 * render_block_core_shortcode(), which is wpautop( do_shortcode( $content ) ).
 * Running wpautop over the EXPANDED output injects stray <p>/</p> tags inside
 * our generated markup — on /products/ that split every rm-pcard <a> into ~3
 * anchors (238 products became 713 grid cells, most of them empty), which is
 * what broke the All Products grid. The projects archive dodged the same bug
 * by avoiding the shortcode block (see patterns/projects-archive.php); this
 * fixes it centrally for every template/part that still uses one.
 *
 * All theme shortcodes emit their own complete HTML (ricoman_products_seo
 * even runs its own wpautop internally), so the block-level autop is never
 * wanted here.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	$block = WP_Block_Type_Registry::get_instance()->get_registered( 'core/shortcode' );
	if ( ! $block ) {
		return;
	}
	$block->render_callback = function ( $attributes, $content ) {
		return do_shortcode( shortcode_unautop( $content ) );
	};
}, 20 );
