<?php
/**
 * Configurator option images — the picture on each tap-through tile.
 *
 * The visual configurator (inc/configurator-visual.php) shows a tile per option
 * (e.g. Colour Finish → Matte Silver / Polished Chrome / Titanium, or each
 * Dimension). By default a tile borrows the first matching variant's photo. This
 * file lets the team set a deliberate image per option in two places:
 *
 *   • A MASTER set in admin (Ricoman → Configurator Images): map an option value
 *     to an image once and it applies to every product (e.g. "Matte White" finish
 *     always uses the same swatch).
 *   • A PER-PRODUCT override on the product edit screen ("Configurator option
 *     images" box) — auto-lists this product's option values, each with a picker.
 *
 * Resolution order used by the configurator: per-product → master → variant photo.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ===========================================================================
 * Data model + resolver
 * ======================================================================== */

/** Normalised key for an (axis label, option value) pair. */
function ricoman_config_opt_key( $label, $value ) {
	return strtolower( trim( (string) $label ) ) . '|' . strtolower( trim( (string) $value ) );
}

/** Turn a stored value (attachment ID or URL) into a usable image URL. */
function ricoman_config_img_url( $v ) {
	if ( is_numeric( $v ) ) {
		$u = wp_get_attachment_image_url( (int) $v, 'medium' );
		return $u ? $u : '';
	}
	return is_string( $v ) ? $v : '';
}

/**
 * The image to show on a configurator tile for this axis/value, or '' to let the
 * configurator fall back to a representative variant photo.
 */
function ricoman_config_option_image( $label, $value, $pid = 0 ) {
	$key = ricoman_config_opt_key( $label, $value );
	if ( $pid ) {
		$per = get_post_meta( (int) $pid, '_ricoman_config_opt_images', true );
		if ( is_array( $per ) && ! empty( $per[ $key ] ) ) {
			$u = ricoman_config_img_url( $per[ $key ] );
			if ( $u ) {
				return $u;
			}
		}
	}
	$master = get_option( 'rm_config_opt_images', array() );
	if ( is_array( $master ) && ! empty( $master[ $key ] ) ) {
		$u = ricoman_config_img_url( $master[ $key ] );
		if ( $u ) {
			return $u;
		}
	}
	return '';
}

/**
 * This product's configurable axes => option values (family-aware, cached).
 * Used to auto-list the per-product picker rows.
 */
function ricoman_config_axis_values( $pid ) {
	if ( ! post_type_exists( 'variant-product' ) || ! function_exists( 'ricoman_variant_spec_pairs' ) ) {
		return array();
	}
	$ckey   = 'rm_cfgopt_' . (int) $pid . '_' . (int) get_post_meta( $pid, '_rm_secver', true );
	$cached = get_transient( $ckey );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$fam = function_exists( 'ricoman_pf_family_products' ) ? ricoman_pf_family_products( $pid ) : array( (int) $pid );
	$mq  = count( $fam ) > 1
		? array( array( 'key' => 'parent_product', 'value' => array_map( 'strval', $fam ), 'compare' => 'IN' ) )
		: array( array( 'key' => 'parent_product', 'value' => (string) $pid ) );
	$ids = get_posts( array(
		'post_type'      => 'variant-product',
		'post_status'    => 'publish',
		'posts_per_page' => 1500,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => $mq,
	) );
	$axes = array();
	foreach ( $ids as $vid ) {
		foreach ( ricoman_variant_spec_pairs( $vid ) as $label => $v ) {
			$axes[ $label ][ (string) $v ] = true;
		}
	}
	$out = array();
	foreach ( ricoman_variant_spec_label_order() as $label ) {
		if ( isset( $axes[ $label ] ) && count( $axes[ $label ] ) > 1 ) {
			$vals = array_keys( $axes[ $label ] );
			natcasesort( $vals );
			$out[ $label ] = array_slice( array_values( $vals ), 0, 40 );
		}
	}
	set_transient( $ckey, $out, HOUR_IN_SECONDS );
	return $out;
}

