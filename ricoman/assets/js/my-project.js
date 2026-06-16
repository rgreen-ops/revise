/**
 * Ricoman — "My Project" specification list (Toolbox).
 *
 * A lightweight, dependency-free spec basket stored in localStorage. Specifiers
 * add products from anywhere on the site, review the list on the My Project page,
 * adjust quantities, then submit it as an enquiry (handled server-side).
 */
( function () {
	'use strict';

	var KEY = 'ricoman_my_project_v1';

	/* ---- storage helpers ------------------------------------------------ */
	function read() {
		try {
			return JSON.parse( window.localStorage.getItem( KEY ) ) || [];
		} catch ( e ) {
			return [];
		}
	}

	function write( list ) {
		window.localStorage.setItem( KEY, JSON.stringify( list ) );
		refreshCounts();
		renderList();
	}

	function count( list ) {
		return ( list || read() ).reduce( function ( n, item ) {
			return n + ( parseInt( item.qty, 10 ) || 1 );
		}, 0 );
	}

	function find( list, id ) {
		for ( var i = 0; i < list.length; i++ ) {
			if ( String( list[ i ].id ) === String( id ) ) {
				return i;
			}
		}
		return -1;
	}

	/* ---- mutations ------------------------------------------------------ */
	function add( item ) {
		var list = read();
		var i = find( list, item.id );
		if ( i === -1 ) {
			item.qty = 1;
			list.push( item );
		} else {
			list[ i ].qty = ( parseInt( list[ i ].qty, 10 ) || 1 ) + 1;
		}
		write( list );
	}

	function setQty( id, qty ) {
		var list = read();
		var i = find( list, id );
		if ( i !== -1 ) {
			list[ i ].qty = Math.max( 1, parseInt( qty, 10 ) || 1 );
			write( list );
		}
	}

	function remove( id ) {
		write(
			read().filter( function ( item ) {
				return String( item.id ) !== String( id );
			} )
		);
	}

	function clear() {
		write( [] );
	}

	/* ---- UI: header count badges --------------------------------------- */
	function refreshCounts() {
		var n = count();
		document.querySelectorAll( '[data-mp-count]' ).forEach( function ( el ) {
			el.textContent = n;
			el.setAttribute( 'data-empty', n === 0 ? 'true' : 'false' );
		} );
	}

	/* ---- UI: add buttons ------------------------------------------------ */
	function bindAddButtons() {
		document.querySelectorAll( '[data-add-to-project]' ).forEach( function ( btn ) {
			if ( btn.dataset.mpBound ) {
				return;
			}
			btn.dataset.mpBound = '1';
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				add( {
					id: btn.getAttribute( 'data-id' ),
					title: btn.getAttribute( 'data-title' ) || '',
					sku: btn.getAttribute( 'data-sku' ) || '',
					url: btn.getAttribute( 'data-url' ) || '',
				} );
				var original = btn.getAttribute( 'data-label' ) || btn.textContent;
				btn.setAttribute( 'data-label', original );
				btn.textContent = '✓ Added to My Project';
				btn.classList.add( 'is-added' );
				window.setTimeout( function () {
					btn.textContent = original;
					btn.classList.remove( 'is-added' );
				}, 1800 );
			} );
		} );
	}

	/* ---- UI: the My Project list --------------------------------------- */
	function renderList() {
		var host = document.querySelector( '[data-mp-list]' );
		if ( ! host ) {
			return;
		}
		var list = read();
		var hidden = document.querySelector( '[data-mp-items]' );
		if ( hidden ) {
			hidden.value = JSON.stringify( list );
		}

		var empty = document.querySelector( '[data-mp-empty]' );
		var panel = document.querySelector( '[data-mp-panel]' );
		if ( ! list.length ) {
			host.innerHTML = '';
			if ( empty ) {
				empty.hidden = false;
			}
			if ( panel ) {
				panel.hidden = true;
			}
			return;
		}
		if ( empty ) {
			empty.hidden = true;
		}
		if ( panel ) {
			panel.hidden = false;
		}

		var rows = list
			.map( function ( item ) {
				var title = escapeHtml( item.title );
				var sku = escapeHtml( item.sku );
				var url = encodeURI( item.url || '#' );
				return (
					'<tr>' +
					'<td class="mp-name"><a href="' + url + '">' + title + '</a>' +
					( sku ? '<span class="mp-sku">' + sku + '</span>' : '' ) +
					'</td>' +
					'<td class="mp-qty"><input type="number" min="1" value="' +
					( parseInt( item.qty, 10 ) || 1 ) +
					'" data-mp-qty="' + escapeAttr( item.id ) + '" aria-label="Quantity"></td>' +
					'<td class="mp-remove"><button type="button" data-mp-remove="' +
					escapeAttr( item.id ) + '" aria-label="Remove">×</button></td>' +
					'</tr>'
				);
			} )
			.join( '' );

		host.innerHTML =
			'<table class="ricoman-mp-table"><thead><tr><th>Product</th><th>Qty</th><th></th></tr></thead><tbody>' +
			rows +
			'</tbody></table>';

		host.querySelectorAll( '[data-mp-qty]' ).forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				setQty( input.getAttribute( 'data-mp-qty' ), input.value );
			} );
		} );
		host.querySelectorAll( '[data-mp-remove]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				remove( b.getAttribute( 'data-mp-remove' ) );
			} );
		} );
	}

	function escapeHtml( s ) {
		return String( s || '' ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function escapeAttr( s ) {
		return String( s || '' ).replace( /"/g, '&quot;' );
	}

	/* ---- wire up -------------------------------------------------------- */
	function init() {
		bindAddButtons();
		refreshCounts();
		renderList();

		var clearBtn = document.querySelector( '[data-mp-clear]' );
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				clear();
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Keep multiple tabs in sync.
	window.addEventListener( 'storage', function ( e ) {
		if ( e.key === KEY ) {
			refreshCounts();
			renderList();
		}
	} );
} )();
