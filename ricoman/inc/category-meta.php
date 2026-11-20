<?php
/**
 * Per-category images for product categories (product-cat).
 *
 * Adds two image fields — Studio and In-situ — to each product category in the
 * admin, with the standard WordPress media picker. The /products/ category tiles
 * show the In-situ image by default with a Studio/In-situ toggle; both fall back
 * to an auto-derived product image when not set.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The product category taxonomy slug actually in use. */
function ricoman_cat_tax() {
	return taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
}

/**
 * Editable SEO copy for a product category. 'intro' renders above the product
 * grid (keyword-rich landing copy + helps users); 'body' renders below the grid
 * (longer description / FAQ, kept out of the way of the products). Basic HTML.
 */
function ricoman_cat_seo( $term_id, $which = 'intro' ) {
	$key = 'body' === $which ? '_rm_cat_seo_body' : '_rm_cat_seo_intro';
	return (string) get_term_meta( (int) $term_id, $key, true );
}

/**
 * Manual display order for a product category (lower = earlier). 0 / unset means
 * "no manual order" so the term sorts after ordered ones by product count.
 */
function ricoman_cat_order( $term_id ) {
	$v = get_term_meta( (int) $term_id, '_rm_cat_order', true );
	return ( '' === $v || null === $v ) ? 0 : (int) $v;
}

/**
 * Sort product-cat terms by manual display order (ASC) first, then by a fallback
 * for any without a manual order: 'count' (most products first, default — used by
 * the homepage range grid and /products/ tiles) or 'name' (alphabetical — used by
 * the mega menu). Shared so every listing honours the same arrangement.
 */
function ricoman_cat_sort_terms( $terms, $fallback = 'count' ) {
	$terms = array_values( (array) $terms );
	usort( $terms, function ( $a, $b ) use ( $fallback ) {
		$oa = ricoman_cat_order( $a->term_id );
		$ob = ricoman_cat_order( $b->term_id );
		// Terms with a manual order come first, in that order.
		$ha = $oa > 0 ? 0 : 1;
		$hb = $ob > 0 ? 0 : 1;
		if ( $ha !== $hb ) {
			return $ha - $hb;
		}
		if ( $oa !== $ob && 0 !== $oa && 0 !== $ob ) {
			return $oa - $ob;
		}
		// Tie-break (and the unordered remainder).
		if ( 'name' === $fallback ) {
			return strcasecmp( (string) $a->name, (string) $b->name );
		}
		return (int) $b->count - (int) $a->count;
	} );
	return $terms;
}

/**
 * A category image URL for a given style ('insitu' | 'studio'). Uses the term's
 * own image meta if set, else falls back to the auto-derived product image.
 */
function ricoman_cat_img( $term_id, $which = 'insitu', $tax = '' ) {
	$tax = $tax ? $tax : ricoman_cat_tax();
	$key = 'studio' === $which ? '_rm_cat_img_studio' : '_rm_cat_img_insitu';
	$id  = (int) get_term_meta( (int) $term_id, $key, true );
	if ( $id ) {
		$u = wp_get_attachment_image_url( $id, 'large' );
		if ( $u ) {
			return $u;
		}
	}
	return function_exists( 'ricoman_category_image' ) ? ricoman_category_image( (int) $term_id, $tax ) : '';
}

/* ---------------------------------------------------------------- admin UI -- */

/** One image picker row (label + preview + choose/remove buttons + hidden id). */
function ricoman_cat_img_field( $key, $label, $help, $value = 0 ) {
	$url = $value ? wp_get_attachment_image_url( (int) $value, 'medium' ) : '';
	if ( $value && ! $url ) { // migrated images may have no "medium" sub-size on disk.
		$url = wp_get_attachment_url( (int) $value );
	}
	ob_start();
	?>
	<div class="rm-catimg-field" style="margin:6px 0 14px">
		<label style="display:block;font-weight:600;margin-bottom:6px"><?php echo esc_html( $label ); ?></label>
		<div class="rm-catimg-preview" style="margin-bottom:8px">
			<img src="<?php echo esc_url( $url ); ?>" alt="" style="max-width:160px;height:auto;border:1px solid #dcdcde;border-radius:6px;<?php echo $url ? '' : 'display:none'; ?>">
		</div>
		<input type="hidden" class="rm-catimg-id" name="<?php echo esc_attr( $key ); ?>" value="<?php echo (int) $value; ?>">
		<button type="button" class="button rm-catimg-pick">Select image</button>
		<button type="button" class="button-link rm-catimg-clear" style="margin-left:8px;<?php echo $url ? '' : 'display:none'; ?>">Remove</button>
		<p class="description" style="margin-top:6px"><?php echo esc_html( $help ); ?></p>
	</div>
	<?php
	return ob_get_clean();
}

