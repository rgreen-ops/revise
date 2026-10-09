<?php
/**
 * "Why Ricoman" tabbed band — an editable pattern where a row of titles reveals
 * one image-and-text panel at a time (UK Manufacturing / Lighting Design /
 * Customer Support, by default).
 *
 * Built from core blocks so every title, image and paragraph is click-to-edit.
 * The tab row itself is generated on the front end (assets/js/tabs.js) from each
 * panel's .rm-tab-title heading, so adding a panel adds a tab automatically. With
 * JS off (or in the editor) all panels simply stack — nothing is hidden from
 * search engines. Styled by .rm-tabsec / .rm-tabs in ricoman.css.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The pattern's block markup (three starter panels). */
function ricoman_tabs_pattern_content() {
	$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };

	$panel = function ( $title, $imgurl, $alt, $body, $btn_label, $btn_href ) {
		return '<!-- wp:group {"className":"rm-tabpanel"} --><div class="wp-block-group rm-tabpanel">'
			. '<!-- wp:heading {"level":3,"className":"rm-tab-title"} --><h3 class="wp-block-heading rm-tab-title">' . $title . '</h3><!-- /wp:heading -->'
			. '<!-- wp:columns {"verticalAlignment":"center","className":"rm-tabpanel-row"} --><div class="wp-block-columns are-vertically-aligned-center rm-tabpanel-row">'
			. '<!-- wp:column {"verticalAlignment":"center","width":"52%"} --><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:52%">'
			. '<!-- wp:image {"sizeSlug":"large","className":"rm-tabpanel-img"} --><figure class="wp-block-image size-large rm-tabpanel-img"><img src="' . $imgurl . '" alt="' . esc_attr( $alt ) . '"/></figure><!-- /wp:image -->'
			. '</div><!-- /wp:column -->'
			. '<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">'
			. '<!-- wp:paragraph --><p>' . $body . '</p><!-- /wp:paragraph -->'
			. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $btn_href ) . '">' . $btn_label . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
			. '</div><!-- /wp:column -->'
			. '</div><!-- /wp:columns -->'
			. '</div><!-- /wp:group -->';
	};

	$panels = $panel(
			'UK Manufacturing',
			$img( 'workshop.webp' ),
			'Ricoman LED luminaires designed and made in Manchester',
			'Every Ricoman luminaire is designed, assembled, finished and tested in our own facility in Manchester. Keeping manufacturing in-house gives us full control over quality and lead times &mdash; with 2,000+ components stocked and an average six-day UK-made lead.',
			'Inside our manufacturing',
			'/manufacturing/'
		)
		. $panel(
			'Lighting Design',
			$img( 'rico-office-render.webp' ),
			'Commercial lighting design scheme by Ricoman&rsquo;s in-house team',
			'Our in-house lighting designers turn your drawings and finishes schedule into a fully specified, photometric-backed and costed scheme &mdash; complimentary on commercial projects, usually within 3&ndash;5 days.',
			'Our lighting design service',
			'/lighting-design/'
		)
		. $panel(
			'Customer Support',
			$img( 'rico-office.webp' ),
			'Ricoman project support team',
			'From first enquiry to final delivery, a dedicated coordinator keeps your project moving &mdash; clear communication, honest lead times and a real person on the phone whenever you need one.',
			'Talk to our team',
			'/contact/'
		);

	$tabs = '<!-- wp:group {"className":"rm-tabs"} --><div class="wp-block-group rm-tabs">' . $panels . '</div><!-- /wp:group -->';

	return '<!-- wp:group {"align":"full","className":"rm-section rm-tabsec","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section rm-tabsec">' . $tabs . '</div><!-- /wp:group -->';
}

/** Register the pattern (after the "Ricoman — Page" category is set up). */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern( 'ricoman/why-tabs', array(
		'title'       => 'Tabs · Image + text (Why Ricoman)',
		'description' => 'Clickable titles that each reveal an image + text panel (UK Manufacturing / Lighting Design / Customer Support). Editable titles, images and copy; add a panel to add a tab.',
		'categories'  => array( 'ricoman-page' ),
		'content'     => ricoman_tabs_pattern_content(),
	) );
}, 15 );

/** Load the tab script only on pages that actually use the pattern. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_post();
	if ( ! $post || false === strpos( (string) $post->post_content, 'rm-tabpanel' ) ) {
		return;
	}
	$src = get_theme_file_path( 'assets/js/tabs.js' );
	wp_enqueue_script( 'ricoman-tabs', get_theme_file_uri( 'assets/js/tabs.js' ), array(), file_exists( $src ) ? (string) filemtime( $src ) : '1', true );
} );