/* ===========================================================================
 * Shared media-picker assets (product editor + master page)
 * ======================================================================== */

function ricoman_config_img_assets() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	wp_enqueue_media();
	$js = <<<'JS'
jQuery(function($){
	$(document).on('click','.rm-cfgimg-pick',function(e){
		e.preventDefault();
		var wrap=$(this).closest('.rm-cfgimg');
		var frame=wp.media({title:'Choose option image',multiple:false,library:{type:'image'}});
		frame.on('select',function(){
			var a=frame.state().get('selection').first().toJSON();
			var url=(a.sizes&&a.sizes.medium)?a.sizes.medium.url:a.url;
			wrap.find('.rm-cfgimg-val').val(a.id);
			wrap.find('.rm-cfgimg-thumb').css('background-image','url('+url+')').addClass('has');
			wrap.addClass('is-set');
		});
		frame.open();
	});
	$(document).on('click','.rm-cfgimg-clear',function(e){
		e.preventDefault();
		var wrap=$(this).closest('.rm-cfgimg');
		wrap.find('.rm-cfgimg-val').val('');
		wrap.find('.rm-cfgimg-thumb').css('background-image','').removeClass('has');
		wrap.removeClass('is-set');
	});
	$(document).on('click','.rm-cfgmaster-add',function(e){
		e.preventDefault();
		var t=$('#rm-cfgmaster-tmpl').html().replace(/__i__/g,Date.now());
		$('.rm-cfgmaster-rows').append(t);
	});
	$(document).on('click','.rm-cfgmaster-del',function(e){
		e.preventDefault();
		$(this).closest('.rm-cfgmaster-row').remove();
	});
});
JS;
	wp_add_inline_script( 'jquery-core', $js );
	$css = '.rm-cfgimg{display:flex;align-items:center;gap:8px}'
		. '.rm-cfgimg-thumb{width:46px;height:46px;border:1px solid #c9ccd1;border-radius:8px;background:#f6f7f9 center/cover no-repeat;flex:0 0 auto}'
		. '.rm-cfgimg-thumb.has{border-color:#2271b1}'
		. '.rm-cfgopt-table{border-collapse:collapse;width:100%;max-width:760px}'
		. '.rm-cfgopt-table th,.rm-cfgopt-table td{text-align:left;padding:7px 10px;border-bottom:1px solid #eef0f2;vertical-align:middle}'
		. '.rm-cfgopt-axis{font-weight:600;color:#1d2327}'
		. '.rm-cfgmaster-row{display:flex;gap:10px;align-items:center;margin:0 0 10px;flex-wrap:wrap}'
		. '.rm-cfgmaster-row input[type=text]{min-width:160px}';
	wp_add_inline_style( 'common', $css );
}

/** Render one media-picker control bound to a hidden field. */
function ricoman_config_img_control( $field_name, $stored ) {
	$url = $stored ? ricoman_config_img_url( $stored ) : '';
	$set = $url ? ' is-set' : '';
	echo '<span class="rm-cfgimg' . esc_attr( $set ) . '">';
	echo '<span class="rm-cfgimg-thumb' . ( $url ? ' has' : '' ) . '" style="' . ( $url ? 'background-image:url(' . esc_url( $url ) . ')' : '' ) . '"></span>';
	echo '<input type="hidden" class="rm-cfgimg-val" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( (string) $stored ) . '">';
	echo '<button type="button" class="button button-small rm-cfgimg-pick">' . esc_html__( 'Set image', 'ricoman' ) . '</button>';
	echo '<button type="button" class="button-link rm-cfgimg-clear">' . esc_html__( 'Remove', 'ricoman' ) . '</button>';
	echo '</span>';
}

/* ===========================================================================
 * Per-product metabox
 * ======================================================================== */

add_action( 'add_meta_boxes_product', function () {
	add_meta_box( 'ricoman_config_images', __( 'Configurator option images', 'ricoman' ), 'ricoman_config_images_box', 'product', 'normal', 'default' );
} );

