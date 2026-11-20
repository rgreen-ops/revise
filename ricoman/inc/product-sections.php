<?php
/**
 * Composable product-page sections.
 *
 * The whole product page is built by ricoman_pf_sections() (in acf-product.php)
 * as a map of named fragments. This file exposes each fragment as its own
 * shortcode AND as an insertable block pattern, plus a "Ricoman: Product page"
 * pattern that lays the default order out as separate section blocks.
 *
 * Result: an admin can open a product, insert the "Product page" pattern, then
 * reorder the sections or drop ANY of their own patterns into the gaps between
 * them — full control over the layout — while products left with empty content
 * keep auto-rendering the default page (see the_content filter in acf-product).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emit one named section of the current product. Safe to call anywhere: returns
 * empty unless we're on a product and that section produced output.
 */
function ricoman_section_render( $key ) {
	$pid = get_the_ID();
	if ( ! $pid || 'product' !== get_post_type( $pid ) || ! function_exists( 'ricoman_pf_sections' ) ) {
		return '';
	}
	$s = ricoman_pf_sections( $pid );
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

/** The section shortcodes (one per fragment). */
add_shortcode( 'ricoman_section_hero', function () {
	return ricoman_section_render( 'hero' );
} );
add_shortcode( 'ricoman_section_specs', function () {
	return ricoman_section_render( 'specs' );
} );
add_shortcode( 'ricoman_section_downloads', function () {
	return ricoman_section_render( 'downloads' );
} );
add_shortcode( 'ricoman_section_configure', function () {
	return ricoman_section_render( 'configure' );
} );
add_shortcode( 'ricoman_section_range', function () {
	return ricoman_section_render( 'range' );
} );
add_shortcode( 'ricoman_section_accessories', function () {
	return ricoman_section_render( 'accessories' );
} );
add_shortcode( 'ricoman_section_related', function () {
	return ricoman_section_render( 'related' );
} );
add_shortcode( 'ricoman_section_faq', function () {
	return ricoman_section_render( 'faq' );
} );
add_shortcode( 'ricoman_section_cta', function () {
	return ricoman_section_render( 'cta' );
} );

/** The product sections, in default order: key => editor label. */
function ricoman_section_defs() {
	return array(
		'hero'        => __( 'Product: Hero (gallery + panel)', 'ricoman' ),
		'specs'       => __( 'Product: Specification & details', 'ricoman' ),
		'downloads'   => __( 'Product: Downloads', 'ricoman' ),
		'configure'   => __( 'Product: Configure & order codes', 'ricoman' ),
		'range'       => __( 'Product: Range content (Estrella)', 'ricoman' ),
		'accessories' => __( 'Product: Accessories', 'ricoman' ),
		'related'     => __( 'Product: You may also like', 'ricoman' ),
		'faq'         => __( 'Product: FAQs', 'ricoman' ),
		'cta'         => __( 'Product: Specify call-to-action', 'ricoman' ),
	);
}

/** The default product layout, as live-preview section blocks (in order). */
function ricoman_product_layout_blocks() {
	$out = '';
	foreach ( array_keys( ricoman_section_defs() ) as $key ) {
		$out .= '<!-- wp:ricoman/product-' . $key . ' /-->' . "\n";
	}
	return $out;
}

/**
 * Server render for a section block. On the front end the product is the current
 * post; inside the editor it renders through the REST block-renderer, which sets
 * up the post from the `post_id` query arg (we also read it as a fallback) so the
 * preview shows the real product.
 */
function ricoman_section_block_render( $key ) {
	$pid = get_the_ID();
	if ( ( ! $pid || 'product' !== get_post_type( $pid ) ) && ! empty( $_REQUEST['post_id'] ) ) {
		$pid = (int) $_REQUEST['post_id'];
	}
	$labels = ricoman_section_defs();
	// Placeholders are ONLY for the block-editor preview (admin / REST block
	// renderer). On the live front end (and the builder's WYSIWYG preview) an
	// empty section must render nothing, never a "no content" note.
	$editor_ctx = is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	if ( ! $pid || 'product' !== get_post_type( $pid ) || ! function_exists( 'ricoman_pf_sections' ) ) {
		if ( ! $editor_ctx ) {
			return '';
		}
		return '<div class="rm-block-ph" style="padding:22px;border:1px dashed #c9ccd1;border-radius:10px;color:#6b7280;font:14px/1.5 system-ui,sans-serif">'
			. esc_html( isset( $labels[ $key ] ) ? $labels[ $key ] : $key ) . ' — ' . esc_html__( 'shows on the live product page.', 'ricoman' ) . '</div>';
	}
	$s    = ricoman_pf_sections( $pid );
	$html = isset( $s[ $key ] ) ? $s[ $key ] : '';
	if ( '' === trim( (string) $html ) ) {
		if ( ! $editor_ctx ) {
			return '';
		}
		return '<div class="rm-block-ph" style="padding:16px;border:1px dashed #c9ccd1;border-radius:10px;color:#9096a0;font:13px/1.5 system-ui,sans-serif">'
			. esc_html( isset( $labels[ $key ] ) ? $labels[ $key ] : $key ) . ' — ' . esc_html__( 'no content for this product yet.', 'ricoman' ) . '</div>';
	}
	return $html;
}

/**
 * Register the live-preview section blocks + the full "Product page" pattern.
 * Each section is a dynamic block that renders server-side, so the editor canvas
 * shows a real preview and the ➕ between blocks inserts patterns anywhere.
 */
add_action( 'init', function () {
	register_block_pattern_category(
		'ricoman-product',
		array(
			'label'       => __( 'Ricoman: Product page', 'ricoman' ),
			'description' => __( 'Drop-in sections for building a product page.', 'ricoman' ),
		)
	);

	if ( function_exists( 'register_block_type' ) ) {
		foreach ( array_keys( ricoman_section_defs() ) as $key ) {
			register_block_type( 'ricoman/product-' . $key, array(
				'api_version'     => 2,
				'category'        => 'ricoman-product',
				'render_callback' => function () use ( $key ) {
					return ricoman_section_block_render( $key );
				},
				'supports'        => array( 'html' => false, 'reusable' => false, 'multiple' => false ),
			) );
		}
	}

	// The whole page, laid out as separate section blocks you can rearrange.
	register_block_pattern(
		'ricoman/product-page',
		array(
			'title'      => __( 'Ricoman: Product page (full layout)', 'ricoman' ),
			'categories' => array( 'ricoman-product' ),
			'postTypes'  => array( 'product' ),
			'content'    => ricoman_product_layout_blocks(),
		)
	);
} );

/** Give the section blocks their own editor category. */
add_filter( 'block_categories_all', function ( $cats ) {
	foreach ( $cats as $c ) {
		if ( isset( $c['slug'] ) && 'ricoman-product' === $c['slug'] ) {
			return $cats;
		}
	}
	$cats[] = array(
		'slug'  => 'ricoman-product',
		'title' => __( 'Ricoman: Product page', 'ricoman' ),
	);
	return $cats;
} );

/** Editor script that registers the blocks with a live ServerSideRender preview. */
add_action( 'enqueue_block_editor_assets', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_script(
		'ricoman-product-blocks',
		get_theme_file_uri( 'assets/js/product-blocks.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-data', 'wp-i18n' ),
		defined( 'RICOMAN_VERSION' ) ? RICOMAN_VERSION : false,
		true
	);
} );

