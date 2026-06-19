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

/** Editable content fields shown in the builder: meta key => label. */
function ricoman_pe_fields() {
	return array(
		'product_subname'          => __( 'Subtitle', 'ricoman' ),
		'product_sort_description' => __( 'Short description', 'ricoman' ),
		'product_code'             => __( 'Order code', 'ricoman' ),
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

/** Read a product's saved layout (or the default). */
function ricoman_pe_get_layout( $pid ) {
	$raw = get_post_meta( $pid, '_ricoman_layout', true );
	if ( $raw ) {
		$data = json_decode( $raw, true );
		if ( is_array( $data ) && $data ) {
			return $data;
		}
	}
	return ricoman_pe_default_layout();
}

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
		} elseif ( 'pattern' === $type && ! empty( $item['name'] ) && $reg && $reg->is_registered( $item['name'] ) ) {
			$pat      = $reg->get_registered( $item['name'] );
			$content .= ( isset( $pat['content'] ) ? $pat['content'] : '' ) . "\n";
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
	$GLOBALS['rm_pe_preview'] = array(
		'pid'    => $pid,
		'fields' => ( is_array( $draft ) && ! empty( $draft['fields'] ) ) ? $draft['fields'] : array(),
		'layout' => ( is_array( $draft ) && ! empty( $draft['layout'] ) ) ? $draft['layout'] : ricoman_pe_get_layout( $pid ),
	);
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
	return ricoman_pe_build_content( $GLOBALS['rm_pe_preview']['layout'] );
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
	set_transient( ricoman_pe_draft_key( $pid ), array( 'layout' => $layout, 'fields' => $fields ), HOUR_IN_SECONDS );
	wp_send_json_success();
} );

/* -------------------------------------------------------------------- screen */

