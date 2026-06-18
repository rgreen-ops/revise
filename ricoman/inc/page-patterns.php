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
	// Brand videos (hosted on ricoman.com). Editable in the block (swap the URL).
	$vid_banner = 'https://ricoman.com/back-end/wp-content/uploads/2026/02/Ricoman-Website-banner-1.mp4';
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
	$p['home-hero'] = array( 'Home · Hero (video)', $vcover( $vid_banner, $hero_inner, 90, 'bottom left', 60 ) );
	$p['home-hero-image'] = array( 'Home · Hero (image)', $cover( $u( 'warm-int.webp' ), $hero_inner, 90, 'bottom left', 50 ) );
	$p['home-film'] = array( 'Home · Brand film', $sec( $eyebrow( 'Watch · Made in Britain' ) . $shead( 'See how we make light' ) . '<!-- wp:video {"className":"rm-filmvid"} --><figure class="wp-block-video rm-filmvid"><video controls playsinline poster="' . $u( 'workshop.webp' ) . '" src="' . $vid_story . '"></video></figure><!-- /wp:video -->' ) );

	$p['home-facts'] = array( 'Home · Facts band', $sec( '<!-- wp:columns --><div class="wp-block-columns">' . $stat( 'UK-made, ~6-day average', 'Lead Time' ) . $stat( '535+ schemes designed', 'Last Year' ) . $stat( '2,000+ components', 'In Stock' ) . $stat( 'Strong local network', 'Partners' ) . '</div><!-- /wp:columns -->', 'rm-facts' ) );

	$p['home-statement'] = array( 'Home · Statement', $sec( '<!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.9rem,5vw,4rem)","lineHeight":"1.08"}}} --><h2 class="wp-block-heading" style="font-size:clamp(1.9rem,5vw,4rem);font-weight:500;line-height:1.08">We believe great light is felt, not noticed. So we design and make commercial lighting in Britain that lets architects and designers shape how a space looks, feels and performs — delivered on spec, on time, on budget.</h2><!-- /wp:heading -->' ) );

	$p['home-range'] = array( 'Home · Range grid', $sec( $shead( 'A luminaire for every commercial interior' ) .
		'<!-- wp:columns --><div class="wp-block-columns">' . $rcard( $u( 'arch-line.webp' ), 'Linear Lighting', 'Continuous runs &amp; profile systems', '/products/' ) . $rcard( $u( 'ceiling.webp' ), 'Downlights', 'Fire-rated, switchable CCT', '/products/' ) . $rcard( $u( 'pendant.webp' ), 'Pendants', 'Architectural &amp; decorative', '/products/' ) . '</div><!-- /wp:columns -->' .
		'<!-- wp:columns --><div class="wp-block-columns">' . $rcard( $u( 'retail.webp' ), 'Track &amp; Spotlights', 'Retail &amp; gallery accent', '/products/' ) . $rcard( $u( 'office5.webp' ), 'Biophilic Lighting', 'Human-centric, tunable', '/products/' ) . $rcard( $u( 'office3.webp' ), 'Modular Recessed', 'Offices, schools, healthcare', '/products/' ) . '</div><!-- /wp:columns -->' ) );

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

	/* ===== About ===== */
	$p['about-hero'] = array( 'About · Hero', $cover( $u( 'rico-office.webp' ), $eyebrow( 'About Ricoman' ) . '<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)","lineHeight":"1"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500;line-height:1">British lighting, made with intent.</h1><!-- /wp:heading -->' . $para( 'A Manchester manufacturer of commercial interior LED lighting — designing, making and delivering schemes for the people who build great spaces.' ), 62, 'bottom left', 50 ) );
	$p['about-intro'] = array( 'About · Who we are', $sec( $twocol(
		$eyebrow( '01 · Who we are' ) . $shead( 'Lighting that works on spec, on time, on budget' ) .
		$para( 'Ricoman designs and manufactures commercial interior LED lighting from our own facility in Manchester. We supply architects, interior designers, design &amp; build teams and electrical contractors across the UK.', true ) .
		$para( 'Because we&rsquo;re the manufacturer — not a reseller — we control quality, lead times and bespoke detail in-house. That lets us offer free scheme design, 2,000+ components stocked ready to build, an average six-day UK-made lead, strong local partnerships and the confidence of a 5-year warranty.', true ) .
		$buttons( $btn( 'Inside our manufacturing →', '/manufacturing/', false ) ),
		$image( $u( 'rico-office.webp' ), '', 'Inside Ricoman&rsquo;s Manchester facility' )
	) ) );
	$p['about-stats'] = array( 'About · Stats', $sec( '<!-- wp:columns --><div class="wp-block-columns">' . $bignum( '1999', 'Our journey began' ) . $bignum( '535', 'Lighting design projects in 2025' ) . $bignum( '2,000+', 'Components stocked, ready to build' ) . $bignum( '5 yr', 'Standard warranty' ) . '</div><!-- /wp:columns -->', 'rm-statband' ) );
	$p['about-values'] = array( 'About · Values', $sec( $eyebrow( '02 · What we stand for' ) . $shead( 'The way we like to work' ) . '<!-- wp:columns --><div class="wp-block-columns">' . $aud( '↳ 01', 'Made in Britain', 'Designed, built, finished and tested in Manchester — full control, full traceability, shorter lead times.' ) . $aud( '↳ 02', 'Specifier-first', 'Free scheme design, clean photometrics and honest lead times. We make the spec easy to stand behind.' ) . $aud( '↳ 03', 'Built to last', 'Serviceable, high-CRI fittings backed by a 5-year warranty — good for the building and the planet.' ) . '</div><!-- /wp:columns -->' ) );
	$contact_left  = $eyebrow( '03 · Get in touch' ) . $shead( 'Talk to the team' ) . $para( 'Quotes, lead times, a tricky detail or a full scheme — our Manchester team will get you a real answer, fast.', true ) . '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Visit / Post</h4><!-- /wp:heading -->' . $para( 'Metroplex Business Park<br>520 Broadway, M50 2UE<br>Manchester, United Kingdom' ) . '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Call</h4><!-- /wp:heading -->' . $para( '<a href="tel:01614515913">0161 451 5913</a>' ) . '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Email</h4><!-- /wp:heading -->' . $para( '<a href="mailto:sales@ricoman.com">sales@ricoman.com</a>' ) . '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Hours</h4><!-- /wp:heading -->' . $para( 'Mon&ndash;Thu 8:30&ndash;17:00 &middot; Fri 8:30&ndash;16:00' );
	$contact_form  = '<!-- wp:group {"className":"rm-soft","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group rm-soft" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">Send an enquiry</p><!-- /wp:paragraph --><!-- wp:shortcode -->[ricoman_lead_form]<!-- /wp:shortcode --></div><!-- /wp:group -->';
	$p['about-contact'] = array( 'About · Contact &amp; enquiry', $sec( $twocol( $contact_left, $contact_form ) ) );

	/* ===== Manufacturing ===== */
	$p['mfg-hero'] = array( 'Manufacturing · Hero', $cover( $u( 'workshop.webp' ), $eyebrow( 'Made in Britain' ) . '<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)","lineHeight":"1"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500;line-height:1">Designed &amp; manufactured in Manchester.</h1><!-- /wp:heading -->' . $para( 'We design, assemble, test and finish our luminaires in our own UK facility — controlling quality, lead times and every bespoke detail in-house.' ), 64, 'bottom left', 50 ) );
	$p['mfg-stats'] = array( 'Manufacturing · Stats', $sec( '<!-- wp:columns --><div class="wp-block-columns">' . $bignum( '15,000ft²', 'Production area, Manchester' ) . $bignum( '2,000+', 'Components ready to build' ) . $bignum( '~6 days', 'Average UK-made lead' ) . $bignum( '98%', 'On-time-in-full target' ) . '</div><!-- /wp:columns -->', 'rm-statband' ) );
	$p['mfg-statement'] = array( 'Manufacturing · Statement', $sec( '<!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.9rem,5vw,4rem)","lineHeight":"1.08"}}} --><h2 class="wp-block-heading" style="font-size:clamp(1.9rem,5vw,4rem);font-weight:500;line-height:1.08">Vertical integration, end to end — we make to order, not to a catalogue.</h2><!-- /wp:heading -->' ) );
	$p['mfg-split'] = array( 'Manufacturing · One roof', $sec( $twocol( $image( $u( 'rico-making.webp' ), '', 'In-house LED lighting assembly and finishing in the UK' ), $eyebrow( 'In-house, end to end' ) . $shead( 'One roof, full control' ) . $para( 'Design, electronics, assembly, finishing and testing all happen under one roof in Manchester — nothing outsourced to a supply chain we can&rsquo;t see. Shorter lead times, full traceability, and the ability to make a fitting to your exact geometry.', true ) ) ) );
	$p['mfg-steps'] = array( 'Manufacturing · Process', $sec( $shead( 'How a Ricoman fitting is made' ) . '<!-- wp:columns --><div class="wp-block-columns">' . $aud( '01', 'Design &amp; tooling', 'CAD, photometric modelling and tooling, in-house.' ) . $aud( '02', 'Assembly', 'Boards, optics and housings hand-built to order.' ) . $aud( '03', 'Finishing', 'Powder-coat &amp; bespoke finishes to your spec.' ) . $aud( '04', 'Test &amp; despatch', 'Batch burn-in, then delivered UK-wide.' ) . '</div><!-- /wp:columns -->' ) );
	$p['mfg-bespoke'] = array( 'Manufacturing · Bespoke (dark)', $darkgroup( $twocol( $eyebrow( 'Bespoke as standard' ) . $shead( 'If you can draw it, we can make it' ) . $para( 'Curved linear runs, custom lengths, special CCTs, brand-matched finishes — bespoke isn&rsquo;t a bolt-on, it&rsquo;s how the factory is built to work.' ) . $buttons( $btn( 'Talk to our designers →', '/lighting-design/' ) ), $image( $u( 'warehouse.webp' ), '', 'Ricoman component stock for made-to-order UK lighting' ) ) ) );
	$p['mfg-cta'] = array( 'Manufacturing · CTA', $cover( $u( 'workshop.webp' ), '<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Want to see it for yourself?</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Book a visit to the Manchester facility, or send us a project and let our team spec it end to end.</p><!-- /wp:paragraph -->' . $buttons( $btn( 'Book a visit', '/about/' ) . $btn( 'Start a project →', '/lighting-design/', false ), true ), 52, 'center center', 70 ) );

	/* ===== Lighting Design ===== */
	$p['lighting-hero'] = array( 'Lighting · Hero', $cover( $u( 'rico-office-render.webp' ), $eyebrow( 'Free Scheme Design' ) . '<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)","lineHeight":"1"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500;line-height:1">Your scheme, fully designed — at no cost.</h1><!-- /wp:heading -->' . $para( 'Send us a drawing or a finishes schedule and our in-house lighting designers return a fully specified, photometric-backed and costed scheme. Usually within 3–5 days.' ), 64, 'bottom left', 50 ) );
	$p['lighting-statement'] = array( 'Lighting · Statement', $sec( '<!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.9rem,5vw,4rem)","lineHeight":"1.08"}}} --><h2 class="wp-block-heading" style="font-size:clamp(1.9rem,5vw,4rem);font-weight:500;line-height:1.08">We don&rsquo;t just sell luminaires — we design the light, prove it works, and cost it before you commit a penny.</h2><!-- /wp:heading -->' ) );
	$p['lighting-steps'] = array( 'Lighting · Process', $sec( $shead( 'Four steps from drawing to delivered scheme' ) . '<!-- wp:columns --><div class="wp-block-columns">' . $aud( '01', 'Send your drawings', 'A plan, RCP or sketch and a finishes schedule.' ) . $aud( '02', 'We design the light', 'A DIALux photometric study — lux, uniformity, UGR.' ) . $aud( '03', 'Costed scheme in 3–5 days', 'A specified luminaire schedule, layout and costing.' ) . $aud( '04', 'Made &amp; delivered', 'Approved, made to order in Manchester, to programme.' ) . '</div><!-- /wp:columns -->' ) );
	$p['lighting-receive'] = array( 'Lighting · What you receive (dark)', $darkgroup( $twocol( $eyebrow( 'What you receive' ) . $shead( 'A scheme you can specify with confidence' ) . $checklist( array( '<strong>DIALux photometric study</strong> — lux, uniformity &amp; UGR', '<strong>Luminaire schedule</strong> — every fitting, finish &amp; quantity', '<strong>Reflected ceiling layout</strong> — positions for the contractor', '<strong>Itemised costing</strong> — with value-engineered options', '<strong>Data sheets &amp; BIM files</strong> — ready for your spec pack' ) ), $image( $u( 'office5.webp' ), '', 'Low-glare workplace LED lighting scheme' ) ) ) );
	$p['lighting-cta'] = array( 'Lighting · CTA', $cover( $u( 'office1.webp' ), '<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Start your scheme</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Tell us about the project and share your drawings — a lighting designer will be in touch, costed scheme to follow.</p><!-- /wp:paragraph -->' . $buttons( $btn( 'Talk to the team', '/about/' ) . $btn( 'See the results →', '/projects/', false ), true ), 52, 'center center', 70 ) );

	/* ===== Downloads ===== */
	$dlcard = function ( $title, $desc, $href ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-card rm-soft","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group rm-card rm-soft" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40)"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><a href="' . $href . '">' . $title . ' &darr;</a></h3><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted","fontSize":"small"} --><p class="has-muted-color has-text-color has-small-font-size">' . $desc . '</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->'; };
	$p['downloads-hero'] = array( 'Downloads · Hero', $cover( $u( 'rico-downloads.webp' ), $eyebrow( 'Downloads &amp; Resources' ) . '<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)","lineHeight":"1"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500;line-height:1">Catalogues, datasheets &amp; BIM.</h1><!-- /wp:heading -->' . $para( 'Everything you need to specify Ricoman — product catalogue, technical datasheets, photometric (IES/LDT) files, BIM objects and installation guides.' ), 58, 'bottom left', 50 ) );
	$p['downloads-grid'] = array( 'Downloads · Resource grid', $sec( $eyebrow( 'Resource library' ) . $shead( 'Download what you need' ) . '<!-- wp:columns --><div class="wp-block-columns">' . $dlcard( 'Product catalogue', 'The full Ricoman range in one PDF.', '/downloads/' ) . $dlcard( 'Datasheets', 'Per-product technical datasheets &amp; specs.', '/downloads/' ) . $dlcard( 'Photometric files', 'IES / LDT files for DIALux &amp; Relux.', '/downloads/' ) . '</div><!-- /wp:columns --><!-- wp:columns --><div class="wp-block-columns">' . $dlcard( 'BIM objects', 'Revit families for your model.', '/downloads/' ) . $dlcard( 'Installation guides', 'Step-by-step fitting instructions.', '/downloads/' ) . $dlcard( 'Certificates', 'Warranty, CE/UKCA &amp; compliance.', '/downloads/' ) . '</div><!-- /wp:columns -->' ) );
	$p['downloads-cta'] = array( 'Downloads · CTA', $cover( $u( 'rico-proline-breakout.webp' ), '<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Can&rsquo;t find a document?</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Tell us the product or project and our team will send the exact files you need.</p><!-- /wp:paragraph -->' . $buttons( $btn( 'Request files', '/contact/' ) . $btn( 'Talk to the team →', '/about/', false ), true ), 50, 'center center', 70 ) );

	/* ===== Customisation ===== */
	$p['custom-hero'] = array( 'Customisation · Hero', $cover( $u( 'rico-astrowave-banner.webp' ), $eyebrow( 'Customisation &amp; Bespoke' ) . '<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)","lineHeight":"1"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500;line-height:1">If you can draw it, we can make it.</h1><!-- /wp:heading -->' . $para( 'Curved runs, custom lengths, special CCTs and brand-matched finishes — bespoke is how our Manchester factory is built to work.' ), 62, 'bottom left', 50 ) );
	$p['custom-intro'] = array( 'Customisation · Intro', $sec( $twocol( $eyebrow( 'Made to your spec' ) . $shead( 'Bespoke as standard' ) . $para( 'Tell us the geometry, the look and the performance you need. Our in-house design and manufacturing teams turn it into a buildable, costed luminaire — made to order on an average six-day UK lead.', true ) . $buttons( $btn( 'Design a Flow+ run →', '/flow-designer/' ) . $btn( 'Talk to our designers', '/contact/', false ) ), $image( $u( 'rico-betfred-flow.webp' ), '', 'Bespoke Flow+ curved linear lighting at Betfred HQ' ) ) ) );
	$p['custom-cta'] = array( 'Customisation · CTA', $cover( $u( 'rico-soundslikelight.webp' ), '<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Start a bespoke project</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Use the Flow+ Designer to draw your run, or send us a sketch and we&rsquo;ll spec it end to end.</p><!-- /wp:paragraph -->' . $buttons( $btn( 'Open Flow+ Designer', '/flow-designer/' ) . $btn( 'Talk to the team →', '/contact/', false ), true ), 52, 'center center', 70 ) );

	/* ===== Contact ===== */
	$p['contact-hero'] = array( 'Contact · Hero', $cover( $u( 'rico-office.webp' ), $eyebrow( 'Contact' ) . '<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem, 6vw, 5rem)","lineHeight":"1"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 5rem);font-weight:500;line-height:1">Let&rsquo;s talk lighting.</h1><!-- /wp:heading -->' . $para( 'Send us a project, a drawing or a question — our Manchester team will get straight back to you.' ), 52, 'bottom left', 50 ) );
	$p['contact-body'] = array( 'Contact · Form &amp; details', $sec( '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column {"width":"58%"} --><div class="wp-block-column" style="flex-basis:58%">' . $eyebrow( 'Send an enquiry' ) . $shead( 'Tell us about your project' ) . '<!-- wp:shortcode -->[ricoman_lead_form]<!-- /wp:shortcode --></div><!-- /wp:column --><!-- wp:column {"width":"42%"} --><div class="wp-block-column" style="flex-basis:42%"><!-- wp:group {"className":"rm-soft","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group rm-soft" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40)"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Get in touch</h3><!-- /wp:heading -->' . $para( 'Metroplex Business Park<br>520 Broadway, M50 2UE<br>Manchester, UK' ) . $para( '<strong>0161 451 5913</strong><br>sales@ricoman.com' ) . $para( 'Mon–Thu 8:30–17:00 · Fri 8:30–16:00' ) . '</div><!-- /wp:group --></div><!-- /wp:column --></div><!-- /wp:columns -->' ) );

	foreach ( $p as $slug => $data ) {
		register_block_pattern( 'ricoman/' . $slug, array( 'title' => $data[0], 'categories' => array( 'ricoman-page' ), 'content' => $data[1] ) );
	}
}, 12 );

