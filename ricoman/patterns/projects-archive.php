<?php
/**
 * Title: Projects listing
 * Slug: ricoman/projects-archive
 * Categories: ricoman, ricoman-pages
 * Description: Projects listing — editorial header, searchable/filterable case-study grid, CTA.
 *
 * @package Ricoman
 */
$u    = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
$cover = function ( $url, $inner, $min, $pos, $dim ) {
	$poscls = 'center center' === $pos ? '' : ' has-custom-content-position is-position-' . str_replace( ' ', '-', $pos );
	return '<!-- wp:cover {"url":"' . $url . '","dimRatio":' . $dim . ',"overlayColor":"ink","minHeight":' . $min . ',"minHeightUnit":"vh","contentPosition":"' . $pos . '","align":"full","textColor":"base"} --><div class="wp-block-cover alignfull has-base-color has-text-color' . $poscls . '" style="min-height:' . $min . 'vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-' . $dim . ' has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">' . $inner . '</div></div><!-- /wp:cover -->';
};

// Clean editorial header — no full-bleed photo, no dead whitespace.
echo '<!-- wp:group {"align":"full","className":"rm-section rm-projintro","layout":{"type":"constrained"}} -->'
	. '<div class="wp-block-group alignfull rm-section rm-projintro">'
	. '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Selected Work</p><!-- /wp:paragraph -->'
	. '<!-- wp:heading {"level":1,"className":"rm-projintro-h","style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.4rem,5vw,4rem)","lineHeight":"1.02","letterSpacing":"-0.02em"}}} -->'
	. '<h1 class="wp-block-heading rm-projintro-h" style="font-size:clamp(2.4rem,5vw,4rem);font-weight:500;letter-spacing:-0.02em;line-height:1.02">Light that performs in the real world.</h1><!-- /wp:heading -->'
	. '<!-- wp:paragraph {"className":"rm-projintro-sub","textColor":"muted"} --><p class="rm-projintro-sub has-muted-color has-text-color">From workplace fit-outs to flagship retail, our luminaires are specified, delivered and installed across the UK — explore selected projects by sector below.</p><!-- /wp:paragraph -->'
	. '</div><!-- /wp:group -->';

// Grid rendered as raw HTML (not a wp:shortcode block) so WordPress' wpautop
// can't wrap the toolbar/grid in stray <p> tags — that was the broken whitespace.
echo '<div class="rm-projwide-outer">' . do_shortcode( '[ricoman_projects_grid count="-1"]' ) . '</div>';

echo $cover(
	$u( 'office6.webp' ),
	'<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Have a project on the board?</h2><!-- /wp:heading -->' .
	'<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Send us your drawings or a finishes schedule and our in-house lighting designers will return a fully specified scheme — usually within 3–5 days.</p><!-- /wp:paragraph -->' .
	'<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons is-content-justification-center"><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/lighting-design/">Start a Project</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/about/">Talk to the design team →</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
	52,
	'center center',
	70
);
