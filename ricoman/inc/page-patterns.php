<?php
/**
 * Page sections — NATIVE editable patterns (Home, and reusable elsewhere).
 *
 * Recreates the beautiful preview pages as small add/remove, click-to-edit
 * blocks under ＋ → Patterns → "Ricoman — Page". Built from core blocks so every
 * image and line of text is editable. ricoman_home_blocks() stacks them into the
 * homepage; each is reusable on any page.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {

	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'ricoman-page', array( 'label' => __( 'Ricoman — Page', 'ricoman' ) ) );
	}

	$u       = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
	$sec     = function ( $inner, $cls = '' ) { return '<!-- wp:group {"align":"full","className":"rm-section ' . $cls . '","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section ' . $cls . '">' . $inner . '</div><!-- /wp:group -->'; };
	$eyebrow = function ( $t ) { return '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $t . '</p><!-- /wp:paragraph -->'; };
	$shead   = function ( $t ) { return '<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">' . $t . '</h2><!-- /wp:heading -->'; };
	$para    = function ( $t, $muted = false ) { return '<!-- wp:paragraph' . ( $muted ? ' {"textColor":"muted"}' : '' ) . ' --><p' . ( $muted ? ' class="has-muted-color has-text-color"' : '' ) . '>' . $t . '</p><!-- /wp:paragraph -->'; };
	$image   = function ( $url, $href = '', $alt = '' ) { $i = '<img src="' . $url . '" alt="' . esc_attr( $alt ) . '"/>'; if ( $href ) { return '<!-- wp:image {"linkDestination":"custom","sizeSlug":"large"} --><figure class="wp-block-image size-large"><a href="' . $href . '">' . $i . '</a></figure><!-- /wp:image -->'; } return '<!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large">' . $i . '</figure><!-- /wp:image -->'; };
	$btn     = function ( $label, $href = '#', $light = true ) { return '<!-- wp:button {"className":"is-style-outline' . ( $light ? '-light' : '' ) . '"} --><div class="wp-block-button is-style-outline' . ( $light ? '-light' : '' ) . '"><a class="wp-block-button__link wp-element-button" href="' . $href . '">' . $label . '</a></div><!-- /wp:button -->'; };
	$buttons = function ( $inner, $center = false ) { return '<!-- wp:buttons' . ( $center ? ' {"layout":{"type":"flex","justifyContent":"center"}}' : '' ) . ' --><div class="wp-block-buttons' . ( $center ? ' is-content-justification-center' : '' ) . '">' . $inner . '</div><!-- /wp:buttons -->'; };
	$cover   = function ( $url, $inner, $min, $pos, $dim ) {
		$poscls = 'center center' === $pos ? '' : ' has-custom-content-position is-position-' . str_replace( ' ', '-', $pos );
		return '<!-- wp:cover {"url":"' . $url . '","dimRatio":' . $dim . ',"overlayColor":"ink","minHeight":' . $min . ',"minHeightUnit":"vh","contentPosition":"' . $pos . '","align":"full","textColor":"base"} --><div class="wp-block-cover alignfull has-base-color has-text-color' . $poscls . '" style="min-height:' . $min . 'vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-' . $dim . ' has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">' . $inner . '</div></div><!-- /wp:cover -->';
	};
	// Brand videos. The hero banner is the compressed, theme-hosted copy
	// (1.9MB vs 12.6MB) so it's same-origin + cacheable; deferred + postered by
	// the hero-video filter below. Editable in the block (swap the URL).
	$vid_banner = get_theme_file_uri( 'assets/videos/hero-banner.mp4' );
	$vid_story  = 'https://ricoman.com/back-end/wp-content/uploads/2025/09/RICOMAN-homepage-video-webfile.mp4';
	// Full-bleed cover with an autoplay (muted, looped) video background.
	$vcover = function ( $video, $inner, $min, $pos, $dim ) {
		$poscls = 'center center' === $pos ? '' : ' has-custom-content-position is-position-' . str_replace( ' ', '-', $pos );
		return '<!-- wp:cover {"url":"' . $video . '","backgroundType":"video","dimRatio":' . $dim . ',"overlayColor":"ink","minHeight":' . $min . ',"minHeightUnit":"vh","contentPosition":"' . $pos . '","align":"full","textColor":"base"} --><div class="wp-block-cover alignfull has-base-color has-text-color' . $poscls . '" style="min-height:' . $min . 'vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-' . $dim . ' has-background-dim"></span><video class="wp-block-cover__video-background intrinsic-ignore" autoplay muted loop playsinline src="' . $video . '" data-object-fit="cover"></video><div class="wp-block-cover__inner-container">' . $inner . '</div></div><!-- /wp:cover -->';
	};
	$rcard = function ( $url, $title, $sub, $href ) use ( $image ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-card">' . $image( $url, $href, wp_strip_all_tags( html_entity_decode( $title ) ) . ' — Ricoman commercial LED lighting' ) . '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><a href="' . $href . '">' . $title . '</a></h3><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted","fontSize":"small"} --><p class="has-muted-color has-text-color has-small-font-size">' . $sub . '</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->'; };
	$ptile = function ( $url, $eyb, $title, $href ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:cover {"url":"' . $url . '","dimRatio":40,"overlayColor":"ink","minHeight":320,"contentPosition":"bottom left","isLink":true,"href":"' . $href . '"} --><div class="wp-block-cover has-custom-content-position is-position-bottom-left" style="min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"className":"rm-eyebrow","textColor":"base"} --><p class="rm-eyebrow has-base-color has-text-color">' . $eyb . '</p><!-- /wp:paragraph --><!-- wp:heading {"level":3,"textColor":"base"} --><h3 class="wp-block-heading has-base-color has-text-color">' . $title . '</h3><!-- /wp:heading --></div></div><!-- /wp:cover --></div><!-- /wp:column -->'; };
	$aud   = function ( $n, $h, $p ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $n . '</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . $h . '</h3><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color">' . $p . '</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->'; };
	$stat  = function ( $n, $l ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">' . $l . '</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-fval"} --><p class="rm-fval">' . $n . '</p><!-- /wp:paragraph --></div><!-- /wp:column -->'; };

	$p = array();

	$hero_inner = $eyebrow( 'Commercial Interior Lighting · Made in Britain' ) .
		'<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"clamp(2.8rem, 7vw, 6rem)","fontWeight":"500","lineHeight":"0.98"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.8rem, 7vw, 6rem);font-weight:500;line-height:0.98">Light that transforms how a space feels.</h1><!-- /wp:heading -->' .
		$para( 'We&rsquo;re a British manufacturer obsessed with getting light right — designing and making commercial luminaires in Manchester for the architects, designers and specifiers who shape great spaces. On spec, on time, on budget.' ) .
		$buttons( $btn( 'Explore Products', '/products/' ) . $btn( 'Free Scheme Design', '/lighting-design/' ) );
	// Image hero kept as an alternative pattern; video hero is the default.
	$p['home-hero'] = array( 'Home · Hero (video)', $vcover( $vid_banner, $hero_inner, 82, 'bottom left', 60 ) );
	$p['home-hero-image'] = array( 'Home · Hero (image)', $cover( $u( 'warm-int.webp' ), $hero_inner, 82, 'bottom left', 50 ) );
	$p['home-film'] = array( 'Home · Brand film', $sec( $eyebrow( 'Watch · Made in Britain' ) . $shead( 'See how we make light' ) . '<!-- wp:video {"className":"rm-filmvid"} --><figure class="wp-block-video rm-filmvid"><video controls playsinline poster="' . $u( 'workshop.webp' ) . '" src="' . $vid_story . '"></video></figure><!-- /wp:video -->' ) );

	$p['home-facts'] = array( 'Home · Facts band', $sec( '<!-- wp:columns --><div class="wp-block-columns">' . $stat( 'UK-made, ~6-day average', 'Lead Time' ) . $stat( '535+ schemes designed', 'Last Year' ) . $stat( '2,000+ components', 'In Stock' ) . $stat( 'Strong local network', 'Partners' ) . '</div><!-- /wp:columns -->', 'rm-facts' ) );

	$p['home-statement'] = array( 'Home · Statement', $sec( '<!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.9rem,5vw,4rem)","lineHeight":"1.08"}}} --><h2 class="wp-block-heading" style="font-size:clamp(1.9rem,5vw,4rem);font-weight:500;line-height:1.08">We believe great light is felt, not noticed. So we design and make commercial lighting in Britain that lets architects and designers shape how a space looks, feels and performs — delivered on spec, on time, on budget.</h2><!-- /wp:heading -->' ) );

	$p['home-range'] = array( 'Home · Range grid', $sec( $shead( 'A luminaire for every commercial interior' ) .
		'<!-- wp:shortcode -->[ricoman_category_cards limit="8"]<!-- /wp:shortcode -->' ) );

	$p['home-featured'] = array( 'Home · Featured (dark)', '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)"><!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $eyebrow( 'Featured · Linear' ) . $shead( 'Flow+ — seamless curves of light' ) . $para( 'A flexible linear system that bends to any architectural line, continuous and dot-free. Made to order in Manchester, to your exact geometry.' ) . $buttons( $btn( 'View Flow+', '/products/flow-plus/' ) . $btn( 'Design your run', '/flow-designer/' ) ) . '</div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $image( $u( 'arch-line.webp' ), '', 'Flow+ continuous architectural linear lighting' ) . '</div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->' );

	$p['home-projects'] = array( 'Home · Projects grid', $sec( $shead( 'Lighting that performs in the real world' ) .
		'<!-- wp:columns --><div class="wp-block-columns">' . $ptile( $u( 'office1.webp' ), 'Commercial Office · Leeds', 'Allianz HQ Fit-out', '/projects/allianz-hq/' ) . $ptile( $u( 'retail.webp' ), 'Retail · Manchester', 'Flagship Store', '/projects/flagship-store/' ) . $ptile( $u( 'office6.webp' ), 'Workplace', 'Studio HQ', '/projects/studio-hq/' ) . $ptile( $u( 'office2.webp' ), 'Hospitality', 'Boutique Hotel', '/projects/boutique-hotel/' ) . '</div><!-- /wp:columns -->' .
		$buttons( $btn( 'All projects', '/projects/', false ) ) ) );

	$p['home-britain'] = array( 'Home · Made in Britain (dark)', '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)"><!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $eyebrow( 'Made in Britain' ) . $shead( 'Designed &amp; manufactured in Manchester' ) . $para( 'We design, assemble, test and finish our luminaires in our own UK facility — controlling quality, lead times and every bespoke detail. With 2,000+ components stocked, we make to order on an average six-day lead.' ) . $buttons( $btn( 'Inside our manufacturing →', '/manufacturing/' ) ) . '</div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $image( $u( 'workshop.webp' ), '', 'Ricoman LED luminaires being manufactured in Manchester' ) . '</div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->' );

	$p['home-audience'] = array( 'Home · Audience', $sec( $shead( 'A partner for the whole project team' ) .
		'<!-- wp:columns --><div class="wp-block-columns">' . $aud( '01', 'Architects', 'Clean photometrics, full data sheets and BIM-ready files for precise specification.' ) . $aud( '02', 'Interior Designers', 'Warm, high-CRI light and decorative ranges that flatter materials and finishes.' ) . $aud( '03', 'Design &amp; Build', 'Value-engineered alternatives, budget certainty and stock to keep programmes moving.' ) . $aud( '04', 'Contractors', 'Fast quotes, reliable lead times and easy-install fittings that wire up first time.' ) . '</div><!-- /wp:columns -->' ) );

	$p['home-cta'] = array( 'Home · CTA', $cover( $u( 'office1.webp' ),
		'<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Have a project on the board?</h2><!-- /wp:heading -->' .
		'<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Send us your drawings and our in-house lighting designers will return a fully specified, costed scheme — usually within 3–5 days.</p><!-- /wp:paragraph -->' .
		$buttons( $btn( 'Start a Project', '/lighting-design/' ) . $btn( 'Talk to the team', '/about/', false ), true ), 60, 'center center', 70 ) );

	// ---- shared helpers for content pages ----
	$bignum   = function ( $n, $l ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3,"className":"rm-statnum"} --><h3 class="wp-block-heading rm-statnum">' . $n . '</h3><!-- /wp:heading --><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">' . $l . '</p><!-- /wp:paragraph --></div><!-- /wp:column -->'; };
	$darkgroup = function ( $inner ) { return '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)">' . $inner . '</div><!-- /wp:group -->'; };
	$twocol   = function ( $a, $b ) { return '<!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $a . '</div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $b . '</div><!-- /wp:column --></div><!-- /wp:columns -->'; };
	$checklist = function ( $items ) { $li = ''; foreach ( $items as $t ) { $li .= '<!-- wp:list-item --><li>' . $t . '</li><!-- /wp:list-item -->'; } return '<!-- wp:list {"className":"rm-flist"} --><ul class="wp-block-list rm-flist">' . $li . '</ul><!-- /wp:list -->'; };

	/* -------------------------------------------------------------------------
	 * Page header treatments — varied by purpose so pages don't all open with
	 * the same full-bleed photo banner + identical heading. Marketing pages get
	 * a medium hero; listing / utility pages get a compact header so the real
	 * content (grid, form, resources) sits near the top of the first screen.
	 * ---------------------------------------------------------------------- */

	// Editorial — light, NO photo: a confident statement heading + lead + rule.
	$hdr_editorial = function ( $eyb, $title, $lead, $btns = '' ) {
		return '<!-- wp:group {"align":"full","className":"rm-section rm-hdr rm-hdr-editorial","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section rm-hdr rm-hdr-editorial">'
			. '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $eyb . '</p><!-- /wp:paragraph -->'
			. '<!-- wp:heading {"level":1,"className":"rm-hdr-title","style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.4rem,5vw,4.2rem)","lineHeight":"1.03"}}} --><h1 class="wp-block-heading rm-hdr-title" style="font-size:clamp(2.4rem,5vw,4.2rem);font-weight:500;line-height:1.03">' . $title . '</h1><!-- /wp:heading -->'
			. ( $lead ? '<!-- wp:paragraph {"className":"rm-hdr-lead","style":{"typography":{"fontSize":"clamp(1.05rem,1.5vw,1.3rem)"}}} --><p class="rm-hdr-lead" style="font-size:clamp(1.05rem,1.5vw,1.3rem)">' . $lead . '</p><!-- /wp:paragraph -->' : '' )
			. $btns
			. '</div><!-- /wp:group -->';
	};

	// Compact band — light, NO photo, short: content sits right beneath it.
	$hdr_band = function ( $eyb, $title, $lead ) {
		return '<!-- wp:group {"align":"full","className":"rm-section rm-hdr rm-hdr-band","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section rm-hdr rm-hdr-band">'
			. '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $eyb . '</p><!-- /wp:paragraph -->'
			. '<!-- wp:heading {"level":1,"className":"rm-hdr-title","style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.9rem,3.6vw,2.9rem)","lineHeight":"1.06"}}} --><h1 class="wp-block-heading rm-hdr-title" style="font-size:clamp(1.9rem,3.6vw,2.9rem);font-weight:500;line-height:1.06">' . $title . '</h1><!-- /wp:heading -->'
			. ( $lead ? '<!-- wp:paragraph {"className":"rm-hdr-lead","textColor":"muted"} --><p class="rm-hdr-lead has-muted-color has-text-color">' . $lead . '</p><!-- /wp:paragraph -->' : '' )
			. '</div><!-- /wp:group -->';
	};

	// Split — text left / image right (no full-bleed); image earns its place.
	$hdr_split = function ( $eyb, $title, $lead, $img, $btns = '' ) use ( $image ) {
		$left = '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $eyb . '</p><!-- /wp:paragraph -->'
			. '<!-- wp:heading {"level":1,"className":"rm-hdr-title","style":{"typography":{"fontWeight":"500","fontSize":"clamp(2rem,4vw,3.4rem)","lineHeight":"1.04"}}} --><h1 class="wp-block-heading rm-hdr-title" style="font-size:clamp(2rem,4vw,3.4rem);font-weight:500;line-height:1.04">' . $title . '</h1><!-- /wp:heading -->'
			. ( $lead ? '<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color">' . $lead . '</p><!-- /wp:paragraph -->' : '' )
			. $btns;
		return '<!-- wp:group {"align":"full","className":"rm-section rm-hdr rm-hdr-split","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section rm-hdr rm-hdr-split">'
			. '<!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"52%"} --><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:52%">' . $left . '</div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center","width":"48%"} --><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:48%">' . $image( $img, '', wp_strip_all_tags( html_entity_decode( $title ) ) ) . '</div><!-- /wp:column --></div><!-- /wp:columns -->'
			. '</div><!-- /wp:group -->';
	};

	// Dark band — bold solid ink, no photo (still compact, no full-screen image).
	$hdr_dark = function ( $eyb, $title, $lead, $btns = '' ) {
		$pad = 'padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)';
		return '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark rm-hdr rm-hdr-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-dark rm-hdr rm-hdr-dark has-base-color has-ink-background-color has-text-color has-background" style="' . $pad . '">'
			. '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $eyb . '</p><!-- /wp:paragraph -->'
			. '<!-- wp:heading {"level":1,"className":"rm-hdr-title","style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.2rem,5vw,3.8rem)","lineHeight":"1.02"}}} --><h1 class="wp-block-heading rm-hdr-title" style="font-size:clamp(2.2rem,5vw,3.8rem);font-weight:500;line-height:1.02">' . $title . '</h1><!-- /wp:heading -->'
			. ( $lead ? '<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.15rem"}}} --><p style="font-size:1.15rem">' . $lead . '</p><!-- /wp:paragraph -->' : '' )
			. $btns
			. '</div><!-- /wp:group -->';
	};

	/* ===== About ===== */
	// About — editorial header (no photo): a confident, design-led statement.
	$p['about-hero'] = array( 'About · Hero', $hdr_editorial( 'About Ricoman', 'British lighting, made with intent.', 'A Manchester manufacturer of commercial interior LED lighting — designing, making and delivering schemes for the people who build great spaces.', $buttons( $btn( 'Inside our manufacturing', '/manufacturing/', false ) . $btn( 'See our work', '/projects/', false ) ) ) );
	$p['about-intro'] = array( 'About · Who we are', $sec( $twocol(
		$eyebrow( '01 · Who we are' ) . $shead( 'Lighting that works on spec, on time, on budget' ) .
		$para( 'Ricoman designs and manufactures commercial interior LED lighting from our own facility in Manchester. We supply architects, interior designers, design &amp; build teams and electrical contractors across the UK.', true ) .
		$para( 'Because we&rsquo;re the manufacturer — not a reseller — we control quality, lead times and bespoke detail in-house. That lets us offer free scheme design, 2,000+ components stocked ready to build, an average six-day UK-made lead, strong local partnerships and the confidence of a 5-year warranty.', true ) .
		$buttons( $btn( 'Inside our manufacturing →', '/manufacturing/', false ) ),
		$image( $u( 'rico-office.webp' ), '', 'Inside Ricoman&rsquo;s Manchester facility' )
	) ) );
	$p['about-capabilities'] = array( 'About · What we do', $sec(
		$eyebrow( '02 · What we do' ) . $shead( 'Design. Manufacture. Deliver.' ) .
		'<!-- wp:columns --><div class="wp-block-columns">'
		. $aud( '01', 'In-house lighting design', 'Send us drawings or a finishes schedule — our designers return a fully specified, photometric scheme, usually within 3–5 days. Free of charge.' )
		. $aud( '02', 'UK manufacturing', 'Designed, built, finished and tested in our own Manchester facility — full control of quality, bespoke detail and lead times, with UK stock ready to build.' )
		. $aud( '03', 'Delivered &amp; supported', 'On spec, on time, on budget — backed by a 5-year warranty, serviceable fittings and a team that actually picks up the phone.' )
		. '</div><!-- /wp:columns -->'
		. $buttons( $btn( 'Request a free lighting design →', '/lighting-design/', false ) )
	) );
	$p['about-stats'] = array( 'About · Stats', $sec( '<!-- wp:columns --><div class="wp-block-columns">' . $bignum( '1999', 'Our journey began' ) . $bignum( '535', 'Lighting design projects in 2025' ) . $bignum( '2,000+', 'Components stocked, ready to build' ) . $bignum( '5 yr', 'Standard warranty' ) . '</div><!-- /wp:columns -->', 'rm-statband' ) );
	$p['about-values'] = array( 'About · Values', $sec( $eyebrow( '03 · What we stand for' ) . $shead( 'The way we like to work' ) . '<!-- wp:columns --><div class="wp-block-columns">' . $aud( '↳ 01', 'Made in Britain', 'Designed, built, finished and tested in Manchester — full control, full traceability, shorter lead times.' ) . $aud( '↳ 02', 'Specifier-first', 'Free scheme design, clean photometrics and honest lead times. We make the spec easy to stand behind.' ) . $aud( '↳ 03', 'Built to last', 'Serviceable, high-CRI fittings backed by a 5-year warranty — good for the building and the planet.' ) . '</div><!-- /wp:columns -->' ) );
	$contact_left  = $eyebrow( '04 · Get in touch' ) . $shead( 'Talk to the team' ) . $para( 'Quotes, lead times, a tricky detail or a full scheme — our Manchester team will get you a real answer, fast.', true ) . '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Visit / Post</h4><!-- /wp:heading -->' . $para( 'Metroplex Business Park<br>520 Broadway, M50 2UE<br>Manchester, United Kingdom' ) . '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Call</h4><!-- /wp:heading -->' . $para( '<a href="tel:01614515913">0161 451 5913</a>' ) . '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Email</h4><!-- /wp:heading -->' . $para( '<a href="mailto:sales@ricoman.com">sales@ricoman.com</a>' ) . '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Hours</h4><!-- /wp:heading -->' . $para( 'Mon&ndash;Thu 8:30&ndash;17:00 &middot; Fri 8:30&ndash;16:00' );
	$contact_form  = '<!-- wp:group {"className":"rm-soft","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group rm-soft" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Send an enquiry</p><!-- /wp:paragraph --><!-- wp:shortcode -->[ricoman_lead_form]<!-- /wp:shortcode --></div><!-- /wp:group -->';
	$p['about-contact'] = array( 'About · Contact &amp; enquiry', $sec( $twocol( $contact_left, $contact_form ) ) );

	/* ===== Manufacturing ===== */
	// Manufacturing — split header: heading left, factory photo right (not full-bleed).
	$p['mfg-hero'] = array( 'Manufacturing · Hero', $hdr_split( 'Made in Britain', 'Designed &amp; manufactured in Manchester.', 'We design, assemble, test and finish our luminaires in our own UK facility — controlling quality, lead times and every bespoke detail in-house.', $u( 'workshop.webp' ), $buttons( $btn( 'How we make it', '#mfg-process', false ) ) ) );
	$p['mfg-stats'] = array( 'Manufacturing · Stats', $sec( '<!-- wp:columns --><div class="wp-block-columns">' . $bignum( '15,000ft²', 'Production area, Manchester' ) . $bignum( '2,000+', 'Components ready to build' ) . $bignum( '~6 days', 'Average UK-made lead' ) . $bignum( '98%', 'On-time-in-full target' ) . '</div><!-- /wp:columns -->', 'rm-statband' ) );
	$p['mfg-statement'] = array( 'Manufacturing · Statement', $sec( '<!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.9rem,5vw,4rem)","lineHeight":"1.08"}}} --><h2 class="wp-block-heading" style="font-size:clamp(1.9rem,5vw,4rem);font-weight:500;line-height:1.08">Vertical integration, end to end — we make to order, not to a catalogue.</h2><!-- /wp:heading -->' ) );
	$p['mfg-split'] = array( 'Manufacturing · One roof', $sec( $twocol( $image( $u( 'rico-making.webp' ), '', 'In-house LED lighting assembly and finishing in the UK' ), $eyebrow( 'In-house, end to end' ) . $shead( 'One roof, full control' ) . $para( 'Design, electronics, assembly, finishing and testing all happen under one roof in Manchester — nothing outsourced to a supply chain we can&rsquo;t see. Shorter lead times, full traceability, and the ability to make a fitting to your exact geometry.', true ) ) ) );
	$p['mfg-steps'] = array( 'Manufacturing · Process', $sec( '<!-- wp:heading {"level":2,"className":"rm-shead","anchor":"mfg-process"} --><h2 class="wp-block-heading rm-shead" id="mfg-process">How a Ricoman fitting is made</h2><!-- /wp:heading -->' . '<!-- wp:columns --><div class="wp-block-columns">' . $aud( '01', 'Design &amp; tooling', 'CAD, photometric modelling and tooling, in-house.' ) . $aud( '02', 'Assembly', 'Boards, optics and housings hand-built to order.' ) . $aud( '03', 'Finishing', 'Powder-coat &amp; bespoke finishes to your spec.' ) . $aud( '04', 'Test &amp; despatch', 'Batch burn-in, then delivered UK-wide.' ) . '</div><!-- /wp:columns -->' ) );
	$p['mfg-bespoke'] = array( 'Manufacturing · Bespoke (dark)', $darkgroup( $twocol( $eyebrow( 'Bespoke as standard' ) . $shead( 'If you can draw it, we can make it' ) . $para( 'Curved linear runs, custom lengths, special CCTs, brand-matched finishes — bespoke isn&rsquo;t a bolt-on, it&rsquo;s how the factory is built to work.' ) . $buttons( $btn( 'Talk to our designers →', '/lighting-design/' ) ), $image( $u( 'warehouse.webp' ), '', 'Ricoman component stock for made-to-order UK lighting' ) ) ) );
	$p['mfg-cta'] = array( 'Manufacturing · CTA', $cover( $u( 'workshop.webp' ), '<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Want to see it for yourself?</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Book a visit to the Manchester facility, or send us a project and let our team spec it end to end.</p><!-- /wp:paragraph -->' . $buttons( $btn( 'Book a visit', '/about/' ) . $btn( 'Start a project →', '/lighting-design/', false ), true ), 52, 'center center', 70 ) );

	/* ===== Lighting Design ===== */
	// Lighting Design — the one flagship photo hero (reduced height so content peeks).
	$p['lighting-hero'] = array( 'Lighting · Hero', $cover( $u( 'rico-office-render.webp' ), $eyebrow( 'Free Scheme Design' ) . '<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.4rem,5.5vw,4.4rem)","lineHeight":"1.02"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.4rem,5.5vw,4.4rem);font-weight:500;line-height:1.02">Your scheme, fully designed — at no cost.</h1><!-- /wp:heading -->' . $para( 'Send us a drawing or a finishes schedule and our in-house lighting designers return a fully specified, photometric-backed and costed scheme. Usually within 3–5 days.' ) . $buttons( $btn( 'Start your scheme', '/contact/' ) . $btn( 'How it works ↓', '#lighting-process' ) ), 54, 'bottom left', 55 ) );
	$p['lighting-statement'] = array( 'Lighting · Statement', $sec( '<!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.9rem,5vw,4rem)","lineHeight":"1.08"}}} --><h2 class="wp-block-heading" style="font-size:clamp(1.9rem,5vw,4rem);font-weight:500;line-height:1.08">We don&rsquo;t just sell luminaires — we design the light, prove it works, and cost it before you commit a penny.</h2><!-- /wp:heading -->' ) );
	$p['lighting-steps'] = array( 'Lighting · Process', $sec( '<!-- wp:heading {"level":2,"className":"rm-shead","anchor":"lighting-process"} --><h2 class="wp-block-heading rm-shead" id="lighting-process">Four steps from drawing to delivered scheme</h2><!-- /wp:heading -->' . '<!-- wp:columns --><div class="wp-block-columns">' . $aud( '01', 'Send your drawings', 'A plan, RCP or sketch and a finishes schedule.' ) . $aud( '02', 'We design the light', 'A DIALux photometric study — lux, uniformity, UGR.' ) . $aud( '03', 'Costed scheme in 3–5 days', 'A specified luminaire schedule, layout and costing.' ) . $aud( '04', 'Made &amp; delivered', 'Approved, made to order in Manchester, to programme.' ) . '</div><!-- /wp:columns -->' ) );
	$p['lighting-receive'] = array( 'Lighting · What you receive (dark)', $darkgroup( $twocol( $eyebrow( 'What you receive' ) . $shead( 'A scheme you can specify with confidence' ) . $checklist( array( '<strong>DIALux photometric study</strong> — lux, uniformity &amp; UGR', '<strong>Luminaire schedule</strong> — every fitting, finish &amp; quantity', '<strong>Reflected ceiling layout</strong> — positions for the contractor', '<strong>Itemised costing</strong> — with value-engineered options', '<strong>Data sheets &amp; BIM files</strong> — ready for your spec pack' ) ), $image( $u( 'office5.webp' ), '', 'Low-glare workplace LED lighting scheme' ) ) ) );
	$p['lighting-cta'] = array( 'Lighting · CTA', $cover( $u( 'office1.webp' ), '<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Start your scheme</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Tell us about the project and share your drawings — a lighting designer will be in touch, costed scheme to follow.</p><!-- /wp:paragraph -->' . $buttons( $btn( 'Talk to the team', '/about/' ) . $btn( 'See the results →', '/projects/', false ), true ), 52, 'center center', 70 ) );

	/* ===== Downloads ===== */
	$dlcard = function ( $title, $desc, $href ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-card rm-soft","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group rm-card rm-soft" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40)"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><a href="' . $href . '">' . $title . ' &darr;</a></h3><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted","fontSize":"small"} --><p class="has-muted-color has-text-color has-small-font-size">' . $desc . '</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->'; };
	// Downloads — compact band (utility page): resources show immediately below.
	$p['downloads-hero'] = array( 'Downloads · Hero', $hdr_band( 'Downloads &amp; Resources', 'Catalogues, datasheets &amp; BIM.', 'Everything you need to specify Ricoman — product catalogue, technical datasheets, photometric (IES/LDT) files, BIM objects and installation guides.' ) );
	$p['downloads-grid'] = array( 'Downloads · Resource grid', $sec( $eyebrow( 'Resource library' ) . $shead( 'Download what you need' ) . '<!-- wp:columns --><div class="wp-block-columns">' . $dlcard( 'Product catalogue', 'The full Ricoman range in one PDF.', '/downloads/' ) . $dlcard( 'Datasheets', 'Per-product technical datasheets &amp; specs.', '/downloads/' ) . $dlcard( 'Photometric files', 'IES / LDT files for DIALux &amp; Relux.', '/downloads/' ) . '</div><!-- /wp:columns --><!-- wp:columns --><div class="wp-block-columns">' . $dlcard( 'BIM objects', 'Revit families for your model.', '/downloads/' ) . $dlcard( 'Installation guides', 'Step-by-step fitting instructions.', '/downloads/' ) . $dlcard( 'Certificates', 'Warranty, CE/UKCA &amp; compliance.', '/downloads/' ) . '</div><!-- /wp:columns -->' ) );
	$p['downloads-cta'] = array( 'Downloads · CTA', $cover( $u( 'rico-proline-breakout.webp' ), '<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Can&rsquo;t find a document?</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Tell us the product or project and our team will send the exact files you need.</p><!-- /wp:paragraph -->' . $buttons( $btn( 'Request files', '/contact/' ) . $btn( 'Talk to the team →', '/about/', false ), true ), 50, 'center center', 70 ) );

	/* ===== Customisation ===== */
	// Customisation — dark band header: bold, confident, no full-screen photo.
	$p['custom-hero'] = array( 'Customisation · Hero', $hdr_dark( 'Customisation &amp; Bespoke', 'If you can draw it, we can make it.', 'Curved runs, custom lengths, special CCTs and brand-matched finishes — bespoke is how our Manchester factory is built to work.', $buttons( $btn( 'Open Flow+ Designer', '/flow-designer/' ) . $btn( 'Talk to our designers', '/contact/' ) ) ) );
	$p['custom-intro'] = array( 'Customisation · Intro', $sec( $twocol( $eyebrow( 'Made to your spec' ) . $shead( 'Bespoke as standard' ) . $para( 'Tell us the geometry, the look and the performance you need. Our in-house design and manufacturing teams turn it into a buildable, costed luminaire — made to order on an average six-day UK lead.', true ) . $buttons( $btn( 'Design a Flow+ run →', '/flow-designer/' ) . $btn( 'Talk to our designers', '/contact/', false ) ), $image( $u( 'rico-betfred-flow.webp' ), '', 'Bespoke Flow+ curved linear lighting at Betfred HQ' ) ) ) );
	$p['custom-cta'] = array( 'Customisation · CTA', $cover( $u( 'rico-soundslikelight.webp' ), '<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Start a bespoke project</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Use the Flow+ Designer to draw your run, or send us a sketch and we&rsquo;ll spec it end to end.</p><!-- /wp:paragraph -->' . $buttons( $btn( 'Open Flow+ Designer', '/flow-designer/' ) . $btn( 'Talk to the team →', '/contact/', false ), true ), 52, 'center center', 70 ) );

	/* ===== Contact ===== */
	// Contact — compact band (utility page): the enquiry form is the priority.
	$p['contact-hero'] = array( 'Contact · Hero', $hdr_band( 'Contact', 'Let&rsquo;s talk lighting.', 'Send us a project, a drawing or a question — our Manchester team will get straight back to you.' ) );
	$p['contact-body'] = array( 'Contact · Form &amp; details', $sec( '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column {"width":"58%"} --><div class="wp-block-column" style="flex-basis:58%">' . $eyebrow( 'Send an enquiry' ) . $shead( 'Tell us about your project' ) . '<!-- wp:shortcode -->[ricoman_lead_form]<!-- /wp:shortcode --></div><!-- /wp:column --><!-- wp:column {"width":"42%"} --><div class="wp-block-column" style="flex-basis:42%"><!-- wp:group {"className":"rm-soft","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group rm-soft" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40)"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Get in touch</h3><!-- /wp:heading -->' . $para( 'Metroplex Business Park<br>520 Broadway, M50 2UE<br>Manchester, UK' ) . $para( '<strong>0161 451 5913</strong><br>sales@ricoman.com' ) . $para( 'Mon–Thu 8:30–17:00 · Fri 8:30–16:00' ) . '</div><!-- /wp:group --></div><!-- /wp:column --></div><!-- /wp:columns -->' ) );

	/* ===== Feature / enhanced landing pages =====
	 * Rich, editable landing pages for the marketing features (Casambi, Human
	 * Centric, Antimicrobial, Fire Safety…). One comprehensive pattern each:
	 * split header, benefits, an image split + checklist, an FAQ (which emits
	 * FAQPage schema via [ricoman_faq]) and a conversion CTA. All built from core
	 * blocks so every word + image stays click-to-edit.
	 */
	$faqblock = function ( $head, $qa ) use ( $shead ) {
		return '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section">'
			. $shead( $head )
			. '<!-- wp:shortcode -->[ricoman_faq]' . "\n" . $qa . "\n" . '[/ricoman_faq]<!-- /wp:shortcode -->'
			. '</div><!-- /wp:group -->';
	};
	$feature = function ( $a ) use ( $hdr_split, $sec, $shead, $eyebrow, $para, $aud, $twocol, $image, $checklist, $buttons, $btn, $cover, $faqblock ) {
		$out  = $hdr_split( $a['eyb'], $a['title'], $a['lead'], $a['hero'], $buttons( $btn( $a['cta1'][0], $a['cta1'][1], false ) . ( isset( $a['cta2'] ) ? $btn( $a['cta2'][0], $a['cta2'][1], false ) : '' ) ) );
		// Benefits row (3–4 cards).
		$cards = '';
		foreach ( $a['benefits'] as $b ) {
			$cards .= $aud( $b[0], $b[1], $b[2] );
		}
		$out .= $sec( $eyebrow( $a['ben_eyb'] ) . $shead( $a['ben_head'] ) . '<!-- wp:columns --><div class="wp-block-columns">' . $cards . '</div><!-- /wp:columns -->' );
		// Image split + checklist.
		$out .= $sec( $twocol(
			$image( $a['split_img'], '', wp_strip_all_tags( html_entity_decode( $a['title'] ) ) ),
			$eyebrow( $a['split_eyb'] ) . $shead( $a['split_head'] ) . $para( $a['split_body'], true ) . $checklist( $a['split_items'] )
		) );
		// FAQ (schema) + CTA.
		$out .= $faqblock( $a['faq_head'], $a['faqs'] );
		$out .= $cover( $a['cta_img'],
			'<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">' . $a['cta_head'] . '</h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">' . $a['cta_sub'] . '</p><!-- /wp:paragraph -->'
			. $buttons( $btn( $a['cta1'][0], $a['cta1'][1] ) . $btn( 'Talk to the team →', '/contact/', false ), true ),
			52, 'center center', 70 );
		return $out;
	};

	$p['feat-casambi'] = array( 'Feature · Casambi', $feature( array(
		'eyb'       => 'Wireless Lighting Control',
		'title'     => 'Casambi — wireless control, beautifully simple.',
		'lead'      => 'Dim, tune and group your lighting from a phone or wall switch — no control wiring, no gateway, no fuss. Many Ricoman luminaires are available Casambi-ready.',
		'hero'      => $u( 'office5.webp' ),
		'cta1'      => array( 'Request Casambi options', '/lighting-design/' ),
		'cta2'      => array( 'Browse products', '/products/' ),
		'ben_eyb'   => 'Why Casambi',
		'ben_head'  => 'Control that installs in minutes',
		'benefits'  => array(
			array( '01', 'No control wiring', 'Bluetooth mesh built into the fitting — no DALI bus, no extra cabling, lower install cost.' ),
			array( '02', 'Dim, tune &amp; scene', 'Set brightness, white tuning and scenes per room, then recall them from a phone or wall control.' ),
			array( '03', 'Scales with the space', 'From a single room to a whole floor — the mesh grows without a central controller.' ),
		),
		'split_img'  => $u( 'office6.webp' ),
		'split_eyb'  => 'How it works',
		'split_head' => 'A self-healing Bluetooth mesh',
		'split_body' => 'Each Casambi-enabled luminaire is a node in a secure wireless mesh. Commission with the free app, group fittings, and the network keeps working even if one node drops.',
		'split_items'=> array( '<strong>Casambi-ready luminaires</strong> across many ranges', '<strong>App or wall control</strong> — phones, switches &amp; sensors', '<strong>Tunable white &amp; scenes</strong> for workplaces &amp; hospitality', '<strong>Daylight &amp; occupancy</strong> sensors for energy savings', '<strong>No gateway required</strong> for standard installs' ),
		'faq_head'   => 'Casambi FAQs',
		'faqs'       => "Q: Do I need special wiring for Casambi?\nA: No — Casambi uses a Bluetooth mesh built into the luminaire, so there's no control bus or extra cabling for a standard install.\nQ: Which Ricoman products are Casambi-ready?\nA: Many ranges offer a Casambi option; tell us the fittings you're specifying and we'll confirm which are available Casambi-enabled.\nQ: Can I control it from a wall switch as well as a phone?\nA: Yes — Casambi supports wall controls, switches and sensors alongside the app.\nQ: How many fittings can one network handle?\nA: The mesh scales from a single room to large installations; for very large schemes our team will advise on the best topology.",
		'cta_img'    => $u( 'office1.webp' ),
		'cta_head'   => 'Specify Casambi on your next scheme',
		'cta_sub'    => 'Send us your drawings and we&rsquo;ll return a Casambi-ready luminaire schedule and a costed scheme — usually within 3–5 days.',
	) ) );

	$p['feat-human-centric'] = array( 'Feature · Human Centric Lighting', $feature( array(
		'eyb'       => 'Wellbeing &amp; Productivity',
		'title'     => 'Human Centric Lighting that works with people.',
		'lead'      => 'Tunable-white, circadian-aware lighting that supports how people feel, focus and rest — for workplaces, healthcare and education.',
		'hero'      => $u( 'office2.webp' ),
		'cta1'      => array( 'Design an HCL scheme', '/lighting-design/' ),
		'cta2'      => array( 'Browse products', '/products/' ),
		'ben_eyb'   => 'Why it matters',
		'ben_head'  => 'Light tuned to the rhythm of the day',
		'benefits'  => array(
			array( '01', 'Supports focus', 'Cooler, brighter light through the working day helps alertness and concentration.' ),
			array( '02', 'Aids comfort &amp; rest', 'Warmer tones in the evening reduce glare and support natural wind-down.' ),
			array( '03', 'Better spaces', 'High-CRI light renders materials and skin tones accurately, for spaces that simply feel right.' ),
		),
		'split_img'  => $u( 'office5.webp' ),
		'split_eyb'  => 'How we design it',
		'split_head' => 'Tunable white, specified properly',
		'split_body' => 'We model the scheme so light levels, colour temperature and control work together across the day — not just a tunable fitting bolted on, but a considered, compliant design.',
		'split_items'=> array( '<strong>Tunable-white luminaires</strong> (2700–6500K options)', '<strong>CRI 90+</strong> for accurate colour rendering', '<strong>Casambi / DALI control</strong> for automated scenes', '<strong>Daylight integration</strong> with sensors', '<strong>DIALux study</strong> proving lux, uniformity &amp; UGR' ),
		'faq_head'   => 'Human Centric Lighting FAQs',
		'faqs'       => "Q: What is human centric lighting?\nA: It's lighting designed around people — varying brightness and colour temperature through the day to support alertness, comfort and wellbeing.\nQ: Where does HCL make the biggest difference?\nA: Workplaces, healthcare, education and care environments, where people spend long periods indoors.\nQ: Do you provide a lighting design for HCL schemes?\nA: Yes — our in-house designers return a DIALux-backed, costed scheme, usually within 3–5 working days.\nQ: Can HCL be controlled automatically?\nA: Yes — with Casambi or DALI controls and sensors, colour temperature and levels can follow the day automatically.",
		'cta_img'    => $u( 'rico-office-render.webp' ),
		'cta_head'   => 'Bring human centric lighting to your project',
		'cta_sub'    => 'Share your drawings and our designers will specify a tunable-white scheme with the controls to match.',
	) ) );

	$p['feat-antimicrobial'] = array( 'Feature · Antimicrobial Protection', $feature( array(
		'eyb'       => 'Hygiene-Critical Spaces',
		'title'     => 'Antimicrobial lighting for cleaner environments.',
		'lead'      => 'Luminaires with an antimicrobial surface treatment that inhibits bacterial growth on the fitting — for healthcare, education and food environments.',
		'hero'      => $u( 'office6.webp' ),
		'cta1'      => array( 'Request antimicrobial options', '/lighting-design/' ),
		'cta2'      => array( 'Browse products', '/products/' ),
		'ben_eyb'   => 'Why specify it',
		'ben_head'  => 'Protection built into the surface',
		'benefits'  => array(
			array( '01', 'Inhibits bacteria', 'An antimicrobial additive in the surface finish suppresses bacterial growth on the luminaire.' ),
			array( '02', 'Built for cleaning', 'Sealed, IP-rated options stand up to regular wash-down and cleaning regimes.' ),
			array( '03', 'Compliance-friendly', 'A sensible choice for clinical, care and food-prep specifications.' ),
		),
		'split_img'  => $u( 'office2.webp' ),
		'split_eyb'  => 'Where it fits',
		'split_head' => 'Designed for hygiene-sensitive spaces',
		'split_body' => 'Antimicrobial protection pairs with sealed, easy-clean luminaires to support infection-control standards in the most demanding environments.',
		'split_items'=> array( '<strong>Antimicrobial surface treatment</strong> on selected fittings', '<strong>IP-rated, sealed options</strong> for wash-down areas', '<strong>Healthcare &amp; education</strong> ready specifications', '<strong>Emergency variants</strong> for life-safety compliance', '<strong>UK-made</strong>, backed by a 5-year warranty' ),
		'faq_head'   => 'Antimicrobial lighting FAQs',
		'faqs'       => "Q: How does antimicrobial lighting work?\nA: An antimicrobial additive in the luminaire's surface finish inhibits the growth of bacteria on the fitting itself.\nQ: Which environments is it suitable for?\nA: Healthcare, care homes, education, laboratories and food-preparation areas where hygiene is critical.\nQ: Is it available with IP-rated fittings?\nA: Yes — antimicrobial treatment can be combined with sealed, IP-rated luminaires for wash-down areas.\nQ: Can you help specify a compliant scheme?\nA: Yes — send us your requirements and our team will recommend suitable fittings and return a costed scheme.",
		'cta_img'    => $u( 'office1.webp' ),
		'cta_head'   => 'Specify antimicrobial lighting',
		'cta_sub'    => 'Tell us about the environment and our team will recommend the right protected, easy-clean fittings.',
	) ) );

	$p['feat-fire-safety'] = array( 'Feature · Fire Safety', $feature( array(
		'eyb'       => 'Fire-Rated &amp; Emergency',
		'title'     => 'Fire safety lighting you can specify with confidence.',
		'lead'      => 'Fire-rated downlights and emergency luminaires designed to protect escape routes and maintain ceiling integrity — British-made and held in UK stock.',
		'hero'      => $u( 'office5.webp' ),
		'cta1'      => array( 'Design a compliant scheme', '/lighting-design/' ),
		'cta2'      => array( 'Browse products', '/products/' ),
		'ben_eyb'   => 'Why it matters',
		'ben_head'  => 'Two jobs: contain fire, light the exit',
		'benefits'  => array(
			array( '01', 'Fire-rated fittings', 'Fire-rated downlights help maintain the fire integrity of a ceiling for up to 30, 60 or 90 minutes.' ),
			array( '02', 'Emergency lighting', 'Maintained and non-maintained luminaires and exit signage keep escape routes lit when the mains fails.' ),
			array( '03', 'Designed to standards', 'Schemes designed to support BS 5266 and building-regulation compliance.' ),
		),
		'split_img'  => $u( 'office6.webp' ),
		'split_eyb'  => 'What we offer',
		'split_head' => 'Fire-rated and emergency, in one place',
		'split_body' => 'Specify fire-rated downlights and emergency lighting from a single British manufacturer, with the design support to prove the scheme works.',
		'split_items'=> array( '<strong>Fire-rated downlights</strong> (30 / 60 / 90 minute)', '<strong>Maintained &amp; non-maintained</strong> emergency fittings', '<strong>Exit signage</strong> and emergency conversions', '<strong>3-hour duration</strong> emergency as standard', '<strong>BS 5266-aware</strong> design support' ),
		'faq_head'   => 'Fire safety lighting FAQs',
		'faqs'       => "Q: What does a fire-rated downlight do?\nA: It helps maintain the fire-resistance of a ceiling that's been penetrated to install the fitting, for a rated period such as 30, 60 or 90 minutes.\nQ: Do you supply emergency lighting?\nA: Yes — maintained and non-maintained luminaires, exit signage and emergency variants of standard fittings.\nQ: How long must emergency lighting stay on?\nA: A minimum three-hour duration is standard for most commercial applications.\nQ: Can you design a BS 5266-compliant scheme?\nA: Our team designs emergency schemes to support BS 5266; final compliance should always be confirmed by a competent design.",
		'cta_img'    => $u( 'office1.webp' ),
		'cta_head'   => 'Get a compliant fire safety scheme',
		'cta_sub'    => 'Send us your plans and our designers will specify fire-rated and emergency lighting to suit the building.',
	) ) );

	$p['feat-sustainability'] = array( 'Feature · Sustainability', $feature( array(
		'eyb'       => 'Responsibility',
		'title'     => 'Better light, made responsibly.',
		'lead'      => 'Lower energy in use, less waste in manufacture and products built to last — sustainability designed into how we make light.',
		'hero'      => $u( 'workshop.webp' ),
		'cta1'      => array( 'Talk to our designers', '/lighting-design/' ),
		'cta2'      => array( 'Inside our manufacturing', '/manufacturing/' ),
		'ben_eyb'   => 'Our approach',
		'ben_head'  => 'Designed to use less and last longer',
		'benefits'  => array(
			array( '01', 'Efficient in use', 'High-efficacy LEDs and good optics cut energy and running costs over the life of the scheme.' ),
			array( '02', 'Built to last', 'Serviceable, replaceable components extend product life and keep fittings out of landfill.' ),
			array( '03', 'Made in Britain', 'UK manufacturing and made-to-order production cut transport miles and overproduction.' ),
		),
		'split_img'  => $u( 'warehouse.webp' ),
		'split_eyb'  => 'Circular by design',
		'split_head' => 'Less waste, by design',
		'split_body' => 'We make to order rather than to a catalogue, design fittings to be serviced rather than scrapped, and manufacture in the UK to keep our footprint short.',
		'split_items'=> array( '<strong>High-efficacy LEDs</strong> for lower energy in use', '<strong>Replaceable drivers</strong> &amp; modular components', '<strong>Recyclable materials</strong> where possible', '<strong>Made to order</strong> — less overproduction', '<strong>UK manufacture</strong> — fewer transport miles' ),
		'faq_head'   => 'Sustainability FAQs',
		'faqs'       => "Q: How is Ricoman lighting more sustainable?\nA: We design for efficiency and longevity — high-efficacy LEDs, serviceable fittings and UK manufacturing that reduces transport and overproduction.\nQ: Are your fittings repairable?\nA: Many use replaceable drivers and modular components so they can be serviced rather than scrapped.\nQ: Does made-to-order reduce waste?\nA: Yes — building to order rather than to stock cuts overproduction and the waste that comes with it.\nQ: Where are your products made?\nA: In our own facility in Manchester, UK.",
		'cta_img'    => $u( 'office1.webp' ),
		'cta_head'   => 'Specify lighting that lasts',
		'cta_sub'    => 'Tell us about your project and we&rsquo;ll help you specify efficient, long-life lighting with the data to back it up.',
	) ) );

	$p['feat-made-in-britain'] = array( 'Feature · Made in Britain', $feature( array(
		'eyb'       => 'Provenance',
		'title'     => 'Made in Britain — lighting designed &amp; built here.',
		'lead'      => 'We design, assemble, finish and test our commercial luminaires in our own Manchester facility — British manufacturing you can specify with confidence.',
		'hero'      => $u( 'rico-mfg.webp' ),
		'cta1'      => array( 'Inside our manufacturing', '/manufacturing/' ),
		'cta2'      => array( 'Talk to the team', '/contact/' ),
		'ben_eyb'   => 'Why it matters',
		'ben_head'  => 'The advantages of buying British',
		'benefits'  => array(
			array( '01', 'Shorter lead times', 'UK production and 2,000+ stocked components mean an average six-day made-to-order lead — no long import waits.' ),
			array( '02', 'Quality &amp; traceability', 'Design, build, finish and test under one roof — full control and full traceability on every fitting.' ),
			array( '03', 'Support close to hand', 'Spares, service and a team you can actually reach, backed by a 5-year warranty.' ),
		),
		'split_img'  => $u( 'rico-making.webp' ),
		'split_eyb'  => 'Under one roof',
		'split_head' => 'Real manufacturing, not just assembly',
		'split_body' => 'From CAD and photometric modelling to powder-coat finishing and batch burn-in, the work happens in Manchester — so we can make a fitting to your exact geometry and stand behind it.',
		'split_items'=> array( '<strong>Designed &amp; engineered</strong> in the UK', '<strong>Assembled, finished &amp; tested</strong> in Manchester', '<strong>2,000+ components</strong> stocked, ready to build', '<strong>Bespoke as standard</strong> — custom sizes &amp; finishes', '<strong>5-year warranty</strong>, UK spares &amp; support' ),
		'faq_head'   => 'Made in Britain FAQs',
		'faqs'       => "Q: Are Ricoman products really made in the UK?\nA: Yes — we design, assemble, finish and test our luminaires in our own facility in Manchester.\nQ: Does UK manufacturing mean longer lead times?\nA: The opposite — with components stocked locally we make to order on an average six-day lead, without import delays.\nQ: Can you make bespoke fittings?\nA: Yes — custom lengths, curved runs, special colour temperatures and brand-matched finishes are part of how the factory works.\nQ: What warranty do you offer?\nA: A 5-year standard warranty, with spares and support based in the UK.",
		'cta_img'    => $u( 'workshop.webp' ),
		'cta_head'   => 'Specify British-made lighting',
		'cta_sub'    => 'Send us your project and our Manchester team will spec, make and deliver it — on spec, on time, on budget.',
	) ) );

	$p['feat-trade'] = array( 'Feature · Trade', $feature( array(
		'eyb'       => 'For the Trade',
		'title'     => 'A trade partner that keeps projects moving.',
		'lead'      => 'Contractors, wholesalers and design &amp; build teams get fast quotes, reliable UK stock, free scheme design and a team that picks up the phone.',
		'hero'      => $u( 'warehouse.webp' ),
		'cta1'      => array( 'Open a trade enquiry', '/contact/' ),
		'cta2'      => array( 'Browse products', '/products/' ),
		'ben_eyb'   => 'Why work with us',
		'ben_head'  => 'Built around how the trade works',
		'benefits'  => array(
			array( '01', 'Fast, clear quotes', 'Send us a schedule or a drawing and get a prompt, itemised quote — with value-engineered alternatives where they help.' ),
			array( '02', 'Stock that ships', 'Core ranges held in UK stock with 2,000+ components, so programmes don&rsquo;t stall waiting on imports.' ),
			array( '03', 'Free scheme design', 'Our in-house designers turn drawings into a costed, photometric scheme — usually within 3–5 days.' ),
		),
		'split_img'  => $u( 'courier.webp' ),
		'split_eyb'  => 'On site &amp; on programme',
		'split_head' => 'Fittings that wire up first time',
		'split_body' => 'Easy-install luminaires, honest lead times and real support — so the install goes smoothly and the snag list stays short.',
		'split_items'=> array( '<strong>Account &amp; quick re-ordering</strong> for registered users', '<strong>Datasheets, IES/LDT &amp; BIM</strong> ready to download', '<strong>Saved project lists</strong> &amp; one-click project packs', '<strong>Value-engineered options</strong> for tight budgets', '<strong>5-year warranty</strong> &amp; UK-based support' ),
		'faq_head'   => 'Trade FAQs',
		'faqs'       => "Q: Can I open a trade account?\nA: Yes — register on the site for quick re-ordering and saved project lists, or send us an enquiry and our team will set you up.\nQ: How fast can I get a quote?\nA: Send a schedule or drawing and we&rsquo;ll turn around an itemised quote promptly, with alternatives where they help the budget.\nQ: Do you hold stock?\nA: Core ranges are held in UK stock with 2,000+ components; made-to-order items run an average six-day lead.\nQ: Do you support contractors on site?\nA: Yes — easy-install fittings, full technical data and a UK team you can actually reach.",
		'cta_img'    => $u( 'office1.webp' ),
		'cta_head'   => 'Let&rsquo;s get your project moving',
		'cta_sub'    => 'Send us a schedule or a drawing and we&rsquo;ll come back with a quote, stock and lead times — fast.',
	) ) );

	$p['feat-where-to-buy'] = array( 'Feature · Where to Buy', $feature( array(
		'eyb'       => 'How to Buy',
		'title'     => 'How to buy Ricoman lighting.',
		'lead'      => 'Specify direct with our team or order through your wholesaler — either way you get UK stock, free scheme design and full technical support.',
		'hero'      => $u( 'rico-office.webp' ),
		'cta1'      => array( 'Contact our team', '/contact/' ),
		'cta2'      => array( 'Browse products', '/products/' ),
		'ben_eyb'   => 'Three ways to buy',
		'ben_head'  => 'Whatever suits your project',
		'benefits'  => array(
			array( '01', 'Direct from Ricoman', 'Specify and order direct with our Manchester team for the fullest design and technical support.' ),
			array( '02', 'Through a wholesaler', 'Already buy through an electrical wholesaler? We can supply your stockist with the fittings you specify.' ),
			array( '03', 'With design support', 'Not sure what you need? Send drawings and we&rsquo;ll return a costed, specified scheme first.' ),
		),
		'split_img'  => $u( 'courier.webp' ),
		'split_eyb'  => 'Delivered UK-wide',
		'split_head' => 'From Manchester to your project',
		'split_body' => 'Core ranges ship from UK stock and bespoke items are made to order on an average six-day lead — delivered across the UK and available for export.',
		'split_items'=> array( '<strong>UK stock</strong> on core ranges, ready to ship', '<strong>Made to order</strong> on an average six-day lead', '<strong>Free scheme design</strong> before you commit', '<strong>Export available</strong>, including UAE', '<strong>5-year warranty</strong> &amp; UK support' ),
		'faq_head'   => 'Where to buy FAQs',
		'faqs'       => "Q: Can I buy directly from Ricoman?\nA: Yes — specify and order direct with our Manchester team, who can also provide free scheme design and technical support.\nQ: Can I order through my wholesaler?\nA: Yes — if you buy through an electrical wholesaler we can supply your stockist with the fittings you specify.\nQ: Do you deliver across the UK?\nA: Yes — core ranges ship from UK stock and made-to-order items run an average six-day lead, delivered UK-wide.\nQ: Do you export?\nA: Yes — we supply international projects, including exports to the UAE.",
		'cta_img'    => $u( 'office1.webp' ),
		'cta_head'   => 'Ready to order or need a hand?',
		'cta_sub'    => 'Tell us about your project and we&rsquo;ll point you to the quickest route to buy — and spec it for you if you need.',
	) ) );

	foreach ( $p as $slug => $data ) {
		register_block_pattern( 'ricoman/' . $slug, array( 'title' => $data[0], 'categories' => array( 'ricoman-page' ), 'content' => $data[1] ) );
	}
}, 12 );

