<?php
/**
 * Title: Products listing
 * Slug: ricoman/products-archive
 * Categories: ricoman, ricoman-pages
 * Description: Products range listing — native editable, clickable tiles.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };

/** A clickable product tile (cover block). */
$tile = function ( $file, $family, $name, $href ) use ( $img ) {
	?>
	<!-- wp:column --><div class="wp-block-column"><!-- wp:cover {"url":"<?php echo $img( $file ); ?>","dimRatio":40,"overlayColor":"ink","minHeight":300,"contentPosition":"bottom left","isLink":true,"href":"<?php echo esc_url( $href ); ?>"} -->
	<div class="wp-block-cover has-custom-content-position is-position-bottom-left" style="min-height:300px"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background" alt="<?php echo esc_attr( $name ); ?>" src="<?php echo $img( $file ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
		<!-- wp:paragraph {"className":"rm-eyebrow","textColor":"base"} --><p class="rm-eyebrow has-base-color has-text-color"><?php echo esc_html( $family ); ?></p><!-- /wp:paragraph -->
		<!-- wp:heading {"level":3,"textColor":"base"} --><h3 class="wp-block-heading has-base-color has-text-color"><?php echo esc_html( $name ); ?></h3><!-- /wp:heading -->
	</div></div>
	<!-- /wp:cover --></div><!-- /wp:column -->
	<?php
};
?>
<!-- wp:cover {"url":"<?php echo $img( 'arch-line.jpg' ); ?>","dimRatio":60,"overlayColor":"ink","minHeight":58,"minHeightUnit":"vh","contentPosition":"bottom left","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color has-custom-content-position is-position-bottom-left" style="min-height:58vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-60 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'arch-line.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Our Lighting Range</p><!-- /wp:paragraph -->
	<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500">Commercial luminaires, made to specify.</h1><!-- /wp:heading -->
</div></div>
<!-- /wp:cover -->

<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rm-section">
	<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">Find the right light for the space</h2><!-- /wp:heading -->
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<?php
		$tile( 'arch-line.jpg', 'Linear · Featured', 'Flow+', '/products/flow-plus/' );
		$tile( 'estrella-lounge.webp', 'Pendant · Configurable', 'Estrella', '/products/estrella/' );
		$tile( 'ceiling.jpg', 'Downlight · Fire-rated', 'Neptune', '/products/neptune/' );
		?>
	</div>
	<!-- /wp:columns -->
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<?php
		$tile( 'office5.jpg', 'Linear · Suspended', 'Line Pro', '/products/' );
		$tile( 'pendant.jpg', 'Pendant · Architectural', 'Halo Ring', '/products/' );
		$tile( 'rico-acoustic-corridor.jpg', 'Acoustic · Linear', 'Astrawave', '/products/' );
	?>
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:cover {"url":"<?php echo $img( 'office1.jpg' ); ?>","dimRatio":70,"overlayColor":"ink","minHeight":52,"minHeightUnit":"vh","contentPosition":"center center","align":"full","textColor":"base"} -->
<div class="wp-block-cover alignfull has-base-color has-text-color" style="min-height:52vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-70 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $img( 'office1.jpg' ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:heading {"level":2,"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Can't find the exact fitting?</h2><!-- /wp:heading -->
	<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Send us a finishes schedule or drawing and our team will spec the range, beam and finish — costed, usually within 3–5 days.</p><!-- /wp:paragraph -->
	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/lighting-design/">Free Scheme Design</a></div><!-- /wp:button --><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/about/">Talk to the team</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div></div>
<!-- /wp:cover -->
