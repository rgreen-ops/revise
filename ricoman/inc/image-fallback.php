<?php
/**
 * Missing-media fallback (staging stop-gap).
 *
 * The database/content migration brought the Media Library *records* across, but
 * not necessarily the actual files in wp-content/uploads. Where a file is missing
 * locally, this rewrites its URL to the live origin (ricoman.com) so pages and
 * the editor still show the image. It is self-limiting: only URLs whose file is
 * genuinely absent on disk are rewritten, so once the uploads folder is copied
 * over, the fallback stops automatically with no code change.
 *
 * Live origin resolution order:
 *   1. RICOMAN_LIVE_ORIGIN constant (wp-config.php)
 *   2. `ricoman_live_origin` option
 *   3. derived from the site host with a leading "staging." stripped
 * Returns '' (fallback disabled) when that resolves to the current site.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The live site origin to borrow missing images from (no trailing slash), or ''. */
function ricoman_live_origin() {
	static $origin = null;
	if ( null !== $origin ) {
		return $origin;
	}
	$live = '';
	if ( defined( 'RICOMAN_LIVE_ORIGIN' ) ) {
		$live = (string) RICOMAN_LIVE_ORIGIN;
	} elseif ( get_option( 'ricoman_live_origin' ) ) {
		$live = (string) get_option( 'ricoman_live_origin' );
	} else {
		$parts = wp_parse_url( home_url() );
		$host  = isset( $parts['host'] ) ? $parts['host'] : '';
		$bare  = preg_replace( '/^staging\./i', '', $host );
		if ( $bare && $bare !== $host ) {
			$live = 'https://' . $bare;
		}
	}
	$live = $live ? rtrim( $live, '/' ) : '';
	// Disable if it points back at this same site (no separate live origin).
	if ( $live ) {
		$lh = wp_parse_url( $live, PHP_URL_HOST );
		$hh = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $lh && $hh && strtolower( $lh ) === strtolower( $hh ) ) {
			$live = '';
		}
	}
	/** Allow code to override/disable the live origin. */
	$origin = (string) apply_filters( 'ricoman_live_origin', $live );
	return $origin;
}

/**
 * If $url is a local uploads URL whose file is missing on disk, return the same
 * path on the live origin; otherwise return $url unchanged. Cached per request.
 */
function ricoman_img_fallback( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return $url;
	}
	static $cache = array();
	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}
	$out  = $url;
	$live = ricoman_live_origin();
	if ( $live ) {
		$up      = wp_get_upload_dir();
		$baseurl = isset( $up['baseurl'] ) ? $up['baseurl'] : '';
		// Compare ignoring scheme, so http/https differences don't defeat it.
		$norm    = function ( $u ) { return preg_replace( '#^https?:#i', '', $u ); };
		$nbase   = $norm( $baseurl );
		$nurl    = $norm( $url );
		if ( $baseurl && 0 === strpos( $nurl, $nbase ) ) {
			$rel = substr( $nurl, strlen( $nbase ) ); // e.g. /2023/07/file.png
			// Strip any query string before the disk check.
			$relpath = preg_replace( '/[?#].*$/', '', $rel );
			if ( ! file_exists( $up['basedir'] . $relpath ) ) {
				$path = wp_parse_url( $baseurl, PHP_URL_PATH ); // /wp-content/uploads
				$out  = $live . $path . $rel;
			}
		}
	}
	$cache[ $url ] = $out;
	return $out;
}

/* Apply broadly so both the front end and the back-end thumbnails benefit. */
add_filter( 'wp_get_attachment_url', 'ricoman_img_fallback', 20 );
add_filter( 'wp_get_attachment_image_src', function ( $image ) {
	if ( is_array( $image ) && ! empty( $image[0] ) ) {
		$image[0] = ricoman_img_fallback( $image[0] );
	}
	return $image;
}, 20 );
add_filter( 'wp_calculate_image_srcset', function ( $sources ) {
	if ( is_array( $sources ) ) {
		foreach ( $sources as $w => $s ) {
			if ( ! empty( $s['url'] ) ) {
				$sources[ $w ]['url'] = ricoman_img_fallback( $s['url'] );
			}
		}
	}
	return $sources;
}, 20 );