/** Editable block stacks for the content pages. */
function ricoman_about_blocks() {
	return ricoman_stack( array( 'about-hero', 'about-intro', 'about-capabilities', 'about-stats', 'about-values', 'about-contact' ) );
}
function ricoman_manufacturing_blocks() {
	return ricoman_stack( array( 'mfg-hero', 'mfg-stats', 'mfg-statement', 'mfg-split', 'mfg-steps', 'mfg-bespoke', 'mfg-cta' ) );
}
function ricoman_lighting_blocks() {
	return ricoman_stack( array( 'lighting-hero', 'lighting-statement', 'lighting-steps', 'lighting-receive', 'lighting-cta' ) );
}
function ricoman_downloads_blocks() {
	return ricoman_stack( array( 'downloads-hero', 'downloads-grid', 'downloads-cta' ) );
}
function ricoman_customisation_blocks() {
	return ricoman_stack( array( 'custom-hero', 'custom-intro', 'custom-cta' ) );
}
function ricoman_contact_blocks() {
	return ricoman_stack( array( 'contact-hero', 'contact-body' ) );
}

/* Feature / enhanced landing pages — each is one comprehensive editable pattern. */
function ricoman_casambi_blocks() {
	return ricoman_stack( array( 'feat-casambi' ) );
}
function ricoman_human_centric_blocks() {
	return ricoman_stack( array( 'feat-human-centric' ) );
}
function ricoman_antimicrobial_blocks() {
	return ricoman_stack( array( 'feat-antimicrobial' ) );
}
function ricoman_fire_safety_blocks() {
	return ricoman_stack( array( 'feat-fire-safety' ) );
}
function ricoman_sustainability_blocks() {
	return ricoman_stack( array( 'feat-sustainability' ) );
}
function ricoman_made_in_britain_blocks() {
	return ricoman_stack( array( 'feat-made-in-britain' ) );
}
function ricoman_trade_blocks() {
	return ricoman_stack( array( 'feat-trade' ) );
}
function ricoman_where_to_buy_blocks() {
	return ricoman_stack( array( 'feat-where-to-buy' ) );
}

