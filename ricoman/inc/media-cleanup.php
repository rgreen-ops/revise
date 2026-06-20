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

	echo '<h2>' . esc_html__( 'Step 2 — Preview the merge (safe)', 'ricoman' ) . '</h2>';
	echo '<p>' . esc_html__( 'Runs the merge logic on a sample of duplicates WITHOUT changing anything — it reports how many references would be remapped, so you can sanity-check first.', 'ricoman' ) . '</p>';
	echo '<p><button class="button" id="rm-mc-preview">' . esc_html__( 'Preview 40 duplicates', 'ricoman' ) . '</button> <span id="rm-mc-preview-out" style="margin-left:10px"></span></p>';

	echo '<h2>' . esc_html__( 'Step 3 — Merge duplicates', 'ricoman' ) . '</h2>';
	echo '<p style="color:#b32d2e"><strong>' . esc_html__( 'Back up first.', 'ricoman' ) . '</strong> ' . esc_html__( 'This remaps every reference to one canonical copy and moves the duplicates to Trash (recoverable for ~30 days). Take a full database + uploads backup before running.', 'ricoman' ) . '</p>';
	echo '<p>' . esc_html__( 'Type', 'ricoman' ) . ' <code>MERGE</code> ' . esc_html__( 'to enable, then run. It processes in batches and keeps going until done — leave the tab open.', 'ricoman' ) . '</p>';
	echo '<p><input type="text" id="rm-mc-confirm" placeholder="Type MERGE" style="width:140px"> '
		. '<button class="button button-primary" id="rm-mc-merge" disabled>' . esc_html__( 'Merge duplicates (to Trash)', 'ricoman' ) . '</button> '
		. '<span id="rm-mc-merge-out" style="margin-left:10px"></span></p>';

	echo '<h2>' . esc_html__( 'Step 4 — Find unused images (report only)', 'ricoman' ) . '</h2>';
	echo '<p>' . esc_html__( 'Scans for images with no reference we can detect and tags them, so you can review them in the Media Library before deciding. Nothing is deleted.', 'ricoman' ) . '</p>';
	echo '<p><button class="button" id="rm-mc-unused">' . esc_html__( 'Scan for unused images', 'ricoman' ) . '</button> <span id="rm-mc-unused-out" style="margin-left:10px">';
	$uc = (int) get_option( 'rm_mc_unused_count', 0 );
	if ( $uc ) {
		printf( esc_html__( 'Last scan: %s unused.', 'ricoman' ), '<strong>' . esc_html( number_format_i18n( $uc ) ) . '</strong>' );
	}
	echo '</span></p>';

	echo '<h2>' . esc_html__( 'Step 5 — Rename junk filenames from context', 'ricoman' ) . '</h2>';
	echo '<p>' . esc_html__( 'Finds images with meaningless titles (just numbers, IMG_1234, etc.) and renames the title + alt text from the product/post they belong to, plus its category. Preview first; only changes metadata (reversible).', 'ricoman' ) . '</p>';
	echo '<p><button class="button" id="rm-mc-rename-pv">' . esc_html__( 'Preview renames', 'ricoman' ) . '</button> '
		. '<button class="button button-primary" id="rm-mc-rename">' . esc_html__( 'Rename now', 'ricoman' ) . '</button> '
		. '<span id="rm-mc-rename-out" style="margin-left:10px"></span></p>';

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

		// Preview (dry-run).
		var pv=document.getElementById('rm-mc-preview'),pvOut=document.getElementById('rm-mc-preview-out');
		pv.addEventListener('click',function(){
			pv.disabled=true; pvOut.textContent='Checking…';
			var b=new URLSearchParams({action:'rm_mc_merge',nonce:nonce,apply:'0',limit:'40'});
			fetch(ajax,{method:'POST',body:b,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
				pv.disabled=false;
				if(!j||!j.success){ pvOut.textContent='Error.'; return; }
				var d=j.data;
				pvOut.textContent='Sample of '+d.processed+' duplicates would remap '+d.refs+' reference(s). No changes made.';
			}).catch(function(){ pv.disabled=false; pvOut.textContent='Network error.'; });
		});

		// Merge (apply) — gated by typing MERGE.
		var cf=document.getElementById('rm-mc-confirm'),mg=document.getElementById('rm-mc-merge'),mgOut=document.getElementById('rm-mc-merge-out');
		cf.addEventListener('input',function(){ mg.disabled=(cf.value.trim().toUpperCase()!=='MERGE'); });
		var merging=false,total=0;
		function mergeBatch(){
			var b=new URLSearchParams({action:'rm_mc_merge',nonce:nonce,apply:'1',confirm:cf.value,limit:'40'});
			fetch(ajax,{method:'POST',body:b,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
				if(!j||!j.success){ mgOut.textContent='Error — stopped.'; merging=false; mg.disabled=false; return; }
				var d=j.data; total+=d.trashed;
				setStat({extra:d.remaining});
				mgOut.textContent='Merged '+total.toLocaleString()+' duplicates… '+d.remaining.toLocaleString()+' remaining.';
				if(d.processed>0 && d.remaining>0){ mergeBatch(); }
				else { mgOut.textContent='Done — merged '+total.toLocaleString()+' duplicates to Trash. '+d.remaining.toLocaleString()+' remaining.'; merging=false; }
			}).catch(function(){ mgOut.textContent='Network error — click to resume.'; merging=false; mg.disabled=false; });
		}
		mg.addEventListener('click',function(){ if(merging)return; if(cf.value.trim().toUpperCase()!=='MERGE')return;
			if(!confirm('Merge duplicate images to Trash? Make sure you have a backup.'))return;
			merging=true; mg.disabled=true; mgOut.textContent='Merging…'; mergeBatch(); });

		// Step 4 — unused scan (report only).
		var us=document.getElementById('rm-mc-unused'),usOut=document.getElementById('rm-mc-unused-out'),usRun=false;
		function unusedBatch(off){
			var b=new URLSearchParams({action:'rm_mc_unused',nonce:nonce,offset:off});
			fetch(ajax,{method:'POST',body:b,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
				if(!j||!j.success){ usOut.textContent='Error.'; usRun=false; us.disabled=false; return; }
				var d=j.data; usOut.innerHTML='Scanned '+d.done.toLocaleString()+' of '+d.total.toLocaleString()+' — <strong>'+d.unused.toLocaleString()+'</strong> unused so far…';
				if(d.next!==null){ unusedBatch(d.next); }
				else { usOut.innerHTML='Done — <strong>'+d.unused.toLocaleString()+'</strong> unused images tagged (filter Media by them to review).'; usRun=false; us.disabled=false; }
			}).catch(function(){ usOut.textContent='Network error.'; usRun=false; us.disabled=false; });
		}
		us.addEventListener('click',function(){ if(usRun)return; usRun=true; us.disabled=true; usOut.textContent='Scanning…'; unusedBatch(0); });

		// Step 5 — rename (preview + apply).
		var rPv=document.getElementById('rm-mc-rename-pv'),rGo=document.getElementById('rm-mc-rename'),rOut=document.getElementById('rm-mc-rename-out'),rRun=false;
		function renameRun(apply){
			var totalR=0;
			function step(off){
				var b=new URLSearchParams({action:'rm_mc_rename',nonce:nonce,offset:off,apply:apply?'1':'0'});
				fetch(ajax,{method:'POST',body:b,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
					if(!j||!j.success){ rOut.textContent='Error.'; rRun=false; rPv.disabled=rGo.disabled=false; return; }
					var d=j.data; totalR+=d.renamed;
					rOut.textContent=(apply?'Renamed ':'Would rename ')+totalR.toLocaleString()+' so far ('+d.done.toLocaleString()+'/'+d.total.toLocaleString()+')…';
					if(d.next!==null){ step(d.next); }
					else { rOut.textContent=(apply?'Done — renamed ':'Preview — would rename ')+totalR.toLocaleString()+' images'+(apply?'.':' (nothing changed).'); rRun=false; rPv.disabled=rGo.disabled=false; }
				}).catch(function(){ rOut.textContent='Network error.'; rRun=false; rPv.disabled=rGo.disabled=false; });
			}
			step(0);
		}
		rPv.addEventListener('click',function(){ if(rRun)return; rRun=true; rPv.disabled=rGo.disabled=true; rOut.textContent='Checking…'; renameRun(false); });
		rGo.addEventListener('click',function(){ if(rRun)return; if(!confirm('Rename junk-named images from their context?'))return; rRun=true; rPv.disabled=rGo.disabled=true; rOut.textContent='Renaming…'; renameRun(true); });
	})();
	</script>
	<?php
	echo '</div>';
}

