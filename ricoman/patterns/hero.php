<?php
/**
 * Title: Hero
 * Slug: ricoman/hero
 * Categories: ricoman, ricoman-pages, banner
 * Description: Full-width black hero with headline, intro and outlined call-to-action buttons.
 *
 * @package Ricoman
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}},"dimensions":{"minHeight":"72vh"}},"gradient":"ink-glow","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-color has-ink-glow-gradient-background has-text-color has-background" style="min-height:72vh;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">
	<!-- wp:paragraph {"className":"ricoman-eyebrow","textColor":"accent"} -->
	<p class="ricoman-eyebrow has-accent-color has-text-color">UK Made Commercial Lighting</p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"clamp(2.6rem, 6vw, 4.5rem)","lineHeight":"1.05"}},"textColor":"base"} -->
	<h1 class="wp-block-heading has-base-color has-text-color" style="font-size:clamp(2.6rem, 6vw, 4.5rem);line-height:1.05">Transforming Spaces With Lighting That Inspires, Performs, And Endures.</h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.2rem"},"spacing":{"margin":{"top":"var:preset|spacing|40"}}},"textColor":"surface"} -->
	<p class="has-surface-color has-text-color" style="margin-top:var(--wp--preset--spacing--40);font-size:1.2rem">Designed and manufactured in Britain for architects, interior designers, design &amp; build teams and electrical contractors — delivered on spec, on time and on budget.</p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons {"layout":{"type":"flex"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--50)">
		<!-- wp:button {"className":"is-style-outline-light"} -->
		<div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="#projects">Explore Our Projects →</a></div>
		<!-- /wp:button -->
		<!-- wp:button {"className":"is-style-outline-light"} -->
		<div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="#enquire">Get A Free Scheme →</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
