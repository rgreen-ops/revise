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
		var limit = parseInt( vp.getAttribute( 'data-limit' ) || '10', 10 );
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
			if ( ! vp.hasAttribute( 'data-limit' ) ) { vp.setAttribute( 'data-limit', '10' ); }
			vtApply( vp );
		} );
	}
	if ( document.readyState !== 'loading' ) { vtInit(); } else { document.addEventListener( 'DOMContentLoaded', vtInit ); }
	document.addEventListener( 'change', function ( e ) {
		var s = e.target.closest( '.rm-vt-filter' ); if ( ! s ) { return; }
		var vp = s.closest( '.rm-vp' ); if ( ! vp ) { return; }
		vp.setAttribute( 'data-limit', '10' ); vtApply( vp );
	} );
	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '.rm-vt-clear' ) ) {
			var vpc = e.target.closest( '.rm-vp' ); if ( ! vpc ) { return; }
			vpc.querySelectorAll( '.rm-vt-filter' ).forEach( function ( s ) { s.value = ''; } );
			vpc.setAttribute( 'data-limit', '10' ); vtApply( vpc );
			return;
		}
		var b = e.target.closest( '.rm-vt-morebtn' );
		if ( ! b ) { return; }
		var vp = b.closest( '.rm-vp' ); if ( ! vp ) { return; }
		var step = parseInt( b.getAttribute( 'data-step' ) || '10', 10 );
		vp.setAttribute( 'data-limit', String( ( parseInt( vp.getAttribute( 'data-limit' ) || '10', 10 ) ) + step ) );
		vtApply( vp );
	} );

	/* ---- Projects search + sector filter toolbar ---- */
	function projScope( el ) {
		// Tools bar and grid are siblings; walk forward to the grid wrapper.
		var tools = el.closest( '.rm-projtools' );
		if ( ! tools ) { return null; }
		var n = tools.nextElementSibling;
		while ( n && ! ( n.classList && n.classList.contains( 'rm-projwide' ) ) ) { n = n.nextElementSibling; }
		var grid = n && n.querySelector( '.rm-projgrid' );
		if ( ! grid ) { return null; }
		return {
			tools: tools, grid: grid,
			q: tools.querySelector( '.rm-projq' ),
			sel: tools.querySelector( '.rm-projsel' ),
			count: tools.querySelector( '.rm-projcount' ),
			empty: n.querySelector( '.rm-projempty' )
		};
	}
	function projApply( s ) {
		if ( ! s ) { return; }
		var q = ( s.q && s.q.value || '' ).trim().toLowerCase();
		var cat = s.sel && s.sel.value || '';
		var shown = 0;
		s.grid.querySelectorAll( '.rm-projcard' ).forEach( function ( card ) {
			var cats = ( card.getAttribute( 'data-cats' ) || '' ).split( ' ' );
			var hay = card.getAttribute( 'data-search' ) || '';
			var ok = ( ! cat || cats.indexOf( cat ) > -1 ) && ( ! q || hay.indexOf( q ) > -1 );
			card.style.display = ok ? '' : 'none';
			if ( ok ) { shown++; }
		} );
		if ( s.count ) {
			var total = s.count.getAttribute( 'data-total' ) || shown;
			s.count.textContent = ( q || cat )
				? ( shown + ' of ' + total + ' projects' )
				: ( total + ' projects' );
		}
		if ( s.empty ) { s.empty.hidden = shown !== 0; }
	}
	document.addEventListener( 'input', function ( e ) {
		if ( e.target.closest( '.rm-projq' ) ) { projApply( projScope( e.target ) ); }
	} );
	document.addEventListener( 'change', function ( e ) {
		if ( e.target.closest( '.rm-projsel' ) ) { projApply( projScope( e.target ) ); }
	} );
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest( '.rm-projreset' ) ) { return; }
		var wide = e.target.closest( '.rm-projwide' );
		var tools = wide && wide.previousElementSibling;
		while ( tools && ! ( tools.classList && tools.classList.contains( 'rm-projtools' ) ) ) { tools = tools.previousElementSibling; }
		var s = tools && projScope( tools.querySelector( '.rm-projq' ) || tools );
		if ( ! s ) { return; }
		if ( s.q ) { s.q.value = ''; }
		if ( s.sel ) { s.sel.value = ''; }
		projApply( s );
	} );

	/* ---- Product catalogue / category faceted filter (lumens + watts + ticks) ---- */
	function rmCatFilter( w ) {
		var cards = [].slice.call( w.querySelectorAll( '.rm-fcard' ) );
		if ( ! cards.length ) { return; }
		var lmMin = w.querySelector( '.rm-lm-min' ), lmMax = w.querySelector( '.rm-lm-max' );
		var wMin = w.querySelector( '.rm-w-min' ), wMax = w.querySelector( '.rm-w-max' );
		var lmLo = w.querySelector( '.rm-lm-lo' ), lmHi = w.querySelector( '.rm-lm-hi' );
		var wLo = w.querySelector( '.rm-w-lo' ), wHi = w.querySelector( '.rm-w-hi' );
		var count = w.querySelector( '.rm-fcount b' ), none = w.querySelector( '.rm-fnone' );
		function ticks() {
			return [].slice.call( w.querySelectorAll( '.rm-ftick input:checked' ) ).map( function ( i ) { return i.value; } );
		}
		function apply() {
			var loLm = lmMin ? +lmMin.value : 0, hiLm = lmMax ? +lmMax.value : 1e9;
			var loW = wMin ? +wMin.value : 0, hiW = wMax ? +wMax.value : 1e9;
			var lmFull = ! lmMin || ( loLm <= +lmMin.min && hiLm >= +lmMax.max );
			var wFull = ! wMin || ( loW <= +wMin.min && hiW >= +wMax.max );
			if ( lmLo ) { lmLo.textContent = loLm.toLocaleString(); }
			if ( lmHi ) { lmHi.textContent = hiLm.toLocaleString(); }
			if ( wLo ) { wLo.textContent = loW; }
			if ( wHi ) { wHi.textContent = hiW; }
			var want = ticks(), shown = 0;
			cards.forEach( function ( c ) {
				var clm = +c.dataset.lm || 0, cw = +c.dataset.w || 0, cf = ( c.dataset.feat || '' ).split( ' ' );
				var ok = true;
				// When a range filter is active, products without that metric
				// (accessories, track housing, etc.) are excluded — they can't
				// satisfy a lumen/wattage requirement.
				if ( ! lmFull && ( clm <= 0 || clm < loLm || clm > hiLm ) ) { ok = false; }
				if ( ! wFull && ( cw <= 0 || cw < loW || cw > hiW ) ) { ok = false; }
				want.forEach( function ( f ) { if ( cf.indexOf( f ) < 0 ) { ok = false; } } );
				c.hidden = ! ok; if ( ok ) { shown++; }
			} );
			[].slice.call( w.querySelectorAll( '.rm-catsec' ) ).forEach( function ( s ) {
				var vis = s.querySelectorAll( '.rm-fcard:not([hidden])' ).length;
				s.hidden = vis === 0;
				var cc = s.querySelector( '.rm-catarch-count' ); if ( cc ) { cc.textContent = vis; }
			} );
			if ( count ) { count.textContent = shown; }
			if ( none ) { none.hidden = shown > 0; }
		}
		function pair( min, max, gap ) {
			if ( ! min || ! max ) { return; }
			min.addEventListener( 'input', function () { if ( +min.value > +max.value - gap ) { min.value = Math.max( +min.min, +max.value - gap ); } apply(); } );
			max.addEventListener( 'input', function () { if ( +max.value < +min.value + gap ) { max.value = Math.min( +max.max, +min.value + gap ); } apply(); } );
		}
		pair( lmMin, lmMax, 100 ); pair( wMin, wMax, 1 );
		w.querySelectorAll( '.rm-ftick input' ).forEach( function ( i ) { i.addEventListener( 'change', apply ); } );
		w.querySelectorAll( '.rm-fclear' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				if ( lmMin ) { lmMin.value = lmMin.min; } if ( lmMax ) { lmMax.value = lmMax.max; }
				if ( wMin ) { wMin.value = wMin.min; } if ( wMax ) { wMax.value = wMax.max; }
				w.querySelectorAll( '.rm-ftick input' ).forEach( function ( i ) { i.checked = false; } ); apply();
			} );
		} );
		apply();
	}
	function rmCatFilterInit() {
		document.querySelectorAll( '.rm-catwide, .rm-catarch' ).forEach( function ( w ) {
			if ( w.dataset.rmcf ) { return; } w.dataset.rmcf = '1'; rmCatFilter( w );
		} );
	}
	if ( document.readyState !== 'loading' ) { rmCatFilterInit(); } else { document.addEventListener( 'DOMContentLoaded', rmCatFilterInit ); }

	/* ---- Horizontal-scroll affordance: fade edges that have more content ---- */
	function rmEdgeScroller( el ) {
		function upd() {
			var max = el.scrollWidth - el.clientWidth;
			if ( max <= 2 ) { el.classList.remove( 'rm-fade-l', 'rm-fade-r' ); return; }
			el.classList.toggle( 'rm-fade-l', el.scrollLeft > 4 );
			el.classList.toggle( 'rm-fade-r', el.scrollLeft < max - 4 );
		}
		el.addEventListener( 'scroll', upd, { passive: true } );
		window.addEventListener( 'resize', upd );
		// Recheck after layout/fonts settle.
		upd(); setTimeout( upd, 250 );
	}
	function rmEdgeInit() {
		document.querySelectorAll( '.rm-newschips, .rm-catnav' ).forEach( function ( el ) {
			if ( el.dataset.rmedge ) { return; } el.dataset.rmedge = '1'; rmEdgeScroller( el );
		} );
	}
	if ( document.readyState !== 'loading' ) { rmEdgeInit(); } else { document.addEventListener( 'DOMContentLoaded', rmEdgeInit ); }

	/* ---- News listing: search + topic chips ---- */
	function rmNewsFilter( w ) {
		var q = w.querySelector( '.rm-newsq' );
		var chips = [].slice.call( w.querySelectorAll( '.rm-newschip' ) );
		var cards = [].slice.call( w.querySelectorAll( '.rm-newscard' ) );
		var none = w.querySelector( '.rm-newsnone' );
		if ( ! cards.length ) { return; }
		function apply() {
			var qv = ( q && q.value || '' ).trim().toLowerCase();
			var active = w.querySelector( '.rm-newschip.on' );
			var tag = active ? active.getAttribute( 'data-tag' ) : '';
			var shown = 0;
			cards.forEach( function ( c ) {
				var tags = ( c.getAttribute( 'data-tags' ) || '' ).split( ' ' );
				var hay = c.getAttribute( 'data-search' ) || '';
				var ok = ( ! tag || tags.indexOf( tag ) > -1 ) && ( ! qv || hay.indexOf( qv ) > -1 );
				c.style.display = ok ? '' : 'none';
				var lead = c.closest( '.rm-news-lead' );
				if ( lead ) { lead.style.display = ok ? '' : 'none'; }
				if ( ok ) { shown++; }
			} );
			if ( none ) { none.hidden = shown !== 0; }
		}
		if ( q ) { q.addEventListener( 'input', apply ); }
		chips.forEach( function ( c ) {
			c.addEventListener( 'click', function () {
				chips.forEach( function ( x ) { x.classList.remove( 'on' ); } );
				c.classList.add( 'on' ); apply();
			} );
		} );
		var reset = w.querySelector( '.rm-newsreset' );
		if ( reset ) {
			reset.addEventListener( 'click', function () {
				if ( q ) { q.value = ''; }
				chips.forEach( function ( x ) { x.classList.remove( 'on' ); } );
				if ( chips[0] ) { chips[0].classList.add( 'on' ); }
				apply();
			} );
		}
	}
	function rmNewsInit() {
		document.querySelectorAll( '.rm-newswrap' ).forEach( function ( w ) {
			if ( w.dataset.rmnf ) { return; } w.dataset.rmnf = '1'; rmNewsFilter( w );
		} );
	}
	if ( document.readyState !== 'loading' ) { rmNewsInit(); } else { document.addEventListener( 'DOMContentLoaded', rmNewsInit ); }

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