/**
 * A short note in the product editor sidebar pointing to the dedicated live
 * Product Page Editor (the bespoke edit-left / preview-right screen).
 */
add_action( 'add_meta_boxes_product', function () {
	add_meta_box(
		'ricoman_product_layout',
		__( 'Product page editor', 'ricoman' ),
		'ricoman_product_layout_box',
		'product',
		'side',
		'high'
	);
} );

function ricoman_product_layout_box( $post ) {
	$url = admin_url( 'admin.php?page=ricoman-product-editor&product=' . (int) $post->ID );
	echo '<p class="description">' . esc_html__( 'Build this product’s page — edit the content with a live preview and drop patterns between sections — on the dedicated editor:', 'ricoman' ) . '</p>';
	echo '<p><a class="button button-primary button-large" href="' . esc_url( $url ) . '">' . esc_html__( 'Open Product Page Editor', 'ricoman' ) . '</a></p>';
}

/**
 * Product FAQs editor — a simple Q:/A: textarea on the product edit screen. The
 * content renders as the product's FAQ section (with FAQPage schema) via
 * ricoman_pf_sections(). Kept independent of the visual builder so it always works.
 */
add_action( 'add_meta_boxes_product', function () {
	add_meta_box(
		'ricoman_product_faq',
		__( 'Product FAQs', 'ricoman' ),
		'ricoman_product_faq_box',
		'product',
		'normal',
		'low'
	);
} );

