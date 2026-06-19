<?php
/**
 * Custom Product Page Editor.
 *
 * A bespoke full-width screen for building a product's page: the content fields
 * on the left, a live preview of the real page on the right, and a section
 * manager where you reorder the page sections, switch them on/off, and drop your
 * own patterns into the gaps between them.
 *
 * The arrangement is stored as a small layout list in post meta (_ricoman_layout)
 * and compiled into the product's post_content (section blocks + chosen pattern
 * content) so the front end renders exactly what the preview shows. Products that
 * are never touched keep auto-rendering the default layout.
 *
 * Reached from the product editor ("Open Product Page Editor") at
 * admin.php?page=ricoman-product-editor&product=ID.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		// Don't offer the product section blocks or the whole-page pattern here.
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

/* ------------------------------------------------------------------- routing */

add_action( 'admin_menu', function () {
	// Hidden page (no menu item) reached via admin.php?page=ricoman-product-editor.
	add_submenu_page(
		'options.php',
		__( 'Product Page Editor', 'ricoman' ),
		__( 'Product Page Editor', 'ricoman' ),
		'edit_posts',
		'ricoman-product-editor',
		'ricoman_product_editor_render'
	);
} );

/** Row action on the product list: open this editor directly. */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'product' === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
		$url               = admin_url( 'admin.php?page=ricoman-product-editor&product=' . $post->ID );
		$actions['rm_page'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Page Editor', 'ricoman' ) . '</a>';
	}
	return $actions;
}, 10, 2 );

/* -------------------------------------------------------------------- screen */

