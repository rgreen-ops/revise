<?php
/**
 * Ricoman pattern library — NATIVE editable blocks.
 *
 * Section patterns built from core blocks (cover / gallery / columns / heading /
 * paragraph / image / buttons) so every image and line of text is click-to-edit
 * in the editor. Insert via ＋ → Patterns → "Ricoman — Library".
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {

	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'ricoman-library', array( 'label' => __( 'Ricoman — Library', 'ricoman' ) ) );
	}

	$u = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };

	// Helpers --------------------------------------------------------------
	$section = function ( $inner ) {
		return '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section">' . $inner . '</div><!-- /wp:group -->';
	};
	$eyebrow = function ( $t ) { return '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $t . '</p><!-- /wp:paragraph -->'; };
	$shead   = function ( $t ) { return '<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">' . $t . '</h2><!-- /wp:heading -->'; };
	$para    = function ( $t, $muted = false ) { return '<!-- wp:paragraph' . ( $muted ? ' {"textColor":"muted"}' : '' ) . ' --><p' . ( $muted ? ' class="has-muted-color has-text-color"' : '' ) . '>' . $t . '</p><!-- /wp:paragraph -->'; };
	$img_block = function ( $url ) { return '<!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="' . $url . '" alt=""/></figure><!-- /wp:image -->'; };
	$cover = function ( $url, $inner, $min = 70, $pos = 'bottom left', $dim = 50 ) {
		$poscls = 'center center' === $pos ? '' : ' has-custom-content-position is-position-' . str_replace( ' ', '-', $pos );
		return '<!-- wp:cover {"url":"' . $url . '","dimRatio":' . $dim . ',"overlayColor":"ink","minHeight":' . $min . ',"minHeightUnit":"vh","contentPosition":"' . $pos . '","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color' . $poscls . '" style="min-height:' . $min . 'vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-' . $dim . ' has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">' . $inner . '</div></div>
<!-- /wp:cover -->';
	};
	$btn = function ( $label, $light = true ) { return '<!-- wp:button {"className":"is-style-outline' . ( $light ? '-light' : '' ) . '"} --><div class="wp-block-button is-style-outline' . ( $light ? '-light' : '' ) . '"><a class="wp-block-button__link wp-element-button" href="#">' . $label . '</a></div><!-- /wp:button -->'; };
	$buttons = function ( $inner, $center = false ) { return '<!-- wp:buttons' . ( $center ? ' {"layout":{"type":"flex","justifyContent":"center"}}' : '' ) . ' --><div class="wp-block-buttons' . ( $center ? ' is-content-justification-center' : '' ) . '">' . $inner . '</div><!-- /wp:buttons -->'; };

	$lib = array();

	/* Heroes */
	$lib['hero-image'] = array( 'Hero — image', $cover( $u( 'warm-int.webp' ),
		$eyebrow( 'Eyebrow label' ) .
		'<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"clamp(2.6rem, 6vw, 5rem)","fontWeight":"500","lineHeight":"1"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500;line-height:1">A bold headline for this page.</h1><!-- /wp:heading -->' .
		$para( 'One supporting sentence that sets up the section beneath.' ) .
		$buttons( $btn( 'Primary action' ) . $btn( 'Secondary' ) ) ) );

	$lib['hero-split'] = array( 'Hero — split', '<!-- wp:columns {"align":"full","verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"0"}}}} -->
<div class="wp-block-columns alignfull are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"padding":{"left":"var:preset|spacing|60","right":"var:preset|spacing|60","top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}}} -->
<div class="wp-block-column is-vertically-aligned-center" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)">' . $eyebrow( 'Eyebrow' ) . $shead( 'Split hero with text and image.' ) . $para( 'A short intro paragraph explaining the value in a sentence or two.', true ) . $buttons( $btn( 'Call to action', false ) ) . '</div><!-- /wp:column -->
<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $img_block( $u( 'office5.webp' ) ) . '</div><!-- /wp:column --></div>
<!-- /wp:columns -->' );

	$lib['hero-centered'] = array( 'Hero — centered', $section( '<!-- wp:group {"layout":{"type":"constrained","contentSize":"760px"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}}} --><div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:paragraph {"align":"center","className":"rm-eyebrow"} --><p class="has-text-align-center rm-eyebrow">Eyebrow</p><!-- /wp:paragraph --><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.4rem,5vw,4.4rem)"}}} --><h1 class="wp-block-heading has-text-align-center" style="font-size:clamp(2.4rem,5vw,4.4rem);font-weight:500">A centered, type-led hero.</h1><!-- /wp:heading --><!-- wp:paragraph {"align":"center","textColor":"muted"} --><p class="has-text-align-center has-muted-color has-text-color">Clean and minimal — ideal for campaign and landing pages.</p><!-- /wp:paragraph -->' . $buttons( $btn( 'Get started', false ), true ) . '</div><!-- /wp:group -->' ) );

	/* Stats */
	$stat = function ( $n, $l ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3,"className":"rm-statnum"} --><h3 class="wp-block-heading rm-statnum">' . $n . '</h3><!-- /wp:heading --><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">' . $l . '</p><!-- /wp:paragraph --></div><!-- /wp:column -->'; };
	$lib['stats-band'] = array( 'Stats — band', $section( '<!-- wp:columns -->
<div class="wp-block-columns">' . $stat( '500+', 'Projects delivered' ) . $stat( '2,000+', 'Components in stock' ) . $stat( '~6 days', 'Average lead time' ) . $stat( '98%', 'On-time in full' ) . '</div>
<!-- /wp:columns -->' ) );

	$lib['statement'] = array( 'Big statement', $section( '<!-- wp:heading {"level":2,"className":"rm-statement","style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.9rem,5vw,4rem)","lineHeight":"1.08"}}} --><h2 class="wp-block-heading rm-statement" style="font-size:clamp(1.9rem,5vw,4rem);font-weight:500;line-height:1.08">A big editorial statement that carries the section on its own.</h2><!-- /wp:heading -->' ) );

	/* Features */
	$lib['feature-left'] = array( 'Feature — image left', '<!-- wp:columns {"align":"full","verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"0"}}}} -->
<div class="wp-block-columns alignfull are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $img_block( $u( 'rico-making.webp' ) ) . '</div><!-- /wp:column -->
<!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"padding":{"left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}}} --><div class="wp-block-column is-vertically-aligned-center" style="padding-left:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60)">' . $eyebrow( 'Eyebrow' ) . $shead( 'Feature heading goes here.' ) . $para( 'Two or three lines describing the feature, the benefit, and why it matters.', true ) . '</div><!-- /wp:column --></div>
<!-- /wp:columns -->' );

	$lib['feature-dark'] = array( 'Feature — dark (image right)', '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)"><!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $eyebrow( 'Eyebrow' ) . $shead( 'A dark feature section.' ) . $para( 'Dark sections add rhythm and contrast between light content blocks.' ) . $buttons( $btn( 'Call to action' ) ) . '</div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $img_block( $u( 'workshop.webp' ) ) . '</div><!-- /wp:column --></div><!-- /wp:columns --></div>
<!-- /wp:group -->' );

	/* Media */
	$lib['gallery'] = array( 'Gallery — grid', $section( $shead( 'A few highlights' ) . '<!-- wp:gallery {"columns":3,"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-3 is-cropped">' . $img_block( $u( 'office1.webp' ) ) . $img_block( $u( 'office2.webp' ) ) . $img_block( $u( 'retail.webp' ) ) . $img_block( $u( 'office5.webp' ) ) . $img_block( $u( 'office6.webp' ) ) . $img_block( $u( 'pendant.webp' ) ) . '</figure>
<!-- /wp:gallery -->' ) );

	$lib['image-full'] = array( 'Full-bleed image', '<!-- wp:image {"align":"full","sizeSlug":"large"} --><figure class="wp-block-image alignfull size-large"><img src="' . $u( 'arch-line.webp' ) . '" alt=""/></figure><!-- /wp:image -->' );

	$lib['logos'] = array( 'Logo strip', $section( '<!-- wp:paragraph {"align":"center","className":"rm-eyebrow"} --><p class="has-text-align-center rm-eyebrow">Trusted by teams across the UK</p><!-- /wp:paragraph --><!-- wp:paragraph {"align":"center","style":{"typography":{"fontWeight":"700","letterSpacing":"0.1em","fontSize":"1.05rem"}},"textColor":"muted"} --><p class="has-text-align-center has-muted-color has-text-color" style="font-size:1.05rem;font-weight:700;letter-spacing:0.1em">ALLIANZ&nbsp;&nbsp;·&nbsp;&nbsp;BETFRED&nbsp;&nbsp;·&nbsp;&nbsp;KINGSGATE&nbsp;&nbsp;·&nbsp;&nbsp;NHS&nbsp;&nbsp;·&nbsp;&nbsp;SAVILLS&nbsp;&nbsp;·&nbsp;&nbsp;JLL</p><!-- /wp:paragraph -->' ) );

	/* Social proof */
	$lib['quote'] = array( 'Quote', $section( '<!-- wp:quote {"className":"rm-quote"} --><blockquote class="wp-block-quote rm-quote"><!-- wp:paragraph {"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.4rem,3vw,2.2rem)","lineHeight":"1.25"}}} --><p style="font-size:clamp(1.4rem,3vw,2.2rem);font-weight:500;line-height:1.25">“A genuinely useful quote from a happy client, kept short and punchy.”</p><!-- /wp:paragraph --><cite>Name Surname, Role, Company</cite></blockquote><!-- /wp:quote -->' ) );

	$member = function ( $img, $name, $role ) { return '<!-- wp:column --><div class="wp-block-column">' . $img . '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">' . $name . '</h4><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted","fontSize":"small"} --><p class="has-muted-color has-text-color has-small-font-size">' . $role . '</p><!-- /wp:paragraph --></div><!-- /wp:column -->'; };
	$lib['team'] = array( 'Team grid', $section( $shead( 'People behind the work' ) . '<!-- wp:columns -->
<div class="wp-block-columns">' . $member( $img_block( $u( 'office3.webp' ) ), 'Full Name', 'Job title' ) . $member( $img_block( $u( 'office6.webp' ) ), 'Full Name', 'Job title' ) . $member( $img_block( $u( 'office2.webp' ) ), 'Full Name', 'Job title' ) . $member( $img_block( $u( 'office1.webp' ) ), 'Full Name', 'Job title' ) . '</div>
<!-- /wp:columns -->' ) );

	/* Process / cards */
	$card = function ( $eyb, $h, $p ) use ( $eyebrow ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card">' . ( $eyb ? '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $eyb . '</p><!-- /wp:paragraph -->' : '' ) . '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . $h . '</h3><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color">' . $p . '</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->'; };
	$lib['steps'] = array( 'Process — 4 steps', $section( $shead( 'Four simple steps' ) . '<!-- wp:columns -->
<div class="wp-block-columns">' . $card( '01', 'Step one', 'Short description of this step.' ) . $card( '02', 'Step two', 'Short description of this step.' ) . $card( '03', 'Step three', 'Short description of this step.' ) . $card( '04', 'Step four', 'Short description of this step.' ) . '</div>
<!-- /wp:columns -->' ) );

	$lib['cards-3'] = array( 'Feature cards — 3', $section( '<!-- wp:columns -->
<div class="wp-block-columns">' . $card( '', 'Feature one', 'A sentence about this feature and its benefit.' ) . $card( '', 'Feature two', 'A sentence about this feature and its benefit.' ) . $card( '', 'Feature three', 'A sentence about this feature and its benefit.' ) . '</div>
<!-- /wp:columns -->' ) );

	/* Content */
	$lib['two-col-text'] = array( 'Two-column text', $section( $shead( 'A section with two text columns' ) . '<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column">' . $para( 'First column of body text. Useful for longer-form content where a single column would be too wide to read comfortably.' ) . '</div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column">' . $para( 'Second column continues the thought. Keep paragraphs short and scannable for the best reading experience.' ) . '</div><!-- /wp:column --></div>
<!-- /wp:columns -->' ) );

	/* CTAs */
	$lib['cta-band'] = array( 'CTA — image band', $cover( $u( 'office1.webp' ),
		'<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">A strong closing call to action.</h2><!-- /wp:heading -->' .
		'<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">One sentence of supporting copy to nudge the click.</p><!-- /wp:paragraph -->' .
		$buttons( $btn( 'Primary' ) . $btn( 'Secondary', false ), true ), 52, 'center center', 70 ) );

	$lib['cta-simple'] = array( 'CTA — simple', $section( '<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Ready to get started?</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center","textColor":"muted"} --><p class="has-text-align-center has-muted-color has-text-color">A short line of supporting copy.</p><!-- /wp:paragraph -->' . $buttons( $btn( 'Get in touch', false ), true ) ) );

	foreach ( $lib as $slug => $data ) {
		register_block_pattern(
			'ricoman/' . $slug,
			array(
				'title'      => 'Ricoman · ' . $data[0],
				'categories' => array( 'ricoman-library', 'ricoman' ),
				'content'    => $data[1],
			)
		);
	}
}, 11 );
