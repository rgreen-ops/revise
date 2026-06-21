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

/**
 * SQL fragment matching an attachment that's an image file by EXTENSION (on the
 * given _wp_attached_file column). Extensions are a fixed safe whitelist, so
 * direct interpolation is intentional. Catches migrated images whose
 * post_mime_type was imported empty (and so miss a plain `image/%` match).
 */
function rm_mc_image_file_cond( $col ) {
	$exts  = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tiff' );
	$likes = array();
	foreach ( $exts as $e ) {
		$likes[] = "$col LIKE '%." . $e . "'";
	}
	return '( ' . implode( ' OR ', $likes ) . ' )';
}

/** Totals for the dashboard (images, indexed, duplicate groups, reclaimable). */
function rm_mc_stats() {
	global $wpdb;
	$cond   = rm_mc_image_file_cond( 'f.meta_value' );
	$images = (int) $wpdb->get_var(
		"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
		 JOIN {$wpdb->postmeta} f ON f.post_id=p.ID AND f.meta_key='_wp_attached_file'
		 WHERE p.post_type='attachment' AND p.post_status<>'trash'
		 AND ( p.post_mime_type LIKE 'image/%' OR $cond )"
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

/**
 * Why the Media Library total (e.g. 42,526) is far bigger than the "images by
 * mime" count (e.g. 6,596): the migrated import created thousands of attachment
 * posts with an EMPTY/odd post_mime_type, so `mime LIKE 'image/%'` misses them.
 * This read-only breakdown shows where every attachment falls so the gap is
 * explained, and counts image-by-extension files the dedup would otherwise skip.
 */
function rm_mc_breakdown() {
	global $wpdb;
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status<>'trash'" );
	$mime  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status<>'trash' AND post_mime_type LIKE 'image/%'" );
	$empty = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status<>'trash' AND (post_mime_type='' OR post_mime_type IS NULL)" );
	// Attachments that are image files by extension (catches empty-mime images).
	$img_ext = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_wp_attached_file'
		 WHERE p.post_type='attachment' AND p.post_status<>'trash'
		 AND ( m.meta_value LIKE '%.jpg' OR m.meta_value LIKE '%.jpeg' OR m.meta_value LIKE '%.png'
		    OR m.meta_value LIKE '%.gif' OR m.meta_value LIKE '%.webp' OR m.meta_value LIKE '%.avif' OR m.meta_value LIKE '%.bmp' OR m.meta_value LIKE '%.tiff' )"
	);
	return array(
		'total'    => $total,
		'mime'     => $mime,
		'empty'    => $empty,
		'img_ext'  => $img_ext,
		'other'    => max( 0, $total - $img_ext ),
	);
}

function rm_mc_render_page() {
	$s = rm_mc_stats();
	echo '<div class="wrap"><h1>' . esc_html__( 'Media Cleanup', 'ricoman' ) . '</h1>';
	echo '<p>' . esc_html__( 'Find and safely remove duplicate images created by the old variant import. Work through the steps in order — nothing is deleted until you choose to.', 'ricoman' ) . '</p>';

	$bd = rm_mc_breakdown();
	echo '<table class="widefat" style="max-width:640px;margin:16px 0"><tbody>';
	printf( '<tr><th>%s</th><td id="rm-mc-images">%s</td></tr>', esc_html__( 'Image files in library (by extension)', 'ricoman' ), esc_html( number_format_i18n( $bd['img_ext'] ) ) );
	printf( '<tr><th>%s</th><td id="rm-mc-indexed">%s</td></tr>', esc_html__( 'Indexed (hashed)', 'ricoman' ), esc_html( number_format_i18n( $s['indexed'] ) ) );
	printf( '<tr><th>%s</th><td id="rm-mc-groups">%s</td></tr>', esc_html__( 'Duplicate groups', 'ricoman' ), esc_html( number_format_i18n( $s['groups'] ) ) );
	printf( '<tr><th>%s</th><td id="rm-mc-extra"><strong>%s</strong></td></tr>', esc_html__( 'Removable duplicate files', 'ricoman' ), esc_html( number_format_i18n( $s['extra'] ) ) );
	echo '</tbody></table>';

	// Library breakdown — explains why Media Library shows far more than the
	// "images by mime" count (migrated attachments with empty/odd mime types).
	echo '<details style="max-width:640px;margin:0 0 16px"><summary style="cursor:pointer;font-weight:600">'
		. esc_html__( 'Why does Media Library show a different (larger) number?', 'ricoman' ) . '</summary>';
	echo '<table class="widefat" style="margin:10px 0"><tbody>';
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Total attachments (what Media Library counts)', 'ricoman' ), esc_html( number_format_i18n( $bd['total'] ) ) );
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( '— with an image/* mime type', 'ricoman' ), esc_html( number_format_i18n( $bd['mime'] ) ) );
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( '— image files by extension (incl. empty mime)', 'ricoman' ), esc_html( number_format_i18n( $bd['img_ext'] ) ) );
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( '— with empty/missing mime type', 'ricoman' ), esc_html( number_format_i18n( $bd['empty'] ) ) );
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( '— non-image / other', 'ricoman' ), esc_html( number_format_i18n( $bd['other'] ) ) );
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'The old import saved thousands of attachment posts with a blank mime type, so a plain "image/*" count undercounts them. The indexer below now matches images by file extension, so it sees and de-duplicates these too.', 'ricoman' ) . '</p>';
	echo '</details>';

	$me = wp_get_current_user();
	$to = $me && $me->user_email ? $me->user_email : get_option( 'admin_email' );
	echo '<p><input type="email" id="rm-mc-email-to" value="' . esc_attr( $to ) . '" style="width:240px" placeholder="you@example.com"> '
		. '<button class="button" id="rm-mc-email">' . esc_html__( 'Email me these stats', 'ricoman' ) . '</button> '
		. '<span id="rm-mc-email-out" style="margin-left:10px"></span></p>';

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
	echo '<div id="rm-mc-merge-prog" style="max-width:640px;display:none">'
		. '<div style="background:#e2e4e7;border-radius:10px;height:18px;overflow:hidden"><div id="rm-mc-merge-bar" style="background:#2271b1;height:100%;width:0;transition:width .3s"></div></div>'
		. '<p id="rm-mc-merge-pct" style="font-weight:600;margin:6px 0 0"></p></div>';

	echo '<h2>' . esc_html__( 'Step 3b — Permanently delete merged duplicates (reclaim space)', 'ricoman' ) . '</h2>';
	echo '<p style="color:#b32d2e"><strong>' . esc_html__( 'Back up first.', 'ricoman' ) . '</strong> '
		. esc_html__( 'This permanently deletes the duplicates already merged to Trash and removes their files from disk — this is what actually reclaims storage and fixes the backups. It only ever touches images this tool merged, and never deletes a file a kept image still shares.', 'ricoman' ) . '</p>';
	$purge_total = (int) rm_mc_trashed_dupe_count();
	echo '<p><strong>' . esc_html( sprintf( __( '%s duplicates currently in Trash, ready to delete.', 'ricoman' ), number_format_i18n( $purge_total ) ) ) . '</strong></p>';
	echo '<p><input type="text" id="rm-mc-purge-confirm" placeholder="Type DELETE" style="width:140px"> '
		. '<button class="button button-primary" id="rm-mc-purge" disabled>' . esc_html__( 'Delete duplicates permanently', 'ricoman' ) . '</button> '
		. '<span id="rm-mc-purge-out" style="margin-left:10px"></span></p>';
	echo '<div id="rm-mc-purge-prog" style="max-width:640px;display:none">'
		. '<div style="background:#e2e4e7;border-radius:10px;height:18px;overflow:hidden"><div id="rm-mc-purge-bar" style="background:#b32d2e;height:100%;width:0;transition:width .3s"></div></div>'
		. '<p id="rm-mc-purge-pct" style="font-weight:600;margin:6px 0 0"></p></div>';

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
		var mgProg=document.getElementById('rm-mc-merge-prog'),mgBar=document.getElementById('rm-mc-merge-bar'),mgPct=document.getElementById('rm-mc-merge-pct'),mgStart=0,mgT0=Date.now();
		function mergeBatch(){
			var b=new URLSearchParams({action:'rm_mc_merge',nonce:nonce,apply:'1',confirm:cf.value,limit:'80'});
			fetch(ajax,{method:'POST',body:b,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
				if(!j||!j.success){ mgOut.textContent='Stopped (server busy). Click Merge to resume.'; merging=false; mg.disabled=false; return; }
				var d=j.data; total+=d.trashed;
				if(mgStart===0){ mgStart=total+d.remaining; mgProg.style.display='block'; }
				setStat({extra:d.remaining});
				var pctNum=mgStart?Math.min(100,(total/mgStart*100)):0, pct=pctNum.toFixed(1);
				mgBar.style.width=pct+'%';
				var rate=total/((Date.now()-mgT0)/1000), eta=rate>0?Math.round(d.remaining/rate/60):0;
				mgPct.textContent='Merged '+total.toLocaleString()+' of '+mgStart.toLocaleString()+' ('+pct+'%) — '+d.remaining.toLocaleString()+' left'+(eta>0?', ~'+eta+' min remaining':'');
				mgOut.textContent='Working…';
				if(d.trashed>0 && d.remaining>0){ setTimeout(mergeBatch, 150); }  // brief pause so the live site isn't hammered
				else { mgBar.style.width='100%'; mgPct.textContent='Done — merged '+total.toLocaleString()+' duplicates to Trash. '+d.remaining.toLocaleString()+' remaining.'; mgOut.textContent='✅ Complete.'; merging=false; mg.disabled=false; }
			}).catch(function(){ mgOut.textContent='Paused (network/timeout). Click Merge to resume — progress is saved.'; merging=false; mg.disabled=false; });
		}
		mg.addEventListener('click',function(){ if(merging)return; if(cf.value.trim().toUpperCase()!=='MERGE')return;
			if(!confirm('Merge duplicate images to Trash? Make sure you have a backup.'))return;
			merging=true; mg.disabled=true; mgOut.textContent='Merging…'; mergeBatch(); });

		// Step 3b — permanently delete merged duplicates (gated by typing DELETE).
		var pcf=document.getElementById('rm-mc-purge-confirm'),pg=document.getElementById('rm-mc-purge'),pgOut=document.getElementById('rm-mc-purge-out');
		var pgProg=document.getElementById('rm-mc-purge-prog'),pgBar=document.getElementById('rm-mc-purge-bar'),pgPct=document.getElementById('rm-mc-purge-pct');
		var purging=false,pTotal=0,pFreed=0,pStart=0,pT0=Date.now();
		if(pcf){ pcf.addEventListener('input',function(){ pg.disabled=(pcf.value.trim().toUpperCase()!=='DELETE'); }); }
		function fmtMB(b){ return (b/1048576)>=1024 ? (b/1073741824).toFixed(2)+' GB' : Math.round(b/1048576).toLocaleString()+' MB'; }
		function purgeBatch(){
			var b=new URLSearchParams({action:'rm_mc_purge',nonce:nonce,confirm:pcf.value,limit:'200'});
			fetch(ajax,{method:'POST',body:b,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
				if(!j||!j.success){ pgOut.textContent='Stopped (server busy). Click Delete to resume.'; purging=false; pg.disabled=false; return; }
				var d=j.data; pTotal+=d.deleted; pFreed+=d.freed;
				if(pStart===0){ pStart=pTotal+d.remaining; pgProg.style.display='block'; }
				var pct=(pStart?Math.min(100,(pTotal/pStart*100)):0).toFixed(1);
				pgBar.style.width=pct+'%';
				var rate=pTotal/((Date.now()-pT0)/1000), eta=rate>0?Math.round(d.remaining/rate/60):0;
				pgPct.textContent='Deleted '+pTotal.toLocaleString()+' of '+pStart.toLocaleString()+' ('+pct+'%) — '+d.remaining.toLocaleString()+' left, ~'+fmtMB(pFreed)+' freed'+(eta>0?', ~'+eta+' min remaining':'');
				pgOut.textContent='Working…';
				if(d.deleted>0 && d.remaining>0){ setTimeout(purgeBatch, 200); }
				else { pgBar.style.width='100%'; pgPct.textContent='Done — deleted '+pTotal.toLocaleString()+' duplicates, ~'+fmtMB(pFreed)+' reclaimed. '+d.remaining.toLocaleString()+' remaining.'; pgOut.textContent='✅ Complete.'; purging=false; pg.disabled=false; }
			}).catch(function(){ pgOut.textContent='Paused (network/timeout). Click Delete to resume — progress is saved.'; purging=false; pg.disabled=false; });
		}
		if(pg){ pg.addEventListener('click',function(){ if(purging)return; if(pcf.value.trim().toUpperCase()!=='DELETE')return;
			if(!confirm('Permanently delete the merged duplicates and their files? This cannot be undone. Make sure you have a backup.'))return;
			purging=true; pg.disabled=true; pgOut.textContent='Deleting…'; purgeBatch(); }); }

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

		// Email the stats.
		var em=document.getElementById('rm-mc-email'),emTo=document.getElementById('rm-mc-email-to'),emOut=document.getElementById('rm-mc-email-out');
		em.addEventListener('click',function(){
			em.disabled=true; emOut.textContent='Sending…';
			var b=new URLSearchParams({action:'rm_mc_email',nonce:nonce,to:emTo.value});
			fetch(ajax,{method:'POST',body:b,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
				em.disabled=false;
				emOut.textContent=(j&&j.success)?('Sent to '+j.data.to):('Could not send'+(j&&j.data&&j.data.msg?': '+j.data.msg:'.'));
			}).catch(function(){ em.disabled=false; emOut.textContent='Network error.'; });
		});
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