/* ------------------------------------------------------------------ *
 * 4. Merge duplicates — remap references to the canonical image, then
 *    Trash the duplicate. Bounded to image-type meta so unrelated numeric
 *    meta (parent IDs, menu order, …) is never touched.
 * ------------------------------------------------------------------ */

/** SQL fragment: meta rows that plausibly hold an image/gallery reference. */
function rm_mc_image_key_sql( $alias = 'm' ) {
	return "($alias.meta_key = '_thumbnail_id' OR $alias.meta_key REGEXP '(image|images|diagram|diagrams|gallery|galleries|galary|icon|photo|banner|thumbnail)$')";
}

/** Replace attachment id $from with $to inside one meta value (plain / CSV /
 * serialized). Returns array( new_value, changed ). Only exact int matches. */
function rm_mc_replace_in_value( $value, $from, $to ) {
	$un = is_string( $value ) ? @unserialize( $value ) : false;
	if ( false !== $un || 'b:0;' === $value ) {
		$changed = false;
		if ( is_array( $un ) ) {
			array_walk_recursive( $un, function ( &$v ) use ( $from, $to, &$changed ) {
				if ( ( is_int( $v ) && $v === $from ) || ( is_string( $v ) && ctype_digit( $v ) && (int) $v === $from ) ) {
					$v = is_int( $v ) ? $to : (string) $to;
					$changed = true;
				}
			} );
		}
		return $changed ? array( serialize( $un ), true ) : array( $value, false );
	}
	$trim = trim( (string) $value );
	if ( ctype_digit( $trim ) && (int) $trim === $from ) {
		return array( (string) $to, true );
	}
	if ( false !== strpos( $value, ',' ) ) {
		$parts   = array_map( 'trim', explode( ',', $value ) );
		$changed = false;
		foreach ( $parts as &$p ) {
			if ( ctype_digit( $p ) && (int) $p === $from ) {
				$p = (string) $to;
				$changed = true;
			}
		}
		return $changed ? array( implode( ',', $parts ), true ) : array( $value, false );
	}
	return array( $value, false );
}