/** A textarea field (label + help) for SEO copy. Basic HTML allowed. */
function ricoman_cat_text_field( $key, $label, $help, $value = '', $rows = 4 ) {
	ob_start();
	?>
	<div class="rm-cattext-field" style="margin:6px 0 14px">
		<label for="<?php echo esc_attr( $key ); ?>" style="display:block;font-weight:600;margin-bottom:6px"><?php echo esc_html( $label ); ?></label>
		<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="<?php echo (int) $rows; ?>" style="width:100%;max-width:640px"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description" style="margin-top:6px"><?php echo esc_html( $help ); ?></p>
	</div>
	<?php
	return ob_get_clean();
}

/** A small number field (label + help) for the manual display order. */
function ricoman_cat_order_field( $value = 0 ) {
	ob_start();
	?>
	<div class="rm-catorder-field" style="margin:6px 0 14px">
		<label for="_rm_cat_order" style="display:block;font-weight:600;margin-bottom:6px">Display order</label>
		<input type="number" id="_rm_cat_order" name="_rm_cat_order" min="0" step="1" value="<?php echo (int) $value; ?>" style="width:90px">
		<p class="description" style="margin-top:6px">Lower numbers show first on the Products page and homepage range grid. Leave 0 to sort automatically by number of products.</p>
	</div>
	<?php
	return ob_get_clean();
}

/** Add-category screen (fields stacked, no table cell). */
add_action( 'admin_init', function () {
	$tax = ricoman_cat_tax();
	add_action( "{$tax}_add_form_fields", function () {
		echo '<div class="form-field">';
		echo ricoman_cat_img_field( '_rm_cat_img_insitu', 'In-situ image (lifestyle)', 'Shown by default on the Products page tiles.' );
		echo ricoman_cat_img_field( '_rm_cat_img_studio', 'Studio image (on white)', 'Shown when the visitor toggles to “Studio”.' );
		echo ricoman_cat_order_field( 0 );
		echo ricoman_cat_text_field( '_rm_cat_seo_intro', 'SEO intro (above products)', 'Short keyword-rich copy shown under the category heading. Basic HTML allowed.', '', 4 );
		echo ricoman_cat_text_field( '_rm_cat_seo_body', 'SEO body / FAQ (below products)', 'Longer description or FAQ shown beneath the product grid. Basic HTML allowed.', '', 8 );
		echo '</div>';
	} );

	// Edit-category screen (table rows).
	add_action( "{$tax}_edit_form_fields", function ( $term ) {
		$insitu = (int) get_term_meta( $term->term_id, '_rm_cat_img_insitu', true );
		$studio = (int) get_term_meta( $term->term_id, '_rm_cat_img_studio', true );
		echo '<tr class="form-field"><th scope="row">Category images</th><td>';
		echo ricoman_cat_img_field( '_rm_cat_img_insitu', 'In-situ image (lifestyle)', 'Shown by default on the Products page tiles.', $insitu );
		echo ricoman_cat_img_field( '_rm_cat_img_studio', 'Studio image (on white)', 'Shown when the visitor toggles to “Studio”.', $studio );
		echo '</td></tr>';

		echo '<tr class="form-field"><th scope="row">Display order</th><td>';
		echo ricoman_cat_order_field( ricoman_cat_order( $term->term_id ) );
		echo '</td></tr>';

		echo '<tr class="form-field"><th scope="row">SEO copy</th><td>';
		echo ricoman_cat_text_field( '_rm_cat_seo_intro', 'SEO intro (above products)', 'Short keyword-rich copy shown under the category heading. Basic HTML allowed.', ricoman_cat_seo( $term->term_id, 'intro' ), 4 );
		echo ricoman_cat_text_field( '_rm_cat_seo_body', 'SEO body / FAQ (below products)', 'Longer description or FAQ shown beneath the product grid. Basic HTML allowed.', ricoman_cat_seo( $term->term_id, 'body' ), 8 );
		echo '</td></tr>';
	} );
} );