/**
 * Generic styled info / legal page: dark title band + constrained body + CTA.
 * Used for Sustainability, Warranty, policies, etc. All blocks stay editable.
 */
function ricoman_info_blocks( $eyebrow, $title, $lead, $body = '', $cta = true ) {
	$pad  = 'padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)';
	$head = '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="' . $pad . '">'
		. '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $eyebrow . '</p><!-- /wp:paragraph -->'
		. '<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.2rem,5vw,3.6rem)","lineHeight":"1.02"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.2rem,5vw,3.6rem);font-weight:500;line-height:1.02">' . $title . '</h1><!-- /wp:heading -->'
		. ( $lead ? '<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.15rem"}}} --><p style="font-size:1.15rem">' . $lead . '</p><!-- /wp:paragraph -->' : '' )
		. '</div><!-- /wp:group -->';
	$content = '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section">' . $body . '</div><!-- /wp:group -->';
	$ctablk  = '';
	if ( $cta ) {
		$ctablk = '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Talk to the team</h2><!-- /wp:heading --><!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons is-content-justification-center"><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/contact/">Contact us</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->';
	}
	return $head . $content . $ctablk;
}

/** Helpers to build simple body markup for info pages. */
function ricoman_info_h( $t ) {
	return '<!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">' . $t . '</h2><!-- /wp:heading -->';
}
function ricoman_info_p( $t ) {
	return '<!-- wp:paragraph --><p>' . $t . '</p><!-- /wp:paragraph -->';
}