/** Known image/gallery meta keys (filterable). Used with an indexed meta_key
 * lookup so reference searches don't full-scan a huge postmeta table. */
function rm_mc_image_meta_keys() {
	return apply_filters( 'rm_mc_image_meta_keys', array(
		'_thumbnail_id',
		'product_gallery_image', 'product_main_image', 'product_diagram', 'product_image',
		'insitu_gallery', 'dimension_diagrams',
		'project_image', 'project_banner_image', 'project_gallery',
		'news_bottom_image', 'galary_image', 'gallery', 'image', 'icon',
		'banner_image', 'main_image', 'thumbnail',
	) );
}

/** SQL fragment: meta_key IN (image keys) — uses the meta_key index. */
function rm_mc_image_key_sql( $alias = 'm' ) {
	$keys = array_map( 'esc_sql', rm_mc_image_meta_keys() );
	return "$alias.meta_key IN ('" . implode( "','", $keys ) . "')";
}

/**
 * Set of attachment IDs referenced anywhere we'd need to remap (featured image,
 * gallery/ACF image meta, and wp-image-{id} in content). Built with a couple of
 * one-time scans and cached, so the merge can skip the expensive per-image
 * reference scan for the vast majority of duplicates that aren't referenced at
 * all. (Trashing an attachment post leaves its file on disk, so URL references
 * keep working — only ID references matter here.)
 *
 * @return array<int,true> id => true
 */
