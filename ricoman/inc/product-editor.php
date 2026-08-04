<?php
/**
 * Custom Product Page Editor — a Shopify-style visual builder.
 *
 * Three panes: section/pattern list (left), a live device-framed preview that
 * updates as you edit (centre), and contextual settings for the selected item
 * (right). Sections can be dragged to reorder, toggled on/off, and patterns can
 * be dropped into any gap. Edits stream into a per-user draft so the preview is
 * always live; nothing is persisted until "Save".
 *
 * Storage: the arrangement is a small layout list saved to post meta
 * (_ricoman_layout) and compiled into the product's post_content (section blocks
 * + chosen pattern content), so the live site renders exactly what you build.
 * Untouched products keep auto-rendering the default layout.
 *
 * Reached via admin.php?page=ricoman-product-editor&product=ID.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------- data model */

/** Editable content fields shown in the builder: meta key => label (legacy). */
function ricoman_pe_fields() {
	return array(
		'product_subname'          => __( 'Subtitle', 'ricoman' ),
		'product_sort_description' => __( 'Short description', 'ricoman' ),
		'product_code'             => __( 'Order code', 'ricoman' ),
	);
}

/**
 * Editable fields grouped by the section they belong to: [key, label, type].
 * type is 'text' or 'textarea'. Keys map to ACF/meta fields (or post_title).
 */
function ricoman_pe_field_groups() {
	return array(
		'hero'  => array(
			array( 'title', __( 'Title', 'ricoman' ), 'text' ),
			array( 'product_subname', __( 'Subtitle', 'ricoman' ), 'text' ),
			array( 'product_sort_description', __( 'Short description', 'ricoman' ), 'textarea' ),
			array( 'product_code', __( 'Order code', 'ricoman' ), 'text' ),
			array( 'key_features', __( 'Key features (one per line)', 'ricoman' ), 'textarea' ),
			array( '_ricoman_ld_btn', __( '“Lighting design” button label', 'ricoman' ), 'text' ),
			array( '_ricoman_ld_url', __( '“Lighting design” button link', 'ricoman' ), 'text' ),
			array( '_ricoman_trade_btn', __( '“Trade account” button label', 'ricoman' ), 'text' ),
			array( '_ricoman_trade_url', __( '“Trade account” button link', 'ricoman' ), 'text' ),
		),
		'specs' => array(
			array( 'specification', __( 'Specification (HTML)', 'ricoman' ), 'textarea' ),
		),
		'faq'  => array(
			array( '_ricoman_faq', __( 'FAQs (Q: / A: format, one pair per question)', 'ricoman' ), 'textarea' ),
		),
	);
}

/** Default layout: every section, in order, enabled. */
function ricoman_pe_default_layout() {
	$items = array();
	foreach ( array_keys( ricoman_section_defs() ) as $key ) {
		$items[] = array( 'type' => 'section', 'key' => $key, 'on' => true );
	}
	return $items;
}

/**
 * Rebuild a layout list from compiled post_content. Recovery path for products
 * whose stored _ricoman_layout JSON was corrupted by the pre-wp_slash save bug
 * (undecodable JSON). Maps our section block comments back to section items and
 * the HTML between them to pattern items, preserving order — so the editor shows
 * the real layout (incl. patterns) and a re-save writes clean JSON.
 */
function ricoman_pe_layout_from_content( $content ) {
	$content = (string) $content;
	if ( '' === trim( $content ) || ! function_exists( 'ricoman_section_defs' ) ) {
		return array();
	}
	$keys  = array_keys( ricoman_section_defs() );
	$out   = array();
	// Split on our section block comments, capturing the section key.
	$parts = preg_split( '/<!--\s*wp:ricoman\/product-([a-z0-9_-]+)\s*\/-->/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( (array) $parts as $i => $part ) {
		if ( 1 === $i % 2 ) { // captured section key
			if ( in_array( $part, $keys, true ) ) {
				$out[] = array( 'type' => 'section', 'key' => $part, 'on' => true );
			}
			continue;
		}
		$html = trim( (string) $part ); // HTML between sections = a dropped-in pattern
		if ( '' === $html ) {
			continue;
		}
		$name = 'ricoman/product-mediapanel';
		if ( false !== strpos( $html, 'rm-mediapanel--right' ) ) {
			$name = 'ricoman/product-mediapanel-rev';
		} elseif ( false === strpos( $html, 'rm-mediapanel' ) ) {
			$name = 'ricoman/product-block'; // unknown block; keep html so it still renders + edits.
		}
		$out[] = array( 'type' => 'pattern', 'name' => $name, 'html' => $html );
	}
	return $out;
}

/** Read a product's saved layout (or the default). */
function ricoman_pe_get_layout( $pid ) {
	$raw = get_post_meta( $pid, '_ricoman_layout', true );
	if ( $raw ) {
		$data = json_decode( $raw, true );
		if ( is_array( $data ) && $data ) {
			return $data;
		}
		// Corrupt/invalid JSON (legacy pre-wp_slash save): rebuild from the
		// compiled post_content so the real layout still loads in the editor.
		$rebuilt = ricoman_pe_layout_from_content( get_post_field( 'post_content', $pid ) );
		if ( $rebuilt ) {
			return $rebuilt;
		}
	}
	return ricoman_pe_default_layout();
}

// TEMP diagnostic (remove after fixing the editor-layout issue): expose the key
// layout flags per product via REST so we can see why the editor loads a
// different layout than the front end renders.
add_action( 'rest_api_init', function () {
	if ( ! function_exists( 'register_rest_field' ) ) {
		return;
	}
	register_rest_field( 'product', 'rm_layout_debug', array(
		'get_callback' => function ( $obj ) {
			$pid     = is_array( $obj ) && isset( $obj['id'] ) ? (int) $obj['id'] : 0;
			$layout  = (string) get_post_meta( $pid, '_ricoman_layout', true );
			$content = (string) get_post_field( 'post_content', $pid );
			$decoded = json_decode( $layout, true );
			$items   = array();
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $it ) {
					$html   = isset( $it['html'] ) ? (string) $it['html'] : '';
					$items[] = array(
						't'    => isset( $it['type'] ) ? $it['type'] : '?',
						'k'    => isset( $it['key'] ) ? $it['key'] : ( isset( $it['name'] ) ? $it['name'] : '' ),
						'hLen' => strlen( $html ),
						// Sequences that can break the inline <script> or a JS string literal:
						'endScript' => ( '' !== $html && false !== stripos( $html, '</script' ) ) ? 1 : 0,
						'u2028'     => ( '' !== $html && ( false !== strpos( $html, "\xE2\x80\xA8" ) || false !== strpos( $html, "\xE2\x80\xA9" ) ) ) ? 1 : 0,
					);
				}
			}
			$resolved = function_exists( 'ricoman_pe_get_layout' ) ? ricoman_pe_get_layout( $pid ) : array();
			$res      = array();
			foreach ( (array) $resolved as $it ) {
				$res[] = ( isset( $it['type'] ) ? $it['type'] : '?' ) . ':' . ( isset( $it['key'] ) ? $it['key'] : ( isset( $it['name'] ) ? $it['name'] : '' ) );
			}
			return array(
				'custom'       => get_post_meta( $pid, '_ricoman_custom', true ) ? 1 : 0,
				'jsonOk'       => is_array( $decoded ) ? 1 : 0,
				'itemCount'    => is_array( $decoded ) ? count( $decoded ) : -1,
				'resolved'     => $res,
				'contentHasMP' => ( false !== strpos( $content, 'mediapanel' ) ) ? 1 : 0,
			);
		},
	) );
} );

/** Patterns an admin can drop between sections (the theme's own patterns). */
function ricoman_pe_patterns() {
	$out = array();
	if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
		return $out;
	}
	foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $p ) {
		$name = isset( $p['name'] ) ? $p['name'] : '';
		$cats = isset( $p['categories'] ) ? (array) $p['categories'] : array();
		$ours = ( 0 === strpos( $name, 'ricoman/' ) )
			|| array_intersect( $cats, array( 'ricoman', 'ricoman-pages', 'ricoman-product' ) );
		if ( ! $ours || 'ricoman/product-page' === $name ) {
			continue;
		}
		$out[ $name ] = isset( $p['title'] ) ? $p['title'] : $name;
	}
	asort( $out );
	return $out;
}

/**
 * Re-absolutise asset URLs in per-page pattern HTML.
 *
 * The page editor's TinyMCE can relativise absolute URLs against the wp-admin
 * base (e.g. ../wp-content/… or ../../wp-includes/…). Those paths 404 on the
 * front-end product page, so the image "disappears" after saving. Rewrite any
 * ../-chained wp-content / wp-includes / uploads URL back to the site root.
 */
function ricoman_pe_absolute_urls( $html ) {
	if ( ! is_string( $html ) || '' === $html || false === strpos( $html, '../' ) ) {
		return $html;
	}
	$root = untrailingslashit( site_url() ); // wp-content / wp-includes hang off the install root.
	// Anchor on the opening quote or paren so this covers src/href/srcset and
	// CSS url(); collapse any ../ chain to the site root.
	return preg_replace(
		'#(["\'(])(?:\.\./)+(wp-content/|wp-includes/)#i',
		'$1' . $root . '/$2',
		$html
	);
}

/** Compile a layout list into post_content (section blocks + pattern content). */
function ricoman_pe_build_content( $layout ) {
	$reg     = class_exists( 'WP_Block_Patterns_Registry' ) ? WP_Block_Patterns_Registry::get_instance() : null;
	$keys    = array_keys( ricoman_section_defs() );
	$content = '';
	foreach ( (array) $layout as $item ) {
		$type = isset( $item['type'] ) ? $item['type'] : '';
		if ( 'section' === $type && ! empty( $item['key'] ) && in_array( $item['key'], $keys, true ) ) {
			if ( ! isset( $item['on'] ) || $item['on'] ) {
				$content .= '<!-- wp:ricoman/product-' . $item['key'] . ' /-->' . "\n";
			}
		} elseif ( 'pattern' === $type && ! empty( $item['name'] ) ) {
			// Per-page edited content wins; otherwise the shared registered template.
			if ( ! empty( $item['html'] ) ) {
				$content .= ricoman_pe_absolute_urls( $item['html'] ) . "\n";
			} elseif ( $reg && $reg->is_registered( $item['name'] ) ) {
				$pat      = $reg->get_registered( $item['name'] );
				$content .= ( isset( $pat['content'] ) ? $pat['content'] : '' ) . "\n";
			}
		}
	}
	return $content;
}

/** Transient key holding this user's unsaved draft for a product. */
function ricoman_pe_draft_key( $pid ) {
	return 'rm_pe_draft_' . (int) $pid . '_' . get_current_user_id();
}

/* ------------------------------------------------------- live preview render */

/** Are we rendering a builder preview of the current product? */
function ricoman_pe_is_preview() {
	if ( empty( $_GET['rmpe'] ) || ! is_singular( 'product' ) || ! is_user_logged_in() ) {
		return false;
	}
	return current_user_can( 'edit_post', get_queried_object_id() );
}

