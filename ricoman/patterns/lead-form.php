<?php
/**
 * Title: Lead capture section
 * Slug: ricoman/lead-form
 * Categories: ricoman, ricoman-pages, call-to-action
 * Description: Two-column contact section pairing copy with the lead capture form.
 *
 * @package Ricoman
 */
?>
<!-- wp:group {"tagName":"section","align":"full","anchor":"enquire","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<section id="enquire" class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:columns {"align":"wide","verticalAlignment":"top","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|50","left":"var:preset|spacing|60"}}}} -->
	<div class="wp-block-columns alignwide are-vertically-aligned-top">
		<!-- wp:column {"verticalAlignment":"top","width":"42%"} -->
		<div class="wp-block-column is-vertically-aligned-top" style="flex-basis:42%">
			<!-- wp:paragraph {"className":"ricoman-eyebrow"} -->
			<p class="ricoman-eyebrow">Start a project</p>
			<!-- /wp:paragraph -->
			<!-- wp:heading -->
			<h2 class="wp-block-heading">Free lighting scheme design</h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"textColor":"muted"} -->
			<p class="has-muted-color has-text-color">Share your floor plans, finishes or a product schedule and our in-house designers will return a fully specified, costed lighting scheme — typically within 48 hours.</p>
			<!-- /wp:paragraph -->
			<!-- wp:list {"className":"is-style-none","style":{"spacing":{"blockGap":"0.6rem"},"typography":{"fontWeight":"600"}}} -->
			<ul class="wp-block-list is-style-none" style="font-weight:600"><!-- wp:list-item --><li>✓ Relux &amp; DIALux calculations</li><!-- /wp:list-item --><!-- wp:list-item --><li>✓ Product schedules &amp; datasheets</li><!-- /wp:list-item --><!-- wp:list-item --><li>✓ UK stock, fast delivery</li><!-- /wp:list-item --></ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"top"} -->
		<div class="wp-block-column is-vertically-aligned-top">
			<!-- wp:shortcode -->[ricoman_lead_form]<!-- /wp:shortcode -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->