/** Definitions for the secondary / legal pages (slug => [eyebrow,title,lead,bodyfn]). */
function ricoman_info_pages() {
	$placeholder = ricoman_info_p( '<em>This is placeholder wording — replace it with your approved copy in the editor.</em>' );
	return array(
		// Casambi, Human Centric, Antimicrobial, Fire Safety & Sustainability are
		// now full feature pages (see ricoman_*_blocks + the Page Designs tool), so
		// they're no longer created here as thin info pages.
		'news'                    => array( 'Insights', 'News', 'Product launches, project stories and lighting know-how from the Ricoman team.', ricoman_info_p( 'Our latest news and articles will appear here.' ) ),
		'product-warranty'        => array( 'Assurance', 'Product Warranty', 'A 5-year warranty as standard, backed by UK manufacturing and support.', ricoman_info_h( 'What&rsquo;s covered' ) . ricoman_info_p( 'Ricoman luminaires carry a 5-year warranty against manufacturing defects under normal commercial use. Because we make in the UK, spares and support are close to hand.' ) . $placeholder ),
		'terms'                   => array( 'Legal', 'Terms &amp; Conditions', '', ricoman_info_h( 'Terms of sale &amp; use' ) . $placeholder ),
		'cookie-policy'           => array( 'Legal', 'Cookie Policy', '', ricoman_info_h( 'How we use cookies' ) . $placeholder ),
		'privacy-policy'          => array( 'Legal', 'Privacy Policy', '', ricoman_info_h( 'How we handle your data' ) . $placeholder ),
		'email-notice'            => array( 'Legal', 'E-mail Notice', '', ricoman_info_h( 'E-mail disclaimer' ) . $placeholder ),
		'modern-slavery-statement'=> array( 'Legal', 'Slavery &amp; Human Trafficking Statement', '', ricoman_info_h( 'Our commitment' ) . $placeholder ),
		'site-map'                => array( 'Navigate', 'Site Map', 'Everything on the Ricoman website, in one place.', ricoman_sitemap_body() ),
	);
}

