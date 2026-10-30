/**
 * Light Box Builder — replace the "Send this spec" mailto panel with a proper
 * enquiry form (same fields as the Ricoman contact page) that posts to the
 * server: logs a lead + emails the spec to our team. Loaded after
 * lightbox-builder.js.
 */
(function () {
	'use strict';
	var AJAX = '/wp-admin/admin-ajax.php';
	var TS = Math.floor(Date.now() / 1000);

	function emailOk(v) { return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test((v || '').trim()); }
	function txt(sel) { var el = document.querySelector(sel); return el ? (el.innerText || el.textContent || '').trim() : ''; }

	// Inject styles for the rebuilt form (the builder iframe doesn't load the main
	// theme CSS). Mirrors the contact page: labelled fields, two-column rows, a
	// single black button.
	var css = ''
		+ '.lbx-form .rm-tradeform-h{margin:0 0 .3em;font-size:clamp(1.5rem,3vw,2rem);font-weight:700;line-height:1.1;text-transform:none;letter-spacing:-.02em}'
		+ '.lbx-form .rm-tradeform-row{display:grid;grid-template-columns:1fr 1fr;gap:clamp(16px,3vw,32px)}'
		+ '.lbx-form .rm-tradeform-label{display:block;margin:0 0 18px;font-weight:600;font-size:.9rem;color:var(--ink,#0b0b0c)}'
		+ '.lbx-form .rm-tradeform-label input{display:block;width:100%;margin-top:8px;padding:14px 16px;border:1px solid #b9b7b2;border-radius:8px;font:inherit;font-weight:400;color:var(--ink,#0b0b0c);background:transparent}'
		+ '.lbx-form .rm-tradeform-label input:focus{outline:0;border-color:var(--blue,#004899);box-shadow:0 0 0 3px rgba(61,123,237,.15)}'
		+ '.lbx-form-actions{margin-top:6px}'
		+ '.lbx-send-btn{display:inline-flex;align-items:center;gap:8px;background:#0b0b0c;color:#fff;border:0;border-radius:8px;padding:15px 30px;font:inherit;font-weight:600;font-size:.95rem;letter-spacing:.02em;cursor:pointer}'
		+ '.lbx-send-btn:hover{background:#000}'
		+ '.lbx-send-btn[disabled]{opacity:.6;cursor:default}'
		+ '.rm-lbx-msg{margin:14px 0 0;font-size:.92rem}'
		+ '.rm-lbx-done{padding:8px 0;font-size:1rem;line-height:1.5}'
		+ '@media(max-width:600px){.lbx-form .rm-tradeform-row{grid-template-columns:1fr}}';
	var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

	function fieldRows() {
		return ''
			+ '<div class="rm-tradeform-row">'
			+ '<label class="rm-tradeform-label">Name *<input type="text" name="name" autocomplete="name"></label>'
			+ '<label class="rm-tradeform-label">Company<input type="text" name="company" autocomplete="organization"></label>'
			+ '</div>'
			+ '<div class="rm-tradeform-row">'
			+ '<label class="rm-tradeform-label">Email *<input type="email" name="email" autocomplete="email"></label>'
			+ '<label class="rm-tradeform-label">Phone<input type="tel" name="phone" autocomplete="tel"></label>'
			+ '</div>'
			+ '<label class="rm-tradeform-label">I am a&hellip;'
			+ '<select name="role"><option value="">Please choose</option><option>Architect</option><option>Interior designer</option><option>Design &amp; build</option><option>Electrical contractor</option><option>Specifier</option><option>Other</option></select>'
			+ '</label>'
			+ '<label class="rm-tradeform-label">Tell us about your project'
			+ '<textarea name="notes" rows="4"></textarea>'
			+ '</label>'
			+ '<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px"><label>Website<input type="text" name="rm_hp" tabindex="-1" autocomplete="off"></label></div>'
			+ '<p class="rm-lbx-msg" role="status" hidden></p>'
			+ '<div class="lbx-form-actions"><button type="submit" class="lbx-send-btn">Send your spec &rarr;</button></div>';
	}

	function enhance() {
		var form = document.querySelector('.lbx-form');
		if (!form || form.dataset.rmEnhanced) { return; }
		form.dataset.rmEnhanced = '1';

		// Keep the live build-code summary bar (the builder updates it in place).
		var specBar = form.querySelector('.lbx-form-spec');

		form.innerHTML = '<h2 class="rm-tradeform-h">Send your spec to our team</h2>'
			+ '<p class="rm-lbx-sub">Send us your design and we&rsquo;ll come back with a firm quote. Your build code, full spec and a link back to this design are included automatically.</p>';
		if (specBar) { form.appendChild(specBar); }
		form.insertAdjacentHTML('beforeend', fieldRows());
	}

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form || !form.classList || !form.classList.contains('lbx-form')) { return; }
		e.preventDefault();
		e.stopImmediatePropagation();

		var g = function (n) { var el = form.querySelector('[name=' + n + ']'); return el ? (el.value || '').trim() : ''; };
		var name = g('name'), email = g('email'), company = g('company'), phone = g('phone'), role = g('role'), notes = g('notes'), hp = g('rm_hp');

		var msg = form.querySelector('.rm-lbx-msg');
		function err(t) { if (msg) { msg.hidden = false; msg.style.color = '#b32d2e'; msg.textContent = t; } }

		if (!name || !emailOk(email)) { err('Please enter your name and a valid email so we can send your quote.'); return; }

		var code = txt('.lbx-code').replace(/^build code:\s*/i, '');
		var spec = txt('.lbx-spec');
		var summary = txt('.lbx-form-spec');
		var design = window.location.href;

		var btn = form.querySelector('[type=submit]');
		if (btn) { btn.disabled = true; }
		if (msg) { msg.hidden = false; msg.style.color = ''; msg.textContent = 'Sending…'; }

		var body = new URLSearchParams();
		body.set('action', 'rm_lightbox_spec');
		body.set('ts', String(TS));
		body.set('rm_hp', hp);
		body.set('name', name);
		body.set('email', email);
		body.set('company', company);
		body.set('phone', phone);
		body.set('role', role);
		body.set('notes', notes);
		body.set('code', code);
		body.set('summary', summary);
		body.set('spec', spec);
		body.set('design', design);

		fetch(AJAX, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
			.then(function (r) { return r.json(); })
			.then(function (r) {
				if (r && r.success) {
					form.innerHTML = '<div class="rm-lbx-done"><strong>Thanks &mdash; your spec is on its way.</strong><br>Our team will be in touch with a firm quote, and we&rsquo;ve emailed you a copy for your records.</div>';
				} else {
					if (btn) { btn.disabled = false; }
					err((r && r.data && r.data.msg) || 'Sorry, something went wrong &mdash; please try again.');
				}
			})
			.catch(function () { if (btn) { btn.disabled = false; } err('Network error — please try again.'); });
	}, true);

	// Enhance once the builder has rendered the form; re-run if it's recreated.
	function boot() {
		enhance();
		var obs = new MutationObserver(function () { enhance(); });
		obs.observe(document.body, { childList: true, subtree: true });
	}
	if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
})();
