<?php
/**
 * One-shot OPcache flush for staging deploys.
 *
 * After an FTP deploy the new theme .php files are on disk, but PHP-FPM's
 * OPcache keeps serving the OLD compiled bytecode until it's restarted — so
 * code fixes appear to "not work". This file is standalone and (being new on
 * each first hit) is compiled fresh, so opcache_reset() here clears ALL cached
 * bytecode. The next request then recompiles every file from the new code.
 *
 * Usage: visit  <staging-domain>/wp-content/themes/ricoman/opcache-flush.php?key=ricoman-flush-2026
 * straight after a deploy. No Plesk / SSH needed.
 *
 * Staging-only convenience. Remove (or change the key) before launch.
 */

$key = isset( $_GET['key'] ) ? (string) $_GET['key'] : '';
if ( ! hash_equals( 'ricoman-flush-2026', $key ) ) {
	http_response_code( 403 );
	exit( 'Forbidden' );
}

header( 'Content-Type: text/plain; charset=utf-8' );

if ( ! function_exists( 'opcache_reset' ) ) {
	echo "opcache_reset() is not available — OPcache is disabled here, or you must restart PHP-FPM in Plesk (Tools & Settings -> Services Management).\n";
	exit;
}

$ok = opcache_reset();
echo $ok
	? "OPcache flushed. The latest PHP is now live — reload your page.\n"
	: "opcache_reset() returned false (it may already be clearing, or another process holds it). Try once more, or restart PHP-FPM.\n";

if ( function_exists( 'opcache_get_status' ) ) {
	$s = @opcache_get_status( false );
	if ( is_array( $s ) && isset( $s['opcache_enabled'] ) ) {
		echo 'OPcache enabled: ' . ( $s['opcache_enabled'] ? 'yes' : 'no' ) . "\n";
	}
}