function ricoman_product_editor_render() {
	$pid = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : 0;
	if ( ! $pid || 'product' !== get_post_type( $pid ) || ! current_user_can( 'edit_post', $pid ) ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Product Page Editor', 'ricoman' ) . '</h1><p>' . esc_html__( 'Open this from a product (Products → row → Page Editor).', 'ricoman' ) . '</p></div>';
		return;
	}

	$layout   = array_values( ricoman_pe_get_layout( $pid ) );
	$patterns = ricoman_pe_patterns();
	$labels   = ricoman_section_defs();
	$fielddef = ricoman_pe_fields();
	$preview  = add_query_arg( 'rmpe', 1, get_permalink( $pid ) );

	$gv = function ( $k ) use ( $pid ) {
		return function_exists( 'get_field' ) ? (string) get_field( $k, $pid ) : (string) get_post_meta( $pid, $k, true );
	};
	$fieldvals = array( 'title' => get_the_title( $pid ) );
	foreach ( array_keys( $fielddef ) as $k ) {
		$fieldvals[ $k ] = $gv( $k );
	}

	// Section meta for the left list (icon hints).
	$icons = array(
		'hero' => 'format-image', 'specs' => 'list-view', 'configure' => 'editor-table',
		'accessories' => 'screenoptions', 'related' => 'grid-view', 'cta' => 'megaphone',
	);

	$boot = array(
		'pid'      => $pid,
		'ajax'     => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'ricoman_pe_' . $pid ),
		'preview'  => $preview,
		'layout'   => $layout,
		'sections' => $labels,
		'patterns' => $patterns,
		'fields'   => $fielddef,
		'values'   => $fieldvals,
		'icons'    => $icons,
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
		#wpcontent{padding-left:0}#wpbody-content{padding-bottom:0}
		#wpfooter{display:none}
		.rmpe{position:fixed;top:32px;left:160px;right:0;bottom:0;display:flex;flex-direction:column;background:#f1f1f4;font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;z-index:9}
		.folded .rmpe{left:36px}
		@media(max-width:782px){.rmpe{left:0;top:46px}}
		/* top bar */
		.rmpe-top{display:flex;align-items:center;gap:14px;height:54px;padding:0 16px;background:#fff;border-bottom:1px solid #e3e3e8;flex:0 0 auto}
		.rmpe-top .rmpe-title{font-weight:600;font-size:14px;color:#1d2327;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.rmpe-top .rmpe-title small{color:#787c82;font-weight:400}
		.rmpe-dev{display:flex;background:#f0f0f3;border-radius:8px;padding:3px}
		.rmpe-dev button{border:0;background:none;padding:5px 9px;border-radius:6px;cursor:pointer;color:#646970;display:flex;align-items:center}
		.rmpe-dev button.on{background:#fff;color:#1d2327;box-shadow:0 1px 2px rgba(0,0,0,.12)}
		.rmpe-dev .dashicons{font-size:18px;width:18px;height:18px}
		.rmpe-btn{border:0;border-radius:8px;padding:8px 16px;font-weight:600;font-size:13px;cursor:pointer}
		.rmpe-btn-primary{background:#004899;color:#fff}.rmpe-btn-primary:hover{background:#013a7d}
		.rmpe-btn-ghost{background:transparent;color:#50575e}.rmpe-btn-ghost:hover{color:#1d2327}
		.rmpe-saved{color:#1a7f37;font-weight:600;opacity:0;transition:opacity .3s}
		.rmpe-saved.show{opacity:1}
		/* body */
		.rmpe-body{flex:1;display:flex;min-height:0}
		.rmpe-rail{width:300px;flex:0 0 auto;background:#fff;border-right:1px solid #e3e3e8;display:flex;flex-direction:column;min-height:0}
		.rmpe-rail.right{border-right:0;border-left:1px solid #e3e3e8;width:320px}
		.rmpe-rail h3{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#787c82;margin:0;padding:16px 18px 8px}
		.rmpe-list{list-style:none;margin:0;padding:0 12px 12px;overflow-y:auto;flex:1}
		.rmpe-card{display:flex;align-items:center;gap:10px;padding:11px 12px;border:1px solid #e3e3e8;border-radius:10px;margin:8px 0;background:#fff;cursor:pointer;transition:border-color .12s,box-shadow .12s}
		.rmpe-card:hover{border-color:#c9ccd1}
		.rmpe-card.sel{border-color:#004899;box-shadow:0 0 0 1px #004899}
		.rmpe-card.off{opacity:.5}
		.rmpe-card.pat{background:#f3f7ff;border-color:#cfe0fb}
		.rmpe-card .drag{color:#b5b9c0;cursor:grab;display:flex}
		.rmpe-card .dashicons{font-size:18px;width:18px;height:18px;color:#646970}
		.rmpe-card .nm{flex:1;font-weight:600;color:#1d2327;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.rmpe-card .eye,.rmpe-card .del{border:0;background:none;cursor:pointer;color:#787c82;padding:3px;border-radius:5px;display:flex}
		.rmpe-card .eye:hover,.rmpe-card .del:hover{background:#f0f0f3;color:#1d2327}
		.rmpe-card.drag-over{border-color:#004899;border-style:dashed}
		.rmpe-card.dragging{opacity:.4}
		.rmpe-add{margin:6px 12px 16px;display:flex}
		.rmpe-add select{width:100%;padding:9px;border-radius:9px;border:1px dashed #b9bdc4;background:#fafafb;color:#50575e;font-weight:600}
		/* preview */
		.rmpe-stage{flex:1;min-width:0;display:flex;align-items:flex-start;justify-content:center;overflow:auto;padding:22px}
		.rmpe-frame{background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.12);overflow:hidden;width:100%;max-width:100%;height:calc(100vh - 32px - 54px - 44px);transition:max-width .25s ease}
		.rmpe-frame.tablet{max-width:820px}.rmpe-frame.mobile{max-width:390px}
		.rmpe-frame iframe{width:100%;height:100%;border:0;display:block}
		.rmpe-load{position:absolute;top:70px;left:50%;transform:translateX(-50%);background:#1d2327;color:#fff;padding:6px 14px;border-radius:20px;font-size:12px;opacity:0;transition:opacity .2s;pointer-events:none}
		.rmpe-load.show{opacity:.9}
		/* settings */
		.rmpe-set{padding:18px;overflow-y:auto;flex:1}
		.rmpe-set .ttl{font-weight:700;font-size:15px;color:#1d2327;margin:0 0 4px}
		.rmpe-set .hint{color:#787c82;margin:0 0 16px;font-size:12px}
		.rmpe-set label{display:block;font-weight:600;color:#1d2327;margin:0 0 14px;font-size:12px}
		.rmpe-set label span{display:block;margin-bottom:5px}
		.rmpe-set input[type=text],.rmpe-set textarea{width:100%;border:1px solid #d5d8dd;border-radius:8px;padding:9px 11px;font-size:13px}
		.rmpe-set input[type=text]:focus,.rmpe-set textarea:focus{border-color:#004899;outline:none;box-shadow:0 0 0 1px #004899}
		.rmpe-set .row{display:flex;align-items:center;justify-content:space-between;padding:11px 0;border-top:1px solid #f0f0f3}
		.rmpe-set .sw{position:relative;width:40px;height:22px}
		.rmpe-set .sw input{opacity:0;width:0;height:0}
		.rmpe-set .sl{position:absolute;inset:0;background:#c9ccd1;border-radius:22px;transition:.2s;cursor:pointer}
		.rmpe-set .sl:before{content:"";position:absolute;width:16px;height:16px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s}
		.rmpe-set .sw input:checked+.sl{background:#004899}
		.rmpe-set .sw input:checked+.sl:before{transform:translateX(18px)}
		.rmpe-set .danger{color:#b32d2e;border:1px solid #f0c4c4;background:#fff;border-radius:8px;padding:9px;width:100%;cursor:pointer;font-weight:600;margin-top:10px}
		.rmpe-set .danger:hover{background:#fcf0f0}
		.rmpe-empty{color:#787c82;text-align:center;padding:40px 18px;font-size:13px}
	</style>

	<div class="rmpe">
		<div class="rmpe-top">
			<button class="rmpe-btn rmpe-btn-ghost" id="rmpe-exit">&larr; <?php esc_html_e( 'Exit', 'ricoman' ); ?></button>
			<div class="rmpe-title"><?php echo esc_html( get_the_title( $pid ) ); ?> <small>· <?php esc_html_e( 'Product page', 'ricoman' ); ?></small></div>
			<span class="rmpe-saved" id="rmpe-saved"><?php esc_html_e( 'Saved', 'ricoman' ); ?></span>
			<div class="rmpe-dev" id="rmpe-dev">
				<button data-d="desktop" class="on" title="Desktop"><span class="dashicons dashicons-desktop"></span></button>
				<button data-d="tablet" title="Tablet"><span class="dashicons dashicons-tablet"></span></button>
				<button data-d="mobile" title="Mobile"><span class="dashicons dashicons-smartphone"></span></button>
			</div>
			<a class="rmpe-btn rmpe-btn-ghost" id="rmpe-view" target="_blank" href="<?php echo esc_url( get_permalink( $pid ) ); ?>"><?php esc_html_e( 'View live', 'ricoman' ); ?></a>
			<button class="rmpe-btn rmpe-btn-primary" id="rmpe-save"><?php esc_html_e( 'Save', 'ricoman' ); ?></button>
		</div>

		<div class="rmpe-body">
			<div class="rmpe-rail left">
				<h3><?php esc_html_e( 'Sections', 'ricoman' ); ?></h3>
				<ul class="rmpe-list" id="rmpe-list"></ul>
				<div class="rmpe-add">
					<select id="rmpe-add"></select>
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

		<form id="rmpe-saveform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none">
			<input type="hidden" name="action" value="ricoman_save_product_page">
			<input type="hidden" name="product" value="<?php echo esc_attr( $pid ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'ricoman_save_product_page_' . $pid ) ); ?>">
			<input type="hidden" name="layout_json" id="rmpe-save-layout">
			<input type="hidden" name="fields_json" id="rmpe-save-fields">
		</form>
	</div>

	<script>
	( function () {
		var B = <?php echo wp_json_encode( $boot ); ?>;
		var state = { layout: B.layout.slice(), fields: Object.assign( {}, B.values ), sel: 0, device: 'desktop' };
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
				h += '<span class="dashicons dashicons-' + icon + '"></span>';
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

		/* ---- add picker ---- */
		function renderAdd() {
			var sel = $( 'rmpe-add' );
			var used = state.layout.filter( function ( i ) { return i.type === 'section'; } ).map( function ( i ) { return i.key; } );
			var s = '<option value="">+ ' + B.i18n.addBlock + '…</option>';
			s += '<optgroup label="Sections">';
			Object.keys( B.sections ).forEach( function ( k ) {
				if ( used.indexOf( k ) === -1 ) { s += '<option value="sec:' + k + '">' + B.sections[ k ] + '</option>'; }
			} );
			s += '</optgroup><optgroup label="Patterns">';
			Object.keys( B.patterns ).forEach( function ( n ) {
				s += '<option value="pat:' + n.replace( /"/g, '&quot;' ) + '">' + B.patterns[ n ] + '</option>';
			} );
			s += '</optgroup>';
			sel.innerHTML = s;
		}
		$( 'rmpe-add' ).addEventListener( 'change', function () {
			var v = this.value; if ( ! v ) { return; }
			var at = ( state.sel >= 0 ? state.sel + 1 : state.layout.length );
			if ( v.indexOf( 'sec:' ) === 0 ) { state.layout.splice( at, 0, { type: 'section', key: v.slice( 4 ), on: true } ); }
			else if ( v.indexOf( 'pat:' ) === 0 ) { state.layout.splice( at, 0, { type: 'pattern', name: v.slice( 4 ) } ); }
			state.sel = at; this.value = '';
			renderList(); renderAdd(); renderSettings(); pushDraft();
		} );

		/* ---- right settings ---- */
		function renderSettings() {
			var box = $( 'rmpe-set' );
			var it = state.layout[ state.sel ];
			if ( ! it ) { box.innerHTML = '<div class="rmpe-empty">Select a section to edit it.</div>'; return; }
			var html = '';
			if ( it.type === 'section' && it.key === 'hero' ) {
				html += '<p class="ttl">' + B.i18n.details + '</p><p class="hint">Shown in the hero section.</p>';
				html += field( 'title', 'Title', false );
				Object.keys( B.fields ).forEach( function ( k ) {
					html += field( k, B.fields[ k ], k === 'product_sort_description' );
				} );
				html += visRow( it );
			} else if ( it.type === 'section' ) {
				html += '<p class="ttl">' + sectionName( it ) + '</p><p class="hint">This section renders from the product’s fields.</p>';
				html += visRow( it );
			} else {
				html += '<p class="ttl">' + sectionName( it ) + '</p><p class="hint">Pattern block. Edit its content in the page editor; here you can position or remove it.</p>';
				html += '<button class="danger" data-act="remove">Remove pattern</button>';
			}
			box.innerHTML = html;
		}
		function field( key, label, area ) {
			var v = ( state.fields[ key ] || '' ).replace( /</g, '&lt;' );
			var input = area ? '<textarea rows="3" data-f="' + key + '">' + v + '</textarea>' : '<input type="text" data-f="' + key + '" value="' + v.replace( /"/g, '&quot;' ) + '">';
			return '<label><span>' + label + '</span>' + input + '</label>';
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
			if ( act === 'vis' ) { state.layout[ state.sel ].on = e.target.checked; renderList(); pushDraft(); }
		} );
		$( 'rmpe-set' ).addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-act=remove]' ); if ( ! btn ) { return; }
			state.layout.splice( state.sel, 1 ); state.sel = Math.max( 0, state.sel - 1 );
			renderList(); renderAdd(); renderSettings(); pushDraft();
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
			$( 'rmpe-saveform' ).submit();
		} );
		$( 'rmpe-exit' ).addEventListener( 'click', function () { window.location.href = B.exitUrl; } );

		/* ---- boot ---- */
		renderList(); renderAdd(); renderSettings();
		pushDraft( false ); // seed the draft so the first preview matches.
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

	// Fields.
	$fields = array();
	if ( isset( $_POST['fields_json'] ) ) {
		$d = json_decode( wp_unslash( $_POST['fields_json'] ), true );
		if ( is_array( $d ) ) {
			$fields = $d;
		}
	}
	$title = isset( $fields['title'] ) ? sanitize_text_field( $fields['title'] ) : get_the_title( $pid );
	foreach ( ricoman_pe_fields() as $k => $label ) {
		if ( ! array_key_exists( $k, $fields ) ) {
			continue;
		}
		$v = ( 'product_sort_description' === $k ) ? sanitize_textarea_field( $fields[ $k ] ) : sanitize_text_field( $fields[ $k ] );
		if ( function_exists( 'update_field' ) ) {
			update_field( $k, $v, $pid );
		} else {
			update_post_meta( $pid, $k, $v );
		}
	}

	// Layout -> meta + compiled content.
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
	update_post_meta( $pid, '_ricoman_layout', wp_json_encode( array_values( $layout ) ) );

	wp_update_post( array(
		'ID'           => $pid,
		'post_title'   => $title ? $title : get_the_title( $pid ),
		'post_content' => ricoman_pe_build_content( $layout ),
	) );

	delete_transient( ricoman_pe_draft_key( $pid ) );
	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-product-editor&product=' . $pid . '&saved=1' ) );
	exit;
} );
