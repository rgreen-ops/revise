/**
 * Download lead-gate (front end).
 *
 * Intercepts clicks on document-download links for logged-out visitors who
 * haven't been captured yet, and shows the gate popup. On success it sets a
 * cookie and opens the file. Logged-in users and already-captured visitors are
 * never interrupted. Delegated on document, so it covers every download link
 * without per-template markup.
 */
( function () {
	'use strict';

	var G = window.rmGate || {};

	/* ---- BIM / Revit request (runs for everyone, incl. logged-in) ---- */
	( function () {
		var modal = document.querySelector( '.rm-bimgate' );
		if ( ! modal ) { return; }
		var form = modal.querySelector( '.rm-bim-form' );
		var msg = modal.querySelector( '.rm-gate-msg' );
		function open( product ) {
			form.product.value = product || '';
			if ( G.name && ! form.name.value ) { form.name.value = G.name; }
			if ( G.email && ! form.email.value ) { form.email.value = G.email; }
			if ( msg ) { msg.hidden = true; }
			modal.hidden = false; document.body.style.overflow = 'hidden';
		}
		function close() { modal.hidden = true; document.body.style.overflow = ''; }
		document.addEventListener( 'click', function ( e ) {
			var req = e.target.closest && e.target.closest( '.rm-bim-req' );
			if ( req ) { e.preventDefault(); open( req.getAttribute( 'data-product' ) ); return; }
			if ( ! modal.hidden && ( e.target === modal || e.target.classList.contains( 'rm-gate-x' ) ) ) { close(); }
		}, true );
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' && ! modal.hidden ) { close(); } } );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = form.querySelector( '.rm-bim-go' );
			var body = new URLSearchParams( { action: 'rm_bim_request', nonce: G.nonce || '', name: form.name.value, email: form.email.value, company: form.company.value, product: form.product.value } );
			if ( btn ) { btn.disabled = true; }
			if ( msg ) { msg.hidden = true; }
			fetch( G.ajax, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( j ) {
				if ( btn ) { btn.disabled = false; }
				if ( ! j || ! j.success ) { if ( msg ) { msg.textContent = ( j && j.data && j.data.msg ) || 'Something went wrong — please try again.'; msg.hidden = false; } return; }
				form.innerHTML = '<p class="rm-gate-sub" style="margin:0">Thanks — your BIM request is in. Our lighting team will email the file shortly.</p>';
			} ).catch( function () { if ( btn ) { btn.disabled = false; } if ( msg ) { msg.textContent = 'Network error — please try again.'; msg.hidden = false; } } );
		} );
	} )();

	/* ---- Add-to-project for guests → prompt to sign in / register ---- */
	( function () {
		var authgate = document.querySelector( '.rm-authgate' );
		if ( ! authgate ) { return; }
		function close() { authgate.hidden = true; document.body.style.overflow = ''; }
		document.addEventListener( 'click', function ( e ) {
			var add = e.target.closest( '[data-add-to-project], .ricoman-add-project' );
			if ( add ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				var id = add.getAttribute( 'data-id' );
				if ( id ) { document.cookie = 'rm_pending_add=' + id + '; max-age=1800; path=/'; }
				var ret = encodeURIComponent( window.location.href );
				var li = authgate.querySelector( '.rm-auth-login' );
				if ( li && G.login ) { li.href = G.login + ( G.login.indexOf( '?' ) > -1 ? '&' : '?' ) + 'redirect_to=' + ret; }
				authgate.hidden = false; document.body.style.overflow = 'hidden';
				return;
			}
			if ( ! authgate.hidden && ( e.target === authgate || e.target.classList.contains( 'rm-gate-x' ) ) ) { close(); }
		}, true );
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' && ! authgate.hidden ) { close(); } } );

		// Flow designer (iframe) save while logged out → stash design + prompt auth.
		window.addEventListener( 'message', function ( ev ) {
			var d = ev.data;
			if ( ! d || d.type !== 'rm_flow_save' || ! d.design ) { return; }
			try { window.localStorage.setItem( 'rm_pending_flow', JSON.stringify( d.design ) ); } catch ( e ) {}
			var ret = encodeURIComponent( window.location.href );
			var li = authgate.querySelector( '.rm-auth-login' );
			if ( li && G.login ) { li.href = G.login + ( G.login.indexOf( '?' ) > -1 ? '&' : '?' ) + 'redirect_to=' + ret; }
			authgate.hidden = false; document.body.style.overflow = 'hidden';
			if ( ev.source ) { ev.source.postMessage( { type: 'rm_flow_saved', ok: false, auth: true }, '*' ); }
		} );
	} )();

	if ( G.in ) { return; } // Logged in — never show the download gate.

	// File types we treat as gated downloads.
	var EXT = /\.(pdf|ies|ldt|rfa|rvt|dwg|dxf|step|stp|zip|3ds|skp|docx?|xlsx?)(\?|#|$)/i;

	function hasCookie() {
		return /(?:^|;\s*)rm_dl_gate=1(?:;|$)/.test( document.cookie );
	}
	function isDownload( a ) {
		if ( ! a || ! a.getAttribute( 'href' ) ) { return false; }
		var href = a.getAttribute( 'href' );
		if ( href.charAt( 0 ) === '#' || /^(mailto:|tel:|javascript:)/i.test( href ) ) { return false; }
		if ( a.hasAttribute( 'download' ) ) { return true; }
		if ( EXT.test( href ) ) { return true; }
		if ( /[?&](datasheet|rm_pack|download)=/i.test( href ) ) { return true; }
		// Inside a known downloads area.
		return !! a.closest( '.rm-dls, .rm-prod-downloads, .rm-acc-dl, .vt-dl, .rm-cfg-dl' );
	}

	var gate = document.querySelector( '.rm-dlgate' );
	var pending = null;

	function openGate() {
		if ( ! gate ) { return; }
		gate.hidden = false;
		document.body.style.overflow = 'hidden';
		var f = gate.querySelector( 'input[name="name"]' );
		if ( f ) { setTimeout( function () { f.focus(); }, 30 ); }
	}
	function closeGate() {
		if ( ! gate ) { return; }
		gate.hidden = true;
		document.body.style.overflow = '';
	}
	function proceed() {
		if ( ! pending ) { return; }
		var a = pending; pending = null;
		var url = a.getAttribute( 'href' );
		var tgt = a.getAttribute( 'target' );
		if ( tgt === '_blank' || a.hasAttribute( 'download' ) ) { window.open( url, tgt || '_blank' ); }
		else { window.location.href = url; }
	}

	document.addEventListener( 'click', function ( e ) {
		// Close actions inside the modal.
		if ( gate && ! gate.hidden ) {
			if ( e.target === gate || e.target.classList.contains( 'rm-gate-x' ) ) {
				closeGate();
				return;
			}
		}
		var a = e.target.closest && e.target.closest( 'a' );
		if ( ! a || ! isDownload( a ) ) { return; }
		if ( hasCookie() ) { return; } // already captured — let it through.
		e.preventDefault();
		pending = a;
		openGate();
	}, true );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && gate && ! gate.hidden ) { closeGate(); }
	} );

	if ( gate ) {
		var form = gate.querySelector( '.rm-gate-form' );
		var msg = gate.querySelector( '.rm-gate-msg' );
		form && form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = form.querySelector( '.rm-gate-go' );
			var body = new URLSearchParams( {
				action: 'rm_lead_gate',
				nonce: G.nonce || '',
				name: form.name.value,
				email: form.email.value,
				ctype: form.ctype.value,
				source: ( pending && pending.getAttribute( 'href' ) ) || document.title
			} );
			if ( btn ) { btn.disabled = true; }
			if ( msg ) { msg.hidden = true; }
			fetch( G.ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( j ) {
					if ( btn ) { btn.disabled = false; }
					if ( ! j || ! j.success ) {
						if ( msg ) { msg.textContent = ( j && j.data && j.data.msg ) || 'Something went wrong — please try again.'; msg.hidden = false; }
						return;
					}
					document.cookie = 'rm_dl_gate=1; max-age=' + ( 60 * 60 * 24 * 30 ) + '; path=/';
					closeGate();
					proceed();
				} )
				.catch( function () {
					if ( btn ) { btn.disabled = false; }
					if ( msg ) { msg.textContent = 'Network error — please try again.'; msg.hidden = false; }
				} );
		} );
	}
} )();