/** Apply the draft field overrides + compiled draft layout while previewing. */
add_action( 'wp', function () {
	if ( ! ricoman_pe_is_preview() ) {
		return;
	}
	$pid   = get_queried_object_id();
	$draft = get_transient( ricoman_pe_draft_key( $pid ) );
	$fallback = function_exists( 'ricoman_pe_resolve_layout' ) ? ricoman_pe_resolve_layout( $pid ) : ricoman_pe_get_layout( $pid );
	$GLOBALS['rm_pe_preview'] = array(
		'pid'    => $pid,
		'fields' => ( is_array( $draft ) && ! empty( $draft['fields'] ) ) ? $draft['fields'] : array(),
		'layout' => ( is_array( $draft ) && ! empty( $draft['layout'] ) ) ? $draft['layout'] : $fallback,
	);
	if ( is_array( $draft ) && isset( $draft['cols'] ) && is_array( $draft['cols'] ) ) {
		$GLOBALS['rm_pe_preview']['cols'] = $draft['cols'];
	}
	if ( is_array( $draft ) && isset( $draft['filteroff'] ) && is_array( $draft['filteroff'] ) ) {
		$GLOBALS['rm_pe_preview']['filteroff'] = $draft['filteroff'];
	}
	if ( is_array( $draft ) && isset( $draft['configvisual'] ) && null !== $draft['configvisual'] ) {
		$GLOBALS['rm_pe_preview']['configvisual'] = (bool) $draft['configvisual'];
	}
	foreach ( array( 'gallery', 'insitu' ) as $gk ) {
		if ( is_array( $draft ) && isset( $draft[ $gk ] ) && is_array( $draft[ $gk ] ) ) {
			$GLOBALS['rm_pe_preview'][ $gk ] = $draft[ $gk ];
		}
	}
	if ( is_array( $draft ) && array_key_exists( 'accessories', $draft ) && is_array( $draft['accessories'] ) ) {
		$GLOBALS['rm_pe_preview']['accessories'] = array_map( 'absint', $draft['accessories'] );
	}
	add_filter( 'body_class', function ( $classes ) {
		return array_merge( $classes, array( 'rm-pe-preview' ) );
	} );
} );

/** Title override in preview. */
add_filter( 'the_title', function ( $title, $id ) {
	if ( empty( $GLOBALS['rm_pe_preview'] ) || (int) $id !== (int) $GLOBALS['rm_pe_preview']['pid'] ) {
		return $title;
	}
	$t = isset( $GLOBALS['rm_pe_preview']['fields']['title'] ) ? $GLOBALS['rm_pe_preview']['fields']['title'] : '';
	return '' !== $t ? $t : $title;
}, 10, 2 );

/** Render the compiled draft layout in preview, ahead of the normal renderers. */
add_filter( 'the_content', function ( $content ) {
	if ( empty( $GLOBALS['rm_pe_preview'] ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$pid = (int) $GLOBALS['rm_pe_preview']['pid'];
	return function_exists( 'ricoman_pe_render_layout' )
		? ricoman_pe_render_layout( $pid, $GLOBALS['rm_pe_preview']['layout'] )
		: ricoman_pe_build_content( $GLOBALS['rm_pe_preview']['layout'] );
}, 8 );

/* ------------------------------------------------------------------- routing */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'options.php',
		__( 'Product Page Editor', 'ricoman' ),
		__( 'Product Page Editor', 'ricoman' ),
		'edit_posts',
		'ricoman-product-editor',
		'ricoman_product_editor_render'
	);
} );

/**
 * Make the visual builder the default screen when editing a product. The native
 * WordPress editor stays reachable via the "All fields" link (?classic=1).
 */
add_action( 'load-post.php', function () {
	if ( empty( $_GET['post'] ) ) {
		return;
	}
	if ( isset( $_GET['action'] ) && 'edit' !== $_GET['action'] ) {
		return;
	}
	if ( isset( $_GET['classic'] ) ) {
		return; // escape hatch to the classic editor.
	}
	$pid = (int) $_GET['post'];
	if ( 'product' !== get_post_type( $pid ) || ! current_user_can( 'edit_post', $pid ) ) {
		return;
	}
	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-product-editor&product=' . $pid ) );
	exit;
} );

/** Row action on the product list. */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'product' === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
		$url                = admin_url( 'admin.php?page=ricoman-product-editor&product=' . $post->ID );
		$actions['rm_page'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Page Editor', 'ricoman' ) . '</a>';
	}
	return $actions;
}, 10, 2 );

/* --------------------------------------------------------------- ajax: draft */

add_action( 'wp_ajax_ricoman_pe_draft', function () {
	$pid = isset( $_POST['product'] ) ? absint( $_POST['product'] ) : 0;
	if ( ! $pid || ! current_user_can( 'edit_post', $pid ) || ! check_ajax_referer( 'ricoman_pe_' . $pid, 'nonce', false ) ) {
		wp_send_json_error();
	}
	$layout = array();
	$fields = array();
	if ( isset( $_POST['layout'] ) ) {
		$d = json_decode( wp_unslash( $_POST['layout'] ), true );
		if ( is_array( $d ) ) {
			$layout = $d;
		}
	}
	if ( isset( $_POST['fields'] ) ) {
		$d = json_decode( wp_unslash( $_POST['fields'] ), true );
		if ( is_array( $d ) ) {
			$fields = array_map( 'wp_kses_post', $d );
		}
	}
	$cols = null;
	if ( isset( $_POST['cols'] ) ) {
		$d = json_decode( wp_unslash( $_POST['cols'] ), true );
		if ( is_array( $d ) ) {
			$cols = array_map( 'sanitize_text_field', $d );
		}
	}
	$filteroff = null;
	if ( isset( $_POST['filteroff'] ) ) {
		$d = json_decode( wp_unslash( $_POST['filteroff'] ), true );
		if ( is_array( $d ) ) {
			$filteroff = array_map( 'sanitize_text_field', $d );
		}
	}
	$ids = function ( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return null;
		}
		$d = json_decode( wp_unslash( $_POST[ $key ] ), true );
		return is_array( $d ) ? array_map( 'absint', $d ) : null;
	};
	$configvisual = isset( $_POST['configvisual'] ) ? ( '1' === (string) wp_unslash( $_POST['configvisual'] ) ) : null;
	set_transient( ricoman_pe_draft_key( $pid ), array(
		'layout'       => $layout,
		'fields'       => $fields,
		'cols'         => $cols,
		'filteroff'    => $filteroff,
		'configvisual' => $configvisual,
		'gallery'      => $ids( 'gallery' ),
		'insitu'       => $ids( 'insitu' ),
		'accessories'  => $ids( 'accessories' ),
	), HOUR_IN_SECONDS );
	wp_send_json_success();
} );

/** Product search for the accessories picker. */
add_action( 'wp_ajax_ricoman_pe_acc_search', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_ajax_referer( 'ricoman_pe_thumb', 'nonce', false ) ) {
		wp_send_json_error();
	}
	$q   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$exc = isset( $_GET['exclude'] ) ? absint( $_GET['exclude'] ) : 0;
	$ps  = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 10,
		'post__not_in'   => $exc ? array( $exc ) : array(),
		's'              => $q,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	wp_send_json_success( array_map( function ( $p ) {
		return array( 'id' => $p->ID, 'title' => $p->post_title );
	}, $ps ) );
} );

/** Return a pattern's shared template content (to seed the per-page editor). */
add_action( 'wp_ajax_ricoman_pe_patcontent', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_ajax_referer( 'ricoman_pe_thumb', 'nonce', false ) ) {
		wp_send_json_error();
	}
	$name = isset( $_GET['name'] ) ? sanitize_text_field( wp_unslash( $_GET['name'] ) ) : '';
	if ( '' === $name || ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
		wp_send_json_error();
	}
	$reg = WP_Block_Patterns_Registry::get_instance();
	if ( ! $reg->is_registered( $name ) ) {
		wp_send_json_error();
	}
	$p = $reg->get_registered( $name );
	wp_send_json_success( isset( $p['content'] ) ? $p['content'] : '' );
} );

/**
 * Render a single pattern (or a product section) as a standalone HTML document
 * with the theme's front-end CSS, for the visual picker's thumbnail iframes.
 * Output is non-interactive (pointer-events disabled) and same-origin.
 */
add_action( 'wp_ajax_ricoman_pe_thumb', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_ajax_referer( 'ricoman_pe_thumb', 'nonce', false ) ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}
	$pid  = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : 0;
	$kind = isset( $_GET['kind'] ) ? sanitize_key( $_GET['kind'] ) : '';
	$name = isset( $_GET['name'] ) ? sanitize_text_field( wp_unslash( $_GET['name'] ) ) : '';

	// Cache each rendered preview — the picker requests ~150 of these and every
	// one is a full do_blocks()/do_shortcode() render. Keyed by the product's
	// last-modified time so an edit refreshes its own previews, and by the CSS
	// mtime so a restyle refreshes all of them.
	$sig  = ( $pid ? (string) get_post_modified_time( 'U', true, $pid ) : '0' )
		. '|' . ( file_exists( get_theme_file_path( 'assets/css/ricoman.css' ) ) ? (string) filemtime( get_theme_file_path( 'assets/css/ricoman.css' ) ) : '0' );
	$ckey = 'rmpe_thumb_' . md5( $kind . '|' . $name . '|' . $pid . '|' . $sig );
	$body = get_transient( $ckey );
	if ( false === $body ) {
		$body = '';
		if ( 'section' === $kind && $pid ) {
			if ( $GLOBALS['post'] = get_post( $pid ) ) { // phpcs:ignore
				setup_postdata( $GLOBALS['post'] );
			}
			$s    = function_exists( 'ricoman_pf_sections' ) ? ricoman_pf_sections( $pid ) : array();
			$body = isset( $s[ $name ] ) ? $s[ $name ] : '';
			wp_reset_postdata();
		} elseif ( 'pattern' === $kind && class_exists( 'WP_Block_Patterns_Registry' ) ) {
			$reg = WP_Block_Patterns_Registry::get_instance();
			if ( $reg->is_registered( $name ) ) {
				if ( $pid && ( $GLOBALS['post'] = get_post( $pid ) ) ) { // phpcs:ignore
					setup_postdata( $GLOBALS['post'] );
				}
				$p    = $reg->get_registered( $name );
				$body = do_shortcode( do_blocks( isset( $p['content'] ) ? $p['content'] : '' ) );
				wp_reset_postdata();
			}
		}
		set_transient( $ckey, (string) $body, DAY_IN_SECONDS );
	}

	$links = '';
	foreach ( array( 'assets/css/fonts.css', 'assets/css/shared.css', 'assets/css/ricoman.css' ) as $c ) {
		$links .= '<link rel="stylesheet" href="' . esc_url( get_theme_file_uri( $c ) ) . '">';
	}
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	// Let the browser keep each preview for a day so re-opening the picker or
	// scrolling back is instant (no re-fetch/re-render). The URL already varies
	// by product + pattern, and the signature transient invalidates on edits.
	header( 'Cache-Control: private, max-age=86400' );
	echo '<!doctype html><html lang="en"><head><meta charset="utf-8">' . $links // phpcs:ignore
		. '<style>html,body{margin:0;padding:0;background:#fff;pointer-events:none;width:1280px;overflow:hidden}.rm-lightbox{display:none!important}</style></head><body>'
		. $body . '</body></html>';
	exit;
} );

/* -------------------------------------------------------------------- screen */

