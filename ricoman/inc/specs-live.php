<?php
/**
 * Live, configurator-aware specification & accessories blocks.
 *
 * On a family-linked product page nothing is bound to a single code until the
 * customer picks a product in the Variant range block and configures it. These
 * blocks listen for the page-wide events the configurator emits and re-render:
 *   - document 'ricoman:product' { code, name }  — a product was chosen
 *   - document 'ricoman:variant' { code, sku }    — a full valid SKU was built
 * so "Specification" updates to the exact variant, and "Accessories" to the
 * chosen product. Both fall back to the linked product's base code on load.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---- Proxy: per-variant technical data + accessories (cached 1h) ---- */
function ricoman_rb_specs_cb() {
	check_ajax_referer( 'ricoman_rb_front', 'nonce' );
	$code = isset( $_REQUEST['code'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', wp_unslash( $_REQUEST['code'] ) ) : '';
	$sku  = isset( $_REQUEST['sku'] ) ? preg_replace( '#[^A-Za-z0-9_\-/]#', '', wp_unslash( $_REQUEST['sku'] ) ) : '';
	if ( '' === $code && '' !== $sku ) {
		$code = strtok( $sku, '/' );
	}
	if ( '' === $code ) {
		wp_send_json_error( 'No product code.' );
	}
	if ( ! function_exists( 'ricoman_ricobot_get' ) ) {
		wp_send_json_error( 'RICOBOT unavailable.' );
	}
	// Pass the resolved SKU so RICOBOT can fill variant-dependent ("auto") rows.
	$path = 'api/public/products/' . rawurlencode( $code );
	if ( '' !== $sku ) {
		$path .= '?sku=' . rawurlencode( $sku );
	}
	$data = ricoman_ricobot_get( $path );
	if ( is_wp_error( $data ) ) {
		wp_send_json_error( $data->get_error_message() );
	}
	wp_send_json_success(
		array(
			'name'          => isset( $data['name'] ) ? $data['name'] : $code,
			'technicalData' => isset( $data['technicalData'] ) && is_array( $data['technicalData'] ) ? $data['technicalData'] : array(),
			'accessories'   => isset( $data['accessories'] ) && is_array( $data['accessories'] ) ? $data['accessories'] : array(),
			'documents'     => isset( $data['documents'] ) && is_array( $data['documents'] ) ? $data['documents'] : array(),
			'photometric'   => isset( $data['photometric'] ) && is_array( $data['photometric'] ) ? $data['photometric'] : array(),
			'gallery'       => isset( $data['gallery'] ) && is_array( $data['gallery'] ) ? $data['gallery'] : array(),
			'heroUrl'       => isset( $data['heroUrl'] ) ? $data['heroUrl'] : '',
		)
	);
}
add_action( 'wp_ajax_ricoman_rb_specs', 'ricoman_rb_specs_cb' );
add_action( 'wp_ajax_nopriv_ricoman_rb_specs', 'ricoman_rb_specs_cb' );

/* ---- Block: live specification (changes with the configurator) ---- */
add_shortcode( 'ricoman_specs_live', function ( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'ricoman_specs_live' );
	$pid  = $atts['id'] ? (int) $atts['id'] : get_the_ID();
	// When RICOBOT isn't connected, fall back to the editable static spec table.
	if ( ! function_exists( 'ricoman_ricobot_ready' ) || ! ricoman_ricobot_ready() ) {
		return do_shortcode( '[ricoman_product_specs]' );
	}
	$code  = $pid ? (string) get_post_meta( $pid, '_ricoman_sku', true ) : '';
	$nonce = wp_create_nonce( 'ricoman_rb_front' );
	$ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
	ob_start();
	?>
	<div class="rm-specs-live" data-code="<?php echo esc_attr( $code ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo $ajax; ?>">
		<h2 class="wp-block-heading rm-shead">Specification</h2>
		<div class="rm-specs-live-body"><p class="rm-config-note"><?php echo $code ? esc_html__( 'Loading specification…', 'ricoman' ) : esc_html__( 'Choose a variant above to see its full specification.', 'ricoman' ); ?></p></div>
	</div>
	<script>
	( function () {
		var root = document.currentScript.previousElementSibling;
		var body = root.querySelector( '.rm-specs-live-body' );
		var nonce = root.dataset.nonce, ajax = root.dataset.ajax;
		var code = root.dataset.code || '';
		function call( params ) {
			var b = new FormData(); b.append( 'action', 'ricoman_rb_specs' ); b.append( 'nonce', nonce );
			Object.keys( params ).forEach( function ( k ) { if ( params[ k ] ) { b.append( k, params[ k ] ); } } );
			return fetch( ajax, { method: 'POST', body: b } ).then( function ( r ) { return r.json(); } );
		}
		function render( sku ) {
			if ( ! code && ! sku ) { return; }
			call( { code: code, sku: sku || '' } ).then( function ( res ) {
				if ( ! res.success ) { body.innerHTML = '<p class="rm-config-note">Specification unavailable.</p>'; return; }
				var sections = res.data.technicalData || [];
				if ( ! sections.length ) { body.innerHTML = '<p class="rm-config-note">No specification published for this code yet.</p>'; return; }
				var html = '';
				if ( sku ) { html += '<p class="rm-specs-for">Showing specification for <strong>' + sku + '</strong></p>'; }
				sections.forEach( function ( sec ) {
					if ( sec.section ) { html += '<h3 class="rm-specs-section rm-shead">' + sec.section + '</h3>'; }
					html += '<table class="ricoman-spec-table"><tbody>';
					( sec.rows || [] ).forEach( function ( row ) {
						html += '<tr><th scope="row">' + ( row.label || '' ) + '</th><td>' + ( row.value || '' ) + '</td></tr>';
					} );
					html += '</tbody></table>';
				} );
				body.innerHTML = html;
			} ).catch( function () { body.innerHTML = '<p class="rm-config-note">Could not load specification.</p>'; } );
		}
		if ( code ) { render( '' ); }
		document.addEventListener( 'ricoman:product', function ( e ) { code = ( e.detail && e.detail.code ) || code; render( '' ); } );
		document.addEventListener( 'ricoman:variant', function ( e ) { if ( e.detail && e.detail.sku ) { render( e.detail.sku ); } } );
	} )();
	</script>
	<?php
	return ob_get_clean();
} );

/* ---- Block: live accessories (follows the chosen product) ---- */
add_shortcode( 'ricoman_accessories_live', function ( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'ricoman_accessories_live' );
	$pid  = $atts['id'] ? (int) $atts['id'] : get_the_ID();
	if ( ! function_exists( 'ricoman_ricobot_ready' ) || ! ricoman_ricobot_ready() ) {
		return do_shortcode( '[ricoman_product_accessories]' );
	}
	$code  = $pid ? (string) get_post_meta( $pid, '_ricoman_sku', true ) : '';
	$nonce = wp_create_nonce( 'ricoman_rb_front' );
	$ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
	ob_start();
	?>
	<div class="rm-acc-live" data-code="<?php echo esc_attr( $code ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo $ajax; ?>">
		<div class="rm-acc-live-body"><?php echo $code ? '' : '<p class="rm-config-note">' . esc_html__( 'Choose a product above to see compatible accessories.', 'ricoman' ) . '</p>'; ?></div>
	</div>
	<script>
	( function () {
		var root = document.currentScript.previousElementSibling;
		var body = root.querySelector( '.rm-acc-live-body' );
		var nonce = root.dataset.nonce, ajax = root.dataset.ajax;
		var code = root.dataset.code || '';
		function render() {
			if ( ! code ) { return; }
			var b = new FormData(); b.append( 'action', 'ricoman_rb_specs' ); b.append( 'nonce', nonce ); b.append( 'code', code );
			fetch( ajax, { method: 'POST', body: b } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
				if ( ! res.success ) { body.innerHTML = ''; return; }
				var acc = res.data.accessories || [];
				if ( ! acc.length ) { body.innerHTML = ''; return; }
				var html = '<div class="rm-accessories"><h3 class="rm-shead">Compatible accessories (' + acc.length + ')</h3><table class="rm-acc-table"><tbody>';
				acc.forEach( function ( a ) { html += '<tr><td class="acc-code">' + ( a.code || '' ) + '</td><td class="acc-name">' + ( a.name || a.code || '' ) + '</td></tr>'; } );
				body.innerHTML = html + '</tbody></table></div>';
			} ).catch( function () {} );
		}
		if ( code ) { render(); }
		document.addEventListener( 'ricoman:product', function ( e ) { code = ( e.detail && e.detail.code ) || code; render(); } );
	} )();
	</script>
	<?php
	return ob_get_clean();
} );
