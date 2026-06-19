<?php
/**
 * Title: Products listing
 * Slug: ricoman/products-archive
 * Categories: ricoman, ricoman-pages
 * Description: Products range listing — native editable blocks (hero, category grids, applications, CTA).
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
// Product card: linked image + small label + title + spec line. No pricing.
$pcard = function ( $url, $label, $title, $spec, $href ) {
	$img = '<!-- wp:image {"linkDestination":"custom","sizeSlug":"large"} --><figure class="wp-block-image size-large"><a href="' . $href . '"><img src="' . $url . '" alt="' . esc_attr( $title ) . '"/></a></figure><!-- /wp:image -->';
	return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-card">' . $img .
		'<!-- wp:paragraph {"className":"rm-eyebrow","fontSize":"small"} --><p class="rm-eyebrow has-small-font-size">' . $label . '</p><!-- /wp:paragraph -->' .
		'<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><a href="' . $href . '">' . $title . '</a></h3><!-- /wp:heading -->' .
		'<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} --><p class="has-muted-color has-text-color has-small-font-size">' . $spec . '</p><!-- /wp:paragraph -->' .
		'</div><!-- /wp:group --></div><!-- /wp:column -->';
};
// Application tile: cover with overlaid label, links to projects.
$ptile = function ( $url, $title, $sub, $href ) {
	return '<!-- wp:column --><div class="wp-block-column"><!-- wp:cover {"url":"' . $url . '","dimRatio":40,"overlayColor":"ink","minHeight":300,"contentPosition":"bottom left","isLink":true,"href":"' . $href . '"} --><div class="wp-block-cover has-custom-content-position is-position-bottom-left" style="min-height:300px"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":3,"textColor":"base"} --><h3 class="wp-block-heading has-base-color has-text-color">' . $title . '</h3><!-- /wp:heading --><!-- wp:paragraph {"className":"rm-eyebrow","textColor":"base"} --><p class="rm-eyebrow has-base-color has-text-color">' . $sub . '</p><!-- /wp:paragraph --></div></div><!-- /wp:cover --></div><!-- /wp:column -->';
};

echo $cover(
	$u( 'arch-line.webp' ),
	$eyebrow( 'Our Lighting Range' ) .
	'<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.1rem,4.4vw,3.4rem)","lineHeight":"1.04"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.1rem,4.4vw,3.4rem);font-weight:500;line-height:1.04">Commercial luminaires, made to specify.</h1><!-- /wp:heading -->' .
	$para( 'Over 500 interior fittings across linear, downlights, pendants, track and modular ranges — held in UK stock and made to order in Manchester.' ),
	38,
	'bottom left',
	55
);

// The live catalogue — real product categories + products from the database
// (falls back to a flat product grid if nothing is categorised yet).
echo $sec( '<!-- wp:shortcode -->[ricoman_catalogue per_cat="8"]<!-- /wp:shortcode -->' );

echo $cover(
	$u( 'office1.webp' ),
	'<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Can&rsquo;t find the exact fitting?</h2><!-- /wp:heading -->' .
	'<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Send us a finishes schedule or a drawing and our in-house team will spec the range, beam and finish — and return a costed scheme, usually within 3–5 days.</p><!-- /wp:paragraph -->' .
	$buttons( $btn( 'Free Scheme Design', '/lighting-design/' ) . $btn( 'Talk to the team →', '/about/', false ), true ),
	52,
	'center center',
	70
);