/** Remap every reference of attachment $from to $to. Dry-run unless $apply.
 * Returns the number of references found/changed. */
function rm_mc_remap_reference( $from, $to, $apply = false ) {
	global $wpdb;
	$from = (int) $from;
	$to   = (int) $to;
	$n    = 0;

	// Meta references (image-type keys only).
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.meta_id, m.meta_value FROM {$wpdb->postmeta} m
		 WHERE " . rm_mc_image_key_sql( 'm' ) . "
		   AND m.meta_value REGEXP %s",
		'(^|[^0-9])' . $from . '([^0-9]|$)'
	) );
	foreach ( $rows as $r ) {
		list( $new, $changed ) = rm_mc_replace_in_value( $r->meta_value, $from, $to );
		if ( $changed ) {
			$n++;
			if ( $apply ) {
				$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new ), array( 'meta_id' => (int) $r->meta_id ) );
			}
		}
	}

	// Content references: wp-image-{id} class + the full-size URL.
	$from_url = wp_get_attachment_url( $from );
	$to_url   = wp_get_attachment_url( $to );
	$like1    = '%wp-image-' . $from . '%';
	$like2    = $from_url ? '%' . $wpdb->esc_like( $from_url ) . '%' : null;
	$where    = "post_content LIKE %s";
	$params   = array( $like1 );
	if ( $like2 ) {
		$where   .= " OR post_content LIKE %s";
		$params[] = $like2;
	}
	$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM {$wpdb->posts} WHERE $where", $params ) );
	foreach ( $posts as $p ) {
		$new = str_replace( 'wp-image-' . $from, 'wp-image-' . $to, $p->post_content );
		if ( $from_url && $to_url ) {
			$new = str_replace( $from_url, $to_url, $new );
		}
		if ( $new !== $p->post_content ) {
			$n++;
			if ( $apply ) {
				$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => (int) $p->ID ) );
				clean_post_cache( (int) $p->ID );
			}
		}
	}
	return $n;
}

