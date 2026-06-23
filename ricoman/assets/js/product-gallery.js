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

	// Shared: original-image candidates for a failed "-rmwebp.webp" twin (WebP
	// generation is dormant while the disk is full, so many twins don't exist).
	function rmOrigAlts( url ) {
		if ( ! url || ! /-rmwebp\.webp/i.test( url ) ) { return []; }
		return [ url.replace( /-rmwebp\.webp/i, '.png' ), url.replace( /-rmwebp\.webp/i, '.jpg' ), url.replace( /-rmwebp\.webp/i, '.jpeg' ) ];
	}
	// Set an <img> src with graceful fallback: try the URL, then its original
	// (non-webp) candidates, before the placeholder — so clicking a thumbnail whose
	// webp twin is missing shows the REAL photo, not the ceiling placeholder.
	function rmSetImg( im, url ) {
		if ( ! im || ! url ) { return; }
		var chain = [ url ].concat( rmOrigAlts( url ) ), i = 0;
		im.onerror = function () {
			i++;
			if ( i < chain.length ) { im.src = chain[ i ]; return; }
			im.onerror = null;
			var PH = ( window.rmGallery && window.rmGallery.ph ) || '';
			if ( PH ) { im.src = PH; }
		};
		im.src = chain[0];
	}

	/* ---- Sticky header: solid bar once scrolled ---- */
	( function () {
		var hdr = document.querySelector( 'header.site' ) || document.querySelector( '.ricoman-site-header' );
		if ( ! hdr ) { return; }
		function onScroll() { hdr.classList.toggle( 'rm-stuck', ( window.scrollY || window.pageYOffset ) > 40 ); }
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		onScroll();
	} )();

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
			if ( btn.dataset.img && im ) { rmSetImg( im, btn.dataset.img ); }
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
	// Server-paginated tables (big ranges): fetch a page of rows for the current
	// filter selection instead of shipping every row to the browser.
	function vtFilters( vp ) {
		var f = {};
		vp.querySelectorAll( '.rm-vt-filter' ).forEach( function ( s ) {
			if ( s.value ) { f[ s.getAttribute( 'data-col' ) ] = s.value; }
		} );
		return f;
	}
	function vtSrvLoad( vp, page, append ) {
		var base = vp.getAttribute( 'data-url' ) || ( window.ajaxurl || '/wp-admin/admin-ajax.php' );
		var pid = vp.getAttribute( 'data-product' );
		var size = parseInt( vp.getAttribute( 'data-page-size' ) || '25', 10 );
		var tbody = vp.querySelector( 'tbody' );
		var more = vp.querySelector( '.rm-vt-morewrap' );
		var btn = more && more.querySelector( '.rm-vt-morebtn' );
		if ( ! tbody || vp.getAttribute( 'data-busy' ) ) { return; }
		vp.setAttribute( 'data-busy', '1' );
		if ( btn ) { btn.disabled = true; }
		var url = base + '?action=rm_vrows&product=' + encodeURIComponent( pid ) + '&page=' + page + '&f=' + encodeURIComponent( JSON.stringify( vtFilters( vp ) ) );
		fetch( url, { credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( d ) {
			if ( append ) { tbody.insertAdjacentHTML( 'beforeend', d.rows || '' ); }
			else { tbody.innerHTML = d.rows || ''; }
			vp.setAttribute( 'data-page', String( d.page || 1 ) );
			if ( more ) {
				if ( d.hasMore ) {
					more.style.display = '';
					var c = more.querySelector( '.rm-vt-morecount' );
					if ( c ) { c.textContent = '(' + Math.max( 0, ( d.total || 0 ) - ( d.page || 1 ) * size ) + ' more)'; }
				} else { more.style.display = 'none'; }
			}
			vp.removeAttribute( 'data-busy' );
			if ( btn ) { btn.disabled = false; }
		} ).catch( function () {
			vp.removeAttribute( 'data-busy' );
			if ( btn ) { btn.disabled = false; }
		} );
	}
	function vtApply( vp ) {
		if ( vp.getAttribute( 'data-srv' ) ) { return; } // server-paginated; handled by AJAX.
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
	window.ricomanVtInit = vtInit;
	if ( document.readyState !== 'loading' ) { vtInit(); } else { document.addEventListener( 'DOMContentLoaded', vtInit ); }

	/* ---- Lazy configurator: stream the heavy variant section in after paint ---- */
	function loadCfg( el ) {
		var pid = el.getAttribute( 'data-product' );
		var base = el.getAttribute( 'data-url' ) || ( window.ajaxurl || '/wp-admin/admin-ajax.php' );
		if ( ! pid || el.getAttribute( 'data-loading' ) ) { return; }
		el.setAttribute( 'data-loading', '1' );
		fetch( base + '?action=rm_cfg_section&product=' + encodeURIComponent( pid ), { credentials: 'same-origin' } )
			.then( function ( r ) { return r.text(); } )
			.then( function ( html ) {
				var tmp = document.createElement( 'div' );
				tmp.innerHTML = html;
				var frag = document.createDocumentFragment();
				while ( tmp.firstChild ) { frag.appendChild( tmp.firstChild ); }
				el.parentNode.replaceChild( frag, el );
				if ( window.ricomanVcfgInit ) { window.ricomanVcfgInit(); }
				if ( window.ricomanVtInit ) { window.ricomanVtInit(); }
			} )
			.catch( function () {
				el.removeAttribute( 'data-loading' );
				var m = el.querySelector( '.rm-cfg-loading' );
				if ( m ) { m.textContent = 'Could not load the configurator — please refresh.'; }
			} );
	}
	function cfgLazyInit() {
		var nodes = document.querySelectorAll( '.rm-cfg-lazy' );
		if ( ! nodes.length ) { return; }
		if ( 'IntersectionObserver' in window ) {
			var io = new IntersectionObserver( function ( ents ) {
				ents.forEach( function ( en ) { if ( en.isIntersecting ) { io.unobserve( en.target ); loadCfg( en.target ); } } );
			}, { rootMargin: '600px' } );
			nodes.forEach( function ( n ) { io.observe( n ); } );
		} else {
			nodes.forEach( loadCfg );
		}
	}
	if ( document.readyState !== 'loading' ) { cfgLazyInit(); } else { document.addEventListener( 'DOMContentLoaded', cfgLazyInit ); }
	document.addEventListener( 'change', function ( e ) {
		var s = e.target.closest( '.rm-vt-filter' ); if ( ! s ) { return; }
		var vp = s.closest( '.rm-vp' ); if ( ! vp ) { return; }
		if ( vp.getAttribute( 'data-srv' ) ) { vtSrvLoad( vp, 1, false ); return; }
		vp.setAttribute( 'data-limit', '10' ); vtApply( vp );
	} );
	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '.rm-vt-clear' ) ) {
			var vpc = e.target.closest( '.rm-vp' ); if ( ! vpc ) { return; }
			vpc.querySelectorAll( '.rm-vt-filter' ).forEach( function ( s ) { s.value = ''; } );
			if ( vpc.getAttribute( 'data-srv' ) ) { vtSrvLoad( vpc, 1, false ); return; }
			vpc.setAttribute( 'data-limit', '10' ); vtApply( vpc );
			return;
		}
		var b = e.target.closest( '.rm-vt-morebtn' );
		if ( ! b ) { return; }
		var vp = b.closest( '.rm-vp' ); if ( ! vp ) { return; }
		if ( vp.getAttribute( 'data-srv' ) ) {
			vtSrvLoad( vp, parseInt( vp.getAttribute( 'data-page' ) || '1', 10 ) + 1, true );
			return;
		}
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
			count: tools.querySelector( '.rm-projcount' ),
			empty: n.querySelector( '.rm-projempty' )
		};
	}
	function projApply( s ) {
		if ( ! s ) { return; }
		var q = ( s.q && s.q.value || '' ).trim().toLowerCase();
		var active = s.tools.querySelector( '.rm-projchip.on' );
		var cat = active ? active.getAttribute( 'data-cat' ) : '';
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
	document.addEventListener( 'click', function ( e ) {
		var chip = e.target.closest( '.rm-projchip' );
		if ( chip ) {
			var bar = chip.closest( '.rm-projtools' );
			if ( bar ) { bar.querySelectorAll( '.rm-projchip' ).forEach( function ( x ) { x.classList.remove( 'on' ); } ); }
			chip.classList.add( 'on' );
			projApply( projScope( chip ) );
			return;
		}
		if ( ! e.target.closest( '.rm-projreset' ) ) { return; }
		var wide = e.target.closest( '.rm-projwide' );
		var tools = wide && wide.previousElementSibling;
		while ( tools && ! ( tools.classList && tools.classList.contains( 'rm-projtools' ) ) ) { tools = tools.previousElementSibling; }
		var s = tools && projScope( tools.querySelector( '.rm-projq' ) || tools );
		if ( ! s ) { return; }
		if ( s.q ) { s.q.value = ''; }
		s.tools.querySelectorAll( '.rm-projchip' ).forEach( function ( x ) { x.classList.remove( 'on' ); } );
		var all = s.tools.querySelector( '.rm-projchip' );
		if ( all ) { all.classList.add( 'on' ); }
		projApply( s );
	} );

	/* ---- Product catalogue / category faceted filter (lumens + watts + ticks) ---- */
	function rmCatFilter( w ) {
		var cards = [].slice.call( w.querySelectorAll( '.rm-fcard' ) );
		if ( ! cards.length ) { return; }
		var lmMin = w.querySelector( '.rm-lm-min' ), lmMax = w.querySelector( '.rm-lm-max' );
		var wMin = w.querySelector( '.rm-w-min' ), wMax = w.querySelector( '.rm-w-max' );
		var coMin = w.querySelector( '.rm-co-min' ), coMax = w.querySelector( '.rm-co-max' );
		var lmLo = w.querySelector( '.rm-lm-lo' ), lmHi = w.querySelector( '.rm-lm-hi' );
		var wLo = w.querySelector( '.rm-w-lo' ), wHi = w.querySelector( '.rm-w-hi' );
		var coLo = w.querySelector( '.rm-co-lo' ), coHi = w.querySelector( '.rm-co-hi' );
		var count = w.querySelector( '.rm-fcount b' ), none = w.querySelector( '.rm-fnone' );
		function ticks() {
			return [].slice.call( w.querySelectorAll( '.rm-ftick input:checked' ) ).map( function ( i ) { return i.value; } );
		}
		function apply() {
			var loLm = lmMin ? +lmMin.value : 0, hiLm = lmMax ? +lmMax.value : 1e9;
			var loW = wMin ? +wMin.value : 0, hiW = wMax ? +wMax.value : 1e9;
			var loCo = coMin ? +coMin.value : 0, hiCo = coMax ? +coMax.value : 1e9;
			var lmFull = ! lmMin || ( loLm <= +lmMin.min && hiLm >= +lmMax.max );
			var wFull = ! wMin || ( loW <= +wMin.min && hiW >= +wMax.max );
			var coFull = ! coMin || ( loCo <= +coMin.min && hiCo >= +coMax.max );
			if ( lmLo ) { lmLo.textContent = loLm.toLocaleString(); }
			if ( lmHi ) { lmHi.textContent = hiLm.toLocaleString(); }
			if ( wLo ) { wLo.textContent = loW; }
			if ( wHi ) { wHi.textContent = hiW; }
			if ( coLo ) { coLo.textContent = loCo; }
			if ( coHi ) { coHi.textContent = hiCo; }
			var want = ticks(), shown = 0;
			cards.forEach( function ( c ) {
				var clm = +c.dataset.lm || 0, cw = +c.dataset.w || 0, cco = +c.dataset.co || 0, cf = ( c.dataset.feat || '' ).split( ' ' );
				var ok = true;
				// When a range filter is active, products without that metric
				// (accessories, track housing, etc.) are excluded — they can't
				// satisfy a lumen/wattage/cut-out requirement.
				if ( ! lmFull && ( clm <= 0 || clm < loLm || clm > hiLm ) ) { ok = false; }
				if ( ! wFull && ( cw <= 0 || cw < loW || cw > hiW ) ) { ok = false; }
				if ( ! coFull && ( cco <= 0 || cco < loCo || cco > hiCo ) ) { ok = false; }
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
		pair( lmMin, lmMax, 100 ); pair( wMin, wMax, 1 ); pair( coMin, coMax, 1 );
		w.querySelectorAll( '.rm-ftick input' ).forEach( function ( i ) { i.addEventListener( 'change', apply ); } );
		w.querySelectorAll( '.rm-fclear' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				if ( lmMin ) { lmMin.value = lmMin.min; } if ( lmMax ) { lmMax.value = lmMax.max; }
				if ( wMin ) { wMin.value = wMin.min; } if ( wMax ) { wMax.value = wMax.max; }
				if ( coMin ) { coMin.value = coMin.min; } if ( coMax ) { coMax.value = coMax.max; }
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
			// Read layout inside rAF so it doesn't force a synchronous reflow
			// during the load task (reads happen after the browser's own layout).
			requestAnimationFrame( function () {
				var max = el.scrollWidth - el.clientWidth;
				if ( max <= 2 ) { el.classList.remove( 'rm-fade-l', 'rm-fade-r' ); return; }
				el.classList.toggle( 'rm-fade-l', el.scrollLeft > 4 );
				el.classList.toggle( 'rm-fade-r', el.scrollLeft < max - 4 );
			} );
		}
		el.addEventListener( 'scroll', upd, { passive: true } );
		window.addEventListener( 'resize', upd );
		// Recheck after layout/fonts settle.
		upd(); setTimeout( upd, 250 );
	}
	function rmEdgeInit() {
		document.querySelectorAll( '.rm-newschips, .rm-catnav, .rm-projchips' ).forEach( function ( el ) {
			if ( el.dataset.rmedge ) { return; } el.dataset.rmedge = '1'; rmEdgeScroller( el );
		} );
	}
	if ( document.readyState !== 'loading' ) { rmEdgeInit(); } else { document.addEventListener( 'DOMContentLoaded', rmEdgeInit ); }

	/* ---- Lazy background images (catalogue / product cards) ----
	 * The /products/ archive renders ~500 cards. Loading every background image
	 * up front made the page very heavy; instead each card keeps its URL in
	 * data-bg and we set the background only as the card nears the viewport. */
	function rmLazyBg() {
		var els = document.querySelectorAll( '[data-bg]' );
		if ( ! els.length ) { return; }
		if ( ! ( 'IntersectionObserver' in window ) ) {
			els.forEach( function ( el ) { el.style.backgroundImage = 'url(' + el.getAttribute( 'data-bg' ) + ')'; el.removeAttribute( 'data-bg' ); } );
			return;
		}
		var io = new IntersectionObserver( function ( ents ) {
			ents.forEach( function ( e ) {
				if ( ! e.isIntersecting ) { return; }
				var el = e.target, u = el.getAttribute( 'data-bg' );
				if ( u ) { el.style.backgroundImage = 'url(' + u + ')'; el.removeAttribute( 'data-bg' ); }
				io.unobserve( el );
			} );
		}, { rootMargin: '500px 0px' } );
		els.forEach( function ( el ) { io.observe( el ); } );
	}
	if ( document.readyState !== 'loading' ) { rmLazyBg(); } else { document.addEventListener( 'DOMContentLoaded', rmLazyBg ); }

	/* ---- Category tiles: lazy In-situ/Studio images + toggle ----
	 * Each tile carries data-insitu and data-studio URLs. Only the current mode's
	 * image is loaded (lazily, on scroll); toggling swaps loaded tiles to the
	 * other style on demand. Default mode is In-situ. */
	function rmCatImages() {
		var cards = [].slice.call( document.querySelectorAll( '.rm-catcard-img[data-insitu],.rm-catcard-img[data-studio]' ) );
		if ( ! cards.length ) { return; }
		var mode = 'insitu';
		function setBg( el ) {
			var u = el.getAttribute( 'data-' + mode ) || el.getAttribute( 'data-insitu' ) || el.getAttribute( 'data-studio' );
			if ( u ) { el.style.backgroundImage = 'url(' + u + ')'; }
		}
		var io = ( 'IntersectionObserver' in window )
			? new IntersectionObserver( function ( ents ) {
				ents.forEach( function ( e ) {
					if ( ! e.isIntersecting ) { return; }
					e.target._rmseen = true; setBg( e.target );
				} );
			}, { rootMargin: '500px 0px' } )
			: null;
		cards.forEach( function ( el ) { if ( io ) { io.observe( el ); } else { el._rmseen = true; setBg( el ); } } );
		document.addEventListener( 'click', function ( ev ) {
			var b = ev.target.closest ? ev.target.closest( '.rm-cattoggle button[data-mode]' ) : null;
			if ( ! b ) { return; }
			mode = b.getAttribute( 'data-mode' );
			var wrap = b.closest( '.rm-cattoggle' );
			[].slice.call( wrap.querySelectorAll( 'button[data-mode]' ) ).forEach( function ( x ) { x.classList.toggle( 'on', x === b ); } );
			cards.forEach( function ( el ) { if ( el._rmseen ) { setBg( el ); } } );
		} );
	}
	if ( document.readyState !== 'loading' ) { rmCatImages(); } else { document.addEventListener( 'DOMContentLoaded', rmCatImages ); }

	/* ---- Generic lazy <img> (variant-table thumbnails etc.) ----
	 * Images carry their URL in data-src; load it as the image nears the viewport.
	 * Keeps large numbers of thumbnails off the initial render. Hidden rows that
	 * are revealed (e.g. "show more") get picked up when they gain layout. */
	function rmLazyImg() {
		var imgs = [].slice.call( document.querySelectorAll( 'img.rm-lazyimg[data-src]' ) );
		if ( ! imgs.length ) { return; }
		function load( el ) { var u = el.getAttribute( 'data-src' ); if ( u ) { el.src = u; el.removeAttribute( 'data-src' ); } }
		if ( ! ( 'IntersectionObserver' in window ) ) { imgs.forEach( load ); return; }
		var io = new IntersectionObserver( function ( ents ) {
			ents.forEach( function ( e ) { if ( e.isIntersecting ) { load( e.target ); io.unobserve( e.target ); } } );
		}, { rootMargin: '600px 0px' } );
		imgs.forEach( function ( el ) { io.observe( el ); } );
		// Safety net: when a "show more" / reveal happens, load any still-pending
		// thumbnails that are now displayed.
		document.addEventListener( 'click', function () {
			setTimeout( function () {
				[].slice.call( document.querySelectorAll( 'img.rm-lazyimg[data-src]' ) ).forEach( function ( el ) {
					if ( el.offsetParent !== null ) { load( el ); }
				} );
			}, 60 );
		}, true );
	}
	if ( document.readyState !== 'loading' ) { rmLazyImg(); } else { document.addEventListener( 'DOMContentLoaded', rmLazyImg ); }

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
	function vtEsc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = ( s == null ) ? '' : String( s );
		return d.innerHTML;
	}
	function openVariant( row ) {
		var vp = row.closest( '.rm-vp' ); if ( ! vp ) { return; }
		var modal = vp.querySelector( '.rm-vt-modal' );
		var body = modal && modal.querySelector( '.rm-vt-body' );
		if ( ! modal || ! body ) { return; }
		// Built from the row's own cells + table headers (no per-row JSON blob).
		var heads = vp.querySelectorAll( 'thead th' );
		var cells = row.children;
		var imgEl = row.querySelector( '.vt-thumb img' );
		var imgU = imgEl ? imgEl.getAttribute( 'src' ) : '';
		var codeEl = row.querySelector( '.vt-code' );
		var code = codeEl ? codeEl.textContent : '';
		var descEl = row.querySelector( '.vt-desc' );
		var desc = descEl ? descEl.textContent : '';
		var ldt = '', ds = '';
		row.querySelectorAll( '.vt-dl a' ).forEach( function ( a ) {
			var lbl = ( a.getAttribute( 'aria-label' ) || '' ).toLowerCase();
			if ( lbl.indexOf( 'ldt' ) > -1 ) { ldt = a.href; } else { ds = a.href; }
		} );
		var img = imgU ? '<div class="vt-d-img"><img src="' + encodeURI( imgU ) + '" alt="' + vtEsc( code ) + '"></div>' : '';
		var html = '<div class="vt-detail"><div class="vt-d-head">' + img
			+ '<div class="vt-d-head-t"><p class="vt-d-eyebrow">Order code</p><h3 class="vt-d-code">' + vtEsc( code ) + '</h3>'
			+ ( desc ? '<p class="vt-d-desc">' + vtEsc( desc ) + '</p>' : '' ) + '</div></div>';
		var specRows = '';
		for ( var i = 0; i < cells.length; i++ ) {
			if ( cells[ i ].classList && cells[ i ].classList.contains( 'vt-spec' ) ) {
				var label = heads[ i ] ? heads[ i ].textContent : '';
				var val = cells[ i ].textContent;
				if ( val && val !== '–' ) { specRows += '<div class="vt-d-row"><dt>' + vtEsc( label ) + '</dt><dd>' + vtEsc( val ) + '</dd></div>'; }
			}
		}
		if ( specRows ) {
			html += '<dl class="vt-d-specs">' + specRows + '</dl>';
		} else {
			html += '<p class="vt-d-empty">No specification recorded for this variant yet.</p>';
		}
		html += '<div class="vt-d-acts">'
			+ ( ldt ? '<a class="btn btn-line-d" href="' + encodeURI( ldt ) + '" target="_blank" rel="noopener">LDT file ↓</a>' : '' )
			+ ( ds ? '<a class="btn btn-solid" href="' + encodeURI( ds ) + '" target="_blank" rel="noopener">Download datasheet ↓</a>' : '' )
			+ '</div></div>';
		body.innerHTML = html;
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

	/* ---- Content image lightbox (article / news / project body images) ---- */
	function rmImgLb() {
		var lb = document.getElementById( 'rm-imglb' );
		if ( ! lb ) {
			lb = document.createElement( 'div' );
			lb.id = 'rm-imglb';
			lb.className = 'rm-imglb';
			lb.hidden = true;
			lb.innerHTML = '<button type="button" class="rm-imglb-x" aria-label="Close">&times;</button><img class="rm-imglb-img" alt="">';
			document.body.appendChild( lb );
			lb.addEventListener( 'click', function ( e ) {
				if ( e.target === lb || e.target.classList.contains( 'rm-imglb-x' ) ) {
					lb.hidden = true; document.body.style.overflow = '';
				}
			} );
		}
		return lb;
	}
	document.addEventListener( 'click', function ( e ) {
		var img = e.target.closest( '.rm-news-body img, .rm-apage-gallery img, .rm-apage-wysiwyg img, .rm-proj-body img, .rm-projgal-feature img, .rm-projgal-simple img' );
		if ( ! img || img.closest( 'a' ) ) { return; }
		var lb = rmImgLb(), li = lb.querySelector( '.rm-imglb-img' );
		li.src = img.currentSrc || img.src;
		lb.hidden = false; document.body.style.overflow = 'hidden';
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Escape' ) { return; }
		var lb = document.getElementById( 'rm-imglb' );
		if ( lb && ! lb.hidden ) { lb.hidden = true; document.body.style.overflow = ''; }
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
			// White-background photo (opaque, near-white corners): give the frame a
			// white backdrop so the baked-in white blends in instead of showing as a
			// white box on the grey frame. Transparent cut-outs keep the grey frame.
			var whiteBg = false;
			if ( ! transparent ) {
				var corner = function ( px, py ) { var o = ( py * s + px ) * 4; return d[ o ] > 245 && d[ o + 1 ] > 245 && d[ o + 2 ] > 245; };
				whiteBg = corner( 0, 0 ) && corner( s - 1, 0 ) && corner( 0, s - 1 ) && corner( s - 1, s - 1 );
			}
			var viz = img.closest && img.closest( '.rm-cfg-viz' );
			if ( viz ) { viz.classList.toggle( 'rm-viz-white', whiteBg ); }
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

/* ---- Global broken-image fallback ----
 * Any product/content image whose file is missing (e.g. not yet pulled from the
 * old site) would otherwise show a broken icon or a blank tile. Swap broken
 * <img>s to the placeholder (hide small thumbnails), and give background-image
 * tiles the placeholder too. Catches every render path in one place. */
( function () {
	var PH = ( window.rmGallery && window.rmGallery.ph ) || '';
	// A failed "-rmwebp.webp" twin (WebP generation is dormant while the disk is
	// full, so many twins don't exist) must fall back to the ORIGINAL image — which
	// does exist — BEFORE ever showing the placeholder. This also fixes already
	// page-cached HTML that has the twin URLs baked in.
	function origAlts( url ) {
		if ( ! url || ! /-rmwebp\.webp/i.test( url ) ) { return []; }
		return [
			url.replace( /-rmwebp\.webp/i, '.png' ),
			url.replace( /-rmwebp\.webp/i, '.jpg' ),
			url.replace( /-rmwebp\.webp/i, '.jpeg' )
		];
	}
	function placeImg( img ) {
		img.dataset.rmfb = '1';
		var thumb = img.closest && img.closest( '.rm-cfg-thumb' );
		if ( thumb ) { thumb.style.display = 'none'; return; }
		if ( PH ) { img.src = PH; } else { img.style.visibility = 'hidden'; }
	}
	function fixImg( img ) {
		if ( ! img || img.dataset.rmfb ) { return; }
		var src = img.getAttribute( 'src' ) || '';
		if ( img.dataset.rmtwin === undefined && /-rmwebp\.webp/i.test( src ) ) {
			img.dataset.rmtwin = src; // remember the twin to build the alt chain.
		}
		if ( img.dataset.rmtwin !== undefined ) {
			var alts = origAlts( img.dataset.rmtwin );
			var n    = img.dataset.rmalt === undefined ? 0 : ( parseInt( img.dataset.rmalt, 10 ) + 1 );
			if ( alts[ n ] ) { img.dataset.rmalt = String( n ); img.src = alts[ n ]; return; }
		}
		placeImg( img );
	}
	// Live errors (capture phase so it fires for any img).
	document.addEventListener( 'error', function ( e ) {
		var t = e.target;
		if ( t && t.tagName === 'IMG' ) { fixImg( t ); }
	}, true );
	function setBgFallback( el, alts, i ) {
		if ( i >= alts.length ) { el.dataset.rmfb = '1'; if ( PH ) { el.style.backgroundImage = 'url(' + PH + ')'; } return; }
		var u = alts[ i ], p = new Image();
		p.onload  = function () { el.style.backgroundImage = 'url(' + u + ')'; };
		p.onerror = function () { setBgFallback( el, alts, i + 1 ); };
		p.src = u;
	}
	function bgTile( el ) {
		if ( ! el || el.dataset.rmfb ) { return; }
		var s = el.style.backgroundImage || '';
		var m = s.match( /url\(["']?(.*?)["']?\)/i );
		if ( ! m || ! m[1] ) { return; }
		var url = m[1], probe = new Image();
		probe.onerror = function () { setBgFallback( el, origAlts( url ), 0 ); };
		probe.src = url;
	}
	function scan() {
		// Images that already failed before this script ran.
		document.querySelectorAll( 'img' ).forEach( function ( img ) {
			if ( img.complete && img.naturalWidth === 0 && img.getAttribute( 'src' ) ) { fixImg( img ); }
		} );
		// Background-image tiles (related/accessories, category & project cards).
		document.querySelectorAll( '.rm-relc-img,.rm-projcard,.rm-catcard-img,.rm-searchcard' ).forEach( bgTile );
	}
	if ( document.readyState !== 'loading' ) { scan(); }
	else { document.addEventListener( 'DOMContentLoaded', scan ); }
} )();

/* ---- Thumbnail rail: match the main image height and add tiny up/down arrows
 * (never a scrollbar) when there are more thumbnails than fit. ---- */
( function () {
	function build( wrap ) {
		var thumbs = wrap.querySelector( '.rm-gthumbs' );
		if ( ! thumbs || thumbs.dataset.railed ) { return wrap._rmrail || null; }
		thumbs.dataset.railed = '1';
		var rail = document.createElement( 'div' );
		rail.className = 'rm-thumbrail';
		thumbs.parentNode.insertBefore( rail, thumbs );
		var up = document.createElement( 'button' );
		up.type = 'button'; up.className = 'rm-thumbrail-btn rm-thumbrail-up';
		up.setAttribute( 'aria-label', 'Scroll thumbnails up' ); up.innerHTML = '▲';
		var dn = document.createElement( 'button' );
		dn.type = 'button'; dn.className = 'rm-thumbrail-btn rm-thumbrail-dn';
		dn.setAttribute( 'aria-label', 'Scroll thumbnails down' ); dn.innerHTML = '▼';
		rail.appendChild( up ); rail.appendChild( thumbs ); rail.appendChild( dn );
		function step( d ) { thumbs.scrollBy( { top: d * Math.round( thumbs.clientHeight * 0.85 ), behavior: 'smooth' } ); }
		up.addEventListener( 'click', step.bind( null, -1 ) );
		dn.addEventListener( 'click', step.bind( null, 1 ) );
		thumbs.addEventListener( 'scroll', function () { ends( wrap ); } );
		wrap._rmrail = { rail: rail, up: up, dn: dn, thumbs: thumbs };
		return wrap._rmrail;
	}
	function ends( wrap ) {
		var r = wrap._rmrail; if ( ! r ) { return; }
		r.up.classList.toggle( 'rm-off', r.thumbs.scrollTop <= 1 );
		r.dn.classList.toggle( 'rm-off', r.thumbs.scrollTop + r.thumbs.clientHeight >= r.thumbs.scrollHeight - 1 );
	}
	function sync( wrap ) {
		var r = build( wrap ); if ( ! r ) { return; }
		var stage = wrap.querySelector( '.rm-cfg-viz' ) || wrap.querySelector( '.rm-cfg-stage' );
		if ( window.matchMedia( '(max-width:600px)' ).matches || ! stage ) {
			r.rail.classList.add( 'rm-thumbrail--flat' );
			r.rail.style.height = ''; r.up.style.display = r.dn.style.display = 'none';
			return;
		}
		r.rail.classList.remove( 'rm-thumbrail--flat' );
		// Height is matched to the main image in CSS (same min(56vh,520px)); here we
		// only decide whether the up/down arrows are needed.
		r.up.style.display = r.dn.style.display = 'none';
		if ( r.thumbs.scrollHeight > r.thumbs.clientHeight + 2 ) {
			r.up.style.display = r.dn.style.display = '';
		}
		ends( wrap );
	}
	function all() { document.querySelectorAll( '.rm-pdp-gallery' ).forEach( sync ); }
	function init() {
		all();
		window.addEventListener( 'load', all );
		window.addEventListener( 'resize', all );
		document.querySelectorAll( '.rm-pdp-gallery .rm-cfg-img' ).forEach( function ( im ) {
			im.addEventListener( 'load', all );
		} );
	}
	if ( document.readyState !== 'loading' ) { init(); }
	else { document.addEventListener( 'DOMContentLoaded', init ); }
} )();