function ricoman_product_faq_box( $post ) {
	wp_nonce_field( 'ricoman_product_faq', 'ricoman_product_faq_nonce' );
	$val = (string) get_post_meta( $post->ID, '_ricoman_faq', true );
	echo '<p class="description">' . esc_html__( 'One question/answer per pair, e.g.', 'ricoman' ) . ' <code>Q: ...</code> ' . esc_html__( 'then', 'ricoman' ) . ' <code>A: ...</code>. ' . esc_html__( 'Shows as an FAQ section on the product page and adds FAQ schema for SEO.', 'ricoman' ) . '</p>';
	echo '<textarea name="ricoman_product_faq" rows="10" style="width:100%;font-family:monospace" placeholder="Q: Is this fitting dimmable?&#10;A: Yes — it supports mains and DALI dimming.">' . esc_textarea( $val ) . '</textarea>';
}

add_action( 'save_post_product', function ( $pid ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['ricoman_product_faq_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ricoman_product_faq_nonce'] ) ), 'ricoman_product_faq' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $pid ) ) {
		return;
	}
	if ( isset( $_POST['ricoman_product_faq'] ) ) {
		$v = wp_kses_post( wp_unslash( $_POST['ricoman_product_faq'] ) );
		if ( '' !== trim( $v ) ) {
			update_post_meta( $pid, '_ricoman_faq', $v );
		} else {
			delete_post_meta( $pid, '_ricoman_faq' );
		}
	}
	// Refresh product-derived caches so the FAQ shows immediately.
	update_option( 'rm_products_ver', (string) time(), false );
} );

/* ============================================================ *
 * Colour finishes / variants — the chips shown on the product image.
 *
 * Each chip = a name + a swatch (the little colour dot) + the main photo shown
 * when that chip is clicked. Stored as a clean JSON list in _ricoman_finishes,
 * which ricoman_pf_color_variants() reads FIRST (falling back to the migrated
 * "Product Variation By Color" ACF field). Image values are an attachment ID
 * (new picks) or a URL (kept from migrated data) — both resolve via pf_imgurl.
 * ============================================================ */

/** Make the media library available on the product edit screen. */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && function_exists( 'get_current_screen' ) ) {
		$s = get_current_screen();
		if ( $s && 'product' === $s->post_type ) {
			wp_enqueue_media();
		}
	}
} );

/** Sanitize one image value: an attachment ID (numeric) or a URL. */
function ricoman_cv_clean( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	return is_numeric( $v ) ? (string) (int) $v : esc_url_raw( $v );
}

