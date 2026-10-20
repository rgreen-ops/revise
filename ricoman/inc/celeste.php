<?php
/**
 * Celeste — stretched fabric light box product page body.
 *
 * A bespoke marketing body for the Celeste product, built from the product-page
 * brief (v2). Rendered by the [ricoman_celeste] shortcode so it can be dropped
 * into the Celeste product's content; the product itself still provides the
 * image gallery, downloads, "Add to My Project", breadcrumb (Home / Products /
 * Stretch Ceiling Lighting / Celeste) and "You may also like".
 *
 * Sections: hero + bullets + CTAs · Build your own (3 steps) · Why contractors
 * choose Celeste (benefit cards) · before/after image · Specification at a glance
 * · Dimensions · FAQ (FAQPage schema via [ricoman_faq]) · final CTA.
 *
 * The "Build your own light box" button points at ricoman_celeste_builder_url()
 * (a placeholder until the builder tool is live).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Public URL of the Celeste "Build your own light box" builder. Filterable. */
function ricoman_celeste_builder_url() {
	return apply_filters( 'ricoman_celeste_builder_url', '#build' );
}

/** The dimensions drawing (profile depths). Swap for a real image URL/attachment. */
function ricoman_celeste_dimensions_img() {
	return apply_filters( 'ricoman_celeste_dimensions_img', '' );
}

