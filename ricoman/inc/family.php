<?php
/**
 * Family page — filter facets → matching products → pick → configure.
 *
 * Mirrors the live RICOBOT family page. Uses GET /api/public/families/{family}
 * when available; otherwise falls back to grouping /api/public/products by the
 * `family` field and deriving facets from each product's `attributes`. Nothing
 * variant-level is stored; everything is proxied server-side and cached.
 *
 * Use:  [ricoman_family family="Estrella"]
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---- Proxy: family detail (dedicated endpoint, else grouped fallback) ---- */
add_action( 'wp_ajax_ricoman_rb_family', 'ricoman_rb_family_cb' );
add_action( 'wp_ajax_nopriv_ricoman_rb_family', 'ricoman_rb_family_cb' );
function ricoman_rb_family_cb() {
	check_ajax_referer( 'ricoman_rb_front', 'nonce' );
	$family = isset( $_REQUEST['family'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['family'] ) ) : '';
	if ( '' === $family || ! function_exists( 'ricoman_ricobot_ready' ) || ! ricoman_ricobot_ready() ) {
		wp_send_json_error( 'Not available.' );
	}

	// 1) dedicated endpoint
	$data = ricoman_ricobot_get( 'api/public/families/' . rawurlencode( $family ) );
	if ( ! is_wp_error( $data ) && ! empty( $data['products'] ) ) {
		if ( function_exists( 'ricoman_rb_proxy_url' ) ) {
			foreach ( $data['products'] as &$pp ) {
				if ( isset( $pp['heroUrl'] ) ) {
					$pp['heroUrl'] = ricoman_rb_proxy_url( $pp['heroUrl'] );
				}
			}
			unset( $pp );
		}
		wp_send_json_success( $data );
	}

	// 2) fallback: group the product catalogue by family
	$all   = ricoman_ricobot_all_products();
	$prods = array();
	foreach ( $all as $p ) {
		if ( isset( $p['family'] ) && 0 === strcasecmp( (string) $p['family'], $family ) ) {
			$prods[] = $p;
		}
	}
	if ( ! $prods ) {
		wp_send_json_error( 'No products found for this family.' );
	}

	$facetmap = array(); // key => [ value => count ]
	foreach ( $prods as $p ) {
		$attrs = isset( $p['attributes'] ) && is_array( $p['attributes'] ) ? $p['attributes'] : array();
		foreach ( $attrs as $k => $v ) {
			$v = (string) $v;
			if ( '' === $v ) {
				continue;
			}
			if ( ! isset( $facetmap[ $k ][ $v ] ) ) {
				$facetmap[ $k ][ $v ] = 0;
			}
			$facetmap[ $k ][ $v ]++;
		}
	}
	$facets = array();
	foreach ( $facetmap as $key => $values ) {
		$choices = array();
		foreach ( $values as $value => $count ) {
			$choices[] = array( 'value' => $value, 'label' => ucwords( str_replace( array( '-', '_' ), ' ', $value ) ), 'count' => $count );
		}
		$facets[] = array( 'key' => $key, 'label' => ucwords( str_replace( array( '-', '_' ), ' ', $key ) ), 'choices' => $choices );
	}
	$products = array();
	foreach ( $prods as $p ) {
		$hero = isset( $p['heroUrl'] ) ? $p['heroUrl'] : ( isset( $p['image'] ) ? $p['image'] : '' );
		$products[] = array(
			'code'       => isset( $p['code'] ) ? $p['code'] : '',
			'name'       => isset( $p['name'] ) ? $p['name'] : ( isset( $p['code'] ) ? $p['code'] : '' ),
			'attributes' => isset( $p['attributes'] ) ? $p['attributes'] : array(),
			'heroUrl'    => ( $hero && function_exists( 'ricoman_rb_proxy_url' ) ) ? ricoman_rb_proxy_url( $hero ) : $hero,
		);
	}
	wp_send_json_success(
		array(
			'family'   => $family,
			'category' => isset( $prods[0]['category'] ) ? $prods[0]['category'] : '',
			'count'    => count( $products ),
			'facets'   => $facets,
			'products' => $products,
		)
	);
}

