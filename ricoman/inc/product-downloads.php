<?php
/**
 * Product Downloads manager — a dedicated, reliable screen for editing a
 * product's download files (the "Download Section" rows + the Family Datasheet).
 *
 * WHY THIS EXISTS: editing these ACF fields through the WordPress post editor
 * proved unreliable on this install (the block editor accepts the change and
 * says "saved", but silently fails to persist it). This screen writes the fields
 * directly via ACF's update_field() from a plain admin-post handler — no post
 * editor, no metaboxes — so a save always sticks.
 *
 * Reached at: admin.php?page=ricoman-product-downloads&product=<ID>
 * (linked from a "Manage downloads" button in the Product Page Editor).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register the (hidden) admin screen. */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'', // hidden — reached via the direct link only.
		__( 'Product Downloads', 'ricoman' ),
		__( 'Product Downloads', 'ricoman' ),
		'edit_posts',
		'ricoman-product-downloads',
		'ricoman_product_downloads_screen'
	);
} );

/** Filename/label for a stored attachment id (for display). */
function ricoman_pd_file_label( $id ) {
	$id = (int) $id;
	if ( ! $id ) {
		return '';
	}
	$file = get_attached_file( $id );
	return $file ? basename( $file ) : ( get_the_title( $id ) ?: ( 'Attachment #' . $id ) );
}