function rm_mc_referenced_ids() {
	$cached = get_transient( 'rm_mc_refset' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$set = array();

	// Image-reference meta (thumbnail / galleries / ACF image fields). One scan;
	// pull every integer id out of each value (plain, CSV or serialized).
	$vals = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} m WHERE " . rm_mc_image_key_sql( 'm' ) . " AND m.meta_value <> ''" );
	foreach ( $vals as $value ) {
		$un = is_string( $value ) ? @unserialize( $value ) : false;
		if ( is_array( $un ) ) {
			array_walk_recursive( $un, function ( $v ) use ( &$set ) {
				if ( is_int( $v ) || ( is_string( $v ) && ctype_digit( $v ) ) ) {
					$set[ (int) $v ] = true;
				}
			} );
		} else {
			foreach ( preg_split( '/[^0-9]+/', (string) $value ) as $n ) {
				if ( '' !== $n ) {
					$set[ (int) $n ] = true;
				}
			}
		}
	}

	// Content references by the wp-image-{id} class (one scan).
	$contents = $wpdb->get_col( "SELECT post_content FROM {$wpdb->posts} WHERE post_content LIKE '%wp-image-%'" );
	foreach ( $contents as $c ) {
		if ( preg_match_all( '/wp-image-(\d+)/', (string) $c, $mm ) ) {
			foreach ( $mm[1] as $id ) {
				$set[ (int) $id ] = true;
			}
		}
	}

	set_transient( 'rm_mc_refset', $set, 600 );
	return $set;
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

	// Meta references (indexed meta_key lookup + LIKE; exact match verified in PHP).
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.meta_id, m.meta_value FROM {$wpdb->postmeta} m
		 WHERE " . rm_mc_image_key_sql( 'm' ) . "
		   AND m.meta_value LIKE %s",
		'%' . $wpdb->esc_like( (string) $from ) . '%'
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
	// Cache URL lookups for the request — this runs once per duplicate and each
	// wp_get_attachment_url() is several queries + filters.
	static $url_cache = array();
	if ( ! array_key_exists( $from, $url_cache ) ) {
		$url_cache[ $from ] = wp_get_attachment_url( $from );
	}
	if ( ! array_key_exists( $to, $url_cache ) ) {
		$url_cache[ $to ] = wp_get_attachment_url( $to );
	}
	$from_url = $url_cache[ $from ];
	$to_url   = $url_cache[ $to ];
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
	$start  = microtime( true );
	$budget = (float) apply_filters( 'rm_mc_time_budget', 18.0 ); // seconds per request.

	// Enumerate duplicate groups in ONE pass: each shared hash, its canonical
	// (lowest ID) and up to 80 of its duplicate IDs. Big groups are whittled
	// across successive calls. (Cheap — no per-duplicate lookups.)
	$wpdb->query( 'SET SESSION group_concat_max_len = 200000' );
	$groups = $wpdb->get_results( $wpdb->prepare(
		"SELECT MIN(post_id) AS canon,
		        SUBSTRING_INDEX(GROUP_CONCAT(post_id ORDER BY post_id ASC), ',', 80) AS ids
		 FROM {$wpdb->postmeta}
		 WHERE meta_key='_rm_sha1' AND meta_value NOT IN ('', 'missing')
		 GROUP BY meta_value HAVING COUNT(*) > 1
		 LIMIT %d",
		(int) $limit
	) );

	$refset = rm_mc_referenced_ids();

	$refs = 0;
	$done = 0;
	$seen = 0;
	$out_of_time = false;
	$trash = array(); // vid => canon, trashed in one bulk pass at the end.
	foreach ( $groups as $g ) {
		$canon = (int) $g->canon;
		foreach ( array_map( 'intval', explode( ',', (string) $g->ids ) ) as $vid ) {
			if ( $vid === $canon || $vid <= 0 ) {
				continue;
			}
			$seen++;
			// Only the (rare) duplicates that are actually referenced need the
			// expensive reference remap; the orphaned import duplicates don't.
			if ( isset( $refset[ $vid ] ) ) {
				$refs += rm_mc_remap_reference( $vid, $canon, $apply );
			}
			if ( $apply ) {
				$trash[ $vid ] = $canon;
				$done++;
			}
			if ( microtime( true ) - $start > $budget ) {
				$out_of_time = true;
				break 2;
			}
		}
	}

	// Bulk-trash everything in this batch in a handful of set-based queries
	// instead of a slow wp_trash_post() per image (which fires a cascade of hooks
	// each time). Still recoverable: we set the same trash meta WordPress uses.
	if ( $apply && $trash ) {
		$ids = array_map( 'intval', array_keys( $trash ) );
		$in  = implode( ',', $ids );
		$now = time();

		$status_vals = array();
		$time_vals   = array();
		$merged_vals = array();
		foreach ( $trash as $vid => $canon ) {
			$vid = (int) $vid;
			$status_vals[] = $wpdb->prepare( '(%d,%s,%s)', $vid, '_wp_trash_meta_status', 'inherit' );
			$time_vals[]   = $wpdb->prepare( '(%d,%s,%s)', $vid, '_wp_trash_meta_time', (string) $now );
			$merged_vals[] = $wpdb->prepare( '(%d,%s,%s)', $vid, '_rm_merged_into', (string) (int) $canon );
		}
		// Clear any stale copies of these helper metas, then insert fresh.
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($in) AND meta_key IN ('_rm_sha1','_wp_trash_meta_status','_wp_trash_meta_time','_rm_merged_into')" );
		$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES " . implode( ',', $status_vals ) );
		$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES " . implode( ',', $time_vals ) );
		$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES " . implode( ',', $merged_vals ) );
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_status='trash' WHERE ID IN ($in)" );
		foreach ( $ids as $vid ) {
			clean_post_cache( $vid );
		}
	}

	// Accurate remaining count. Cheap now that the per-duplicate reference scan is
	// gone, and always truthful — so the run never stops early on a stale tally.
	$remaining = (int) rm_mc_stats()['extra'];

	return array(
		'processed' => $seen,
		'refs'      => $refs,
		'trashed'   => $done,
		'timed'     => $out_of_time,
		'remaining' => $remaining,
	);
}

