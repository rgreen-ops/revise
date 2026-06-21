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

/** Add-category screen (fields stacked, no table cell). */
add_action( 'admin_init', function () {
	$tax = ricoman_cat_tax();
	add_action( "{$tax}_add_form_fields", function () {
		echo '<div class="form-field">';
		echo wp_kses_post( ricoman_cat_img_field( '_rm_cat_img_insitu', 'In-situ image (lifestyle)', 'Shown by default on the Products page tiles.' ) );
		echo wp_kses_post( ricoman_cat_img_field( '_rm_cat_img_studio', 'Studio image (on white)', 'Shown when the visitor toggles to “Studio”.' ) );
		echo '</div>';
	} );

	// Edit-category screen (table rows).
	add_action( "{$tax}_edit_form_fields", function ( $term ) {
		$insitu = (int) get_term_meta( $term->term_id, '_rm_cat_img_insitu', true );
		$studio = (int) get_term_meta( $term->term_id, '_rm_cat_img_studio', true );
		echo '<tr class="form-field"><th scope="row">Category images</th><td>';
		echo wp_kses_post( ricoman_cat_img_field( '_rm_cat_img_insitu', 'In-situ image (lifestyle)', 'Shown by default on the Products page tiles.', $insitu ) );
		echo wp_kses_post( ricoman_cat_img_field( '_rm_cat_img_studio', 'Studio image (on white)', 'Shown when the visitor toggles to “Studio”.', $studio ) );
		echo '</td></tr>';
	} );
} );

/** Save both image ids on create + edit. */
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
	// Category imagery changed — refresh the cached tiles.
	if ( function_exists( 'ricoman_products_ver' ) ) {
		update_option( 'rm_products_ver', (string) time(), false );
	}
}
add_action( 'created_' . ricoman_cat_tax(), 'ricoman_cat_img_save' );
add_action( 'edited_' . ricoman_cat_tax(), 'ricoman_cat_img_save' );

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
