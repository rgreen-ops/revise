<?php
/**
 * Title: My Project page
 * Slug: ricoman/page-my-project
 * Categories: ricoman, ricoman-pages
 * Description: The My Project specification list and enquiry form.
 *
 * @package Ricoman
 */
?>
<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:paragraph {"className":"ricoman-eyebrow","textColor":"primary"} -->
	<p class="ricoman-eyebrow has-primary-color has-text-color">My Project</p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"level":1} -->
	<h1 class="wp-block-heading">Your Specification List</h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"textColor":"muted","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} -->
	<p class="has-muted-color has-text-color" style="margin-bottom:var(--wp--preset--spacing--40)">Review the products you’ve saved, set quantities, then send the list to our lighting design team for a costed scheme.</p>
	<!-- /wp:paragraph -->

	<!-- wp:shortcode -->[ricoman_my_project]<!-- /wp:shortcode -->
</main>
<!-- /wp:group -->