/** Save images, display order and SEO copy on create + edit. */
function ricoman_cat_img_save( $term_id ) {
	foreach ( array( '_rm_cat_img_insitu', '_rm_cat_img_studio' ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$id = (int) $_POST[ $key ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $id > 0 ) {
				update_term_meta( $term_id, $key, $id );
			} else {
				delete_term_meta( $term_id, $key );
			}
		}
	}

	// Manual display order (0 / empty = automatic; store nothing).
	if ( isset( $_POST['_rm_cat_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ord = max( 0, (int) $_POST['_rm_cat_order'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $ord > 0 ) {
			update_term_meta( $term_id, '_rm_cat_order', $ord );
		} else {
			delete_term_meta( $term_id, '_rm_cat_order' );
		}
	}

	// SEO copy (basic HTML allowed; empty clears).
	foreach ( array( '_rm_cat_seo_intro', '_rm_cat_seo_body' ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$val = wp_kses_post( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' !== trim( $val ) ) {
				update_term_meta( $term_id, $key, $val );
			} else {
				delete_term_meta( $term_id, $key );
			}
		}
	}

	// Category imagery / order / copy changed — refresh the cached tiles.
	if ( function_exists( 'ricoman_products_ver' ) ) {
		update_option( 'rm_products_ver', (string) time(), false );
	}
}
// Bind the save to BOTH possible taxonomy names. These add_action calls run at
// file-load time, before the taxonomy is registered on `init`, so ricoman_cat_tax()
// would resolve to the wrong name here and the save would never fire. Registering
// for both names is harmless and guarantees it runs for the real taxonomy.
foreach ( array( 'product-cat', 'product_cat' ) as $rm_save_tax ) {
	add_action( 'created_' . $rm_save_tax, 'ricoman_cat_img_save' );
	add_action( 'edited_' . $rm_save_tax, 'ricoman_cat_img_save' );
}

/** Show the manual display order as a column in the category list table. */
add_action( 'admin_init', function () {
	$tax = ricoman_cat_tax();
	add_filter( "manage_edit-{$tax}_columns", function ( $cols ) {
		$cols['rm_cat_order'] = __( 'Order', 'ricoman' );
		return $cols;
	} );
	add_filter( "manage_{$tax}_custom_column", function ( $out, $col, $term_id ) {
		if ( 'rm_cat_order' === $col ) {
			$ord = ricoman_cat_order( $term_id );
			return $ord > 0 ? (string) $ord : '<span style="color:#a7aaad">—</span>';
		}
		return $out;
	}, 10, 3 );
} );

/** Media picker JS on the category screens. */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
		return;
	}
	$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ricoman_cat_tax() !== $tax ) {
		return;
	}
	wp_enqueue_media();
	$js = <<<'JS'
(function($){
	function bind(scope){
		$(scope).on('click','.rm-catimg-pick',function(e){
			e.preventDefault();
			var $f=$(this).closest('.rm-catimg-field');
			var frame=wp.media({title:'Select category image',multiple:false,library:{type:'image'}});
			frame.on('select',function(){
				var a=frame.state().get('selection').first().toJSON();
				var url=(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url);
				$f.find('.rm-catimg-id').val(a.id);
				$f.find('.rm-catimg-preview img').attr('src',url).show();
				$f.find('.rm-catimg-clear').show();
			});
			frame.open();
		});
		$(scope).on('click','.rm-catimg-clear',function(e){
			e.preventDefault();
			var $f=$(this).closest('.rm-catimg-field');
			$f.find('.rm-catimg-id').val(0);
			$f.find('.rm-catimg-preview img').attr('src','').hide();
			$(this).hide();
		});
	}
	$(function(){ bind(document); });
	// The add-category form is cleared via AJAX after adding a term.
	$(document).ajaxComplete(function(){ });
})(jQuery);
JS;
	wp_add_inline_script( 'jquery-core', $js );
} );

/* ============================================================ *
 * Drag-to-reorder categories (simple back-end ordering)
 *
 * "Display order" can be set per-category, but typing numbers is fiddly. This
 * adds a Ricoman → Reorder Categories screen: drag the rows into the order you
 * want and Save — it writes a clean 1..N _rm_cat_order across all product-cat
 * terms (the same meta the mega menu, /products/ tiles and homepage grid read).
 * ============================================================ */
