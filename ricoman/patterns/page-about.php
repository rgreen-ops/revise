<?php
/**
 * Title: Page — About & Contact
 * Slug: ricoman/page-about
 * Categories: ricoman, ricoman-pages
 * Description: Company story, stats, values and contact — native editable blocks.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
?>
<!-- wp:cover {"url":"<?php echo $img( 'rico-office.jpg' ); ?>","dimRatio":60,"overlayColor":"ink","minHeight":62,"minHeightUnit":"vh","contentPosition":"bottom left","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color has-custom-content-position is-position-bottom-left" style="min-height:62vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-60 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'rico-office.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">About Ricoman</p><!-- /wp:paragraph -->
	<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500">British lighting, made with intent.</h1><!-- /wp:heading -->
</div></div>
<!-- /wp:cover -->

<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section">
	<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">Lighting that works on spec, on time, on budget</h2><!-- /wp:heading -->
	<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.12rem"}}} --><p style="font-size:1.12rem">Ricoman designs and manufactures commercial interior LED lighting from our own facility in Manchester. Because we're the manufacturer — not a reseller — we control quality, lead times and bespoke detail in-house: free scheme design, 2,000+ components stocked ready to build, an average six-day UK-made lead, strong local partnerships and a 5-year warranty.</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"rm-statband","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-statband" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)">
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-statnum"} --><p class="rm-statnum">1999</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">Our journey began</p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-statnum"} --><p class="rm-statnum">535</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">Schemes designed in 2025</p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-statnum"} --><p class="rm-statnum">2,000+</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">Components stocked</p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-statnum"} --><p class="rm-statnum">20+</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">Countries supplied</p><!-- /wp:paragraph --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section">
	<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">The way we like to work</h2><!-- /wp:heading -->
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Curiosity</h3><!-- /wp:heading --><!-- wp:paragraph --><p>We question the status quo, asking how lighting can be smarter, greener and more human. Innovation begins with the right questions.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Growth</h3><!-- /wp:heading --><!-- wp:paragraph --><p>We grow through learning, sustainable practice and a drive to improve — investing in in-house manufacturing for precision and quality.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Community</h3><!-- /wp:heading --><!-- wp:paragraph --><p>We invest in the people and places around us, from our Manchester roots to the partners on every project. Long-term relationships matter.</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"rm-dark","backgroundColor":"ink","textColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)">
	<!-- wp:columns {"verticalAlignment":"center"} -->
	<div class="wp-block-columns are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Get in touch</p><!-- /wp:paragraph --><!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Talk to the team</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Metroplex Business Park, 520 Broadway, M50 2UE, Manchester, UK</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><strong>0161 451 5913</strong><br>sales@ricoman.com</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Mon–Thu 8:30–17:00 · Fri 8:30–16:00</p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column --><div class="wp-block-column"><!-- wp:image --><figure class="wp-block-image"><img src="<?php echo $img( 'rico-office.jpg' ); ?>" alt="Ricoman Manchester"/></figure><!-- /wp:image --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