/** One-time CSS for the Celeste body (printed once per request). */
function ricoman_celeste_css() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	$css =
		'.rm-cel-sec{padding:clamp(40px,6vw,88px) 0}'
		. '.rm-cel-sec--tight{padding:clamp(28px,4vw,56px) 0}'
		. '.rm-cel-wrap{max-width:1200px;margin:0 auto;padding:0 24px;box-sizing:border-box}'
		. '.rm-cel-eyebrow{font-family:Poppins,sans-serif;font-weight:600;font-size:.78rem;letter-spacing:.16em;text-transform:uppercase;color:var(--blue,#004899);margin:0 0 14px}'
		. '.rm-cel-h1{font-family:Poppins,sans-serif;font-weight:700;font-size:clamp(2.4rem,5vw,3.6rem);line-height:1.05;letter-spacing:-.02em;margin:0 0 .25em;color:var(--ink,#16161a)}'
		. '.rm-cel-sub{font-family:Poppins,sans-serif;font-weight:600;font-size:clamp(1.15rem,2.2vw,1.6rem);line-height:1.2;color:var(--ink,#16161a);margin:0 0 .6em}'
		. '.rm-cel-lead{font-size:clamp(1rem,1.3vw,1.12rem);line-height:1.6;color:var(--muted,#5b6068);max-width:60ch;margin:0 0 1.6em}'
		. '.rm-cel-h2{font-family:Poppins,sans-serif;font-weight:700;font-size:clamp(1.6rem,3vw,2.3rem);line-height:1.1;letter-spacing:-.01em;margin:0 0 .5em;color:var(--ink,#16161a)}'
		. '.rm-cel-intro{font-size:1.02rem;line-height:1.6;color:var(--muted,#5b6068);max-width:64ch;margin:0 0 2em}'
		// Hero intro bullets
		. '.rm-cel-bullets{list-style:none;margin:0 0 2em;padding:0;display:grid;gap:16px;max-width:62ch}'
		. '.rm-cel-bullets li{position:relative;padding-left:30px;font-size:1rem;line-height:1.5;color:var(--ink,#16161a)}'
		. '.rm-cel-bullets li::before{content:"";position:absolute;left:0;top:2px;width:18px;height:18px;border-radius:50%;background:var(--blue,#004899);'
		. "background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='none' stroke='white' stroke-width='2' d='M2 6l3 3 5-6'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:center}"
		. '.rm-cel-bullets strong{font-weight:600}'
		// CTA row + buttons
		. '.rm-cel-ctas{display:flex;flex-wrap:wrap;gap:12px;margin-top:8px}'
		// Anchor tabs
		. '.rm-cel-tabs{display:flex;flex-wrap:wrap;gap:8px 28px;border-top:1px solid var(--line,#e6e4de);border-bottom:1px solid var(--line,#e6e4de);padding:14px 0}'
		. '.rm-cel-tabs a{font-family:Poppins,sans-serif;font-weight:600;font-size:.82rem;letter-spacing:.04em;text-transform:uppercase;color:var(--ink,#16161a);text-decoration:none}'
		. '.rm-cel-tabs a:hover{color:var(--blue,#004899)}'
		// Build-your-own steps
		. '.rm-cel-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(18px,2.4vw,32px);margin:0 0 2em}'
		. '.rm-cel-step{background:var(--surface,#f6f5f2);border-radius:14px;padding:26px 24px}'
		. '.rm-cel-step-n{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:var(--blue,#004899);color:#fff;font-family:Poppins,sans-serif;font-weight:700;font-size:.95rem;margin-bottom:14px}'
		. '.rm-cel-step h3{font-family:Poppins,sans-serif;font-weight:600;font-size:1.05rem;margin:0 0 .4em;color:var(--ink,#16161a)}'
		. '.rm-cel-step p{margin:0;font-size:.92rem;line-height:1.5;color:var(--muted,#5b6068)}'
		// Benefit cards
		. '.rm-cel-benefits{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(18px,2.4vw,28px)}'
		. '.rm-cel-benefit{border:1px solid var(--line,#e6e4de);border-radius:14px;padding:26px 24px;background:#fff}'
		. '.rm-cel-benefit h3{font-family:Poppins,sans-serif;font-weight:600;font-size:1.1rem;margin:0 0 .45em;color:var(--ink,#16161a)}'
		. '.rm-cel-benefit p{margin:0;font-size:.94rem;line-height:1.55;color:var(--muted,#5b6068)}'
		// Spec at a glance
		. '.rm-cel-spec{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:12px 40px;max-width:920px}'
		. '.rm-cel-spec li{display:grid;grid-template-columns:120px 1fr;gap:16px;padding:12px 0;border-bottom:1px solid var(--line,#e6e4de);font-size:.95rem;line-height:1.45}'
		. '.rm-cel-spec dt,.rm-cel-spec .k{font-family:Poppins,sans-serif;font-weight:600;color:var(--ink,#16161a)}'
		. '.rm-cel-spec .v{color:var(--muted,#5b6068)}'
		// Dimensions + before/after
		. '.rm-cel-figure{margin:0}'
		. '.rm-cel-figure img{display:block;width:100%;height:auto;border-radius:14px}'
		. '.rm-cel-figure figcaption{margin-top:12px;font-size:.88rem;color:var(--muted,#5b6068);text-align:center}'
		. '.rm-cel-ph{display:flex;align-items:center;justify-content:center;min-height:300px;border:1px dashed var(--line,#cfccc4);border-radius:14px;color:var(--muted,#8a8a8a);font-size:.9rem;text-align:center;padding:24px;background:var(--surface,#f6f5f2)}'
		// Alt background section
		. '.rm-cel-sec--alt{background:var(--surface,#f6f5f2)}'
		// Final CTA
		. '.rm-cel-cta-band{background:var(--ink,#16161a);color:#fff;border-radius:18px;padding:clamp(32px,5vw,56px)}'
		. '.rm-cel-cta-band .rm-cel-h2{color:#fff}'
		. '.rm-cel-cta-band p{color:rgba(255,255,255,.78);font-size:1.02rem;line-height:1.6;max-width:56ch;margin:0 0 1.4em}'
		// Responsive
		. '@media(max-width:860px){.rm-cel-steps,.rm-cel-benefits{grid-template-columns:1fr}.rm-cel-spec{grid-template-columns:1fr}}';
	return '<style id="rm-celeste-css">' . $css . '</style>';
}

/**
 * [ricoman_celeste context="full|product"]
 *
 * context="full"    — standalone page: shows the H1, subheading and anchor tabs.
 * context="product" — injected into the Celeste product (the product header
 *                     already provides the title + gallery + CTAs), so the
 *                     duplicate H1/subheading and anchor tabs are omitted.
 */
