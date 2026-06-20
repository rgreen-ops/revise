<?php
/**
 * Media Library cleanup toolkit.
 *
 * The old site's product-variant import created a fresh attachment for every
 * variant, so the library has grown to 100k+ images, many byte-for-byte
 * identical. This toolkit, in safe phases, lets the team:
 *   1. INDEX every image with a content hash (sha1)         — read-only.
 *   2. STOP new exact-duplicates from being created          — on upload/import.
 *   3. REPORT duplicates + reclaimable space (dry-run)       — read-only.
 *   4. MERGE duplicates: remap every reference to one canon  — destructive.
 *      image, then trash the rest                              (gated + batched).
 *
 * Everything runs in batches via admin-ajax so it survives a 100k library, and
 * nothing is deleted until you've reviewed the report and confirmed.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** How many attachments to process per AJAX batch. */
function rm_mc_batch() {
	return (int) apply_filters( 'rm_mc_batch_size', 150 );
}

/** Content hash (sha1 of the original file) for an attachment, cached in meta. */
function rm_mc_hash( $att_id, $recompute = false ) {
	if ( ! $recompute ) {
		$h = get_post_meta( $att_id, '_rm_sha1', true );
		if ( $h ) {
			return $h;
		}
	}
	$file = get_attached_file( $att_id );
	if ( ! $file || ! file_exists( $file ) ) {
		update_post_meta( $att_id, '_rm_sha1', 'missing' );
		return 'missing';
	}
	$h = sha1_file( $file );
	if ( $h ) {
		update_post_meta( $att_id, '_rm_sha1', $h );
	}
	return $h ? $h : '';
}

/** The canonical (earliest) attachment ID that shares a hash, if any. */
function rm_mc_canonical_for_hash( $hash, $exclude = 0 ) {
	if ( ! $hash || 'missing' === $hash ) {
		return 0;
	}
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_rm_sha1' AND meta_value = %s AND post_id <> %d ORDER BY post_id ASC LIMIT 1",
		$hash,
		(int) $exclude
	) );
}

/* ------------------------------------------------------------------ *
 * 2. Stop new exact-duplicates: hash every upload and flag matches.
 * ------------------------------------------------------------------ */
add_action( 'add_attachment', function ( $att_id ) {
	if ( ! wp_attachment_is_image( $att_id ) ) {
		return;
	}
	$h = rm_mc_hash( $att_id, true );
	$canon = rm_mc_canonical_for_hash( $h, $att_id );
	if ( $canon ) {
		// Flag only — never auto-delete here, as the uploader/importer may
		// already hold this new ID. The merge tool resolves flagged dupes.
		update_post_meta( $att_id, '_rm_dupe_of', (int) $canon );
	}
} );

/**
 * Dedupe-aware sideload for importers: return an existing attachment with the
 * same content instead of creating a new one. Future image imports should call
 * this rather than media_handle_sideload(), so the library can't re-bloat.
 *
 * @param string $file_path Local path to the image to import.
 * @param int    $parent    Parent post ID (0 for none).
 * @return int Attachment ID (existing or newly created), or 0 on failure.
 */
function ricoman_dedupe_sideload( $file_path, $parent = 0 ) {
	if ( ! $file_path || ! file_exists( $file_path ) ) {
		return 0;
	}
	$hash  = sha1_file( $file_path );
	$canon = rm_mc_canonical_for_hash( $hash, 0 );
	if ( $canon ) {
		return (int) $canon; // reuse the existing identical image.
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_sideload( array(
		'name'     => basename( $file_path ),
		'tmp_name' => $file_path,
	), (int) $parent );
	return is_wp_error( $id ) ? 0 : (int) $id;
}

/* ------------------------------------------------------------------ *
 * Admin page: Media Cleanup (index + report; merge is gated).
 * ------------------------------------------------------------------ */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Media Cleanup', 'ricoman' ),
		__( 'Media Cleanup', 'ricoman' ),
		'manage_options',
		'ricoman-media-cleanup',
		'rm_mc_render_page'
	);
}, 32 );

/** Totals for the dashboard (images, indexed, duplicate groups, reclaimable). */
function rm_mc_stats() {
	global $wpdb;
	$images = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'"
	);
	$indexed = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_rm_sha1' AND meta_value<>'missing'"
	);
	// Duplicate groups + extra files (hashes shared by 2+ attachments).
	$rows = $wpdb->get_results(
		"SELECT meta_value AS h, COUNT(*) AS c FROM {$wpdb->postmeta}
		 WHERE meta_key='_rm_sha1' AND meta_value NOT IN ('', 'missing')
		 GROUP BY meta_value HAVING c > 1"
	);
	$groups = count( $rows );
	$extra  = 0;
	foreach ( $rows as $r ) {
		$extra += ( (int) $r->c - 1 );
	}
	return array(
		'images'  => $images,
		'indexed' => $indexed,
		'groups'  => $groups,
		'extra'   => $extra,
	);
}