/** How many merged duplicates are sitting in Trash, ready to permanently delete. */
function rm_mc_trashed_dupe_count() {
	global $wpdb;
	return (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} mm ON mm.post_id = p.ID AND mm.meta_key = '_rm_merged_into'
		 WHERE p.post_type = 'attachment' AND p.post_status = 'trash'"
	);
}

/**
 * Permanently delete one batch of merged duplicates (those in Trash tagged with
 * _rm_merged_into). Deletes each duplicate's own files to reclaim space — but if
 * a kept (non-trashed) attachment still points at the same file path, the file is
 * preserved and only the duplicate post row is removed.
 */
function rm_mc_purge_batch( $limit ) {
	global $wpdb;
	$start  = microtime( true );
	$budget = (float) apply_filters( 'rm_mc_time_budget', 18.0 );

	$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} mm ON mm.post_id = p.ID AND mm.meta_key = '_rm_merged_into'
		 WHERE p.post_type = 'attachment' AND p.post_status = 'trash'
		 LIMIT %d",
		(int) $limit
	) ) );
	if ( ! $ids ) {
		return array( 'deleted' => 0, 'freed' => 0, 'remaining' => 0 );
	}
	$in = implode( ',', $ids );

	// Each duplicate's stored file path.
	$files = array();
	foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND post_id IN ($in)" ) as $r ) {
		$files[ (int) $r->post_id ] = (string) $r->meta_value;
	}
	// Which of those paths are still used by a kept (non-trashed) attachment?
	// Those files must NOT be deleted — only the duplicate post row goes.
	$protected = array();
	$paths     = array_values( array_unique( array_filter( $files ) ) );
	if ( $paths ) {
		$ph   = implode( ',', array_fill( 0, count( $paths ), '%s' ) );
		$keep = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key='_wp_attached_file' AND pm.meta_value IN ($ph)
			   AND p.post_status <> 'trash'",
			$paths
		) );
		foreach ( $keep as $kp ) {
			$protected[ $kp ] = true;
		}
	}

	$deleted = 0;
	$freed   = 0;
	foreach ( $ids as $id ) {
		$path   = isset( $files[ $id ] ) ? $files[ $id ] : '';
		$shared = ( '' !== $path && ! empty( $protected[ $path ] ) );
		if ( $shared ) {
			// Keep the shared file; just remove the duplicate post + its meta.
			$wpdb->delete( $wpdb->posts, array( 'ID' => $id ) );
			$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $id ) );
			clean_post_cache( $id );
		} else {
			// Unique file — measure it (incl. resized sub-files) then delete all.
			$abs = get_attached_file( $id );
			if ( $abs && is_file( $abs ) ) {
				$freed += (int) @filesize( $abs );
				$meta = wp_get_attachment_metadata( $id );
				if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
					$dir = trailingslashit( dirname( $abs ) );
					foreach ( $meta['sizes'] as $s ) {
						if ( ! empty( $s['file'] ) && is_file( $dir . $s['file'] ) ) {
							$freed += (int) @filesize( $dir . $s['file'] );
						}
					}
				}
			}
			wp_delete_attachment( $id, true );
		}
		$deleted++;
		if ( microtime( true ) - $start > $budget ) {
			break;
		}
	}

	return array(
		'deleted'   => $deleted,
		'freed'     => $freed,
		'remaining' => rm_mc_trashed_dupe_count(),
	);
}

