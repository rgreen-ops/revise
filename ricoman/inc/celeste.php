<?php
/**
 * Celeste — stretched fabric light box.
 *
 * Provides EDITABLE block patterns (not hardcoded output) so the team can build
 * and rearrange the Celeste page themselves: add them from the Product Page
 * Editor's "Add section or pattern", then edit the text, images and buttons like
 * any other block. Built from core blocks + the theme's section/card styles, so
 * each section sits in the normal constrained content column.
 *
 * Also creates the new "Stretch Ceiling Lighting" product category.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Public URL of the Celeste "Build your own light box" builder. Filterable. */
function ricoman_celeste_builder_url() {
	return apply_filters( 'ricoman_celeste_builder_url', '#' );
}

/* ---------------------------------------------------------------------------
 * New product category: Stretch Ceiling Lighting (create-once).
 * ------------------------------------------------------------------------- */
add_action( 'admin_init', function () {
	if ( get_option( 'ricoman_stretch_ceiling_cat_v1' ) ) {
		return;
	}
	$tax = taxonomy_exists( 'product-cat' ) ? 'product-cat' : ( taxonomy_exists( 'product_cat' ) ? 'product_cat' : '' );
	if ( '' === $tax ) {
		return;
	}
	if ( ! term_exists( 'Stretch Ceiling Lighting', $tax ) ) {
		wp_insert_term( 'Stretch Ceiling Lighting', $tax, array(
			'slug'        => 'stretch-ceiling-lighting',
			'description' => 'Backlit stretched fabric ceilings, walls and light boxes, made to measure.',
		) );
	}
	update_option( 'ricoman_stretch_ceiling_cat_v1', '1' );
} );

/* ---------------------------------------------------------------------------
 * A little CSS for the step-number badge + spec list (loads front + editor).
 * Everything else reuses existing theme classes (rm-section, rm-aud-card, …).
 * ------------------------------------------------------------------------- */
add_action( 'enqueue_block_assets', function () {
	$css =
		'.rm-cel-step .rm-cel-num{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:var(--blue,#004899);color:#fff;font-family:Poppins,sans-serif;font-weight:700;font-size:.95rem;margin-bottom:6px}'
		. '.rm-cel-spec{max-width:900px}'
		. '.rm-cel-spec li{padding:10px 0;border-bottom:1px solid var(--line,#e6e4de);line-height:1.5}';
	wp_register_style( 'ricoman-celeste-inline', false );
	wp_enqueue_style( 'ricoman-celeste-inline' );
	wp_add_inline_style( 'ricoman-celeste-inline', $css );
} );

