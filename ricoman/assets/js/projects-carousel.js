/**
 * Projects showcase carousel — prev/next arrows scroll the card track; the track
 * also scrolls/swipes natively. Arrows hide at each end.
 */
(function () {
	'use strict';

	// Load a card's background only when it's needed. The showcase sits well
	// below the fold, and one project's image is a very heavy GIF, so eager
	// backgrounds bloated the initial load (worst on mobile). Images ship as
	// data-bg and are hydrated as they near the viewport.
	function hydrateBg(el) {
		var u = el && el.getAttribute('data-bg');
		if (u) { el.style.backgroundImage = 'url("' + u + '")'; el.removeAttribute('data-bg'); }
	}
	function hydrateNear(track) {
		var r = track.getBoundingClientRect();
		var vh = window.innerHeight || document.documentElement.clientHeight || 0;
		// Skip unless the carousel is genuinely near the vertical viewport — else a
		// below-the-fold track would hydrate its horizontally-visible cards on init.
		if (r.bottom < -400 || r.top > vh + 400) { return; }
		Array.prototype.forEach.call(track.querySelectorAll('.rm-projshow-img[data-bg]'), function (el) {
			var c = el.getBoundingClientRect();
			if (c.right > r.left - 400 && c.left < r.right + 400) { hydrateBg(el); }
		});
	}
	function lazyBackgrounds() {
		if (!document.querySelector('.rm-projshow-img[data-bg]')) { return; }
		// Load any pending card whose box is vertically near the viewport.
		function scan() {
			var vh = window.innerHeight || document.documentElement.clientHeight || 0;
			Array.prototype.forEach.call(document.querySelectorAll('.rm-projshow-img[data-bg]'), function (el) {
				var r = el.getBoundingClientRect();
				if (r.top < vh + 400 && r.bottom > -400) { hydrateBg(el); }
			});
			if (!document.querySelector('.rm-projshow-img[data-bg]')) {
				window.removeEventListener('scroll', onScroll);
				window.removeEventListener('resize', onScroll);
			}
		}
		var ticking = false;
		function onScroll() {
			if (ticking) { return; }
			ticking = true;
			var raf = window.requestAnimationFrame || function (cb) { return setTimeout(cb, 16); };
			raf(function () { ticking = false; scan(); });
		}
		// Primary path: IntersectionObserver where available…
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (e) { if (e.isIntersecting) { hydrateBg(e.target); io.unobserve(e.target); } });
			}, { rootMargin: '400px' });
			Array.prototype.forEach.call(document.querySelectorAll('.rm-projshow-img[data-bg]'), function (el) { io.observe(el); });
		}
		// …plus a passive scroll/resize scan as a bulletproof fallback, and one
		// scan now for anything already near the viewport.
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll);
		scan();
	}

	function init(view) {
		if (view.__pcInit) { return; }
		view.__pcInit = true;

		var track = view.querySelector('.rm-projshow-track');
		var prev = view.querySelector('.rm-projshow-prev');
		var next = view.querySelector('.rm-projshow-next');
		if (!track) { return; }

		function stepPx() {
			var card = track.querySelector('.rm-projshow-card');
			return card ? (card.getBoundingClientRect().width + 20) : 300;
		}
		function update() {
			if (prev) { prev.hidden = track.scrollLeft <= 4; }
			if (next) { next.hidden = (track.scrollLeft + track.clientWidth) >= (track.scrollWidth - 4); }
			hydrateNear(track); // load backgrounds as cards scroll into the carousel's view
		}
		if (prev) { prev.addEventListener('click', function () { track.scrollBy({ left: -stepPx() * 1.5, behavior: 'smooth' }); }); }
		if (next) { next.addEventListener('click', function () { track.scrollBy({ left: stepPx() * 1.5, behavior: 'smooth' }); }); }
		track.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update);
		update();
	}

	function boot() { lazyBackgrounds(); Array.prototype.forEach.call(document.querySelectorAll('.rm-projshow-view'), init); }
	if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
})();
