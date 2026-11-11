/**
 * "Apply for a trade account" form.
 * Attaches directly to each .rm-tradeform, validates, submits via AJAX into the
 * rm_trade action, then either redirects to a thank-you page (data-redirect, for
 * ad conversion tracking) or shows an inline thank-you.
 */
(function () {
	'use strict';

	function emailOk(v) { return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test((v || '').trim()); }

	function init(f) {
		if (f.__tradeInit) { return; }
		f.__tradeInit = true;

		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var hp = f.querySelector('[name=rm_hp]');
			if (hp && hp.value) { return; }

			var msg = f.querySelector('.rm-tradeform-msg');
			var btn = f.querySelector('button[type=submit]');
			var g = function (n) { var el = f.querySelector('[name=' + n + ']'); return el ? (el.value || '').trim() : ''; };

			function err(t) { if (msg) { msg.hidden = false; msg.className = 'rm-tradeform-msg rm-tradeform-msg--err'; msg.textContent = t; } }

			var phoneDigits = (g('phone').match(/\d/g) || []).length;
			if (!g('applicant_name') || !g('company') || !emailOk(g('email')) || phoneDigits < 7) {
				err('Please fill in your name, company, a valid email and phone number.');
				return;
			}

			var body = new URLSearchParams();
			body.set('action', 'rm_trade');
			body.set('nonce', f.dataset.nonce || '');
			body.set('ts', f.dataset.ts || '');
			['applicant_name', 'company', 'job', 'email', 'phone'].forEach(function (n) { body.set(n, g(n)); });

			if (btn) { btn.disabled = true; }
			fetch(f.dataset.ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					if (r && r.success) {
						if (f.dataset.redirect) { window.location.href = f.dataset.redirect; return; }
						f.innerHTML = '<div class="rm-tradeform-done"><span class="rm-tradeform-tick" aria-hidden="true"></span><h2 class="rm-tradeform-h">Thank you — your application has been received.</h2><p>Our team will review it and be in touch shortly.</p></div>';
					} else {
						if (btn) { btn.disabled = false; }
						err((r && r.data && r.data.msg) || 'Sorry, something went wrong — please try again.');
					}
				})
				.catch(function () { if (btn) { btn.disabled = false; } err('Sorry, something went wrong — please try again.'); });
		});
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('.rm-tradeform'), init); }
	if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
})();