/** Process one batch of duplicates: remap + (optionally) trash. */
function rm_mc_merge_batch( $apply, $limit ) {
	global $wpdb;
	// Duplicate attachments = image attachments whose hash is shared by an
	// earlier (lower-ID) attachment. The earliest in each group is the canon.
	$dupes = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.post_id AS id, m.meta_value AS hash FROM {$wpdb->postmeta} m
		 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_status <> 'trash'
		 WHERE m.meta_key='_rm_sha1' AND m.meta_value NOT IN ('', 'missing')
		   AND EXISTS (
		     SELECT 1 FROM {$wpdb->postmeta} m2
		     WHERE m2.meta_key='_rm_sha1' AND m2.meta_value = m.meta_value AND m2.post_id < m.post_id
		   )
		 ORDER BY m.post_id ASC LIMIT %d",
		(int) $limit
	) );
	$refs = 0;
	$done = 0;
	foreach ( $dupes as $d ) {
		$canon = rm_mc_canonical_for_hash( $d->hash, (int) $d->id );
		if ( ! $canon || $canon === (int) $d->id ) {
			continue;
		}
		$refs += rm_mc_remap_reference( (int) $d->id, (int) $canon, $apply );
		if ( $apply ) {
			delete_post_meta( (int) $d->id, '_rm_sha1' ); // so it stops counting as a dup.
			update_post_meta( (int) $d->id, '_rm_merged_into', (int) $canon );
			wp_trash_post( (int) $d->id ); // recoverable; references already moved.
			$done++;
		}
	}
	return array(
		'processed' => count( $dupes ),
		'refs'      => $refs,
		'trashed'   => $done,
		'remaining' => (int) rm_mc_stats()['extra'],
	);
}

/** AJAX: merge a batch (dry-run unless apply=1 with the confirm token). */
add_action( 'wp_ajax_rm_mc_merge', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_mc', 'nonce', false ) ) {
		wp_send_json_error();
	}
	$apply = isset( $_POST['apply'] ) && '1' === $_POST['apply']
		&& isset( $_POST['confirm'] ) && 'MERGE' === strtoupper( trim( wp_unslash( $_POST['confirm'] ) ) );
	$limit = max( 5, min( 100, (int) ( $_POST['limit'] ?? 40 ) ) );
	$res   = rm_mc_merge_batch( $apply, $limit );
	$res['applied'] = $apply;
	wp_send_json_success( $res );
} );

/* ------------------------------------------------------------------ *
 * 5. Unused report (read-only) + context-based renaming.
 * ------------------------------------------------------------------ */

/** Is this attachment referenced anywhere we can detect? */
function rm_mc_is_referenced( $id ) {
	global $wpdb;
	$id = (int) $id;
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key='_thumbnail_id' AND meta_value=%d LIMIT 1", $id ) ) ) {
		return true;
	}
	if ( $wpdb->get_var( $wpdb->prepare(
		"SELECT 1 FROM {$wpdb->postmeta} m WHERE " . rm_mc_image_key_sql( 'm' ) . " AND m.meta_value REGEXP %s LIMIT 1",
		'(^|[^0-9])' . $id . '([^0-9]|$)'
	) ) ) {
		return true;
	}
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->posts} WHERE post_content LIKE %s LIMIT 1", '%wp-image-' . $id . '%' ) ) ) {
		return true;
	}
	$url = wp_get_attachment_url( $id );
	if ( $url && $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->posts} WHERE post_content LIKE %s LIMIT 1", '%' . $wpdb->esc_like( $url ) . '%' ) ) ) {
		return true;
	}
	$parent = (int) get_post_field( 'post_parent', $id );
	if ( $parent && get_post_status( $parent ) && 'trash' !== get_post_status( $parent ) ) {
		return true;
	}
	return false;
}

/** A post that owns / references this image, for naming context. */
function rm_mc_owner_post( $id ) {
	global $wpdb;
	$id     = (int) $id;
	$parent = (int) get_post_field( 'post_parent', $id );
	if ( $parent && get_post_status( $parent ) ) {
		return $parent;
	}
	$owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_thumbnail_id' AND meta_value=%d LIMIT 1", $id ) );
	if ( $owner ) {
		return $owner;
	}
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} m WHERE " . rm_mc_image_key_sql( 'm' ) . " AND m.meta_value REGEXP %s LIMIT 1",
		'(^|[^0-9])' . $id . '([^0-9]|$)'
	) );
}

