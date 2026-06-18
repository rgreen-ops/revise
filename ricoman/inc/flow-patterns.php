<?php
/**
 * Flow+ product-page sections — NATIVE editable patterns.
 *
 * Recreates the original Flow+ page as small, add/remove, click-to-edit blocks
 * under ＋ → Patterns → "Ricoman — Product". Built from core blocks (cover /
 * columns / heading / paragraph / image / list / buttons) styled with the theme
 * design system, so every image and line of text is editable.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {

	$u = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };

	$sec     = function ( $inner, $cls = '' ) { return '<!-- wp:group {"align":"full","className":"rm-section ' . $cls . '","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section ' . $cls . '">' . $inner . '</div><!-- /wp:group -->'; };
	$eyebrow = function ( $t ) { return '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $t . '</p><!-- /wp:paragraph -->'; };
	$shead   = function ( $t ) { return '<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">' . $t . '</h2><!-- /wp:heading -->'; };
	$para    = function ( $t, $muted = false ) { return '<!-- wp:paragraph' . ( $muted ? ' {"textColor":"muted"}' : '' ) . ' --><p' . ( $muted ? ' class="has-muted-color has-text-color"' : '' ) . '>' . $t . '</p><!-- /wp:paragraph -->'; };
	$image   = function ( $url ) { return '<!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="' . $url . '" alt=""/></figure><!-- /wp:image -->'; };
	$btn     = function ( $label, $light = true ) { return '<!-- wp:button {"className":"is-style-outline' . ( $light ? '-light' : '' ) . '"} --><div class="wp-block-button is-style-outline' . ( $light ? '-light' : '' ) . '"><a class="wp-block-button__link wp-element-button" href="#">' . $label . '</a></div><!-- /wp:button -->'; };
	$buttons = function ( $inner, $center = false ) { return '<!-- wp:buttons' . ( $center ? ' {"layout":{"type":"flex","justifyContent":"center"}}' : '' ) . ' --><div class="wp-block-buttons">' . $inner . '</div><!-- /wp:buttons -->'; };
	$cover   = function ( $url, $inner, $min, $pos, $dim ) {
		$poscls = 'center center' === $pos ? '' : ' has-custom-content-position is-position-' . str_replace( ' ', '-', $pos );
		return '<!-- wp:cover {"url":"' . $url . '","dimRatio":' . $dim . ',"overlayColor":"ink","minHeight":' . $min . ',"minHeightUnit":"vh","contentPosition":"' . $pos . '","align":"full","textColor":"base"} --><div class="wp-block-cover alignfull has-base-color has-text-color' . $poscls . '" style="min-height:' . $min . 'vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-' . $dim . ' has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">' . $inner . '</div></div><!-- /wp:cover -->';
	};
	// number-eyebrow card (border-top + heading + paragraph)
	$ncard = function ( $num, $h, $p, $level = 3 ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $num . '</p><!-- /wp:paragraph --><!-- wp:heading {"level":' . $level . '} --><h' . $level . ' class="wp-block-heading">' . $h . '</h' . $level . '><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color">' . $p . '</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->'; };
	$stat  = function ( $n, $l ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">' . $l . '</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-fval"} --><p class="rm-fval">' . $n . '</p><!-- /wp:paragraph --></div><!-- /wp:column -->'; };
	$bigstat = function ( $n, $l ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3,"className":"rm-statnum"} --><h3 class="wp-block-heading rm-statnum">' . $n . '</h3><!-- /wp:heading --><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">' . $l . '</p><!-- /wp:paragraph --></div><!-- /wp:column -->'; };
	$shape = function ( $url, $name ) use ( $image ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-shapecard","layout":{"type":"constrained"}} --><div class="wp-block-group rm-shapecard">' . $image( $url ) . '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">' . $name . '</h4><!-- /wp:heading --></div><!-- /wp:group --></div><!-- /wp:column -->'; };

	$cols = function ( $inner, $atts = '' ) { return '<!-- wp:columns' . ( $atts ? ' ' . $atts : '' ) . ' --><div class="wp-block-columns' . ( false !== strpos( $atts, 'verticalAlignment":"center' ) ? ' are-vertically-aligned-center' : '' ) . '">' . $inner . '</div><!-- /wp:columns -->'; };

	$p = array();

	$p['flow-hero'] = array( 'Flow · Hero', $cover( $u( 'bf-flow3.jpg' ),
		$eyebrow( 'Linear Lighting — Flexible System' ) .
		'<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"clamp(3rem, 8vw, 6rem)","fontWeight":"500","lineHeight":"1"}}} --><h1 class="wp-block-heading" style="font-size:clamp(3rem, 8vw, 6rem);font-weight:500;line-height:1">Flow+</h1><!-- /wp:heading -->' .
		$para( 'A flexible linear system that bends to any architectural line — continuous, dot-free and made to order in Manchester.' ) .
		$buttons( $btn( '＋ Add to My Project' ) . $btn( 'Datasheet ↓' ) ), 86, 'bottom left', 55 ) );

	$p['flow-facts'] = array( 'Flow · Facts band', $sec( $cols( $stat( 'Up to 1100 lm/m', 'Output' ) . $stat( '2700–6500K · CRI 90+', 'Colour' ) . $stat( '360° H &amp; V', 'Bend' ) . $stat( 'Bespoke', 'Lengths' ) . $stat( '5 years', 'Warranty' ) ), 'rm-facts' ) );

	$p['flow-statement'] = array( 'Flow · Statement', $sec( '<!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.9rem,5vw,4rem)","lineHeight":"1.08"}}} --><h2 class="wp-block-heading" style="font-size:clamp(1.9rem,5vw,4rem);font-weight:500;line-height:1.08">Turn any installation into a genuine architectural creation — designed, engineered and built in Britain, to your line.</h2><!-- /wp:heading -->' ) );

	$flist = function ( $items ) { $li = ''; foreach ( $items as $t ) { $li .= '<!-- wp:list-item --><li>' . $t . '</li><!-- /wp:list-item -->'; } return '<!-- wp:list {"className":"rm-flist"} --><ul class="wp-block-list rm-flist">' . $li . '</ul><!-- /wp:list -->'; };

	$p['flow-feature-1'] = array( 'Flow · Feature (image left)', $sec( $cols(
		'<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $image( $u( 'rico-wave.png' ) ) . '</div><!-- /wp:column -->' .
		'<!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"padding":{"left":"var:preset|spacing|50"}}}} --><div class="wp-block-column is-vertically-aligned-center" style="padding-left:var(--wp--preset--spacing--50)">' . $eyebrow( '01 · Flexibility' ) . $shead( 'Shape light to the space' ) . $para( 'Flow+ bends both horizontally and vertically, tracing any architectural line without breaks, hotspots or visible joints.', true ) . $flist( array( 'Horizontal &amp; vertical bending to 360°', 'Continuous, dot-free diffusion', 'Surface, suspended or recessed' ) ) . '</div><!-- /wp:column -->',
		'{"verticalAlignment":"center"}' ) ) );

	$p['flow-feature-2'] = array( 'Flow · Feature (image right)', $sec( $cols(
		'<!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"padding":{"right":"var:preset|spacing|50"}}}} --><div class="wp-block-column is-vertically-aligned-center" style="padding-right:var(--wp--preset--spacing--50)">' . $eyebrow( '02 · Light Quality' ) . $shead( 'Calibrated for interiors' ) . $para( 'High-CRI light with tunable white, factory-calibrated so every metre matches — across a single run or an entire scheme.', true ) . $flist( array( 'CRI 90+ as standard', '2700K – 6500K tunable white', 'DALI &amp; phase dimming' ) ) . '</div><!-- /wp:column -->' .
		'<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $image( $u( 'bf-flow3.jpg' ) ) . '</div><!-- /wp:column -->',
		'{"verticalAlignment":"center"}' ) ) );

	$p['flow-manufacturing'] = array( 'Flow · Manufacturing (dark)', '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)">' . $cols(
		'<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $eyebrow( '03 · In-house Manufacturing' ) . $shead( 'Home to the UK&rsquo;s first automated light-bending machine' ) . $para( 'Flow+ isn&rsquo;t imported and rebadged. It&rsquo;s designed, bent, assembled and tested under one roof in Manchester — on the UK&rsquo;s first automated light-bending machine, so every curve is repeatable to the millimetre.' ) . $buttons( $btn( 'Inside our manufacturing →' ) ) . '</div><!-- /wp:column -->' .
		'<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $image( $u( 'rico-making.webp' ) ) . '</div><!-- /wp:column -->',
		'{"verticalAlignment":"center"}' ) . '<!-- wp:columns {"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} --><div class="wp-block-columns" style="margin-top:var(--wp--preset--spacing--50)">' . $ncard( '01', 'Designed in-house', 'Our UK engineering team develops every Flow+ profile, optic and driver — no rebadging.', 4 ) . $ncard( '02', 'UK-first automated bending', 'The first automated light-bending machine in the UK — precise, repeatable curves at scale.', 4 ) . $ncard( '03', 'Assembled in Manchester', 'Hand-assembled, QA-tested and finished in our own facility before it ships.', 4 ) . '</div><!-- /wp:columns --></div><!-- /wp:group -->' );

	$p['flow-custom'] = array( 'Flow · Customisation', $sec( $eyebrow( '04 · Customisation' ) . $shead( 'Made to order, not off the shelf' ) . $para( 'Because we manufacture in-house, almost everything about Flow+ can be tailored to your scheme — at project quantities, without bespoke-tooling minimums.', true ) .
		'<!-- wp:columns {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} --><div class="wp-block-columns" style="margin-top:var(--wp--preset--spacing--40)">' . $ncard( '01', 'Bespoke runs', 'Cut and joined to your exact dimensions — continuous lengths or repeating modules.' ) . $ncard( '02', 'Any RAL finish', 'Powder-coated housings in white, black or any RAL to match the palette.' ) . $ncard( '03', 'Custom diffusers &amp; optics', 'Opal, frosted or micro-prismatic diffusers and beam shaping for glare control.' ) . '</div><!-- /wp:columns -->' .
		'<!-- wp:columns --><div class="wp-block-columns">' . $ncard( '04', 'Tuned colour', 'Fixed or tunable white calibrated to a specified colour temperature and CRI.' ) . $ncard( '05', 'Mounting &amp; trims', 'Surface, suspended, recessed and plaster-in trims, with custom drops.' ) . $ncard( '06', 'Controls &amp; drivers', 'DALI, DALI-2, phase or 1–10V, pre-wired and zoned to your control layout.' ) . '</div><!-- /wp:columns -->' ) );

	$p['flow-opportunities'] = array( 'Flow · Design opportunities', $sec( $eyebrow( '05 · Design Opportunities' ) . $shead( 'Start from an idea. Or design your own.' ) . $para( 'Every Flow+ run is bent to order, so the only real limit is the drawing. Begin with one of our preset forms — or send us your concept and our team will model it, return a photoreal render, and supply full BIM/Revit files.', true ) .
		'<!-- wp:columns {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} --><div class="wp-block-columns" style="margin-top:var(--wp--preset--spacing--40)">' . $shape( $u( 'shape-arc1.png' ), 'Single inward curve' ) . $shape( $u( 'shape-arc2.png' ), 'Single outward curve' ) . $shape( $u( 'shape-arc3.png' ), 'Arc' ) . $shape( $u( 'rico-wave.png' ), 'Wave' ) . '</div><!-- /wp:columns -->' .
		'<!-- wp:columns --><div class="wp-block-columns">' . $shape( $u( 'shape-arc4.png' ), 'Twist' ) . $shape( $u( 'shape-twist.png' ), 'Inverted circles' ) . $shape( $u( 'shape-abstract.png' ), 'Floral &amp; freeform' ) . $shape( $u( 'rico-office-render.webp' ), 'Your geometry' ) . '</div><!-- /wp:columns -->' .
		$buttons( $btn( 'Request render &amp; BIM →', false ) ) ) );

	$p['flow-team'] = array( 'Flow · Design team', $sec( $eyebrow( '06 · Lighting Design' ) . $shead( 'Our in-house design team, on your project' ) . $para( 'Our UK lighting designers work alongside architects, interior designers and contractors to take a scheme from concept to commissioning — free of charge.', true ) .
		'<!-- wp:columns {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} --><div class="wp-block-columns" style="margin-top:var(--wp--preset--spacing--40)">' . $ncard( '01', 'Consult', 'We review drawings, finishes and the brief with your team.', 4 ) . $ncard( '02', 'Design', 'Relux &amp; DIALux calculations, lux levels and schedules.', 4 ) . $ncard( '03', 'Engineer', 'Photometry, BIM/Revit objects and bespoke detailing.', 4 ) . $ncard( '04', 'Manufacture', 'Made to order in Manchester with full QA testing.', 4 ) . $ncard( '05', 'Support', 'Delivery, on-site support and handover documentation.', 4 ) . '</div><!-- /wp:columns -->', 'rm-soft' ) );

	$p['flow-specband'] = array( 'Flow · Spec band', $cover( $u( 'bf-flow3.jpg' ),
		'<!-- wp:columns -->' . '<div class="wp-block-columns">' . $bigstat( '1100 lm/m', 'Output' ) . $bigstat( '90+', 'CRI' ) . $bigstat( '2700–6500K', 'Tunable' ) . $bigstat( 'IP20–65', 'Rating' ) . $bigstat( '360°', 'Bend' ) . '</div><!-- /wp:columns -->', 40, 'center center', 75 ) );

	$p['flow-mosaic'] = array( 'Flow · Image mosaic', $sec( '<!-- wp:gallery {"columns":3,"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-3 is-cropped">' . $image( $u( 'rico-office-render.webp' ) ) . $image( $u( 'rico-wave.png' ) ) . $image( $u( 'courier.jpg' ) ) . '</figure><!-- /wp:gallery -->' ) );

	$p['flow-quote'] = array( 'Flow · Quote', $sec( '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph {"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.5rem,3vw,2.4rem)","lineHeight":"1.25"}}} --><p style="font-size:clamp(1.5rem,3vw,2.4rem);font-weight:500;line-height:1.25">&ldquo;The Flow+ run was made to our exact ceiling geometry — no compromises.&rdquo;</p><!-- /wp:paragraph --><cite>Specification Architect · Commercial Office Fit-out</cite></blockquote><!-- /wp:quote -->' ) );

	$p['flow-cta'] = array( 'Flow · CTA', $cover( $u( 'office1.jpg' ),
		'<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Specify Flow+ on your next project</h2><!-- /wp:heading -->' .
		'<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Send us your drawings and our in-house lighting designers will return a fully specified, costed, made-to-order Flow+ scheme — usually within 48 hours.</p><!-- /wp:paragraph -->' .
		$buttons( $btn( '＋ Add to My Project' ) . $btn( 'Talk to the design team →', false ), true ), 52, 'center center', 70 ) );

	foreach ( $p as $slug => $data ) {
		register_block_pattern( 'ricoman/' . $slug, array( 'title' => $data[0], 'categories' => array( 'ricoman-product' ), 'content' => $data[1] ) );
	}
}, 12 );

/** Flow+ product page = the original design as a stack of editable blocks. */
function ricoman_flow_product_blocks() {
	$stack = array( 'flow-hero', 'flow-facts', 'flow-statement', 'flow-feature-1', 'flow-feature-2', 'flow-manufacturing', 'flow-custom', 'flow-opportunities', 'flow-team', 'flow-specband', 'product-configure', 'product-specs', 'flow-mosaic', 'flow-quote', 'flow-cta' );
	$out   = '';
	foreach ( $stack as $slug ) {
		$out .= '<!-- wp:pattern {"slug":"ricoman/' . $slug . '"} /-->' . "\n";
	}
	return $out;
}
