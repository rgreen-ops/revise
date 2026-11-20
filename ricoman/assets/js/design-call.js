/**
 * "Request a 30-minute call with our lighting designers" form.
 * Attaches to each .rm-callform, validates, submits via AJAX (rm_designcall),
 * then redirects to a thank-you page (data-redirect) or shows an inline thank-you.
 */
(function () {
	'use strict';

	function emailOk(v) { return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test((v || '').trim()); }

	function init(f) {
		if (f.__callInit) { return; }
		f.__callInit = true;

		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var hp = f.querySelector('[name=rm_hp]');
			if (hp && hp.value) { return; }

			var msg = f.querySelector('.rm-tradeform-msg');
			var btn = f.querySelector('button[type=submit]');
			var g = function (n) { var el = f.querySelector('[name=' + n + ']'); return el ? (el.value || '').trim() : ''; };
			function err(t) { if (msg) { msg.hidden = false; msg.className = 'rm-tradeform-msg rm-tradeform-msg--err'; msg.textContent = t; } }

			var phoneDigits = (g('phone').match(/\d/g) || []).length;
			if (!g('reqname') || !emailOk(g('email')) || phoneDigits < 7) {
				err('Please enter your name, a valid email and phone number.');
				return;
			}

			var body = new URLSearchParams();
			body.set('action', 'rm_designcall');
			body.set('nonce', f.dataset.nonce || '');
			body.set('ts', f.dataset.ts || '');
			['reqname', 'email', 'phone', 'preferred', 'message'].forEach(function (n) { body.set(n, g(n)); });

			if (btn) { btn.disabled = true; }
			fetch(f.dataset.ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					if (r && r.success) {
						if (f.dataset.redirect) { window.location.href = f.dataset.redirect; return; }
						f.innerHTML = '<div class="rm-tradeform-done"><span class="rm-tradeform-tick" aria-hidden="true"></span><h2 class="rm-tradeform-h">Thanks — your call request has been received.</h2><p>Our lighting design team will be in touch to arrange a time.</p></div>';
					} else {
						if (btn) { btn.disabled = false; }
						err((r && r.data && r.data.msg) || 'Sorry, something went wrong — please try again.');
					}
				})
				.catch(function () { if (btn) { btn.disabled = false; } err('Sorry, something went wrong — please try again.'); });
		});
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('.rm-callform'), init); }
	if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
})();
