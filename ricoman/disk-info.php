<?php
/**
 * Read-only disk diagnostic for staging. Tells us what's ACTUALLY using the disk
 * instead of guessing. Visit:
 *   <staging>/wp-content/themes/ricoman/disk-info.php?key=ricoman-flush-2026
 * Staging-only. Safe (reads sizes, deletes nothing). Remove before launch.
 */

$key = isset( $_GET['key'] ) ? (string) $_GET['key'] : '';
if ( ! hash_equals( 'ricoman-flush-2026', $key ) ) {
	http_response_code( 403 );
	exit( 'Forbidden' );
}
header( 'Content-Type: text/plain; charset=utf-8' );

function rm_fmt( $b ) {
	$u = array( 'B', 'KB', 'MB', 'GB', 'TB' );
	$i = 0;
	$b = (float) $b;
	while ( $b >= 1024 && $i < 4 ) { $b /= 1024; $i++; }
	return round( $b, 2 ) . ' ' . $u[ $i ];
}

// Locate the uploads dir without a full WP load if possible.
$uploads = __DIR__; // theme dir
$try     = dirname( __DIR__, 2 ) . '/uploads'; // wp-content/uploads
if ( is_dir( $try ) ) { $uploads = $try; }

$free  = @disk_free_space( $uploads );
$total = @disk_total_space( $uploads );

echo "=== DISK ===\n";
if ( $total ) {
	$used = $total - $free;
	echo 'Total: ' . rm_fmt( $total ) . "\n";
	echo 'Used:  ' . rm_fmt( $used ) . '  (' . round( $used / $total * 100, 1 ) . "%)\n";
	echo 'Free:  ' . rm_fmt( $free ) . "\n";
	echo 'Disk full? ' . ( $free < 200 * 1024 * 1024 ? "YES (under 200MB free)" : "no" ) . "\n";
} else {
	echo "disk_free_space/disk_total_space unavailable on this host.\n";
}

echo "\n=== UPLOADS (" . $uploads . ") ===\n";
if ( is_dir( $uploads ) ) {
	$deadline   = microtime( true ) + 8.0; // time budget so it never hangs.
	$total_size = 0;
	$file_count = 0;
	$webp_size  = 0;
	$webp_count = 0;
	$capped     = false;
	try {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $uploads, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( microtime( true ) > $deadline ) { $capped = true; break; }
			if ( ! $f->isFile() ) { continue; }
			$sz          = $f->getSize();
			$total_size += $sz;
			$file_count++;
			if ( false !== stripos( $f->getFilename(), '-rmwebp.webp' ) ) {
				$webp_size += $sz;
				$webp_count++;
			}
		}
	} catch ( Exception $e ) {
		echo 'scan error: ' . $e->getMessage() . "\n";
	}
	echo 'Files scanned: ' . number_format( $file_count ) . ( $capped ? ' (TIME-CAPPED — partial)' : '' ) . "\n";
	echo 'Uploads size:  ' . rm_fmt( $total_size ) . ( $capped ? ' (partial)' : '' ) . "\n";
	echo 'WebP twins:    ' . number_format( $webp_count ) . ' files, ' . rm_fmt( $webp_size ) . "\n";
} else {
	echo "uploads dir not found at expected path.\n";
}

echo "\n(If 'Disk full?' says no, the WebP/missing-image issues were caching/missing\n";
echo " twins — not space — and WebP can be turned back on. If YES, the space is NOT\n";
echo " the media library (cleanup is empty); check Plesk backups next.)\n";