/* ---- Shortcode: the family filter UI ---- */
add_shortcode( 'ricoman_family', function ( $atts ) {
	$atts   = shortcode_atts( array( 'family' => '' ), $atts, 'ricoman_family' );
	$family = trim( (string) $atts['family'] );
	// Fall back to the family linked to the current product in the Product Builder,
	// so a bare [ricoman_family] on any product page lists that product's range.
	if ( '' === $family ) {
		$pid = get_the_ID();
		if ( $pid ) {
			$family = trim( (string) get_post_meta( $pid, '_ricoman_family', true ) );
		}
	}
	if ( '' === $family ) {
		return '';
	}
	$nonce   = wp_create_nonce( 'ricoman_rb_front' );
	$ajax    = esc_url( admin_url( 'admin-ajax.php' ) );
	$enquire = esc_url( home_url( '/my-project/' ) );
	ob_start();
	?>
	<div class="rm-fam" data-family="<?php echo esc_attr( $family ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo $ajax; ?>" data-enquire="<?php echo $enquire; ?>">
		<p class="rm-fam-intro">Loading the <?php echo esc_html( $family ); ?> range…</p>
		<div class="rm-fam-filter" hidden>
			<div class="rm-vf-row"></div>
		</div>
		<div class="rm-fam-products"></div>
		<div class="rm-fam-configure"></div>
	</div>
	<script>
	( function () {
		var root = document.currentScript.previousElementSibling;
		var nonce = root.dataset.nonce, ajax = root.dataset.ajax, enquire = root.dataset.enquire, family = root.dataset.family;
		var introEl = root.querySelector( '.rm-fam-intro' );
		var filterEl = root.querySelector( '.rm-fam-filter' );
		var filtersEl = root.querySelector( '.rm-vf-row' );
		var productsEl = root.querySelector( '.rm-fam-products' );
		var configureEl = root.querySelector( '.rm-fam-configure' );
		var facets = [], products = [], selected = {}, expanded = false, LIMIT = 6;

		function call( action, params ) {
			var b = new FormData(); b.append( 'action', action ); b.append( 'nonce', nonce );
			Object.keys( params ).forEach( function ( k ) { b.append( k, params[ k ] ); } );
			return fetch( ajax, { method: 'POST', body: b } ).then( function ( r ) { return r.json(); } );
		}
		function matches( p, skipKey ) {
			var a = p.attributes || {};
			for ( var k in selected ) { if ( k !== skipKey && selected[ k ] && String( a[ k ] ) !== String( selected[ k ] ) ) { return false; } }
			return true;
		}
		function matching() { return products.filter( function ( p ) { return matches( p, null ); } ); }
		function esc( s ) { return String( s == null ? '' : s ).replace( /[&<>"]/g, function ( c ) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[ c ]; } ); }
		function attr( a, keys ) { for ( var i = 0; i < keys.length; i++ ) { if ( a[ keys[ i ] ] != null && a[ keys[ i ] ] !== '' ) { return a[ keys[ i ] ]; } } return ''; }
		function descOf( p ) {
			if ( p.description ) { return p.description; }
			var a = p.attributes || {}, parts = [];
			[ 'length', 'size', 'optic', 'shape', 'bodyColour', 'finish', 'cct', 'kelvin', 'control', 'dimming' ].forEach( function ( k ) { if ( a[ k ] ) { parts.push( a[ k ] ); } } );
			return parts.length ? parts.join( ' · ' ) : ( p.name || '' );
		}

		function render() {
			// Dropdown filters (Length / Body Colour / Colour Temperature …).
			filtersEl.innerHTML = '';
			facets.forEach( function ( f ) {
				var wrap = document.createElement( 'div' ); wrap.className = 'rm-vf';
				var lab = document.createElement( 'label' ); lab.className = 'rm-vf-label'; lab.textContent = f.label; wrap.appendChild( lab );
				var sel = document.createElement( 'select' ); sel.className = 'rm-vf-sel';
				var all = document.createElement( 'option' ); all.value = ''; all.textContent = 'All'; sel.appendChild( all );
				f.choices.forEach( function ( c ) {
					var o = document.createElement( 'option' ); o.value = c.value; o.textContent = c.label;
					if ( selected[ f.key ] === c.value ) { o.selected = true; }
					sel.appendChild( o );
				} );
				sel.addEventListener( 'change', function () { selected[ f.key ] = sel.value; expanded = false; render(); } );
				wrap.appendChild( sel ); filtersEl.appendChild( wrap );
			} );

			// Variant table (Part code · Description · Lumens · Dimensions · actions).
			var m = matching();
			var shown = expanded ? m : m.slice( 0, LIMIT );
			var rows = shown.map( function ( p ) {
				var a = p.attributes || {};
				var lum = attr( a, [ 'lumens', 'output', 'lm' ] );
				var dim = attr( a, [ 'dimensions', 'size', 'dimension' ] );
				var thumb = p.heroUrl ? '<img src="' + esc( p.heroUrl ) + '" alt="" loading="lazy">' : '<span class="rm-vt-noimg"></span>';
				return '<tr>' +
					'<td class="vt-thumb">' + thumb + '</td>' +
					'<td class="vt-code"><button type="button" class="rm-vt-view" data-code="' + esc( p.code ) + '">' + esc( p.code ) + '</button></td>' +
					'<td class="vt-desc">' + esc( descOf( p ) ) + '</td>' +
					'<td class="vt-num">' + esc( lum ) + '</td>' +
					'<td class="vt-num">' + esc( dim ) + '</td>' +
					'<td class="vt-act"><button type="button" class="rm-vt-view rm-vt-ico" title="View &amp; configure" data-code="' + esc( p.code ) + '" aria-label="View">&#128065;</button></td>' +
					'<td class="vt-act"><a class="rm-vt-ico" title="Add to My Project" aria-label="Add" href="' + enquire + '?sku=' + encodeURIComponent( p.code ) + '">&#65291;</a></td>' +
					'<td class="vt-act"><button type="button" class="rm-vt-doc rm-vt-ico" data-kind="ldt" data-code="' + esc( p.code ) + '" title="Photometric (LDT)" aria-label="LDT">&#8615;</button></td>' +
					'<td class="vt-act"><button type="button" class="rm-vt-doc rm-vt-ico" data-kind="datasheet" data-code="' + esc( p.code ) + '" title="Datasheet (PDF)" aria-label="Datasheet">&#8615;</button></td>' +
					'</tr>';
			} ).join( '' );

			productsEl.innerHTML =
				'<div class="rm-vt-wrap"><table class="rm-vtable"><thead><tr>' +
				'<th></th><th>Part Code</th><th>Description</th><th>Lumens</th><th>Dimensions</th>' +
				'<th>View</th><th>Add</th><th>LDT</th><th>Datasheet</th>' +
				'</tr></thead><tbody>' + ( rows || '<tr><td colspan="9" class="rm-vt-empty">No variants match these filters.</td></tr>' ) + '</tbody></table></div>' +
				( m.length > LIMIT ? '<button type="button" class="rm-vt-more">' + ( expanded ? 'Show fewer' : 'View all variants (' + m.length + ') ▾' ) + '</button>' : '' );

			productsEl.querySelectorAll( '.rm-vt-view' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () { loadConfigurator( { code: b.dataset.code } ); } );
			} );
			productsEl.querySelectorAll( '.rm-vt-doc' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () { openDoc( b.dataset.code, b.dataset.kind, b ); } );
			} );
			var more = productsEl.querySelector( '.rm-vt-more' );
			if ( more ) { more.addEventListener( 'click', function () { expanded = ! expanded; render(); } ); }
		}

		// Resolve a datasheet / photometric link on demand and open it.
		function openDoc( code, kind, btn ) {
			btn.classList.add( 'is-loading' );
			call( 'ricoman_rb_specs', { code: code } ).then( function ( res ) {
				btn.classList.remove( 'is-loading' );
				if ( ! res.success ) { return; }
				var url = '';
				if ( 'datasheet' === kind ) {
					( res.data.documents || [] ).some( function ( d ) { if ( ( d.type || '' ).toLowerCase().indexOf( 'datasheet' ) > -1 || /\.pdf/i.test( d.url || '' ) ) { url = d.url; return true; } return false; } );
				} else {
					var ph = res.data.photometric || [];
					if ( ph.length ) { url = ph[ 0 ].url; }
				}
				if ( url ) { window.open( url, '_blank', 'noopener' ); }
				else { btn.classList.add( 'rm-vt-na' ); btn.title = 'Not published — please enquire'; }
			} ).catch( function () { btn.classList.remove( 'is-loading' ); } );
		}

		/* inline configurator for the chosen product (no pricing) */
		function loadConfigurator( p ) {
			// Tell live Specification / Accessories blocks which product was chosen.
			document.dispatchEvent( new CustomEvent( 'ricoman:product', { detail: { code: p.code, name: p.name || '' } } ) );
			configureEl.innerHTML = '<div class="rm-config"><h3 class="rm-shead">Build a variant — ' + p.code + '</h3><div class="rm-config-axes"><p class="rm-config-loading">Loading options…</p></div><div class="rm-config-result" hidden><div class="rm-config-sku"></div><div class="rm-config-status rm-config-note"></div><div class="rm-config-actions"></div></div></div>';
			configureEl.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			var axesEl = configureEl.querySelector( '.rm-config-axes' );
			var resEl = configureEl.querySelector( '.rm-config-result' );
			var skuEl = configureEl.querySelector( '.rm-config-sku' );
			var statusEl = configureEl.querySelector( '.rm-config-status' );
			var actEl = configureEl.querySelector( '.rm-config-actions' );
			var axes = [], sel = {};
			function build() { var parts = [ p.code ]; for ( var i = 0; i < axes.length; i++ ) { if ( ! sel[ i ] ) { return null; } parts.push( sel[ i ] ); } return parts.join( '/' ); }
			function result() {
				var sku = build(); skuEl.textContent = sku || '';
				document.dispatchEvent( new CustomEvent( 'ricoman:variant', { detail: { code: p.code, sku: sku || '' } } ) );
				if ( ! sku ) { resEl.hidden = true; return; } resEl.hidden = false; statusEl.textContent = '';
				actEl.innerHTML = '<a class="btn btn-solid" href="' + enquire + '?sku=' + encodeURIComponent( sku ) + '">Add to My Project</a> <a class="btn btn-line-d" href="' + enquire + '?sku=' + encodeURIComponent( sku ) + '">Request a quote</a>';
				call( 'ricoman_rb_price', { sku: sku } ).then( function ( res ) {
					if ( ! res.success ) { return; } var d = res.data, s = d.status;
					if ( ( s === 'priced' || s === 'poa' || s === 'qty-dependent' ) && d.datasheetUrl ) { actEl.insertAdjacentHTML( 'afterbegin', '<a class="btn btn-line-d" target="_blank" rel="noopener" href="' + d.datasheetUrl + '">Datasheet (PDF)</a> ' ); }
					else if ( s === 'configurator-partial' ) { statusEl.textContent = 'Select all options.'; }
					else if ( s && s !== 'priced' && s !== 'poa' && s !== 'qty-dependent' ) { statusEl.textContent = 'This combination isn\'t available — please enquire.'; }
				} ).catch( function () {} );
			}
			call( 'ricoman_rb_config', { code: p.code } ).then( function ( res ) {
				if ( ! res.success ) { axesEl.innerHTML = '<p class="rm-config-note">Options unavailable.</p>'; return; }
				axes = ( res.data && res.data.options ) ? res.data.options : [];
				axesEl.innerHTML = '';
				axes.forEach( function ( axis, i ) {
					var w = document.createElement( 'div' ); w.className = 'rm-axis';
					var h = document.createElement( 'div' ); h.className = 'rm-axis-label'; h.textContent = axis.label || axis.name || ( 'Option ' + ( i + 1 ) ); w.appendChild( h );
					var o = document.createElement( 'div' ); o.className = 'rm-axis-choices';
					( axis.choices || [] ).forEach( function ( ch ) {
						var b = document.createElement( 'button' ); b.type = 'button'; b.className = 'rm-choice'; b.textContent = ch.label || ch.code;
						if ( ch.isDefault ) { b.classList.add( 'on' ); sel[ i ] = ch.code; }
						b.addEventListener( 'click', function () { o.querySelectorAll( '.rm-choice' ).forEach( function ( x ) { x.classList.remove( 'on' ); } ); b.classList.add( 'on' ); sel[ i ] = ch.code; result(); } );
						o.appendChild( b );
					} );
					w.appendChild( o ); axesEl.appendChild( w );
				} );
				result();
			} ).catch( function () { axesEl.innerHTML = '<p class="rm-config-note">Could not load options.</p>'; } );
		}

		call( 'ricoman_rb_family', { family: family } ).then( function ( res ) {
			if ( ! res.success || ! ( res.data && ( res.data.products || [] ).length ) ) {
				introEl.innerHTML = 'The full range for this product is being set up. <a href="/contact/">Contact us</a> for the exact order codes and a quote.';
				return;
			}
			facets = res.data.facets || []; products = res.data.products || [];
			introEl.textContent = products.length + ' sizes &amp; variants — filter, then View to configure or download.';
			filterEl.hidden = false;
			render();
		} ).catch( function () { introEl.textContent = 'Could not load the range.'; } );
	} )();
	</script>
	<?php
	return ob_get_clean();
} );

