/**
 * Saved project lists (logged-in customers).
 *
 * Drives the projects manager on the My Project page (create / rename / delete /
 * switch / qty / remove) and routes the product "Add to My Project" buttons to
 * the server-side active project instead of the guest localStorage list.
 */
( function () {
	'use strict';
	var P = window.rmProj || {};
	if ( ! P.ajax ) { return; }

	function setCount( n ) {
		document.querySelectorAll( '[data-mp-count]' ).forEach( function ( el ) {
			el.textContent = n;
			el.setAttribute( 'data-empty', n > 0 ? 'false' : 'true' );
		} );
	}

	function post( params, done ) {
		var body = new URLSearchParams( Object.assign( { action: 'rm_proj', nonce: P.nonce || '' }, params ) );
		return fetch( P.ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				if ( j && j.success ) {
					if ( typeof j.data.count !== 'undefined' ) { setCount( j.data.count ); }
					if ( done ) { done( j.data ); }
				}
				return j;
			} );
	}

	function swap( data ) {
		var mgr = document.querySelector( '.rm-projmgr' );
		if ( mgr && data && data.html ) {
			mgr.outerHTML = data.html;
		}
	}

	// ---- Manager controls (delegated; the fragment is replaced on each op) ----
	document.addEventListener( 'click', function ( e ) {
		var sw = e.target.closest( '[data-proj-switch]' );
		if ( sw ) { post( { op: 'switch', pid: sw.getAttribute( 'data-proj-switch' ) }, swap ); return; }

		if ( e.target.closest( '[data-proj-new]' ) ) {
			var name = window.prompt( 'Name this project:', '' );
			if ( name !== null ) { post( { op: 'create', name: name || 'Untitled project' }, swap ); }
			return;
		}
		var del = e.target.closest( '[data-proj-delete]' );
		if ( del ) {
			if ( window.confirm( 'Delete this project? This cannot be undone.' ) ) { post( { op: 'delete', pid: del.getAttribute( 'data-proj-delete' ) }, swap ); }
			return;
		}
		var rm = e.target.closest( '[data-proj-remove]' );
		if ( rm ) { post( { op: 'remove', product: rm.getAttribute( 'data-proj-remove' ) }, swap ); return; }
	} );

	document.addEventListener( 'change', function ( e ) {
		var q = e.target.closest( '[data-proj-qty]' );
		if ( q ) { post( { op: 'qty', product: q.getAttribute( 'data-proj-qty' ), qty: q.value }, swap ); return; }
		var rn = e.target.closest( '[data-proj-rename]' );
		if ( rn ) {
			var mgr = rn.closest( '.rm-projmgr' );
			post( { op: 'rename', pid: mgr ? mgr.getAttribute( 'data-active' ) : '', name: rn.value }, swap );
		}
	} );

	// ---- "Add to My Project" → active server project (capture phase so the guest
	//      localStorage handler never fires for logged-in users). ----
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-add-to-project], .ricoman-add-project' );
		if ( ! btn ) { return; }
		e.preventDefault();
		e.stopImmediatePropagation();
		var id = btn.getAttribute( 'data-id' );
		if ( ! id ) { return; }
		var label = btn.textContent;
		post( { op: 'add', product: id }, function ( data ) {
			btn.textContent = '✓ Added to project';
			setTimeout( function () { btn.textContent = label; }, 1600 );
			swap( data );
		} );
	}, true );

	// Initialise the header badge from the active project.
	if ( typeof P.count !== 'undefined' ) { setCount( P.count ); }

	// A product a guest tried to add before signing in — add it now.
	var pend = document.cookie.match( /(?:^|;\s*)rm_pending_add=(\d+)/ );
	if ( pend ) {
		document.cookie = 'rm_pending_add=; max-age=0; path=/';
		post( { op: 'add', product: pend[1] }, function ( data ) { swap( data ); } );
	}
} )();