/** AJAX: permanently delete a batch of merged duplicates (gated by DELETE token). */
add_action( 'wp_ajax_rm_mc_purge', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_mc', 'nonce', false ) ) {
		wp_send_json_error();
	}
	if ( ! isset( $_POST['confirm'] ) || 'DELETE' !== strtoupper( trim( wp_unslash( $_POST['confirm'] ) ) ) ) {
		wp_send_json_error();
	}
	$limit = max( 20, min( 400, (int) ( $_POST['limit'] ?? 200 ) ) );
	wp_send_json_success( rm_mc_purge_batch( $limit ) );
} );

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
	$mrows = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.meta_value FROM {$wpdb->postmeta} m WHERE " . rm_mc_image_key_sql( 'm' ) . " AND m.meta_value LIKE %s LIMIT 50",
		'%' . $wpdb->esc_like( (string) $id ) . '%'
	) );
	foreach ( $mrows as $mr ) {
		list( , $hit ) = rm_mc_replace_in_value( $mr->meta_value, $id, $id );
		if ( $hit ) {
			return true;
		}
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
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m WHERE " . rm_mc_image_key_sql( 'm' ) . " AND m.meta_value LIKE %s LIMIT 50",
		'%' . $wpdb->esc_like( (string) $id ) . '%'
	) );
	foreach ( $rows as $r ) {
		list( , $hit ) = rm_mc_replace_in_value( $r->meta_value, $id, $id );
		if ( $hit ) {
			return (int) $r->post_id;
		}
	}
	return 0;
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

