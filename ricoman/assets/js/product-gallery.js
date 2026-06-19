/**
 * Product gallery interactions — robust, delegated, position-independent.
 *
 * Bound once on `document`, so it works no matter where the gallery markup ends
 * up in the page (the_content, blocks, builder preview) and regardless of load
 * order. Handles: thumbnail + finish-swatch image switching, the All/Studio/
 * In-situ tabs, the click-to-zoom lightbox, and transparent-cutout detection
 * (adds .rm-cutout so cut-outs keep the grey frame while photos fill).
 */
( function () {
	'use strict';

	function wrapOf( el ) {
		return el.closest( '.rm-pdp-gallery' ) || el.closest( '.rm-cfghero-wrap' ) || document;
	}
	function mainImg( wrap ) {
		return wrap.querySelector( '.rm-cfg-img' );
	}

	document.addEventListener( 'click', function ( e ) {
		// 1) Thumbnail or finish swatch -> swap the main image.
		var btn = e.target.closest( '.rm-cfg-thumb, .rm-cv-sw' );
		if ( btn ) {
			var wrap = wrapOf( btn ), im = mainImg( wrap );
			if ( btn.dataset.img && im ) { im.src = btn.dataset.img; }
			var sel = btn.classList.contains( 'rm-cv-sw' ) ? '.rm-cv-sw' : '.rm-cfg-thumb';
			var group = btn.parentNode;
			group.querySelectorAll( sel ).forEach( function ( x ) { x.classList.remove( 'on' ); } );
			btn.classList.add( 'on' );
			return;
		}

		// 2) All / Studio / In-situ tab.
		var tab = e.target.closest( '.rm-gtab' );
		if ( tab ) {
			if ( tab.disabled ) { return; }
			var w = wrapOf( tab );
			w.querySelectorAll( '.rm-gtab' ).forEach( function ( x ) { x.classList.remove( 'on' ); } );
			tab.classList.add( 'on' );
			var t = tab.getAttribute( 'data-tab' );
			w.querySelectorAll( '.rm-gthumbs .rm-cfg-thumb' ).forEach( function ( th ) {
				th.style.display = ( t === 'all' || th.getAttribute( 'data-tab' ) === t ) ? '' : 'none';
			} );
			return;
		}

		// 3) Main image or zoom hint -> open lightbox.
		var zoom = e.target.closest( '.rm-cfg-img, .rm-zoom-hint' );
		if ( zoom && ! zoom.closest( '.rm-cfg-thumb' ) ) {
			var gw = wrapOf( zoom ), gim = mainImg( gw );
			var lb = gw.querySelector( '.rm-lightbox' ), lbi = lb && lb.querySelector( '.rm-lightbox-img' );
			if ( lb && lbi && gim ) {
				lbi.src = gim.src;
				lb.hidden = false;
				document.body.style.overflow = 'hidden';
			}
			return;
		}

		// 4) Close the lightbox (backdrop or the × button).
		var inLb = e.target.closest( '.rm-lightbox' );
		if ( inLb && ( e.target === inLb || e.target.classList.contains( 'rm-lightbox-x' ) ) ) {
			inLb.hidden = true;
			document.body.style.overflow = '';
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Escape' ) { return; }
		document.querySelectorAll( '.rm-lightbox, .rm-vt-modal' ).forEach( function ( lb ) {
			if ( ! lb.hidden ) { lb.hidden = true; document.body.style.overflow = ''; }
		} );
	} );

	/* ---- Related / Accessories carousel arrows ---- */
	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest( '.rm-relarrow' );
		if ( ! a ) { return; }
		var head = a.closest( '.rm-relhead' );
		var slider = head && head.nextElementSibling;
		if ( ! slider || ! slider.classList || ! slider.classList.contains( 'rm-relslider' ) ) { return; }
		var first = slider.firstElementChild;
		var step = first ? ( first.getBoundingClientRect().width + 24 ) * 2 : 600;
		slider.scrollBy( { left: ( a.getAttribute( 'data-rel' ) === 'prev' ? -step : step ), behavior: 'smooth' } );
	} );

	/* ---- Configure table: filters + "Show more" pagination ---- */
	function vtApply( vp ) {
		var limit = parseInt( vp.getAttribute( 'data-limit' ) || '20', 10 );
		var filters = {};
		vp.querySelectorAll( '.rm-vt-filter' ).forEach( function ( s ) {
			if ( s.value ) { filters[ 'data-f-' + s.getAttribute( 'data-col' ) ] = s.value; }
		} );
		var rows = vp.querySelectorAll( 'tbody .vt-row' ), shown = 0, matching = 0;
		rows.forEach( function ( r ) {
			var ok = true;
			for ( var k in filters ) { if ( r.getAttribute( k ) !== filters[ k ] ) { ok = false; break; } }
			if ( ok ) {
				matching++;
				if ( shown < limit ) { r.classList.remove( 'rm-vt-hide' ); shown++; }
				else { r.classList.add( 'rm-vt-hide' ); }
			} else {
				r.classList.add( 'rm-vt-hide' );
			}
		} );
		var wrap = vp.querySelector( '.rm-vt-morewrap' );
		if ( wrap ) {
			if ( matching > limit ) {
				wrap.style.display = '';
				var c = wrap.querySelector( '.rm-vt-morecount' );
				if ( c ) { c.textContent = '(' + ( matching - limit ) + ' more)'; }
			} else { wrap.style.display = 'none'; }
		}
	}
	function vtInit() {
		document.querySelectorAll( '.rm-vp' ).forEach( function ( vp ) {
			if ( ! vp.hasAttribute( 'data-limit' ) ) { vp.setAttribute( 'data-limit', '20' ); }
			vtApply( vp );
		} );
	}
	if ( document.readyState !== 'loading' ) { vtInit(); } else { document.addEventListener( 'DOMContentLoaded', vtInit ); }
	document.addEventListener( 'change', function ( e ) {
		var s = e.target.closest( '.rm-vt-filter' ); if ( ! s ) { return; }
		var vp = s.closest( '.rm-vp' ); if ( ! vp ) { return; }
		vp.setAttribute( 'data-limit', '20' ); vtApply( vp );
	} );
	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '.rm-vt-clear' ) ) {
			var vpc = e.target.closest( '.rm-vp' ); if ( ! vpc ) { return; }
			vpc.querySelectorAll( '.rm-vt-filter' ).forEach( function ( s ) { s.value = ''; } );
			vpc.setAttribute( 'data-limit', '20' ); vtApply( vpc );
			return;
		}
		var b = e.target.closest( '.rm-vt-morebtn' );
		if ( ! b ) { return; }
		var vp = b.closest( '.rm-vp' ); if ( ! vp ) { return; }
		vp.setAttribute( 'data-limit', String( ( parseInt( vp.getAttribute( 'data-limit' ) || '20', 10 ) ) + 20 ) );
		vtApply( vp );
	} );

	/* ---- Variant spec popup ---- */
	function openVariant( row ) {
		var vp = row.closest( '.rm-vp' ); if ( ! vp ) { return; }
		var idx = row.getAttribute( 'data-vt' );
		var detail = vp.querySelector( '.rm-vt-details .vt-detail[data-vt="' + idx + '"]' );
		var modal = vp.querySelector( '.rm-vt-modal' );
		var body = modal && modal.querySelector( '.rm-vt-body' );
		if ( ! detail || ! modal || ! body ) { return; }
		body.innerHTML = detail.innerHTML;
		modal.hidden = false;
		document.body.style.overflow = 'hidden';
	}
	document.addEventListener( 'click', function ( e ) {
		// Don't hijack the LDT / Datasheet links inside a row.
		if ( e.target.closest( '.rm-vptable a' ) ) { return; }
		var row = e.target.closest( '.vt-row' );
		if ( row ) { openVariant( row ); return; }
		var m = e.target.closest( '.rm-vt-modal' );
		if ( m && ( e.target === m || e.target.classList.contains( 'rm-vt-x' ) ) ) {
			m.hidden = true; document.body.style.overflow = '';
		}
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Enter' ) { return; }
		var row = e.target.closest && e.target.closest( '.vt-row' );
		if ( row ) { openVariant( row ); }
	} );

	/* Transparent cut-out detection: photos fill, cut-outs keep the grey frame. */
	function applyFit( img ) {
		if ( ! img || ! img.naturalWidth ) { return; }
		try {
			var c = document.createElement( 'canvas' ), s = 24;
			c.width = s; c.height = s;
			var x = c.getContext( '2d' );
			x.drawImage( img, 0, 0, s, s );
			var d = x.getImageData( 0, 0, s, s ).data, transparent = false;
			for ( var i = 3; i < d.length; i += 4 ) { if ( d[ i ] < 240 ) { transparent = true; break; } }
			img.classList.toggle( 'rm-cutout', transparent );
		} catch ( err ) {
			img.classList.remove( 'rm-cutout' );
		}
	}
	function scanFit() {
		document.querySelectorAll( '.rm-pdp-gallery .rm-cfg-img' ).forEach( function ( im ) {
			if ( im.complete ) { applyFit( im ); }
		} );
	}
	// Re-check whenever a main image finishes loading (incl. after a swap).
	document.addEventListener( 'load', function ( e ) {
		var im = e.target;
		if ( im && im.classList && im.classList.contains( 'rm-cfg-img' ) ) { applyFit( im ); }
	}, true );
	if ( document.readyState !== 'loading' ) { scanFit(); }
	else { document.addEventListener( 'DOMContentLoaded', scanFit ); }
} )();