/* ---------------------------------------------------------------------------
 * Editable Celeste patterns.
 * ------------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'ricoman-celeste', array( 'label' => __( 'Ricoman — Celeste', 'ricoman' ) ) );
	}
	$cats = array( 'ricoman-celeste', 'ricoman-page' );

	// --- building blocks (valid core-block markup) ---------------------------
	$sec     = function ( $inner, $cls = '' ) { return '<!-- wp:group {"align":"full","className":"rm-section ' . $cls . '","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section ' . trim( $cls ) . '">' . $inner . '</div><!-- /wp:group -->'; };
	$h2      = function ( $t ) { return '<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">' . $t . '</h2><!-- /wp:heading -->'; };
	$h3      = function ( $t ) { return '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . $t . '</h3><!-- /wp:heading -->'; };
	$p       = function ( $t, $muted = false ) { return '<!-- wp:paragraph' . ( $muted ? ' {"textColor":"muted"}' : '' ) . ' --><p' . ( $muted ? ' class="has-muted-color has-text-color"' : '' ) . '>' . $t . '</p><!-- /wp:paragraph -->'; };
	$li      = function ( $t ) { return '<!-- wp:list-item --><li>' . $t . '</li><!-- /wp:list-item -->'; };
	$list    = function ( $items, $cls = '' ) { return '<!-- wp:list' . ( $cls ? ' {"className":"' . $cls . '"}' : '' ) . ' --><ul class="wp-block-list' . ( $cls ? ' ' . $cls : '' ) . '">' . $items . '</ul><!-- /wp:list -->'; };
	$btnsolid = function ( $label, $href ) { return '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . $href . '">' . $label . '</a></div><!-- /wp:button -->'; };
	$btnline  = function ( $label, $href, $light = false ) { $s = 'is-style-outline' . ( $light ? '-light' : '' ); return '<!-- wp:button {"className":"' . $s . '"} --><div class="wp-block-button ' . $s . '"><a class="wp-block-button__link wp-element-button" href="' . $href . '">' . $label . '</a></div><!-- /wp:button -->'; };
	$buttons  = function ( $inner ) { return '<!-- wp:buttons --><div class="wp-block-buttons">' . $inner . '</div><!-- /wp:buttons -->'; };
	$card     = function ( $inner ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card">' . $inner . '</div><!-- /wp:group --></div><!-- /wp:column -->'; };
	$cols     = function ( $inner ) { return '<!-- wp:columns --><div class="wp-block-columns">' . $inner . '</div><!-- /wp:columns -->'; };
	$eyebrow  = function ( $t ) { return '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $t . '</p><!-- /wp:paragraph -->'; };

	$builder = esc_url( ricoman_celeste_builder_url() );
	$design  = esc_url( home_url( '/lighting-design/' ) );
	$trade   = esc_url( home_url( '/trade/' ) );

	// 1) Hero intro ----------------------------------------------------------
	$hero = $sec(
		$h2( 'Stretched fabric light boxes, designed in minutes.' )
		. $p( 'Give your client a feature that lights up the whole space: a seamless printed graphic, an even glow from edge to edge, and no visible frame from the front. Design your Celeste light box online, download a complete spec straight away, and we&rsquo;ll come back with a firm quote.', true )
		. $list(
			$li( '<strong>Design it yourself, in minutes.</strong> Choose the size, shape, lighting and mounting in our builder and see it in 3D.' )
			. $li( '<strong>Quote with confidence.</strong> Every design comes with a full spec summary and a reference code, so nothing gets lost between you, your client and us.' )
			. $li( '<strong>Easy to live with.</strong> Graphics swap in minutes with no tools, so your client can refresh their space season after season.' )
		)
		. $buttons( $btnsolid( 'Build your own light box →', $builder ) . $btnline( 'Request a Lighting Design', $design ) . $btnline( 'Apply for a Trade Account', $trade ) )
	);
	register_block_pattern( 'ricoman/celeste-hero', array(
		'title'       => 'Celeste · Hero intro + bullets',
		'description' => 'Celeste intro: subheading, lead paragraph, the three value bullets and the main CTAs.',
		'categories'  => $cats,
		'content'     => $hero,
	) );

	// 2) Build your own ------------------------------------------------------
	$step = function ( $n, $h, $t ) use ( $card, $h3, $p ) {
		return $card( '<!-- wp:paragraph {"className":"rm-cel-num"} --><p class="rm-cel-num">' . $n . '</p><!-- /wp:paragraph -->' . $h3( $h ) . $p( $t, true ) );
	};
	$build = $sec(
		$h2( 'Build your own light box' )
		. $p( 'No more waiting days just to understand a job. Pick your options in the Celeste builder, turn your design in 3D, and send the spec to us in one click. You get a clear summary with a reference code to share with your client, and we get everything we need to quote.', true )
		. $cols( $step( '1', 'Design', 'Choose the size, shape, depth, lighting, fabric and mounting.' ) . $step( '2', 'Check', 'See it in 3D and as a measured drawing, then download the spec.' ) . $step( '3', 'Send', 'Submit it for a firm quote and we&rsquo;ll come back to you.' ) )
		. $buttons( $btnsolid( 'Build your own light box →', $builder ) ),
		'rm-oncards'
	);
	register_block_pattern( 'ricoman/celeste-build', array(
		'title'       => 'Celeste · Build your own (3 steps)',
		'description' => 'The "Build your own light box" section: Design / Check / Send, with the builder button.',
		'categories'  => $cats,
		'content'     => $build,
	) );

	// 3) Benefits ------------------------------------------------------------
	$benefit = function ( $h, $t ) use ( $card, $h3, $p ) { return $card( $h3( $h ) . $p( $t, true ) ); };
	$benefits = $sec(
		$h2( 'Why contractors choose Celeste' )
		. $cols( $benefit( 'Any space, any shape', 'Up to 4.9 m wide and any length, in rectangles, circles, rings or cut-outs. Large runs arrive in sections and join on site with no visible gaps.' ) . $benefit( 'The right white for the room', 'Warm 3000K for restaurants and hotels, neutral for workplaces, cool for retail, or tunable from warm to cool through the day. Celeste is not limited to cold signage white.' ) . $benefit( 'Colour and movement when you want it', 'RGB, RGBW and pixel-addressable options turn a ceiling into a moving sky or a wall into changing brand colour.' ) )
		. $cols( $benefit( 'Graphic changes in minutes', 'The silicone-edged fabric pushes into the frame, so a new season&rsquo;s artwork goes up without tools or a callout.' ) . $benefit( 'Fits the way you build', 'Wall, ceiling, suspended or freestanding, from 16 mm ultra-slim to double-sided. A built-in or remote driver suits the access on site.' ) . $benefit( 'Fewer surprises at handover', 'Flame-retardant fabric, a clear spec for every unit and a 3-year warranty.' ) )
	);
	register_block_pattern( 'ricoman/celeste-benefits', array(
		'title'       => 'Celeste · Why contractors choose Celeste (cards)',
		'description' => 'Six benefit cards. Edit the text, add or remove cards.',
		'categories'  => $cats,
		'content'     => $benefits,
	) );

	// 4) Specification at a glance ------------------------------------------
	$spec = $sec(
		$h2( 'Specification at a glance' )
		. $p( 'The full detail is in the downloadable specification document.', true )
		. $list(
			$li( '<strong>Types:</strong> SEG backlit, ultra-slim LGP (16&ndash;45 mm deep), double-sided' )
			. $li( '<strong>Size:</strong> up to 4.9 m wide &times; any length; ultra-slim up to 1220 &times; 2440 mm' )
			. $li( '<strong>Shapes:</strong> rectangle, rounded, circle, ring, cut-out, custom' )
			. $li( '<strong>Lighting:</strong> 3000K to 6500K, tunable white, RGB, RGBW, pixel-addressable' )
			. $li( '<strong>Control:</strong> switched, dimmable (mains, 0&ndash;10V, DALI), Casambi, DMX' )
			. $li( '<strong>Fabric:</strong> 100% flame-retardant polyester, dye-sublimation printed' )
			. $li( '<strong>Frame:</strong> aluminium, silver anodised as standard, plus black, white or custom RAL' )
			. $li( '<strong>Rating:</strong> indoor as standard, IP65 version available' )
			. $li( '<strong>Warranty:</strong> 3 years &middot; CE and RoHS' ),
			'rm-cel-spec'
		),
		'rm-oncards'
	);
	register_block_pattern( 'ricoman/celeste-spec', array(
		'title'       => 'Celeste · Specification at a glance',
		'description' => 'A short at-a-glance spec list. The full detail stays in the downloadable spec document.',
		'categories'  => $cats,
		'content'     => $spec,
	) );

	// 5) FAQ (FAQPage schema via [ricoman_faq]) ------------------------------
	$faq_sc = "[ricoman_faq]\n"
		. "Q: How do I get a price for Celeste?\n"
		. "A: Design your light box in our online builder and send us the spec in one click. You'll get a complete summary with a reference code straight away, and we'll come back with a firm quote.\n"
		. "Q: Can Celeste work in a restaurant, hotel or office, not just retail?\n"
		. "A: Yes. Celeste comes in warm 3000K as well as neutral and cool whites, and in tunable white that shifts through the day, so it suits hospitality and workplace interiors as well as shop fronts.\n"
		. "Q: How big can a Celeste light box be?\n"
		. "A: Up to 4.9 m wide and any length. Large faces are made in sections and joined on site with no visible gaps in the fabric.\n"
		. "Q: How does my client change the graphic?\n"
		. "A: The printed fabric has a silicone edge that pushes into the frame. Your client pulls the old graphic out and presses the new one in, in minutes and without tools.\n"
		. "Q: Can Celeste be used on a ceiling?\n"
		. "A: Yes. Celeste can be mounted face down on a ceiling or suspended, including circles, rings and cut-outs. Printed sky graphics and pixel-addressable lighting create a moving sky.\n"
		. "Q: What warranty does Celeste carry?\n"
		. "A: 3 years, and every unit is CE and RoHS compliant.\n"
		. "[/ricoman_faq]";
	$faq = $sec( $h2( 'Frequently asked questions' ) . '<!-- wp:shortcode -->' . $faq_sc . '<!-- /wp:shortcode -->' );
	register_block_pattern( 'ricoman/celeste-faq', array(
		'title'       => 'Celeste · FAQ',
		'description' => 'Celeste FAQ (emits FAQPage schema). Edit the Q: / A: lines in the shortcode block.',
		'categories'  => $cats,
		'content'     => $faq,
	) );

	// 6) Specify CTA (dark band) --------------------------------------------
	$cta = '<!-- wp:group {"align":"full","className":"rm-section","backgroundColor":"ink","textColor":"base","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section has-base-color has-ink-background-color has-text-color has-background">'
		. $h2( 'Ready to light up your next fit-out?' )
		. $p( 'Design your Celeste light box online, or send us a drawing and we&rsquo;ll do it for you.' )
		. $buttons( $btnsolid( 'Build your own light box →', $builder ) . $btnline( 'Request a Lighting Design', $design, true ) )
		. '</div><!-- /wp:group -->';
	register_block_pattern( 'ricoman/celeste-cta', array(
		'title'       => 'Celeste · Specify CTA (dark band)',
		'description' => 'Closing call-to-action band.',
		'categories'  => $cats,
		'content'     => $cta,
	) );

	// Convenience: the whole page in one pattern (all sections, still editable).
	register_block_pattern( 'ricoman/celeste-all', array(
		'title'       => 'Celeste · Whole page (all sections)',
		'description' => 'Every Celeste section in order — drop in once, then edit/rearrange. Add your gallery, before/after image, dimensions drawing and downloads around it.',
		'categories'  => $cats,
		'content'     => $hero . $build . $benefits . $spec . $faq . $cta,
	) );
}, 14 );
