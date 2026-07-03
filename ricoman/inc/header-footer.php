<?php
/**
 * [ricoman_header] and [ricoman_footer] — render the header (mega menu, search,
 * login) and footer (contact, socials, link columns) from the Ricoman Site
 * options, so the template parts stay editable from one admin screen.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A11y: the block templates already provide the page's <main> landmark, so any
 * <main> inside PAGE CONTENT (e.g. a Group block authored as <main> on the My
 * Project page) is a duplicate-main landmark violation. Demote any <main> in the
 * rendered content to a <div> (the template's <main> is untouched). Runs at
 * priority 9 — before the product renderer (11), whose content is still empty
 * then, so products are unaffected.
 */
add_filter( 'the_content', function ( $html ) {
	if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $html;
	}
	if ( ! is_string( $html ) || false === stripos( $html, '<main' ) ) {
		return $html;
	}
	$html = preg_replace( '#<main(\b[^>]*)>#i', '<div$1 data-was-main="1">', $html );
	$html = preg_replace( '#</main>#i', '</div>', $html );
	return $html;
}, 9 );

/**
 * SEO/A11y: normalise heading levels in rendered singular content so no level is
 * skipped (e.g. a legal page that jumps H1 → H5). Headings don't nest, so we walk
 * them in order, demoting any heading that skips more than one level below the
 * previous one. The H1 is never touched (it stays the single page H1), so this
 * can only fix skips, never remove the H1 or make the outline worse. Runs at
 * priority 12 — after the product renderer (11) — so it also tidies product
 * pages. Filterable off via ricoman_normalise_headings.
 */
add_filter( 'the_content', function ( $html ) {
	if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $html;
	}
	if ( ! is_string( $html ) || ! preg_match( '/<h[2-6]\b/i', $html ) || ! apply_filters( 'ricoman_normalise_headings', true ) ) {
		return $html;
	}
	$prev  = 1; // the page H1.
	$stack = array();
	return preg_replace_callback( '#<(/?)h([1-6])(\b[^>]*)>#i', function ( $m ) use ( &$prev, &$stack ) {
		if ( '/' === $m[1] ) {
			$lvl = $stack ? array_pop( $stack ) : (int) $m[2];
			return '</h' . $lvl . '>';
		}
		$lvl = (int) $m[2];
		if ( 1 !== $lvl && $lvl > $prev + 1 ) {
			$lvl = $prev + 1; // fill the skipped level.
		}
		$prev    = $lvl;
		$stack[] = $lvl;
		return '<h' . $lvl . $m[3] . '>';
	}, $html );
}, 12 );

/** Render a list of "Label | url" lines as <li><a>…</a></li>. */
function ricoman_render_links( $key ) {
	$out = '';
	foreach ( ricoman_opt_lines( $key ) as $parts ) {
		$label = isset( $parts[0] ) ? $parts[0] : '';
		$url   = isset( $parts[1] ) ? $parts[1] : '#';
		if ( '' === $label ) {
			continue;
		}
		$out .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
	}
	return $out;
}