function ricoman_product_editor_render() {
	$pid = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : 0;
	if ( ! $pid || 'product' !== get_post_type( $pid ) || ! current_user_can( 'edit_post', $pid ) ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Product Page Editor', 'ricoman' ) . '</h1><p>' . esc_html__( 'Choose a product from the Products list.', 'ricoman' ) . '</p></div>';
		return;
	}

	$layout   = ricoman_pe_get_layout( $pid );
	$patterns = ricoman_pe_patterns();
	$labels   = ricoman_section_defs();
	$preview  = add_query_arg( array( 'rmpe' => 1, 't' => time() ), get_permalink( $pid ) );
	$saved    = isset( $_GET['saved'] );

	$g = function ( $k ) use ( $pid ) {
		return function_exists( 'get_field' ) ? (string) get_field( $k, $pid ) : (string) get_post_meta( $pid, $k, true );
	};
	?>
	<style>
		#wpcontent { padding-left: 0; }
		.rmpe { display: grid; grid-template-columns: 460px 1fr; gap: 0; height: calc(100vh - 32px); }
		.rmpe-edit { overflow-y: auto; padding: 22px 24px 60px; background: #fff; border-right: 1px solid #e2e4e7; }
		.rmpe-prev { background: #e4e4e4; position: relative; }
		.rmpe-prev iframe { width: 100%; height: 100%; border: 0; display: block; background: #fff; }
		.rmpe h1 { font-size: 20px; margin: 0 0 4px; }
		.rmpe .rmpe-sub { color: #646970; margin: 0 0 18px; }
		.rmpe label.rmpe-f { display: block; margin: 0 0 14px; font-weight: 600; }
		.rmpe label.rmpe-f span { display: block; font-size: 12px; color: #50575e; margin-bottom: 4px; }
		.rmpe input[type=text], .rmpe textarea { width: 100%; }
		.rmpe h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .04em; color: #1d2327; margin: 26px 0 10px; }
		#rmpe-list { list-style: none; margin: 0; padding: 0; }
		.rmpe-item { border: 1px solid #dcdcde; border-radius: 8px; padding: 9px 10px; margin: 0 0 8px; background: #f6f7f7; display: flex; align-items: center; gap: 8px; }
		.rmpe-item.is-off { opacity: .5; }
		.rmpe-item.is-pat { background: #eef4ff; border-color: #c5d8fb; }
		.rmpe-item .rmpe-name { flex: 1; font-weight: 600; font-size: 13px; }
		.rmpe-item button { cursor: pointer; }
		.rmpe-gap { text-align: center; margin: -2px 0 8px; }
		.rmpe-gap select { max-width: 200px; }
		.rmpe-save { position: sticky; bottom: 0; background: #fff; padding: 14px 0 0; border-top: 1px solid #e2e4e7; margin-top: 18px; }
		.rmpe-mini { font-size: 12px; }
		.rmpe-links { margin-top: 12px; font-size: 13px; }
	</style>

	<div class="rmpe">
		<div class="rmpe-edit">
			<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="rmpe-mini">&larr; <?php esc_html_e( 'All products', 'ricoman' ); ?></a></p>
			<h1><?php echo esc_html( get_the_title( $pid ) ); ?></h1>
			<p class="rmpe-sub"><?php esc_html_e( 'Edit the page on the left; the live preview is on the right.', 'ricoman' ); ?></p>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success" style="margin:0 0 16px"><p><?php esc_html_e( 'Saved.', 'ricoman' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rmpe-form">
				<input type="hidden" name="action" value="ricoman_save_product_page">
				<input type="hidden" name="product" value="<?php echo esc_attr( $pid ); ?>">
				<?php wp_nonce_field( 'ricoman_save_product_page_' . $pid ); ?>

				<h2><?php esc_html_e( 'Content', 'ricoman' ); ?></h2>
				<label class="rmpe-f"><span><?php esc_html_e( 'Title', 'ricoman' ); ?></span>
					<input type="text" name="f_title" value="<?php echo esc_attr( get_the_title( $pid ) ); ?>"></label>
				<label class="rmpe-f"><span><?php esc_html_e( 'Subtitle', 'ricoman' ); ?></span>
					<input type="text" name="f_product_subname" value="<?php echo esc_attr( $g( 'product_subname' ) ); ?>"></label>
				<label class="rmpe-f"><span><?php esc_html_e( 'Short description', 'ricoman' ); ?></span>
					<textarea name="f_product_sort_description" rows="3"><?php echo esc_textarea( $g( 'product_sort_description' ) ); ?></textarea></label>
				<label class="rmpe-f"><span><?php esc_html_e( 'Order code', 'ricoman' ); ?></span>
					<input type="text" name="f_product_code" value="<?php echo esc_attr( $g( 'product_code' ) ); ?>"></label>

				<h2><?php esc_html_e( 'Sections & patterns', 'ricoman' ); ?></h2>
				<p class="rmpe-mini" style="color:#646970;margin-top:-4px"><?php esc_html_e( 'Reorder with the arrows, switch sections off with the eye, or insert a pattern into any gap.', 'ricoman' ); ?></p>
				<ul id="rmpe-list"></ul>
				<input type="hidden" name="layout_json" id="rmpe-layout-json" value="">

				<div class="rmpe-save">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save page', 'ricoman' ); ?></button>
					<a class="button" target="_blank" href="<?php echo esc_url( get_permalink( $pid ) ); ?>"><?php esc_html_e( 'View live', 'ricoman' ); ?></a>
				</div>
			</form>

			<div class="rmpe-links">
				<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php esc_html_e( 'Edit all fields (gallery, specification, variants…)', 'ricoman' ); ?></a>
			</div>
		</div>

		<div class="rmpe-prev">
			<iframe id="rmpe-iframe" src="<?php echo esc_url( $preview ); ?>" title="<?php esc_attr_e( 'Live preview', 'ricoman' ); ?>"></iframe>
		</div>
	</div>

	<script>
	( function () {
		var LAYOUT   = <?php echo wp_json_encode( array_values( $layout ) ); ?>;
		var SECTIONS = <?php echo wp_json_encode( $labels ); ?>;
		var PATTERNS = <?php echo wp_json_encode( $patterns ); ?>;
		var list = document.getElementById( 'rmpe-list' );
		var json = document.getElementById( 'rmpe-layout-json' );

		function patternOptions() {
			var s = '<option value="">+ Insert pattern…</option>';
			Object.keys( PATTERNS ).forEach( function ( n ) {
				s += '<option value="' + n.replace( /"/g, '&quot;' ) + '">' + PATTERNS[ n ] + '</option>';
			} );
			return s;
		}

		function render() {
			list.innerHTML = '';
			LAYOUT.forEach( function ( item, i ) {
				var li = document.createElement( 'li' );
				var isPat = item.type === 'pattern';
				li.className = 'rmpe-item' + ( isPat ? ' is-pat' : '' ) + ( ( !isPat && item.on === false ) ? ' is-off' : '' );

				var name = isPat ? ( PATTERNS[ item.name ] || item.name ) : ( SECTIONS[ item.key ] || item.key );
				var html = '<span class="rmpe-name">' + ( isPat ? '◧ ' : '' ) + name + '</span>';
				html += '<button type="button" class="button button-small" data-act="up" data-i="' + i + '" title="Move up">↑</button>';
				html += '<button type="button" class="button button-small" data-act="down" data-i="' + i + '" title="Move down">↓</button>';
				if ( isPat ) {
					html += '<button type="button" class="button button-small" data-act="remove" data-i="' + i + '" title="Remove">✕</button>';
				} else {
					html += '<button type="button" class="button button-small" data-act="toggle" data-i="' + i + '" title="Show / hide">' + ( item.on === false ? '🚫' : '👁' ) + '</button>';
				}
				li.innerHTML = html;
				list.appendChild( li );

				// "insert pattern after this row" gap.
				var gap = document.createElement( 'div' );
				gap.className = 'rmpe-gap';
				gap.innerHTML = '<select data-after="' + i + '">' + patternOptions() + '</select>';
				list.appendChild( gap );
			} );
			json.value = JSON.stringify( LAYOUT );
		}

		list.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( 'button' );
			if ( ! b ) { return; }
			var i = parseInt( b.getAttribute( 'data-i' ), 10 );
			var act = b.getAttribute( 'data-act' );
			if ( act === 'up' && i > 0 ) { var t = LAYOUT[ i - 1 ]; LAYOUT[ i - 1 ] = LAYOUT[ i ]; LAYOUT[ i ] = t; }
			else if ( act === 'down' && i < LAYOUT.length - 1 ) { var u = LAYOUT[ i + 1 ]; LAYOUT[ i + 1 ] = LAYOUT[ i ]; LAYOUT[ i ] = u; }
			else if ( act === 'remove' ) { LAYOUT.splice( i, 1 ); }
			else if ( act === 'toggle' ) { LAYOUT[ i ].on = ( LAYOUT[ i ].on === false ); }
			render();
		} );

		list.addEventListener( 'change', function ( e ) {
			var sel = e.target;
			if ( sel.tagName !== 'SELECT' || ! sel.value ) { return; }
			var after = parseInt( sel.getAttribute( 'data-after' ), 10 );
			LAYOUT.splice( after + 1, 0, { type: 'pattern', name: sel.value } );
			render();
		} );

		render();
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

	// Content fields.
	$fields = array(
		'product_subname'          => isset( $_POST['f_product_subname'] ) ? sanitize_text_field( wp_unslash( $_POST['f_product_subname'] ) ) : '',
		'product_sort_description' => isset( $_POST['f_product_sort_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['f_product_sort_description'] ) ) : '',
		'product_code'             => isset( $_POST['f_product_code'] ) ? sanitize_text_field( wp_unslash( $_POST['f_product_code'] ) ) : '',
	);
	foreach ( $fields as $k => $v ) {
		if ( function_exists( 'update_field' ) ) {
			update_field( $k, $v, $pid );
		} else {
			update_post_meta( $pid, $k, $v );
		}
	}

	// Layout -> meta + compiled post_content.
	$layout = array();
	if ( isset( $_POST['layout_json'] ) ) {
		$decoded = json_decode( wp_unslash( $_POST['layout_json'] ), true );
		if ( is_array( $decoded ) ) {
			$layout = $decoded;
		}
	}
	if ( ! $layout ) {
		$layout = ricoman_pe_default_layout();
	}
	update_post_meta( $pid, '_ricoman_layout', wp_json_encode( array_values( $layout ) ) );

	$title   = isset( $_POST['f_title'] ) ? sanitize_text_field( wp_unslash( $_POST['f_title'] ) ) : get_the_title( $pid );
	$content = ricoman_pe_build_content( $layout );
	wp_update_post( array(
		'ID'           => $pid,
		'post_title'   => $title ? $title : get_the_title( $pid ),
		'post_content' => $content,
	) );

	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-product-editor&product=' . $pid . '&saved=1' ) );
	exit;
} );
