<?php
/**
 * Celeste — stretched fabric light box.
 *
 * Visual, EDITABLE block patterns so the team builds and rearranges the Celeste
 * page themselves (Product Page Editor → "Add section or pattern"), then edits
 * text, images and buttons like any block. Rich sections — cover hero, shapes
 * showcase, colour/lighting strip, full-bleed image+text panels, a bold "build"
 * band, icon benefit cards and a cover CTA — all in the Ricoman look.
 *
 * Also creates the "Stretch Ceiling Lighting" product category.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Public URL of the Celeste "Build your own light box" builder. Filterable. */
function ricoman_celeste_builder_url() {
	return apply_filters( 'ricoman_celeste_builder_url', home_url( '/light-box-builder/' ) );
}

/** Where Light Box Builder specs are sent. Filterable. */
function ricoman_lightbox_inbox() {
	return apply_filters( 'ricoman_lightbox_inbox', 'support@ricoman.com' );
}

/**
 * Is this the Celeste product? Matches by slug OR title (drafts often have an
 * empty slug) OR the Stretch Ceiling Lighting category. Filterable.
 */
function ricoman_is_celeste_product( $pid ) {
	$post = get_post( $pid );
	if ( ! $post instanceof WP_Post ) {
		return (bool) apply_filters( 'ricoman_is_celeste_product', false, $pid );
	}
	$is = ( 0 === strpos( (string) $post->post_name, 'celeste' ) )
		|| ( false !== stripos( (string) $post->post_title, 'celeste' ) );
	if ( ! $is ) {
		$tax   = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
		$terms = get_the_terms( $pid, $tax );
		if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( 'stretch-ceiling-lighting' === $t->slug ) {
					$is = true;
					break;
				}
			}
		}
	}
	return (bool) apply_filters( 'ricoman_is_celeste_product', $is, $pid );
}

/**
 * AJAX: capture a Light Box Builder spec submission as a lead + email sales.
 * The builder is a static tool (no WP nonce context), so this relies on the
 * shared spam screen + a honeypot rather than a nonce.
 */
function ricoman_lightbox_spec_capture() {
	$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$customer = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) )
		: ( isset( $_POST['customer'] ) ? sanitize_text_field( wp_unslash( $_POST['customer'] ) ) : '' );
	$phone    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$role     = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
	$notes    = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
	$code     = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
	$summary  = isset( $_POST['summary'] ) ? sanitize_text_field( wp_unslash( $_POST['summary'] ) ) : '';
	$spec     = isset( $_POST['spec'] ) ? sanitize_textarea_field( wp_unslash( $_POST['spec'] ) ) : '';
	$design   = isset( $_POST['design'] ) ? esc_url_raw( wp_unslash( $_POST['design'] ) ) : '';
	$ts       = isset( $_POST['ts'] ) ? (int) $_POST['ts'] : 0;
	$hp       = isset( $_POST['rm_hp'] ) ? (string) wp_unslash( $_POST['rm_hp'] ) : '';

	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'msg' => __( 'Please enter a valid email.', 'ricoman' ) ) );
	}
	// Honeypot + shared spam screen — silently acknowledge spam.
	if ( '' !== $hp || ( function_exists( 'ricoman_lead_is_spam' ) && ricoman_lead_is_spam( array( 'ts' => $ts, 'fields' => array( $customer, $notes, $spec ) ) ) ) ) {
		wp_send_json_success( array( 'msg' => __( 'Thanks — your spec has been received.', 'ricoman' ) ) );
	}

	if ( '' === $name ) {
		$name = '' !== $customer ? $customer : $email;
	}
	$lines  = array();
	if ( '' !== $code ) {
		$lines[] = 'Build code: ' . $code;
	}
	if ( '' !== $summary ) {
		$lines[] = $summary;
	}
	if ( '' !== $spec ) {
		$lines[] = "\n" . $spec;
	}
	if ( '' !== $role ) {
		$lines[] = "\nRole: " . $role;
	}
	if ( '' !== $notes ) {
		$lines[] = "\nNotes: " . $notes;
	}
	if ( '' !== $design ) {
		$lines[] = "\nOpen this design: " . $design;
	}
	$message = trim( implode( "\n", $lines ) );

	$source  = 'Light Box Builder';
	$data    = array(
		'name'      => $name,
		'email'     => $email,
		'company'   => $customer,
		'phone'     => $phone,
		'role'      => $role,
		'message'   => $message,
		'source'    => $source,
		'items'     => '',
		'submitted' => current_time( 'mysql' ),
	);
	$attr    = function_exists( 'ricoman_lead_attribution' ) ? ricoman_lead_attribution() : array();
	$lead_id = wp_insert_post( array(
		'post_type'   => 'lead',
		'post_status' => 'private',
		'post_title'  => sprintf( '%s — Celeste light box%s', $name, ( '' !== $code ? ' (' . $code . ')' : '' ) ),
		'meta_input'  => array_merge( array(
			'_lead_name'    => $name,
			'_lead_email'   => $email,
			'_lead_company' => $customer,
			'_lead_phone'   => $phone,
			'_lead_role'    => $role,
			'_lead_type'    => __( 'Light box spec', 'ricoman' ),
			'_lead_message' => $message,
			'_lead_source'  => $source,
			'_lead_ref'     => $code,
		), $attr ),
	) );

	// Notify our team with the full spec.
	$subject = sprintf( 'Light Box Builder spec%s', ( '' !== $code ? ' — ' . $code : '' ) );
	$body    = sprintf(
		"New Light Box Builder spec\n\nFrom: %s\nEmail: %s\nPhone: %s\nCompany: %s\n\n%s\n",
		$name,
		$email,
		( '' !== $phone ? $phone : '—' ),
		( '' !== $customer ? $customer : '—' ),
		$message
	);
	wp_mail( ricoman_lightbox_inbox(), $subject, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );

	// Confirmation copy to the person who designed it.
	if ( function_exists( 'ricoman_send_lead_confirmation' ) ) {
		ricoman_send_lead_confirmation( $email, $name, array(
			'subject'  => sprintf( __( 'Your Celeste light box spec — %s', 'ricoman' ), get_bloginfo( 'name' ) ),
			'intro'    => __( 'Thanks for designing a Celeste light box. Here is the spec you sent us — our team will be in touch with a firm quote.', 'ricoman' ),
			'reply_to' => ricoman_lightbox_inbox(),
		) );
	}

	do_action( 'ricoman_lead_captured', $data, is_wp_error( $lead_id ) ? 0 : (int) $lead_id );

	wp_send_json_success( array( 'msg' => __( 'Thanks — your spec has been sent.', 'ricoman' ) ) );
}
add_action( 'wp_ajax_nopriv_rm_lightbox_spec', 'ricoman_lightbox_spec_capture' );
add_action( 'wp_ajax_rm_lightbox_spec', 'ricoman_lightbox_spec_capture' );

