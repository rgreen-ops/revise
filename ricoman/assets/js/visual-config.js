/**
 * Visual configurator — tap-through option tiles that narrow to one variant.
 * Reads the embedded JSON (axes + variants) and renders steps + a result panel.
 */
( function () {
	'use strict';

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	function build( root ) {
		var dataEl = root.querySelector( '.rm-vcfg-data' );
		var stepsEl = root.querySelector( '.rm-vcfg-steps' );
		var resEl = root.querySelector( '.rm-vcfg-result' );
		if ( ! dataEl || ! stepsEl ) { return; }
		var data;
		try { data = JSON.parse( dataEl.textContent || '{}' ); } catch ( e ) { return; }
		var axes = data.axes || [], variants = data.variants || [];
		if ( ! axes.length || ! variants.length ) { return; }
		var fallback = root.getAttribute( 'data-fallback' ) || '';
		var sel = {}; // axisKey -> value

		function matches( ignoreKey ) {
			return variants.filter( function ( v ) {
				for ( var k in sel ) {
					if ( k === ignoreKey ) { continue; }
					if ( ( v.vals[ k ] || '' ) !== sel[ k ] ) { return false; }
				}
				return true;
			} );
		}
		function available( axisKey, value ) {
			// Nothing chosen yet → every option is open (don't grey the first step).
			var hasSel = false;
			for ( var s in sel ) { if ( s !== axisKey ) { hasSel = true; break; } }
			if ( ! hasSel ) { return true; }
			// Is there a variant with this option, given the OTHER selections?
			var pool = matches( axisKey );
			for ( var i = 0; i < pool.length; i++ ) {
				if ( ( pool[ i ].vals[ axisKey ] || '' ) === value ) { return true; }
			}
			return false;
		}

		function renderSteps() {
			var html = '';
			axes.forEach( function ( ax ) {
				html += '<div class="rm-vcfg-step" data-axis="' + esc( ax.key ) + '">';
				html += '<p class="rm-vcfg-label">' + esc( ax.label ) + ( sel[ ax.key ] ? ' <span class="rm-vcfg-chosen">' + esc( sel[ ax.key ] ) + '</span>' : '' ) + '</p>';
				html += '<div class="rm-vcfg-opts' + ( ax.image ? ' rm-vcfg-opts--img' : '' ) + '">';
				ax.options.forEach( function ( o ) {
					var on = sel[ ax.key ] === o.v;
					var ok = available( ax.key, o.v );
					var cls = 'rm-vcfg-opt' + ( on ? ' is-on' : '' ) + ( ok ? '' : ' is-off' );
					var inner = '';
					if ( ax.image && o.img ) {
						inner = '<span class="rm-vcfg-opt-img" style="background-image:url(' + encodeURI( o.img ) + ')"></span>';
					}
					inner += '<span class="rm-vcfg-opt-t">' + esc( o.v ) + '</span>';
					html += '<button type="button" class="' + cls + '" data-axis="' + esc( ax.key ) + '" data-val="' + esc( o.v ) + '"' + ( ok ? '' : ' disabled' ) + '>' + inner + '</button>';
				} );
				html += '</div></div>';
			} );
			stepsEl.innerHTML = html;
		}

		function renderResult() {
			var pool = matches();
			var chosenCount = Object.keys( sel ).length;
			// Live preview image = first matching variant with an image.
			var preview = '';
			for ( var i = 0; i < pool.length; i++ ) { if ( pool[ i ].img ) { preview = pool[ i ].img; break; } }

			if ( pool.length === 1 ) {
				var v = pool[ 0 ];
				var rows = '';
				Object.keys( v.specs || {} ).forEach( function ( k ) {
					rows += '<div class="rm-vcfg-spec"><dt>' + esc( k ) + '</dt><dd>' + esc( v.specs[ k ] ) + '</dd></div>';
				} );
				var dl = '';
				if ( v.ldt ) { dl += '<a class="btn btn-line" href="' + encodeURI( v.ldt ) + '" target="_blank" rel="noopener">LDT file ↓</a>'; }
				if ( v.ds ) { dl += '<a class="btn btn-solid" href="' + encodeURI( v.ds ) + '" target="_blank" rel="noopener">Download datasheet ↓</a>'; }
				resEl.innerHTML = '<div class="rm-vcfg-card">'
					+ ( ( v.img || fallback ) ? '<span class="rm-vcfg-cardimg" style="background-image:url(' + encodeURI( v.img || fallback ) + ')"></span>' : '' )
					+ '<div class="rm-vcfg-cardbody"><p class="rm-vcfg-eyebrow">Your configuration</p>'
					+ '<h3 class="rm-vcfg-code">' + esc( v.code ) + '</h3>'
					+ ( rows ? '<dl class="rm-vcfg-specs">' + rows + '</dl>' : '' )
					+ '<div class="rm-vcfg-acts">' + dl + '</div>'
					+ '<button type="button" class="rm-vcfg-reset">Start again</button>'
					+ '</div></div>';
				resEl.hidden = false;
			} else {
				resEl.innerHTML = '<div class="rm-vcfg-status">'
					+ ( preview ? '<span class="rm-vcfg-statusimg" style="background-image:url(' + encodeURI( preview ) + ')"></span>' : '' )
					+ '<p>' + pool.length + ' options match' + ( chosenCount ? '' : ' — start choosing above' ) + '.'
					+ ( chosenCount ? ' <button type="button" class="rm-vcfg-reset">Reset</button>' : '' ) + '</p></div>';
				resEl.hidden = false;
			}
		}

		function render() { renderSteps(); renderResult(); }

		root.addEventListener( 'click', function ( e ) {
			var reset = e.target.closest( '.rm-vcfg-reset' );
			if ( reset ) { sel = {}; render(); return; }
			var btn = e.target.closest( '.rm-vcfg-opt' );
			if ( ! btn || btn.disabled ) { return; }
			var ax = btn.getAttribute( 'data-axis' ), val = btn.getAttribute( 'data-val' );
			if ( sel[ ax ] === val ) { delete sel[ ax ]; } else { sel[ ax ] = val; }
			// Drop any now-impossible later selections.
			Object.keys( sel ).forEach( function ( k ) {
				if ( ! available( k, sel[ k ] ) ) { delete sel[ k ]; }
			} );
			render();
		} );

		render();
	}

	function init() { document.querySelectorAll( '.rm-vcfg' ).forEach( build ); }
	if ( document.readyState !== 'loading' ) { init(); } else { document.addEventListener( 'DOMContentLoaded', init ); }
} )();