/** AJAX: email the current media-cleanup stats. */
add_action( 'wp_ajax_rm_mc_email', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_mc', 'nonce', false ) ) {
		wp_send_json_error( array( 'msg' => 'Not allowed' ) );
	}
	$to = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
	if ( ! is_email( $to ) ) {
		wp_send_json_error( array( 'msg' => 'Invalid email address' ) );
	}
	$s      = rm_mc_stats();
	$unused = (int) get_option( 'rm_mc_unused_count', 0 );
	$site   = get_bloginfo( 'name' );
	$pct    = $s['images'] ? round( $s['indexed'] / $s['images'] * 100 ) : 0;
	$n      = 'number_format_i18n';
	$lines  = array(
		'Media Library cleanup — ' . $site,
		gmdate( 'Y-m-d H:i' ) . ' UTC',
		'',
		'Images in library:        ' . $n( $s['images'] ),
		'Indexed (hashed):         ' . $n( $s['indexed'] ) . ' (' . $pct . '%)',
		'Duplicate groups:         ' . $n( $s['groups'] ),
		'Removable duplicate files:' . ' ' . $n( $s['extra'] ),
		'Unused images (last scan):' . ' ' . $n( $unused ),
		'',
		'Manage: ' . admin_url( 'admin.php?page=ricoman-media-cleanup' ),
	);
	if ( $s['indexed'] < $s['images'] ) {
		$lines[] = '';
		$lines[] = 'Note: indexing is not complete, so the duplicate figures will rise as it finishes.';
	}
	$sent = wp_mail( $to, 'Media cleanup stats — ' . $site, implode( "\n", $lines ) );
	if ( $sent ) {
		wp_send_json_success( array( 'to' => $to ) );
	}
	wp_send_json_error( array( 'msg' => 'wp_mail failed (check the site can send email)' ) );
} );

