<?php
/**
 * Theme-level full-page cache.
 *
 * A theme loads inside WordPress, so it can't beat the core-boot floor (~0.25s)
 * — but it CAN skip the heavy template render (header + mega menu + section
 * assembly + footer) by serving a previously-captured copy of the whole page at
 * template_redirect. For anonymous GET requests this takes server time from
 * ~0.45s to ~0.25s, and the emitted Cache-Control lets Cloudflare / the browser
 * cache the HTML too (→ tens of ms once warm).
 *
 * Deliberately conservative: only anonymous, GET, no query string, no session
 * cookies, not the dynamic account/checkout pages. Auto-busts on any edit + a 1h
 * TTL. Disable with: define('RICOMAN_PAGE_CACHE', false) or the filters below.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Master on/off. */
function ricoman_pagecache_on() {
	if ( defined( 'RICOMAN_PAGE_CACHE' ) && ! RICOMAN_PAGE_CACHE ) {
		return false;
	}
	return (bool) apply_filters( 'ricoman_pagecache_on', true );
}

/** Cache key for the current URL (version-stamped so edits invalidate it). */
function ricoman_pagecache_key() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';
	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/', PHP_URL_PATH );
	$ver  = get_option( 'rm_pagecache_ver', '1' ) . '_' . ( function_exists( 'ricoman_products_ver' ) ? ricoman_products_ver() : '1' );
	return 'rm_pc_' . md5( $host . '|' . $path ) . '_' . $ver;
}

/** Is this request safe to serve/store from the page cache? */
function ricoman_pagecache_eligible() {
	if ( ! ricoman_pagecache_on() ) {
		return false;
	}
	if ( is_user_logged_in() || is_admin() ) {
		return false;
	}
	if ( 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) {
		return false;
	}
	if ( ! empty( $_GET ) ) {
		return false; // search / filtered / UTM / param'd URLs vary — don't cache.
	}
	if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return false;
	}
	if ( is_search() || is_404() || is_feed() || is_trackback() || is_preview() || post_password_required() ) {
		return false;
	}
	// Any session / auth / commerce cookie → treat as personalised, skip.
	foreach ( array_keys( (array) $_COOKIE ) as $ck ) {
		if ( false !== stripos( $ck, 'wordpress_logged_in' ) || false !== stripos( $ck, 'comment_author' ) || 0 === stripos( $ck, 'woocommerce_' ) || 0 === stripos( $ck, 'wp-postpass' ) ) {
			return false;
		}
	}
	// Dynamic / per-user pages never cache.
	$exclude = apply_filters( 'ricoman_pagecache_exclude_slugs', array(
		'my-project', 'create-project', 'dashboard', 'thank-you', 'registration', 'login', 'checkout', 'cart', 'downloads',
	) );
	if ( is_singular() ) {
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		if ( in_array( $slug, (array) $exclude, true ) ) {
			return false;
		}
	}
	return (bool) apply_filters( 'ricoman_pagecache_eligible', true );
}

/** Serve a cached page (early) or start capturing this one. */
add_action( 'template_redirect', function () {
	if ( ! ricoman_pagecache_eligible() ) {
		return;
	}
	$key    = ricoman_pagecache_key();
	$cached = get_transient( $key );
	$ttl    = (int) apply_filters( 'ricoman_pagecache_ttl', HOUR_IN_SECONDS );

	if ( is_string( $cached ) && '' !== $cached ) {
		header( 'X-Ricoman-Cache: HIT' );
		header( 'Cache-Control: public, max-age=0, s-maxage=' . max( 60, (int) apply_filters( 'ricoman_pagecache_smaxage', 3600 ) ) );
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput — stored full-page HTML.
		exit;
	}

	header( 'X-Ricoman-Cache: MISS' );
	header( 'Cache-Control: public, max-age=0, s-maxage=' . max( 60, (int) apply_filters( 'ricoman_pagecache_smaxage', 3600 ) ) );
	ob_start( function ( $html ) use ( $key, $ttl ) {
		// Only store a complete, successful HTML document.
		if ( is_string( $html ) && strlen( $html ) > 500
			&& 200 === (int) http_response_code()
			&& false !== stripos( $html, '</html>' )
			&& false === stripos( $html, 'X-Ricoman-Cache: ' ) ) {
			set_transient( $key, $html, $ttl );
		}
		return $html;
	} );
}, 0 );

/* ----------------------------------------------------------- invalidation */

/** Bump the page-cache version so every page rebuilds on the next hit. */
function ricoman_pagecache_flush() {
	update_option( 'rm_pagecache_ver', (string) time(), false );
}
add_action( 'save_post', 'ricoman_pagecache_flush' );
add_action( 'deleted_post', 'ricoman_pagecache_flush' );
add_action( 'customize_save_after', 'ricoman_pagecache_flush' );
add_action( 'switch_theme', 'ricoman_pagecache_flush' );
// Menus / widgets / site options edited from the Ricoman screens.
add_action( 'wp_update_nav_menu', 'ricoman_pagecache_flush' );
add_action( 'updated_option', function ( $option ) {
	if ( is_string( $option ) && ( 0 === strpos( $option, 'ricoman_' ) || 'rm_catalogue_cache' === $option || 'show_on_front' === $option ) ) {
		ricoman_pagecache_flush();
	}
} );