function ricoman_product_editor_render() {
	$tpl    = isset( $_GET['template'] ) ? absint( $_GET['template'] ) : 0;
	$is_tpl = $tpl && 'rm_ptemplate' === get_post_type( $tpl );

	if ( $is_tpl ) {
		if ( ! current_user_can( 'edit_post', $tpl ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ricoman' ) );
		}
		// A representative product to preview the template against.
		$sample = get_posts( array( 'post_type' => 'product', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_ricoman_template', 'value' => $tpl ) ) ) );
		if ( ! $sample ) {
			$sample = get_posts( array( 'post_type' => 'product', 'posts_per_page' => 1, 'fields' => 'ids' ) );
		}
		$pid = $sample ? (int) $sample[0] : 0;
		if ( ! $pid ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Product Templates', 'ricoman' ) . '</h1><p>' . esc_html__( 'Add at least one product first — templates preview against a real product.', 'ricoman' ) . '</p></div>';
			return;
		}
		$layout    = array_values( ricoman_template_layout( $tpl ) );
		$headTitle = get_the_title( $tpl ) . ' — ' . __( 'template', 'ricoman' );
	} else {
		$pid = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : 0;
		if ( ! $pid || 'product' !== get_post_type( $pid ) || ! current_user_can( 'edit_post', $pid ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Product Page Editor', 'ricoman' ) . '</h1><p>' . esc_html__( 'Open this from a product (Products → row → Page Editor).', 'ricoman' ) . '</p></div>';
			return;
		}
		$layout    = array_values( function_exists( 'ricoman_pe_resolve_layout' ) ? ricoman_pe_resolve_layout( $pid ) : ricoman_pe_get_layout( $pid ) );
		$headTitle = get_the_title( $pid );
	}

	wp_enqueue_media(); // WordPress media frame for the gallery picker.
	wp_enqueue_editor(); // TinyMCE for the Specification WYSIWYG.

	$patterns = ricoman_pe_patterns();
	$labels   = ricoman_section_defs();
	$fgroups  = ricoman_pe_field_groups();
	$preview  = add_query_arg( 'rmpe', 1, get_permalink( $pid ) );

	// Gallery values (id + url) for the media picker.
	// Always read raw post_meta to bypass ACF format conversion (which drops
	// items when an attachment ID doesn't resolve on this site).
	$gallery_of = function ( $key ) use ( $pid ) {
		$out  = array();
		$seen = array();

		// Primary: raw post_meta (plain integer IDs or serialised arrays).
		$raw = get_post_meta( $pid, $key, true );
		// Fallback: ACF-processed value (attachment arrays, useful when raw is empty).
		if ( ! is_array( $raw ) || ! $raw ) {
			if ( function_exists( 'get_field' ) ) {
				$raw = get_field( $key, $pid );
			}
		}

		if ( ! is_array( $raw ) ) {
			return $out;
		}

		foreach ( $raw as $item ) {
			$id = 0;
			$u  = '';

			if ( is_numeric( $item ) && (int) $item > 0 ) {
				$id = (int) $item;
				// Resolve URL for this attachment on the current site.
				$u = (string) wp_get_attachment_image_url( $id, 'large' );
				if ( ! $u ) {
					$u = (string) wp_get_attachment_url( $id );
				}
			} elseif ( is_array( $item ) ) {
				$id = (int) ( isset( $item['ID'] ) ? $item['ID'] : ( isset( $item['id'] ) ? $item['id'] : 0 ) );
				// Try attachment lookup first, then fall back to the embedded URL.
				if ( $id ) {
					$u = (string) wp_get_attachment_image_url( $id, 'large' );
					if ( ! $u ) {
						$u = (string) wp_get_attachment_url( $id );
					}
				}
				if ( ! $u ) {
					$u = function_exists( 'ricoman_pf_imgurl' ) ? ricoman_pf_imgurl( $item ) : ( $item['url'] ?? ( $item['sizes']['large'] ?? '' ) );
				}
				// If URL resolved but ID still unknown, look it up (covers migrated URL-only items).
				if ( $u && ! $id ) {
					$found = attachment_url_to_postid( $u );
					if ( $found ) {
						$id = $found;
					}
				}
			}

			if ( ! $u || isset( $seen[ $u ] ) ) {
				continue;
			}
			$seen[ $u ] = true;
			$out[] = array( 'id' => $id, 'url' => $u );
		}
		return $out;
	};
	$gallery = $gallery_of( 'product_gallery_image' );
	$insitu  = $gallery_of( 'insitu_gallery' );

	// Current values for every editable field (across all groups).
	$fieldvals = array( 'title' => get_the_title( $pid ) );
	foreach ( $fgroups as $grp ) {
		foreach ( $grp as $f ) {
			$k = $f[0];
			if ( 'title' === $k ) {
				continue;
			}
			$raw = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, $k ) : get_post_meta( $pid, $k, true );
			if ( 'key_features' === $k ) {
				$fieldvals[ $k ] = function_exists( 'ricoman_pf_features_items' ) ? implode( "\n", ricoman_pf_features_items( $raw ) ) : ( is_string( $raw ) ? $raw : '' );
			} else {
				$fieldvals[ $k ] = is_scalar( $raw ) ? (string) $raw : '';
			}
		}
	}

	// Section meta for the left list (icon hints).
	$icons = array(
		'hero' => 'format-image', 'specs' => 'list-view', 'configure' => 'editor-table',
		'accessories' => 'screenoptions', 'related' => 'grid-view', 'faq' => 'editor-help', 'cta' => 'megaphone',
	);

	// Configure-table columns: all options + the product's current selection.
	$all_cols = function_exists( 'ricoman_variant_spec_label_order' ) ? ricoman_variant_spec_label_order() : array();
	$cur_cols = get_post_meta( $pid, '_ricoman_cols', true );
	if ( ! is_array( $cur_cols ) || ! $cur_cols ) {
		$g        = get_option( 'ricoman_spec_columns' );
		$cur_cols = ( is_array( $g ) && $g ) ? $g : ( function_exists( 'ricoman_variant_populated_cols' ) ? ricoman_variant_populated_cols( $pid ) : array() );
	}
	$cur_foff = function_exists( 'ricoman_variant_filters_off' ) ? ricoman_variant_filters_off( $pid ) : array();

	// Saved accessories for the picker (load titles from post data).
	$acc_data = array();
	$acc_raw  = get_post_meta( $pid, '_ricoman_accessories', true );
	if ( $acc_raw ) {
		$acc_ids = json_decode( $acc_raw, true );
		if ( is_array( $acc_ids ) ) {
			foreach ( array_filter( array_map( 'absint', $acc_ids ) ) as $acc_id ) {
				$acc_p = get_post( $acc_id );
				if ( $acc_p && 'product' === $acc_p->post_type ) {
					$acc_data[] = array( 'id' => (int) $acc_id, 'title' => $acc_p->post_title );
				}
			}
		}
	}

	$boot = array(
		'pid'      => $pid,
		'isTpl'    => $is_tpl,
		'tpl'      => $tpl,
		'ajax'     => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'ricoman_pe_' . $pid ),
		'tnonce'   => wp_create_nonce( 'ricoman_pe_thumb' ),
		'preview'  => $preview,
		'layout'   => $layout,
		'sections' => $labels,
		'patterns' => $patterns,
		'heroFields' => $fgroups['hero'],
		'specFields' => $fgroups['specs'],
		'faqFields'        => isset( $fgroups['faq'] ) ? $fgroups['faq'] : array(),
		'accessoriesData'  => $acc_data,
		'values'   => $fieldvals,
		'gallery'  => $gallery,
		'insitu'   => $insitu,
		'icons'    => $icons,
		'specCols' => array_values( $all_cols ),
		'cols'     => array_values( $cur_cols ),
		'filterOff' => array_values( (array) $cur_foff ),
		'configVisual' => function_exists( 'ricoman_pf_visual_config_enabled' ) ? ricoman_pf_visual_config_enabled( $pid ) : false,
		'optImgUrl' => admin_url( 'admin.php?page=ricoman-config-images&product=' . $pid ),
		'saveUrl'  => admin_url( 'admin-post.php' ),
		'saveNonce'=> wp_create_nonce( 'ricoman_save_product_page_' . $pid ),
		'exitUrl'  => admin_url( 'edit.php?post_type=product' ),
		'liveUrl'  => get_permalink( $pid ),
		'i18n'     => array(
			'details'  => __( 'Product details', 'ricoman' ),
			'addBlock' => __( 'Add section', 'ricoman' ),
		),
	);
	?>
	<style>
		.rmpe{--ink:#15171e;--muted:#697086;--faint:#9aa1b1;--line:#e7e9f0;--line-2:#eef0f5;--surface:#fff;--rail:#fbfbfd;--accent:#004899;--accent-2:#013d82;--tint:#eef3fc;--tint-2:#f4f8ff;
			--r-sm:8px;--r:11px;--r-lg:16px;--sh-sm:0 1px 2px rgba(16,24,40,.06);--sh:0 1px 3px rgba(16,24,40,.09);--sh-lift:0 12px 30px -10px rgba(16,24,40,.22);--sh-frame:0 36px 70px -28px rgba(16,24,40,.42)}
		#wpcontent{padding-left:0}#wpbody-content{padding-bottom:0}#wpfooter{display:none}
		.rmpe{position:fixed;top:32px;left:160px;right:0;bottom:0;display:flex;flex-direction:column;background:radial-gradient(120% 120% at 50% 0,#f6f8fc 0,#e8ebf2 100%);font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Roboto,sans-serif;color:var(--ink);z-index:9990;-webkit-font-smoothing:antialiased}
		.folded .rmpe{left:36px}
		@media(max-width:782px){.rmpe{left:0;top:46px}}
		.rmpe *{box-sizing:border-box}
		/* top bar */
		.rmpe-top{display:flex;align-items:center;gap:12px;height:58px;padding:0 18px;background:rgba(255,255,255,.86);backdrop-filter:saturate(1.4) blur(8px);border-bottom:1px solid var(--line);flex:0 0 auto;box-shadow:var(--sh-sm)}
		.rmpe-top .rmpe-title{font-weight:650;font-size:14px;letter-spacing:-.01em;color:var(--ink);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.rmpe-top .rmpe-title small{color:var(--faint);font-weight:500}
		.rmpe-dev{display:flex;background:#eef0f5;border-radius:10px;padding:3px;gap:2px}
		.rmpe-dev button{border:0;background:none;padding:6px 10px;border-radius:8px;cursor:pointer;color:var(--muted);display:flex;align-items:center;transition:.16s}
		.rmpe-dev button:hover{color:var(--ink)}
		.rmpe-dev button.on{background:#fff;color:var(--accent);box-shadow:var(--sh-sm)}
		.rmpe-dev .dashicons{font-size:18px;width:18px;height:18px}
		.rmpe-btn{border:0;border-radius:10px;padding:9px 17px;font-weight:650;font-size:13px;cursor:pointer;transition:.16s;font-family:inherit}
		.rmpe-btn-primary{background:linear-gradient(180deg,#0a57b0,var(--accent));color:#fff;box-shadow:0 1px 0 rgba(255,255,255,.18) inset,0 3px 8px rgba(0,72,153,.32)}
		.rmpe-btn-primary:hover{filter:brightness(1.06);transform:translateY(-1px)}
		.rmpe-btn-primary:active{transform:translateY(0)}
		.rmpe-btn-ghost{background:transparent;color:var(--muted)}.rmpe-btn-ghost:hover{color:var(--ink);background:#eef0f5}
		.rmpe-btn-on{background:#e6efff;color:var(--accent)}
		.rmpe-saved{color:#1a7f37;font-weight:650;opacity:0;transition:opacity .3s;display:flex;align-items:center;gap:5px}
		.rmpe-saved:before{content:"";width:7px;height:7px;border-radius:50%;background:#1a7f37}
		.rmpe-saved.show{opacity:1}
		/* body */
		.rmpe-body{flex:1;display:flex;min-height:0}
		.rmpe-rail{width:236px;flex:0 0 auto;background:var(--rail);border-right:1px solid var(--line);display:flex;flex-direction:column;min-height:0}
		.rmpe-rail.right{border-right:0;border-left:1px solid var(--line);width:340px;background:var(--surface)}
		.rmpe.hide-left .rmpe-rail.left{display:none}
		.rmpe.hide-right .rmpe-rail.right{display:none}
		.rmpe-rail h3{font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:var(--faint);font-weight:700;margin:0;padding:18px 20px 6px}
		.rmpe-list{list-style:none;margin:0;padding:4px 14px 14px;overflow-y:auto;flex:1}
		.rmpe-card{display:flex;align-items:center;gap:8px;padding:8px 9px;border:1px solid var(--line);border-radius:var(--r);margin:7px 0;background:var(--surface);cursor:pointer;box-shadow:var(--sh-sm);transition:transform .14s,box-shadow .14s,border-color .14s}
		.rmpe-card:hover{border-color:#d4d8e4;box-shadow:var(--sh);transform:translateY(-1px)}
		.rmpe-card.sel{border-color:var(--accent);box-shadow:0 0 0 1px var(--accent),var(--sh-lift)}
		.rmpe-card.off{opacity:.52}
		.rmpe-card .ic{width:26px;height:26px;border-radius:7px;background:var(--tint);display:flex;align-items:center;justify-content:center;flex:0 0 auto}
		.rmpe-card .ic .dashicons{font-size:17px;width:17px;height:17px;color:var(--accent)}
		.rmpe-card.pat .ic{background:#eaf3ec}.rmpe-card.pat .ic .dashicons{color:#2f7d46}
		.rmpe-card .drag{color:#c4c8d4;cursor:grab;display:flex;opacity:0;transition:.14s;margin-left:-4px}
		.rmpe-card:hover .drag,.rmpe-card.sel .drag{opacity:1}
		.rmpe-card .nm{flex:1;font-weight:600;color:var(--ink);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.rmpe-card .eye,.rmpe-card .del{border:0;background:none;cursor:pointer;color:var(--faint);padding:4px;border-radius:7px;display:flex;transition:.14s}
		.rmpe-card .eye:hover{background:var(--tint);color:var(--accent)}
		.rmpe-card .del:hover{background:#fdeaea;color:#c0392b}
		.rmpe-card.drag-over{border-color:var(--accent);border-style:dashed}
		.rmpe-card.dragging{opacity:.35}
		.rmpe-add{margin:4px 14px 18px;display:flex}
		.rmpe-addbtn{width:100%;padding:12px;border-radius:var(--r);border:1.5px dashed #b3c2dd;background:var(--tint-2);color:var(--accent);font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:.16s;font-family:inherit;font-size:13px}
		.rmpe-addbtn:hover{background:var(--tint);border-color:var(--accent);box-shadow:var(--sh-sm)}
		.rmpe-addbtn .dashicons{font-size:16px;width:16px;height:16px}
		/* preview stage */
		.rmpe-stage{flex:1;min-width:0;display:flex;align-items:flex-start;justify-content:center;overflow:auto;padding:26px}
		.rmpe-frame{background:#fff;border-radius:var(--r-lg);box-shadow:var(--sh-frame);overflow:hidden;width:100%;max-width:100%;height:calc(100vh - 32px - 58px - 52px);transition:max-width .3s cubic-bezier(.22,1,.36,1);outline:1px solid rgba(16,24,40,.06)}
		.rmpe-frame.tablet{max-width:834px}.rmpe-frame.mobile{max-width:392px;border-radius:30px;outline:6px solid #1c1f27;box-shadow:0 26px 60px -18px rgba(16,24,40,.5)}
		.rmpe-frame iframe{width:100%;height:100%;border:0;display:block}
		.rmpe-load{position:absolute;top:78px;left:50%;transform:translateX(-50%) translateY(-4px);background:rgba(21,23,30,.92);color:#fff;padding:7px 16px;border-radius:30px;font-size:12px;font-weight:600;opacity:0;transition:.22s;pointer-events:none;box-shadow:var(--sh-lift)}
		.rmpe-load.show{opacity:1;transform:translateX(-50%) translateY(0)}
		/* settings */
		.rmpe-set{padding:20px;overflow-y:auto;flex:1}
		.rmpe-set .ttl{font-weight:750;font-size:16px;letter-spacing:-.01em;color:var(--ink);margin:0 0 4px}
		.rmpe-set .hint{color:var(--muted);margin:0 0 18px;font-size:12px;line-height:1.45}
		.rmpe-set label{display:block;font-weight:650;color:var(--ink);margin:0 0 15px;font-size:12px}
		.rmpe-set label span{display:block;margin-bottom:6px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;font-size:11px}
		.rmpe-set input[type=text],.rmpe-set textarea{width:100%;border:1px solid #d8dbe4;border-radius:var(--r-sm);padding:10px 12px;font-size:13px;font-family:inherit;background:#fff;transition:.14s;color:var(--ink)}
		.rmpe-set input[type=text]:focus,.rmpe-set textarea:focus{border-color:var(--accent);outline:none;box-shadow:0 0 0 3px rgba(0,72,153,.14)}
		.rmpe-set .row{display:flex;align-items:center;justify-content:space-between;padding:13px 0;border-top:1px solid var(--line-2);font-weight:600}
		.rmpe-set .sw{position:relative;width:42px;height:24px;flex:0 0 auto}
		.rmpe-set .sw input{opacity:0;width:0;height:0}
		.rmpe-set .sl{position:absolute;inset:0;background:#cfd3df;border-radius:24px;transition:.22s;cursor:pointer}
		.rmpe-set .sl:before{content:"";position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.22s;box-shadow:0 1px 3px rgba(0,0,0,.25)}
		.rmpe-set .sw input:checked+.sl{background:var(--accent)}
		.rmpe-set .sw input:checked+.sl:before{transform:translateX(18px)}
		.rmpe-set .danger{color:#c0392b;border:1px solid #f1cccc;background:#fff;border-radius:var(--r-sm);padding:10px;width:100%;cursor:pointer;font-weight:650;margin-top:12px;transition:.14s;font-family:inherit}
		.rmpe-set .danger:hover{background:#fdf1f1;border-color:#e8a9a9}
		.rmpe-empty{color:var(--faint);text-align:center;padding:54px 18px;font-size:13px}
		.rmpe-cfgmode{display:grid;gap:6px;margin:4px 0 10px;border:1px solid var(--line);border-radius:10px;padding:12px}
		.rmpe-cfgmode label{display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:500;color:var(--ink);cursor:pointer;margin:0}
		.rmpe-cols{display:grid;gap:7px;margin:4px 0 14px;max-height:46vh;overflow:auto;border:1px solid var(--line);border-radius:10px;padding:12px}
		.rmpe-colrow{display:flex;align-items:center;justify-content:space-between;gap:9px;font-size:12.5px;font-weight:500;color:var(--ink);margin:0}
		.rmpe-colcb{display:flex;align-items:center;gap:9px;cursor:pointer;flex:1 1 auto}
		.rmpe-filt{flex:0 0 auto;font-size:11px;border:1px solid var(--line);border-radius:6px;padding:2px 8px;background:#fff;color:var(--accent,#2563eb);cursor:pointer;white-space:nowrap}
		.rmpe-filt.off{color:var(--faint);text-decoration:line-through;opacity:.7}
		.rmpe-filt:disabled{visibility:hidden}
		.rmpe-colrow input{margin:0}
		.rmpe-colglobal{display:flex;align-items:center;gap:9px;font-size:12px;font-weight:600;color:var(--muted);margin:0 0 14px;cursor:pointer}
		.rmpe-gallery{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 16px}
		.rmpe-gthumb{position:relative;width:46px;height:46px;border-radius:7px;background:#eef0f5 center/cover no-repeat;border:1px solid var(--line);cursor:pointer}
		.rmpe-gthumb:hover::after{content:"\00d7";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(20,22,28,.55);color:#fff;font-size:18px;border-radius:7px}
		.rmpe-gthumb.rmpe-gdrag{opacity:.4}
		.rmpe-gbtn{border:1px dashed #b3c2dd;background:var(--tint-2);color:var(--accent);border-radius:7px;padding:0 14px;height:46px;font-weight:700;font-size:12px;cursor:pointer}
		.rmpe-gbtn:hover{background:var(--tint)}
		/* accessories picker */
		.rmpe-accsrch{position:relative;margin:0 0 10px}
		.rmpe-accsrch input{width:100%;border:1px solid #d8dbe4;border-radius:var(--r-sm);padding:10px 12px;font-size:13px;font-family:inherit;background:#fff;transition:.14s;color:var(--ink)}
		.rmpe-accsrch input:focus{border-color:var(--accent);outline:none;box-shadow:0 0 0 3px rgba(0,72,153,.14)}
		.rmpe-accdd{position:absolute;top:calc(100% + 2px);left:0;right:0;background:#fff;border:1px solid #d8dbe4;border-radius:var(--r-sm);box-shadow:var(--sh-lift);z-index:20;max-height:190px;overflow-y:auto;display:none}
		.rmpe-accsrch.open .rmpe-accdd{display:block}
		.rmpe-accopt{padding:9px 12px;cursor:pointer;font-size:13px;color:var(--ink);transition:.12s}
		.rmpe-accopt:hover,.rmpe-accopt:focus{background:var(--tint)}
		.rmpe-accnone{padding:9px 12px;font-size:13px;color:var(--faint);font-style:italic}
		.rmpe-acclist{display:flex;flex-direction:column;gap:5px;margin:0 0 12px}
		.rmpe-accitem{display:flex;align-items:center;gap:8px;background:var(--tint-2);border:1px solid var(--line);border-radius:7px;padding:7px 10px;font-size:12.5px;font-weight:500;color:var(--ink)}
		.rmpe-accitem span{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
		.rmpe-accitem button{border:0;background:none;cursor:pointer;color:var(--faint);padding:2px 4px;border-radius:4px;line-height:1;font-size:16px;transition:.12s;font-family:inherit}
		.rmpe-accitem button:hover{color:#c0392b;background:#fdeaea}
		/* visual picker modal */
		.rmpe-modal{position:fixed;inset:0;background:rgba(13,15,22,.5);backdrop-filter:blur(4px);z-index:100000;display:flex;align-items:center;justify-content:center;padding:3vh 3vw;animation:rmpe-fade .18s ease}
		@keyframes rmpe-fade{from{opacity:0}to{opacity:1}}
		.rmpe-modal[hidden]{display:none}
		.rmpe-modal-box{background:#fff;border-radius:18px;width:1160px;max-width:100%;height:88vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 40px 90px -20px rgba(0,0,0,.55);animation:rmpe-pop .22s cubic-bezier(.22,1,.36,1)}
		@keyframes rmpe-pop{from{transform:translateY(10px) scale(.99);opacity:.6}to{transform:none;opacity:1}}
		.rmpe-modal-head{display:flex;align-items:center;gap:14px;padding:18px 22px;border-bottom:1px solid var(--line)}
		.rmpe-modal-head strong{font-size:17px;letter-spacing:-.01em}
		.rmpe-modal-head input{flex:1;border:1px solid #d8dbe4;border-radius:10px;padding:10px 14px;font-size:14px;font-family:inherit}
		.rmpe-modal-head input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,72,153,.14)}
		.rmpe-modal-x{border:0;background:#f1f2f6;width:34px;height:34px;border-radius:9px;font-size:22px;line-height:1;cursor:pointer;color:var(--muted);transition:.14s}
		.rmpe-modal-x:hover{background:#e6e8ef;color:var(--ink)}
		.rmpe-modal-cats{display:flex;gap:8px;flex-wrap:wrap;padding:14px 22px;border-bottom:1px solid var(--line-2);flex:0 0 auto}
		.rmpe-chip{border:1px solid #dfe2ea;background:#fff;border-radius:999px;padding:7px 15px;font-size:12px;font-weight:650;color:var(--muted);cursor:pointer;white-space:nowrap;transition:.14s}
		.rmpe-chip:hover{border-color:#bcc3d4;color:var(--ink)}
		.rmpe-chip.on{background:var(--accent);border-color:var(--accent);color:#fff;box-shadow:0 2px 6px rgba(0,72,153,.3)}
		.rmpe-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;padding:22px;overflow-y:auto;align-content:start;background:linear-gradient(180deg,#fafbfd,#fff)}
		.rmpe-tile{border:1px solid var(--line);border-radius:13px;overflow:hidden;cursor:pointer;background:#fff;box-shadow:var(--sh-sm);transition:border-color .15s,box-shadow .15s,transform .15s}
		.rmpe-tile:hover{border-color:var(--accent);box-shadow:var(--sh-lift);transform:translateY(-3px)}
		.rmpe-thumb{position:relative;height:210px;background:#f4f5f8;overflow:hidden;border-bottom:1px solid var(--line-2)}
		.rmpe-thumb iframe{position:absolute;top:0;left:0;width:1280px;height:900px;border:0;transform-origin:0 0;pointer-events:none;opacity:0;transition:opacity .35s ease}
		.rmpe-thumb.ready iframe{opacity:1}
		.rmpe-thumb.loading::after{content:"";position:absolute;inset:0;background:linear-gradient(100deg,#f4f5f8 30%,#eaedf3 50%,#f4f5f8 70%);background-size:200% 100%;animation:rmpe-shimmer 1.1s linear infinite}
		@keyframes rmpe-shimmer{from{background-position:200% 0}to{background-position:-200% 0}}
		.rmpe-thumb.sec{display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#eef3fc,#e3ecfa)}
		.rmpe-thumb.sec .dashicons{font-size:38px;width:38px;height:38px;color:var(--accent);opacity:.8}
		.rmpe-tile .lbl{padding:11px 13px;font-size:12.5px;font-weight:650;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.rmpe-tile .cat{display:block;font-size:10px;color:var(--faint);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px}
	</style>

	<div class="rmpe">
		<div class="rmpe-top">
			<button class="rmpe-btn rmpe-btn-ghost" id="rmpe-exit">&larr; <?php esc_html_e( 'Exit', 'ricoman' ); ?></button>
			<div class="rmpe-title"><?php echo esc_html( $headTitle ); ?> <small>· <?php echo $is_tpl ? esc_html__( 'Template', 'ricoman' ) : esc_html__( 'Product page', 'ricoman' ); ?></small></div>
			<span class="rmpe-saved" id="rmpe-saved"><?php esc_html_e( 'Saved', 'ricoman' ); ?></span>
			<div class="rmpe-dev" id="rmpe-dev">
				<button data-d="desktop" class="on" title="Desktop"><span class="dashicons dashicons-desktop"></span></button>
				<button data-d="tablet" title="Tablet"><span class="dashicons dashicons-tablet"></span></button>
				<button data-d="mobile" title="Mobile"><span class="dashicons dashicons-smartphone"></span></button>
			</div>
			<button class="rmpe-btn rmpe-btn-ghost" id="rmpe-tleft" title="<?php esc_attr_e( 'Hide / show the sections panel', 'ricoman' ); ?>"><span class="dashicons dashicons-align-pull-left"></span></button>
			<button class="rmpe-btn rmpe-btn-ghost" id="rmpe-tright" title="<?php esc_attr_e( 'Hide / show the settings panel', 'ricoman' ); ?>"><span class="dashicons dashicons-align-pull-right"></span></button>
			<?php if ( ! $is_tpl ) : ?>
				<a class="rmpe-btn rmpe-btn-ghost" href="<?php echo esc_url( admin_url( 'post.php?post=' . $pid . '&action=edit&classic=1' ) ); ?>"><?php esc_html_e( 'All fields', 'ricoman' ); ?></a>
				<button class="rmpe-btn rmpe-btn-ghost" id="rmpe-reset"><?php esc_html_e( 'Reset', 'ricoman' ); ?></button>
			<?php endif; ?>
			<a class="rmpe-btn rmpe-btn-ghost" id="rmpe-view" target="_blank" href="<?php echo esc_url( get_permalink( $pid ) ); ?>"><?php esc_html_e( 'View live', 'ricoman' ); ?></a>
			<button class="rmpe-btn rmpe-btn-primary" id="rmpe-save"><?php esc_html_e( 'Save', 'ricoman' ); ?></button>
		</div>

		<div class="rmpe-body">
			<div class="rmpe-rail left">
				<h3><?php esc_html_e( 'Sections', 'ricoman' ); ?></h3>
				<ul class="rmpe-list" id="rmpe-list"></ul>
				<div class="rmpe-add">
					<button type="button" class="rmpe-addbtn" id="rmpe-add"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add section or pattern', 'ricoman' ); ?></button>
				</div>
			</div>

			<div class="rmpe-stage">
				<div class="rmpe-frame" id="rmpe-frame">
					<iframe id="rmpe-iframe" src="<?php echo esc_url( $preview ); ?>" title="<?php esc_attr_e( 'Live preview', 'ricoman' ); ?>"></iframe>
				</div>
				<div class="rmpe-load" id="rmpe-load"><?php esc_html_e( 'Updating…', 'ricoman' ); ?></div>
			</div>

			<div class="rmpe-rail right">
				<div class="rmpe-set" id="rmpe-set"></div>
			</div>
		</div>

		<div class="rmpe-modal" id="rmpe-modal" hidden>
			<div class="rmpe-modal-box">
				<div class="rmpe-modal-head">
					<strong><?php esc_html_e( 'Add to page', 'ricoman' ); ?></strong>
					<input type="search" id="rmpe-search" placeholder="<?php esc_attr_e( 'Search…', 'ricoman' ); ?>">
					<button type="button" class="rmpe-modal-x" id="rmpe-modal-x" aria-label="Close">&times;</button>
				</div>
				<div class="rmpe-modal-cats" id="rmpe-cats"></div>
				<div class="rmpe-grid" id="rmpe-grid"></div>
			</div>
		</div>

		<form id="rmpe-saveform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none">
			<input type="hidden" name="action" value="ricoman_save_product_page">
			<input type="hidden" name="product" value="<?php echo esc_attr( $pid ); ?>">
			<input type="hidden" name="template" value="<?php echo esc_attr( $tpl ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'ricoman_save_product_page_' . $pid ) ); ?>">
			<input type="hidden" name="layout_json" id="rmpe-save-layout">
			<input type="hidden" name="fields_json" id="rmpe-save-fields">
			<input type="hidden" name="cols_json" id="rmpe-save-cols">
			<input type="hidden" name="filteroff_json" id="rmpe-save-filteroff">
			<input type="hidden" name="config_visual_json" id="rmpe-save-configvisual">
			<input type="hidden" name="cols_global" id="rmpe-save-cols-global" value="0">
			<input type="hidden" name="gallery_json" id="rmpe-save-gallery">
			<input type="hidden" name="insitu_json" id="rmpe-save-insitu">
			<input type="hidden" name="accessories_json" id="rmpe-save-accessories">
		</form>

		<form id="rmpe-resetform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none">
			<input type="hidden" name="action" value="ricoman_pe_reset">
			<input type="hidden" name="product" value="<?php echo esc_attr( $pid ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'ricoman_pe_reset_' . $pid ) ); ?>">
		</form>
	</div>

	<script>
	( function () {
		var B = <?php echo wp_json_encode( $boot ); ?>;
		var state = { layout: B.layout.slice(), fields: Object.assign( {}, B.values ), cols: ( B.cols || [] ).slice(), filterOff: ( B.filterOff || [] ).slice(), configVisual: !! B.configVisual, colsGlobal: false, gallery: ( B.gallery || [] ).slice(), insitu: ( B.insitu || [] ).slice(), accessories: ( B.accessoriesData || [] ).slice(), sel: 0, device: 'desktop' };
		var $ = function ( id ) { return document.getElementById( id ); };
		var iframe = $( 'rmpe-iframe' ), load = $( 'rmpe-load' );

		/* ---- live draft -> preview ---- */
		var draftTimer, reloadTimer;
		function pushDraft( reload ) {
			var fd = new FormData();
			fd.append( 'action', 'ricoman_pe_draft' );
			fd.append( 'product', B.pid );
			fd.append( 'nonce', B.nonce );
			fd.append( 'layout', JSON.stringify( state.layout ) );
			fd.append( 'fields', JSON.stringify( state.fields ) );
			fd.append( 'cols', JSON.stringify( state.cols ) );
			fd.append( 'filteroff', JSON.stringify( state.filterOff ) );
			fd.append( 'configvisual', state.configVisual ? '1' : '0' );
			fd.append( 'gallery', JSON.stringify( state.gallery.map( function ( i ) { return i.id; } ) ) );
			fd.append( 'insitu', JSON.stringify( state.insitu.map( function ( i ) { return i.id; } ) ) );
			fd.append( 'accessories', JSON.stringify( state.accessories.map( function ( i ) { return i.id; } ) ) );
			clearTimeout( draftTimer );
			draftTimer = setTimeout( function () {
				fetch( B.ajax, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function () {
					if ( reload !== false ) { refreshPreview(); }
				} );
			}, 250 );
		}
		function refreshPreview() {
			load.classList.add( 'show' );
			clearTimeout( reloadTimer );
			reloadTimer = setTimeout( function () {
				try { iframe.contentWindow.location.reload(); }
				catch ( e ) { iframe.src = B.preview + '&t=' + Date.now(); }
			}, 120 );
		}
		iframe.addEventListener( 'load', function () { load.classList.remove( 'show' ); } );

		/* ---- left list ---- */
		var dragIndex = null;
		function sectionName( it ) {
			return it.type === 'pattern' ? ( B.patterns[ it.name ] || it.name ) : ( B.sections[ it.key ] || it.key );
		}
		function renderList() {
			var ul = $( 'rmpe-list' ); ul.innerHTML = '';
			state.layout.forEach( function ( it, i ) {
				var li = document.createElement( 'li' );
				li.className = 'rmpe-card' + ( i === state.sel ? ' sel' : '' ) + ( it.type === 'pattern' ? ' pat' : '' ) + ( ( it.type === 'section' && it.on === false ) ? ' off' : '' );
				li.setAttribute( 'draggable', 'true' );
				li.dataset.i = i;
				var icon = it.type === 'pattern' ? 'screenoptions' : ( B.icons[ it.key ] || 'block-default' );
				var h = '<span class="drag dashicons dashicons-menu"></span>';
				h += '<span class="ic"><span class="dashicons dashicons-' + icon + '"></span></span>';
				h += '<span class="nm">' + sectionName( it ) + '</span>';
				if ( it.type === 'section' ) {
					h += '<button class="eye" data-act="toggle" title="Show / hide"><span class="dashicons dashicons-' + ( it.on === false ? 'hidden' : 'visibility' ) + '"></span></button>';
				} else {
					h += '<button class="del" data-act="remove" title="Remove"><span class="dashicons dashicons-trash"></span></button>';
				}
				li.innerHTML = h;
				ul.appendChild( li );
			} );
		}
		$( 'rmpe-list' ).addEventListener( 'click', function ( e ) {
			var card = e.target.closest( '.rmpe-card' ); if ( ! card ) { return; }
			var i = +card.dataset.i;
			var btn = e.target.closest( 'button' );
			if ( btn ) {
				var act = btn.dataset.act;
				if ( act === 'toggle' ) { state.layout[ i ].on = ( state.layout[ i ].on === false ); }
				else if ( act === 'remove' ) { state.layout.splice( i, 1 ); if ( state.sel >= state.layout.length ) { state.sel = state.layout.length - 1; } }
				renderList(); renderSettings(); pushDraft();
				return;
			}
			state.sel = i; renderList(); renderSettings();
		} );
		/* drag + drop reorder */
		$( 'rmpe-list' ).addEventListener( 'dragstart', function ( e ) {
			var c = e.target.closest( '.rmpe-card' ); if ( ! c ) { return; }
			dragIndex = +c.dataset.i; c.classList.add( 'dragging' );
		} );
		$( 'rmpe-list' ).addEventListener( 'dragend', function ( e ) {
			var c = e.target.closest( '.rmpe-card' ); if ( c ) { c.classList.remove( 'dragging' ); }
			document.querySelectorAll( '.rmpe-card.drag-over' ).forEach( function ( x ) { x.classList.remove( 'drag-over' ); } );
		} );
		$( 'rmpe-list' ).addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			var c = e.target.closest( '.rmpe-card' );
			document.querySelectorAll( '.rmpe-card.drag-over' ).forEach( function ( x ) { x.classList.remove( 'drag-over' ); } );
			if ( c ) { c.classList.add( 'drag-over' ); }
		} );
		$( 'rmpe-list' ).addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			var c = e.target.closest( '.rmpe-card' ); if ( ! c || dragIndex === null ) { return; }
			var to = +c.dataset.i;
			var moved = state.layout.splice( dragIndex, 1 )[ 0 ];
			state.layout.splice( to, 0, moved );
			state.sel = to; dragIndex = null;
			renderList(); renderSettings(); pushDraft();
		} );

		/* ---- visual add picker ---- */
		function renderAdd() {} // grid is built when the modal opens.
		var modal = $( 'rmpe-modal' ), grid = $( 'rmpe-grid' ), cats = $( 'rmpe-cats' ), search = $( 'rmpe-search' ), activeCat = 'All';
		function patCat( title ) { var i = title.indexOf( '·' ); return i > -1 ? title.slice( 0, i ).trim() : 'Other'; }
		function buildCats() {
			var set = { 'All': 1, 'Sections': 1 };
			Object.keys( B.patterns ).forEach( function ( n ) { set[ patCat( B.patterns[ n ] ) ] = 1; } );
			cats.innerHTML = '';
			Object.keys( set ).forEach( function ( c ) {
				var b = document.createElement( 'button' );
				b.className = 'rmpe-chip' + ( c === activeCat ? ' on' : '' ); b.textContent = c; b.dataset.c = c;
				cats.appendChild( b );
			} );
		}
		function thumbUrl( kind, name ) {
			return B.ajax + '?action=ricoman_pe_thumb&nonce=' + B.tnonce + '&product=' + B.pid + '&kind=' + kind + '&name=' + encodeURIComponent( name );
		}
		function tile( kind, name, label, cat, icon ) {
			var t = document.createElement( 'div' ); t.className = 'rmpe-tile'; t.dataset.kind = kind; t.dataset.name = name;
			var thumb = ( kind === 'section' )
				? '<div class="rmpe-thumb sec"><span class="dashicons dashicons-' + ( icon || 'block-default' ) + '"></span></div>'
				: '<div class="rmpe-thumb loading"><iframe scrolling="no" data-src="' + thumbUrl( kind, name ) + '" onload="this.style.transform=\'scale(\'+(this.parentNode.clientWidth/1280)+\')\';this.parentNode.classList.remove(\'loading\');this.parentNode.classList.add(\'ready\');"></iframe></div>';
			t.innerHTML = thumb + '<div class="lbl"><span class="cat">' + cat + '</span>' + label + '</div>';
			return t;
		}
		function buildGrid() {
			grid.innerHTML = '';
			var q = ( search.value || '' ).toLowerCase();
			var used = state.layout.filter( function ( i ) { return i.type === 'section'; } ).map( function ( i ) { return i.key; } );
			if ( activeCat === 'All' || activeCat === 'Sections' ) {
				Object.keys( B.sections ).forEach( function ( k ) {
					if ( used.indexOf( k ) > -1 ) { return; }
					var label = B.sections[ k ]; if ( q && label.toLowerCase().indexOf( q ) < 0 ) { return; }
					grid.appendChild( tile( 'section', k, label, 'Section', B.icons[ k ] ) );
				} );
			}
			if ( activeCat !== 'Sections' ) {
				Object.keys( B.patterns ).forEach( function ( n ) {
					var label = B.patterns[ n ], cat = patCat( label );
					if ( activeCat !== 'All' && cat !== activeCat ) { return; }
					if ( q && label.toLowerCase().indexOf( q ) < 0 ) { return; }
					grid.appendChild( tile( 'pattern', n, label.replace( /^[^·]*·\s*/, '' ), cat ) );
				} );
			}
			// Only render a preview once its tile scrolls into view — with ~150
			// patterns, firing every iframe up front rendered the whole catalogue
			// server-side at once (slow, lots of blank tiles). IntersectionObserver
			// loads just the visible dozen, the rest as you scroll.
			if ( grid._io ) { grid._io.disconnect(); }
			if ( 'IntersectionObserver' in window ) {
				grid._io = new IntersectionObserver( function ( entries ) {
					entries.forEach( function ( en ) {
						if ( ! en.isIntersecting ) { return; }
						var f = en.target;
						if ( f.dataset.src ) { f.src = f.dataset.src; f.removeAttribute( 'data-src' ); }
						grid._io.unobserve( f );
					} );
				}, { rootMargin: '400px 0px' } ); // viewport root — robust to whichever element actually scrolls
				grid.querySelectorAll( 'iframe[data-src]' ).forEach( function ( f ) { grid._io.observe( f ); } );
			} else {
				// Fallback for very old browsers: staggered load.
				grid.querySelectorAll( 'iframe[data-src]' ).forEach( function ( f, i ) {
					setTimeout( function () { if ( f.dataset.src ) { f.src = f.dataset.src; f.removeAttribute( 'data-src' ); } }, i * 70 );
				} );
			}
		}
		// Show the modal BEFORE building the grid so the scroll container has real
		// dimensions when the IntersectionObserver attaches — otherwise it measures
		// a 0-height grid and loads every preview at once.
		function openModal() { modal.hidden = false; buildCats(); buildGrid(); }
		function closeModal() { modal.hidden = true; }
		$( 'rmpe-add' ).addEventListener( 'click', openModal );
		$( 'rmpe-modal-x' ).addEventListener( 'click', closeModal );
		modal.addEventListener( 'click', function ( e ) { if ( e.target === modal ) { closeModal(); } } );
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' && ! modal.hidden ) { closeModal(); } } );
		cats.addEventListener( 'click', function ( e ) { var b = e.target.closest( '.rmpe-chip' ); if ( ! b ) { return; } activeCat = b.dataset.c; buildCats(); buildGrid(); } );
		search.addEventListener( 'input', buildGrid );
		grid.addEventListener( 'click', function ( e ) {
			var t = e.target.closest( '.rmpe-tile' ); if ( ! t ) { return; }
			var at = ( state.sel >= 0 ? state.sel + 1 : state.layout.length );
			if ( t.dataset.kind === 'section' ) { state.layout.splice( at, 0, { type: 'section', key: t.dataset.name, on: true } ); }
			else { state.layout.splice( at, 0, { type: 'pattern', name: t.dataset.name } ); }
			state.sel = at; closeModal(); renderList(); renderSettings(); pushDraft();
		} );

		/* ---- accessories search ---- */
		function initAccSearch() {
			var inp = document.getElementById( 'rmpe-acc-q' );
			if ( ! inp ) { return; }
			var wrap = inp.parentNode;
			var dd = document.createElement( 'div' ); dd.className = 'rmpe-accdd'; wrap.appendChild( dd );
			var timer = null;
			function currentIds() { return state.accessories.map( function ( a ) { return a.id; } ); }
			function doSearch() {
				var q = inp.value.trim();
				if ( q.length < 1 ) { wrap.classList.remove( 'open' ); return; }
				clearTimeout( timer );
				timer = setTimeout( function () {
					var url = B.ajax + '?action=ricoman_pe_acc_search&nonce=' + encodeURIComponent( B.tnonce ) + '&exclude=' + B.pid + '&q=' + encodeURIComponent( q );
					fetch( url, { credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( j ) {
						dd.innerHTML = '';
						var sel = currentIds();
						var items = ( j && j.success && j.data ) ? j.data.filter( function ( p ) { return sel.indexOf( p.id ) < 0; } ) : [];
						if ( ! items.length ) {
							dd.innerHTML = '<div class="rmpe-accnone">No products found.</div>';
						} else {
							items.forEach( function ( p ) {
								var opt = document.createElement( 'div' ); opt.className = 'rmpe-accopt'; opt.textContent = p.title;
								opt.dataset.accid = p.id; opt.dataset.acctitle = p.title;
								dd.appendChild( opt );
							} );
						}
						wrap.classList.add( 'open' );
					} );
				}, 250 );
			}
			inp.addEventListener( 'input', doSearch );
			dd.addEventListener( 'click', function ( e ) {
				var opt = e.target.closest( '.rmpe-accopt' ); if ( ! opt || ! opt.dataset.accid ) { return; }
				var id = parseInt( opt.dataset.accid, 10 ), title = opt.dataset.acctitle || '';
				if ( currentIds().indexOf( id ) < 0 ) {
					state.accessories.push( { id: id, title: title } );
					pushDraft();
					renderSettings();
				}
			} );
			document.addEventListener( 'click', function closeAcc( e ) {
				if ( ! wrap.contains( e.target ) ) { wrap.classList.remove( 'open' ); document.removeEventListener( 'click', closeAcc ); }
			} );
		}

		/* ---- right settings ---- */
		function teardownSpecEditor() {
			if ( window.wp && wp.editor && document.getElementById( 'rmpe-spec' ) ) {
				try { wp.editor.remove( 'rmpe-spec' ); } catch ( e ) {}
			}
		}
		function initSpecEditor() {
			if ( ! window.wp || ! wp.editor || ! document.getElementById( 'rmpe-spec' ) ) { return; }
			wp.editor.initialize( 'rmpe-spec', {
				tinymce: { toolbar1: 'bold italic bullist numlist link removeformat', menubar: false, statusbar: false, height: 300 },
				quicktags: false,
				mediaButtons: false
			} );
			setTimeout( function () {
				if ( window.tinymce ) {
					var ed = tinymce.get( 'rmpe-spec' );
					if ( ed ) { ed.on( 'input change keyup undo redo SetContent', function () { state.fields.specification = ed.getContent(); pushDraft(); } ); }
				}
			}, 300 );
		}
		function renderSettings() {
			teardownSpecEditor();
			teardownPatEditor();
			var box = $( 'rmpe-set' );
			var it = state.layout[ state.sel ];
			if ( ! it ) { box.innerHTML = '<div class="rmpe-empty">Select a section to edit it.</div>'; return; }
			var html = '';
			if ( it.type === 'section' && it.key === 'hero' && ! B.isTpl ) {
				html += '<p class="ttl">' + B.i18n.details + '</p><p class="hint">Title, copy, buttons and key features shown in the hero.</p>';
				( B.heroFields || [] ).forEach( function ( f ) { html += field( f[0], f[1], f[2] ); } );
				html += galleryControl( 'Studio gallery', 'gallery' );
				html += galleryControl( 'In-situ photos', 'insitu' );
				html += visRow( it );
			} else if ( it.type === 'section' && it.key === 'specs' && ! B.isTpl ) {
				html += '<p class="ttl">Specification &amp; details</p><p class="hint">Edit the specification shown in this section.</p>';
				( B.specFields || [] ).forEach( function ( f ) { html += field( f[0], f[1], f[2] ); } );
				html += visRow( it );
			} else if ( it.type === 'section' && it.key === 'faq' && ! B.isTpl ) {
				html += '<p class="ttl">Product FAQs</p><p class="hint">One Q: / A: pair per question, e.g.<br><code>Q: Is this dimmable?<br>A: Yes — DALI and mains dimming.</code><br>Renders as a FAQ section and adds FAQ schema for SEO.</p>';
				( B.faqFields || [] ).forEach( function ( f ) { html += field( f[0], f[1], f[2] ); } );
				html += visRow( it );
			} else if ( it.type === 'section' && it.key === 'configure' ) {
				html += '<p class="ttl">Configure &amp; order codes</p><p class="hint">Choose how customers pick a variant, then which spec columns / filters show.</p>';
				html += '<div class="rmpe-cfgmode"><label><input type="radio" name="rmpe-cfgmode" data-act="cfgmode" value="0"' + ( state.configVisual ? '' : ' checked' ) + '> Table with filters</label>'
					+ '<label><input type="radio" name="rmpe-cfgmode" data-act="cfgmode" value="1"' + ( state.configVisual ? ' checked' : '' ) + '> Visual configurator (tap-through tiles)</label></div>';
				html += '<p class="hint">' + ( state.configVisual ? 'In visual mode the ticked columns below become the tap-through steps — untick one to remove that step. (The eye/filter toggles only apply to the table.)' : 'Tick a column to show it; the eye toggles its filter drop-down (column data still shows). The full spec shows when a row is opened.' ) + '</p>';
				html += '<div class="rmpe-cols">';
				( B.specCols || [] ).forEach( function ( c ) {
					var esc = c.replace( /"/g, '&quot;' );
					var on  = state.cols.indexOf( c ) > -1;
					var foff = state.filterOff.indexOf( c ) > -1;
					html += '<div class="rmpe-colrow">'
						+ '<label class="rmpe-colcb"><input type="checkbox" data-col="' + esc + '"' + ( on ? ' checked' : '' ) + '> ' + c + '</label>'
						+ '<button type="button" class="rmpe-filt' + ( foff ? ' off' : '' ) + '" data-filt="' + esc + '"' + ( on ? '' : ' disabled' )
						+ ' title="' + ( foff ? 'Filter hidden — click to show' : 'Filter shown — click to hide' ) + '" aria-label="Toggle filter drop-down">' + ( foff ? '🚫 filter' : '🔽 filter' ) + '</button>'
						+ '</div>';
				} );
				html += '</div>';
				html += '<label class="rmpe-colglobal"><input type="checkbox" data-act="colsglobal"' + ( state.colsGlobal ? ' checked' : '' ) + '> Apply to all products (global default)</label>';
				if ( state.configVisual && B.optImgUrl ) {
					html += '<p class="hint" style="margin-top:14px">🖼️ Want a custom picture on each tile (finishes, sizes…)? '
						+ '<a href="' + B.optImgUrl + '" target="_blank" rel="noopener"><strong>Edit option tile images ↗</strong></a> '
						+ '<br><span style="opacity:.7">Opens in a new tab — set, save, then reload this editor to preview.</span></p>';
				}
				html += visRow( it );
			} else if ( it.type === 'section' && it.key === 'accessories' && ! B.isTpl ) {
				html += '<p class="ttl">Product Accessories</p><p class="hint">Choose which products appear as accessories. Leave empty to use automatic category matching.</p>';
				if ( state.accessories.length ) {
					html += '<div class="rmpe-acclist">';
					state.accessories.forEach( function ( a ) {
						html += '<div class="rmpe-accitem" data-accid="' + a.id + '"><span>' + ( a.title || '' ).replace( /</g, '&lt;' ) + '</span><button type="button" data-accrem="' + a.id + '" title="Remove" aria-label="Remove">&times;</button></div>';
					} );
					html += '</div>';
				}
				html += '<div class="rmpe-accsrch"><input type="search" id="rmpe-acc-q" placeholder="Search and add a product…" autocomplete="off"></div>';
				html += visRow( it );
			} else if ( it.type === 'section' ) {
				html += '<p class="ttl">' + sectionName( it ) + '</p><p class="hint">This section renders from the product’s fields.</p>';
				html += visRow( it );
			} else {
				html += '<p class="ttl">' + sectionName( it ) + '</p><p class="hint">Edit this pattern’s text &amp; images for THIS page only.</p>';
				html += '<textarea id="rmpe-pat" class="rmpe-wysiwyg">' + ( it.html ? it.html.replace( /</g, '&lt;' ) : '' ) + '</textarea>';
				html += '<button class="danger" data-act="remove" style="margin-top:12px">Remove pattern</button>';
			}
			box.innerHTML = html;
			if ( it.type === 'section' && it.key === 'specs' && ! B.isTpl ) { initSpecEditor(); }
			if ( it.type === 'section' && it.key === 'accessories' && ! B.isTpl ) { initAccSearch(); }
			if ( it.type === 'pattern' ) { initPatEditor( it ); }
		}
		function teardownPatEditor() {
			if ( window.wp && wp.editor && document.getElementById( 'rmpe-pat' ) ) {
				try { wp.editor.remove( 'rmpe-pat' ); } catch ( e ) {}
			}
		}
		function initPatEditor( it ) {
			if ( ! window.wp || ! wp.editor || ! document.getElementById( 'rmpe-pat' ) ) { return; }
			wp.editor.initialize( 'rmpe-pat', {
				// convert_urls:false keeps image / link URLs ABSOLUTE. Left to its
				// default, TinyMCE relativises them against the wp-admin base
				// (../wp-content/…); that path then 404s on the front-end product
				// page and the image "disappears" after saving.
				tinymce: { toolbar1: 'bold italic bullist numlist link removeformat', toolbar2: '', menubar: false, statusbar: false, height: 360, convert_urls: false, relative_urls: false, remove_script_host: false },
				quicktags: { buttons: 'strong,em,link,ul,ol,li,img' },
				mediaButtons: true
			} );
			setTimeout( function () {
				if ( ! window.tinymce ) { return; }
				var ed = tinymce.get( 'rmpe-pat' );
				if ( ! ed ) { return; }
				// Seed from the shared pattern template the first time (no per-page copy yet).
				if ( ! it.html && it.name ) {
					var u = B.ajax + '?action=ricoman_pe_patcontent&nonce=' + encodeURIComponent( B.tnonce ) + '&name=' + encodeURIComponent( it.name );
					fetch( u, { credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( j ) {
						if ( j && j.success && ! it.html ) { ed.setContent( j.data || '' ); }
					} );
				}
				ed.on( 'input change keyup undo redo SetContent ExecCommand', function () { it.html = ed.getContent(); pushDraft(); } );
			}, 300 );
		}
		function field( key, label, type ) {
			var v = ( state.fields[ key ] || '' ).replace( /</g, '&lt;' );
			var input;
			if ( key === 'specification' ) {
				input = '<textarea id="rmpe-spec" class="rmpe-wysiwyg" data-f="specification">' + v + '</textarea>';
			} else if ( type === 'textarea' ) {
				var rows = 3;
				input = '<textarea rows="' + rows + '" data-f="' + key + '">' + v + '</textarea>';
			} else {
				input = '<input type="text" data-f="' + key + '" value="' + v.replace( /"/g, '&quot;' ) + '">';
			}
			return '<label><span>' + label + '</span>' + input + '</label>';
		}
		function galleryControl( label, key ) {
			var arr = state[ key ] || [];
			var thumbs = arr.map( function ( it, idx ) {
				return '<span class="rmpe-gthumb" draggable="true" data-grm="' + key + '" data-gi="' + idx + '" title="Drag to reorder · click to remove" style="background-image:url(' + it.url + ')"></span>';
			} ).join( '' );
			return '<label><span>' + label + ' <em style="font-weight:400;color:var(--faint);text-transform:none;letter-spacing:0">(drag to reorder · click to remove)</em></span></label><div class="rmpe-gallery">' + thumbs
				+ '<button type="button" class="rmpe-gbtn" data-gal="' + key + '">＋ Add images</button></div>';
		}
		function openMedia( cb ) {
			if ( ! window.wp || ! wp.media ) { return; }
			var frame = wp.media( { title: 'Add images', multiple: 'add', library: { type: 'image' }, button: { text: 'Add to gallery' } } );
			frame.on( 'select', function () {
				var items = frame.state().get( 'selection' ).map( function ( a ) {
					a = a.toJSON();
					var u = ( a.sizes && a.sizes.medium ) ? a.sizes.medium.url : a.url;
					return { id: a.id, url: u };
				} );
				cb( items );
			} );
			frame.open();
		}
		function visRow( it ) {
			return '<div class="row"><span>Visible</span><label class="sw"><input type="checkbox" data-act="vis"' + ( it.on === false ? '' : ' checked' ) + '><span class="sl"></span></label></div>';
		}
		$( 'rmpe-set' ).addEventListener( 'input', function ( e ) {
			var f = e.target.dataset.f; if ( ! f ) { return; }
			state.fields[ f ] = e.target.value;
			if ( f === 'title' ) { /* title shows in preview hero */ }
			pushDraft();
		} );
		$( 'rmpe-set' ).addEventListener( 'change', function ( e ) {
			var act = e.target.dataset.act;
			if ( act === 'vis' ) { state.layout[ state.sel ].on = e.target.checked; renderList(); pushDraft(); return; }
			if ( act === 'colsglobal' ) { state.colsGlobal = e.target.checked; return; }
			if ( act === 'cfgmode' ) { state.configVisual = e.target.value === '1'; renderSettings(); pushDraft(); return; }
			if ( e.target.dataset.col ) {
				var c = e.target.dataset.col;
				var i = state.cols.indexOf( c );
				if ( e.target.checked && i < 0 ) { state.cols.push( c ); }
				else if ( ! e.target.checked && i > -1 ) { state.cols.splice( i, 1 ); }
				// keep columns in canonical order
				state.cols = ( B.specCols || [] ).filter( function ( x ) { return state.cols.indexOf( x ) > -1; } );
				renderSettings(); // refresh so the filter eye enables/disables with the column
				pushDraft();
			}
		} );
		$( 'rmpe-set' ).addEventListener( 'click', function ( e ) {
			var filt = e.target.closest( '[data-filt]' );
			if ( filt ) {
				if ( filt.disabled ) { return; }
				var fc = filt.getAttribute( 'data-filt' );
				var fk = state.filterOff.indexOf( fc );
				if ( fk > -1 ) { state.filterOff.splice( fk, 1 ); } else { state.filterOff.push( fc ); }
				renderSettings(); pushDraft();
				return;
			}
			var accRem = e.target.closest( '[data-accrem]' );
			if ( accRem ) {
				var remId = parseInt( accRem.getAttribute( 'data-accrem' ), 10 );
				state.accessories = state.accessories.filter( function ( a ) { return a.id !== remId; } );
				pushDraft(); renderSettings();
				return;
			}
			var grm = e.target.closest( '[data-grm]' );
			if ( grm ) {
				var rk = grm.getAttribute( 'data-grm' );
				var gi = parseInt( grm.getAttribute( 'data-gi' ), 10 );
				if ( state[ rk ] && gi > -1 ) { state[ rk ].splice( gi, 1 ); renderSettings(); pushDraft(); }
				return;
			}
			var gal = e.target.closest( '[data-gal]' );
			if ( gal ) {
				var key = gal.getAttribute( 'data-gal' );
				openMedia( function ( items ) {
					var byId = {};
					( state[ key ] || [] ).concat( items ).forEach( function ( it ) { if ( it && it.id ) { byId[ it.id ] = it; } } );
					state[ key ] = Object.keys( byId ).map( function ( id ) { return byId[ id ]; } );
					renderSettings(); pushDraft();
				} );
				return;
			}
			var btn = e.target.closest( '[data-act=remove]' ); if ( ! btn ) { return; }
			state.layout.splice( state.sel, 1 ); state.sel = Math.max( 0, state.sel - 1 );
			renderList(); renderAdd(); renderSettings(); pushDraft();
		} );

		/* ---- gallery drag-to-reorder ---- */
		var gDragKey = null, gDragIdx = null;
		$( 'rmpe-set' ).addEventListener( 'dragstart', function ( e ) {
			var t = e.target.closest( '.rmpe-gthumb' ); if ( ! t ) { return; }
			gDragKey = t.getAttribute( 'data-grm' ); gDragIdx = parseInt( t.getAttribute( 'data-gi' ), 10 );
			e.dataTransfer.effectAllowed = 'move'; t.classList.add( 'rmpe-gdrag' );
		} );
		$( 'rmpe-set' ).addEventListener( 'dragover', function ( e ) {
			if ( e.target.closest( '.rmpe-gthumb' ) && gDragKey !== null ) { e.preventDefault(); }
		} );
		$( 'rmpe-set' ).addEventListener( 'drop', function ( e ) {
			var t = e.target.closest( '.rmpe-gthumb' ); if ( ! t || gDragKey === null ) { return; }
			e.preventDefault();
			var k = t.getAttribute( 'data-grm' ); if ( k !== gDragKey ) { return; }
			var to = parseInt( t.getAttribute( 'data-gi' ), 10 );
			var arr = state[ k ]; if ( ! arr || gDragIdx === to ) { return; }
			var moved = arr.splice( gDragIdx, 1 )[ 0 ]; arr.splice( to, 0, moved );
			gDragKey = null; gDragIdx = null; renderSettings(); pushDraft();
		} );
		$( 'rmpe-set' ).addEventListener( 'dragend', function () {
			document.querySelectorAll( '.rmpe-gdrag' ).forEach( function ( x ) { x.classList.remove( 'rmpe-gdrag' ); } );
			gDragKey = null; gDragIdx = null;
		} );

		/* ---- device toggle ---- */
		$( 'rmpe-dev' ).addEventListener( 'click', function ( e ) {
			var b = e.target.closest( 'button' ); if ( ! b ) { return; }
			state.device = b.dataset.d;
			$( 'rmpe-dev' ).querySelectorAll( 'button' ).forEach( function ( x ) { x.classList.toggle( 'on', x === b ); } );
			var f = $( 'rmpe-frame' ); f.className = 'rmpe-frame' + ( state.device === 'desktop' ? '' : ' ' + state.device );
		} );

		/* ---- save / exit ---- */
		$( 'rmpe-save' ).addEventListener( 'click', function () {
			$( 'rmpe-save-layout' ).value = JSON.stringify( state.layout );
			$( 'rmpe-save-fields' ).value = JSON.stringify( state.fields );
			$( 'rmpe-save-cols' ).value = JSON.stringify( state.cols );
			$( 'rmpe-save-filteroff' ).value = JSON.stringify( state.filterOff );
			$( 'rmpe-save-configvisual' ).value = state.configVisual ? '1' : '0';
			$( 'rmpe-save-cols-global' ).value = state.colsGlobal ? '1' : '0';
			$( 'rmpe-save-gallery' ).value = JSON.stringify( state.gallery.map( function ( i ) { return i.id; } ) );
			$( 'rmpe-save-insitu' ).value = JSON.stringify( state.insitu.map( function ( i ) { return i.id; } ) );
			$( 'rmpe-save-accessories' ).value = JSON.stringify( state.accessories.map( function ( i ) { return i.id; } ) );
			$( 'rmpe-saveform' ).submit();
		} );
		$( 'rmpe-exit' ).addEventListener( 'click', function () { window.location.href = B.exitUrl; } );
		// Independently hide the left / right panels for a wider preview.
		$( 'rmpe-tleft' ).addEventListener( 'click', function () {
			document.querySelector( '.rmpe' ).classList.toggle( 'hide-left' );
			this.classList.toggle( 'rmpe-btn-on' );
		} );
		$( 'rmpe-tright' ).addEventListener( 'click', function () {
			document.querySelector( '.rmpe' ).classList.toggle( 'hide-right' );
			this.classList.toggle( 'rmpe-btn-on' );
		} );
		// Reset this product back to its template / default layout.
		var rst = $( 'rmpe-reset' );
		if ( rst ) {
			rst.addEventListener( 'click', function () {
				if ( window.confirm( 'Reset this product to its template/default layout? Your custom changes will be removed.' ) ) {
					$( 'rmpe-resetform' ).submit();
				}
			} );
		}

		/* ---- boot ---- */
		renderList(); renderAdd(); renderSettings();
		pushDraft(); // seed the draft and reload preview so the saved state is always shown.
	} )();
	</script>
	<?php
}

/* --------------------------------------------------------------------- save */

add_action( 'admin_post_ricoman_save_product_page', function () {
	$pid = isset( $_POST['product'] ) ? absint( $_POST['product'] ) : 0;
	if ( ! $pid || 'product' !== get_post_type( $pid ) ) {
		wp_die( esc_html__( 'Invalid product.', 'ricoman' ) );
	}
	check_admin_referer( 'ricoman_save_product_page_' . $pid );
	if ( ! current_user_can( 'edit_post', $pid ) ) {
		wp_die( esc_html__( 'Permission denied.', 'ricoman' ) );
	}

	$layout = array();
	if ( isset( $_POST['layout_json'] ) ) {
		$d = json_decode( wp_unslash( $_POST['layout_json'] ), true );
		if ( is_array( $d ) ) {
			$layout = $d;
		}
	}
	if ( ! $layout ) {
		$layout = ricoman_pe_default_layout();
	}

	// Template mode: save the layout to the template, not the product.
	$tpl = isset( $_POST['template'] ) ? absint( $_POST['template'] ) : 0;
	if ( $tpl && 'rm_ptemplate' === get_post_type( $tpl ) && current_user_can( 'edit_post', $tpl ) ) {
		update_post_meta( $tpl, '_ricoman_layout', wp_slash( wp_json_encode( array_values( $layout ) ) ) ); // wp_slash: see product save below.
		delete_transient( ricoman_pe_draft_key( $pid ) );
		wp_safe_redirect( admin_url( 'admin.php?page=ricoman-product-editor&template=' . $tpl . '&saved=1' ) );
		exit;
	}

	// Fields.
	$fields = array();
	if ( isset( $_POST['fields_json'] ) ) {
		$d = json_decode( wp_unslash( $_POST['fields_json'] ), true );
		if ( is_array( $d ) ) {
			$fields = $d;
		}
	}
	$title = isset( $fields['title'] ) ? sanitize_text_field( $fields['title'] ) : get_the_title( $pid );
	foreach ( ricoman_pe_field_groups() as $grp ) {
		foreach ( $grp as $f ) {
			$k    = $f[0];
			$type = $f[2];
			if ( 'title' === $k || ! array_key_exists( $k, $fields ) ) {
				continue;
			}
			$raw = (string) $fields[ $k ];
			if ( 'specification' === $k ) {
				$v = wp_kses_post( $raw );
			} elseif ( 'textarea' === $type ) {
				$v = sanitize_textarea_field( $raw );
			} else {
				$v = sanitize_text_field( $raw );
			}
			if ( 0 === strpos( $k, '_ricoman_' ) ) {
				update_post_meta( $pid, $k, $v ); // plain theme meta (CTA buttons).
			} elseif ( function_exists( 'update_field' ) ) {
				update_field( $k, $v, $pid );
			} else {
				update_post_meta( $pid, $k, $v );
			}
		}
	}

	// Galleries (studio + in-situ) — arrays of attachment IDs.
	foreach ( array( 'gallery_json' => 'product_gallery_image', 'insitu_json' => 'insitu_gallery' ) as $post_key => $field_key ) {
		if ( ! isset( $_POST[ $post_key ] ) ) {
			continue;
		}
		$d = json_decode( wp_unslash( $_POST[ $post_key ] ), true );
		if ( ! is_array( $d ) ) {
			continue;
		}
		$idlist = array_values( array_filter( array_map( 'absint', $d ) ) );
		// Use update_post_meta directly — update_field triggers ACF's gallery
		// return-format conversion which can silently drop valid IDs.
		update_post_meta( $pid, $field_key, $idlist );
	}

	// Configure-table columns (per-product + optional global default).
	if ( isset( $_POST['cols_json'] ) ) {
		$cols = json_decode( wp_unslash( $_POST['cols_json'] ), true );
		if ( is_array( $cols ) ) {
			$cols = array_values( array_map( 'sanitize_text_field', $cols ) );
			update_post_meta( $pid, '_ricoman_cols', $cols );
			if ( ! empty( $_POST['cols_global'] ) && '1' === (string) $_POST['cols_global'] ) {
				update_option( 'ricoman_spec_columns', $cols );
			}
		}
	}

	// Hidden-filter columns (column data still shows; only the filter drop-down is hidden).
	if ( isset( $_POST['filteroff_json'] ) ) {
		$foff = json_decode( wp_unslash( $_POST['filteroff_json'] ), true );
		if ( is_array( $foff ) ) {
			$foff = array_values( array_map( 'sanitize_text_field', $foff ) );
			update_post_meta( $pid, '_ricoman_filter_off', $foff );
			if ( ! empty( $_POST['cols_global'] ) && '1' === (string) $_POST['cols_global'] ) {
				update_option( 'ricoman_spec_filters_off', $foff );
			}
		}
	}

	// Accessories: per-product curated list (overrides auto-matching when non-empty).
	if ( isset( $_POST['accessories_json'] ) ) {
		$acc = json_decode( wp_unslash( $_POST['accessories_json'] ), true );
		if ( is_array( $acc ) ) {
			update_post_meta( $pid, '_ricoman_accessories', wp_json_encode( array_values( array_filter( array_map( 'absint', $acc ) ) ) ) );
		}
	}

	// Configure display style (table vs visual configurator).
	if ( isset( $_POST['config_visual_json'] ) ) {
		if ( '1' === (string) wp_unslash( $_POST['config_visual_json'] ) ) {
			update_post_meta( $pid, '_ricoman_config_visual', '1' );
		} else {
			delete_post_meta( $pid, '_ricoman_config_visual' );
		}
	}

	// Heal any TinyMCE-relativised asset URLs in per-page pattern HTML BEFORE
	// storing, so both the saved layout and the compiled content are absolute
	// (front-end + editor both render the image regardless of the page base).
	foreach ( $layout as $li => $lit ) {
		if ( is_array( $lit ) && isset( $lit['type'], $lit['html'] ) && 'pattern' === $lit['type'] && is_string( $lit['html'] ) ) {
			$layout[ $li ]['html'] = ricoman_pe_absolute_urls( $lit['html'] );
		}
	}

	// Layout -> meta + compiled content ($layout already parsed above).
	// wp_slash: update_post_meta() unslashes internally, which would strip the
	// backslashes JSON uses to escape quotes inside per-page pattern HTML and
	// corrupt the stored JSON (get_layout would then fail to parse it and the
	// pattern would vanish from the editor). Slash it so it round-trips intact.
	update_post_meta( $pid, '_ricoman_layout', wp_slash( wp_json_encode( array_values( $layout ) ) ) );
	update_post_meta( $pid, '_ricoman_custom', 1 ); // this product now overrides its template.

	wp_update_post( array(
		'ID'           => $pid,
		'post_title'   => $title ? $title : get_the_title( $pid ),
		'post_content' => ricoman_pe_build_content( $layout ),
	) );

	delete_transient( ricoman_pe_draft_key( $pid ) );
	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-product-editor&product=' . $pid . '&saved=1' ) );
	exit;
} );

/** Reset a product back to its template / default layout (clear the override). */
add_action( 'admin_post_ricoman_pe_reset', function () {
	$pid = isset( $_POST['product'] ) ? absint( $_POST['product'] ) : 0;
	if ( ! $pid || 'product' !== get_post_type( $pid ) ) {
		wp_die( esc_html__( 'Invalid product.', 'ricoman' ) );
	}
	check_admin_referer( 'ricoman_pe_reset_' . $pid );
	if ( ! current_user_can( 'edit_post', $pid ) ) {
		wp_die( esc_html__( 'Permission denied.', 'ricoman' ) );
	}
	delete_post_meta( $pid, '_ricoman_custom' );
	delete_post_meta( $pid, '_ricoman_layout' );
	delete_post_meta( $pid, '_ricoman_cols' );
	delete_post_meta( $pid, '_ricoman_filter_off' );
	wp_update_post( array( 'ID' => $pid, 'post_content' => '' ) ); // back to auto-render.
	delete_transient( ricoman_pe_draft_key( $pid ) );
	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-product-editor&product=' . $pid . '&reset=1' ) );
	exit;
} );
