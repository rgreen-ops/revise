<?php
/**
 * Title: Projects listing
 * Slug: ricoman/projects-archive
 * Categories: ricoman, ricoman-pages
 * Description: Projects listing — native editable, clickable case-study tiles.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };

$tile = function ( $file, $sector, $name, $href ) use ( $img ) {
	?>
	<!-- wp:column --><div class="wp-block-column"><!-- wp:cover {"url":"<?php echo $img( $file ); ?>","dimRatio":40,"overlayColor":"ink","minHeight":320,"contentPosition":"bottom left","isLink":true,"href":"<?php echo esc_url( $href ); ?>"} -->
	<div class="wp-block-cover has-custom-content-position is-position-bottom-left" style="min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background" alt="<?php echo esc_attr( $name ); ?>" src="<?php echo $img( $file ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
		<!-- wp:paragraph {"className":"rm-eyebrow","textColor":"base"} --><p class="rm-eyebrow has-base-color has-text-color"><?php echo esc_html( $sector ); ?></p><!-- /wp:paragraph -->
		<!-- wp:heading {"level":3,"textColor":"base"} --><h3 class="wp-block-heading has-base-color has-text-color"><?php echo esc_html( $name ); ?></h3><!-- /wp:heading -->
	</div></div>
	<!-- /wp:cover --></div><!-- /wp:column -->
	<?php
};
?>
<!-- wp:cover {"url":"<?php echo $img( 'office1.jpg' ); ?>","dimRatio":60,"overlayColor":"ink","minHeight":58,"minHeightUnit":"vh","contentPosition":"bottom left","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color has-custom-content-position is-position-bottom-left" style="min-height:58vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-60 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'office1.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Selected Work</p><!-- /wp:paragraph -->
	<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500">Light that performs in the real world.</h1><!-- /wp:heading -->
</div></div>
<!-- /wp:cover -->

<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section">
	<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">Specified, delivered and installed across the UK</h2><!-- /wp:heading -->
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<?php
		$tile( 'office1.jpg', 'Commercial Office · Leeds', 'Allianz HQ Fit-out', '/projects/allianz-hq/' );
		$tile( 'retail.jpg', 'Retail · Manchester', 'Flagship Retail Store', '/projects/flagship-store/' );
		$tile( 'office2.jpg', 'Hospitality', 'Boutique Hotel', '/projects/' );
		?>
	</div>
	<!-- /wp:columns -->
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<?php
		$tile( 'rico-betfred7.webp', 'Workplace · Warrington', 'Betfred HQ', '/projects/' );
		$tile( 'rico-acoustic-corridor.jpg', 'Workplace · Acoustic', 'Acoustic Ceiling', '/projects/' );
		$tile( 'office5.jpg', 'Education', 'Campus Library', '/projects/' );
		?>
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:cover {"url":"<?php echo $img( 'office6.jpg' ); ?>","dimRatio":70,"overlayColor":"ink","minHeight":52,"minHeightUnit":"vh","contentPosition":"center center","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color" style="min-height:52vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-70 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'office6.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:heading {"level":2,"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Have a project on the board?</h2><!-- /wp:heading -->
	<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Send us your drawings and our in-house designers will return a fully specified, costed scheme — usually within 3–5 days.</p><!-- /wp:paragraph -->
	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/lighting-design/">Start a Project</a></div><!-- /wp:button --><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/about/">Talk to the team</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div></div>
<!-- /wp:cover -->
