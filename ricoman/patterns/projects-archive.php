<?php
/**
 * Title: Projects listing
 * Slug: ricoman/projects-archive
 * Categories: ricoman, ricoman-pages
 * Description: Projects listing — native editable blocks (hero, case-study grid, CTA).
 *
 * @package Ricoman
 */
$u       = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
$eyebrow = function ( $t ) { return '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $t . '</p><!-- /wp:paragraph -->'; };
$shead   = function ( $t ) { return '<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">' . $t . '</h2><!-- /wp:heading -->'; };
$para    = function ( $t ) { return '<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color">' . $t . '</p><!-- /wp:paragraph -->'; };
$btn     = function ( $label, $href, $light = true ) { return '<!-- wp:button {"className":"is-style-outline' . ( $light ? '-light' : '' ) . '"} --><div class="wp-block-button is-style-outline' . ( $light ? '-light' : '' ) . '"><a class="wp-block-button__link wp-element-button" href="' . $href . '">' . $label . '</a></div><!-- /wp:button -->'; };
$buttons = function ( $inner, $center = false ) { return '<!-- wp:buttons' . ( $center ? ' {"layout":{"type":"flex","justifyContent":"center"}}' : '' ) . ' --><div class="wp-block-buttons' . ( $center ? ' is-content-justification-center' : '' ) . '">' . $inner . '</div><!-- /wp:buttons -->'; };
$sec     = function ( $inner, $cls = '' ) { return '<!-- wp:group {"align":"full","className":"rm-section ' . $cls . '","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section ' . $cls . '">' . $inner . '</div><!-- /wp:group -->'; };
$cover   = function ( $url, $inner, $min, $pos, $dim ) {
	$poscls = 'center center' === $pos ? '' : ' has-custom-content-position is-position-' . str_replace( ' ', '-', $pos );
	return '<!-- wp:cover {"url":"' . $url . '","dimRatio":' . $dim . ',"overlayColor":"ink","minHeight":' . $min . ',"minHeightUnit":"vh","contentPosition":"' . $pos . '","align":"full","textColor":"base"} --><div class="wp-block-cover alignfull has-base-color has-text-color' . $poscls . '" style="min-height:' . $min . 'vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-' . $dim . ' has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">' . $inner . '</div></div><!-- /wp:cover -->';
};
// Project tile: cover with sector eyebrow + title, links to the case study.
$tile = function ( $url, $sub, $title, $href, $min = 320 ) {
	return '<!-- wp:column --><div class="wp-block-column"><!-- wp:cover {"url":"' . $url . '","dimRatio":40,"overlayColor":"ink","minHeight":' . $min . ',"contentPosition":"bottom left","isLink":true,"href":"' . $href . '"} --><div class="wp-block-cover has-custom-content-position is-position-bottom-left" style="min-height:' . $min . 'px"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"className":"rm-eyebrow","textColor":"base"} --><p class="rm-eyebrow has-base-color has-text-color">' . $sub . '</p><!-- /wp:paragraph --><!-- wp:heading {"level":3,"textColor":"base"} --><h3 class="wp-block-heading has-base-color has-text-color">' . $title . '</h3><!-- /wp:heading --></div></div><!-- /wp:cover --></div><!-- /wp:column -->';
};

echo $cover(
	$u( 'office1.jpg' ),
	$eyebrow( 'Selected Work' ) .
	'<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)","lineHeight":"1"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500;line-height:1">Light that performs in the real world.</h1><!-- /wp:heading -->' .
	$para( 'From workplace fit-outs to flagship retail, our luminaires are specified, delivered and installed across the UK.' ),
	62,
	'bottom left',
	50
);

echo $sec(
	'<!-- wp:columns --><div class="wp-block-columns">' .
	$tile( $u( 'rico-acoustic-corridor.jpg' ), 'Commercial Office · Manchester', 'Acoustic linear ceiling', '/projects/acoustic-ceiling/', 420 ) .
	$tile( $u( 'retail.jpg' ), 'Retail · Manchester', 'Flagship Store', '/projects/flagship-store/', 420 ) .
	'</div><!-- /wp:columns -->' .
	'<!-- wp:columns --><div class="wp-block-columns">' .
	$tile( $u( 'rico-breakout-lounge.jpg' ), 'Workplace · Amenity', 'Breakout lounge', '/projects/breakout-lounge/' ) .
	$tile( $u( 'office2.jpg' ), 'Hospitality', 'Boutique Hotel', '/projects/boutique-hotel/' ) .
	$tile( $u( 'rico-betfred7.webp' ), 'Workplace · Warrington', 'Betfred HQ', '/projects/betfred-hq/' ) .
	'</div><!-- /wp:columns -->' .
	'<!-- wp:columns --><div class="wp-block-columns">' .
	$tile( $u( 'rico-kingsgate.jpg' ), 'Retail · London', 'Kingsgate', '/projects/kingsgate/' ) .
	$tile( $u( 'estrella-canteen.jpg' ), 'Hospitality · Staff dining', 'Estrella Canteen', '/projects/estrella-canteen/' ) .
	$tile( $u( 'office5.jpg' ), 'Education', 'Campus Library', '/projects/campus-library/' ) .
	'</div><!-- /wp:columns -->'
);

echo $cover(
	$u( 'office6.jpg' ),
	'<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Have a project on the board?</h2><!-- /wp:heading -->' .
	'<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Send us your drawings or a finishes schedule and our in-house lighting designers will return a fully specified, costed scheme — usually within 3–5 days.</p><!-- /wp:paragraph -->' .
	$buttons( $btn( 'Start a Project', '/lighting-design/' ) . $btn( 'Talk to the design team →', '/about/', false ), true ),
	52,
	'center center',
	70
);