/** Build a simple HTML site map from the main pages. */
function ricoman_sitemap_body() {
	$links = array(
		'Products' => '/products/', 'Projects' => '/projects/', 'Flow+ Designer' => '/flow-designer/',
		'Lighting Design' => '/lighting-design/', 'Manufacturing' => '/manufacturing/', 'Customisation' => '/customisation/',
		'About' => '/about/', 'Contact' => '/contact/', 'Downloads' => '/downloads/', 'Sustainability' => '/sustainability/',
		'Casambi' => '/casambi/', 'Human Centric Lighting' => '/human-centric-lighting/',
		'Antimicrobial Protection' => '/antimicrobial-protection/', 'Fire Safety' => '/fire-safety/',
		'Made in Britain' => '/made-in-britain/', 'Trade' => '/trade/', 'Where to Buy' => '/where-to-buy/',
		'Product Warranty' => '/product-warranty/',
		'Terms &amp; Conditions' => '/terms/', 'Privacy Policy' => '/privacy-policy/', 'Cookie Policy' => '/cookie-policy/',
	);
	$li = '';
	foreach ( $links as $label => $url ) {
		$li .= '<!-- wp:list-item --><li><a href="' . $url . '">' . $label . '</a></li><!-- /wp:list-item -->';
	}
	return '<!-- wp:list --><ul class="wp-block-list">' . $li . '</ul><!-- /wp:list -->';
}
function ricoman_stack( $slugs ) {
	$out = '';
	foreach ( $slugs as $s ) {
		// Resolve to real block markup so the page is directly click-to-edit
		// (not a read-only pattern reference). Falls back to a reference.
		$out .= ( function_exists( 'ricoman_pattern_content' ) ? ricoman_pattern_content( 'ricoman/' . $s ) : '<!-- wp:pattern {"slug":"ricoman/' . $s . '"} /-->' ) . "\n";
	}
	return $out;
}

