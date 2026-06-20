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
	if ( G.in ) { return; } // Logged in — never gate.

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

	var gate = document.querySelector( '.rm-gate' );
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
