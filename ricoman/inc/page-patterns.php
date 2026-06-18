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
	$image   = function ( $url, $href = '' ) { $i = '<img src="' . $url . '" alt=""/>'; if ( $href ) { return '<!-- wp:image {"linkDestination":"custom"} --><figure class="wp-block-image size-large"><a href="' . $href . '">' . $i . '</a></figure><!-- /wp:image -->'; } return '<!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large">' . $i . '</figure><!-- /wp:image -->'; };
	$btn     = function ( $label, $href = '#', $light = true ) { return '<!-- wp:button {"className":"is-style-outline' . ( $light ? '-light' : '' ) . '"} --><div class="wp-block-button is-style-outline' . ( $light ? '-light' : '' ) . '"><a class="wp-block-button__link wp-element-button" href="' . $href . '">' . $label . '</a></div><!-- /wp:button -->'; };
	$buttons = function ( $inner, $center = false ) { return '<!-- wp:buttons' . ( $center ? ' {"layout":{"type":"flex","justifyContent":"center"}}' : '' ) . ' --><div class="wp-block-buttons' . ( $center ? ' is-content-justification-center' : '' ) . '">' . $inner . '</div><!-- /wp:buttons -->'; };
	$cover   = function ( $url, $inner, $min, $pos, $dim ) {
		$poscls = 'center center' === $pos ? '' : ' has-custom-content-position is-position-' . str_replace( ' ', '-', $pos );
		return '<!-- wp:cover {"url":"' . $url . '","dimRatio":' . $dim . ',"overlayColor":"ink","minHeight":' . $min . ',"minHeightUnit":"vh","contentPosition":"' . $pos . '","align":"full","textColor":"base"} --><div class="wp-block-cover alignfull has-base-color has-text-color' . $poscls . '" style="min-height:' . $min . 'vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-' . $dim . ' has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">' . $inner . '</div></div><!-- /wp:cover -->';
	};
	$rcard = function ( $url, $title, $sub, $href ) use ( $image ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-card">' . $image( $url, $href ) . '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><a href="' . $href . '">' . $title . '</a></h3><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted","fontSize":"small"} --><p class="has-muted-color has-text-color has-small-font-size">' . $sub . '</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->'; };
	$ptile = function ( $url, $eyb, $title, $href ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:cover {"url":"' . $url . '","dimRatio":40,"overlayColor":"ink","minHeight":320,"contentPosition":"bottom left","isLink":true,"href":"' . $href . '"} --><div class="wp-block-cover has-custom-content-position is-position-bottom-left" style="min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"className":"rm-eyebrow","textColor":"base"} --><p class="rm-eyebrow has-base-color has-text-color">' . $eyb . '</p><!-- /wp:paragraph --><!-- wp:heading {"level":3,"textColor":"base"} --><h3 class="wp-block-heading has-base-color has-text-color">' . $title . '</h3><!-- /wp:heading --></div></div><!-- /wp:cover --></div><!-- /wp:column -->'; };
	$aud   = function ( $n, $h, $p ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"rm-aud-card","layout":{"type":"constrained"}} --><div class="wp-block-group rm-aud-card"><!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">' . $n . '</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . $h . '</h3><!-- /wp:heading --><!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color">' . $p . '</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->'; };
	$stat  = function ( $n, $l ) { return '<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"rm-flabel"} --><p class="rm-flabel">' . $l . '</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"rm-fval"} --><p class="rm-fval">' . $n . '</p><!-- /wp:paragraph --></div><!-- /wp:column -->'; };

	$p = array();

	$p['home-hero'] = array( 'Home · Hero', $cover( $u( 'warm-int.jpg' ),
		$eyebrow( 'Commercial Interior Lighting · Made in Britain' ) .
		'<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"clamp(2.8rem, 7vw, 6rem)","fontWeight":"500","lineHeight":"0.98"}}} --><h1 class="wp-block-heading" style="font-size:clamp(2.8rem, 7vw, 6rem);font-weight:500;line-height:0.98">Lighting that transforms commercial interiors.</h1><!-- /wp:heading -->' .
		$para( 'Designed and manufactured in Manchester for architects, interior designers, design &amp; build teams and electrical contractors — on spec, on time, on budget.' ) .
		$buttons( $btn( 'Explore Products', '/products/' ) . $btn( 'Free Scheme Design', '/lighting-design/' ) ), 90, 'bottom left', 50 ) );

	$p['home-facts'] = array( 'Home · Facts band', $sec( '<!-- wp:columns --><div class="wp-block-columns">' . $stat( 'UK-made, ~6-day average', 'Lead Time' ) . $stat( '535+ schemes designed', 'Last Year' ) . $stat( '2,000+ components', 'In Stock' ) . $stat( 'Strong local network', 'Partners' ) . '</div><!-- /wp:columns -->', 'rm-facts' ) );

	$p['home-statement'] = array( 'Home · Statement', $sec( '<!-- wp:heading {"level":2,"style":{"typography":{"fontWeight":"500","fontSize":"clamp(1.9rem,5vw,4rem)","lineHeight":"1.08"}}} --><h2 class="wp-block-heading" style="font-size:clamp(1.9rem,5vw,4rem);font-weight:500;line-height:1.08">We design and deliver UK-made commercial lighting — bringing spaces to life on spec, on time, and on budget.</h2><!-- /wp:heading -->' ) );

	$p['home-range'] = array( 'Home · Range grid', $sec( $shead( 'A luminaire for every commercial interior' ) .
		'<!-- wp:columns --><div class="wp-block-columns">' . $rcard( $u( 'arch-line.jpg' ), 'Linear Lighting', 'Continuous runs &amp; profile systems', '/products/' ) . $rcard( $u( 'ceiling.jpg' ), 'Downlights', 'Fire-rated, switchable CCT', '/products/' ) . $rcard( $u( 'pendant.jpg' ), 'Pendants', 'Architectural &amp; decorative', '/products/' ) . '</div><!-- /wp:columns -->' .
		'<!-- wp:columns --><div class="wp-block-columns">' . $rcard( $u( 'retail.jpg' ), 'Track &amp; Spotlights', 'Retail &amp; gallery accent', '/products/' ) . $rcard( $u( 'office5.jpg' ), 'Biophilic Lighting', 'Human-centric, tunable', '/products/' ) . $rcard( $u( 'office3.jpg' ), 'Modular Recessed', 'Offices, schools, healthcare', '/products/' ) . '</div><!-- /wp:columns -->' ) );

	$p['home-featured'] = array( 'Home · Featured (dark)', '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)"><!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $eyebrow( 'Featured · Linear' ) . $shead( 'Flow+ — seamless curves of light' ) . $para( 'A flexible linear system that bends to any architectural line, continuous and dot-free. Made to order in Manchester, to your exact geometry.' ) . $buttons( $btn( 'View Flow+', '/products/flow-plus/' ) . $btn( 'Design your run', '/flow-designer/' ) ) . '</div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $image( $u( 'light-a.jpg' ) ) . '</div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->' );

	$p['home-projects'] = array( 'Home · Projects grid', $sec( $shead( 'Lighting that performs in the real world' ) .
		'<!-- wp:columns --><div class="wp-block-columns">' . $ptile( $u( 'office1.jpg' ), 'Commercial Office · Leeds', 'Allianz HQ Fit-out', '/projects/allianz-hq/' ) . $ptile( $u( 'retail.jpg' ), 'Retail · Manchester', 'Flagship Store', '/projects/flagship-store/' ) . $ptile( $u( 'office6.jpg' ), 'Workplace', 'Studio HQ', '/projects/studio-hq/' ) . $ptile( $u( 'office2.jpg' ), 'Hospitality', 'Boutique Hotel', '/projects/boutique-hotel/' ) . '</div><!-- /wp:columns -->' .
		$buttons( $btn( 'All projects', '/projects/', false ) ) ) );

	$p['home-britain'] = array( 'Home · Made in Britain (dark)', '<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"base","className":"rm-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-dark has-base-color has-ink-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50)"><!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $eyebrow( 'Made in Britain' ) . $shead( 'Designed &amp; manufactured in Manchester' ) . $para( 'We design, assemble, test and finish our luminaires in our own UK facility — controlling quality, lead times and every bespoke detail. With 2,000+ components stocked, we make to order on an average six-day lead.' ) . $buttons( $btn( 'Inside our manufacturing →', '/manufacturing/' ) ) . '</div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $image( $u( 'workshop.jpg' ) ) . '</div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->' );

	$p['home-audience'] = array( 'Home · Audience', $sec( $shead( 'A partner for the whole project team' ) .
		'<!-- wp:columns --><div class="wp-block-columns">' . $aud( '01', 'Architects', 'Clean photometrics, full data sheets and BIM-ready files for precise specification.' ) . $aud( '02', 'Interior Designers', 'Warm, high-CRI light and decorative ranges that flatter materials and finishes.' ) . $aud( '03', 'Design &amp; Build', 'Value-engineered alternatives, budget certainty and stock to keep programmes moving.' ) . $aud( '04', 'Contractors', 'Fast quotes, reliable lead times and easy-install fittings that wire up first time.' ) . '</div><!-- /wp:columns -->' ) );

	$p['home-cta'] = array( 'Home · CTA', $cover( $u( 'office1.jpg' ),
		'<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Have a project on the board?</h2><!-- /wp:heading -->' .
		'<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Send us your drawings and our in-house lighting designers will return a fully specified, costed scheme — usually within 3–5 days.</p><!-- /wp:paragraph -->' .
		$buttons( $btn( 'Start a Project', '/lighting-design/' ) . $btn( 'Talk to the team', '/about/', false ), true ), 60, 'center center', 70 ) );

	foreach ( $p as $slug => $data ) {
		register_block_pattern( 'ricoman/' . $slug, array( 'title' => $data[0], 'categories' => array( 'ricoman-page' ), 'content' => $data[1] ) );
	}
}, 12 );

/** Homepage = the original design as a stack of editable blocks. */
function ricoman_home_blocks() {
	$stack = array( 'home-hero', 'home-facts', 'home-statement', 'home-range', 'home-featured', 'home-projects', 'home-britain', 'home-audience', 'home-cta' );
	$out   = '';
	foreach ( $stack as $slug ) {
		$out .= '<!-- wp:pattern {"slug":"ricoman/' . $slug . '"} /-->' . "\n";
	}
	return $out;
}
