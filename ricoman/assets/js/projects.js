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

		var ed = e.target.closest( '[data-proj-editname]' );
		if ( ed ) {
			var wrap = ed.closest( '.rm-proj-titlewrap' );
			var input = wrap && wrap.querySelector( '.rm-proj-title' );
			if ( input ) { input.focus(); input.select(); }
			return;
		}
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

	// ---- Project picker modal (shown when the user has more than one project) ----
	var picker = null;
	function buildPicker() {
		if ( picker ) { return picker; }
		picker = document.createElement( 'div' );
		picker.className = 'rm-gate rm-pickgate';
		picker.hidden = true;
		picker.innerHTML = '<div class="rm-gate-box" role="dialog" aria-modal="true">'
			+ '<button type="button" class="rm-gate-x" aria-label="Close">&times;</button>'
			+ '<h3 class="rm-gate-title">Add to which project?</h3>'
			+ '<div class="rm-pick-list"></div>'
			+ '<button type="button" class="btn btn-line-d rm-pick-new" style="width:100%;justify-content:center;margin-top:10px">＋ New project</button>'
			+ '</div>';
		document.body.appendChild( picker );
		picker.addEventListener( 'click', function ( e ) {
			if ( e.target === picker || e.target.classList.contains( 'rm-gate-x' ) ) { closePicker(); }
		} );
		return picker;
	}
	function closePicker() { if ( picker ) { picker.hidden = true; document.body.style.overflow = ''; } }
	function openPicker( productId, projects, btn, label ) {
		var p = buildPicker();
		var list = p.querySelector( '.rm-pick-list' );
		list.innerHTML = '';
		projects.forEach( function ( pr ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.className = 'rm-pick-item';
			b.innerHTML = '<span>' + ( pr.name || 'Project' ).replace( /[<>&]/g, '' ) + '</span><span class="rm-pick-c">' + ( pr.count || 0 ) + '</span>';
			b.addEventListener( 'click', function () {
				post( { op: 'add', product: productId, pid: pr.id }, function ( data ) { added( btn, label ); swap( data ); } );
				closePicker();
			} );
			list.appendChild( b );
		} );
		p.querySelector( '.rm-pick-new' ).onclick = function () {
			var name = window.prompt( 'Name the new project:', '' );
			if ( name === null ) { return; }
			post( { op: 'add', product: productId, newname: name || 'Untitled project' }, function ( data ) { added( btn, label ); swap( data ); } );
			closePicker();
		};
		p.hidden = false; document.body.style.overflow = 'hidden';
	}
	function added( btn, label ) {
		if ( ! btn ) { return; }
		btn.textContent = '✓ Added to project';
		setTimeout( function () { btn.textContent = label; }, 1600 );
	}
	document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { closePicker(); } } );

	// ---- "Add to My Project" → choose a project when there's more than one ----
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-add-to-project], .ricoman-add-project' );
		if ( ! btn ) { return; }
		e.preventDefault();
		e.stopImmediatePropagation();
		var id = btn.getAttribute( 'data-id' );
		if ( ! id ) { return; }
		var label = btn.textContent;
		// Look up the user's projects first; one project = add straight away.
		post( { op: 'list' }, null ).then( function ( j ) {
			var projects = ( j && j.success && j.data.projects ) || [];
			if ( projects.length > 1 ) {
				openPicker( id, projects, btn, label );
			} else {
				post( { op: 'add', product: id }, function ( data ) { added( btn, label ); swap( data ); } );
			}
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

	// A Flow+ design saved before signing in — add it now.
	try {
		var pf = window.localStorage.getItem( 'rm_pending_flow' );
		if ( pf ) {
			window.localStorage.removeItem( 'rm_pending_flow' );
			var dz = JSON.parse( pf );
			post( { op: 'addcustom', ctype: 'flow', name: dz.name || 'Flow+ run', summary: dz.summary || '' }, function ( data ) { swap( data ); } );
		}
	} catch ( e ) {}

	// Flow designer (iframe) → save the current design as a custom project item.
	window.addEventListener( 'message', function ( ev ) {
		var d = ev.data;
		if ( ! d || d.type !== 'rm_flow_save' || ! d.design ) { return; }
		post( { op: 'addcustom', ctype: 'flow', name: d.design.name || 'Flow+ run', summary: d.design.summary || '' }, function ( data ) { swap( data ); } );
		if ( ev.source ) { ev.source.postMessage( { type: 'rm_flow_saved', ok: true }, '*' ); }
	} );
} )();
