<?php
/**
 * Title: Page — Manufacturing
 * Slug: ricoman/page-manufacturing
 * Categories: ricoman, ricoman-pages
 * Description: Made-in-Britain manufacturing story — native editable blocks.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
?>
<!-- wp:cover {"url":"<?php echo $img( 'workshop.jpg' ); ?>","dimRatio":60,"overlayColor":"ink","minHeight":64,"minHeightUnit":"vh","contentPosition":"bottom left","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color has-custom-content-position is-position-bottom-left" style="min-height:64vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-60 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'workshop.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Made in Britain</p><!-- /wp:paragraph -->
	<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500">Designed &amp; manufactured in Manchester.</h1><!-- /wp:heading -->
</div></div>
<!-- /wp:cover -->

<!-- wp:group {"align":"full","className":"rm-statband","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-statband" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)">
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-statnum"} --><p class="rm-statnum">15,000ft²</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">Production area, Manchester</p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-statnum"} --><p class="rm-statnum">2,000+</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">Components ready to build</p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-statnum"} --><p class="rm-statnum">~6 days</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">Average UK-made lead time</p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-statnum"} --><p class="rm-statnum">98%</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">On-time-in-full target</p><!-- /wp:paragraph --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"rm-section rm-statement","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section rm-statement">
	<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Vertical integration, end to end — we make to order, not to a catalogue.</h2><!-- /wp:heading -->
</div>
<!-- /wp:group -->

<!-- wp:columns {"align":"full","verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"0"}}}} -->
<div class="wp-block-columns alignfull are-vertically-aligned-center">
	<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"style":{"border":{"radius":"0px"}}} --><figure class="wp-block-image"><img src="<?php echo $img( 'rico-making.webp' ); ?>" alt="In-house manufacturing"/></figure><!-- /wp:image --></div><!-- /wp:column -->
	<!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"padding":{"left":"var:preset|spacing|60","right":"var:preset|spacing|50"}}}} --><div class="wp-block-column is-vertically-aligned-center" style="padding-left:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50)"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">In-house, end to end</p><!-- /wp:paragraph --><!-- wp:heading {"level":2} --><h2 class="wp-block-heading">One roof, full control</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Design, electronics, assembly, finishing and testing all happen under one roof in Manchester — nothing outsourced to a supply chain we can't see. That means shorter lead times, full traceability on every batch, and the ability to make a fitting to your exact geometry.</p><!-- /wp:paragraph --></div><!-- /wp:column -->
</div>
<!-- /wp:columns -->

<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section">
	<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">How a Ricoman fitting is made</h2><!-- /wp:heading -->
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">01</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Design &amp; tooling</h3><!-- /wp:heading --><!-- wp:paragraph --><p>CAD, photometric modelling and tooling, in-house.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">02</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Assembly</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Boards, optics and housings hand-built to order.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">03</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Finishing</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Powder-coat &amp; bespoke finishes to your spec.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">04</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Test &amp; despatch</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Batch burn-in, then delivered UK-wide.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:cover {"url":"<?php echo $img( 'warehouse.jpg' ); ?>","dimRatio":70,"overlayColor":"ink","minHeight":52,"minHeightUnit":"vh","contentPosition":"center center","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color" style="min-height:52vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-70 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'warehouse.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:heading {"level":2,"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">If you can draw it, we can make it</h2><!-- /wp:heading -->
	<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Curved runs, custom lengths, special CCTs, brand-matched finishes — bespoke isn't a bolt-on, it's how the factory works.</p><!-- /wp:paragraph -->
	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/lighting-design/">Talk to our designers</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div></div>
<!-- /wp:cover -->