/**
 * Create the full-screen "Light Box Builder" tool page (create-once). It uses the
 * page-lightbox-builder template, which iframes the builder app in
 * assets/tools/lightbox-builder/. Kept out of search (noindex).
 */
add_action( 'admin_init', function () {
	if ( get_option( 'ricoman_lightbox_builder_page_v1' ) ) {
		return;
	}
	$existing = get_page_by_path( 'light-box-builder', OBJECT, 'page' );
	if ( ! $existing ) {
		$pid = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Light Box Builder',
			'post_name'    => 'light-box-builder',
			'post_content' => '',
		) );
		if ( $pid && ! is_wp_error( $pid ) ) {
			update_post_meta( $pid, '_wp_page_template', 'page-lightbox-builder' );
			update_post_meta( $pid, '_ricoman_seo_noindex', '1' );
		}
	}
	update_option( 'ricoman_lightbox_builder_page_v1', '1' );
} );

/* ---- New product category: Stretch Ceiling Lighting (create-once) --------- */
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

/* ---- Celeste visual CSS (loads front + editor) ---------------------------- */
add_action( 'enqueue_block_assets', function () {
	$blue = 'var(--blue,#004899)';
	$ink  = 'var(--ink,#16161a)';
	$mut  = 'var(--muted,#5b6068)';
	$line = 'var(--line,#e6e4de)';
	$css =
		// Shapes showcase
		".rm-cel-showcase{max-width:1120px;margin:0 auto}"
		. ".rm-cel-shapes{display:grid;grid-template-columns:repeat(6,1fr);gap:20px;margin-top:8px}"
		. ".rm-cel-shape{text-align:center}"
		. ".rm-cel-shape svg{width:66px;height:66px;color:$blue;display:block;margin:0 auto 12px;stroke:$blue;fill:none;stroke-width:1.5}"
		. ".rm-cel-shape .n{font-family:Poppins,sans-serif;font-weight:600;font-size:.95rem;color:$ink;display:block}"
		. ".rm-cel-shape .d{font-size:.78rem;color:$mut;display:block;margin-top:3px;line-height:1.35}"
		. "@media(max-width:860px){.rm-cel-shapes{grid-template-columns:repeat(3,1fr)}}"
		. "@media(max-width:460px){.rm-cel-shapes{grid-template-columns:repeat(2,1fr)}}"
		// Colour / lighting chips
		. ".rm-cel-chips{display:flex;flex-wrap:wrap;gap:14px;margin-top:8px}"
		. ".rm-cel-chip{flex:1 1 150px;border-radius:14px;min-height:118px;padding:16px;display:flex;flex-direction:column;justify-content:flex-end;font-family:Poppins,sans-serif;font-weight:600;font-size:.92rem;border:1px solid rgba(0,0,0,.06);box-shadow:inset 0 0 0 1px rgba(255,255,255,.25)}"
		. ".rm-cel-chip small{display:block;font-weight:400;font-size:.76rem;opacity:.85;margin-top:2px}"
		// Build band (dark)
		. ".rm-cel-band{background:$ink;color:#fff;border-radius:18px;padding:clamp(34px,5vw,64px)!important}"
		. ".rm-cel-band .rm-cel-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(20px,2.6vw,36px);margin:26px 0 30px}"
		. ".rm-cel-stepcard .num{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:50%;background:$blue;color:#fff;font-family:Poppins,sans-serif;font-weight:700;margin-bottom:12px}"
		. ".rm-cel-stepcard h3{font-family:Poppins,sans-serif;font-weight:600;font-size:1.1rem;color:#fff;margin:0 0 .35em}"
		. ".rm-cel-stepcard p{color:rgba(255,255,255,.72);font-size:.92rem;line-height:1.5;margin:0}"
		. "@media(max-width:760px){.rm-cel-band .rm-cel-steps{grid-template-columns:1fr}}"
		// Benefit icon cards
		. ".rm-cel-benefits{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}"
		. ".rm-cel-bcard{border:1px solid $line;border-radius:16px;padding:28px 26px;background:#fff;position:relative;overflow:hidden}"
		. ".rm-cel-bcard::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:$blue}"
		. ".rm-cel-bcard svg{width:34px;height:34px;color:$blue;stroke:$blue;fill:none;stroke-width:1.6;margin-bottom:14px}"
		. ".rm-cel-bcard h3{font-family:Poppins,sans-serif;font-weight:600;font-size:1.12rem;color:$ink;margin:0 0 .4em}"
		. ".rm-cel-bcard p{color:$mut;font-size:.94rem;line-height:1.55;margin:0}"
		. "@media(max-width:860px){.rm-cel-benefits{grid-template-columns:1fr}}"
		// Spec card band
		. ".rm-cel-specwrap{background:var(--surface,#f6f5f2);border-radius:16px;padding:clamp(26px,3vw,44px)}"
		. ".rm-cel-spec{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:0 48px}"
		. ".rm-cel-spec li{display:grid;grid-template-columns:118px 1fr;gap:14px;padding:13px 0;border-bottom:1px solid $line;font-size:.95rem;line-height:1.5}"
		. ".rm-cel-spec .k{font-family:Poppins,sans-serif;font-weight:600;color:$ink}"
		. ".rm-cel-spec .v{color:$mut}"
		. "@media(max-width:700px){.rm-cel-spec{grid-template-columns:1fr}}"
		// FAQ accordion — mirror [ricoman_faq]'s styles so it's always applied here
		// (its own inline <style> only prints once per request and can be consumed
		// before our FAQ renders on the product page).
		. ".ricoman-faq__item{border-top:1px solid $line;padding:0}"
		. ".ricoman-faq__item:last-child{border-bottom:1px solid $line}"
		. ".ricoman-faq summary{cursor:pointer;list-style:none;padding:1rem 0;font-weight:600;font-family:Poppins,sans-serif}"
		. ".ricoman-faq summary::-webkit-details-marker{display:none}"
		. ".ricoman-faq summary::after{content:'+';float:right;font-weight:400}"
		. ".ricoman-faq__item[open] summary::after{content:'\\2013'}"
		. ".ricoman-faq__a{padding:0 0 1rem;color:$mut}"
		// Build-your-own split CTA: numbered how-to steps + rounded image
		. ".rm-cel-howlist{list-style:none!important;counter-reset:rm-step;margin:1.2em 0 1.6em!important;padding:0!important;display:grid;gap:14px}"
		. ".rm-cel-howlist li{position:relative;padding-left:50px;min-height:34px;line-height:1.5;color:$ink}"
		. ".rm-cel-howlist li::before{counter-increment:rm-step;content:counter(rm-step);position:absolute;left:0;top:-2px;width:34px;height:34px;border-radius:50%;background:$blue;color:#fff;font-family:Poppins,sans-serif;font-weight:700;font-size:.92rem;display:flex;align-items:center;justify-content:center}"
		. ".rm-cel-howimg img{border-radius:16px;width:100%;height:auto;display:block}"
		// Safety net: on the product page, constrain any .rm-section whose content
		// was added without an inner .rm-pp-wrap (e.g. hand-built/pasted sections)
		// so it lines up with the site margins like every other section. Theme
		// sections (which use .rm-pp-wrap) and full-width blocks are excluded.
		. ".single-product .rm-section > :where(.wp-block-heading,.wp-block-paragraph,p,.wp-block-columns,.wp-block-list,ul,ol,.wp-block-buttons,.wp-block-image,figure):not(.alignfull):not(.alignwide):not(.rm-pp-wrap){max-width:1640px;margin-left:auto;margin-right:auto;padding-left:clamp(20px,4vw,72px);padding-right:clamp(20px,4vw,72px);box-sizing:border-box}";
	wp_register_style( 'ricoman-celeste-inline', false );
	wp_enqueue_style( 'ricoman-celeste-inline' );
	wp_add_inline_style( 'ricoman-celeste-inline', $css );
} );

