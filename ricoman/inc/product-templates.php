<?php
/**
 * Product page templates.
 *
 * A product page is built from a *template* — a saved layout of sections +
 * patterns/elements — chosen per product. We ship a "Standard" template and
 * feature templates ("Flow", "Estrella"), and more can be added. Each template
 * is edited in the same visual builder as a product.
 *
 * Inheritance (chosen model: inherit, override optional):
 *   1. If a product has its own customised layout  → use it.
 *   2. else use its chosen template's layout.
 *   3. else the Standard template / the built-in default.
 *
 * Templates are stored as a small 'rm_ptemplate' post type (title + layout meta),
 * so "add a template" is just adding a post, and the builder can edit them.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* --------------------------------------------------------------- post type */

add_action( 'init', function () {
	register_post_type( 'rm_ptemplate', array(
		'labels'       => array(
			'name'          => __( 'Product Templates', 'ricoman' ),
			'singular_name' => __( 'Product Template', 'ricoman' ),
			'add_new_item'  => __( 'Add Product Template', 'ricoman' ),
			'edit_item'     => __( 'Edit Product Template', 'ricoman' ),
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => false, // surfaced under the Ricoman menu instead.
		'supports'     => array( 'title' ),
		'rewrite'      => false,
		'query_var'    => false,
	) );
} );

/** Seed the starter templates once. */
add_action( 'admin_init', function () {
	if ( get_option( 'ricoman_templates_seeded' ) ) {
		return;
	}
	$defaults = function_exists( 'ricoman_pe_default_layout' ) ? ricoman_pe_default_layout() : array();
	foreach ( array( 'Standard', 'Flow', 'Estrella' ) as $name ) {
		$existing = get_page_by_title( $name, OBJECT, 'rm_ptemplate' );
		if ( $existing ) {
			continue;
		}
		$id = wp_insert_post( array(
			'post_type'   => 'rm_ptemplate',
			'post_status' => 'publish',
			'post_title'  => $name,
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_ricoman_layout', wp_json_encode( $defaults ) );
			if ( 'Standard' === $name ) {
				update_option( 'ricoman_standard_template', $id );
			}
		}
	}
	update_option( 'ricoman_templates_seeded', 1 );
} );

/** Templates as id => name. */
function ricoman_pe_templates() {
	$out = array();
	$q   = get_posts( array(
		'post_type'      => 'rm_ptemplate',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	foreach ( $q as $p ) {
		$out[ $p->ID ] = $p->post_title;
	}
	return $out;
}

/** The Standard template's post ID (or 0). */
function ricoman_pe_standard_template() {
	$id = (int) get_option( 'ricoman_standard_template' );
	if ( $id && 'rm_ptemplate' === get_post_type( $id ) ) {
		return $id;
	}
	$p = get_page_by_title( 'Standard', OBJECT, 'rm_ptemplate' );
	return $p ? (int) $p->ID : 0;
}

/** A template's layout (array), or the built-in default. */
function ricoman_template_layout( $tid ) {
	$raw = $tid ? get_post_meta( $tid, '_ricoman_layout', true ) : '';
	if ( $raw ) {
		$d = json_decode( $raw, true );
		if ( is_array( $d ) && $d ) {
			return $d;
		}
	}
	return function_exists( 'ricoman_pe_default_layout' ) ? ricoman_pe_default_layout() : array();
}

/* --------------------------------------------------------- resolve & render */

/** Does this product have an explicitly managed layout (custom or template)? */
function ricoman_pe_has_managed_layout( $pid ) {
	return get_post_meta( $pid, '_ricoman_custom', true ) || get_post_meta( $pid, '_ricoman_template', true );
}

/** The layout that should render for a product (custom > template > standard > default). */
function ricoman_pe_resolve_layout( $pid ) {
	if ( get_post_meta( $pid, '_ricoman_custom', true ) && function_exists( 'ricoman_pe_get_layout' ) ) {
		return ricoman_pe_get_layout( $pid );
	}
	$tid = (int) get_post_meta( $pid, '_ricoman_template', true );
	if ( ! $tid ) {
		$tid = ricoman_pe_standard_template();
	}
	if ( $tid ) {
		return ricoman_template_layout( $tid );
	}
	return function_exists( 'ricoman_pe_default_layout' ) ? ricoman_pe_default_layout() : array();
}

/** Render a layout for a product: built section HTML + rendered patterns. */
function ricoman_pe_render_layout( $pid, $layout = null ) {
	if ( null === $layout ) {
		$layout = ricoman_pe_resolve_layout( $pid );
	}
	$sections = function_exists( 'ricoman_pf_sections' ) ? ricoman_pf_sections( $pid ) : array();
	$reg      = class_exists( 'WP_Block_Patterns_Registry' ) ? WP_Block_Patterns_Registry::get_instance() : null;
	// Estrella-style range content isn't in older saved layouts, so inject it after
	// the configure section for hub products (and don't double-render it).
	$range_html = isset( $sections['range'] ) ? (string) $sections['range'] : '';
	$range_done = '' === $range_html;
	// Downloads section isn't in older saved layouts; inject it before configure.
	$dl_html    = isset( $sections['downloads'] ) ? (string) $sections['downloads'] : '';
	$dl_done    = '' === $dl_html;
	$out        = '';
	foreach ( (array) $layout as $it ) {
		$type = isset( $it['type'] ) ? $it['type'] : '';
		if ( 'section' === $type && ! empty( $it['key'] ) ) {
			// Inject downloads before configure if not explicitly in the layout.
			if ( 'configure' === $it['key'] && ! $dl_done ) {
				$out    .= $dl_html;
				$dl_done = true;
			}
			if ( ! isset( $it['on'] ) || $it['on'] ) {
				$out .= isset( $sections[ $it['key'] ] ) ? $sections[ $it['key'] ] : '';
			}
			if ( 'range' === $it['key'] ) {
				$range_done = true; // layout already places it explicitly.
			} elseif ( 'configure' === $it['key'] && ! $range_done ) {
				$out       .= $range_html;
				$range_done = true;
			} elseif ( 'downloads' === $it['key'] ) {
				$dl_done = true; // layout already places it explicitly.
			}
		} elseif ( 'pattern' === $type && ! empty( $it['name'] ) && $reg && $reg->is_registered( $it['name'] ) ) {
			if ( ! empty( $it['html'] ) ) {
				// Per-page override: TinyMCE produces rendered HTML, not block markup.
				$out .= wp_kses_post( $it['html'] );
			} else {
				$p    = $reg->get_registered( $it['name'] );
				$out .= do_blocks( isset( $p['content'] ) ? $p['content'] : '' );
			}
		}
	}
	if ( ! $range_done ) {
		$out .= $range_html; // no configure section in the layout — append at the end.
	}
	if ( ! $dl_done ) {
		$out .= $dl_html; // no configure section in the layout — append at the end.
	}
	return $out;
}

/* -------------------------------------------------- per-product template pick */

add_action( 'add_meta_boxes_product', function () {
	add_meta_box(
		'ricoman_product_template',
		__( 'Page template', 'ricoman' ),
		'ricoman_product_template_box',
		'product',
		'side',
		'default'
	);
} );

function ricoman_product_template_box( $post ) {
	$cur     = (int) get_post_meta( $post->ID, '_ricoman_template', true );
	if ( ! $cur ) {
		$cur = ricoman_pe_standard_template();
	}
	$custom  = get_post_meta( $post->ID, '_ricoman_custom', true );
	wp_nonce_field( 'ricoman_product_template', 'ricoman_product_template_nonce' );
	echo '<p class="description">' . esc_html__( 'The template this product’s page follows. Feature ranges (Flow, Estrella) have their own template.', 'ricoman' ) . '</p>';
	echo '<select name="ricoman_template" style="width:100%">';
	foreach ( ricoman_pe_templates() as $id => $name ) {
		echo '<option value="' . esc_attr( $id ) . '"' . selected( $id, $cur, false ) . '>' . esc_html( $name ) . '</option>';
	}
	echo '</select>';
	if ( $custom ) {
		echo '<p class="description" style="margin-top:10px;color:#b26a00">' . esc_html__( 'This product has custom layout edits that override its template.', 'ricoman' ) . '</p>';
		echo '<label style="display:block;margin-top:6px"><input type="checkbox" name="ricoman_reset_custom" value="1"> ' . esc_html__( 'Reset to the template layout', 'ricoman' ) . '</label>';
	}
}

add_action( 'save_post_product', function ( $pid ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( empty( $_POST['ricoman_product_template_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ricoman_product_template_nonce'] ), 'ricoman_product_template' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $pid ) ) {
		return;
	}
	if ( isset( $_POST['ricoman_template'] ) ) {
		update_post_meta( $pid, '_ricoman_template', absint( $_POST['ricoman_template'] ) );
	}
	if ( ! empty( $_POST['ricoman_reset_custom'] ) ) {
		delete_post_meta( $pid, '_ricoman_custom' );
		delete_post_meta( $pid, '_ricoman_layout' );
	}
} );

/* ------------------------------------------------------------- admin menu */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Product Templates', 'ricoman' ),
		__( 'Product Templates', 'ricoman' ),
		'edit_posts',
		'edit.php?post_type=rm_ptemplate'
	);
}, 30 );

/** On the templates list, point "edit" at the visual builder. */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'rm_ptemplate' === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
		$url     = admin_url( 'admin.php?page=ricoman-product-editor&template=' . $post->ID );
		$actions = array( 'rm_build' => '<a href="' . esc_url( $url ) . '"><strong>' . esc_html__( 'Edit in builder', 'ricoman' ) . '</strong></a>' ) + $actions;
	}
	return $actions;
}, 10, 2 );
