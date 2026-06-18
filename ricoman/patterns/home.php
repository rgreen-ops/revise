<?php
/**
 * Title: Home
 * Slug: ricoman/home
 * Categories: ricoman, ricoman-pages
 * Description: The Ricoman homepage, built from native WordPress blocks so every
 *              headline, paragraph, button and image is click-to-edit.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
?>
<!-- wp:cover {"url":"<?php echo $img( 'warm-int.jpg' ); ?>","dimRatio":60,"overlayColor":"ink","minHeight":90,"minHeightUnit":"vh","contentPosition":"bottom left","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color has-custom-content-position is-position-bottom-left" style="min-height:90vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-60 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'warm-int.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Commercial Interior Lighting · Made in Britain</p><!-- /wp:paragraph -->
	<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"clamp(2.8rem, 7vw, 6rem)","lineHeight":"0.98","fontWeight":"500"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.8rem, 7vw, 6rem);line-height:0.98;font-weight:500">Lighting that transforms commercial interiors.</h1><!-- /wp:heading -->
	<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.15rem"}}} --><p style="font-size:1.15rem">Designed and manufactured in Manchester for architects, interior designers, design &amp; build teams and electrical contractors — on spec, on time, on budget.</p><!-- /wp:paragraph -->
	<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} --><div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)">
		<!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/products/">Explore Products</a></div><!-- /wp:button -->
		<!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/lighting-design/">Free Scheme Design</a></div><!-- /wp:button -->
	</div><!-- /wp:buttons -->
</div></div>
<!-- /wp:cover -->

<!-- wp:group {"align":"full","className":"rm-facts","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-facts" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)">
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">Lead Time</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-fval"} --><p class="rm-fval">UK-made, ~6-day average</p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">Last Year</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-fval"} --><p class="rm-fval">535+ schemes designed</p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">In Stock</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-fval"} --><p class="rm-fval">2,000+ components</p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">Partners</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-fval"} --><p class="rm-fval">Strong local network</p><!-- /wp:paragraph --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"rm-section rm-statement","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section rm-statement">
	<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">We design and deliver UK-made commercial lighting — bringing spaces to life on spec, on time, and on budget.</h2><!-- /wp:heading -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"rm-section","style":{"spacing":{"padding":{"top":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section" style="padding-top:0">
	<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">A luminaire for every commercial interior</h2><!-- /wp:heading -->
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-card"><!-- wp:image --><figure class="wp-block-image"><img src="<?php echo $img( 'arch-line.jpg' ); ?>" alt="Linear lighting"/></figure><!-- /wp:image --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Linear Lighting</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Continuous runs &amp; profile systems</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-card"><!-- wp:image --><figure class="wp-block-image"><img src="<?php echo $img( 'ceiling.jpg' ); ?>" alt="Downlights"/></figure><!-- /wp:image --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Downlights</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Fire-rated, switchable CCT</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-card"><!-- wp:image --><figure class="wp-block-image"><img src="<?php echo $img( 'pendant.jpg' ); ?>" alt="Pendants"/></figure><!-- /wp:image --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Pendants</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Architectural &amp; decorative</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
	<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} --><div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)"><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/products/">View all products</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"rm-dark","backgroundColor":"ink","textColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)">
	<!-- wp:columns {"verticalAlignment":"center"} -->
	<div class="wp-block-columns are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Featured · Linear</p><!-- /wp:paragraph --><!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Flow+ — seamless curves of light</h2><!-- /wp:heading --><!-- wp:paragraph --><p>A flexible linear system that bends to any architectural line, continuous and dot-free. Made to order in Manchester, to your exact geometry.</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/product/flow-plus/">View Flow+</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:image --><figure class="wp-block-image"><img src="<?php echo $img( 'light-a.jpg' ); ?>" alt="Flow+ linear lighting"/></figure><!-- /wp:image --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section">
	<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">Lighting that performs in the real world</h2><!-- /wp:heading -->
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column --><div class="wp-block-column"><!-- wp:cover {"url":"<?php echo $img( 'office1.jpg' ); ?>","dimRatio":40,"overlayColor":"ink","minHeight":320,"contentPosition":"bottom left","isLink":true,"href":"/projects/"} --><div class="wp-block-cover has-custom-content-position is-position-bottom-left" style="min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'office1.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":3,"textColor":"base"} --><h3 class="wp-block-heading has-base-color has-text-color">Allianz HQ Fit-out</h3><!-- /wp:heading --></div></div><!-- /wp:cover --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:cover {"url":"<?php echo $img( 'retail.jpg' ); ?>","dimRatio":40,"overlayColor":"ink","minHeight":320,"contentPosition":"bottom left","isLink":true,"href":"/projects/"} --><div class="wp-block-cover has-custom-content-position is-position-bottom-left" style="min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'retail.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":3,"textColor":"base"} --><h3 class="wp-block-heading has-base-color has-text-color">Flagship Store</h3><!-- /wp:heading --></div></div><!-- /wp:cover --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:cover {"url":"<?php echo $img( 'office2.jpg' ); ?>","dimRatio":40,"overlayColor":"ink","minHeight":320,"contentPosition":"bottom left","isLink":true,"href":"/projects/"} --><div class="wp-block-cover has-custom-content-position is-position-bottom-left" style="min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'office2.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":3,"textColor":"base"} --><h3 class="wp-block-heading has-base-color has-text-color">Boutique Hotel</h3><!-- /wp:heading --></div></div><!-- /wp:cover --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
	<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} --><div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)"><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/projects/">All projects</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section">
	<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">A partner for the whole project team</h2><!-- /wp:heading -->
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Architects</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Clean photometrics, full data sheets and BIM-ready files for precise specification.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Interior Designers</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Warm, high-CRI light and decorative ranges that flatter materials and finishes.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Design &amp; Build</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Value-engineered alternatives, budget certainty and stock to keep programmes moving.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Contractors</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Fast quotes, reliable lead times and easy-install fittings that wire up first time.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:cover {"url":"<?php echo $img( 'office6.jpg' ); ?>","dimRatio":70,"overlayColor":"ink","minHeight":60,"minHeightUnit":"vh","contentPosition":"center center","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color" style="min-height:60vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-70 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'office6.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:heading {"level":2,"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Have a project on the board?</h2><!-- /wp:heading -->
	<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Send us your drawings and our in-house designers will return a fully specified, costed scheme — usually within 3–5 days.</p><!-- /wp:paragraph -->
	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/lighting-design/">Start a Project</a></div><!-- /wp:button --><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/about/">Talk to the team</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div></div>
<!-- /wp:cover -->