/** Homepage = the original design as a stack of editable blocks. */
function ricoman_home_blocks() {
	return ricoman_stack( array( 'home-hero', 'home-facts', 'home-statement', 'home-range', 'home-featured', 'home-projects', 'home-britain', 'home-film', 'home-audience', 'home-cta' ) );
}

/**
 * Rich case-study body as native editable blocks (the "feature" project page).
 * The single-project template supplies the featured-image hero + closing CTA;
 * this is the editable post_content in between.
 *
 * @param array $pr [ title, image, sector, excerpt, products(assoc slug=>name) ].
 */
function ricoman_project_feature_content( $pr ) {
	$u    = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
	$lead = esc_html( $pr[3] );

	// --- Meta strip (dark band of label / value pairs) ---
	$meta = array(
		'Sector'   => $pr[2],
		'Location' => 'Manchester, UK',
		'Scope'    => 'Supply & free scheme design',
		'Lighting' => 'UK-made to order',
		'Warranty' => '5 years',
	);
	$mcols = '';
	foreach ( $meta as $k => $v ) {
		$mcols .= '<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">' . esc_html( $k ) . '</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>' . esc_html( $v ) . '</p><!-- /wp:paragraph --></div><!-- /wp:column -->';
	}
	$metastrip = '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)"><!-- wp:columns --><div class="wp-block-columns">' . $mcols . '</div><!-- /wp:columns --></div><!-- /wp:group -->';

	// --- Lead + body, with an "at a glance" facts box ---
	$glance = '<!-- wp:group {"className":"rm-soft","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group rm-soft" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">Project at a glance</p><!-- /wp:paragraph --><!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li><strong>Sector</strong> — ' . esc_html( $pr[2] ) . '</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Location</strong> — Manchester</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Avg. UGR</strong> — &lt;19</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>CRI</strong> — 90+</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Energy vs. base</strong> — &minus;52%</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Warranty</strong> — 5 years</li><!-- /wp:list-item --></ul><!-- /wp:list --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline","width":100} --><div class="wp-block-button is-style-outline has-custom-width wp-block-button__width-100"><a class="wp-block-button__link wp-element-button" href="/products/">View products used →</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->';

	$bodytext = '<!-- wp:paragraph {"style":{"typography":{"fontSize":"clamp(1.4rem,2.4vw,1.9rem)","lineHeight":"1.3","fontWeight":"500"}}} --><p style="font-size:clamp(1.4rem,2.4vw,1.9rem);line-height:1.3;font-weight:500">' . $lead . '</p><!-- /wp:paragraph -->' .
		'<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">The challenge</h3><!-- /wp:heading -->' .
		'<!-- wp:paragraph --><p>The space needed light that did more than meet a lux level — low glare for comfort, a calm visual rhythm, and a ceiling that felt intentional rather than service-led. Programme mattered too: the floor had to stay usable through the fit-out.</p><!-- /wp:paragraph -->' .
		'<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">What we specified</h3><!-- /wp:heading -->' .
		'<!-- wp:paragraph --><p>Our in-house team designed the scheme end to end — photometric study, luminaire schedule and a reflected ceiling layout — then made every fitting to order in Manchester to the exact geometry, delivered phased to suit the programme.</p><!-- /wp:paragraph -->';

	$bodysection = '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section"><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column {"width":"60%"} --><div class="wp-block-column" style="flex-basis:60%">' . $bodytext . '</div><!-- /wp:column --><!-- wp:column {"width":"40%"} --><div class="wp-block-column" style="flex-basis:40%">' . $glance . '</div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->';

	// --- Quote ---
	$quote = '<!-- wp:group {"align":"full","className":"rm-soft rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-soft rm-section"><!-- wp:heading {"textAlign":"center","level":2,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.6rem,3.4vw,2.6rem)","lineHeight":"1.2"}}} --><h2 class="wp-block-heading has-text-align-center" style="font-size:clamp(1.6rem,3.4vw,2.6rem);font-weight:500;line-height:1.2">&ldquo;It feels calm, it feels considered — and it was made to our exact ceiling.&rdquo;</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center","className":"rm-eyebrow"} --><p class="has-text-align-center rm-eyebrow">— Project Architect</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

	// --- Gallery ---
	$gallery = '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section"><!-- wp:gallery {"columns":2,"linkTo":"none","className":"rm-gallery"} --><figure class="wp-block-gallery has-nested-images columns-2 is-cropped rm-gallery"><!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="' . $u( 'rico-breakout-lounge.webp' ) . '" alt=""/></figure><!-- /wp:image --><!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="' . $u( 'rico-product-boards.webp' ) . '" alt=""/></figure><!-- /wp:image --></figure><!-- /wp:gallery --></div><!-- /wp:group -->';

	// --- Products used ---
	$cards = '';
	if ( ! empty( $pr[4] ) ) {
		$imgmap = array( 'flow-plus' => 'arch-line.webp', 'estrella' => 'estrella-lounge.webp', 'neptune' => 'ceiling.webp' );
		foreach ( $pr[4] as $ps => $pn ) {
			$im   = isset( $imgmap[ $ps ] ) ? $imgmap[ $ps ] : 'ceiling.webp';
			$href = '/products/' . $ps . '/';
			$cards .= '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-card"><!-- wp:image {"linkDestination":"custom","sizeSlug":"large"} --><figure class="wp-block-image size-large"><a href="' . $href . '"><img src="' . $u( $im ) . '" alt="' . esc_attr( $pn ) . '"/></a></figure><!-- /wp:image --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><a href="' . $href . '">' . esc_html( $pn ) . '</a></h3><!-- /wp:heading --></div><!-- /wp:group --></div><!-- /wp:column -->';
		}
		$products = '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Products specified</p><!-- /wp:paragraph --><!-- wp:heading {"level":2,"className":"rm-shead"} --><h2 class="wp-block-heading rm-shead">What&rsquo;s in this scheme</h2><!-- /wp:heading --><!-- wp:columns --><div class="wp-block-columns">' . $cards . '</div><!-- /wp:columns --></div><!-- /wp:group -->';
	} else {
		$products = '';
	}

	return $metastrip . $bodysection . $quote . $gallery . $products;
}