/* ------------------------------------------------------------------ header */
add_shortcode( 'ricoman_header', function () {
	$logo  = ricoman_opt( 'brand_logo' );
	$brand = $logo
		? '<a class="brand" href="' . esc_url( home_url( '/' ) ) . '"><img src="' . esc_url( $logo ) . '" alt="Ricoman" class="rm-logo"></a>'
		: '<a class="brand" href="' . esc_url( home_url( '/' ) ) . '">RICOMAN<sup>&reg;</sup></a>';

	// Mega panel (Products). Auto-list the real product categories so the menu
	// matches the /products/ structure — each links straight to its category
	// page. (Replaces a stale manual list whose links all pointed at /products/.)
	// Manual display order first (set per category), then alphabetical.
	$cats  = '';
	$ctax  = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
	$terms = get_terms( array( 'taxonomy' => $ctax, 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC' ) );
	if ( ! is_wp_error( $terms ) && $terms && function_exists( 'ricoman_cat_sort_terms' ) ) {
		$terms = ricoman_cat_sort_terms( $terms, 'name' );
	}
	if ( ! is_wp_error( $terms ) && $terms ) {
		foreach ( $terms as $t ) {
			$link  = get_term_link( $t );
			$cats .= '<li><a href="' . esc_url( is_wp_error( $link ) ? '#' : $link ) . '">' . esc_html( $t->name ) . '</a></li>';
		}
	}
	$cols = '';
	foreach ( ricoman_opt_lines( 'mega_collections' ) as $p ) {
		$name = isset( $p[0] ) ? $p[0] : '';
		$sub  = isset( $p[1] ) ? $p[1] : '';
		$url  = isset( $p[2] ) ? $p[2] : '#';
		$cols .= '<li><a href="' . esc_url( $url ) . '"><span class="rm-mega-name">' . esc_html( $name ) . '</span>' . ( $sub ? '<span class="rm-mega-sub">' . esc_html( $sub ) . '</span>' : '' ) . '</a></li>';
	}
	$apps = '';
	foreach ( ricoman_opt_lines( 'mega_apps' ) as $p ) {
		$apps .= '<li><a href="' . esc_url( isset( $p[1] ) ? $p[1] : '#' ) . '">' . esc_html( $p[0] ) . '</a></li>';
	}
	$card = function ( $img, $title, $url ) {
		$style = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
		return '<a class="rm-mega-card' . ( $img ? '' : ' rm-mega-card--plain' ) . '" href="' . esc_url( $url ) . '"' . $style . '><span class="rm-mega-card-t">' . esc_html( $title ) . '</span><span class="rm-mega-card-m">view more &rarr;</span></a>';
	};
	$mega = '<div class="rm-mega"><div class="rm-mega-inner">'
		. '<a class="rm-mega-head" href="' . esc_url( ricoman_opt( 'mega_heading_url' ) ) . '">' . esc_html( ricoman_opt( 'mega_heading' ) ) . '</a>'
		. '<div class="rm-mega-grid"><div class="rm-mega-cols">'
		. '<div class="rm-mega-col"><a class="rm-mega-h rm-mega-h-link" href="' . esc_url( ricoman_opt( 'mega_heading_url' ) ) . '">Categories</a><ul>' . $cats . '</ul></div>'
		. '<div class="rm-mega-col"><p class="rm-mega-h">Collections</p><ul class="rm-mega-coll">' . $cols . '</ul></div>'
		. '<div class="rm-mega-col"><p class="rm-mega-h">By Application</p><ul>' . $apps . '</ul></div>'
		. '</div><div class="rm-mega-cards">'
		. $card( ricoman_opt( 'mega_card1_img' ), ricoman_opt( 'mega_card1_title' ), ricoman_opt( 'mega_card1_url' ) )
		. $card( ricoman_opt( 'mega_card2_img' ), ricoman_opt( 'mega_card2_title' ), ricoman_opt( 'mega_card2_url' ) )
		. '</div></div></div></div>';

	// Top nav.
	$items = '';
	foreach ( ricoman_opt_lines( 'nav_primary' ) as $p ) {
		$label = isset( $p[0] ) ? $p[0] : '';
		$url   = isset( $p[1] ) ? $p[1] : '#';
		if ( '' === $label ) {
			continue;
		}
		if ( '#products' === $url ) {
			$items .= '<li class="rm-has-mega"><a href="' . esc_url( ricoman_opt( 'mega_heading_url' ) ) . '">' . esc_html( $label ) . '</a>' . $mega . '</li>';
		} else {
			$items .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
		}
	}

	// Search + login.
	$search = '';
	if ( '1' === (string) ricoman_opt( 'show_search' ) ) {
		$search = '<form class="rm-search" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"><span class="rm-search-i" aria-hidden="true">&#9906;</span><input type="search" name="s" placeholder="Search" aria-label="Search"></form>';
	}
	// My Project link with a live count (server count for logged-in, JS updates it).
	$mp_url   = function_exists( 'ricoman_my_project_url' ) ? ricoman_my_project_url() : home_url( '/my-project/' );
	$mp_count = ( is_user_logged_in() && function_exists( 'ricoman_active_project_count' ) ) ? (int) ricoman_active_project_count() : 0;
	$myproj   = '<a class="rm-myproj" href="' . esc_url( $mp_url ) . '">' . esc_html__( 'My Project', 'ricoman' )
		. ' <span class="ricoman-mp-count" data-mp-count data-empty="' . ( $mp_count > 0 ? 'false' : 'true' ) . '">' . $mp_count . '</span></a>';

	// Account: name + logout when signed in, otherwise the login link.
	if ( is_user_logged_in() ) {
		$u     = wp_get_current_user();
		$nm    = $u->first_name ? $u->first_name : $u->display_name;
		$login = '<a class="rm-login" href="' . esc_url( $mp_url ) . '">' . esc_html( $nm ) . '</a>'
			. '<a class="rm-logout" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Log out', 'ricoman' ) . '</a>';
	} else {
		$login_label = ricoman_opt( 'login_label' );
		$login_label = '' !== trim( (string) $login_label ) ? $login_label : __( 'Login', 'ricoman' );
		$login_url   = ricoman_opt( 'login_url' );
		if ( '' === trim( (string) $login_url ) ) {
			$login_url = wp_login_url();
		}
		$login = '<a class="rm-login" href="' . esc_url( $login_url ) . '">' . esc_html( $login_label ) . '</a>';
	}

	// Critical mega-menu CSS inlined so the correct layout shows even if an old
	// cached stylesheet is still being served (overrides with !important).
	$crit = '<style id="rm-mega-crit">'
		. '.rm-mega-head{font-size:1.25rem!important}'
		. '.rm-mega-col ul{display:flex!important;flex-direction:column!important;gap:.62em!important}'
		. '.rm-mega-col ul.rm-mega-coll{gap:1em!important}'
		. '.rm-mega-col li{margin:0!important;line-height:1.35!important}'
		. '.rm-mega-col ul a{font-size:.9rem!important;font-weight:300!important;line-height:1.35!important;display:block!important;padding:0!important}'
		. '.rm-mega-col ul.rm-mega-coll a{display:flex!important;flex-direction:column!important;gap:2px!important}'
		. '.rm-mega-name{font-weight:500!important}'
		. '.rm-mega-sub{font-size:.76rem!important;font-weight:300!important}'
		. '.rm-mega-col h4,.rm-mega-col .rm-mega-h{font-size:.95rem!important;margin:0 0 .9em!important;font-family:Poppins!important;font-weight:700!important}'
		// Mobile: keep only the Categories column, shown as a compact two-column
		// list so the lower nav items (About, Flow Designer, Downloads) stay reachable.
		. '@media(max-width:1024px){'
		. '.rm-mega-cols{grid-template-columns:1fr!important}'
		. '.rm-mega-cols .rm-mega-col:nth-child(2),.rm-mega-cols .rm-mega-col:nth-child(3){display:none!important}'
		. '.rm-mega-col h4,.rm-mega-col .rm-mega-h,.rm-mega-col ul a,.rm-mega-name{color:#fff!important}'
		. '.rm-mega{padding-top:6px!important}'
		. '.rm-mega-head{font-size:.95rem!important;margin-bottom:.5em!important}'
		. '.rm-mega-col .rm-mega-h{font-size:.8rem!important;margin:0 0 .5em!important}'
		. '.rm-mega-col ul{display:grid!important;grid-template-columns:1fr 1fr!important;gap:.45em 16px!important}'
		. '.rm-mega-col ul a{font-size:.82rem!important;line-height:1.25!important}'
		. '}'
		. '.ricoman-site-header.rm-stuck{background:#000!important;box-shadow:0 6px 26px rgba(0,0,0,.22)!important}'
		. '</style>';

	return $crit . '<div class="site ricoman-site-header"><div class="wrap nav">'
		. $brand
		. '<input type="checkbox" id="rm-navtoggle" class="rm-navtoggle" hidden>'
		. '<label for="rm-navtoggle" class="rm-burger" aria-label="Menu"><span></span><span></span><span></span></label>'
		. '<ul class="rm-nav">' . $items . '</ul>'
		. '<div class="rm-nav-right">' . $search . $myproj . $login . '</div>'
		. '</div></div>';
} );

/* ------------------------------------------------------------------ footer */
add_shortcode( 'ricoman_footer', function () {
	$logo  = ricoman_opt( 'brand_logo' );
	$brand = $logo
		? '<img src="' . esc_url( $logo ) . '" alt="Ricoman" class="rm-logo">'
		: 'RICOMAN<sup style="font-size:.5em">&reg;</sup>';

	// Contact block.
	$addr  = nl2br( esc_html( ricoman_opt( 'foot_address' ) ) );
	$phone = ricoman_opt( 'foot_phone' );
	$email = ricoman_opt( 'foot_email' );
	$hours = '';
	foreach ( preg_split( '/\r\n|\r|\n/', (string) ricoman_opt( 'foot_hours' ) ) as $h ) {
		$h = trim( $h );
		if ( '' !== $h ) {
			$hours .= '<p>' . esc_html( $h ) . '</p>';
		}
	}

	// Socials.
	$icons = array(
		'soc_instagram' => 'Instagram',
		'soc_facebook'  => 'Facebook',
		'soc_linkedin'  => 'LinkedIn',
		'soc_youtube'   => 'YouTube',
	);
	$social = '';
	foreach ( $icons as $k => $name ) {
		$url = ricoman_opt( $k );
		if ( '' !== trim( (string) $url ) ) {
			$social .= '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $name ) . '" target="_blank" rel="noopener">' . esc_html( $name[0] ) . '</a>';
		}
	}

	$phone_html = $phone ? '<p class="rm-foot-phone"><a href="tel:' . esc_attr( preg_replace( '/\s+/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a></p>' : '';
	$email_html = $email ? '<p class="rm-foot-email"><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>' : '';

	$legal = '';
	foreach ( ricoman_opt_lines( 'foot_legal' ) as $p ) {
		$legal .= '<a href="' . esc_url( isset( $p[1] ) ? $p[1] : '#' ) . '">' . esc_html( $p[0] ) . '</a>';
	}

	return '<div class="site ricoman-site-footer"><div class="wrap">'
		. '<div class="rm-foot-grid">'
		. '<div class="rm-foot-brandcol"><div class="brand">' . $brand . '</div>'
		. '<h2 class="rm-foot-h">Contact Us</h2><address class="rm-foot-addr">' . $addr . '</address>'
		. $phone_html . $email_html
		. '<h2 class="rm-foot-h">Business Times</h2><div class="rm-foot-hours">' . $hours . '</div>'
		. ( $social ? '<h2 class="rm-foot-h">Follow Us</h2><div class="rm-foot-social">' . $social . '</div>' : '' )
		. '</div>'
		. '<div class="rm-foot-col"><h2 class="rm-foot-h">About Us</h2><ul>' . ricoman_render_links( 'foot_col_about' ) . '</ul>'
		. '<h2 class="rm-foot-h">Products</h2><ul>' . ricoman_render_links( 'foot_col_products' ) . '</ul></div>'
		. '<div class="rm-foot-col"><h2 class="rm-foot-h">Projects</h2><ul>' . ricoman_render_links( 'foot_col_projects' ) . '</ul>'
		. '<h2 class="rm-foot-h">Other Links</h2><ul>' . ricoman_render_links( 'foot_col_other' ) . '</ul></div>'
		. '</div>'
		. ( function_exists( 'ricoman_newsletter_form' ) ? '<div class="rm-foot-news">' . ricoman_newsletter_form( array( 'source' => 'Footer', 'compact' => '1' ) ) . '</div>' : '' )
		. '<div class="fbar"><span>&copy; ' . esc_html( gmdate( 'Y' ) ) . ' Ricoman Ltd &middot; Made in Britain</span><span class="rm-foot-legal">' . $legal . '</span></div>'
		. '</div></div>';
} );

/* ------------------------------------------------ Flow+ rolling banner ----- */
add_shortcode( 'ricoman_flow_banner', function () {
	$slides = array();
	foreach ( ricoman_opt_lines( 'flow_banner' ) as $p ) {
		$img = isset( $p[0] ) ? $p[0] : '';
		if ( '' === $img ) {
			continue;
		}
		$slides[] = array(
			'img'    => $img,
			'eyebrow'=> isset( $p[1] ) ? $p[1] : '',
			'head'   => isset( $p[2] ) ? $p[2] : '',
			'sub'    => isset( $p[3] ) ? $p[3] : '',
			'blabel' => isset( $p[4] ) ? $p[4] : '',
			'burl'   => isset( $p[5] ) ? $p[5] : '#',
		);
	}
	if ( ! $slides ) {
		return '';
	}
	$out = '<div class="rm-banner" data-rotate="6000">';
	foreach ( $slides as $i => $s ) {
		$btn = ( '' !== trim( $s['blabel'] ) ) ? '<a class="btn btn-line" href="' . esc_url( $s['burl'] ) . '">' . esc_html( $s['blabel'] ) . '</a>' : '';
		$out .= '<div class="rm-banner-slide' . ( 0 === $i ? ' on' : '' ) . '" style="background-image:url(' . esc_url( $s['img'] ) . ')"><div class="rm-banner-scrim"></div><div class="rm-banner-inner">'
			. ( $s['eyebrow'] ? '<p class="rm-eyebrow">' . esc_html( $s['eyebrow'] ) . '</p>' : '' )
			. ( $s['head'] ? '<h1 class="rm-banner-head">' . esc_html( $s['head'] ) . '</h1>' : '' )
			. ( $s['sub'] ? '<p class="rm-banner-sub">' . esc_html( $s['sub'] ) . '</p>' : '' )
			. $btn . '</div></div>';
	}
	$dots = '';
	if ( count( $slides ) > 1 ) {
		for ( $i = 0; $i < count( $slides ); $i++ ) {
			$dots .= '<button type="button" class="rm-banner-dot' . ( 0 === $i ? ' on' : '' ) . '" data-i="' . $i . '" aria-label="Slide ' . ( $i + 1 ) . '"></button>';
		}
		$dots = '<div class="rm-banner-dots">' . $dots . '</div>';
	}
	$out .= $dots . '</div>';
	$out .= '<script>(function(){var b=document.currentScript.previousElementSibling;if(!b||b.dataset.rmInit)return;b.dataset.rmInit=1;var s=b.querySelectorAll(".rm-banner-slide"),d=b.querySelectorAll(".rm-banner-dot"),n=s.length,cur=0,t=parseInt(b.dataset.rotate,10)||6000,timer;if(n<2)return;function go(i){s[cur].classList.remove("on");d[cur]&&d[cur].classList.remove("on");cur=(i+n)%n;s[cur].classList.add("on");d[cur]&&d[cur].classList.add("on");}function start(){timer=setInterval(function(){go(cur+1);},t);}d.forEach(function(x){x.addEventListener("click",function(){clearInterval(timer);go(parseInt(x.dataset.i,10));start();});});start();})();</script>';
	return $out;
} );
