<?php
/**
 * Theme-native WebP conversion for product imagery.
 *
 * The migrated product photos are stored as PNG/JPEG (often 200-300 KiB each),
 * which dominates page weight and mobile LCP. This module transcodes them to
 * WebP (no third-party plugin), stores the WebP URL on the attachment, and
 * ricoman_pf_imgurl() serves the WebP automatically. Conversion runs only in a
 * resumable admin batch / cron — never during a normal page render.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stored WebP URL for an attachment, or '' if not converted. */
function ricoman_webp_url( $id ) {
	$m = get_post_meta( (int) $id, '_rm_webp', true );
	return ( is_array( $m ) && ! empty( $m['url'] ) ) ? $m['url'] : '';
}

/**
 * Create a WebP copy of an attachment (from its 'large' sub-size, capped at
 * 1024px) and record it on the attachment. Idempotent; safe to re-run.
 *
 * @return bool True when a WebP exists afterwards.
 */
function ricoman_webp_make( $id ) {
	$id = (int) $id;
	if ( $id <= 0 ) {
		return false;
	}
	$done = get_post_meta( $id, '_rm_webp', true );
	if ( is_array( $done ) ) {
		if ( ! empty( $done['url'] ) && ! empty( $done['path'] ) && file_exists( $done['path'] ) ) {
			return true;
		}
		if ( ! empty( $done['skip'] ) ) {
			return false;
		}
	}
	$mime = get_post_mime_type( $id );
	if ( ! in_array( $mime, array( 'image/png', 'image/jpeg', 'image/jpg' ), true ) ) {
		update_post_meta( $id, '_rm_webp', array( 'skip' => 1 ) );
		return false;
	}
	$orig = get_attached_file( $id );
	if ( ! $orig || ! file_exists( $orig ) ) {
		return false;
	}
	$meta    = wp_get_attachment_metadata( $id );
	$dir     = dirname( $orig );
	$srcfile = $orig;
	foreach ( array( 'large', 'medium_large' ) as $s ) {
		if ( ! empty( $meta['sizes'][ $s ]['file'] ) ) {
			$cand = $dir . '/' . $meta['sizes'][ $s ]['file'];
			if ( file_exists( $cand ) ) {
				$srcfile = $cand;
				break;
			}
		}
	}
	$editor = wp_get_image_editor( $srcfile );
	if ( is_wp_error( $editor ) ) {
		return false;
	}
	$size = $editor->get_size();
	if ( ! empty( $size['width'] ) && $size['width'] > 1024 ) {
		$editor->resize( 1024, null, false );
	}
	$editor->set_quality( 78 );
	$target = $dir . '/' . pathinfo( $srcfile, PATHINFO_FILENAME ) . '-rmwebp.webp';
	$saved  = $editor->save( $target, 'image/webp' );
	if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
		return false;
	}
	$up  = wp_get_upload_dir();
	$url = str_replace( trailingslashit( $up['basedir'] ), trailingslashit( $up['baseurl'] ), $saved['path'] );
	update_post_meta( $id, '_rm_webp', array( 'url' => $url, 'path' => $saved['path'] ) );
	return true;
}

/** Count of PNG/JPEG attachments still needing a WebP (for the progress UI). */
function ricoman_webp_stats() {
	global $wpdb;
	$total = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts}
		 WHERE post_type='attachment' AND post_mime_type IN ('image/png','image/jpeg','image/jpg')"
	);
	$done = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_rm_webp'"
	);
	return array( 'total' => $total, 'done' => $done );
}

/* ---- URL-based WebP (for migrated images referenced by URL, not attachment) --
 * Migrated product images live in /wp-content/uploads as raw PNG/JPG URLs (not
 * attachments), so the attachment converter never touches them. These helpers
 * serve a "-rmwebp.webp" twin for any uploads image URL, generating it on demand
 * — but ONLY when there's real free disk space, so a full disk is never made
 * worse; until then the original is served unchanged. */

/** Create a WebP at $dest from $src. Disk-guarded + throttled. Returns bool. */
function ricoman_webp_make_file( $src, $dest ) {
	if ( file_exists( $dest ) ) {
		return true;
	}
	if ( ! file_exists( $src ) ) {
		return false;
	}
	// Disk guard — never attempt to write when space is tight.
	$free = @disk_free_space( dirname( $dest ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( false !== $free && $free < 150 * 1024 * 1024 ) { // need >150MB headroom.
		return false;
	}
	// Per-request throttle so one page can't kick off dozens of conversions.
	static $made = 0;
	if ( $made >= 6 ) {
		return false;
	}
	$editor = wp_get_image_editor( $src );
	if ( is_wp_error( $editor ) ) {
		return false;
	}
	$size = $editor->get_size();
	if ( ! empty( $size['width'] ) && $size['width'] > 1400 ) {
		$editor->resize( 1400, null, false );
	}
	$editor->set_quality( 78 );
	$saved = $editor->save( $dest, 'image/webp' );
	if ( is_wp_error( $saved ) ) {
		return false;
	}
	$made++;
	return true;
}

/** WebP twin URL for an uploads image URL, or '' if not applicable/unavailable. */
function ricoman_webp_for_url( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return '';
	}
	static $cache = array();
	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}
	$up      = wp_get_upload_dir();
	$baseurl = isset( $up['baseurl'] ) ? $up['baseurl'] : '';
	$basedir = isset( $up['basedir'] ) ? $up['basedir'] : '';
	$base    = $baseurl ? wp_parse_url( $baseurl, PHP_URL_PATH ) : '';
	$res     = '';
	$pos     = $base ? strpos( $url, $base ) : false;
	if ( false !== $pos ) {
		$rel     = substr( $url, $pos + strlen( $base ) );
		$relpath = preg_replace( '/[?#].*$/', '', $rel );
		if ( preg_match( '/\.(png|jpe?g)$/i', $relpath ) && false === stripos( $relpath, '-rmwebp' ) ) {
			$file    = $basedir . $relpath;
			$twin    = preg_replace( '/\.(png|jpe?g)$/i', '-rmwebp.webp', $file );
			$twinrel = preg_replace( '/\.(png|jpe?g)$/i', '-rmwebp.webp', $relpath );
			if ( file_exists( $twin ) || ricoman_webp_make_file( $file, $twin ) ) {
				$res = $baseurl . $twinrel;
			}
		}
	}
	return $cache[ $url ] = $res;
}