/**
 * Lighter case-study body for the "simple" project template (the template already
 * renders title, excerpt and featured image).
 *
 * @param array $pr [ title, image, sector, excerpt, products(assoc slug=>name) ].
 */
function ricoman_project_simple_content( $pr ) {
	$out  = '<!-- wp:paragraph --><p>Our in-house team designed and supplied the lighting for this ' . esc_html( strtolower( $pr[2] ) ) . ' scheme — a free photometric design, UK-made luminaires built to order in Manchester, and delivery to programme.</p><!-- /wp:paragraph -->';
	$out .= '<!-- wp:paragraph --><p>Low-glare optics, high-CRI light and a 5-year warranty throughout, with value-engineered options kept on the table from the first drawing.</p><!-- /wp:paragraph -->';
	if ( ! empty( $pr[4] ) ) {
		$links = array();
		foreach ( $pr[4] as $ps => $pn ) {
			$links[] = '<a href="/products/' . $ps . '/">' . esc_html( $pn ) . '</a>';
		}
		$out .= '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Products used</h3><!-- /wp:heading -->';
		$out .= '<!-- wp:paragraph --><p>' . implode( ' · ', $links ) . '</p><!-- /wp:paragraph -->';
	}
	return $out;
}

/**
 * One-time: swap the homepage's baked placeholder range grid for the dynamic
 * [ricoman_category_cards] shortcode (real categories + images). Surgical — only
 * replaces the columns inside the "A luminaire…" range group, leaving the rest of
 * the page intact, and only if it still holds the original demo markup.
 */
