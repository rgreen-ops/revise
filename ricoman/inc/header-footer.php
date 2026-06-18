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

	// Mega panel (Products).
	$cats = '';
	foreach ( ricoman_opt_lines( 'mega_categories' ) as $p ) {
		$cats .= '<li><a href="' . esc_url( isset( $p[1] ) ? $p[1] : '#' ) . '">' . esc_html( $p[0] ) . '</a></li>';
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
		. '<div class="rm-mega-col"><h4>Categories</h4><ul>' . $cats . '</ul></div>'
		. '<div class="rm-mega-col"><h4>Collections</h4><ul class="rm-mega-coll">' . $cols . '</ul></div>'
		. '<div class="rm-mega-col"><h4>By Application</h4><ul>' . $apps . '</ul></div>'
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
			$items .= '<li class="rm-has-mega"><a href="' . esc_url( ricoman_opt( 'mega_heading_url' ) ) . '">' . esc_html( $label ) . ' <span class="rm-caret" aria-hidden="true">&#9662;</span></a>' . $mega . '</li>';
		} else {
			$items .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
		}
	}

	// Search + login.
	$search = '';
	if ( '1' === (string) ricoman_opt( 'show_search' ) ) {
		$search = '<form class="rm-search" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"><span class="rm-search-i" aria-hidden="true">&#9906;</span><input type="search" name="s" placeholder="Search" aria-label="Search"></form>';
	}
	$login       = '';
	$login_label = ricoman_opt( 'login_label' );
	if ( '' !== trim( (string) $login_label ) ) {
		$login_url = ricoman_opt( 'login_url' );
		if ( '' === trim( (string) $login_url ) ) {
			$login_url = wp_login_url();
		}
		$login = '<a class="rm-login" href="' . esc_url( $login_url ) . '">' . esc_html( $login_label ) . '</a>';
	}

	return '<header class="site ricoman-site-header"><div class="wrap nav">'
		. $brand
		. '<input type="checkbox" id="rm-navtoggle" class="rm-navtoggle" hidden>'
		. '<label for="rm-navtoggle" class="rm-burger" aria-label="Menu"><span></span><span></span><span></span></label>'
		. '<ul class="rm-nav">' . $items . '</ul>'
		. '<div class="rm-nav-right">' . $search . $login . '</div>'
		. '</div></header>';
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

	return '<footer class="site ricoman-site-footer"><div class="wrap">'
		. '<div class="rm-foot-grid">'
		. '<div class="rm-foot-brandcol"><div class="brand">' . $brand . '</div>'
		. '<h4>Contact Us</h4><address class="rm-foot-addr">' . $addr . '</address>'
		. $phone_html . $email_html
		. '<h4>Business Times</h4><div class="rm-foot-hours">' . $hours . '</div>'
		. ( $social ? '<h4>Follow Us</h4><div class="rm-foot-social">' . $social . '</div>' : '' )
		. '</div>'
		. '<div class="rm-foot-col"><h4>About Us</h4><ul>' . ricoman_render_links( 'foot_col_about' ) . '</ul>'
		. '<h4>Products</h4><ul>' . ricoman_render_links( 'foot_col_products' ) . '</ul></div>'
		. '<div class="rm-foot-col"><h4>Projects</h4><ul>' . ricoman_render_links( 'foot_col_projects' ) . '</ul>'
		. '<h4>Other Links</h4><ul>' . ricoman_render_links( 'foot_col_other' ) . '</ul></div>'
		. '</div>'
		. '<div class="fbar"><span>&copy; ' . esc_html( gmdate( 'Y' ) ) . ' Ricoman Ltd &middot; Made in Britain</span><span class="rm-foot-legal">' . $legal . '</span></div>'
		. '</div></footer>';
} );
