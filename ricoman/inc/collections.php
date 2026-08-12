<?php
/**
 * Product "Collections" — group a whole range together (e.g. Zodiac = its tracks
 * AND luminaires) so customers can browse a range in one place. A public, flat
 * taxonomy on the `product` post type. Each collection carries:
 *   - an image (_rm_col_image, attachment ID) for its card + landing hero,
 *   - a short description (_rm_col_desc) for the card,
 *   - a rich body (_rm_col_body) for the "Know more" landing page,
 *   - an optional display order (_rm_col_order).
 * ONE collection per product — enforced by the single-select picker in the
 * Product Page Editor (see inc/product-editor.php).
 *
 * This file is the data + admin layer. The public /products/ "Collections" tab,
 * cards, filter, landing page and browse view are built on top of these helpers.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------------
 * Taxonomy registration
 * ------------------------------------------------------------------------- */
add_action( 'init', function () {
	register_taxonomy(
		'collection',
		'product',
		array(
			'labels'            => array(
				'name'          => __( 'Collections', 'ricoman' ),
				'singular_name' => __( 'Collection', 'ricoman' ),
				'menu_name'     => __( 'Collections', 'ricoman' ),
				'add_new_item'  => __( 'Add Collection', 'ricoman' ),
				'edit_item'     => __( 'Edit Collection', 'ricoman' ),
				'search_items'  => __( 'Search Collections', 'ricoman' ),
				'not_found'     => __( 'No collections yet', 'ricoman' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'collection', 'with_front' => false ),
		)
	);
}, 9 );

// Flush rewrite rules ONCE so the /collection/<slug>/ archive URLs resolve.
add_action( 'init', function () {
	if ( '1' !== get_option( 'rm_collection_rw', '' ) ) {
		flush_rewrite_rules( false );
		update_option( 'rm_collection_rw', '1', false );
	}
}, 11 );

/* ---------------------------------------------------------------------------
 * Front-end data helpers
 * ------------------------------------------------------------------------- */

/** Card/hero image URL for a collection (from _rm_col_image attachment id). */
function ricoman_collection_image( $term_id, $size = 'large' ) {
	$id = (int) get_term_meta( (int) $term_id, '_rm_col_image', true );
	if ( $id ) {
		$url = wp_get_attachment_image_url( $id, $size );
		if ( $url ) {
			return $url;
		}
	}
	return '';
}

/** Short description shown on the collection card. Falls back to term description. */
function ricoman_collection_desc( $term_id ) {
	$d = (string) get_term_meta( (int) $term_id, '_rm_col_desc', true );
	if ( '' === trim( $d ) ) {
		$t = get_term( (int) $term_id, 'collection' );
		$d = ( $t && ! is_wp_error( $t ) ) ? (string) $t->description : '';
	}
	return trim( $d );
}

/** Rich body (basic HTML) for the "Know more" landing page. */
function ricoman_collection_body( $term_id ) {
	return (string) get_term_meta( (int) $term_id, '_rm_col_body', true );
}

/** Manual display order (0 = default). */
function ricoman_collection_order( $term_id ) {
	return (int) get_term_meta( (int) $term_id, '_rm_col_order', true );
}

/** Published product IDs in a collection, ordered by menu_order then title. */
function ricoman_collection_products( $term_id ) {
	$ids = get_objects_in_term( (int) $term_id, 'collection' );
	if ( is_wp_error( $ids ) || empty( $ids ) ) {
		return array();
	}
	$q = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'post__in'       => array_map( 'intval', $ids ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'no_found_rows'  => true,
	) );
	return $q->posts;
}

/**
 * "X products · X variants" counts for a collection. Cached against
 * rm_products_ver so it refreshes when the catalogue changes (variant count
 * fires a query per product, so we never recompute it in a render loop).
 */
