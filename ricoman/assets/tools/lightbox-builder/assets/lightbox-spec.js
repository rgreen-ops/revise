/**
 * Light Box Builder — route "Send this spec" to the server (lead + email to
 * sales) instead of a mailto. Loaded after lightbox-builder.js.
 *
 * Intercepts the .lbx-form submit in the capture phase (so the builder's own
 * mailto handler never runs), gathers the design spec + build code + notes +
 * the shareable design link, and POSTs to admin-ajax (rm_lightbox_spec). Shows
 * an inline thank-you on success.
 */
(function () {
	'use strict';
	var AJAX = '/wp-admin/admin-ajax.php';
	var TS = Math.floor(Date.now() / 1000);

	function emailOk(v) { return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test((v || '').trim()); }
	function txt(sel) { var el = document.querySelector(sel); return el ? (el.innerText || el.textContent || '').trim() : ''; }

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form || !form.classList || !form.classList.contains('lbx-form')) { return; }
		// Take over from the builder's mailto handler.
		e.preventDefault();
		e.stopImmediatePropagation();

		var g = function (n) { var el = form.querySelector('[name=' + n + ']'); return el ? (el.value || '').trim() : ''; };
		var email = g('to');
		var name = g('customer');
		var notes = g('notes');

		var msg = form.querySelector('.rm-lbx-msg');
		if (!msg) { msg = document.createElement('p'); msg.className = 'rm-lbx-msg'; msg.setAttribute('role', 'status'); form.appendChild(msg); }
		function err(t) { msg.textContent = t; msg.style.color = '#b32d2e'; }

		if (!emailOk(email)) { err('Please enter a valid email so we can send your quote.'); return; }

		var code = txt('.lbx-code').replace(/^build code:\s*/i, '');
		var spec = txt('.lbx-spec');
		var summary = txt('.lbx-form-spec');
		var design = window.location.href;

		var btn = form.querySelector('[type=submit]');
		if (btn) { btn.disabled = true; }
		msg.style.color = ''; msg.textContent = 'Sending…';

		var body = new URLSearchParams();
		body.set('action', 'rm_lightbox_spec');
		body.set('ts', String(TS));
		body.set('rm_hp', '');
		body.set('email', email);
		body.set('customer', name);
		body.set('notes', notes);
		body.set('code', code);
		body.set('summary', summary);
		body.set('spec', spec);
		body.set('design', design);

		fetch(AJAX, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
			.then(function (r) { return r.json(); })
			.then(function (r) {
				if (r && r.success) {
					form.innerHTML = '<div class="rm-lbx-done"><strong>Thanks — your spec is on its way.</strong><br>Our team will be in touch with a firm quote. We&rsquo;ve emailed you a copy for your records.</div>';
				} else {
					if (btn) { btn.disabled = false; }
					err((r && r.data && r.data.msg) || 'Sorry, something went wrong — please try again.');
				}
			})
			.catch(function () { if (btn) { btn.disabled = false; } err('Network error — please try again.'); });
	}, true);
})();