add_action( 'wp_loaded', function () {
	if ( get_option( 'ricoman_home_range_v3' ) ) {
		return;
	}
	$home_id = (int) get_option( 'page_on_front' );
	if ( ! $home_id ) {
		return; // try again later once the front page is set.
	}
	$c = (string) get_post_field( 'post_content', $home_id );
	$anchor = 'A luminaire for every commercial interior';
	if ( '' === $c || false === strpos( $c, $anchor ) || false !== strpos( $c, '[ricoman_category_cards' ) ) {
		update_option( 'ricoman_home_range_v3', 1 );
		return;
	}
	// Replace the two <!-- wp:columns --> grids (the range cards) after the heading
	// with the dynamic shortcode. Target the columns directly so the nested card
	// groups don't trip the boundary detection.
	$ap       = strpos( $c, $anchor );
	$colStart = strpos( $c, '<!-- wp:columns', $ap );
	if ( false !== $colStart ) {
		$tag = '<!-- /wp:columns -->';
		$c1  = strpos( $c, $tag, $colStart );
		$c2  = ( false !== $c1 ) ? strpos( $c, $tag, $c1 + 1 ) : false;
		$end = false;
		if ( false !== $c2 ) {
			$end = $c2 + strlen( $tag );
		} elseif ( false !== $c1 ) {
			$end = $c1 + strlen( $tag );
		}
		if ( false !== $end ) {
			$new = substr( $c, 0, $colStart ) . '<!-- wp:shortcode -->[ricoman_category_cards limit="8"]<!-- /wp:shortcode -->' . substr( $c, $end );
			wp_update_post( array( 'ID' => $home_id, 'post_content' => $new ) );
		}
	}
	update_option( 'ricoman_home_range_v3', 1 );
}, 20 );
