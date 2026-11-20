/**
 * Ricoman tabs — progressive enhancement for the "Why Ricoman" tabbed band
 * (pattern: inc/tabs.php). Each .rm-tabs group holds a series of .rm-tabpanel
 * blocks, each led by a .rm-tab-title heading. This builds a tablist from those
 * titles, shows one panel at a time and wires up click + keyboard control.
 *
 * If this script never runs, every panel stays visible (the .rm-tabs-ready hook
 * in the CSS only hides them once we've taken over) — so there's a clean no-JS
 * fallback and the editor shows all panels for editing.
 */
(function () {
	'use strict';

	var uid = 0;

	function init(root) {
		if (root.__rmTabs) { return; }
		root.__rmTabs = true;

		// Direct-child panels only (so nested columns/groups don't get picked up).
		var panels = [];
		Array.prototype.forEach.call(root.children, function (c) {
			if (c.classList && c.classList.contains('rm-tabpanel')) { panels.push(c); }
		});
		if (panels.length < 2) { return; } // not a tab set

		var group = 'rmtab-' + (++uid);
		var nav = document.createElement('div');
		nav.className = 'rm-tabs-nav';
		nav.setAttribute('role', 'tablist');

		var tabs = [];

		panels.forEach(function (panel, i) {
			var titleEl = panel.querySelector('.rm-tab-title') || panel.querySelector('.wp-block-heading, h2, h3, h4');
			var label = titleEl ? titleEl.textContent.trim() : ('Section ' + (i + 1));
			var tabId = group + '-tab-' + i;
			var panelId = group + '-panel-' + i;

			panel.id = panelId;
			panel.setAttribute('role', 'tabpanel');
			panel.setAttribute('aria-labelledby', tabId);
			panel.setAttribute('tabindex', '0');

			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'rm-tab';
			btn.id = tabId;
			btn.textContent = label;
			btn.setAttribute('role', 'tab');
			btn.setAttribute('aria-controls', panelId);
			btn.addEventListener('click', function () { activate(i, true); });
			btn.addEventListener('keydown', function (e) {
				var n;
				if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { n = (i + 1) % panels.length; }
				else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { n = (i - 1 + panels.length) % panels.length; }
				else if (e.key === 'Home') { n = 0; }
				else if (e.key === 'End') { n = panels.length - 1; }
				else { return; }
				e.preventDefault();
				activate(n, true);
			});

			nav.appendChild(btn);
			tabs.push(btn);
		});

		function activate(idx, focus) {
			panels.forEach(function (p, j) { p.classList.toggle('is-active', j === idx); });
			tabs.forEach(function (t, j) {
				var on = j === idx;
				t.classList.toggle('is-active', on);
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.setAttribute('tabindex', on ? '0' : '-1');
				if (on && focus) { t.focus(); }
			});
		}

		root.insertBefore(nav, root.firstChild);
		root.classList.add('rm-tabs-ready');
		activate(0, false);
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('.rm-tabs'), init); }
	if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
})();
