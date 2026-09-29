/**
 * CPD booking form. Attaches to each .rm-cpdform, reveals the address field when
 * "Your location" is chosen, validates, submits via AJAX (rm_cpd), then redirects
 * to a thank-you page (data-redirect) or shows an inline thank-you.
 */
(function () {
	'use strict';

	function emailOk(v) { return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test((v || '').trim()); }

	function init(f) {
		if (f.__cpdInit) { return; }
		f.__cpdInit = true;

		var g = function (n) { var el = f.querySelector('[name=' + n + ']'); return el ? (el.value || '').trim() : ''; };
		var loc = f.querySelector('[data-cpd-location]');
		var addr = f.querySelector('[data-cpd-address]');

		// Show the address field only when the visitor picks their own location.
		function syncAddress() {
			if (!loc || !addr) { return; }
			var own = /your location/i.test(loc.value || '');
			addr.hidden = !own;
		}
		if (loc) { loc.addEventListener('change', syncAddress); }
		syncAddress();

		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var hp = f.querySelector('[name=rm_hp]');
			if (hp && hp.value) { return; }

			var msg = f.querySelector('.rm-tradeform-msg');
			var btn = f.querySelector('button[type=submit]');
			function err(t) { if (msg) { msg.hidden = false; msg.className = 'rm-tradeform-msg rm-tradeform-msg--err'; msg.textContent = t; } }

			if (!g('firstname') || !g('lastname') || !g('company') || !g('jobrole') || !emailOk(g('email'))) {
				err('Please enter your name, company, job role and a valid email.');
				return;
			}

			var body = new URLSearchParams();
			body.set('action', 'rm_cpd');
			body.set('nonce', f.dataset.nonce || '');
			body.set('ts', f.dataset.ts || '');
			['session', 'location', 'address', 'firstname', 'lastname', 'company', 'jobrole', 'email', 'phone', 'message'].forEach(function (n) { body.set(n, g(n)); });

			if (btn) { btn.disabled = true; }
			fetch(f.dataset.ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					if (r && r.success) {
						if (f.dataset.redirect) { window.location.href = f.dataset.redirect; return; }
						f.innerHTML = '<div class="rm-tradeform-done"><span class="rm-tradeform-tick" aria-hidden="true"></span><h2 class="rm-tradeform-h">Thanks — your CPD enquiry has been received.</h2><p>Our team will be in touch to confirm the date and details.</p></div>';
					} else {
						if (btn) { btn.disabled = false; }
						err((r && r.data && r.data.msg) || 'Sorry, something went wrong — please try again.');
					}
				})
				.catch(function () { if (btn) { btn.disabled = false; } err('Sorry, something went wrong — please try again.'); });
		});
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('.rm-cpdform'), init); }
	if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
})();
