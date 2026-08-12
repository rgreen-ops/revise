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
	$key     = 'rm_colcnt2_' . $ver . '_' . $term_id;
	$cached  = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$prods = ricoman_collection_products( $term_id );
	// Distinct variant count across the whole collection. Expand each product to
	// its configure-family and dedupe FIRST, so family-grouped ranges (e.g.
	// Estrella, whose members share one variant pool) aren't counted multiple
	// times. Then one query counts the unique variant posts.
	$parent_ids = array();
	foreach ( $prods as $pid ) {
		if ( function_exists( 'ricoman_pf_family_products' ) ) {
			foreach ( (array) ricoman_pf_family_products( $pid ) as $f ) {
				$parent_ids[ (int) $f ] = true;
			}
		} else {
			$parent_ids[ (int) $pid ] = true;
		}
	}
	$parent_ids = array_keys( $parent_ids );
	$variants   = 0;
	if ( ! empty( $parent_ids ) && post_type_exists( 'variant-product' ) ) {
		$vq = new WP_Query( array(
			'post_type'      => 'variant-product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => 'parent_product', 'value' => array_map( 'strval', $parent_ids ), 'compare' => 'IN' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		$variants = (int) $vq->found_posts;
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

/* ---------------------------------------------------------------------------
 * Front-end: "Collections" tab + cards on the /products/ archive
 * (injected by ricoman_all_products in inc/product-filter-fix.php). All output
 * is guarded — if there are no collections, these return '' and the products
 * page is completely unchanged (important: keeps LIVE untouched until
 * collections exist there).
 * ------------------------------------------------------------------------- */

/** One dark collection card (image bg + gradient + title/desc/counts/buttons). */
function ricoman_collection_card_html( $term ) {
	$img = ricoman_collection_image( $term->term_id, 'large' );
	if ( '' === $img ) {
		$prods = ricoman_collection_products( $term->term_id );
		if ( ! empty( $prods ) ) {
			$img = function_exists( 'ricoman_product_img' ) ? ricoman_product_img( $prods[0] ) : (string) get_the_post_thumbnail_url( $prods[0], 'large' );
		}
	}
	$c    = ricoman_collection_counts( $term->term_id );
	$desc = ricoman_collection_desc( $term->term_id );
	$link = get_term_link( $term );
	$link = is_wp_error( $link ) ? '#' : $link;

	$h  = '<div class="rm-collcard">';
	if ( $img ) {
		$h .= '<span class="rm-collcard-media" style="background-image:url(' . esc_url( $img ) . ')" aria-hidden="true"></span>';
	}
	$h .= '<div class="rm-collcard-body">';
	$h .= '<h3 class="rm-collcard-title">' . esc_html( $term->name ) . '</h3>';
	if ( '' !== $desc ) {
		$h .= '<p class="rm-collcard-desc">' . esc_html( $desc ) . '</p>';
	}
	$h .= '<p class="rm-collcard-meta">' . (int) $c['products'] . ' products &middot; ' . (int) $c['variants'] . ' variants</p>';
	$h .= '<div class="rm-collcard-btns">';
	$h .= '<a class="rm-cbtn-primary" href="' . esc_url( $link ) . '">' . esc_html__( 'Know more', 'ricoman' ) . '</a>';
	$h .= '<a class="rm-cbtn-outline" href="' . esc_url( $link ) . '#rm-browse">' . esc_html__( 'Browse products', 'ricoman' ) . '</a>';
	$h .= '</div></div></div>';
	return $h;
}

/** The Products / Collections tab bar. '' when there are no collections. */
function ricoman_collections_tabbar() {
	if ( empty( ricoman_collections_all() ) ) {
		return '';
	}
	return '<div class="rm-pp-tabs" role="tablist">'
		. '<button type="button" class="rm-pp-tab on" data-view="products">' . esc_html__( 'Products', 'ricoman' ) . '</button>'
		. '<button type="button" class="rm-pp-tab" data-view="collections">' . esc_html__( 'Collections', 'ricoman' ) . '</button>'
		. '</div>';
}

/** Collections cards panel + CSS + tab-switch JS. '' when there are no collections. */
function ricoman_collections_panel() {
	$terms = ricoman_collections_all();
	if ( empty( $terms ) ) {
		return '';
	}
	$cards = '';
	foreach ( $terms as $t ) {
		$cards .= ricoman_collection_card_html( $t );
	}
	$css = <<<'CSS'
<style id="rm-collections-css">
.rm-pp-tabs{display:flex;gap:26px;border-bottom:1px solid #e3e3e3;margin:0 0 22px}
.rm-pp-tab{background:none;border:0;padding:0 0 12px;font-family:Poppins;font-weight:600;font-size:1rem;color:#8a8a8a;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;line-height:1.2}
.rm-pp-tab.on{color:var(--ink,#111);border-bottom-color:var(--ink,#111)}
.rm-collgrid{display:none;grid-template-columns:repeat(3,1fr);gap:22px}
.rm-view-coll .rm-collgrid{display:grid}
.rm-view-coll .rm-catgrid-top,.rm-view-coll .rm-allpgrid,.rm-view-coll .rm-pgn,.rm-view-coll .rm-fnone{display:none!important}
.rm-view-coll .rm-facets{display:none}
.rm-view-coll .rm-catgrid-wrap{grid-template-columns:1fr}
.rm-collcard{position:relative;display:flex;flex-direction:column;justify-content:flex-end;min-height:430px;border-radius:14px;overflow:hidden;background:#0e0e10;color:#fff}
.rm-collcard-media{position:absolute;inset:0;background-size:cover;background-position:center;z-index:0}
.rm-collcard::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.9),rgba(0,0,0,.25) 55%,rgba(0,0,0,0));z-index:1}
.rm-collcard-body{position:relative;z-index:2;padding:24px}
.rm-collcard-title{font-family:Poppins;font-weight:600;font-size:1.5rem;line-height:1.2;margin:0 0 8px;color:#fff}
.rm-collcard-desc{font-size:.92rem;line-height:1.5;color:rgba(255,255,255,.85);margin:0 0 12px}
.rm-collcard-meta{font-size:.78rem;letter-spacing:.04em;color:rgba(255,255,255,.7);margin:0 0 18px}
.rm-collcard-btns{display:flex;gap:10px;flex-wrap:wrap}
.rm-collcard-btns a{font-family:Poppins;font-weight:600;font-size:.82rem;padding:10px 18px;border-radius:8px;text-decoration:none;transition:.2s}
.rm-cbtn-primary{background:#2f6df6;color:#fff}
.rm-cbtn-primary:hover{background:#255ad6}
.rm-cbtn-outline{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.5)}
.rm-cbtn-outline:hover{background:rgba(255,255,255,.22)}
@media(max-width:1100px){.rm-collgrid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.rm-collgrid{grid-template-columns:1fr}}
</style>
CSS;
	$js = <<<'JS'
<script>(function(){
  var tabs = document.querySelectorAll('.rm-pp-tabs .rm-pp-tab');
  if(!tabs.length) return;
  Array.prototype.forEach.call(tabs, function(tab){
    tab.addEventListener('click', function(){
      var view = tab.getAttribute('data-view');
      var wrap = tab.closest('.rm-allprods');
      if(!wrap) return;
      wrap.classList.toggle('rm-view-coll', view === 'collections');
      Array.prototype.forEach.call(wrap.querySelectorAll('.rm-pp-tab'), function(t){ t.classList.toggle('on', t === tab); });
    });
  });
})();</script>
JS;
	return $css . '<div class="rm-collgrid">' . $cards . '</div>' . $js;
}

/* ---------------------------------------------------------------------------
 * Front-end: the collection landing + browse page ([ricoman_collection_page],
 * placed by templates/taxonomy-collection.html). Renders the "Know more" hero
 * (image + title + description + body) then the products in the collection.
 * ------------------------------------------------------------------------- */
add_shortcode( 'ricoman_collection_page', function () {
	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) || 'collection' !== $term->taxonomy ) {
		return '';
	}
	$img   = ricoman_collection_image( $term->term_id, 'large' );
	$desc  = ricoman_collection_desc( $term->term_id );
	$body  = ricoman_collection_body( $term->term_id );
	$c     = ricoman_collection_counts( $term->term_id );
	$prods = ricoman_collection_products( $term->term_id );
	$crumb = do_shortcode( '[ricoman_breadcrumbs]' );
	if ( '' === $img && ! empty( $prods ) ) {
		$img = function_exists( 'ricoman_product_img' ) ? ricoman_product_img( $prods[0] ) : (string) get_the_post_thumbnail_url( $prods[0], 'large' );
	}

	$cards = '';
	foreach ( $prods as $pid ) {
		$pimg = function_exists( 'ricoman_product_img' ) ? ricoman_product_img( $pid ) : (string) get_the_post_thumbnail_url( $pid, 'large' );
		$sub  = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, 'product_subname' ) : '';
		$mx   = function_exists( 'ricoman_pf_metrics' ) ? ricoman_pf_metrics( $pid ) : array( 'lm' => 0, 'w' => 0, 'co' => 0, 'feats' => array() );
		$fins = function_exists( 'ricoman_pcard_finish_slugs' ) ? ricoman_pcard_finish_slugs( $pid ) : array();
		if ( function_exists( 'ricoman_pcard_html' ) ) {
			$cards .= ricoman_pcard_html( get_permalink( $pid ), $pid, $pimg, $sub, $mx, (int) ( $mx['co'] ?? 0 ), array(), array(), array(), $fins );
		}
	}

	$css = <<<'CSS'
<style>
.rm-colpage-hero{position:relative;background:#0e0e10;background-size:cover;background-position:center;color:#fff}
.rm-colpage-hero::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.85),rgba(0,0,0,.35));z-index:0}
.rm-colpage-hero-in{position:relative;z-index:1;max-width:1200px;margin:0 auto;padding:60px 24px}
.rm-colpage-crumb a{color:rgba(255,255,255,.85)}
.rm-colpage-title{font-family:Poppins;font-weight:700;font-size:clamp(2rem,4vw,3rem);line-height:1.1;margin:.2em 0 .25em;color:#fff}
.rm-colpage-desc{font-size:1.05rem;max-width:62ch;color:rgba(255,255,255,.9);margin:0 0 .6em}
.rm-colpage-meta{font-size:.85rem;letter-spacing:.04em;color:rgba(255,255,255,.75);margin:0}
.rm-colpage-body{max-width:820px;margin:34px auto;padding:0 24px;font-size:1.02rem;line-height:1.7}
.rm-colpage-grid-wrap{max-width:1200px;margin:34px auto 64px;padding:0 24px;scroll-margin-top:80px}
.rm-colpage-gridh{font-family:Poppins;font-weight:600;font-size:1.4rem;margin:0 0 20px}
.rm-colpage-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:24px}
.rm-colpage .rm-pcard{display:flex;flex-direction:column;text-decoration:none;color:inherit}
.rm-colpage .rm-pcard-img{position:relative;aspect-ratio:4/5;background:#f2f2f2;border-radius:12px;overflow:hidden;display:flex;align-items:center;justify-content:center}
.rm-colpage .rm-pcard-img img{width:82%;height:82%;object-fit:contain;display:block;mix-blend-mode:multiply}
.rm-colpage .rm-pcard-body{padding:14px 4px 0;text-align:center}
.rm-colpage .rm-pcard-eyebrow{display:block;font-size:.82rem;font-family:Poppins;color:#888;margin-top:6px}
.rm-colpage .rm-pcard-title{display:block;font-size:1.1rem;font-weight:600;font-family:Poppins;line-height:1.3}
.rm-colpage .rm-pcard-noimg{font-family:Poppins;font-size:1.1rem;font-weight:600;color:#c9c9c9;text-align:center}
@media(max-width:1100px){.rm-colpage-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:600px){.rm-colpage-grid{grid-template-columns:1fr 1fr}}
</style>
CSS;

	$hero = '<div class="rm-colpage-hero"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '>'
		. '<div class="rm-colpage-hero-in">'
		. '<div class="rm-pp-crumb rm-colpage-crumb">' . $crumb . '</div>'
		. '<h1 class="rm-colpage-title">' . esc_html( $term->name ) . '</h1>'
		. ( '' !== $desc ? '<p class="rm-colpage-desc">' . esc_html( $desc ) . '</p>' : '' )
		. '<p class="rm-colpage-meta">' . (int) $c['products'] . ' products &middot; ' . (int) $c['variants'] . ' variants</p>'
		. '</div></div>';
	$bodyhtml = ( '' !== trim( (string) $body ) ) ? '<div class="rm-colpage-body">' . wpautop( wp_kses_post( $body ) ) . '</div>' : '';
	$grid = '<div class="rm-colpage-grid-wrap" id="rm-browse"><h2 class="rm-colpage-gridh">' . esc_html__( 'Products in this collection', 'ricoman' ) . '</h2>'
		. '<div class="rm-colpage-grid">' . $cards . '</div></div>';

	return $css . '<div class="rm-pp-wrap rm-colpage">' . $hero . $bodyhtml . $grid . '</div>';
} );
