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
			// Tell the live Specification / Accessories blocks which variant is built.
			document.dispatchEvent( new CustomEvent( 'ricoman:variant', { detail: { code: code, sku: sku || '' } } ) );
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

		// Announce the bound product so live Specification / Accessories blocks load it.
		document.dispatchEvent( new CustomEvent( 'ricoman:product', { detail: { code: code } } ) );
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

/* ---- Shortcode: the SPLIT HERO (live gallery preview + configurator) ----
 * Recreates the configurator preview hero: gallery/preview on the left, the
 * live build (axes → order code → spec table → downloads) on the right. */
add_shortcode( 'ricoman_configurator_hero', function ( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'ricoman_configurator_hero' );
	$pid  = $atts['id'] ? (int) $atts['id'] : get_the_ID();
	$code    = $pid ? (string) get_post_meta( $pid, '_ricoman_sku', true ) : '';
	$title   = $pid ? get_the_title( $pid ) : '';
	$tagline = $pid ? (string) get_post_meta( $pid, '_ricoman_tagline', true ) : '';
	$terms   = $pid ? get_the_term_list( $pid, 'product_cat', '', ' · ' ) : '';
	$hero    = $pid ? get_the_post_thumbnail_url( $pid, 'large' ) : '';
	if ( '' === $code || ! function_exists( 'ricoman_ricobot_ready' ) || ! ricoman_ricobot_ready() ) {
		// RICOBOT not connected: render a clean cover hero so the page still looks right.
		$ov = '<p class="rm-eyebrow has-base-color has-text-color">' . wp_kses_post( $terms ) . '</p>';
		$ov .= '<h1 class="wp-block-heading" style="font-weight:500;font-size:clamp(2.4rem,6vw,5rem);line-height:1">' . esc_html( $title ) . '</h1>';
		if ( $tagline ) {
			$ov .= '<p class="has-base-color has-text-color">' . esc_html( $tagline ) . '</p>';
		}
		$bg = $hero ? $hero : esc_url( get_theme_file_uri( 'assets/images/ceiling.webp' ) );
		return '<div class="wp-block-cover alignfull has-base-color has-text-color has-custom-content-position is-position-bottom-left" style="min-height:70vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-50 has-background-dim"></span><img class="wp-block-cover__image-background" alt="' . esc_attr( $title ) . '" src="' . $bg . '" data-object-fit="cover" fetchpriority="high"/><div class="wp-block-cover__inner-container">' . $ov . '</div></div>';
	}
	$nonce   = wp_create_nonce( 'ricoman_rb_front' );
	$ajax    = esc_url( admin_url( 'admin-ajax.php' ) );
	$enquire = esc_url( home_url( '/my-project/' ) );
	$hide    = $pid ? (string) get_post_meta( $pid, '_ricoman_hide_options', true ) : '';
	ob_start();
	?>
	<div class="rm-cfghero" data-code="<?php echo esc_attr( $code ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo $ajax; ?>" data-enquire="<?php echo $enquire; ?>" data-hide="<?php echo esc_attr( $hide ); ?>">
		<div class="rm-cfg-stage">
			<div class="rm-cfg-viz"><img class="rm-cfg-img" src="<?php echo esc_url( $hero ); ?>" alt="<?php echo esc_attr( $title ); ?>"><div class="rm-cfg-chips"></div><div class="rm-cfg-codeov"></div></div>
			<div class="rm-cfg-gtabs"><button type="button" class="on" data-set="all">All</button><button type="button" data-set="studio">Studio</button><button type="button" data-set="insitu">In-situ</button></div>
			<div class="rm-cfg-thumbs"></div>
		</div>
		<div class="rm-cfg-panel">
			<?php if ( $terms ) : ?><p class="rm-eyebrow"><?php echo wp_kses_post( $terms ); ?></p><?php endif; ?>
			<h1 class="rm-cfg-name"><?php echo esc_html( $title ); ?></h1>
			<?php if ( $tagline ) : ?><p class="rm-cfg-desc"><?php echo esc_html( $tagline ); ?></p><?php endif; ?>
			<div class="rm-config-axes"><p class="rm-config-loading">Loading configurator…</p></div>
			<div class="rm-cfg-result" hidden>
				<div class="rm-cfg-codebox"><div><span class="rm-cfg-lbl">Order code</span><span class="rm-cfg-code"></span></div></div>
				<table class="ricoman-spec-table rm-cfg-spec"><tbody></tbody></table>
			</div>
			<div class="rm-cfg-acts"></div>
			<div class="rm-cfg-dl"></div>
			<p class="rm-cfg-note">Order code, specification and downloads resolve live from RICOBOT for the exact configuration. Save builds to My Project and our team will follow up.</p>
		</div>
	</div>
	<script>
	( function () {
		var root = document.currentScript.previousElementSibling;
		var d = root.dataset, code = d.code, nonce = d.nonce, ajax = d.ajax, enquire = d.enquire;
		var hide = ( d.hide || '' ).toUpperCase().split( /[\s,]+/ ).filter( Boolean );
		var axesEl = root.querySelector( '.rm-config-axes' ), resEl = root.querySelector( '.rm-cfg-result' );
		var codeEl = root.querySelector( '.rm-cfg-code' ), specEl = root.querySelector( '.rm-cfg-spec tbody' );
		var actEl = root.querySelector( '.rm-cfg-acts' ), dlEl = root.querySelector( '.rm-cfg-dl' );
		var imgEl = root.querySelector( '.rm-cfg-img' ), chipsEl = root.querySelector( '.rm-cfg-chips' ), codeOv = root.querySelector( '.rm-cfg-codeov' );
		var thumbsEl = root.querySelector( '.rm-cfg-thumbs' ), gtabs = root.querySelectorAll( '.rm-cfg-gtabs button' );
		var axes = [], sel = {}, gallery = [], gset = 'all';
		function call( action, params ) { var b = new FormData(); b.append( 'action', action ); b.append( 'nonce', nonce ); Object.keys( params ).forEach( function ( k ) { if ( params[ k ] ) { b.append( k, params[ k ] ); } } ); return fetch( ajax, { method: 'POST', body: b } ).then( function ( r ) { return r.json(); } ); }
		function esc( s ) { return String( s == null ? '' : s ).replace( /[&<>"]/g, function ( c ) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[ c ]; } ); }
		function sku() { var parts = [ code ]; for ( var i = 0; i < axes.length; i++ ) { if ( ! sel[ i ] ) { return null; } parts.push( sel[ i ] ); } return parts.join( '/' ); }
		function renderThumbs() {
			var list = gallery.filter( function ( g ) { return 'all' === gset || ( g.type || 'studio' ) === gset; } );
			thumbsEl.innerHTML = '';
			list.forEach( function ( g, i ) { var b = document.createElement( 'button' ); b.type = 'button'; b.className = 'rm-cfg-thumb' + ( 0 === i ? ' on' : '' ); b.innerHTML = '<img src="' + esc( g.url ) + '" alt="" loading="lazy">'; b.addEventListener( 'click', function () { imgEl.src = g.url; thumbsEl.querySelectorAll( '.rm-cfg-thumb' ).forEach( function ( x ) { x.classList.remove( 'on' ); } ); b.classList.add( 'on' ); } ); thumbsEl.appendChild( b ); } );
			if ( list[ 0 ] ) { imgEl.src = list[ 0 ].url; }
		}
		function update() {
			var s = sku();
			chipsEl.innerHTML = Object.keys( sel ).map( function ( k ) { return '<span class="rm-cfg-chip">' + esc( sel[ k ] ) + '</span>'; } ).join( '' );
			codeOv.textContent = s || '';
			if ( ! s ) { resEl.hidden = true; return; }
			resEl.hidden = false; codeEl.textContent = s;
			actEl.innerHTML = '<a class="btn btn-solid" href="' + enquire + '?sku=' + encodeURIComponent( s ) + '">＋ Add to My Project</a> <a class="btn btn-line-d" href="/lighting-design/">Request a lighting design →</a>';
			call( 'ricoman_rb_specs', { code: code, sku: s } ).then( function ( res ) {
				if ( ! res.success ) { return; }
				var rows = '';
				( res.data.technicalData || [] ).forEach( function ( secn ) { ( secn.rows || [] ).forEach( function ( r ) { rows += '<tr><th scope="row">' + esc( r.label ) + '</th><td>' + esc( r.value ) + '</td></tr>'; } ); } );
				specEl.innerHTML = rows;
				var dl = '';
				( res.data.documents || [] ).forEach( function ( doc ) { if ( doc.url ) { dl += '<a href="' + esc( doc.url ) + '" target="_blank" rel="noopener">' + esc( doc.title || 'Datasheet' ) + ' (PDF) ↓</a>'; } } );
				( res.data.photometric || [] ).forEach( function ( ph ) { if ( ph.url ) { dl += '<a href="' + esc( ph.url ) + '" target="_blank" rel="noopener">Photometric (IES/LDT) ↓</a>'; } } );
				dlEl.innerHTML = dl;
				if ( res.data.gallery && res.data.gallery.length ) { gallery = res.data.gallery; renderThumbs(); }
				else if ( res.data.heroUrl && imgEl.src.indexOf( res.data.heroUrl ) === -1 ) { imgEl.src = res.data.heroUrl; }
			} ).catch( function () {} );
		}
		gtabs.forEach( function ( t ) { t.addEventListener( 'click', function () { gtabs.forEach( function ( x ) { x.classList.remove( 'on' ); } ); t.classList.add( 'on' ); gset = t.dataset.set; renderThumbs(); } ); } );
		document.dispatchEvent( new CustomEvent( 'ricoman:product', { detail: { code: code } } ) );
		call( 'ricoman_rb_config', { code: code } ).then( function ( res ) {
			if ( ! res.success || ! res.data || ! res.data.options ) { axesEl.innerHTML = '<p class="rm-config-note">Configurator unavailable.</p>'; return; }
			axes = res.data.options;
			axesEl.innerHTML = '';
			axes.forEach( function ( axis, i ) {
				var choices = ( axis.choices || [] ).filter( function ( ch ) { return hide.indexOf( String( ch.code ).toUpperCase() ) === -1; } );
				if ( ! choices.length ) { return; }
				var w = document.createElement( 'div' ); w.className = 'rm-axis';
				var h = document.createElement( 'div' ); h.className = 'rm-axis-label'; h.textContent = axis.label || axis.name || ( 'Option ' + ( i + 1 ) ); w.appendChild( h );
				var o = document.createElement( 'div' ); o.className = 'rm-axis-choices';
				choices.forEach( function ( ch ) { var b = document.createElement( 'button' ); b.type = 'button'; b.className = 'rm-choice'; b.textContent = ch.label || ch.code; if ( ch.isDefault ) { b.classList.add( 'on' ); sel[ i ] = ch.code; } b.addEventListener( 'click', function () { o.querySelectorAll( '.rm-choice' ).forEach( function ( x ) { x.classList.remove( 'on' ); } ); b.classList.add( 'on' ); sel[ i ] = ch.code; update(); } ); o.appendChild( b ); } );
				w.appendChild( o ); axesEl.appendChild( w );
			} );
			update();
		} ).catch( function () { axesEl.innerHTML = '<p class="rm-config-note">Could not load options.</p>'; } );
	} )();
	</script>
	<?php
	return ob_get_clean();
} );