/** AJAX: index one batch of un-hashed image attachments. */
add_action( 'wp_ajax_rm_mc_index', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_mc', 'nonce', false ) ) {
		wp_send_json_error();
	}
	global $wpdb;
	$batch = (int) rm_mc_batch();
	$cond  = rm_mc_image_file_cond( 'f.meta_value' );
	// Image attachments (by mime OR file extension) without a hash yet. No user
	// input — extensions + batch size are internal — so a built query is safe.
	$ids = $wpdb->get_col(
		"SELECT p.ID FROM {$wpdb->posts} p
		 JOIN {$wpdb->postmeta} f ON f.post_id=p.ID AND f.meta_key='_wp_attached_file'
		 LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_rm_sha1'
		 WHERE p.post_type='attachment' AND p.post_status<>'trash'
		 AND ( p.post_mime_type LIKE 'image/%' OR $cond )
		 AND m.meta_id IS NULL
		 ORDER BY p.ID ASC LIMIT " . $batch
	);
	foreach ( $ids as $id ) {
		rm_mc_hash( (int) $id, true );
	}
	$total = (int) $wpdb->get_var(
		"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
		 JOIN {$wpdb->postmeta} f ON f.post_id=p.ID AND f.meta_key='_wp_attached_file'
		 WHERE p.post_type='attachment' AND p.post_status<>'trash'
		 AND ( p.post_mime_type LIKE 'image/%' OR $cond )"
	);
	$done  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_rm_sha1'" );
	wp_send_json_success( array(
		'total' => $total,
		'done'  => $done,
		'next'  => count( $ids ) < $batch ? null : $done,
		'stats' => rm_mc_stats(),
	) );
} );