/* ---- Editable Celeste patterns ------------------------------------------- */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'ricoman-celeste', array( 'label' => __( 'Ricoman — Celeste', 'ricoman' ) ) );
	}
	$cats = array( 'ricoman-celeste', 'ricoman-page' );
	$u    = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };

	/* building blocks (valid core-block markup) */
	// Full-bleed band (vertical padding) with an inner .rm-pp-wrap that constrains
	// the content to the site width — the same structure the theme's own product
	// sections use, so it works inside the product template (not just pages).
	$sec     = function ( $inner, $cls = '' ) { return '<!-- wp:group {"align":"full","className":"rm-section ' . $cls . '"} --><div class="wp-block-group alignfull rm-section ' . trim( $cls ) . '"><!-- wp:group {"className":"rm-pp-wrap","layout":{"type":"constrained"}} --><div class="wp-block-group rm-pp-wrap">' . $inner . '</div><!-- /wp:group --></div><!-- /wp:group -->'; };
	$eyebrow = function ( $t, $base = false ) { $c = $base ? ' {"className":"rm-eyebrow","textColor":"base"}' : ' {"className":"rm-eyebrow"}'; $cl = $base ? 'rm-eyebrow has-base-color has-text-color' : 'rm-eyebrow'; return '<!-- wp:paragraph' . $c . ' --><p class="' . $cl . '">' . $t . '</p><!-- /wp:paragraph -->'; };
	$h2      = function ( $t, $base = false ) { return '<!-- wp:heading {"level":2' . ( $base ? ',"textColor":"base"' : '' ) . ',"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead' . ( $base ? ' has-base-color has-text-color' : '' ) . '">' . $t . '</h2><!-- /wp:heading -->'; };
	$p       = function ( $t, $muted = false ) { return '<!-- wp:paragraph' . ( $muted ? ' {"textColor":"muted"}' : '' ) . ' --><p' . ( $muted ? ' class="has-muted-color has-text-color"' : '' ) . '>' . $t . '</p><!-- /wp:paragraph -->'; };
	$li      = function ( $t ) { return '<!-- wp:list-item --><li>' . $t . '</li><!-- /wp:list-item -->'; };
	$list    = function ( $items ) { return '<!-- wp:list --><ul class="wp-block-list">' . $items . '</ul><!-- /wp:list -->'; };
	$html    = function ( $h ) { return '<!-- wp:html -->' . $h . '<!-- /wp:html -->'; };
	$btnsolid = function ( $label, $href ) { return '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . $href . '">' . $label . '</a></div><!-- /wp:button -->'; };
	$btnline  = function ( $label, $href, $light = false ) { $s = 'is-style-outline' . ( $light ? '-light' : '' ); return '<!-- wp:button {"className":"' . $s . '"} --><div class="wp-block-button ' . $s . '"><a class="wp-block-button__link wp-element-button" href="' . $href . '">' . $label . '</a></div><!-- /wp:button -->'; };
	$buttons  = function ( $inner ) { return '<!-- wp:buttons --><div class="wp-block-buttons">' . $inner . '</div><!-- /wp:buttons -->'; };
	$cover    = function ( $img, $inner, $min = 62, $dim = 50 ) { $wrapped = '<!-- wp:group {"className":"rm-pp-wrap","layout":{"type":"constrained"}} --><div class="wp-block-group rm-pp-wrap">' . $inner . '</div><!-- /wp:group -->'; return '<!-- wp:cover {"url":"' . $img . '","dimRatio":' . $dim . ',"overlayColor":"ink","minHeight":' . $min . ',"minHeightUnit":"vh","contentPosition":"center center","align":"full","textColor":"base"} --><div class="wp-block-cover alignfull has-base-color has-text-color" style="min-height:' . $min . 'vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-' . $dim . ' has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $img . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">' . $wrapped . '</div></div><!-- /wp:cover -->'; };

	$builder = esc_url( ricoman_celeste_builder_url() );
	$design  = esc_url( home_url( '/lighting-design/' ) );
	$trade   = esc_url( home_url( '/trade/' ) );

	/* 1) Hero (cover) ------------------------------------------------------- */
	$hero = $cover(
		$u( 'ceiling.webp' ),
		$eyebrow( 'Stretch Ceiling Lighting', true )
		. $h2( 'Stretched fabric light boxes, designed in minutes.', true )
		. $p( 'A seamless printed graphic, an even glow from edge to edge, and no visible frame from the front. Design yours online and get a complete spec to quote from.' )
		. $buttons( $btnsolid( 'Build your own light box →', $builder ) . $btnline( 'Request a Lighting Design', $design, true ) ),
		64, 50
	);
	register_block_pattern( 'ricoman/celeste-hero', array(
		'title' => 'Celeste · Hero banner (image overlay)', 'categories' => $cats,
		'description' => 'Full-bleed cover image with the tagline + CTA. Swap the background image.',
		'content' => $hero,
	) );

	/* 2) Intro + value bullets --------------------------------------------- */
	$intro = $sec(
		$p( 'Give your client a feature that lights up the whole space. Design your Celeste light box online, download a complete spec straight away, and we&rsquo;ll come back with a firm quote.', true )
		. $list(
			$li( '<strong>Design it yourself, in minutes.</strong> Choose the size, shape, lighting and mounting in our builder and see it in 3D.' )
			. $li( '<strong>Quote with confidence.</strong> Every design comes with a full spec summary and a reference code.' )
			. $li( '<strong>Easy to live with.</strong> Graphics swap in minutes with no tools, season after season.' )
		)
	);
	register_block_pattern( 'ricoman/celeste-intro', array(
		'title' => 'Celeste · Intro + value bullets', 'categories' => $cats,
		'content' => $intro,
	) );

	/* 3) Made to measure — shapes showcase (SVG) --------------------------- */
	$shape = function ( $svg, $name, $desc ) { return '<div class="rm-cel-shape">' . $svg . '<span class="n">' . $name . '</span><span class="d">' . $desc . '</span></div>'; };
	$svg_rect = '<svg viewBox="0 0 48 48"><rect x="6" y="13" width="36" height="22" rx="1"/></svg>';
	$svg_rnd  = '<svg viewBox="0 0 48 48"><rect x="6" y="13" width="36" height="22" rx="7"/></svg>';
	$svg_cir  = '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="15"/></svg>';
	$svg_ring = '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="15"/><circle cx="24" cy="24" r="7"/></svg>';
	$svg_cut  = '<svg viewBox="0 0 48 48"><rect x="5" y="12" width="38" height="24" rx="2"/><rect x="17" y="20" width="14" height="8" rx="4"/></svg>';
	$svg_cus  = '<svg viewBox="0 0 48 48"><path d="M10 28c-2-8 6-16 14-14s14 6 12 14-8 12-16 10-8-4-10-10z"/></svg>';
	$shapes = $sec(
		$eyebrow( 'Made to measure' )
		. $h2( 'Any size, any shape' )
		. $p( 'Up to 4.9 m wide and any length. Large runs arrive in sections and join on site with no visible gaps.', true )
		. $html( '<div class="rm-cel-showcase"><div class="rm-cel-shapes">'
			. $shape( $svg_rect, 'Rectangle', 'Up to 4.9 m &times; any length' )
			. $shape( $svg_rnd, 'Rounded', 'Corner radius to suit' )
			. $shape( $svg_cir, 'Circle', '&Oslash;500 &ndash; &Oslash;4,900 mm' )
			. $shape( $svg_ring, 'Ring', 'Up to &Oslash;20,000 mm' )
			. $shape( $svg_cut, 'With cut-out', 'From 800 &times; 800 mm' )
			. $shape( $svg_cus, 'Custom', 'From your sketch or DWG' )
			. '</div></div>' )
	);
	register_block_pattern( 'ricoman/celeste-shapes', array(
		'title' => 'Celeste · Shapes showcase (icons)', 'categories' => $cats,
		'description' => 'The six shapes with icons — rectangle, rounded, circle, ring, cut-out, custom.',
		'content' => $shapes,
	) );

	/* 4) Colour & lighting strip ------------------------------------------- */
	$chip = function ( $bg, $name, $sub, $dark = true ) { return '<div class="rm-cel-chip" style="background:' . $bg . ';color:' . ( $dark ? '#16161a' : '#fff' ) . '">' . $name . '<small style="color:' . ( $dark ? '#16161a' : '#fff' ) . '">' . $sub . '</small></div>'; };
	$colour = $sec(
		$eyebrow( 'Light your way' )
		. $h2( 'The right white — or full colour' )
		. $p( 'Warm for hospitality, neutral for workplaces, cool for retail, tunable through the day, or full RGB and pixel-addressable for moving skies and brand colour.', true )
		. $html( '<div class="rm-cel-showcase"><div class="rm-cel-chips">'
			. $chip( '#ffd9ad', '3000K', 'Warm white' )
			. $chip( '#ffe9cf', '4000K', 'Neutral' )
			. $chip( '#eef3ff', '5000K', 'Cool' )
			. $chip( '#dbe8ff', '6500K', 'Daylight' )
			. $chip( 'linear-gradient(120deg,#f6c445,#ffffff 50%,#9ec1ff)', 'Tunable', 'Warm &rarr; cool' )
			. $chip( 'linear-gradient(120deg,#ff5f6d,#7a5cff 50%,#2bd4c4)', 'RGB / RGBW', 'Any colour', false )
			. $chip( 'linear-gradient(120deg,#ff3cac,#784ba0 45%,#2b86c5)', 'Pixel', 'Moving content', false )
			. '</div></div>' )
	);
	register_block_pattern( 'ricoman/celeste-colour', array(
		'title' => 'Celeste · Colour & lighting strip', 'categories' => $cats,
		'description' => 'Colour-temperature swatches plus tunable, RGB and pixel options.',
		'content' => $colour,
	) );

	/* 5) Media panels (full-bleed image + text, alternating) --------------- */
	$panel = function ( $img, $side, $h, $body ) {
		$imgcol = '<!-- wp:column {"width":"58%","verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:58%"><!-- wp:image {"sizeSlug":"full","className":"size-full"} --><figure class="wp-block-image size-full"><img src="' . $img . '" alt=""/></figure><!-- /wp:image --></div><!-- /wp:column -->';
		$txtpad = ( 'left' === $side ) ? 'left' : 'right';
		$txtcol = '<!-- wp:column {"width":"42%","verticalAlignment":"center","style":{"spacing":{"padding":{"' . $txtpad . '":"var:preset|spacing|50"}}}} --><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:42%;padding-' . $txtpad . ':var(--wp--preset--spacing--50)"><!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"600","lineHeight":"1.1"}}} --><h2 class="wp-block-heading" style="font-weight:600;line-height:1.1">' . $h . '</h2><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color">' . $body . '</p><!-- /wp:paragraph --></div><!-- /wp:column -->';
		$cls = ( 'left' === $side ) ? 'rm-mediapanel--left' : 'rm-mediapanel--right';
		$inner = ( 'left' === $side ) ? $imgcol . $txtcol : $txtcol . $imgcol;
		return '<!-- wp:group {"align":"full","className":"rm-section rm-mediapanel ' . $cls . '","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section rm-mediapanel ' . $cls . '"><!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center">' . $inner . '</div><!-- /wp:columns --></div><!-- /wp:group -->';
	};
	$panel1 = $panel( $u( 'retail.webp' ), 'left', 'Change the graphic in minutes', 'The silicone-edged fabric pushes into the frame, so a new season&rsquo;s artwork goes up without tools or a callout. Your client refreshes the space whenever they like.' );
	$panel2 = $panel( $u( 'ceiling.webp' ), 'right', 'From plain ceiling to open sky', 'Mount it face down or suspend it — circles, rings and cut-outs included. Printed sky graphics and pixel-addressable lighting turn a flat ceiling into a feature.' );
	register_block_pattern( 'ricoman/celeste-panel-left', array(
		'title' => 'Celeste · Feature panel (image left)', 'categories' => $cats,
		'description' => 'Full-bleed image beside a headline + copy. Swap the image and text.',
		'content' => $panel1,
	) );
	register_block_pattern( 'ricoman/celeste-panel-right', array(
		'title' => 'Celeste · Feature panel (image right)', 'categories' => $cats,
		'content' => $panel2,
	) );

	/* 6) Build your own — bold dark band ----------------------------------- */
	$stepcard = function ( $n, $h, $t ) { return '<div class="rm-cel-stepcard"><span class="num">' . $n . '</span><h3>' . $h . '</h3><p>' . $t . '</p></div>'; };
	$build = $sec(
		$html( '<div class="rm-cel-band">'
			. '<p class="rm-eyebrow has-base-color has-text-color" style="color:#9ec1ff">Build your own</p>'
			. '<h2 class="wp-block-heading rm-shead" style="color:#fff;margin:.1em 0 .3em">Design it, check it in 3D, send for a quote</h2>'
			. '<p style="color:rgba(255,255,255,.78);max-width:60ch">Pick your options in the Celeste builder, turn your design in 3D, and send the spec to us in one click — with a reference code to share with your client.</p>'
			. '<div class="rm-cel-steps">'
			. $stepcard( '1', 'Design', 'Choose the size, shape, depth, lighting, fabric and mounting.' )
			. $stepcard( '2', 'Check', 'See it in 3D and as a measured drawing, then download the spec.' )
			. $stepcard( '3', 'Send', 'Submit it for a firm quote and we&rsquo;ll come back to you.' )
			. '</div>'
			. '<a class="btn btn-solid" href="' . $builder . '" style="background:#fff;color:#16161a">Build your own light box →</a>'
			. '</div>' )
	);
	register_block_pattern( 'ricoman/celeste-build', array(
		'title' => 'Celeste · Build your own (bold band)', 'categories' => $cats,
		'description' => 'A dark highlight band: Design / Check / Send + the builder button.',
		'content' => $build,
	) );

	/* 6b) Build your own — split CTA: text + numbered steps left, image/GIF right */
	$col = function ( $w, $inner ) { return '<!-- wp:column {"width":"' . $w . '","verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:' . $w . '">' . $inner . '</div><!-- /wp:column -->'; };
	$howlist = '<!-- wp:list {"className":"rm-cel-howlist"} --><ul class="wp-block-list rm-cel-howlist">'
		. $li( '<strong>Design</strong> &mdash; choose the size, shape, depth, lighting, fabric and mounting.' )
		. $li( '<strong>Check</strong> &mdash; see it in 3D and as a measured drawing, then download the spec.' )
		. $li( '<strong>Send</strong> &mdash; submit it to our team for a firm quote.' )
		. '</ul><!-- /wp:list -->';
	$build_left = $eyebrow( 'Build your own' )
		. $h2( 'Design your own light box in minutes' )
		. $p( 'No more waiting days just to understand a job. Pick your options, turn your design in 3D, and send us the spec in one click &mdash; with a reference code to share with your client.', true )
		. $howlist
		. '<!-- wp:html --><p class="rm-cel-ctabtn"><a class="btn btn-solid" href="' . $builder . '">Build your own light box</a></p><!-- /wp:html -->';
	$build_right = '<!-- wp:image {"sizeSlug":"large","className":"rm-cel-howimg"} --><figure class="wp-block-image size-large rm-cel-howimg"><img src="' . $u( 'ceiling.webp' ) . '" alt="Designing a Celeste light box in the 3D builder"/></figure><!-- /wp:image -->';
	$build_split = '<!-- wp:group {"align":"full","className":"rm-section","backgroundColor":"surface","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section has-surface-background-color has-background">'
		. '<!-- wp:group {"className":"rm-pp-wrap","layout":{"type":"constrained"}} --><div class="wp-block-group rm-pp-wrap">'
		. '<!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center">'
		. $col( '54%', $build_left ) . $col( '46%', $build_right )
		. '</div><!-- /wp:columns -->'
		. '</div><!-- /wp:group --></div><!-- /wp:group -->';
	register_block_pattern( 'ricoman/celeste-build-cta', array(
		'title' => 'Celeste · Build your own CTA (steps + image)', 'categories' => $cats,
		'description' => 'Split call-to-action: title, copy and numbered how-to steps on the left, an image or GIF of the builder on the right, with the builder button. Swap the image for a short GIF of the tool.',
		'content' => $build_split,
	) );

	/* 7) Why contractors choose Celeste — icon cards ----------------------- */
	$ic_shape = '<svg viewBox="0 0 48 48"><rect x="7" y="13" width="34" height="22" rx="4"/><circle cx="24" cy="24" r="5"/></svg>';
	$ic_white = '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="12"/><path d="M24 6v4M24 38v4M6 24h4M38 24h4M11 11l3 3M34 34l3 3M37 11l-3 3M14 34l-3 3"/></svg>';
	$ic_rgb   = '<svg viewBox="0 0 48 48"><circle cx="19" cy="20" r="9"/><circle cx="29" cy="20" r="9"/><circle cx="24" cy="29" r="9"/></svg>';
	$ic_swap  = '<svg viewBox="0 0 48 48"><path d="M12 18h20l-5-5M36 30H16l5 5"/></svg>';
	$ic_mount = '<svg viewBox="0 0 48 48"><rect x="9" y="10" width="30" height="20" rx="2"/><path d="M24 30v6M16 40h16"/></svg>';
	$ic_shield= '<svg viewBox="0 0 48 48"><path d="M24 7l14 5v9c0 9-6 15-14 19-8-4-14-10-14-19v-9z"/><path d="M18 24l5 5 8-9"/></svg>';
	$bcard = function ( $svg, $h, $t ) { return '<div class="rm-cel-bcard">' . $svg . '<h3>' . $h . '</h3><p>' . $t . '</p></div>'; };
	$benefits = $sec(
		$eyebrow( 'Why Celeste' )
		. $h2( 'Why contractors choose Celeste' )
		. $html( '<div class="rm-cel-benefits">'
			. $bcard( $ic_shape, 'Any space, any shape', 'Up to 4.9 m wide and any length, in rectangles, circles, rings or cut-outs. Large runs join on site with no visible gaps.' )
			. $bcard( $ic_white, 'The right white for the room', 'Warm, neutral, cool or tunable white through the day — not limited to cold signage white.' )
			. $bcard( $ic_rgb, 'Colour and movement', 'RGB, RGBW and pixel-addressable options turn a ceiling into a moving sky or a wall into changing brand colour.' )
			. $bcard( $ic_swap, 'Graphic changes in minutes', 'The silicone-edged fabric pushes into the frame — new artwork goes up without tools or a callout.' )
			. $bcard( $ic_mount, 'Fits the way you build', 'Wall, ceiling, suspended or freestanding, from 16 mm ultra-slim to double-sided, with built-in or remote driver.' )
			. $bcard( $ic_shield, 'Fewer surprises at handover', 'Flame-retardant fabric, a clear spec for every unit and a 3-year warranty.' )
			. '</div>' )
	);
	register_block_pattern( 'ricoman/celeste-benefits', array(
		'title' => 'Celeste · Why choose Celeste (icon cards)', 'categories' => $cats,
		'description' => 'Six benefit cards with icons and an accent bar.',
		'content' => $benefits,
	) );

	/* 8) Specification at a glance ----------------------------------------- */
	$row = function ( $k, $v ) { return '<li><span class="k">' . $k . '</span><span class="v">' . $v . '</span></li>'; };
	$spec = $sec(
		$eyebrow( 'The detail' )
		. $h2( 'Specification at a glance' )
		. $p( 'The full detail is in the downloadable specification document.', true )
		. $html( '<div class="rm-cel-specwrap"><ul class="rm-cel-spec">'
			. $row( 'Types', 'SEG backlit, ultra-slim LGP (16&ndash;45 mm), double-sided' )
			. $row( 'Size', 'Up to 4.9 m wide &times; any length' )
			. $row( 'Shapes', 'Rectangle, rounded, circle, ring, cut-out, custom' )
			. $row( 'Lighting', '3000K&ndash;6500K, tunable, RGB, RGBW, pixel' )
			. $row( 'Control', 'Switched, dimmable, 0&ndash;10V, DALI, Casambi, DMX' )
			. $row( 'Fabric', '100% flame-retardant polyester, dye-sub printed' )
			. $row( 'Frame', 'Aluminium — silver, black, white or custom RAL' )
			. $row( 'Rating', 'Indoor as standard, IP65 version available' )
			. $row( 'Warranty', '3 years &middot; CE and RoHS' )
			. $row( 'Mounting', 'Wall, ceiling, suspended, freestanding' )
			. '</ul></div>' )
	);
	register_block_pattern( 'ricoman/celeste-spec', array(
		'title' => 'Celeste · Specification at a glance', 'categories' => $cats,
		'content' => $spec,
	) );

	/* 9) FAQ (FAQPage schema) ---------------------------------------------- */
	$faq_sc = "[ricoman_faq]\n"
		. "Q: How do I get a price for Celeste?\nA: Design your light box in our online builder and send us the spec in one click. You'll get a complete summary with a reference code straight away, and we'll come back with a firm quote.\n"
		. "Q: Can Celeste work in a restaurant, hotel or office, not just retail?\nA: Yes. Celeste comes in warm 3000K as well as neutral and cool whites, and in tunable white that shifts through the day, so it suits hospitality and workplace interiors as well as shop fronts.\n"
		. "Q: How big can a Celeste light box be?\nA: Up to 4.9 m wide and any length. Large faces are made in sections and joined on site with no visible gaps in the fabric.\n"
		. "Q: How does my client change the graphic?\nA: The printed fabric has a silicone edge that pushes into the frame. Your client pulls the old graphic out and presses the new one in, in minutes and without tools.\n"
		. "Q: Can Celeste be used on a ceiling?\nA: Yes. Celeste can be mounted face down on a ceiling or suspended, including circles, rings and cut-outs. Printed sky graphics and pixel-addressable lighting create a moving sky.\n"
		. "Q: What warranty does Celeste carry?\nA: 3 years, and every unit is CE and RoHS compliant.\n"
		. "[/ricoman_faq]";
	$faq = $sec( $eyebrow( 'Good to know' ) . $h2( 'Frequently asked questions' ) . '<!-- wp:shortcode -->' . $faq_sc . '<!-- /wp:shortcode -->' );
	register_block_pattern( 'ricoman/celeste-faq', array(
		'title' => 'Celeste · FAQ', 'categories' => $cats,
		'description' => 'Celeste FAQ (emits FAQPage schema). Edit the Q: / A: lines.',
		'content' => $faq,
	) );

	/* 10) Specify CTA — cover ---------------------------------------------- */
	$cta = $cover(
		$u( 'rico-astrowave-banner.webp' ),
		$h2( 'Ready to light up your next fit-out?', true )
		. $p( 'Design your Celeste light box online, or send us a drawing and we&rsquo;ll do it for you.' )
		. $buttons( $btnsolid( 'Build your own light box →', $builder ) . $btnline( 'Request a Lighting Design', $design, true ) ),
		48, 60
	);
	register_block_pattern( 'ricoman/celeste-cta', array(
		'title' => 'Celeste · Specify CTA (image overlay)', 'categories' => $cats,
		'content' => $cta,
	) );

	/* Whole page (all sections, still editable) — order suited to a product. */
	register_block_pattern( 'ricoman/celeste-all', array(
		'title' => 'Celeste · Whole page (all sections)', 'categories' => $cats,
		'description' => 'Every Celeste section in order — drop in once, then edit/rearrange and swap the placeholder images.',
		'content' => $intro . $shapes . $panel1 . $colour . $panel2 . $build . $benefits . $spec . $faq . $cta,
	) );
}, 14 );
