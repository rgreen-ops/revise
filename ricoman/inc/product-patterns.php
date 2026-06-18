<?php
/**
 * Product page blocks.
 *
 * The product page is built from these insertable blocks (＋ → Patterns →
 * "Ricoman — Product"): Hero, Gallery, Configurator, Specs, Key info,
 * Accessories, Downloads, CTA. Each pulls live data for the linked product, so
 * editors arrange the page (add / remove / reorder) without touching data.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {

	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'ricoman-product', array( 'label' => __( 'Ricoman — Product', 'ricoman' ) ) );
	}

	$sec = function ( $inner, $pad_top = true ) {
		$style = $pad_top ? '' : ' style="padding-top:0"';
		return '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section"' . $style . '>' . $inner . '</div><!-- /wp:group -->';
	};
	$sc = function ( $tag ) { return '<!-- wp:shortcode -->[' . $tag . ']<!-- /wp:shortcode -->'; };

	$p = array();

	$p['product-hero'] = array( 'Product · Hero', '<!-- wp:cover {"useFeaturedImage":true,"dimRatio":55,"overlayColor":"ink","minHeight":70,"minHeightUnit":"vh","contentPosition":"bottom left","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color has-custom-content-position is-position-bottom-left" style="min-height:70vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-50 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:post-terms {"term":"product_cat","className":"rm-eyebrow"} /--><!-- wp:post-title {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.4rem, 6vw, 5rem)","lineHeight":"1"}}} /-->' . $sc( 'ricoman_product_tagline' ) . '</div></div>
<!-- /wp:cover -->' );

	$p['product-gallery'] = array( 'Product · Gallery', $sec( $sc( 'ricoman_product_gallery' ) ) );

	$p['product-configure'] = array( 'Product · Configurator', $sec( $sc( 'ricoman_configurator' ) ) );

	$p['product-specs'] = array( 'Product · Specifications', $sec( $sc( 'ricoman_product_lead' ) . '<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">Specifications</h2><!-- /wp:heading -->' . $sc( 'ricoman_product_specs' ) ) );

	$p['product-keyinfo'] = array( 'Product · Key info', $sec( $sc( 'ricoman_product_features' ) . $sc( 'ricoman_product_finishes' ) ) );

	$p['product-accessories'] = array( 'Product · Accessories', $sec( $sc( 'ricoman_product_accessories' ) ) );

	$p['product-downloads'] = array( 'Product · Downloads', $sec( $sc( 'ricoman_product_downloads' ) ) );

	$p['product-cta'] = array( 'Product · CTA', '<!-- wp:cover {"url":"/wp-content/themes/ricoman/assets/images/office1.jpg","dimRatio":70,"overlayColor":"ink","minHeight":46,"minHeightUnit":"vh","contentPosition":"center center","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color" style="min-height:46vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-70 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="/wp-content/themes/ricoman/assets/images/office1.jpg" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":2,"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Specify this product</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Add it to your project list or request a free lighting scheme.</p><!-- /wp:paragraph --><!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/my-project/">Add to My Project</a></div><!-- /wp:button --><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/lighting-design/">Free Scheme Design</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div></div>
<!-- /wp:cover -->' );

	foreach ( $p as $slug => $data ) {
		register_block_pattern(
			'ricoman/' . $slug,
			array(
				'title'      => $data[0],
				'categories' => array( 'ricoman-product' ),
				'postTypes'  => array( 'product' ),
				'content'    => $data[1],
			)
		);
	}

	// Family filter page block — available on any page (not just products).
	register_block_pattern(
		'ricoman/product-family',
		array(
			'title'      => 'Family · Filter page',
			'categories' => array( 'ricoman-product', 'ricoman-library' ),
			'content'    => $sec( '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Family name</h1><!-- /wp:heading --><!-- wp:shortcode -->[ricoman_family family="Estrella"]<!-- /wp:shortcode -->' ),
		)
	);
}, 11 );

/**
 * The default block stack for a product page (used by the installer seed and as
 * a starting point). Returns block markup referencing the product patterns.
 */
function ricoman_default_product_blocks() {
	$stack = array( 'product-hero', 'product-specs', 'product-configure', 'product-gallery', 'product-keyinfo', 'product-accessories', 'product-downloads', 'product-cta' );
	$out   = '';
	foreach ( $stack as $slug ) {
		$out .= '<!-- wp:pattern {"slug":"ricoman/' . $slug . '"} /-->' . "\n";
	}
	return $out;
}
