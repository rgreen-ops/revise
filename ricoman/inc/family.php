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
		$products[] = array(
			'code'       => isset( $p['code'] ) ? $p['code'] : '',
			'name'       => isset( $p['name'] ) ? $p['name'] : ( isset( $p['code'] ) ? $p['code'] : '' ),
			'attributes' => isset( $p['attributes'] ) ? $p['attributes'] : array(),
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
			<div class="rm-fam-facets"></div>
			<div class="rm-fam-meta"><span class="rm-fam-count"></span><button type="button" class="rm-fam-clear">Clear all filters</button></div>
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
		var facetsEl = root.querySelector( '.rm-fam-facets' );
		var countEl = root.querySelector( '.rm-fam-count' );
		var productsEl = root.querySelector( '.rm-fam-products' );
		var configureEl = root.querySelector( '.rm-fam-configure' );
		var clearBtn = root.querySelector( '.rm-fam-clear' );
		var facets = [], products = [], selected = {};

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

		function render() {
			// facet counts recomputed against the other active filters
			facetsEl.innerHTML = '';
			facets.forEach( function ( f ) {
				var row = document.createElement( 'div' ); row.className = 'rm-facet';
				var lab = document.createElement( 'div' ); lab.className = 'rm-facet-label'; lab.textContent = f.label; row.appendChild( lab );
				var chips = document.createElement( 'div' ); chips.className = 'rm-facet-chips';
				f.choices.forEach( function ( c ) {
					var n = products.filter( function ( p ) { return matches( p, f.key ) && String( ( p.attributes || {} )[ f.key ] ) === String( c.value ); } ).length;
					var b = document.createElement( 'button' ); b.type = 'button'; b.className = 'rm-fchip' + ( selected[ f.key ] === c.value ? ' on' : '' ) + ( n === 0 ? ' off' : '' );
					b.innerHTML = c.label + ' <span class="rm-fcount">' + n + '</span>';
					b.addEventListener( 'click', function () { selected[ f.key ] = ( selected[ f.key ] === c.value ) ? '' : c.value; render(); } );
					chips.appendChild( b );
				} );
				row.appendChild( chips ); facetsEl.appendChild( row );
			} );
			var m = matching();
			countEl.textContent = m.length + ' of ' + products.length + ' match';
			// matching products list
			productsEl.innerHTML = '<h3 class="rm-shead">Matching codes (' + m.length + ')</h3>';
			var ul = document.createElement( 'div' ); ul.className = 'rm-fam-list';
			m.forEach( function ( p ) {
				var a = document.createElement( 'button' ); a.type = 'button'; a.className = 'rm-fam-product';
				a.innerHTML = '<span class="fp-code">' + p.code + '</span><span class="fp-name">' + ( p.name || '' ) + '</span>';
				a.addEventListener( 'click', function () { loadConfigurator( p ); } );
				ul.appendChild( a );
			} );
			productsEl.appendChild( ul );
		}

		/* inline configurator for the chosen product (no pricing) */
		function loadConfigurator( p ) {
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
				var sku = build(); skuEl.textContent = sku || ''; if ( ! sku ) { resEl.hidden = true; return; } resEl.hidden = false; statusEl.textContent = '';
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

		clearBtn.addEventListener( 'click', function () { selected = {}; configureEl.innerHTML = ''; render(); } );

		call( 'ricoman_rb_family', { family: family } ).then( function ( res ) {
			if ( ! res.success ) { introEl.textContent = res.data || 'Range unavailable.'; return; }
			facets = res.data.facets || []; products = res.data.products || [];
			introEl.textContent = products.length + ' sizes / variants in this family. Filter below, then pick one to configure.';
			filterEl.hidden = false;
			render();
		} ).catch( function () { introEl.textContent = 'Could not load the range.'; } );
	} )();
	</script>
	<?php
	return ob_get_clean();
} );