function ricoman_celeste_sc( $atts = array() ) {
	$a       = shortcode_atts( array( 'context' => 'full' ), (array) $atts, 'ricoman_celeste' );
	$inprod  = ( 'product' === $a['context'] );
	$builder = esc_url( ricoman_celeste_builder_url() );
	$design  = esc_url( home_url( '/lighting-design/' ) );
	$trade   = esc_url( home_url( '/trade/' ) );
	$dims    = ricoman_celeste_dimensions_img();

	$btn = function ( $href, $label, $cls = 'btn-solid' ) {
		return '<a class="btn ' . esc_attr( $cls ) . '" href="' . esc_url( $href ) . '">' . esc_html( $label ) . '</a>';
	};

	ob_start();
	echo ricoman_celeste_css(); // phpcs:ignore WordPress.Security.EscapeOutput

	/* 2. Hero / intro ----------------------------------------------------- */
	?>
	<section class="rm-cel-sec" id="top">
		<div class="rm-cel-wrap">
			<?php if ( ! $inprod ) : ?>
				<p class="rm-cel-eyebrow">Stretch Ceiling Lighting</p>
				<h1 class="rm-cel-h1">Celeste</h1>
			<?php endif; ?>
			<p class="rm-cel-sub">Stretched fabric light boxes, designed in minutes.</p>
			<p class="rm-cel-lead">Give your client a feature that lights up the whole space: a seamless printed graphic, an even glow from edge to edge, and no visible frame from the front. Design your Celeste light box online, download a complete spec straight away, and we&rsquo;ll come back with a firm quote.</p>
			<ul class="rm-cel-bullets">
				<li><strong>Design it yourself, in minutes.</strong> Choose the size, shape, lighting and mounting in our builder and see it in 3D.</li>
				<li><strong>Quote with confidence.</strong> Every design comes with a full spec summary and a reference code, so nothing gets lost between you, your client and us.</li>
				<li><strong>Easy to live with.</strong> Graphics swap in minutes with no tools, so your client can refresh their space season after season.</li>
			</ul>
			<div class="rm-cel-ctas">
				<?php
				echo $btn( $builder, 'Build your own light box →', 'btn-solid' ); // phpcs:ignore WordPress.Security.EscapeOutput
				if ( ! $inprod ) {
					echo $btn( $design, 'Request a Lighting Design', 'btn-line' ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo $btn( $trade, 'Apply for a Trade Account', 'btn-line' );  // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</div>
		</div>
	</section>

	<?php if ( ! $inprod ) : ?>
	<!-- 3. Anchor tabs -->
	<div class="rm-cel-wrap">
		<nav class="rm-cel-tabs" aria-label="On this page">
			<a href="#build">Build Your Own</a>
			<a href="#benefits">Benefits</a>
			<a href="#specification">Specifications</a>
			<a href="#downloads">Downloads</a>
		</nav>
	</div>
	<?php endif; ?>

	<!-- 4. Build your own -->
	<section class="rm-cel-sec rm-cel-sec--alt" id="build">
		<div class="rm-cel-wrap">
			<h2 class="rm-cel-h2">Build your own light box</h2>
			<p class="rm-cel-intro">No more waiting days just to understand a job. Pick your options in the Celeste builder, turn your design in 3D, and send the spec to us in one click. You get a clear summary with a reference code to share with your client, and we get everything we need to quote.</p>
			<div class="rm-cel-steps">
				<div class="rm-cel-step"><span class="rm-cel-step-n">1</span><h3>Design</h3><p>Choose the size, shape, depth, lighting, fabric and mounting.</p></div>
				<div class="rm-cel-step"><span class="rm-cel-step-n">2</span><h3>Check</h3><p>See it in 3D and as a measured drawing, then download the spec.</p></div>
				<div class="rm-cel-step"><span class="rm-cel-step-n">3</span><h3>Send</h3><p>Submit it for a firm quote and we&rsquo;ll come back to you.</p></div>
			</div>
			<div class="rm-cel-ctas"><?php echo $btn( $builder, 'Build your own light box →', 'btn-solid' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		</div>
	</section>

	<!-- 5. Why contractors choose Celeste -->
	<section class="rm-cel-sec" id="benefits">
		<div class="rm-cel-wrap">
			<h2 class="rm-cel-h2">Why contractors choose Celeste</h2>
			<div class="rm-cel-benefits">
				<div class="rm-cel-benefit"><h3>Any space, any shape</h3><p>Up to 4.9 m wide and any length, in rectangles, circles, rings or cut-outs. Large runs arrive in sections and join on site with no visible gaps.</p></div>
				<div class="rm-cel-benefit"><h3>The right white for the room</h3><p>Warm 3000K for restaurants and hotels, neutral for workplaces, cool for retail, or tunable from warm to cool through the day. Celeste is not limited to cold signage white.</p></div>
				<div class="rm-cel-benefit"><h3>Colour and movement when you want it</h3><p>RGB, RGBW and pixel-addressable options turn a ceiling into a moving sky or a wall into changing brand colour.</p></div>
				<div class="rm-cel-benefit"><h3>Graphic changes in minutes</h3><p>The silicone-edged fabric pushes into the frame, so a new season&rsquo;s artwork goes up without tools or a callout.</p></div>
				<div class="rm-cel-benefit"><h3>Fits the way you build</h3><p>Wall, ceiling, suspended or freestanding, from 16 mm ultra-slim to double-sided. A built-in or remote driver suits the access on site.</p></div>
				<div class="rm-cel-benefit"><h3>Fewer surprises at handover</h3><p>Flame-retardant fabric, a clear spec for every unit and a 3-year warranty.</p></div>
			</div>
		</div>
	</section>

	<!-- 6. Before / after image block -->
	<section class="rm-cel-sec--tight">
		<div class="rm-cel-wrap">
			<figure class="rm-cel-figure">
				<div class="rm-cel-ph">[ Before &amp; after: a plain ceiling, then the same space with a Celeste sky ceiling ]</div>
				<figcaption>From plain ceiling to open sky.</figcaption>
			</figure>
		</div>
	</section>

	<!-- 7. Specification at a glance -->
	<section class="rm-cel-sec rm-cel-sec--alt" id="specification">
		<div class="rm-cel-wrap">
			<h2 class="rm-cel-h2">Specification at a glance</h2>
			<p class="rm-cel-intro">The full detail is in the downloadable specification document.</p>
			<ul class="rm-cel-spec">
				<li><span class="k">Types</span><span class="v">SEG backlit, ultra-slim LGP (16&ndash;45 mm deep), double-sided</span></li>
				<li><span class="k">Size</span><span class="v">Up to 4.9 m wide &times; any length; ultra-slim up to 1220 &times; 2440 mm</span></li>
				<li><span class="k">Shapes</span><span class="v">Rectangle, rounded, circle, ring, cut-out, custom</span></li>
				<li><span class="k">Lighting</span><span class="v">3000K to 6500K, tunable white, RGB, RGBW, pixel-addressable</span></li>
				<li><span class="k">Control</span><span class="v">Switched, dimmable (mains, 0&ndash;10V, DALI), Casambi, DMX</span></li>
				<li><span class="k">Fabric</span><span class="v">100% flame-retardant polyester, dye-sublimation printed</span></li>
				<li><span class="k">Frame</span><span class="v">Aluminium, silver anodised as standard, plus black, white or custom RAL</span></li>
				<li><span class="k">Rating</span><span class="v">Indoor as standard, IP65 version available</span></li>
				<li><span class="k">Warranty</span><span class="v">3 years &middot; CE and RoHS</span></li>
			</ul>
		</div>
	</section>

	<!-- 8. Dimensions -->
	<section class="rm-cel-sec--tight">
		<div class="rm-cel-wrap">
			<h2 class="rm-cel-h2">Dimensions</h2>
			<figure class="rm-cel-figure">
				<?php if ( $dims ) : ?>
					<img src="<?php echo esc_url( $dims ); ?>" alt="Celeste profile depths from 16 to 120 mm" loading="lazy">
				<?php else : ?>
					<div class="rm-cel-ph">[ Profile depths 16&ndash;120 mm, side by side (from the specification document, page 3) ]</div>
				<?php endif; ?>
			</figure>
		</div>
	</section>

	<!-- 11. FAQ (FAQPage schema) -->
	<section class="rm-cel-sec" id="faq">
		<div class="rm-cel-wrap">
			<h2 class="rm-cel-h2">Frequently asked questions</h2>
			<?php
			echo do_shortcode(
				"[ricoman_faq]\n"
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
				. "[/ricoman_faq]"
			); // phpcs:ignore WordPress.Security.EscapeOutput
			?>
		</div>
	</section>

	<!-- 12. Specify this product -->
	<section class="rm-cel-sec" id="specify">
		<div class="rm-cel-wrap">
			<div class="rm-cel-cta-band">
				<h2 class="rm-cel-h2">Ready to light up your next fit-out?</h2>
				<p>Design your Celeste light box online, or send us a drawing and we&rsquo;ll do it for you.</p>
				<div class="rm-cel-ctas">
					<?php
					echo $btn( $builder, 'Build your own light box →', 'btn-solid' ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo $btn( $design, 'Request a Lighting Design', 'btn-line-d' );   // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</div>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'ricoman_celeste', 'ricoman_celeste_sc' );

/**
 * Is this the Celeste product? (slug starts with "celeste"). Filterable so other
 * products can opt in/out.
 */
function ricoman_is_celeste_product( $pid ) {
	$post = get_post( $pid );
	$is   = ( $post instanceof WP_Post && 0 === strpos( (string) $post->post_name, 'celeste' ) );
	return (bool) apply_filters( 'ricoman_is_celeste_product', $is, $pid );
}

/**
 * Auto-render the Celeste body on the Celeste product page. The product's managed
 * layout ignores the standard content area, so we inject the body (hero-less, as
 * the product header already carries the title + CTAs) just above the Downloads
 * section. Runs at priority 12 — after acf-product's product render at 11.
 */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_singular( 'product' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( false !== strpos( $content, 'rm-cel-sec' ) ) {
		return $content; // already present (e.g. added as a pattern).
	}
	if ( ! ricoman_is_celeste_product( get_the_ID() ) ) {
		return $content;
	}
	$body   = ricoman_celeste_sc( array( 'context' => 'product' ) );
	$marker = '<div class="rm-section" id="downloads">';
	if ( false !== strpos( $content, $marker ) ) {
		return str_replace( $marker, $body . $marker, $content );
	}
	return $content . $body;
}, 12 );

/* ---------------------------------------------------------------------------
 * New product category: Stretch Ceiling Lighting (create-once).
 * ------------------------------------------------------------------------- */
add_action( 'admin_init', function () {
	if ( get_option( 'ricoman_stretch_ceiling_cat_v1' ) ) {
		return;
	}
	$tax = taxonomy_exists( 'product-cat' ) ? 'product-cat' : ( taxonomy_exists( 'product_cat' ) ? 'product_cat' : '' );
	if ( '' === $tax ) {
		return; // taxonomy not registered yet; try again next load.
	}
	if ( ! term_exists( 'Stretch Ceiling Lighting', $tax ) ) {
		wp_insert_term( 'Stretch Ceiling Lighting', $tax, array(
			'slug'        => 'stretch-ceiling-lighting',
			'description' => 'Backlit stretched fabric ceilings, walls and light boxes, made to measure.',
		) );
	}
	update_option( 'ricoman_stretch_ceiling_cat_v1', '1' );
} );

/* Editable pattern wrapper so the body can be inserted from the Patterns list. */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern( 'ricoman/celeste-body', array(
		'title'       => 'Celeste · Product page body',
		'description' => 'The full Celeste light box page body (hero, build-your-own, benefits, spec, dimensions, FAQ, CTA). Drop into the Celeste product content.',
		'categories'  => array( 'ricoman-page' ),
		'content'     => '<!-- wp:shortcode -->[ricoman_celeste]<!-- /wp:shortcode -->',
	) );
}, 14 );
