<?php
/**
 * The canonical lighting-design CTA banner — one design used everywhere.
 *
 * A single source of truth for the full-width dark lighting-design hook so it is
 * identical on every page: feature pages, sector pages, and anywhere the team
 * drops it. Use it three ways:
 *   - PHP:        echo ricoman_design_cta();           (returns block markup)
 *   - Shortcode:  [ricoman_design_cta]
 *   - Editor:     ＋ → Patterns → Ricoman — Page → "CTA · Lighting design"
 *
 * The buttons are deliberately generic ("Request a lighting design" /
 * "Talk to the team") so the banner reads the same site-wide. Buttons inherit the
 * unified button design (uppercase, outlined) from theme.json + shared.css.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the canonical CTA banner as Gutenberg block markup.
 *
 * @param array $args Optional overrides: heading, text, btn1 [label,url], btn2 [label,url].
 * @return string
 */
function ricoman_design_cta( $args = array() ) {
	$a = wp_parse_args( $args, array(
		'heading' => 'Lighting design for your commercial project',
		'text'    => 'Send us your drawings or a finishes schedule and our in-house UK team returns a fully specified, costed scheme &mdash; complimentary on commercial projects, usually within 3&ndash;5 working days.',
		'btn1'    => array( 'Request a lighting design', '/lighting-design/' ),
		'btn2'    => array( 'Talk to the team &rarr;', '/contact/' ),
	) );

	$btn = function ( $label, $href ) {
		return '<!-- wp:button {"className":"is-style-outline-light"} -->'
			. '<div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $href ) . '">' . wp_kses_post( $label ) . '</a></div>'
			. '<!-- /wp:button -->';
	};

	$buttons = '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons is-content-justification-center">'
		. $btn( $a['btn1'][0], $a['btn1'][1] )
		. ( ! empty( $a['btn2'] ) ? $btn( $a['btn2'][0], $a['btn2'][1] ) : '' )
		. '</div><!-- /wp:buttons -->';

	return '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark rm-design-cta","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group alignfull rm-dark rm-design-cta has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)">'
		. '<!-- wp:heading {"textAlign":"center","level":2,"className":"rm-shead"} --><h2 class="wp-block-heading has-text-align-center rm-shead">' . wp_kses_post( $a['heading'] ) . '</h2><!-- /wp:heading -->'
		. '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">' . wp_kses_post( $a['text'] ) . '</p><!-- /wp:paragraph -->'
		. $buttons
		. '</div><!-- /wp:group -->';
}

/** Shortcode: [ricoman_design_cta] */
add_shortcode( 'ricoman_design_cta', function () {
	return ricoman_design_cta();
} );

/** Editable pattern so the team can drop the identical banner onto any page. */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern( 'ricoman/design-cta', array(
		'title'       => __( 'CTA · Lighting design', 'ricoman' ),
		'description' => __( 'The standard full-width lighting-design banner used across the site.', 'ricoman' ),
		'categories'  => array( 'ricoman-page' ),
		'content'     => ricoman_design_cta(),
	) );
}, 14 );