function rm_mc_render_page() {
	$s = rm_mc_stats();
	echo '<div class="wrap"><h1>' . esc_html__( 'Media Cleanup', 'ricoman' ) . '</h1>';
	echo '<p>' . esc_html__( 'Find and safely remove duplicate images created by the old variant import. Work through the steps in order — nothing is deleted until you choose to.', 'ricoman' ) . '</p>';

	echo '<table class="widefat" style="max-width:640px;margin:16px 0"><tbody>';
	printf( '<tr><th>%s</th><td id="rm-mc-images">%s</td></tr>', esc_html__( 'Images in library', 'ricoman' ), esc_html( number_format_i18n( $s['images'] ) ) );
	printf( '<tr><th>%s</th><td id="rm-mc-indexed">%s</td></tr>', esc_html__( 'Indexed (hashed)', 'ricoman' ), esc_html( number_format_i18n( $s['indexed'] ) ) );
	printf( '<tr><th>%s</th><td id="rm-mc-groups">%s</td></tr>', esc_html__( 'Duplicate groups', 'ricoman' ), esc_html( number_format_i18n( $s['groups'] ) ) );
	printf( '<tr><th>%s</th><td id="rm-mc-extra"><strong>%s</strong></td></tr>', esc_html__( 'Removable duplicate files', 'ricoman' ), esc_html( number_format_i18n( $s['extra'] ) ) );
	echo '</tbody></table>';

	echo '<h2>' . esc_html__( 'Step 1 — Index the library', 'ricoman' ) . '</h2>';
	echo '<p>' . esc_html__( 'Reads each image once and records a content fingerprint. Safe to re-run; resumes where it left off. Leave this tab open while it works.', 'ricoman' ) . '</p>';
	echo '<p><button class="button button-primary" id="rm-mc-index">' . esc_html__( 'Start / resume indexing', 'ricoman' ) . '</button> <span id="rm-mc-progress" style="margin-left:10px"></span></p>';

	echo '<h2>' . esc_html__( 'Step 2 — Review duplicates', 'ricoman' ) . '</h2>';
	echo '<p>' . esc_html__( 'Once indexing is complete the "Removable duplicate files" figure above is the number of files that can be safely merged away (each keeps one canonical copy). The destructive merge step is enabled separately once you have a backup.', 'ricoman' ) . '</p>';
	echo '<p style="color:#b32d2e"><strong>' . esc_html__( 'Before merging:', 'ricoman' ) . '</strong> ' . esc_html__( 'take a full backup of the database and the uploads folder. Merging remaps references and trashes files — it cannot be undone from here.', 'ricoman' ) . '</p>';

	$nonce = wp_create_nonce( 'rm_mc' );
	?>
	<script>
	(function(){
		var btn=document.getElementById('rm-mc-index'),prog=document.getElementById('rm-mc-progress');
		var nonce=<?php echo wp_json_encode( $nonce ); ?>, ajax=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var running=false;
		function setStat(d){ if(d.images!=null)document.getElementById('rm-mc-images').textContent=d.images.toLocaleString();
			if(d.indexed!=null)document.getElementById('rm-mc-indexed').textContent=d.indexed.toLocaleString();
			if(d.groups!=null)document.getElementById('rm-mc-groups').textContent=d.groups.toLocaleString();
			if(d.extra!=null)document.querySelector('#rm-mc-extra strong').textContent=d.extra.toLocaleString(); }
		function batch(offset){
			var body=new URLSearchParams({action:'rm_mc_index',nonce:nonce,offset:offset});
			fetch(ajax,{method:'POST',body:body,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
				if(!j||!j.success){ prog.textContent='Error — check logs.'; running=false; btn.disabled=false; return; }
				var d=j.data; setStat(d.stats||{});
				prog.textContent='Indexed '+(d.done).toLocaleString()+' of '+(d.total).toLocaleString()+'…';
				if(d.next!==null){ batch(d.next); }
				else { prog.textContent='Done — indexed '+(d.total).toLocaleString()+' images.'; running=false; btn.disabled=false; }
			}).catch(function(){ prog.textContent='Network error — click to resume.'; running=false; btn.disabled=false; });
		}
		btn.addEventListener('click',function(){ if(running)return; running=true; btn.disabled=true; prog.textContent='Starting…'; batch(0); });
	})();
	</script>
	<?php
	echo '</div>';
}

/** AJAX: index one batch of un-hashed image attachments. */
add_action( 'wp_ajax_rm_mc_index', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_mc', 'nonce', false ) ) {
		wp_send_json_error();
	}
	global $wpdb;
	$batch = rm_mc_batch();
	// Image attachments without a hash yet.
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key='_rm_sha1'
		 WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%%' AND m.meta_id IS NULL
		 ORDER BY p.ID ASC LIMIT %d",
		$batch
	) );
	foreach ( $ids as $id ) {
		rm_mc_hash( (int) $id, true );
	}
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'" );
	$done  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_rm_sha1'" );
	wp_send_json_success( array(
		'total' => $total,
		'done'  => $done,
		'next'  => count( $ids ) < $batch ? null : $done,
		'stats' => rm_mc_stats(),
	) );
} );
