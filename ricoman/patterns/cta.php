<?php
/**
 * Title: Call to action
 * Slug: ricoman/cta
 * Categories: ricoman, ricoman-pages, call-to-action
 * Description: Brand-coloured call-to-action band with heading and buttons.
 *
 * @package Ricoman
 */
?>
<!-- wp:group {"tagName":"section","align":"full","anchor":"contact","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"gradient":"brand","textColor":"base","layout":{"type":"constrained"}} -->
<section id="contact" class="wp-block-group alignfull has-base-color has-brand-gradient-background has-text-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:heading {"textAlign":"center","textColor":"base","style":{"typography":{"fontSize":"clamp(2rem, 4vw, 3rem)"}}} -->
	<h2 class="wp-block-heading has-text-align-center has-base-color has-text-color" style="font-size:clamp(2rem, 4vw, 3rem)">Have a project on the board?</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"1.15rem"},"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"textColor":"base"} -->
	<p class="has-text-align-center has-base-color has-text-color" style="margin-top:var(--wp--preset--spacing--30);font-size:1.15rem">Send us your drawings or a finishes schedule and our lighting designers will return a fully specified, costed scheme — usually within 48 hours.</p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)">
		<!-- wp:button {"backgroundColor":"ink","textColor":"base","className":"is-style-pill"} -->
		<div class="wp-block-button is-style-pill"><a class="wp-block-button__link has-base-color has-ink-background-color has-text-color has-background wp-element-button" href="mailto:sales@ricoman.com">Start a project</a></div>
		<!-- /wp:button -->
		<!-- wp:button {"className":"is-style-outline-light is-style-pill"} -->
		<div class="wp-block-button is-style-outline-light is-style-pill"><a class="wp-block-button__link wp-element-button" href="tel:+441610000000">Call the team</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</section>
<!-- /wp:group -->
