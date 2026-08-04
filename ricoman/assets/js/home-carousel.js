/**
 * Home feature carousel — progressively enhances any .rm-homecaro-track (a row
 * of editable cards) into a swipeable carousel: wraps it, adds prev/next arrows
 * (top-right), wires smooth scrolling, mouse drag-to-scroll, and end-state
 * dimming. The cards stay editable core blocks; this only adds the chrome.
 */
(function () {
	'use strict';

	function stepPx( track ) {
		var card = track.querySelector( '.rm-homecaro-card' );
		return card ? ( card.getBoundingClientRect().width + 20 ) : 320;
	}

	function arrow( cls, label, glyph ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = 'rm-homecaro-arrow ' + cls;
		b.setAttribute( 'aria-label', label );
		b.innerHTML = glyph;
		return b;
	}

	function init( track ) {
		if ( track.__hcInit ) { return; }
		track.__hcInit = true;

		// Wrap the track so the arrow bar can sit above it.
		var view = document.createElement( 'div' );
		view.className = 'rm-homecaro-view';
		track.parentNode.insertBefore( view, track );

		var bar  = document.createElement( 'div' );
		bar.className = 'rm-homecaro-bar';
		var prev = arrow( 'rm-homecaro-prev', 'Previous', '&#8592;' );
		var next = arrow( 'rm-homecaro-next', 'Next', '&#8594;' );
		bar.appendChild( prev );
		bar.appendChild( next );

		view.appendChild( bar );
		view.appendChild( track );

		function update() {
			prev.classList.toggle( 'is-off', track.scrollLeft <= 4 );
			next.classList.toggle( 'is-off', ( track.scrollLeft + track.clientWidth ) >= ( track.scrollWidth - 4 ) );
		}
		prev.addEventListener( 'click', function () { track.scrollBy( { left: -stepPx( track ) * 1.5, behavior: 'smooth' } ); } );
		next.addEventListener( 'click', function () { track.scrollBy( { left: stepPx( track ) * 1.5, behavior: 'smooth' } ); } );
		track.addEventListener( 'scroll', update, { passive: true } );
		window.addEventListener( 'resize', update );

		// Mouse drag-to-scroll (touch uses native overflow scrolling).
		var down = false, startX = 0, startLeft = 0, moved = false;
		track.addEventListener( 'pointerdown', function ( e ) {
			if ( e.pointerType !== 'mouse' ) { return; }
			down = true; moved = false; startX = e.clientX; startLeft = track.scrollLeft;
		} );
		track.addEventListener( 'pointermove', function ( e ) {
			if ( ! down ) { return; }
			var dx = e.clientX - startX;
			if ( Math.abs( dx ) > 4 ) { moved = true; }
			track.scrollLeft = startLeft - dx;
		} );
		window.addEventListener( 'pointerup', function () { down = false; } );
		// Swallow the click that ends a drag so it doesn't follow the card link.
		track.addEventListener( 'click', function ( e ) {
			if ( moved ) { e.preventDefault(); e.stopPropagation(); }
		}, true );

		// Nothing to scroll (everything fits) → hide the controls.
		if ( track.scrollWidth <= track.clientWidth + 4 ) { bar.style.display = 'none'; }
		update();
	}

	function boot() { Array.prototype.forEach.call( document.querySelectorAll( '.rm-homecaro-track' ), init ); }
	if ( document.readyState !== 'loading' ) { boot(); } else { document.addEventListener( 'DOMContentLoaded', boot ); }
})();