/** Swap uploads PNG/JPG image URLs in content for their WebP twin (src + srcset). */
add_filter( 'the_content', function ( $html ) {
	if ( is_admin() || ! is_string( $html ) || false === stripos( $html, '<img' ) ) {
		return $html;
	}
	return preg_replace_callback( '#<img\b[^>]*>#i', function ( $m ) {
		return preg_replace_callback( '#(?:https?:)?//[^\s"\'\\\\)]+?\.(?:png|jpe?g)#i', function ( $u ) {
			$w = ricoman_webp_for_url( $u[0] );
			return $w ? $w : $u[0];
		}, $m[0] );
	}, $html );
}, 8 );

/* ------------------------------------------------------------------ admin -- */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Image WebP', 'ricoman' ),
		__( 'Image WebP', 'ricoman' ),
		'manage_options',
		'ricoman-webp',
		'ricoman_webp_page'
	);
}, 40 );

function ricoman_webp_page() {
	$s = ricoman_webp_stats();
	echo '<div class="wrap"><h1>' . esc_html__( 'Convert images to WebP', 'ricoman' ) . '</h1>';
	echo '<p class="description" style="max-width:760px">' . esc_html__( 'Creates lightweight WebP copies of your PNG/JPEG images (product photos especially) and serves them automatically — big drop in page weight and faster mobile load. Runs in small batches; you can leave the page and come back. Originals are kept.', 'ricoman' ) . '</p>';
	echo '<p><strong>' . esc_html( number_format_i18n( $s['done'] ) ) . '</strong> ' . esc_html__( 'processed of', 'ricoman' ) . ' <strong>' . esc_html( number_format_i18n( $s['total'] ) ) . '</strong> ' . esc_html__( 'images.', 'ricoman' ) . '</p>';
	echo '<p><button class="button button-primary" id="rm-webp-go">' . esc_html__( 'Start / resume conversion', 'ricoman' ) . '</button> <span id="rm-webp-out" style="margin-left:10px"></span></p>';
	$nonce = wp_create_nonce( 'rm_webp' );
	?>
	<script>
	(function(){
		var go=document.getElementById('rm-webp-go'), out=document.getElementById('rm-webp-out'), stop=false;
		function batch(off){
			var fd=new FormData();fd.append('action','rm_webp_run');fd.append('nonce','<?php echo esc_js( $nonce ); ?>');fd.append('offset',off);
			fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
				if(!d||!d.success){out.textContent='Stopped (error).';go.disabled=false;return;}
				out.textContent=d.data.done+' / '+d.data.total+' done…';
				if(!stop && d.data.next!==null){batch(d.data.next);}else{out.textContent='Finished: '+d.data.done+' / '+d.data.total+'. Reload to refresh counts.';go.disabled=false;}
			}).catch(function(){out.textContent='Network error — click to resume.';go.disabled=false;});
		}
		go.addEventListener('click',function(){go.disabled=true;stop=false;out.textContent='Working…';batch(0);});
	})();
	</script>
	<?php
	echo '</div>';
}

add_action( 'wp_ajax_rm_webp_run', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_webp', 'nonce', false ) ) {
		wp_send_json_error();
	}
	@set_time_limit( 60 ); // phpcs:ignore
	$offset = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
	$batch  = 15;
	$ids    = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => array( 'image/png', 'image/jpeg', 'image/jpg' ),
		'posts_per_page' => $batch,
		'offset'         => $offset,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	foreach ( $ids as $id ) {
		ricoman_webp_make( (int) $id );
	}
	$s    = ricoman_webp_stats();
	$next = ( count( $ids ) < $batch ) ? null : ( $offset + count( $ids ) );
	wp_send_json_success( array(
		'done'  => $s['done'],
		'total' => $s['total'],
		'next'  => $next,
	) );
} );

/** Nightly: convert a few outstanding images so new uploads/migrations catch up. */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'ricoman_webp_cron' ) ) {
		wp_schedule_event( time() + 900, 'hourly', 'ricoman_webp_cron' );
	}
} );
add_action( 'ricoman_webp_cron', function () {
	$ids = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => array( 'image/png', 'image/jpeg', 'image/jpg' ),
		'posts_per_page' => 20,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => '_rm_webp', 'compare' => 'NOT EXISTS' ) ),
	) );
	foreach ( $ids as $id ) {
		ricoman_webp_make( (int) $id );
	}
} );
