<?php
/**
 * Title: Hero
 * Slug: ricoman/hero
 * Categories: ricoman, ricoman-pages, banner
 * Description: Dark hero with brand glow, headline, intro and call-to-action buttons.
 *
 * @package Ricoman
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"gradient":"ink-glow","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-color has-ink-glow-gradient-background has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:paragraph {"align":"center","className":"ricoman-eyebrow"} -->
	<p class="has-text-align-center ricoman-eyebrow">Commercial Interior Lighting · Made in Manchester</p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":"clamp(2.75rem, 6vw, 4.5rem)"}},"textColor":"base"} -->
	<h1 class="wp-block-heading has-text-align-center has-base-color has-text-color" style="font-size:clamp(2.75rem, 6vw, 4.5rem)">Interior lighting,<br>specified with confidence.</h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"1.2rem"},"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"textColor":"surface"} -->
	<p class="has-text-align-center has-surface-color has-text-color" style="margin-top:var(--wp--preset--spacing--30);font-size:1.2rem">Ricoman designs and manufactures commercial interior LED lighting for architects, interior designers, design &amp; build teams and electrical contractors — backed by UK stockholding, fast delivery and free scheme design.</p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)">
		<!-- wp:button {"className":"is-style-pill"} -->
		<div class="wp-block-button is-style-pill"><a class="wp-block-button__link wp-element-button" href="#products">Explore products</a></div>
		<!-- /wp:button -->
		<!-- wp:button {"className":"is-style-outline-light is-style-pill"} -->
		<div class="wp-block-button is-style-outline-light is-style-pill"><a class="wp-block-button__link wp-element-button" href="#contact">Free scheme design</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