/** Editable block stacks for the content pages. */
function ricoman_about_blocks() {
	return ricoman_stack( array( 'about-hero', 'about-intro', 'about-stats', 'about-values', 'about-contact' ) );
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
		'sustainability'          => array( 'Responsibility', 'Sustainability', 'Better light, made responsibly — lower energy in use, less waste in manufacture, and products built to last.', ricoman_info_h( 'Our approach' ) . ricoman_info_p( 'We design for efficiency and longevity: high-efficacy LEDs, serviceable fittings, recyclable materials and UK manufacturing that cuts transport miles. Made to order means less overproduction and less waste.' ) . ricoman_info_h( 'Circular by design' ) . ricoman_info_p( 'Replaceable drivers and modular components extend product life and keep fittings out of landfill.' ) ),
		'news'                    => array( 'Insights', 'News', 'Product launches, project stories and lighting know-how from the Ricoman team.', ricoman_info_p( 'Our latest news and articles will appear here.' ) ),
		'casambi'                 => array( 'Controls', 'Casambi', 'Wireless lighting control — tunable, scene-ready and simple to commission.', ricoman_info_h( 'Casambi-ready luminaires' ) . ricoman_info_p( 'Many Ricoman products are available Casambi-enabled for wireless dimming, tuning and scene control, with no extra wiring.' ) ),
		'human-centric-lighting'  => array( 'Wellbeing', 'Human Centric Lighting', 'Light that supports how people feel, focus and rest — tunable white and biophilic schemes.', ricoman_info_h( 'Designing for people' ) . ricoman_info_p( 'Tunable-white and circadian-aware lighting helps align interior light with the rhythm of the day, supporting comfort, concentration and wellbeing in workplaces, healthcare and education.' ) ),
		'antimicrobial-protection'=> array( 'Hygiene', 'Antimicrobial Protection', 'Surface protection for healthcare, education and food environments.', ricoman_info_h( 'Cleaner surfaces' ) . ricoman_info_p( 'Selected fittings are available with antimicrobial surface treatment to inhibit the growth of bacteria on the luminaire surface — ideal for clinical and hygiene-sensitive spaces.' ) ),
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
		'Human Centric Lighting' => '/human-centric-lighting/', 'Product Warranty' => '/product-warranty/',
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