/** The screen. */
function ricoman_product_downloads_screen() {
	$pid = isset( $_GET['product'] ) ? (int) $_GET['product'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $pid || 'product' !== get_post_type( $pid ) || ! current_user_can( 'edit_post', $pid ) ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Product Downloads', 'ricoman' ) . '</h1><p>' . esc_html__( 'Product not found or you cannot edit it.', 'ricoman' ) . '</p></div>';
		return;
	}

	// Current values (raw = attachment ids, not formatted URLs).
	$rows = function_exists( 'get_field' ) ? get_field( 'download_section', $pid, false ) : array();
	$rows = is_array( $rows ) ? $rows : array();
	$fam  = function_exists( 'get_field' ) ? (int) get_field( 'download_family_datasheet', $pid, false ) : 0;

	$saved  = isset( $_GET['saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$editor = admin_url( 'admin.php?page=ricoman-product-editor&product=' . $pid );

	echo '<div class="wrap rm-pd-wrap" style="max-width:920px">';
	echo '<h1>' . esc_html__( 'Downloads', 'ricoman' ) . ' — ' . esc_html( get_the_title( $pid ) ) . '</h1>';
	if ( $saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Downloads saved.', 'ricoman' ) . '</p></div>';
	}
	echo '<p class="description">' . esc_html__( 'Add, replace or remove the PDF (or other) files shown in the Downloads section of this product page. Changes save straight away.', 'ricoman' ) . '</p>';
	echo '<p><a href="' . esc_url( $editor ) . '">&larr; ' . esc_html__( 'Back to the product editor', 'ricoman' ) . '</a> &nbsp; | &nbsp; <a href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the product page', 'ricoman' ) . ' &nearr;</a></p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="ricoman_save_product_downloads">';
	echo '<input type="hidden" name="product" value="' . esc_attr( $pid ) . '">';
	wp_nonce_field( 'ricoman_pd_' . $pid );

	// ---- Download Section rows ----
	echo '<h2>' . esc_html__( 'Download files', 'ricoman' ) . '</h2>';
	echo '<table class="widefat striped rm-pd-table"><thead><tr><th style="width:40%">' . esc_html__( 'Title', 'ricoman' ) . '</th><th>' . esc_html__( 'File', 'ricoman' ) . '</th><th style="width:80px">' . esc_html__( 'Remove', 'ricoman' ) . '</th></tr></thead><tbody id="rm-pd-rows">';

	$render_row = function ( $i, $title, $file_id ) {
		$file_id = (int) $file_id;
		$label   = ricoman_pd_file_label( $file_id );
		$out  = '<tr class="rm-pd-row">';
		$out .= '<td><input type="text" name="rows[' . $i . '][title]" value="' . esc_attr( $title ) . '" class="regular-text" placeholder="e.g. Datasheet" style="width:100%"></td>';
		$out .= '<td class="rm-pd-file">';
		$out .= '<input type="hidden" class="rm-pd-file-id" name="rows[' . $i . '][file]" value="' . esc_attr( $file_id ) . '">';
		$out .= '<span class="rm-pd-file-name" style="margin-right:10px">' . ( $label ? esc_html( $label ) : '<em style="color:#888">' . esc_html__( 'No file', 'ricoman' ) . '</em>' ) . '</span>';
		$out .= '<button type="button" class="button rm-pd-pick">' . esc_html__( 'Choose / replace file', 'ricoman' ) . '</button>';
		$out .= '</td>';
		$out .= '<td style="text-align:center"><button type="button" class="button-link rm-pd-del" style="color:#b32d2e">' . esc_html__( 'Delete', 'ricoman' ) . '</button></td>';
		$out .= '</tr>';
		return $out;
	};

	$i = 0;
	foreach ( $rows as $row ) {
		$title = isset( $row['download-title'] ) ? (string) $row['download-title'] : '';
		$fid   = isset( $row['download-file'] ) ? $row['download-file'] : 0;
		echo $render_row( $i, $title, $fid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$i++;
	}
	echo '</tbody></table>';
	echo '<p><button type="button" class="button" id="rm-pd-add">+ ' . esc_html__( 'Add a download', 'ricoman' ) . '</button></p>';

	// Row template for the "Add" button.
	echo '<template id="rm-pd-tpl">' . $render_row( '__i__', '', 0 ) . '</template>';

	// ---- Family datasheet (separate single field) ----
	echo '<h2 style="margin-top:2em">' . esc_html__( 'Family datasheet', 'ricoman' ) . '</h2>';
	echo '<p class="description">' . esc_html__( 'The single "Family datasheet" link shown on the product page. Leave empty to remove it.', 'ricoman' ) . '</p>';
	echo '<div class="rm-pd-file" style="margin:.5em 0">';
	echo '<input type="hidden" class="rm-pd-file-id" name="family_file" value="' . esc_attr( $fam ) . '">';
	echo '<span class="rm-pd-file-name" style="margin-right:10px">' . ( $fam ? esc_html( ricoman_pd_file_label( $fam ) ) : '<em style="color:#888">' . esc_html__( 'No file', 'ricoman' ) . '</em>' ) . '</span>';
	echo '<button type="button" class="button rm-pd-pick">' . esc_html__( 'Choose / replace file', 'ricoman' ) . '</button> ';
	echo '<button type="button" class="button-link rm-pd-clear" style="color:#b32d2e">' . esc_html__( 'Remove', 'ricoman' ) . '</button>';
	echo '</div>';

	echo '<p style="margin-top:1.6em"><button type="submit" class="button button-primary button-large">' . esc_html__( 'Save downloads', 'ricoman' ) . '</button></p>';
	echo '</form></div>';

	// Media picker + row add/delete JS.
	$js = <<<'JS'
(function($){
  function pick(btn){
    var wrap = btn.closest('.rm-pd-file');
    var frame = wp.media({ title:'Select file', button:{text:'Use this file'}, multiple:false });
    frame.on('select', function(){
      var a = frame.state().get('selection').first().toJSON();
      wrap.querySelector('.rm-pd-file-id').value = a.id;
      wrap.querySelector('.rm-pd-file-name').textContent = a.filename || a.title || ('Attachment #' + a.id);
    });
    frame.open();
  }
  document.addEventListener('click', function(e){
    var t = e.target;
    if (t.classList.contains('rm-pd-pick')) { e.preventDefault(); pick(t); }
    else if (t.classList.contains('rm-pd-del')) { e.preventDefault(); var r = t.closest('.rm-pd-row'); if(r) r.remove(); }
    else if (t.classList.contains('rm-pd-clear')) {
      e.preventDefault();
      var wrap = t.closest('.rm-pd-file');
      wrap.querySelector('.rm-pd-file-id').value = '';
      wrap.querySelector('.rm-pd-file-name').innerHTML = '<em style="color:#888">No file</em>';
    }
    else if (t.id === 'rm-pd-add') {
      e.preventDefault();
      var tpl = document.getElementById('rm-pd-tpl').innerHTML.replace(/__i__/g, 'n' + Date.now());
      document.getElementById('rm-pd-rows').insertAdjacentHTML('beforeend', tpl);
    }
  });
})(jQuery);
JS;
	echo '<script>' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/** Enqueue the media library on this screen. */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( isset( $_GET['page'] ) && 'ricoman-product-downloads' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_enqueue_media();
	}
} );

/** Save handler — writes the fields directly (reliable). */
add_action( 'admin_post_ricoman_save_product_downloads', function () {
	$pid = isset( $_POST['product'] ) ? (int) $_POST['product'] : 0;
	if ( ! $pid || ! current_user_can( 'edit_post', $pid ) ) {
		wp_die( esc_html__( 'You cannot edit this product.', 'ricoman' ) );
	}
	check_admin_referer( 'ricoman_pd_' . $pid );

	// Build the download_section rows.
	$rows_in = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : array(); // phpcs:ignore
	$save    = array();
	foreach ( $rows_in as $row ) {
		$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
		$file  = isset( $row['file'] ) ? (int) $row['file'] : 0;
		if ( '' === $title && ! $file ) {
			continue; // skip empty rows.
		}
		$save[] = array(
			'download-title' => $title,
			'download-file'  => $file ? $file : '',
		);
	}

	$fam = isset( $_POST['family_file'] ) ? (int) $_POST['family_file'] : 0;

	if ( function_exists( 'update_field' ) ) {
		update_field( 'download_section', $save, $pid );
		update_field( 'download_family_datasheet', $fam ? $fam : '', $pid );
	}

	// Bust caches so the product page + Downloads page reflect it immediately.
	clean_post_cache( $pid );
	update_post_meta( $pid, '_rm_secver', (string) time() );
	update_option( 'rm_products_ver', ( (int) get_option( 'rm_products_ver', 1 ) ) + 1, false );
	update_option( 'rm_pagecache_ver', (string) time(), false );

	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-product-downloads&product=' . $pid . '&saved=1' ) );
	exit;
} );
