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
	$u  = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
	$eyebrow = function ( $t ) { return '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $t . '</p><!-- /wp:paragraph -->'; };
	$shead   = function ( $t ) { return '<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">' . $t . '</h2><!-- /wp:heading -->'; };
	$img     = function ( $url, $alt = '' ) { return '<!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="' . $url . '" alt="' . esc_attr( $alt ) . '"/></figure><!-- /wp:image -->'; };
	$stat    = function ( $l, $v ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">' . $l . '</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-fval"} --><p class="rm-fval">' . $v . '</p><!-- /wp:paragraph --></div><!-- /wp:column -->'; };
	$feature = function ( $img_html, $kick, $h, $body, $items, $rev ) {
		$li = '';
		foreach ( $items as $i => $t ) { $li .= '<!-- wp:list-item --><li>' . $t . '</li><!-- /wp:list-item -->'; }
		$txt = '<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $kick . '</p><!-- /wp:paragraph --><!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">' . $h . '</h2><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color">' . $body . '</p><!-- /wp:paragraph --><!-- wp:list {"className":"rm-flist"} --><ul class="wp-block-list rm-flist">' . $li . '</ul><!-- /wp:list --></div><!-- /wp:column -->';
		$media = '<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $img_html . '</div><!-- /wp:column -->';
		$cols  = $rev ? $txt . $media : $media . $txt;
		return '<!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center">' . $cols . '</div><!-- /wp:columns -->';
	};
	$tile = function ( $url, $sub, $title, $href ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:cover {"url":"' . $url . '","dimRatio":40,"overlayColor":"ink","minHeight":300,"contentPosition":"bottom left","isLink":true,"href":"' . $href . '"} --><div class="wp-block-cover has-custom-content-position is-position-bottom-left" style="min-height:300px"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background" alt="' . esc_attr( $title ) . '" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"className":"rm-eyebrow","textColor":"base"} --><p class="rm-eyebrow has-base-color has-text-color">' . $sub . '</p><!-- /wp:paragraph --><!-- wp:heading {"level":3,"textColor":"base"} --><h3 class="wp-block-heading has-base-color has-text-color">' . $title . '</h3><!-- /wp:heading --></div></div><!-- /wp:cover --></div><!-- /wp:column -->'; };
	$rel  = function ( $url, $small, $title, $href ) use ( $img ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-card"><!-- wp:image {"linkDestination":"custom","sizeSlug":"large"} --><figure class="wp-block-image size-large"><a href="' . $href . '"><img src="' . $url . '" alt="' . esc_attr( $title ) . '"/></a></figure><!-- /wp:image --><!-- wp:paragraph {"className":"rm-eyebrow","fontSize":"small"} --><p class="rm-eyebrow has-small-font-size">' . $small . '</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><a href="' . $href . '">' . $title . '</a></h3><!-- /wp:heading --></div><!-- /wp:group --></div><!-- /wp:column -->'; };

	$p = array();

	$p['product-hero'] = array( 'Product · Hero', '<!-- wp:cover {"useFeaturedImage":true,"dimRatio":50,"overlayColor":"ink","minHeight":70,"minHeightUnit":"vh","contentPosition":"bottom left","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color has-custom-content-position is-position-bottom-left" style="min-height:70vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-50 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:shortcode -->[ricoman_breadcrumbs]<!-- /wp:shortcode --><!-- wp:post-terms {"term":"product-cat","className":"rm-eyebrow"} /--><!-- wp:post-title {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.4rem, 6vw, 5rem)","lineHeight":"1"}}} /-->' . $sc( 'ricoman_product_tagline' ) . '</div></div>
<!-- /wp:cover -->' );

	// Split hero — standard product hero (tabbed gallery + title/desc/features +
	// CTAs + downloads), identical to every other product page so feature ranges
	// (Flow, Estrella, Neptune) share the same top section.
	$p['product-confighero'] = array( 'Product · Hero (gallery + panel)', '<!-- wp:shortcode -->[ricoman_section_hero]<!-- /wp:shortcode -->' );

	// The interactive configurator hero is kept available as its own pattern for
	// products that still want the live build-a-variant split hero.
	$p['product-confighero-live'] = array( 'Product · Configurator hero (split, live)', '<!-- wp:group {"align":"full","className":"rm-cfghero-wrap","layout":{"type":"default"}} --><div class="wp-block-group alignfull rm-cfghero-wrap"><!-- wp:shortcode -->[ricoman_configurator_hero]<!-- /wp:shortcode --></div><!-- /wp:group -->' );

	$p['product-gallery'] = array( 'Product · Gallery', $sec( $sc( 'ricoman_product_gallery' ) ) );

	$p['product-configure'] = array( 'Product · Configurator (single code)', $sec( $sc( 'ricoman_configurator' ) ) );

	// Variant range — lists EVERY product/variant in the linked RICOBOT family,
	// with live filter chips; pick one to configure. Family comes from the
	// Product Builder ("RICOBOT family"), so a bare [ricoman_family] just works.
	$p['product-range'] = array( 'Product · Variant range (whole family)', $sec( '<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">Choose your variant</h2><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color">Filter the full range, then pick a code to configure — datasheet and project actions, no pricing.</p><!-- /wp:paragraph -->' . $sc( 'ricoman_family' ) ) );

	$p['product-specs'] = array( 'Product · Specifications (static)', $sec( $sc( 'ricoman_product_lead' ) . '<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">Specifications</h2><!-- /wp:heading -->' . $sc( 'ricoman_product_specs' ) ) );

	// Live specification — updates to the exact variant the customer configures.
	$p['product-specs-live'] = array( 'Product · Specification (live, follows configurator)', $sec( $sc( 'ricoman_specs_live' ) ) );

	// Selling points — "Why specify {family}", driven live from the RICOBOT family
	// endpoint (sellingPoints[]). Hides itself when Richard hasn't filled them in.
	$p['product-why'] = array( 'Product · Why specify (live selling points)', $sc( 'ricoman_selling_points' ) );

	$p['product-keyinfo'] = array( 'Product · Key info', $sec( $sc( 'ricoman_product_features' ) . $sc( 'ricoman_product_finishes' ) ) );

	$p['product-accessories'] = array( 'Product · Accessories (static)', $sec( $sc( 'ricoman_product_accessories' ) ) );

	// Live accessories — follow the chosen product.
	$p['product-accessories-live'] = array( 'Product · Accessories (live)', $sec( $sc( 'ricoman_accessories_live' ) ) );

	$p['product-downloads'] = array( 'Product · Downloads', $sec( $sc( 'ricoman_product_downloads' ) ) );

	$p['product-cta'] = array( 'Product · CTA', '<!-- wp:cover {"url":"/wp-content/themes/ricoman/assets/images/office1.webp","dimRatio":70,"overlayColor":"ink","minHeight":46,"minHeightUnit":"vh","contentPosition":"center center","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color" style="min-height:46vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-70 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="/wp-content/themes/ricoman/assets/images/office1.webp" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":2,"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Specify this product</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Add it to your project list or request a free lighting scheme.</p><!-- /wp:paragraph --><!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons is-content-justification-center"><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/my-project/">Add to My Project</a></div><!-- /wp:button --><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/lighting-design/">Free Scheme Design</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div></div>
<!-- /wp:cover -->' );

	// ---- Secondary editorial sections (1:1 with the configurator preview) ----
	$p['product-facts'] = array( 'Product · Facts band', '<!-- wp:group {"align":"full","className":"rm-section rm-facts rm-soft","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section rm-facts rm-soft"><!-- wp:columns --><div class="wp-block-columns">' . $stat( 'Output', 'Up to 6,200 lm' ) . $stat( 'Colour', '3000K / 4000K · CRI 90' ) . $stat( 'Optics', 'Opal · UGR&lt;19 · Wallwash' ) . $stat( 'Mounting', 'Surface / Suspended' ) . $stat( 'Warranty', '5 years' ) . '</div><!-- /wp:columns --></div><!-- /wp:group -->' );

	$p['product-statement'] = array( 'Product · Statement', $sec( '<!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.7rem,4vw,3rem)","lineHeight":"1.12"}}} --><h2 class="wp-block-heading" style="font-size:clamp(1.7rem,4vw,3rem);font-weight:500;line-height:1.12">One profile, drawn as a single clean line — surface or suspended, in straight runs, L-shapes and frames, configured to your scheme.</h2><!-- /wp:heading -->' ) );

	$p['product-feature-1'] = array( 'Product · Feature 1 (finish)', $sec( $feature( $img( $u( 'rico-office-render.webp' ), 'Product finishes' ), '01 · Finish', 'Two finishes, one clean line', 'A crisp extruded aluminium housing with a flush opal diffuser — matt black or white as standard, or any RAL to match the interior, with no visible fixings down the run.', array( 'Matt black &amp; white as standard', 'Any RAL finish to order', 'Flush opal, UGR&lt;19, wallwash or louvre optics' ), false ) ) );

	$p['product-feature-2'] = array( 'Product · Feature 2 (continuous runs)', $sec( $feature( $img( $u( 'estrella-lounge.webp' ), 'Continuous run in a breakout space' ), '02 · Continuous runs', 'Built to run line after line', 'Links end-to-end into long, dot-free runs and turns through L-shapes and rectangular frames — so a single specified detail can carry across a whole floorplate.', array( 'Continuous, joint-free diffusion', 'L-shapes &amp; rectangular frames', 'Integral 3hr emergency &amp; indirect up-light options' ), true ) ) );

	$p['product-made'] = array( 'Product · Made in Britain (dark)', '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)"><!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Made to order in Manchester</p><!-- /wp:paragraph --><!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">Built for your scheme, not a catalogue</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Designed, assembled, finished and tested under one roof in Manchester — so lead times stay short and every bespoke detail is in our control.</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $img( $u( 'workshop.webp' ), 'Ricoman manufacturing in Manchester' ) . '</div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->' );

	$p['product-where'] = array( 'Product · Where it works', $sec( $eyebrow( 'Applications' ) . $shead( 'Where it works' ) . '<!-- wp:columns --><div class="wp-block-columns">' . $tile( $u( 'office1.webp' ), 'Workplace · Offices', 'Workplace', '/projects/' ) . $tile( $u( 'retail.webp' ), 'Retail · Accent', 'Retail', '/projects/' ) . $tile( $u( 'office2.webp' ), 'Hospitality', 'Hospitality', '/projects/' ) . '</div><!-- /wp:columns -->' ) );

	$p['product-quote'] = array( 'Product · Quote', '<!-- wp:group {"align":"full","className":"rm-section rm-soft","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section rm-soft"><!-- wp:heading {"textAlign":"center","level":2,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.5rem,3.4vw,2.6rem)","lineHeight":"1.2"}}} --><h2 class="wp-block-heading has-text-align-center" style="font-size:clamp(1.5rem,3.4vw,2.6rem);font-weight:500;line-height:1.2">&ldquo;Specified, delivered and installed without a hitch — and the run looks like one clean line.&rdquo;</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center","className":"rm-eyebrow"} --><p class="has-text-align-center rm-eyebrow">— Project Architect</p><!-- /wp:paragraph --></div><!-- /wp:group -->' );

	$p['product-related'] = array( 'Product · You may also like', $sec( $eyebrow( 'More from the range' ) . $shead( 'You may also like' ) . '<!-- wp:columns --><div class="wp-block-columns">' . $rel( $u( 'arch-line.webp' ), 'Linear · Made to order', 'Flow+', '/products/flow-plus/' ) . $rel( $u( 'estrella-lounge.webp' ), 'Pendant · Configurable', 'Estrella', '/products/estrella/' ) . $rel( $u( 'ceiling.webp' ), 'Downlight · Fire-rated', 'Neptune', '/products/neptune/' ) . '</div><!-- /wp:columns -->' ) );

	// ---- Structured-field sections (edited in the Product page content meta box) ----
	$p['product-colour'] = array( 'Product · Colour variants (fields)', $sec( $sc( 'ricoman_colour_variants' ) ) );
	$p['product-paragraphs'] = array( 'Product · Paragraph info (fields)', $sec( $sc( 'ricoman_paragraphs' ) ) );
	$p['product-zigzag'] = array( 'Product · Zig-zag content (fields)', $sec( $sc( 'ricoman_zigzag' ) ) );
	$p['product-cta-buttons'] = array( 'Product · Lighting Design / Trade buttons (fields)', $sec( $sc( 'ricoman_cta_buttons' ) ) );

	// Media panel: image/video left, title + body right.
	$_img_col  = '<!-- wp:column {"width":"58%","verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:58%"><!-- wp:image {"sizeSlug":"full","className":"size-full"} --><figure class="wp-block-image size-full"><img src="' . $u( 'ceiling.webp' ) . '" alt=""/></figure><!-- /wp:image --></div><!-- /wp:column -->';
	$_txt_l    = '<!-- wp:column {"width":"42%","verticalAlignment":"center","style":{"spacing":{"padding":{"left":"var:preset|spacing|50"}}}} --><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:42%;padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"600","lineHeight":"1.1"}}} --><h2 class="wp-block-heading" style="font-weight:600;line-height:1.1">Your headline goes here.</h2><!-- /wp:heading --><!-- wp:paragraph {"style":{"color":{"text":"#a0a0a0"}}} --><p class="has-text-color" style="color:#a0a0a0">Describe the key feature or benefit here. Replace this with your product copy.</p><!-- /wp:paragraph --></div><!-- /wp:column -->';
	$_txt_r    = '<!-- wp:column {"width":"42%","verticalAlignment":"center","style":{"spacing":{"padding":{"right":"var:preset|spacing|50"}}}} --><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:42%;padding-right:var(--wp--preset--spacing--50)"><!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"600","lineHeight":"1.1"}}} --><h2 class="wp-block-heading" style="font-weight:600;line-height:1.1">Your headline goes here.</h2><!-- /wp:heading --><!-- wp:paragraph {"style":{"color":{"text":"#a0a0a0"}}} --><p class="has-text-color" style="color:#a0a0a0">Describe the key feature or benefit here. Replace this with your product copy.</p><!-- /wp:paragraph --></div><!-- /wp:column -->';
	$_cols_ft  = '</div><!-- /wp:columns --></div><!-- /wp:group -->';
	$_hd_l     = '<!-- wp:group {"align":"full","className":"rm-section rm-mediapanel rm-mediapanel--left","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section rm-mediapanel rm-mediapanel--left"><!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center">';
	$_hd_r     = '<!-- wp:group {"align":"full","className":"rm-section rm-mediapanel rm-mediapanel--right","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section rm-mediapanel rm-mediapanel--right"><!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center">';
	$p['product-mediapanel']     = array( 'Product · Media panel (image / video left)',  $_hd_l . $_img_col . $_txt_l . $_cols_ft );
	$p['product-mediapanel-rev'] = array( 'Product · Media panel (image / video right)', $_hd_r . $_txt_r . $_img_col . $_cols_ft );

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
	$stack = array( 'product-confighero', 'product-facts', 'product-statement', 'product-feature-1', 'product-feature-2', 'product-why', 'product-made', 'product-range', 'product-gallery', 'product-where', 'product-quote', 'product-related', 'product-keyinfo', 'product-accessories-live', 'product-downloads', 'product-cta' );
	$out   = '';
	foreach ( $stack as $slug ) {
		$out .= '<!-- wp:pattern {"slug":"ricoman/' . $slug . '"} /-->' . "\n";
	}
	return $out;
}
