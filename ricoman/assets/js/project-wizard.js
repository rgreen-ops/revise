/**
 * "Tell us about your project" multi-step wizard.
 * Client-side step navigation + validation; submits via FormData (supports the
 * optional file) to the rm_wizard AJAX action. Server re-validates everything.
 */
(function () {
	'use strict';

	function emailOk(v) {
		return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test((v || '').trim());
	}

	function init(form) {
		if (form.__wizInit) { return; }
		form.__wizInit = true;

		var steps = Array.prototype.slice.call(form.querySelectorAll('.rm-wiz-step'));
		var done  = form.querySelector('.rm-wiz-done');
		var cur   = 0;

		function show(i) {
			steps.forEach(function (s, idx) { s.hidden = (idx !== i); });
			cur = i;
			try { form.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) { form.scrollIntoView(); }
		}

		// Required-field check for a given step element.
		function stepValid(step) {
			var s = parseInt(step.getAttribute('data-step'), 10);
			if (s === 1 || s === 3) {
				return step.querySelectorAll('input[type=checkbox]:checked').length > 0;
			}
			if (s === 2) {
				if (step.querySelectorAll('input[type=checkbox]:checked').length > 0) { return true; }
				var other = step.querySelector('[name=priorities_other]');
				return !!(other && other.value.trim() !== '');
			}
			if (s === 5) {
				var n = step.querySelector('[name=name]');
				var em = step.querySelector('[name=email]');
				return n && em && n.value.trim() !== '' && emailOk(em.value);
			}
			return true; // step 4 is optional.
		}

		function fail(step) {
			var err = step.querySelector('.rm-wiz-err');
			if (err) { err.hidden = false; }
		}

		// Selection state on the image cards + clear the error once something is chosen.
		form.addEventListener('change', function (e) {
			var card = e.target.closest && e.target.closest('.rm-wiz-card');
			if (card) { card.classList.toggle('on', !!e.target.checked); }
			var step = e.target.closest && e.target.closest('.rm-wiz-step');
			if (step) { var err = step.querySelector('.rm-wiz-err'); if (err) { err.hidden = true; } }
			if (e.target.type === 'file') {
				var lbl = form.querySelector('.rm-wiz-filename');
				if (lbl) { lbl.textContent = (e.target.files && e.target.files[0]) ? e.target.files[0].name : ''; }
			}
		});

		form.addEventListener('click', function (e) {
			var next = e.target.closest && e.target.closest('.rm-wiz-next');
			var back = e.target.closest && e.target.closest('.rm-wiz-back');
			if (next) {
				e.preventDefault();
				if (!stepValid(steps[cur])) { fail(steps[cur]); return; }
				if (cur < steps.length - 1) { show(cur + 1); }
			}
			if (back) {
				e.preventDefault();
				if (cur > 0) { show(cur - 1); }
			}
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var hp = form.querySelector('[name=rm_hp]');
			if (hp && hp.value) { return; } // bot.
			if (!stepValid(steps[cur])) { fail(steps[cur]); return; }

			var btn = form.querySelector('.rm-wiz-submit');
			var msg = form.querySelector('.rm-wiz-submsg');
			if (btn) { btn.disabled = true; }

			var fd = new FormData(form);
			fd.set('action', 'rm_wizard');
			fd.set('nonce', form.dataset.nonce || '');
			fd.set('ts', form.dataset.ts || '');

			fetch(form.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					if (r && r.success) {
						if (form.dataset.redirect) { window.location.href = form.dataset.redirect; return; }
						steps.forEach(function (s) { s.hidden = true; });
						if (done) { done.hidden = false; }
						try { form.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e2) {}
					} else {
						if (btn) { btn.disabled = false; }
						if (msg) { msg.hidden = false; msg.textContent = (r && r.data && r.data.msg) || 'Sorry, something went wrong — please try again.'; }
					}
				})
				.catch(function () {
					if (btn) { btn.disabled = false; }
					if (msg) { msg.hidden = false; msg.textContent = 'Sorry, something went wrong — please try again.'; }
				});
		});

		show(0);
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('.rm-wiz'), init);
	}
	if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
})();
