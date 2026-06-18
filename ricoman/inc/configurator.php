<?php
/**
 * Live product configurator (variants resolved on the fly).
 *
 * Products (the configurable units, e.g. R340501) are WordPress posts; their
 * millions of variants are NOT stored. Instead this:
 *   - reads the option axes from  GET /api/public/products/{code}   (cached 1h),
 *   - lets the customer pick options to build a full SKU in the browser,
 *   - resolves price + datasheet from  GET /api/public/price?sku=…  live.
 * Both calls are proxied server-side (token stays server-side). Per Richard:
 * priced/poa responses are cached 1h; qty-dependent are never cached.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---- Proxy: option axes for a product (cached 1h) ---- */
function ricoman_rb_config_cb() {
	check_ajax_referer( 'ricoman_rb_front', 'nonce' );
	$code = isset( $_REQUEST['code'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', wp_unslash( $_REQUEST['code'] ) ) : '';
	if ( '' === $code ) {
		wp_send_json_error( 'No product code.' );
	}
	if ( ! function_exists( 'ricoman_ricobot_get' ) ) {
		wp_send_json_error( 'RICOBOT unavailable.' );
	}
	$data = ricoman_ricobot_get( 'api/public/products/' . rawurlencode( $code ) ); // cached 1h
	if ( is_wp_error( $data ) ) {
		wp_send_json_error( $data->get_error_message() );
	}
	wp_send_json_success( $data );
}
add_action( 'wp_ajax_ricoman_rb_config', 'ricoman_rb_config_cb' );
add_action( 'wp_ajax_nopriv_ricoman_rb_config', 'ricoman_rb_config_cb' );

/* ---- Proxy: price for a full SKU (cache priced/poa only) ---- */
function ricoman_rb_price_cb() {
	check_ajax_referer( 'ricoman_rb_front', 'nonce' );
	$sku = isset( $_REQUEST['sku'] ) ? preg_replace( '#[^A-Za-z0-9_\-/]#', '', wp_unslash( $_REQUEST['sku'] ) ) : '';
	if ( '' === $sku ) {
		wp_send_json_error( 'No SKU.' );
	}
	if ( ! function_exists( 'ricoman_ricobot_get' ) ) {
		wp_send_json_error( 'RICOBOT unavailable.' );
	}
	$key    = 'ricoman_price_' . md5( $sku );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		wp_send_json_success( $cached );
	}
	$data = ricoman_ricobot_get( 'api/public/price?sku=' . $sku, true ); // bypass generic cache; manage here
	if ( is_wp_error( $data ) ) {
		wp_send_json_error( $data->get_error_message() );
	}
	$status = isset( $data['status'] ) ? $data['status'] : '';
	if ( in_array( $status, array( 'priced', 'poa' ), true ) ) {
		set_transient( $key, $data, HOUR_IN_SECONDS );
	}
	wp_send_json_success( $data );
}
add_action( 'wp_ajax_ricoman_rb_price', 'ricoman_rb_price_cb' );
add_action( 'wp_ajax_nopriv_ricoman_rb_price', 'ricoman_rb_price_cb' );

/* ---- Shortcode: the configurator UI ---- */
add_shortcode( 'ricoman_configurator', function ( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'ricoman_configurator' );
	$pid  = $atts['id'] ? (int) $atts['id'] : get_the_ID();
	$code = $pid ? (string) get_post_meta( $pid, '_ricoman_sku', true ) : '';
	if ( '' === $code || ! function_exists( 'ricoman_ricobot_ready' ) || ! ricoman_ricobot_ready() ) {
		return ''; // Nothing to configure / not connected.
	}
	$nonce   = wp_create_nonce( 'ricoman_rb_front' );
	$ajax    = esc_url( admin_url( 'admin-ajax.php' ) );
	$enquire = esc_url( home_url( '/my-project/' ) );
	ob_start();
	?>
	<h2 class="wp-block-heading rm-shead has-large-font-size">Configure</h2>
	<div class="rm-config" data-code="<?php echo esc_attr( $code ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo $ajax; ?>" data-enquire="<?php echo $enquire; ?>" data-hide="<?php echo esc_attr( (string) get_post_meta( $pid, '_ricoman_hide_options', true ) ); ?>">
		<div class="rm-config-axes"><p class="rm-config-loading">Loading configurator…</p></div>
		<div class="rm-config-result" hidden>
			<div class="rm-config-sku"></div>
			<div class="rm-config-status rm-config-note"></div>
			<div class="rm-config-actions"></div>
		</div>
	</div>
	<script>
	( function () {
		var root = document.currentScript.previousElementSibling;
		var axesEl = root.querySelector( '.rm-config-axes' );
		var resEl = root.querySelector( '.rm-config-result' );
		var skuEl = root.querySelector( '.rm-config-sku' );
		var statusEl = root.querySelector( '.rm-config-status' );
		var actEl = root.querySelector( '.rm-config-actions' );
		var code = root.dataset.code, nonce = root.dataset.nonce, ajax = root.dataset.ajax, enquire = root.dataset.enquire;
		var hide = ( root.dataset.hide || '' ).toUpperCase().split( /[\s,]+/ ).filter( Boolean );
		var axes = [], selected = {};

		function call( action, params ) {
			var b = new FormData();
			b.append( 'action', action ); b.append( 'nonce', nonce );
			Object.keys( params ).forEach( function ( k ) { b.append( k, params[ k ] ); } );
			return fetch( ajax, { method: 'POST', body: b } ).then( function ( r ) { return r.json(); } );
		}
		function buildSku() {
			var parts = [ code ];
			for ( var i = 0; i < axes.length; i++ ) { if ( ! selected[ i ] ) { return null; } parts.push( selected[ i ] ); }
			return parts.join( '/' );
		}

		// No prices on the website — resolve only the SKU, datasheet and validity.
		function renderResult() {
			var sku = buildSku();
			skuEl.textContent = sku ? sku : '';
			if ( ! sku ) { resEl.hidden = true; return; }
			resEl.hidden = false;
			statusEl.textContent = '';
			actEl.innerHTML = '<a class="btn btn-solid" href="' + enquire + '?sku=' + encodeURIComponent( sku ) + '">Add to My Project</a> <a class="btn btn-line-d" href="' + enquire + '?sku=' + encodeURIComponent( sku ) + '">Request a quote</a>';
			call( 'ricoman_rb_price', { sku: sku } ).then( function ( res ) {
				if ( ! res.success ) { return; }
				var d = res.data, s = d.status;
				if ( s === 'priced' || s === 'poa' || s === 'qty-dependent' ) {
					if ( d.datasheetUrl ) {
						actEl.insertAdjacentHTML( 'afterbegin', '<a class="btn btn-line-d" target="_blank" rel="noopener" href="' + d.datasheetUrl + '">Datasheet (PDF)</a> ' );
					}
				} else if ( s === 'configurator-partial' ) {
					statusEl.textContent = 'Select all options.';
				} else {
					statusEl.textContent = 'This combination isn\'t available — please enquire.';
				}
			} ).catch( function () {} );
		}

		function renderAxes() {
			axesEl.innerHTML = '';
			var visible = 0;
			axes.forEach( function ( axis, i ) {
				var choices = ( axis.choices || [] ).filter( function ( ch ) { return hide.indexOf( String( ch.code ).toUpperCase() ) === -1; } );
				if ( ! choices.length ) { return; } // hidden entirely
				var wrap = document.createElement( 'div' ); wrap.className = 'rm-axis';
				var h = document.createElement( 'div' ); h.className = 'rm-axis-label'; h.textContent = ( axis.label || axis.name || ( 'Option ' + ( i + 1 ) ) ) + ( axis.optional ? '' : '' ); wrap.appendChild( h );
				var opts = document.createElement( 'div' ); opts.className = 'rm-axis-choices';
				choices.forEach( function ( ch ) {
					var b = document.createElement( 'button' ); b.type = 'button'; b.className = 'rm-choice'; b.textContent = ch.label || ch.code;
					if ( ch.isDefault ) { b.classList.add( 'on' ); selected[ i ] = ch.code; }
					b.addEventListener( 'click', function () {
						opts.querySelectorAll( '.rm-choice' ).forEach( function ( x ) { x.classList.remove( 'on' ); } );
						b.classList.add( 'on' ); selected[ i ] = ch.code; renderResult();
					} );
					opts.appendChild( b );
				} );
				wrap.appendChild( opts ); axesEl.appendChild( wrap ); visible++;
			} );
			renderResult();
		}

		call( 'ricoman_rb_config', { code: code } ).then( function ( res ) {
			if ( ! res.success ) { axesEl.innerHTML = '<p class="rm-config-note">Configurator unavailable.</p>'; return; }
			axes = ( res.data && res.data.options ) ? res.data.options : [];
			if ( ! axes.length ) { axesEl.innerHTML = ''; return; }
			renderAxes();
		} ).catch( function () { axesEl.innerHTML = '<p class="rm-config-note">Could not load options.</p>'; } );
	} )();
	</script>
	<?php
	return ob_get_clean();
} );