add_action( 'admin_menu', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_submenu_page(
		'ricoman-hub',
		__( 'Reorder Categories', 'ricoman' ),
		__( 'Reorder Categories', 'ricoman' ),
		'manage_options',
		'ricoman-cat-order',
		'ricoman_cat_order_page'
	);
}, 31 );

function ricoman_cat_order_page() {
	$tax   = ricoman_cat_tax();
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}
	$terms = ricoman_cat_sort_terms( $terms, 'name' );

	echo '<div class="wrap"><h1>' . esc_html__( 'Reorder Categories', 'ricoman' ) . '</h1>';
	echo '<p>' . esc_html__( 'Drag the categories into the order you want them to appear — across the mega menu, the /products/ tiles and the homepage range grid — then Save.', 'ricoman' )
		. ' ' . sprintf(
			/* translators: %s: link to the Reorder Products screen */
			esc_html__( 'To change the order of products inside a category, use %s.', 'ricoman' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=ricoman-product-order' ) ) . '"><strong>' . esc_html__( 'Reorder Products', 'ricoman' ) . '</strong></a>'
		) . '</p>';

	if ( ! $terms ) {
		echo '<p><em>' . esc_html__( 'No product categories found.', 'ricoman' ) . '</em></p></div>';
		return;
	}

	echo '<p><span id="rm-catord-status" style="font-weight:600"></span></p>';
	echo '<ol id="rm-catord-list" style="list-style:none;margin:0;padding:0;max-width:560px">';
	foreach ( $terms as $t ) {
		echo '<li class="rm-catord-row" data-id="' . esc_attr( $t->term_id ) . '" style="display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:10px 14px;margin:0 0 8px;cursor:grab">'
			. '<span aria-hidden="true" style="color:#a7aaad;font-size:18px;line-height:1">⠿</span>'
			. '<strong style="flex:1">' . esc_html( $t->name ) . '</strong>'
			. '<span style="color:#646970;font-size:12px">' . esc_html( sprintf( _n( '%s product', '%s products', $t->count, 'ricoman' ), number_format_i18n( $t->count ) ) ) . '</span>'
			. '</li>';
	}
	echo '</ol>';
	echo '<p><button class="button button-primary" id="rm-catord-save">' . esc_html__( 'Save order', 'ricoman' ) . '</button></p>';
	echo '</div>';

	$nonce = wp_create_nonce( 'rm_cat_order' );
	$ajax  = admin_url( 'admin-ajax.php' );
	wp_enqueue_script( 'jquery-ui-sortable' );
	$js = <<<JS
jQuery(function($){
	$('#rm-catord-list').sortable({axis:'y',cursor:'grabbing',placeholder:'rm-catord-ph'});
	$('#rm-catord-save').on('click',function(){
		var btn=$(this).prop('disabled',true);
		var ids=$('#rm-catord-list .rm-catord-row').map(function(){return $(this).data('id');}).get();
		$('#rm-catord-status').css('color','#646970').text('Saving…');
		$.post(%s,{action:'rm_cat_reorder',nonce:%s,ids:ids}).done(function(r){
			if(r&&r.success){ $('#rm-catord-status').css('color','#00a32a').text('✓ Saved — order applied across the site.'); }
			else { $('#rm-catord-status').css('color','#d63638').text('Could not save. Reload and try again.'); }
		}).fail(function(){ $('#rm-catord-status').css('color','#d63638').text('Network error. Try again.'); })
		.always(function(){ btn.prop('disabled',false); });
	});
});
JS;
	$js = sprintf( $js, wp_json_encode( $ajax ), wp_json_encode( $nonce ) );
	wp_add_inline_script( 'jquery-ui-sortable', $js );

	echo '<style>#rm-catord-list .rm-catord-ph{height:44px;border:2px dashed #c3c4c7;border-radius:8px;margin:0 0 8px;background:#f6f7f7}</style>';
}