function ricoman_collection_counts( $term_id ) {
	$term_id = (int) $term_id;
	$ver     = (string) get_option( 'rm_products_ver', '1' );
	$key     = 'rm_colcnt_' . $ver . '_' . $term_id;
	$cached  = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$prods    = ricoman_collection_products( $term_id );
	$variants = 0;
	if ( function_exists( 'ricoman_pf_variant_count' ) ) {
		foreach ( $prods as $pid ) {
			$variants += (int) ricoman_pf_variant_count( $pid );
		}
	}
	$out = array( 'products' => count( $prods ), 'variants' => $variants );
	set_transient( $key, $out, DAY_IN_SECONDS );
	return $out;
}

/** All collection terms that actually have products, ordered (manual, then name). */
function ricoman_collections_all( $include_empty = false ) {
	$terms = get_terms( array(
		'taxonomy'   => 'collection',
		'hide_empty' => ! $include_empty,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}
	usort( $terms, function ( $a, $b ) {
		$oa = ricoman_collection_order( $a->term_id );
		$ob = ricoman_collection_order( $b->term_id );
		if ( $oa !== $ob ) {
			// Terms with an explicit order (>0) sort before unordered (0), ascending.
			if ( 0 === $oa ) { return 1; }
			if ( 0 === $ob ) { return -1; }
			return $oa - $ob;
		}
		return strcasecmp( $a->name, $b->name );
	} );
	return $terms;
}

/* ---------------------------------------------------------------------------
 * Admin: image + description + landing body fields on the Collection term screen
 * ------------------------------------------------------------------------- */

/** Media-picker control (hidden attachment-id input + preview + buttons). */
function ricoman_col_img_control( $value ) {
	$url = $value ? wp_get_attachment_image_url( (int) $value, 'medium' ) : '';
	$out  = '<input type="hidden" class="rm-colimg-id" name="_rm_col_image" value="' . esc_attr( (int) $value ) . '">';
	$out .= '<div class="rm-colimg-prev" style="margin:.4em 0">';
	$out .= '<img src="' . esc_url( $url ) . '" alt="" style="max-width:220px;height:auto;border:1px solid #ddd;border-radius:6px;' . ( $url ? '' : 'display:none' ) . '">';
	$out .= '</div>';
	$out .= '<button type="button" class="button rm-colimg-pick">' . esc_html__( 'Select image', 'ricoman' ) . '</button> ';
	$out .= '<button type="button" class="button-link rm-colimg-clear"' . ( $url ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Remove', 'ricoman' ) . '</button>';
	return $out;
}

add_action( 'admin_init', function () {
	// Add-new term screen: stacked .form-field blocks.
	add_action( 'collection_add_form_fields', function () {
		echo '<div class="form-field"><label>' . esc_html__( 'Collection image', 'ricoman' ) . '</label>';
		echo ricoman_col_img_control( 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p class="description">' . esc_html__( 'Shown on the collection card and landing page.', 'ricoman' ) . '</p></div>';

		echo '<div class="form-field"><label for="_rm_col_desc">' . esc_html__( 'Short description', 'ricoman' ) . '</label>';
		echo '<textarea name="_rm_col_desc" id="_rm_col_desc" rows="3"></textarea>';
		echo '<p class="description">' . esc_html__( 'One or two lines for the collection card.', 'ricoman' ) . '</p></div>';

		echo '<div class="form-field"><label for="_rm_col_body">' . esc_html__( 'Landing page content', 'ricoman' ) . '</label>';
		echo '<textarea name="_rm_col_body" id="_rm_col_body" rows="8"></textarea>';
		echo '<p class="description">' . esc_html__( 'Longer copy for the "Know more" landing page. Basic HTML allowed.', 'ricoman' ) . '</p></div>';

		echo '<div class="form-field"><label for="_rm_col_order">' . esc_html__( 'Display order', 'ricoman' ) . '</label>';
		echo '<input type="number" name="_rm_col_order" id="_rm_col_order" value="0" step="1" style="width:90px">';
		echo '<p class="description">' . esc_html__( 'Lower numbers show first (0 = automatic).', 'ricoman' ) . '</p></div>';
	} );

	// Edit term screen: table rows.
	add_action( 'collection_edit_form_fields', function ( $term ) {
		$img   = (int) get_term_meta( $term->term_id, '_rm_col_image', true );
		$desc  = (string) get_term_meta( $term->term_id, '_rm_col_desc', true );
		$body  = (string) get_term_meta( $term->term_id, '_rm_col_body', true );
		$order = (int) get_term_meta( $term->term_id, '_rm_col_order', true );

		echo '<tr class="form-field"><th scope="row"><label>' . esc_html__( 'Collection image', 'ricoman' ) . '</label></th><td>';
		echo ricoman_col_img_control( $img ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p class="description">' . esc_html__( 'Shown on the collection card and landing page.', 'ricoman' ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row"><label for="_rm_col_desc">' . esc_html__( 'Short description', 'ricoman' ) . '</label></th><td>';
		echo '<textarea name="_rm_col_desc" id="_rm_col_desc" rows="3" class="large-text">' . esc_textarea( $desc ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One or two lines for the collection card.', 'ricoman' ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row"><label for="_rm_col_body">' . esc_html__( 'Landing page content', 'ricoman' ) . '</label></th><td>';
		echo '<textarea name="_rm_col_body" id="_rm_col_body" rows="8" class="large-text">' . esc_textarea( $body ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Longer copy for the "Know more" landing page. Basic HTML allowed.', 'ricoman' ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row"><label for="_rm_col_order">' . esc_html__( 'Display order', 'ricoman' ) . '</label></th><td>';
		echo '<input type="number" name="_rm_col_order" id="_rm_col_order" value="' . esc_attr( $order ) . '" step="1" style="width:90px">';
		echo '<p class="description">' . esc_html__( 'Lower numbers show first (0 = automatic).', 'ricoman' ) . '</p></td></tr>';
	} );
} );

/** Save the Collection term fields (create + edit). */
function ricoman_collection_save_fields( $term_id ) {
	if ( isset( $_POST['_rm_col_image'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$id = absint( $_POST['_rm_col_image'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $id ) {
			update_term_meta( $term_id, '_rm_col_image', $id );
		} else {
			delete_term_meta( $term_id, '_rm_col_image' );
		}
	}
	foreach ( array( '_rm_col_desc' => 'text', '_rm_col_body' => 'html' ) as $key => $type ) {
		if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification
			$val = ( 'html' === $type ) ? wp_kses_post( $raw ) : sanitize_textarea_field( $raw );
			if ( '' !== trim( (string) $val ) ) {
				update_term_meta( $term_id, $key, $val );
			} else {
				delete_term_meta( $term_id, $key );
			}
		}
	}
	if ( isset( $_POST['_rm_col_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		update_term_meta( $term_id, '_rm_col_order', absint( $_POST['_rm_col_order'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	// Bust cached collection counts / product tiles.
	$ver = (int) get_option( 'rm_products_ver', 1 );
	update_option( 'rm_products_ver', $ver + 1, false );
}
add_action( 'created_collection', 'ricoman_collection_save_fields' );
add_action( 'edited_collection', 'ricoman_collection_save_fields' );

/** Media picker JS on the Collection term screens only. */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
		return;
	}
	$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'collection' !== $tax ) {
		return;
	}
	wp_enqueue_media();
	$js = <<<'JS'
(function($){
  $(document).on('click','.rm-colimg-pick',function(e){
    e.preventDefault();
    var wrap = $(this).closest('td,.form-field');
    var frame = wp.media({ title:'Select collection image', button:{text:'Use this image'}, multiple:false, library:{type:'image'} });
    frame.on('select', function(){
      var a = frame.state().get('selection').first().toJSON();
      wrap.find('.rm-colimg-id').val(a.id);
      var u = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
      wrap.find('.rm-colimg-prev img').attr('src',u).show();
      wrap.find('.rm-colimg-clear').show();
    });
    frame.open();
  });
  $(document).on('click','.rm-colimg-clear',function(e){
    e.preventDefault();
    var wrap = $(this).closest('td,.form-field');
    wrap.find('.rm-colimg-id').val('');
    wrap.find('.rm-colimg-prev img').attr('src','').hide();
    $(this).hide();
  });
})(jQuery);
JS;
	wp_add_inline_script( 'jquery-core', $js );
} );