function ricoman_config_images_box( $post ) {
	wp_nonce_field( 'ricoman_config_images', 'ricoman_config_images_nonce' );
	ricoman_config_img_assets();
	$axes  = ricoman_config_axis_values( $post->ID );
	$saved = get_post_meta( $post->ID, '_ricoman_config_opt_images', true );
	$saved = is_array( $saved ) ? $saved : array();

	echo '<p class="description">' . esc_html__( 'Set the picture shown on each tile in the visual configurator for this product. Leave blank to use the master image (Ricoman → Configurator Images) or the variant photo.', 'ricoman' ) . '</p>';

	if ( ! $axes ) {
		echo '<p>' . esc_html__( 'No configurable options found yet — add variants (order codes) with finishes, colours, sizes, etc.', 'ricoman' ) . '</p>';
		return;
	}
	echo '<table class="rm-cfgopt-table"><thead><tr><th>' . esc_html__( 'Option', 'ricoman' ) . '</th><th>' . esc_html__( 'Image', 'ricoman' ) . '</th></tr></thead><tbody>';
	foreach ( $axes as $label => $vals ) {
		echo '<tr><td colspan="2" class="rm-cfgopt-axis">' . esc_html( $label ) . '</td></tr>';
		foreach ( $vals as $v ) {
			$key   = ricoman_config_opt_key( $label, $v );
			$field = 'rm_cfgimg[' . $key . ']';
			$cur   = isset( $saved[ $key ] ) ? $saved[ $key ] : '';
			echo '<tr><td>' . esc_html( $v ) . '</td><td>';
			ricoman_config_img_control( $field, $cur );
			echo '</td></tr>';
		}
	}
	echo '</tbody></table>';
}

add_action( 'save_post_product', function ( $pid ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['ricoman_config_images_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ricoman_config_images_nonce'] ), 'ricoman_config_images' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $pid ) ) {
		return;
	}
	$in  = isset( $_POST['rm_cfgimg'] ) && is_array( $_POST['rm_cfgimg'] ) ? wp_unslash( $_POST['rm_cfgimg'] ) : array();
	$out = array();
	foreach ( $in as $k => $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			continue;
		}
		$key = strtolower( sanitize_text_field( $k ) );
		$out[ $key ] = is_numeric( $v ) ? (int) $v : esc_url_raw( $v );
	}
	if ( $out ) {
		update_post_meta( $pid, '_ricoman_config_opt_images', $out );
	} else {
		delete_post_meta( $pid, '_ricoman_config_opt_images' );
	}
	update_post_meta( $pid, '_rm_secver', time() );
	update_option( 'rm_products_ver', (string) time(), false );
} );

/* ===========================================================================
 * Master admin page (Ricoman → Configurator Images)
 * ======================================================================== */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Configurator Images', 'ricoman' ),
		__( 'Configurator Images', 'ricoman' ),
		'edit_posts',
		'ricoman-config-images',
		'ricoman_config_images_page'
	);
}, 30 );