/* ---- Shortcode: "Why specify {family}" — selling points, data-driven ----
 * Renders res.sellingPoints[] from GET /api/public/families/{slug} as a 3-col
 * grid (1-col on mobile). Hidden entirely when the array is empty (not yet
 * filled in for that family). Icons are Lucide names mapped to inline SVG. */
add_shortcode( 'ricoman_selling_points', function ( $atts ) {
	$atts   = shortcode_atts( array( 'family' => '' ), $atts, 'ricoman_selling_points' );
	$family = trim( (string) $atts['family'] );
	if ( '' === $family ) {
		$pid = get_the_ID();
		if ( $pid ) {
			$family = trim( (string) get_post_meta( $pid, '_ricoman_family', true ) );
		}
	}
	if ( '' === $family || ! function_exists( 'ricoman_ricobot_ready' ) || ! ricoman_ricobot_ready() ) {
		return '';
	}
	$nonce = wp_create_nonce( 'ricoman_rb_front' );
	$ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
	ob_start();
	?>
	<section class="rm-sp" data-family="<?php echo esc_attr( $family ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo $ajax; ?>" hidden>
		<div class="rm-sp-inner"><p class="rm-eyebrow rm-sp-kick"></p><div class="rm-sp-grid"></div></div>
	</section>
	<script>
	( function () {
		var root = document.currentScript.previousElementSibling;
		var family = root.dataset.family, nonce = root.dataset.nonce, ajax = root.dataset.ajax;
		var kick = root.querySelector( '.rm-sp-kick' ), grid = root.querySelector( '.rm-sp-grid' );
		// Lucide icon paths (24x24, currentColor stroke).
		var ICONS = {
			sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
			clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
			home: '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
			shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
			activity: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
			zap: '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
			award: '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
			leaf: '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/>',
			settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
			wrench: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
			lightbulb: '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/>',
			ruler: '<path d="M21.3 8.7 8.7 21.3a1 1 0 0 1-1.4 0l-4.6-4.6a1 1 0 0 1 0-1.4L15.3 2.7a1 1 0 0 1 1.4 0l4.6 4.6a1 1 0 0 1 0 1.4Z"/><path d="m7.5 10.5 2 2M10.5 7.5l2 2M13.5 4.5l2 2M4.5 13.5l2 2"/>'
		};
		function esc( s ) { return String( s == null ? '' : s ).replace( /[&<>"]/g, function ( c ) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[ c ]; } ); }
		function svg( name ) { var p = ICONS[ name ] || ICONS.lightbulb; return '<svg class="ic" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>'; }
		var b = new FormData(); b.append( 'action', 'ricoman_rb_family' ); b.append( 'nonce', nonce ); b.append( 'family', family );
		fetch( ajax, { method: 'POST', body: b } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
			if ( ! res.success ) { return; }
			var pts = ( res.data && res.data.sellingPoints ) || [];
			if ( ! pts.length ) { return; } // empty = not filled in yet -> stay hidden
			kick.textContent = 'Why specify ' + ( res.data.family || family );
			grid.innerHTML = pts.map( function ( pt ) {
				return '<div class="rm-sp-card">' + svg( pt.icon ) + '<h4>' + esc( pt.title ) + '</h4><p>' + esc( pt.body ) + '</p></div>';
			} ).join( '' );
			root.hidden = false;
		} ).catch( function () {} );
	} )();
	</script>
	<?php
	return ob_get_clean();
} );