/** Point people to the drag-to-reorder tool from the Product Categories screen. */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'edit-' . ricoman_cat_tax() !== $screen->id ) {
		return;
	}
	$url = admin_url( 'admin.php?page=ricoman-cat-order' );
	echo '<div class="notice notice-info"><p>↕ <strong>' . esc_html__( 'Want to change the order categories appear in', 'ricoman' ) . '</strong> '
		. esc_html__( '(mega menu, /products/ tiles, homepage grid)? Drag them into order here:', 'ricoman' )
		. ' <a href="' . esc_url( $url ) . '" class="button button-primary button-small">' . esc_html__( 'Reorder Categories', 'ricoman' ) . '</a></p></div>';
} );

/** AJAX: persist the dragged category order as a clean 1..N _rm_cat_order. */
add_action( 'wp_ajax_rm_cat_reorder', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_cat_order', 'nonce', false ) ) {
		wp_send_json_error();
	}
	$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
	$ids = array_values( array_filter( $ids ) );
	if ( ! $ids ) {
		wp_send_json_error();
	}
	$tax = ricoman_cat_tax();
	$pos = 1;
	foreach ( $ids as $term_id ) {
		$term = get_term( $term_id, $tax );
		if ( $term && ! is_wp_error( $term ) ) {
			update_term_meta( $term_id, '_rm_cat_order', $pos );
			$pos++;
		}
	}
	// Bust the cached category tiles so the new order shows immediately.
	update_option( 'rm_products_ver', (string) time(), false );
	wp_send_json_success( array( 'count' => $pos - 1 ) );
} );

/* ============================================================ *
 * Drag-to-reorder PRODUCTS within a category
 *
 * Products list by their native "Order" (menu_order). Typing a number on each
 * product is fiddly, so this gives the same drag UI as categories: pick a
 * category, drag its products into order, Save → writes menu_order 1..N.
 * ============================================================ */
add_action( 'admin_menu', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_submenu_page(
		'ricoman-hub',
		__( 'Reorder Products', 'ricoman' ),
		__( 'Reorder Products', 'ricoman' ),
		'manage_options',
		'ricoman-product-order',
		'ricoman_product_order_page'
	);
}, 32 );

