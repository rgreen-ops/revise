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
		} else {
			// Default to the known live site when the host isn't a "staging." subdomain.
			$live = 'https://ricoman.com';
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

/**
 * Normalise a migrated image URL so it actually resolves.
 *
 * The old-site export left many image values as ABSOLUTE URLs on the source
 * domain (e.g. https://ricoman.com/wp-content/uploads/alluploadedfile/foo-1024x1024.png)
 * referencing a sub-size that was never generated. Two problems: the host is the
 * live site (not this one) and the -WxH sub-size file doesn't exist.
 *
 * This maps any /wp-content/uploads/… URL (whatever its host) onto THIS site's
 * uploads, and if the requested file is missing on disk it tries the original
 * (size suffix stripped). Whatever it resolves to is then run through the
 * live-origin fallback so genuinely-missing files still display.
 *
 * @param string $url
 * @return string
 */
function ricoman_norm_img_url( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return $url;
	}
	static $cache = array();
	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}
	$up      = wp_get_upload_dir();
	$baseurl = isset( $up['baseurl'] ) ? $up['baseurl'] : '';
	$basedir = isset( $up['basedir'] ) ? $up['basedir'] : '';
	$path    = $baseurl ? wp_parse_url( $baseurl, PHP_URL_PATH ) : ''; // /wp-content/uploads
	if ( ! $path ) {
		return $cache[ $url ] = $url;
	}
	$pos = strpos( $url, $path );
	if ( false === $pos ) {
		return $cache[ $url ] = $url; // not an uploads URL — leave alone.
	}
	$rel     = substr( $url, $pos + strlen( $path ) ); // /alluploadedfile/foo-1024x1024.png[?x]
	$relpath = preg_replace( '/[?#].*$/', '', $rel );
	$out     = $baseurl . $rel; // re-host onto THIS site.
	if ( ! file_exists( $basedir . $relpath ) ) {
		// Sub-size missing — try the original (strip a trailing -WxH).
		$orig = preg_replace( '/-\d+x\d+(\.[A-Za-z0-9]+)$/', '$1', $relpath );
		if ( $orig !== $relpath && file_exists( $basedir . $orig ) ) {
			$out = $baseurl . $orig;
		} else {
			// Still missing locally — let the live-origin fallback handle it.
			$out = ricoman_img_fallback( $baseurl . $rel );
		}
	}
	return $cache[ $url ] = $out;
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

/* ============================================================ *
 * Pull missing image files from the live origin into local uploads.
 *
 * For every image attachment whose file is missing on disk, download the same
 * file from the live origin (ricoman_live_origin) and save it to its expected
 * local path, then regenerate its sub-sizes. This permanently re-hosts images
 * that were referenced by the migration but never copied across — so the new
 * site stops depending on the live-origin fallback (and broken images vanish).
 * Batched, resumable, capability-gated.
 * ============================================================ */
add_action( 'admin_menu', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_submenu_page(
		'ricoman-hub',
		__( 'Pull Missing Images', 'ricoman' ),
		__( 'Pull Missing Images', 'ricoman' ),
		'manage_options',
		'ricoman-pull-images',
		'ricoman_pull_images_page'
	);
}, 26 );

