<?php
/**
 * Title: Page — Lighting Design
 * Slug: ricoman/page-lighting
 * Categories: ricoman, ricoman-pages
 * Description: Free scheme design service — native editable blocks.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
?>
<!-- wp:cover {"url":"<?php echo $img( 'rico-office-render.webp' ); ?>","dimRatio":60,"overlayColor":"ink","minHeight":64,"minHeightUnit":"vh","contentPosition":"bottom left","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color has-custom-content-position is-position-bottom-left" style="min-height:64vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-60 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'rico-office-render.webp' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Free Scheme Design</p><!-- /wp:paragraph -->
	<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500">Your scheme, fully designed — at no cost.</h1><!-- /wp:heading -->
</div></div>
<!-- /wp:cover -->

<!-- wp:group {"align":"full","className":"rm-section rm-statement","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section rm-statement">
	<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">We don't just sell luminaires — we design the light, prove it works, and cost it before you commit a penny.</h2><!-- /wp:heading -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"rm-section","style":{"spacing":{"padding":{"top":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section" style="padding-top:0">
	<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">Four steps from drawing to delivered scheme</h2><!-- /wp:heading -->
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">01</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Send your drawings</h3><!-- /wp:heading --><!-- wp:paragraph --><p>A plan, RCP or sketch and a finishes schedule. PDF, DWG or photos.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">02</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">We design the light</h3><!-- /wp:heading --><!-- wp:paragraph --><p>A DIALux photometric study — lux, uniformity, UGR and energy.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">03</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Costed scheme in 3–5 days</h3><!-- /wp:heading --><!-- wp:paragraph --><p>A specified luminaire schedule, layout and itemised costing.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">04</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Made &amp; delivered</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Approved, made to order in Manchester, delivered to programme.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"rm-dark","backgroundColor":"ink","textColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)">
	<!-- wp:columns {"verticalAlignment":"center"} -->
	<div class="wp-block-columns are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">What you receive</p><!-- /wp:paragraph --><!-- wp:heading {"level":2} --><h2 class="wp-block-heading">A scheme you can specify with confidence</h2><!-- /wp:heading --><!-- wp:list {"className":"rm-ul"} --><ul class="wp-block-list rm-ul"><!-- wp:list-item --><li><strong>DIALux photometric study</strong> — lux, uniformity &amp; UGR</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Luminaire schedule</strong> — every fitting, finish &amp; quantity</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Reflected ceiling layout</strong> — positions for the contractor</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Itemised costing</strong> — with value-engineered options</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Data sheets &amp; BIM files</strong> — ready for your spec pack</li><!-- /wp:list-item --></ul><!-- /wp:list --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:image --><figure class="wp-block-image"><img src="<?php echo $img( 'office5.jpg' ); ?>" alt="Lighting scheme"/></figure><!-- /wp:image --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:cover {"url":"<?php echo $img( 'office1.jpg' ); ?>","dimRatio":70,"overlayColor":"ink","minHeight":52,"minHeightUnit":"vh","contentPosition":"center center","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color" style="min-height:52vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-70 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'office1.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:heading {"level":2,"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Start your scheme</h2><!-- /wp:heading -->
	<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Tell us about the project and share your drawings — a lighting designer will be in touch, costed scheme to follow.</p><!-- /wp:paragraph -->
	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/about/">Talk to the team</a></div><!-- /wp:button --><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/projects/">See the results</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div></div>
<!-- /wp:cover -->