/** Does this attachment have a junk / meaningless title? */
function rm_mc_is_junk_name( $id ) {
	$t = trim( get_the_title( $id ) );
	if ( '' === $t ) {
		return true;
	}
	if ( preg_match( '/^[0-9\-_\s\.]+$/', $t ) ) {
		return true; // only digits/separators.
	}
	if ( preg_match( '/^(img|dsc|dscn|image|images|photo|untitled|screenshot|scan|file|p)[-_\s]?\d+$/i', $t ) ) {
		return true;
	}
	if ( ! preg_match( '/[a-z]{3,}/i', $t ) ) {
		return true; // no real word.
	}
	return false;
}

/** Build a human title from the owning post (+ product category). */
function rm_mc_context_name( $id ) {
	$owner = rm_mc_owner_post( $id );
	if ( ! $owner ) {
		return '';
	}
	$base = trim( wp_strip_all_tags( get_the_title( $owner ) ) );
	if ( '' === $base ) {
		return '';
	}
	$cat = '';
	foreach ( array( 'product-cat', 'product_cat', 'project-cat' ) as $tax ) {
		if ( taxonomy_exists( $tax ) ) {
			$terms = get_the_terms( $owner, $tax );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$cat = $terms[0]->name;
				break;
			}
		}
	}
	return $cat ? $base . ' – ' . $cat : $base;
}

/** AJAX: scan a batch for unused images (report only — marks _rm_unref). */
add_action( 'wp_ajax_rm_mc_unused', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_mc', 'nonce', false ) ) {
		wp_send_json_error();
	}
	global $wpdb;
	$batch  = rm_mc_batch();
	$offset = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status<>'trash' AND post_mime_type LIKE 'image/%%' ORDER BY ID ASC LIMIT %d OFFSET %d",
		$batch, $offset
	) );
	$unused = (int) get_option( 'rm_mc_unused_count', 0 );
	if ( 0 === $offset ) {
		$unused = 0;
	}
	foreach ( $ids as $id ) {
		if ( ! rm_mc_is_referenced( (int) $id ) ) {
			update_post_meta( (int) $id, '_rm_unref', 1 );
			$unused++;
		} else {
			delete_post_meta( (int) $id, '_rm_unref' );
		}
	}
	update_option( 'rm_mc_unused_count', $unused, false );
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status<>'trash' AND post_mime_type LIKE 'image/%'" );
	$next  = ( $offset + count( $ids ) );
	wp_send_json_success( array(
		'total'  => $total,
		'done'   => $next,
		'unused' => $unused,
		'next'   => count( $ids ) < $batch ? null : $next,
	) );
} );

/** AJAX: rename a batch of junk-named images from context (title + alt). */
add_action( 'wp_ajax_rm_mc_rename', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_mc', 'nonce', false ) ) {
		wp_send_json_error();
	}
	global $wpdb;
	$apply  = isset( $_POST['apply'] ) && '1' === $_POST['apply'];
	$batch  = rm_mc_batch();
	$offset = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status<>'trash' AND post_mime_type LIKE 'image/%%' ORDER BY ID ASC LIMIT %d OFFSET %d",
		$batch, $offset
	) );
	$renamed = 0;
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( ! rm_mc_is_junk_name( $id ) ) {
			continue;
		}
		$name = rm_mc_context_name( $id );
		if ( '' === $name ) {
			continue;
		}
		$renamed++;
		if ( $apply ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => $name ) );
			if ( '' === trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ) {
				update_post_meta( $id, '_wp_attachment_image_alt', $name );
			}
		}
	}
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status<>'trash' AND post_mime_type LIKE 'image/%'" );
	$next  = ( $offset + count( $ids ) );
	wp_send_json_success( array(
		'total'   => $total,
		'done'    => $next,
		'renamed' => $renamed,
		'applied' => $apply,
		'next'    => count( $ids ) < $batch ? null : $next,
	) );
} );

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