function ricoman_product_order_page() {
	$tax   = ricoman_cat_tax();
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}
	$terms = ricoman_cat_sort_terms( $terms, 'name' );
	$cur   = isset( $_GET['cat'] ) ? sanitize_title( wp_unslash( $_GET['cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	echo '<div class="wrap"><h1>' . esc_html__( 'Reorder Products', 'ricoman' ) . '</h1>';
	echo '<p>' . esc_html__( 'Pick a category, then drag its products into the order you want them to appear on the category page and product tiles. Save writes each product\'s "Order" attribute.', 'ricoman' )
		. ' ' . sprintf(
			/* translators: %s: link to the Reorder Categories screen */
			esc_html__( 'To change the order of the categories themselves, use %s.', 'ricoman' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=ricoman-cat-order' ) ) . '"><strong>' . esc_html__( 'Reorder Categories', 'ricoman' ) . '</strong></a>'
		) . '</p>';

	// Category picker (reloads the page for the chosen category).
	echo '<form method="get" style="margin:0 0 18px"><input type="hidden" name="page" value="ricoman-product-order">';
	echo '<select name="cat" onchange="this.form.submit()" style="min-width:280px"><option value="">' . esc_html__( '— Choose a category —', 'ricoman' ) . '</option>';
	echo '<option value="__all"' . selected( $cur, '__all', false ) . '>' . esc_html__( 'All products (site-wide order)', 'ricoman' ) . '</option>';
	foreach ( $terms as $t ) {
		echo '<option value="' . esc_attr( $t->slug ) . '"' . selected( $cur, $t->slug, false ) . '>' . esc_html( $t->name ) . ' (' . (int) $t->count . ')</option>';
	}
	echo '</select> <noscript><button class="button">' . esc_html__( 'Go', 'ricoman' ) . '</button></noscript></form>';

	if ( ! $cur ) {
		echo '</div>';
		return;
	}

	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 600,
		'no_found_rows'  => true,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
	);
	// "__all" = every product, in the exact order the All Products page uses
	// (menu_order then title) — dragging here controls that page directly.
	// Accessories are excluded: the page hides them by default, and their
	// relative order still holds when the Accessories filter is picked.
	if ( '__all' !== $cur ) {
		$args['tax_query'] = array( array( 'taxonomy' => $tax, 'field' => 'slug', 'terms' => $cur ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	} else {
		$acy = get_term_by( 'name', 'Accessories', $tax );
		if ( $acy ) {
			$args['tax_query'] = array( array( 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => (int) $acy->term_id, 'operator' => 'NOT IN' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'OR',
			array( 'key' => 'is_accessories_product', 'compare' => 'NOT EXISTS' ),
			array( 'key' => 'is_accessories_product', 'value' => array( '1', 'yes', 'true' ), 'compare' => 'NOT IN' ),
		);
	}
	$q = new WP_Query( $args );

	if ( ! $q->have_posts() ) {
		echo '<p><em>' . esc_html__( 'No products in this category.', 'ricoman' ) . '</em></p></div>';
		return;
	}

	if ( '__all' === $cur ) {
		echo '<p style="max-width:620px;color:#646970">' . esc_html__( 'This is the order the All Products page uses (accessories are left out — order those in the Accessories category). Heads-up: saving a single category\'s order afterwards renumbers those products from 1, which pulls them to the front of this list — reorder categories first, then fine-tune here.', 'ricoman' ) . '</p>';
	}

	echo '<p><span id="rm-prodord-status" style="font-weight:600"></span></p>';
	echo '<ol id="rm-prodord-list" style="list-style:none;margin:0;padding:0;max-width:620px">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$thumb = get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' );
		echo '<li class="rm-prodord-row" data-id="' . esc_attr( get_the_ID() ) . '" style="display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:8px 14px;margin:0 0 8px;cursor:grab">'
			. '<span aria-hidden="true" style="color:#a7aaad;font-size:18px;line-height:1">⠿</span>'
			. ( $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px">' : '<span style="width:40px;height:40px;background:#f0f0f1;border-radius:4px;display:inline-block"></span>' )
			. '<strong style="flex:1">' . esc_html( get_the_title() ) . '</strong>'
			. '</li>';
	}
	echo '</ol>';
	wp_reset_postdata();
	echo '<p><button class="button button-primary" id="rm-prodord-save">' . esc_html__( 'Save order', 'ricoman' ) . '</button></p>';
	echo '</div>';

	$nonce = wp_create_nonce( 'rm_product_order' );
	$ajax  = admin_url( 'admin-ajax.php' );
	wp_enqueue_script( 'jquery-ui-sortable' );
	$js = <<<JS
jQuery(function($){
	$('#rm-prodord-list').sortable({axis:'y',cursor:'grabbing',placeholder:'rm-prodord-ph'});
	$('#rm-prodord-save').on('click',function(){
		var btn=$(this).prop('disabled',true);
		var ids=$('#rm-prodord-list .rm-prodord-row').map(function(){return $(this).data('id');}).get();
		$('#rm-prodord-status').css('color','#646970').text('Saving…');
		$.post(%s,{action:'rm_product_reorder',nonce:%s,ids:ids}).done(function(r){
			if(r&&r.success){ $('#rm-prodord-status').css('color','#00a32a').text('✓ Saved — order applied.'); }
			else { $('#rm-prodord-status').css('color','#d63638').text('Could not save. Reload and try again.'); }
		}).fail(function(){ $('#rm-prodord-status').css('color','#d63638').text('Network error. Try again.'); })
		.always(function(){ btn.prop('disabled',false); });
	});
});
JS;
	$js = sprintf( $js, wp_json_encode( $ajax ), wp_json_encode( $nonce ) );
	wp_add_inline_script( 'jquery-ui-sortable', $js );
	echo '<style>#rm-prodord-list .rm-prodord-ph{height:58px;border:2px dashed #c3c4c7;border-radius:8px;margin:0 0 8px;background:#f6f7f7}</style>';
}

/** AJAX: persist the dragged product order as menu_order 1..N. */
add_action( 'wp_ajax_rm_product_reorder', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_product_order', 'nonce', false ) ) {
		wp_send_json_error();
	}
	$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
	$ids = array_values( array_filter( $ids ) );
	if ( ! $ids ) {
		wp_send_json_error();
	}
	$pos = 1;
	foreach ( $ids as $pid ) {
		if ( 'product' === get_post_type( $pid ) ) {
			wp_update_post( array( 'ID' => $pid, 'menu_order' => $pos ) );
			$pos++;
		}
	}
	update_option( 'rm_products_ver', (string) time(), false );
	wp_send_json_success( array( 'count' => $pos - 1 ) );
} );