function ricoman_pull_images_page() {
	$origin = function_exists( 'ricoman_live_origin' ) ? ricoman_live_origin() : '';
	echo '<div class="wrap"><h1>' . esc_html__( 'Pull Missing Images', 'ricoman' ) . '</h1>';
	echo '<p>' . esc_html__( 'Finds every image whose file is missing on this server and downloads the matching file from the live site, saving it here permanently. Run after a migration to fix broken product/gallery images without copying the whole uploads folder. Safe to re-run; it only fetches what is missing.', 'ricoman' ) . '</p>';
	if ( ! $origin ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'No live origin is set, so there is nowhere to pull images from. Define RICOMAN_LIVE_ORIGIN or the ricoman_live_origin option (e.g. https://ricoman.com).', 'ricoman' ) . '</p></div></div>';
		return;
	}
	echo '<p>' . sprintf( esc_html__( 'Pulling from: %s', 'ricoman' ), '<code>' . esc_html( $origin ) . '</code>' ) . '</p>';
	echo '<p><button class="button button-primary" id="rm-pull-go">' . esc_html__( 'Start / resume pulling', 'ricoman' ) . '</button> <span id="rm-pull-out" style="margin-left:10px"></span></p>';
	echo '<div id="rm-pull-bar-wrap" style="display:none;max-width:560px;background:#e2e4e7;border-radius:6px;overflow:hidden;height:18px;margin:8px 0"><div id="rm-pull-bar" style="height:100%;width:0;background:#2271b1"></div></div>';
	$nonce = wp_create_nonce( 'rm_pull_images' );
	?>
	<script>
	(function(){
		var go=document.getElementById('rm-pull-go'),out=document.getElementById('rm-pull-out');
		var barW=document.getElementById('rm-pull-bar-wrap'),bar=document.getElementById('rm-pull-bar');
		var nonce=<?php echo wp_json_encode( $nonce ); ?>, ajax=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var running=false,pulled=0,failed=0;
		function batch(offset){
			var body=new URLSearchParams({action:'ricoman_pull_images',nonce:nonce,offset:offset});
			fetch(ajax,{method:'POST',body:body,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
				if(!j||!j.success){ out.textContent='Error — check logs.'; running=false; go.disabled=false; return; }
				var d=j.data; pulled+=d.pulled; failed+=d.failed;
				barW.style.display='block';
				var pct=d.total?Math.min(100,Math.round(d.done/d.total*100)):100;
				bar.style.width=pct+'%';
				out.textContent='Scanned '+d.done.toLocaleString()+' of '+d.total.toLocaleString()+' — pulled '+pulled.toLocaleString()+', failed '+failed.toLocaleString()+'…';
				if(d.next!==null){ setTimeout(function(){batch(d.next);}, 120); }
				else { out.textContent='Done — pulled '+pulled.toLocaleString()+' missing image(s), '+failed.toLocaleString()+' could not be fetched.'; running=false; go.disabled=false; }
			}).catch(function(){ out.textContent='Network error — click to resume.'; running=false; go.disabled=false; });
		}
		go.addEventListener('click',function(){ if(running)return; running=true; go.disabled=true; pulled=0; failed=0; out.textContent='Starting…'; batch(0); });
	})();
	</script>
	</div>
	<?php
}

add_action( 'wp_ajax_ricoman_pull_images', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_pull_images', 'nonce', false ) ) {
		wp_send_json_error();
	}
	$origin = function_exists( 'ricoman_live_origin' ) ? ricoman_live_origin() : '';
	if ( ! $origin ) {
		wp_send_json_error();
	}
	global $wpdb;
	$batch  = 12;
	$offset = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
	$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status<>'trash'" );
	$ids   = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status<>'trash' ORDER BY ID ASC LIMIT %d OFFSET %d",
		$batch, $offset
	) );

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$pulled = 0;
	$failed = 0;
	foreach ( $ids as $id ) {
		$id   = (int) $id;
		$path = get_attached_file( $id );
		if ( ! $path ) {
			continue;
		}
		// Only images, and only those actually missing on disk.
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tiff' ), true ) ) {
			continue;
		}
		if ( file_exists( $path ) ) {
			continue;
		}
		// wp_get_attachment_url is filtered to the live origin when the file is
		// missing, giving us the source to download.
		$src = wp_get_attachment_url( $id );
		if ( ! $src || strtolower( (string) wp_parse_url( $src, PHP_URL_HOST ) ) === $home_host ) {
			$failed++;
			continue;
		}
		$tmp = download_url( $src, 30 );
		if ( is_wp_error( $tmp ) ) {
			$failed++;
			continue;
		}
		wp_mkdir_p( dirname( $path ) );
		if ( @copy( $tmp, $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $tmp ); // phpcs:ignore
			$meta = wp_generate_attachment_metadata( $id, $path );
			if ( $meta ) {
				wp_update_attachment_metadata( $id, $meta );
			}
			$pulled++;
		} else {
			@unlink( $tmp ); // phpcs:ignore
			$failed++;
		}
	}

	$done = $offset + count( $ids );
	wp_send_json_success( array(
		'total'  => $total,
		'done'   => $done,
		'pulled' => $pulled,
		'failed' => $failed,
		'next'   => count( $ids ) < $batch ? null : $done,
	) );
} );