function ricoman_config_images_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	if ( isset( $_POST['rm_cfgmaster_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['rm_cfgmaster_nonce'] ), 'rm_cfgmaster' ) ) {
		$rows = isset( $_POST['rm_cfgmaster'] ) && is_array( $_POST['rm_cfgmaster'] ) ? wp_unslash( $_POST['rm_cfgmaster'] ) : array();
		$map  = array();
		foreach ( $rows as $r ) {
			$axis = isset( $r['axis'] ) ? trim( sanitize_text_field( $r['axis'] ) ) : '';
			$val  = isset( $r['value'] ) ? trim( sanitize_text_field( $r['value'] ) ) : '';
			$img  = isset( $r['img'] ) ? trim( (string) $r['img'] ) : '';
			if ( '' === $axis || '' === $val || '' === $img ) {
				continue;
			}
			$map[ ricoman_config_opt_key( $axis, $val ) ] = is_numeric( $img ) ? (int) $img : esc_url_raw( $img );
		}
		update_option( 'rm_config_opt_images', $map, false );
		// Invalidate every product's cached configure section (master images are
		// global). A dedicated stamp so it doesn't thrash on routine variant edits.
		update_option( 'rm_cfgimg_ver', (string) time(), false );
		update_option( 'rm_products_ver', (string) time(), false );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Master configurator images saved.', 'ricoman' ) . '</p></div>';
	}

	ricoman_config_img_assets();
	$map    = get_option( 'rm_config_opt_images', array() );
	$labels = function_exists( 'ricoman_variant_spec_label_order' ) ? ricoman_variant_spec_label_order() : array();

	echo '<div class="wrap"><h1>' . esc_html__( 'Configurator Images (master)', 'ricoman' ) . '</h1>';
	echo '<p class="description" style="max-width:760px">' . esc_html__( 'Set a default image for an option value (e.g. axis “Colour Finish”, value “Matte White”). It applies to every product’s visual configurator. A product can still override any of these from its own “Configurator option images” box.', 'ricoman' ) . '</p>';

	// A datalist of known axis labels for the inputs.
	echo '<datalist id="rm-cfg-axes">';
	foreach ( $labels as $l ) {
		echo '<option value="' . esc_attr( $l ) . '"></option>';
	}
	echo '</datalist>';

	echo '<form method="post">';
	wp_nonce_field( 'rm_cfgmaster', 'rm_cfgmaster_nonce' );
	echo '<div class="rm-cfgmaster-rows">';
	$render_row = function ( $idx, $axis, $value, $img ) {
		$n = 'rm_cfgmaster[' . $idx . ']';
		echo '<div class="rm-cfgmaster-row">';
		echo '<input type="text" name="' . esc_attr( $n . '[axis]' ) . '" list="rm-cfg-axes" placeholder="' . esc_attr__( 'Axis (e.g. Colour Finish)', 'ricoman' ) . '" value="' . esc_attr( $axis ) . '">';
		echo '<input type="text" name="' . esc_attr( $n . '[value]' ) . '" placeholder="' . esc_attr__( 'Value (e.g. Matte White)', 'ricoman' ) . '" value="' . esc_attr( $value ) . '">';
		echo '<span class="rm-cfgimg' . ( $img ? ' is-set' : '' ) . '">';
		$url = $img ? ricoman_config_img_url( $img ) : '';
		echo '<span class="rm-cfgimg-thumb' . ( $url ? ' has' : '' ) . '" style="' . ( $url ? 'background-image:url(' . esc_url( $url ) . ')' : '' ) . '"></span>';
		echo '<input type="hidden" class="rm-cfgimg-val" name="' . esc_attr( $n . '[img]' ) . '" value="' . esc_attr( (string) $img ) . '">';
		echo '<button type="button" class="button button-small rm-cfgimg-pick">' . esc_html__( 'Set image', 'ricoman' ) . '</button>';
		echo '<button type="button" class="button-link rm-cfgimg-clear">' . esc_html__( 'Remove', 'ricoman' ) . '</button>';
		echo '</span>';
		echo ' <button type="button" class="button-link rm-cfgmaster-del" style="color:#b32d2e">' . esc_html__( 'Delete row', 'ricoman' ) . '</button>';
		echo '</div>';
	};
	$i = 0;
	foreach ( (array) $map as $key => $img ) {
		$parts = explode( '|', (string) $key, 2 );
		$render_row( $i, isset( $parts[0] ) ? $parts[0] : '', isset( $parts[1] ) ? $parts[1] : '', $img );
		$i++;
	}
	if ( ! $i ) {
		$render_row( 0, '', '', '' );
	}
	echo '</div>';

	// Template for new rows (cloned by JS; __i__ replaced with a unique index).
	echo '<script type="text/template" id="rm-cfgmaster-tmpl">';
	$render_row( '__i__', '', '', '' );
	echo '</script>';

	echo '<p><button type="button" class="button rm-cfgmaster-add">+ ' . esc_html__( 'Add option image', 'ricoman' ) . '</button></p>';
	submit_button( __( 'Save master images', 'ricoman' ) );
	echo '</form></div>';
}