/** One finish row (server render; also used as the JS template for new rows). */
function ricoman_cv_row( $name = '', $main = '', $icon = '', $mainprev = '', $iconprev = '' ) {
	$ico = $iconprev ? ' style="background-image:url(' . esc_url( $iconprev ) . ')"' : '';
	$mai = $mainprev ? ' style="background-image:url(' . esc_url( $mainprev ) . ')"' : '';
	return '<div class="rmcv-row">'
		. '<span class="rmcv-move"><button type="button" class="rmcv-up" title="Move up" aria-label="Move up">&#9650;</button><button type="button" class="rmcv-dn" title="Move down" aria-label="Move down">&#9660;</button></span>'
		. '<span class="rmcv-pic rmcv-swatch"><button type="button" class="rmcv-pick"' . $ico . '>' . ( $iconprev ? '' : esc_html__( 'Swatch', 'ricoman' ) ) . '</button><input type="hidden" class="rmcv-val" name="rmcv_icon[]" value="' . esc_attr( $icon ) . '"></span>'
		. '<input type="text" class="rmcv-name" name="rmcv_name[]" value="' . esc_attr( $name ) . '" placeholder="' . esc_attr__( 'Finish name (e.g. Matte Black)', 'ricoman' ) . '">'
		. '<span class="rmcv-pic rmcv-image"><button type="button" class="rmcv-pick"' . $mai . '>' . ( $mainprev ? '' : esc_html__( 'Main image', 'ricoman' ) ) . '</button><input type="hidden" class="rmcv-val" name="rmcv_main[]" value="' . esc_attr( $main ) . '"></span>'
		. '<button type="button" class="rmcv-remove" title="Remove finish" aria-label="Remove finish">&times;</button>'
		. '</div>';
}

add_action( 'add_meta_boxes_product', function () {
	add_meta_box( 'ricoman_colour_finishes', __( 'Colour finishes (image chips)', 'ricoman' ), 'ricoman_colour_finishes_box', 'product', 'normal', 'low' );
} );

