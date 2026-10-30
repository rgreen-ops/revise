<?php
/**
 * Friendly fatal-error page installer.
 *
 * WordPress shows its bare "There has been a critical error on this website"
 * page on a fatal error, and the only supported way to brand it is a drop-in at
 * wp-content/php-error.php. The theme deploy can only write inside the theme
 * folder, so this copies our branded template (inc/php-error-dropin.php) up into
 * wp-content/ — once, and again whenever we change it.
 *
 * It only ever manages a file it owns (identified by the RICOMAN_ERROR_DROPIN
 * marker); a hand-written php-error.php is left untouched.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Install / refresh wp-content/php-error.php from the theme's template. */
function ricoman_install_error_page() {
	if ( ! defined( 'WP_CONTENT_DIR' ) ) {
		return;
	}
	$src = get_theme_file_path( 'inc/php-error-dropin.php' );
	$dst = WP_CONTENT_DIR . '/php-error.php';
	if ( ! is_readable( $src ) ) {
		return;
	}
	$new = file_get_contents( $src );
	if ( false === $new ) {
		return;
	}
	if ( file_exists( $dst ) ) {
		$cur = file_get_contents( $dst );
		// Don't clobber a php-error.php we didn't write.
		if ( false === strpos( (string) $cur, 'RICOMAN_ERROR_DROPIN' ) ) {
			return;
		}
		if ( $cur === $new ) {
			return; // Already up to date.
		}
		if ( ! is_writable( $dst ) ) {
			return;
		}
	} elseif ( ! is_writable( WP_CONTENT_DIR ) ) {
		return;
	}
	@file_put_contents( $dst, $new );
}

add_action( 'after_switch_theme', 'ricoman_install_error_page' );
// Also run on admin load so existing installs pick up template changes after a
// normal theme update (cheap: it no-ops once the file matches).
add_action( 'admin_init', 'ricoman_install_error_page' );
