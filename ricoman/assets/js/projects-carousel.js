/**
 * Projects showcase carousel — prev/next arrows scroll the card track; the track
 * also scrolls/swipes natively. Arrows hide at each end.
 */
(function () {
	'use strict';

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
		}
		if (prev) { prev.addEventListener('click', function () { track.scrollBy({ left: -stepPx() * 1.5, behavior: 'smooth' }); }); }
		if (next) { next.addEventListener('click', function () { track.scrollBy({ left: stepPx() * 1.5, behavior: 'smooth' }); }); }
		track.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update);
		update();
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('.rm-projshow-view'), init); }
	if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
})();