function ricoman_colour_finishes_box( $post ) {
	wp_nonce_field( 'ricoman_colour_finishes', 'ricoman_colour_finishes_nonce' );
	$rows = array();
	$raw  = get_post_meta( $post->ID, '_ricoman_finishes', true );
	if ( $raw ) {
		$d = json_decode( $raw, true );
		if ( is_array( $d ) ) {
			foreach ( $d as $r ) {
				if ( is_array( $r ) ) {
					$rows[] = array(
						'name' => isset( $r['name'] ) ? (string) $r['name'] : '',
						'main' => isset( $r['main'] ) ? $r['main'] : '',
						'icon' => isset( $r['icon'] ) ? $r['icon'] : '',
					);
				}
			}
		}
	}
	$seeded = false;
	if ( ! $rows && function_exists( 'ricoman_pf_color_variants' ) ) {
		foreach ( ricoman_pf_color_variants( $post->ID ) as $cv ) {
			$rows[] = array( 'name' => $cv['name'], 'main' => $cv['main'], 'icon' => $cv['icon'] );
		}
		$seeded = (bool) $rows;
	}
	$prev = function ( $v ) { return ( '' !== $v && function_exists( 'ricoman_pf_imgurl' ) ) ? ricoman_pf_imgurl( $v ) : ( is_string( $v ) ? $v : '' ); };

	echo '<style>'
		. '.rmcv-row{display:flex;align-items:center;gap:10px;margin:0 0 10px;padding:8px;border:1px solid #e0e0e0;border-radius:8px;background:#fff}'
		. '.rmcv-move{display:flex;flex-direction:column}.rmcv-move button{border:0;background:none;cursor:pointer;line-height:1;color:#787c82;font-size:11px;padding:1px}'
		. '.rmcv-pick{cursor:pointer;background-size:cover;background-position:center;font-size:9px;color:#50575e;padding:0;border:1px solid #c3c4c7;border-radius:8px}'
		. '.rmcv-swatch .rmcv-pick{width:42px;height:42px;border-radius:50%}'
		. '.rmcv-image .rmcv-pick{width:58px;height:58px}'
		. '.rmcv-name{flex:1}'
		. '.rmcv-remove{border:0;background:none;color:#b32d2e;cursor:pointer;font-size:16px;line-height:1}'
		. '</style>';
	echo '<p class="description">' . esc_html__( 'Each row is a colour chip on the product image: a name, a swatch (the small colour dot), and the main photo shown when that chip is selected. Use the arrows to reorder. Remove all rows to fall back to the migrated colour data.', 'ricoman' ) . '</p>';
	if ( $seeded ) {
		echo '<p class="description"><em>' . esc_html__( 'Loaded from the existing migrated colour data — click Update to save it here so you can edit it.', 'ricoman' ) . '</em></p>';
	}
	echo '<div id="rmcv-rows">';
	if ( ! $rows ) {
		$rows[] = array( 'name' => '', 'main' => '', 'icon' => '' );
	}
	foreach ( $rows as $r ) {
		echo ricoman_cv_row( $r['name'], $r['main'], $r['icon'], $prev( $r['main'] ), $prev( $r['icon'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</div>';
	echo '<p><button type="button" class="button button-secondary" id="rmcv-add">&#65291; ' . esc_html__( 'Add finish', 'ricoman' ) . '</button></p>';

	$tpl = ricoman_cv_row();
	echo '<script>(function(){var box=document.getElementById("rmcv-rows");if(!box)return;var TPL=' . wp_json_encode( $tpl ) . ';'
		. 'function pickImg(cb){if(!window.wp||!wp.media){window.alert("Media library not ready — reload the page.");return;}var f=wp.media({title:"Select image",multiple:false,library:{type:"image"}});f.on("select",function(){var a=f.state().get("selection").first().toJSON();var u=(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url);cb(a.id,u);});f.open();}'
		. 'document.addEventListener("click",function(e){'
		. 'var add=e.target.closest("#rmcv-add");if(add){e.preventDefault();box.insertAdjacentHTML("beforeend",TPL);return;}'
		. 'var pick=e.target.closest(".rmcv-pick");if(pick){e.preventDefault();var inp=pick.parentNode.querySelector(".rmcv-val");pickImg(function(id,u){inp.value=id;pick.style.backgroundImage="url("+u+")";pick.textContent="";});return;}'
		. 'var rm=e.target.closest(".rmcv-remove");if(rm){e.preventDefault();var r=rm.closest(".rmcv-row");if(r)r.parentNode.removeChild(r);return;}'
		. 'var up=e.target.closest(".rmcv-up");if(up){e.preventDefault();var ru=up.closest(".rmcv-row");if(ru&&ru.previousElementSibling)ru.parentNode.insertBefore(ru,ru.previousElementSibling);return;}'
		. 'var dn=e.target.closest(".rmcv-dn");if(dn){e.preventDefault();var rd=dn.closest(".rmcv-row");if(rd&&rd.nextElementSibling)rd.parentNode.insertBefore(rd.nextElementSibling,rd);return;}'
		. '});})();</script>';
}

add_action( 'save_post_product', function ( $pid ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['ricoman_colour_finishes_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ricoman_colour_finishes_nonce'] ) ), 'ricoman_colour_finishes' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $pid ) ) {
		return;
	}
	$names = isset( $_POST['rmcv_name'] ) ? (array) wp_unslash( $_POST['rmcv_name'] ) : array();
	$mains = isset( $_POST['rmcv_main'] ) ? (array) wp_unslash( $_POST['rmcv_main'] ) : array();
	$icons = isset( $_POST['rmcv_icon'] ) ? (array) wp_unslash( $_POST['rmcv_icon'] ) : array();
	$out   = array();
	$n     = max( count( $names ), count( $mains ), count( $icons ) );
	for ( $i = 0; $i < $n; $i++ ) {
		$name = isset( $names[ $i ] ) ? sanitize_text_field( $names[ $i ] ) : '';
		$main = isset( $mains[ $i ] ) ? ricoman_cv_clean( $mains[ $i ] ) : '';
		$icon = isset( $icons[ $i ] ) ? ricoman_cv_clean( $icons[ $i ] ) : '';
		if ( '' === $name && '' === $main && '' === $icon ) {
			continue;
		}
		$out[] = array( 'name' => $name, 'main' => $main, 'icon' => $icon );
	}
	if ( $out ) {
		update_post_meta( $pid, '_ricoman_finishes', wp_json_encode( $out ) );
	} else {
		delete_post_meta( $pid, '_ricoman_finishes' );
	}
	update_option( 'rm_products_ver', (string) time(), false );
} );
