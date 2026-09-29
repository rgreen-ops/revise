/**
 * Light Box Builder — stretched-fabric (SEG) light box configurator.
 * Mounts into #rm-lbx (see inc/lightbox-builder.php). Pure vanilla JS.
 *
 * Product data below is from the supplier SEG light box brochure:
 *  - Ultra-slim LGP: 16/28/30/45 mm profiles, 100×100 min, 1220×2440 max, LGP CCTs.
 *  - SEG backlit single-sided: profiles 40/50/60/80/100 (alu thickness, kg/m,
 *    light engines), 2.4 × 4 m per section.
 *  - Double-sided: profiles 100/120.
 * Fabric: 100% flame-retardant polyester, dye-sublimation print, silicone edge.
 * NO PRICING — the tool builds a spec and requests a quote.
 */
(function () {
	'use strict';

	var root = document.getElementById('rm-lbx');
	if (!root) { return; }
	var IMG = root.getAttribute('data-img') || '';

	/* ------------------------------------------------------------------ data */

	var TYPES = {
		seg: {
			name: 'SEG backlit', code: 'SEG', sides: 1,
			blurb: 'LEDs behind a stretched fabric face. Any size or shape; big runs are joined in sections.',
			shapes: ['rect', 'rounded', 'circle', 'ring', 'cutout', 'custom'],
			depths: [40, 50, 60, 65, 80, 100],
			lights: ['white', 'tunable', 'rgb', 'rgbw', 'pixel'],
			mounts: ['wall', 'ceiling', 'suspended', 'freestanding'],
			min: 300, secShort: 2400, secLong: 4000, maxLen: 30000
		},
		lgp: {
			name: 'Ultra-slim LGP', code: 'LGP', sides: 1,
			blurb: 'Only 16 to 45 mm deep. An edge-lit light guide panel sits behind the fabric. Made for posters and retail graphics.',
			shapes: ['rect'],
			depths: [16, 28, 30, 45],
			lights: ['white'],
			mounts: ['wall', 'suspended'],
			min: 100, secShort: 1220, secLong: 2440, maxLen: 2440, onePiece: true
		},
		double: {
			name: 'Double-sided', code: 'DS', sides: 2,
			blurb: 'Graphics on both faces. Stands on the floor or hangs from the ceiling, so it can be seen from both sides.',
			shapes: ['rect', 'rounded'],
			depths: [100, 120],
			lights: ['white', 'tunable', 'rgb', 'rgbw'],
			mounts: ['freestanding', 'suspended'],
			min: 500, secShort: 2400, secLong: 4000, maxLen: 12000
		}
	};

	// Frame profiles: visible face (mm), alu wall (mm), frame weight (kg/m), light engines.
	var PROFILES = {
		'lgp-16': { face: 16, engines: ['LGP edge-lit panel'] },
		'lgp-28': { face: 24.5, engines: ['LGP edge-lit panel'] },
		'lgp-30': { face: 32, engines: ['LGP edge-lit panel'] },
		'lgp-45': { face: 44, engines: ['LGP edge-lit panel'] },
		'seg-40': { face: 20, alu: 1.8, kg: 1.05, engines: ['LGP panel', 'Flexible LED sheet', 'LED lattice'] },
		'seg-50': { face: 20, alu: 1.8, kg: 0.85, engines: ['Flexible LED sheet', 'LED lattice'] },
		'seg-60': { face: 20, alu: 1.8, kg: 0.85, engines: ['Flexible LED sheet', 'LED lattice'] },
		'seg-65': { face: 20, engines: ['Edge-lit LED strip'], note: 'remote driver' },
		'seg-80': { face: 22, alu: 2.0, kg: 1.6, engines: ['LED backlight modules', 'Edge-lit LED strip'] },
		'seg-100': { face: 22, alu: 2.0, kg: 1.65, engines: ['LED backlight modules', 'Edge-lit LED strip'] },
		'double-100': { face: 22, alu: 2.0, kg: 1.7, engines: ['Double-sided backlight', 'Side-lit LED strip'] },
		'double-120': { face: 22, alu: 2.0, kg: 1.55, engines: ['Double-sided backlight', 'Side-lit LED strip'] }
	};

	var SHAPES = {
		rect: { name: 'Rectangle', code: 'RCT', icon: '<rect x="3" y="6" width="26" height="20"/>' },
		rounded: { name: 'Rounded', code: 'RND', icon: '<rect x="3" y="6" width="26" height="20" rx="6"/>' },
		circle: { name: 'Circle', code: 'CIR', icon: '<circle cx="16" cy="16" r="12"/>' },
		ring: { name: 'Ring', code: 'RNG', icon: '<path fill-rule="evenodd" d="M4 16a12 12 0 1 0 24 0a12 12 0 1 0-24 0M10 16a6 6 0 1 0 12 0a6 6 0 1 0-12 0"/>' },
		cutout: { name: 'With cut-out', code: 'CUT', icon: '<path fill-rule="evenodd" d="M3 10a4 4 0 0 1 4-4h18a4 4 0 0 1 4 4v12a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4zM11 13h10a3 3 0 0 1 0 6H11a3 3 0 0 1 0-6z"/>' },
		custom: { name: 'Custom', code: 'CUS', icon: '<path d="M4 22C4 10 12 5 18 8s10 2 10 8-6 10-12 10S4 28 4 22z"/>' }
	};

	var LIGHTS = {
		white: { name: 'Static white', code: 'W', minDepth: 0, blurb: 'One fixed colour temperature' },
		tunable: { name: 'Tunable white', code: 'TW', minDepth: 40, blurb: 'Warm to cool, 3000–6500K' },
		rgb: { name: 'RGB colour', code: 'RGB', minDepth: 60, blurb: 'Any colour, whole face at once' },
		rgbw: { name: 'RGBW colour', code: 'RGBW', minDepth: 60, blurb: 'Any colour plus a true white' },
		pixel: { name: 'Pixel addressable RGB', code: 'PX', minDepth: 80, blurb: 'Every pixel its own colour: moving skies, waves, video content' }
	};

	var CONTROLS = {
		onoff: 'Switched on/off',
		dali: 'DALI dimming',
		casambi: 'Casambi (app / Bluetooth)',
		dmx: 'DMX',
		pixel: 'Pixel controller (SPI/DMX) + content'
	};
	var CONTROL_BY_LIGHT = {
		white: ['onoff', 'dali', 'casambi'],
		tunable: ['dali', 'casambi'],
		rgb: ['casambi', 'dmx'],
		rgbw: ['casambi', 'dmx'],
		pixel: ['pixel']
	};

	var GRAPHICS = {
		print: { name: 'Printed graphic', blurb: 'Your artwork, dye-sub printed' },
		white: { name: 'Plain white fabric', blurb: 'Clean, even glow' },
		sky: { name: 'Sky image', blurb: 'Printed sky, for ceilings' }
	};

	var MOUNTS = {
		wall: { name: 'Wall mounted', code: 'WL' },
		ceiling: { name: 'Ceiling (face down)', code: 'CL' },
		suspended: { name: 'Suspended', code: 'SP' },
		freestanding: { name: 'Freestanding', code: 'FS' }
	};

	var FINISHES = {
		silver: { name: 'Silver anodised', code: 'SV', color: '#b9bcc1', note: 'Standard' },
		black: { name: 'Black', code: 'BK', color: '#1f1f22' },
		white: { name: 'White', code: 'WH', color: '#f1f1ef' },
		ral: { name: 'Custom RAL', code: 'RAL', color: '#8a8f96', note: 'Powder coated' }
	};

	var CCT_LGP = [3500, 4300, 5300, 6300];
	var CCT_STD = [3000, 4000, 5000, 6500];

	var PRESETS = [
		{ name: 'Sky ceiling', img: 'lightbox-sky-round.webp', s: { type: 'seg', shape: 'circle', d: 2400, light: 'pixel', graphic: 'white', mount: 'ceiling', finish: 'white' } },
		{ name: 'Halo ring', img: 'lightbox-ring-ceiling.webp', s: { type: 'seg', shape: 'ring', d: 6000, band: 900, light: 'pixel', graphic: 'white', mount: 'ceiling', finish: 'white' } },
		{ name: 'Feature with cut-out', img: 'lightbox-cutout.webp', s: { type: 'seg', shape: 'cutout', w: 4000, h: 2600, r: 500, cw: 1700, ch: 800, light: 'white', cct: 3000, graphic: 'white', mount: 'ceiling', finish: 'black' } },
		{ name: 'RGB ceiling panel', img: 'lightbox-rgb-ceiling.webp', s: { type: 'seg', shape: 'rect', w: 3000, h: 1600, light: 'pixel', graphic: 'white', mount: 'ceiling', finish: 'black' } },
		{ name: 'Retail poster', img: '', s: { type: 'lgp', shape: 'rect', w: 1000, h: 1500, depth: 30, light: 'white', cct: 4300, graphic: 'print', mount: 'wall', finish: 'silver' } }
	];

	/* ----------------------------------------------------------------- state */

	var DEFAULTS = {
		type: 'seg', shape: 'rect', w: 2400, h: 1200, r: 200, d: 1800, band: 600, cw: 1200, ch: 500,
		depth: 60, depthManual: false, engine: '', engineManual: false, light: 'white', cct: 4000, control: 'onoff',
		graphic: 'print', mount: 'wall', finish: 'silver', ral: '', qty: 1
	};
	var S = assign({}, DEFAULTS);
	var ART = null;      // uploaded artwork (data URL) — stays on the device
	var LIT = true;      // preview lights on/off

	function assign(t) {
		for (var i = 1; i < arguments.length; i++) {
			var o = arguments[i] || {};
			for (var k in o) { if (Object.prototype.hasOwnProperty.call(o, k)) { t[k] = o[k]; } }
		}
		return t;
	}
	function num(v, d) { var n = parseFloat(v); return isFinite(n) ? n : d; }
	function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
	function fmt(n) { return Math.round(n).toLocaleString('en-GB'); }
	function fmt1(n) { return (Math.round(n * 10) / 10).toLocaleString('en-GB'); }
	function fmt2(n) { return (Math.round(n * 100) / 100).toLocaleString('en-GB'); }

	function isRound() { return S.shape === 'circle' || S.shape === 'ring'; }
	function bbox() { return isRound() ? { w: S.d, h: S.d } : { w: S.w, h: S.h }; }
	function profile() { return PROFILES[S.type + '-' + S.depth] || {}; }

	/** Recommended depth for the current type / size / light. */
	function recDepth() {
		var T = TYPES[S.type], b = bbox(), shortS = Math.min(b.w, b.h), longS = Math.max(b.w, b.h), d;
		if (S.type === 'lgp') { d = 30; }
		else if (S.type === 'double') { d = longS > 2400 ? 120 : 100; }
		else if (S.light === 'pixel') { d = shortS > 2400 ? 100 : 80; }
		else if (S.light === 'rgbw' || S.light === 'rgb') { d = shortS > 1500 ? 80 : 60; }
		else if (longS <= 1200) { d = 40; }
		else if (longS <= 2400) { d = 60; }
		else { d = 80; }
		var min = LIGHTS[S.light].minDepth;
		var ok = T.depths.filter(function (x) { return x >= min; });
		if (ok.indexOf(d) < 0) { d = ok[0] || T.depths[T.depths.length - 1]; }
		return d;
	}

	/** Recommended light engine for the current build. */
	function recEngine() {
		var p = profile(), e = p.engines || [];
		if (S.light === 'pixel') { return 'Addressable RGB pixel modules (SPI)'; }
		if (S.light === 'rgb') { return 'RGB LED modules'; }
		if (S.light === 'rgbw') { return 'RGBW LED modules'; }
		if (S.type === 'seg' && e.length > 1) {
			var b = bbox(), area = b.w * b.h / 1e6;
			if (e[0] === 'LGP panel' && S.shape === 'rect' && Math.max(b.w, b.h) <= 2440 && Math.min(b.w, b.h) <= 1220) { return 'LGP panel'; }
			if (e.indexOf('LED lattice') >= 0) { return area > 1.5 ? 'LED lattice' : 'Flexible LED sheet'; }
		}
		return e[0] || '';
	}

	/** Make the state valid; returns a list of notes explaining any auto-changes. */
	function normalise() {
		var T = TYPES[S.type], notes = [];
		if (T.shapes.indexOf(S.shape) < 0) { S.shape = 'rect'; }
		if (T.lights.indexOf(S.light) < 0) { S.light = T.lights[0]; }
		if (T.mounts.indexOf(S.mount) < 0) { S.mount = T.mounts[0]; }
		if (S.mount === 'freestanding' && !(S.shape === 'rect' || S.shape === 'rounded')) { S.mount = 'wall'; }

		var ccts = S.type === 'lgp' ? CCT_LGP : CCT_STD;
		if (ccts.indexOf(+S.cct) < 0) { S.cct = S.type === 'lgp' ? 4300 : 4000; }
		var ctrls = CONTROL_BY_LIGHT[S.light];
		if (ctrls.indexOf(S.control) < 0) { S.control = ctrls[0]; }
		if (S.light === 'pixel' && S.graphic === 'print') { /* print over pixels is allowed */ }
		if (T.sides === 2 && S.graphic === 'sky') { S.graphic = 'print'; }

		// Sizes.
		var maxLen = T.maxLen, min = T.min;
		S.w = clamp(Math.round(num(S.w, 1200)), min, maxLen);
		S.h = clamp(Math.round(num(S.h, 1200)), min, T.onePiece ? maxLen : 6000);
		if (T.onePiece) {
			// Must fit 1220 × 2440 either way round.
			var s = Math.min(S.w, S.h), l = Math.max(S.w, S.h);
			if (s > T.secShort || l > T.secLong) {
				notes.push('Ultra-slim LGP boxes are made up to 1220 × 2440 mm. Choose SEG backlit for bigger sizes.');
				if (S.w >= S.h) { S.w = Math.min(S.w, T.secLong); S.h = Math.min(S.h, T.secShort); }
				else { S.h = Math.min(S.h, T.secLong); S.w = Math.min(S.w, T.secShort); }
			}
		}
		S.d = clamp(Math.round(num(S.d, 1800)), 500, 12000);
		S.band = clamp(Math.round(num(S.band, 600)), 200, Math.floor(S.d / 2) - 100);
		S.r = clamp(Math.round(num(S.r, 200)), 20, Math.floor(Math.min(S.w, S.h) / 2));
		S.cw = clamp(Math.round(num(S.cw, 1000)), 200, S.w - 400);
		S.ch = clamp(Math.round(num(S.ch, 400)), 200, S.h - 400);
		if (S.shape === 'cutout' && (S.w < 800 || S.h < 800)) {
			notes.push('A cut-out needs the box to be at least 800 × 800 mm.');
			S.w = Math.max(S.w, 800); S.h = Math.max(S.h, 800);
			S.cw = clamp(S.cw, 200, S.w - 400); S.ch = clamp(S.ch, 200, S.h - 400);
		}
		S.qty = clamp(Math.round(num(S.qty, 1)), 1, 999);

		// Depth: follow the recommendation unless the visitor picked one that still works.
		var minD = LIGHTS[S.light].minDepth;
		S.depth = +S.depth;
		var okDepth = T.depths.indexOf(S.depth) >= 0 && S.depth >= minD;
		if (S.depthManual && !okDepth && T.depths.indexOf(S.depth) >= 0) {
			notes.push(LIGHTS[S.light].name + ' needs at least ' + minD + ' mm depth, so we changed it to ' + recDepth() + ' mm.');
		}
		if (!S.depthManual || !okDepth) { S.depth = recDepth(); S.depthManual = S.depthManual && okDepth; }
		// Light engine: auto unless picked (white / tunable only).
		var eng = profile().engines || [];
		if (S.light !== 'white' && S.light !== 'tunable') { S.engineManual = false; }
		if (!S.engineManual || eng.indexOf(S.engine) < 0) { S.engine = recEngine(); S.engineManual = false; }
		return notes;
	}

	/* -------------------------------------------------------------- geometry */

	function rr(x, y, w, h, r) {
		r = Math.max(0, Math.min(r, w / 2, h / 2));
		if (!r) { return 'M' + x + ',' + y + 'h' + w + 'v' + h + 'h' + (-w) + 'Z'; }
		return 'M' + (x + r) + ',' + y + 'H' + (x + w - r) + 'A' + r + ',' + r + ' 0 0 1 ' + (x + w) + ',' + (y + r) +
			'V' + (y + h - r) + 'A' + r + ',' + r + ' 0 0 1 ' + (x + w - r) + ',' + (y + h) +
			'H' + (x + r) + 'A' + r + ',' + r + ' 0 0 1 ' + x + ',' + (y + h - r) +
			'V' + (y + r) + 'A' + r + ',' + r + ' 0 0 1 ' + (x + r) + ',' + y + 'Z';
	}
	function circ(cx, cy, r) {
		r = Math.max(1, r);
		return 'M' + (cx - r) + ',' + cy + 'A' + r + ',' + r + ' 0 1 0 ' + (cx + r) + ',' + cy + 'A' + r + ',' + r + ' 0 1 0 ' + (cx - r) + ',' + cy + 'Z';
	}
	function cutR() { return Math.min(S.cw, S.ch) * 0.35; }
	function customR() { return Math.min(S.w, S.h) * 0.3; }

	/** Path of the shape, inset by i mm (for the lit fabric inside the frame). */
	function shapeD(i) {
		var W = S.w, H = S.h, D = S.d;
		switch (S.shape) {
			case 'rounded': return rr(i, i, W - 2 * i, H - 2 * i, S.r - i);
			case 'circle': return circ(D / 2, D / 2, D / 2 - i);
			case 'ring': return circ(D / 2, D / 2, D / 2 - i) + circ(D / 2, D / 2, D / 2 - S.band + i);
			case 'cutout': return rr(i, i, W - 2 * i, H - 2 * i, S.r - i) + rr((W - S.cw) / 2 - i, (H - S.ch) / 2 - i, S.cw + 2 * i, S.ch + 2 * i, cutR() + i);
			case 'custom': return rr(i, i, W - 2 * i, H - 2 * i, customR() - i);
			default: return rr(i, i, W - 2 * i, H - 2 * i, 0);
		}
	}

	function metrics() {
		var PI = Math.PI, W = S.w, H = S.h, D = S.d, a, p;
		function rra(w, h, r) { return w * h - (4 - PI) * r * r; }
		function rrp(w, h, r) { return 2 * (w + h) - 8 * r + 2 * PI * r; }
		switch (S.shape) {
			case 'rounded': a = rra(W, H, S.r); p = rrp(W, H, S.r); break;
			case 'circle': a = PI * D * D / 4; p = PI * D; break;
			case 'ring': var di = D - 2 * S.band; a = PI * (D * D - di * di) / 4; p = PI * (D + di); break;
			case 'cutout': a = rra(W, H, S.r) - rra(S.cw, S.ch, cutR()); p = rrp(W, H, S.r) + rrp(S.cw, S.ch, cutR()); break;
			case 'custom': a = rra(W, H, customR()); p = rrp(W, H, customR()); break;
			default: a = W * H; p = 2 * (W + H);
		}
		var T = TYPES[S.type], b = bbox(), sections = 1, split = '';
		if (isRound()) {
			if (D > T.secShort) { sections = 0; split = 'Made in curved segments, joined on site'; }
		} else {
			var o1 = Math.ceil(b.w / T.secLong) * Math.ceil(b.h / T.secShort);
			var o2 = Math.ceil(b.w / T.secShort) * Math.ceil(b.h / T.secLong);
			sections = Math.min(o1, o2);
			if (sections > 1) {
				var nx = o1 <= o2 ? Math.ceil(b.w / T.secLong) : Math.ceil(b.w / T.secShort);
				var ny = o1 <= o2 ? Math.ceil(b.h / T.secShort) : Math.ceil(b.h / T.secLong);
				split = sections + ' sections (' + nx + ' × ' + ny + '), joined on site';
			}
		}
		var kg = profile().kg ? p / 1000 * profile().kg : 0;
		return { area: a / 1e6 * T.sides, face: a / 1e6, perim: p / 1000, kg: kg, sections: sections, split: split };
	}

	/* --------------------------------------------------------------- preview */

	var CCT_COL = { 3000: '#ffe7c2', 3500: '#ffedd2', 4000: '#fff6e8', 4300: '#fff8ee', 5000: '#f8f9ff', 5300: '#f5f8ff', 6300: '#eaf1ff', 6500: '#e8f0ff' };
	var uid = 0;

	function previewSVG() {
		var b = bbox(), W = b.w, H = b.h, big = Math.max(W, H), id = 'lbx' + (++uid);
		var face = Math.max(profile().face || 20, big * 0.007);
		var fin = FINISHES[S.finish].color;
		var ceiling = S.mount === 'ceiling';
		var person = !ceiling && H < 7000;
		var pad = big * 0.09 + 80;
		var floorY = H + (S.mount === 'freestanding' ? 120 : Math.max(500, H * 0.35));
		if (S.mount === 'suspended') { floorY = H + Math.max(900, H * 0.5); }
		var manW = 520, manH = 1750, gap = Math.max(300, W * 0.06);
		var vx = -pad, vy = -pad - (S.mount === 'suspended' ? big * 0.12 : 0);
		var vw = W + pad * 2 + (person ? gap + manW : 0);
		var fs = Math.max(W, H, 1200) * 0.034;
		if (person && floorY - manH < vy + pad * 0.3) { vy = floorY - manH - pad * 0.3; }
		var vh = (person ? Math.max(floorY, H) : H) + fs * 3 - vy;
		var sw = fs * 0.08;

		var s = [];
		s.push('<svg xmlns="http://www.w3.org/2000/svg" viewBox="' + [vx, vy, vw, vh].join(' ') + '" role="img" aria-label="Preview of your light box">');
		s.push('<defs>');
		s.push('<filter id="' + id + 'g" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="' + (big * 0.03) + '"/></filter>');
		s.push('<filter id="' + id + 'b" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="' + (big * 0.06) + '"/></filter>');
		s.push('<clipPath id="' + id + 'c"><path clip-rule="evenodd" d="' + shapeD(face) + '"/></clipPath>');
		s.push('</defs>');

		// Floor / scale context.
		if (person) {
			s.push('<line x1="' + (vx) + '" y1="' + floorY + '" x2="' + (vx + vw) + '" y2="' + floorY + '" class="lbx-floor" stroke-width="' + sw + '"/>');
		}
		if (S.mount === 'suspended') {
			var cy0 = vy;
			[W * 0.15, W * 0.85].forEach(function (x) { s.push('<line x1="' + x + '" y1="' + cy0 + '" x2="' + x + '" y2="0" class="lbx-cable" stroke-width="' + sw + '"/>'); });
		}
		if (S.mount === 'freestanding') {
			[W * 0.18, W * 0.82].forEach(function (x) { s.push('<rect x="' + (x - W * 0.08) + '" y="' + (H) + '" width="' + (W * 0.16) + '" height="' + (floorY - H) + '" rx="20" fill="' + fin + '" class="lbx-foot"/>'); });
		}

		// Glow + frame + fabric.
		var fill = fabricFill(id, W, H);
		if (LIT && S.light !== 'off') {
			s.push('<path fill-rule="evenodd" d="' + shapeD(0) + '" fill="' + fill.glow + '" opacity=".55" filter="url(#' + id + 'g)"/>');
		}
		s.push('<path fill-rule="evenodd" d="' + shapeD(0) + '" fill="' + fin + '" class="lbx-frame"/>');
		s.push('<g clip-path="url(#' + id + 'c)">' + fill.svg + '</g>');
		if (S.shape === 'custom') {
			s.push('<path d="' + shapeD(0) + '" fill="none" stroke="#004899" stroke-dasharray="' + fs * 0.6 + ' ' + fs * 0.4 + '" stroke-width="' + sw * 1.5 + '"/>');
		}

		// Section join lines (straight shapes).
		var m = metrics();
		if (m.sections > 1 && !isRound()) {
			var T = TYPES[S.type], o1 = Math.ceil(W / T.secLong) * Math.ceil(H / T.secShort), o2 = Math.ceil(W / T.secShort) * Math.ceil(H / T.secLong);
			var nx = o1 <= o2 ? Math.ceil(W / T.secLong) : Math.ceil(W / T.secShort);
			var ny = o1 <= o2 ? Math.ceil(H / T.secShort) : Math.ceil(H / T.secLong);
			var g = '<g clip-path="url(#' + id + 'c)" class="lbx-join">';
			for (var i = 1; i < nx; i++) { g += '<line x1="' + (W * i / nx) + '" y1="0" x2="' + (W * i / nx) + '" y2="' + H + '" stroke-width="' + sw + '"/>'; }
			for (var j = 1; j < ny; j++) { g += '<line x1="0" y1="' + (H * j / ny) + '" x2="' + W + '" y2="' + (H * j / ny) + '" stroke-width="' + sw + '"/>'; }
			s.push(g + '</g>');
		}

		// Dimensions.
		var dy = (person ? Math.max(H, floorY) : H) + fs * 1.4, dx = -fs * 1.2, t = fs * 0.35;
		if (isRound()) {
			s.push(dimLine(0, dy, W, dy, 'Ø ' + fmt(S.d) + ' mm', fs, sw, t, false));
			if (S.shape === 'ring') {
				s.push(dimLine(W / 2 + (W / 2 - S.band), H / 2, W, H / 2, fmt(S.band) + ' band', fs * 0.8, sw, t, false, true));
			}
		} else {
			s.push(dimLine(0, dy, W, dy, fmt(S.w) + ' mm', fs, sw, t, false));
			s.push(dimLine(dx, 0, dx, H, fmt(S.h) + ' mm', fs, sw, t, true));
		}
		if (person) { s.push(man(W + gap, floorY, manH, fs)); }
		if (ceiling) {
			s.push('<text x="' + (W / 2) + '" y="' + (vy + pad * 0.55) + '" text-anchor="middle" class="lbx-note" font-size="' + fs * 0.8 + '">Looking up at the ceiling</text>');
		}
		s.push('</svg>');
		return s.join('');
	}

	function dimLine(x1, y1, x2, y2, label, fs, sw, t, vert, inside) {
		var o = '<g class="lbx-dim' + (inside ? ' lbx-dim-in' : '') + '"><line x1="' + x1 + '" y1="' + y1 + '" x2="' + x2 + '" y2="' + y2 + '" stroke-width="' + sw + '"/>';
		if (vert) {
			o += '<line x1="' + (x1 - t) + '" y1="' + y1 + '" x2="' + (x1 + t) + '" y2="' + y1 + '" stroke-width="' + sw + '"/><line x1="' + (x2 - t) + '" y1="' + y2 + '" x2="' + (x2 + t) + '" y2="' + y2 + '" stroke-width="' + sw + '"/>';
			var cy = (y1 + y2) / 2;
			o += '<text x="' + (x1 - fs * 0.45) + '" y="' + cy + '" font-size="' + fs + '" text-anchor="middle" transform="rotate(-90 ' + (x1 - fs * 0.45) + ' ' + cy + ')">' + label + '</text>';
		} else {
			o += '<line x1="' + x1 + '" y1="' + (y1 - t) + '" x2="' + x1 + '" y2="' + (y1 + t) + '" stroke-width="' + sw + '"/><line x1="' + x2 + '" y1="' + (y2 - t) + '" x2="' + x2 + '" y2="' + (y2 + t) + '" stroke-width="' + sw + '"/>';
			o += '<text x="' + ((x1 + x2) / 2) + '" y="' + (y1 + (inside ? -fs * 0.4 : fs * 1.15)) + '" font-size="' + fs + '" text-anchor="middle">' + label + '</text>';
		}
		return o + '</g>';
	}

	/** A 1.75 m person for scale. */
	function man(x, floor, h, fs) {
		var k = h / 1750, head = 115 * k;
		var y0 = floor - h;
		return '<g class="lbx-man" transform="translate(' + x + ' ' + y0 + ')">' +
			'<circle cx="' + (260 * k) + '" cy="' + head + '" r="' + head + '"/>' +
			'<path d="M' + (130 * k) + ' ' + (270 * k) + 'h' + (260 * k) + 'q' + (60 * k) + ' 0 ' + (60 * k) + ' ' + (60 * k) + 'v' + (520 * k) + 'h-' + (70 * k) + 'v' + (900 * k) + 'h-' + (100 * k) + 'v-' + (560 * k) + 'h-' + (20 * k) + 'v' + (560 * k) + 'h-' + (100 * k) + 'v-' + (900 * k) + 'h-' + (70 * k) + 'v-' + (520 * k) + 'q0-' + (60 * k) + ' ' + (60 * k) + '-' + (60 * k) + 'z"/>' +
			'<text x="' + (260 * k) + '" y="' + (h + fs * 1.15) + '" font-size="' + fs * 0.75 + '" text-anchor="middle">1.75 m</text>' +
			'</g>';
	}

	/** The fabric face: artwork / sky / white / colour, lit or unlit. */
	function fabricFill(id, W, H) {
		var big = Math.max(W, H), out = [], glow = '#fff6e8';
		var dur = 'dur="12s" repeatCount="indefinite"';
		if (!LIT) {
			out.push('<rect x="0" y="0" width="' + W + '" height="' + H + '" fill="#e9e7e3"/>');
		}
		if (S.graphic === 'print' && ART) {
			out.push('<image href="' + ART + '" x="0" y="0" width="' + W + '" height="' + H + '" preserveAspectRatio="xMidYMid slice"' + (LIT ? '' : ' opacity=".55"') + '/>');
			glow = '#fff3de';
			if (LIT && S.light === 'white') { out.push('<rect width="' + W + '" height="' + H + '" fill="' + (CCT_COL[S.cct] || '#fff') + '" opacity=".12"/>'); }
			return { svg: out.join(''), glow: glow };
		}
		if (!LIT) {
			if (S.graphic === 'print') { out.push(placeholderArt(W, H, big, false)); }
			if (S.graphic === 'sky') { out.push('<rect width="' + W + '" height="' + H + '" fill="#b9c9d6"/>'); }
			return { svg: out.join(''), glow: glow };
		}
		if (S.light === 'pixel') {
			glow = '#9d7bff';
			out.push('<rect width="' + W + '" height="' + H + '" fill="#6fa8ff"><animate attributeName="fill" values="#6fa8ff;#ff7ad9;#ff9b4d;#7de0ff;#6fa8ff" ' + dur.replace('12s', '16s') + '/></rect>');
			var blobs = [['#ffffff', .2, .3], ['#ffd1f2', .7, .6], ['#fff1c9', .45, .8], ['#c8f3ff', .85, .2]];
			blobs.forEach(function (bl, i) {
				var cx = W * bl[1], cy = H * bl[2], r = big * 0.22;
				out.push('<circle cx="' + cx + '" cy="' + cy + '" r="' + r + '" fill="' + bl[0] + '" opacity=".75" filter="url(#' + id + 'b)"><animateTransform attributeName="transform" type="translate" values="0 0;' + (W * (i % 2 ? -.25 : .25)) + ' ' + (H * .15) + ';0 0" dur="' + (10 + i * 3) + 's" repeatCount="indefinite"/></circle>');
			});
			return { svg: out.join(''), glow: glow };
		}
		if (S.light === 'rgbw' || S.light === 'rgb') {
			glow = '#c77dff';
			out.push('<rect width="' + W + '" height="' + H + '" fill="#ff4fb3"><animate attributeName="fill" values="#ff4fb3;#7a5cff;#2fd0ff;#46e08a;#ffc93a;#ff4fb3" ' + dur + '/></rect>');
			if (S.graphic === 'print') { out.push(placeholderArt(W, H, big, true)); }
			return { svg: out.join(''), glow: glow };
		}
		if (S.graphic === 'sky') {
			glow = '#bfe0ff';
			out.push('<rect width="' + W + '" height="' + H + '" fill="#7fb6ec"/>');
			[[.25, .35], [.62, .55], [.8, .25], [.4, .8]].forEach(function (c, i) {
				out.push('<ellipse cx="' + W * c[0] + '" cy="' + H * c[1] + '" rx="' + big * 0.16 + '" ry="' + big * 0.07 + '" fill="#ffffff" opacity=".85" filter="url(#' + id + 'b)"><animateTransform attributeName="transform" type="translate" values="0 0;' + (W * 0.08) + ' 0;0 0" dur="' + (18 + i * 4) + 's" repeatCount="indefinite"/></ellipse>');
			});
			return { svg: out.join(''), glow: glow };
		}
		var col = CCT_COL[S.cct] || '#fff';
		if (S.light === 'tunable') {
			out.push('<rect width="' + W + '" height="' + H + '" fill="#ffe7c2"><animate attributeName="fill" values="#ffe2b8;#eaf1ff;#ffe2b8" ' + dur + '/></rect>');
			glow = '#fff1dc';
		} else {
			out.push('<rect width="' + W + '" height="' + H + '" fill="' + col + '"/>');
			glow = col;
		}
		if (S.graphic === 'print') { out.push(placeholderArt(W, H, big, true)); }
		return { svg: out.join(''), glow: glow };
	}

	function placeholderArt(W, H, big, lit) {
		var fs = big * 0.05;
		return '<g opacity="' + (lit ? .9 : .6) + '"><path d="M0 ' + H * .78 + 'L' + W * .3 + ' ' + H * .45 + 'L' + W * .52 + ' ' + H * .68 + 'L' + W * .72 + ' ' + H * .4 + 'L' + W + ' ' + H * .74 + 'V' + H + 'H0Z" fill="#004899" opacity=".22"/>' +
			'<circle cx="' + W * .78 + '" cy="' + H * .25 + '" r="' + big * .06 + '" fill="#004899" opacity=".18"/>' +
			'<text x="' + W / 2 + '" y="' + H / 2 + '" font-size="' + fs + '" text-anchor="middle" fill="#004899" opacity=".55" font-family="Poppins,sans-serif">Your artwork</text></g>';
	}

	/* -------------------------------------------------------------------- UI */

	function h(tag, attrs, kids) {
		var e = document.createElement(tag);
		if (attrs) {
			for (var k in attrs) {
				if (!Object.prototype.hasOwnProperty.call(attrs, k) || attrs[k] == null || attrs[k] === false) { continue; }
				if (k === 'text') { e.textContent = attrs[k]; }
				else if (k === 'html') { e.innerHTML = attrs[k]; }
				else if (k.slice(0, 2) === 'on') { e.addEventListener(k.slice(2), attrs[k]); }
				else { e.setAttribute(k, attrs[k] === true ? '' : attrs[k]); }
			}
		}
		(kids || []).forEach(function (c) { if (c) { e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); } });
		return e;
	}

	function step(n, title, kids, hint) {
		return h('fieldset', { class: 'lbx-step' }, [
			h('legend', null, [h('span', { class: 'lbx-n', text: String(n) }), title]),
			hint ? h('p', { class: 'lbx-hint', text: hint }) : null
		].concat(kids));
	}

	/** Group of radio tiles/chips. opts: [{v, label, sub, icon, img, dis, tag}] */
	function choice(key, opts, kind, onPick) {
		var wrap = h('div', { class: 'lbx-choices lbx-' + kind, role: 'radiogroup' });
		opts.forEach(function (o) {
			var idv = 'lbx-' + key + '-' + String(o.v).replace(/[^a-z0-9]/gi, '');
			var inp = h('input', { type: 'radio', name: 'lbx-' + key, id: idv, value: String(o.v), disabled: !!o.dis });
			if (String(S[key]) === String(o.v)) { inp.checked = true; }
			inp.addEventListener('change', function () {
				if (onPick) { onPick(o.v); } else { S[key] = o.v; }
				update(true, idv);
			});
			var lab = h('label', { for: idv, class: 'lbx-opt' + (o.dis ? ' is-dis' : ''), title: o.dis ? (o.why || 'Not available with your current choices') : null }, [
				o.icon ? h('span', { class: 'lbx-ico', html: '<svg viewBox="0 0 32 32" aria-hidden="true">' + o.icon + '</svg>' }) : null,
				o.swatch ? h('span', { class: 'lbx-sw', style: 'background:' + o.swatch }) : null,
				h('span', { class: 'lbx-opt-t' }, [
					h('strong', { text: o.label }),
					o.tag ? h('em', { class: 'lbx-tag', text: o.tag }) : null,
					o.sub ? h('small', { text: o.sub }) : null
				])
			]);
			wrap.appendChild(inp);
			wrap.appendChild(lab);
		});
		return wrap;
	}

	function field(label, key, opts) {
		opts = opts || {};
		var inp = h('input', { type: 'number', inputmode: 'numeric', min: opts.min, max: opts.max, step: opts.step || 10, value: S[key], id: 'lbx-f-' + key, 'data-key': key });
		inp.addEventListener('input', function () {
			var v = num(inp.value, null);
			if (v == null || v < (opts.min || 0)) { return; } // wait for a sensible number
			S[key] = v;
			update(false);
		});
		inp.addEventListener('change', function () { S[key] = num(inp.value, S[key]); update(true, inp.id); });
		return h('label', { class: 'lbx-field', for: 'lbx-f-' + key }, [
			h('span', { text: label }),
			h('span', { class: 'lbx-inp' }, [inp, h('i', { text: opts.unit || 'mm' })])
		]);
	}

	var els = {};

	function build() {
		root.innerHTML = '';
		els.presets = h('div', { class: 'lbx-presets' });
		els.controls = h('div', { class: 'lbx-controls' });
		els.stage = h('div', { class: 'lbx-stage' });
		els.toggle = h('button', { type: 'button', class: 'lbx-toggle', onclick: function () { LIT = !LIT; render(); } });
		els.notes = h('div', { class: 'lbx-notes', 'aria-live': 'polite' });
		els.spec = h('dl', { class: 'lbx-spec' });
		els.code = h('p', { class: 'lbx-code' });
		els.actions = h('div', { class: 'lbx-actions' }, [
			h('a', { href: '#lbx-quote', class: 'btn btn-blue lbx-go', text: 'Request a quote →' }),
			h('button', { type: 'button', class: 'btn btn-line-d', text: 'Copy link', onclick: copyLink }),
			h('button', { type: 'button', class: 'btn btn-line-d', text: 'Print / PDF', onclick: function () { window.print(); } })
		]);
		// Preview column: 3D render (default) or dimensioned drawing. Sticky, always in view.
		els.stage3d = h('div', { class: 'lbx-stage3d' }, [h('p', { class: 'lbx-loading', text: 'Loading 3D view…' })]);
		els.tab3d = h('button', { type: 'button', class: 'lbx-tab is-on', text: '3D view', onclick: function () { setView('3d'); } });
		els.tab2d = h('button', { type: 'button', class: 'lbx-tab', text: 'Drawing', onclick: function () { setView('2d'); } });
		els.reset = h('button', { type: 'button', class: 'lbx-tool', text: '⟲ Reset view', onclick: function () { if (VIEW3D) { VIEW3D.resetView(); } } });
		els.quick = h('p', { class: 'lbx-quick' });
		els.side = h('aside', { class: 'lbx-side' }, [
			h('div', { class: 'lbx-view is-3d' }, [
				els.stage3d, els.stage,
				h('div', { class: 'lbx-tabs' }, [els.tab3d, els.tab2d]),
				h('div', { class: 'lbx-tools' }, [els.toggle, els.reset]),
				h('p', { class: 'lbx-drag', text: 'Drag to turn it. Scroll or pinch to zoom.' })
			]),
			els.quick,
			els.notes
		]);
		els.view = els.side.firstChild;
		els.right = h('div', { class: 'lbx-right' }, [
			els.controls,
			h('div', { class: 'lbx-sum' }, [h('h2', { text: 'Your light box' }), els.spec, els.code, els.actions])
		]);

		root.appendChild(h('div', { class: 'lbx-pre-wrap' }, [h('p', { class: 'lbx-pre-h', text: 'Start from an idea, or build from scratch below' }), els.presets]));
		root.appendChild(h('div', { class: 'lbx-grid' }, [els.side, els.right]));
		load3d();
		root.appendChild(features());
		root.appendChild(quoteForm());

		PRESETS.forEach(function (p) {
			var btn = h('button', { type: 'button', class: 'lbx-preset' }, [
				p.img ? h('img', { src: IMG + p.img, alt: '', loading: 'lazy', width: 300, height: 170 }) : h('span', { class: 'lbx-preset-ph' }),
				h('span', { text: p.name })
			]);
			btn.addEventListener('click', function () {
				S = assign({}, DEFAULTS, p.s, { depthManual: !!p.s.depth });
				update(true);
				if (VIEW3D) { VIEW3D.resetView(); }
				els.side.scrollIntoView({ behavior: 'smooth', block: 'start' });
			});
			els.presets.appendChild(btn);
		});
	}

	function renderControls(focusId) {
		var T = TYPES[S.type], c = els.controls, n = 0;
		c.innerHTML = '';

		c.appendChild(step(++n, 'Type', [choice('type', Object.keys(TYPES).map(function (k) {
			return { v: k, label: TYPES[k].name, sub: TYPES[k].blurb };
		}), 'tiles', function (v) { S.type = v; S.depthManual = false; S.engineManual = false; })]));

		c.appendChild(step(++n, 'Shape', [choice('shape', Object.keys(SHAPES).map(function (k) {
			return { v: k, label: SHAPES[k].name, icon: SHAPES[k].icon, dis: T.shapes.indexOf(k) < 0, why: T.name + ' is rectangular only' };
		}), 'icons')]));

		var sz = [];
		if (isRound()) {
			sz.push(field(S.shape === 'ring' ? 'Outside diameter' : 'Diameter', 'd', { min: 500, max: 12000 }));
			if (S.shape === 'ring') { sz.push(field('Ring band width', 'band', { min: 200, max: S.d / 2 - 100 })); }
		} else {
			sz.push(field('Width', 'w', { min: T.min, max: T.maxLen }));
			sz.push(field('Height', 'h', { min: T.min, max: T.onePiece ? T.maxLen : 6000 }));
			if (S.shape === 'rounded' || S.shape === 'cutout') { sz.push(field('Corner radius', 'r', { min: 20, max: Math.min(S.w, S.h) / 2 })); }
			if (S.shape === 'cutout') {
				sz.push(field('Cut-out width', 'cw', { min: 200, max: S.w - 400 }));
				sz.push(field('Cut-out height', 'ch', { min: 200, max: S.h - 400 }));
			}
		}
		var hint = S.type === 'lgp' ? 'From 100 × 100 mm up to 1220 × 2440 mm.'
			: S.shape === 'custom' ? 'Give us the overall size. Send your sketch or DWG with the quote and we will draw it up.'
				: 'Any size. Anything over 2.4 × 4 m is made in sections and joined on site, with no visible gaps in the fabric.';
		c.appendChild(step(++n, 'Size', [h('div', { class: 'lbx-fields' }, sz)], hint));

		var lightKids = [choice('light', Object.keys(LIGHTS).map(function (k) {
			return { v: k, label: LIGHTS[k].name, sub: LIGHTS[k].blurb, dis: T.lights.indexOf(k) < 0, why: 'Not available on ' + T.name };
		}), 'tiles', function (v) { S.light = v; })];
		if (S.light === 'white') {
			lightKids.push(h('p', { class: 'lbx-sub', text: 'Colour temperature' }));
			lightKids.push(choice('cct', (S.type === 'lgp' ? CCT_LGP : CCT_STD).map(function (k) { return { v: k, label: k + 'K', swatch: CCT_COL[k] }; }), 'chips', function (v) { S.cct = +v; }));
		}
		lightKids.push(h('p', { class: 'lbx-sub', text: 'Control' }));
		lightKids.push(choice('control', CONTROL_BY_LIGHT[S.light].map(function (k) { return { v: k, label: CONTROLS[k] }; }), 'chips'));
		c.appendChild(step(++n, 'Lighting', lightKids));

		var rec = recDepth(), minD = LIGHTS[S.light].minDepth;
		var depthKids = [choice('depth', T.depths.map(function (d) {
			var p = PROFILES[S.type + '-' + d] || {};
			return { v: d, label: d + ' mm', tag: d === rec ? 'Recommended' : '', sub: p.kg ? p.kg + ' kg/m frame' : (p.face ? p.face + ' mm frame face' : ''), dis: d < minD, why: LIGHTS[S.light].name + ' needs ' + minD + ' mm or more' };
		}), 'chips', function (v) { S.depth = +v; S.depthManual = true; S.engineManual = false; })];
		var engines = (profile().engines || []);
		if ((S.light === 'white' || S.light === 'tunable') && engines.length > 1) {
			depthKids.push(h('p', { class: 'lbx-sub', text: 'Light engine' }));
			depthKids.push(choice('engine', engines.map(function (e) { return { v: e, label: e, tag: e === recEngine() ? 'Recommended' : '' }; }), 'chips', function (v) { S.engine = v; S.engineManual = true; }));
		} else {
			depthKids.push(h('p', { class: 'lbx-hint', text: 'Light engine: ' + S.engine }));
		}
		c.appendChild(step(++n, 'Depth', depthKids, 'A deeper box spreads the light more evenly on large faces. We recommend a depth based on your size and lighting.'));

		var gKids = [choice('graphic', Object.keys(GRAPHICS).map(function (k) {
			var dis = T.sides === 2 && k === 'sky';
			return { v: k, label: GRAPHICS[k].name, sub: k === 'print' && T.sides === 2 ? 'Printed on both faces' : GRAPHICS[k].blurb, dis: dis, why: 'Sky prints are for single-sided ceiling boxes' };
		}), 'tiles')];
		if (S.graphic === 'print') { gKids.push(artDrop()); }
		c.appendChild(step(++n, 'Fabric graphic', gKids, '100% flame-retardant polyester, dye-sublimation printed, with a silicone edge that pushes into the frame. You can swap graphics in minutes.'));

		c.appendChild(step(++n, 'Mounting', [choice('mount', Object.keys(MOUNTS).map(function (k) {
			var dis = T.mounts.indexOf(k) < 0 || (k === 'freestanding' && !(S.shape === 'rect' || S.shape === 'rounded'));
			return { v: k, label: MOUNTS[k].name, dis: dis };
		}), 'chips')]));

		var fKids = [choice('finish', Object.keys(FINISHES).map(function (k) {
			return { v: k, label: FINISHES[k].name, sub: FINISHES[k].note, swatch: FINISHES[k].color };
		}), 'chips')];
		if (S.finish === 'ral') {
			var ral = h('input', { type: 'text', id: 'lbx-f-ral', value: S.ral, placeholder: 'e.g. RAL 9005', maxlength: 30 });
			ral.addEventListener('input', function () { S.ral = ral.value; update(false); });
			fKids.push(h('label', { class: 'lbx-field', for: 'lbx-f-ral' }, [h('span', { text: 'RAL colour' }), h('span', { class: 'lbx-inp' }, [ral])]));
		}
		c.appendChild(step(++n, 'Frame finish', fKids));

		c.appendChild(step(++n, 'Quantity', [h('div', { class: 'lbx-fields' }, [field('How many?', 'qty', { min: 1, max: 999, step: 1, unit: 'pcs' })])]));

		if (focusId) {
			var f = document.getElementById(focusId);
			if (f) { try { f.focus({ preventScroll: true }); } catch (e) { f.focus(); } }
		}
	}

	function artDrop() {
		var inp = h('input', { type: 'file', accept: 'image/*', id: 'lbx-art', class: 'lbx-art-in' });
		var zone = h('label', { for: 'lbx-art', class: 'lbx-drop' + (ART ? ' has-art' : '') }, [
			h('strong', { text: ART ? 'Artwork loaded. Click or drop to change it' : 'Drop your artwork here to preview it' }),
			h('small', { text: 'JPG or PNG. It stays on your device and is not uploaded. Send print-ready files after your quote.' })
		]);
		function load(file) {
			if (!file || !/^image\//.test(file.type)) { return; }
			var rd = new FileReader();
			rd.onload = function () { ART = rd.result; update(true); };
			rd.readAsDataURL(file);
		}
		inp.addEventListener('change', function () { load(inp.files && inp.files[0]); });
		['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
		['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('is-over'); }); });
		zone.addEventListener('drop', function (e) { load(e.dataTransfer && e.dataTransfer.files[0]); });
		var kids = [inp, zone];
		if (ART) { kids.push(h('button', { type: 'button', class: 'lbx-link', text: 'Remove artwork', onclick: function () { ART = null; update(true); } })); }
		return h('div', { class: 'lbx-art' }, kids);
	}

	/* ---------------------------------------------------------- spec + code */

	function specRows() {
		var T = TYPES[S.type], m = metrics(), rows = [];
		var size = isRound()
			? 'Ø ' + fmt(S.d) + ' mm' + (S.shape === 'ring' ? ' (band ' + fmt(S.band) + ' mm, inner Ø ' + fmt(S.d - 2 * S.band) + ' mm)' : '')
			: fmt(S.w) + ' × ' + fmt(S.h) + ' mm';
		var shape = SHAPES[S.shape].name;
		if (S.shape === 'rounded') { shape += ', R' + fmt(S.r); }
		if (S.shape === 'cutout') { shape += ' (' + fmt(S.cw) + ' × ' + fmt(S.ch) + ' mm opening)'; }
		var light = LIGHTS[S.light].name + (S.light === 'white' ? ' ' + S.cct + 'K' : S.light === 'tunable' ? ' 3000–6500K' : '');
		var p = profile();
		rows.push(['Type', T.name + (T.sides === 2 ? ' (2 faces)' : '')]);
		rows.push(['Shape', shape]);
		rows.push(['Size', size]);
		rows.push(['Depth', S.depth + ' mm profile' + (p.alu ? ', ' + p.alu + ' mm aluminium' : '')]);
		rows.push(['Lit area', fmt2(m.area) + ' m²' + (T.sides === 2 ? ' (both faces)' : '')]);
		rows.push(['Frame', fmt1(m.perim) + ' m' + (m.kg ? ', approx ' + fmt1(m.kg) + ' kg (frame only)' : '')]);
		rows.push(['Build', m.sections === 1 ? 'One piece' : m.split]);
		rows.push(['Lighting', light]);
		rows.push(['Light engine', S.engine]);
		rows.push(['Control', CONTROLS[S.control]]);
		rows.push(['Graphic', GRAPHICS[S.graphic].name + (S.graphic === 'print' ? (ART ? ' (artwork previewed)' : '') : '') + ', FR polyester, silicone edge']);
		rows.push(['Mounting', MOUNTS[S.mount].name]);
		rows.push(['Frame finish', FINISHES[S.finish].name + (S.finish === 'ral' && S.ral ? ' ' + S.ral : '')]);
		rows.push(['Quantity', String(S.qty)]);
		return rows;
	}

	function buildCode() {
		var T = TYPES[S.type], b = bbox();
		var size = isRound() ? 'D' + S.d + (S.shape === 'ring' ? '-B' + S.band : '') : b.w + 'x' + b.h;
		var light = LIGHTS[S.light].code + (S.light === 'white' ? String(S.cct).slice(0, 2) : '');
		return ['RLB', T.code + S.depth, SHAPES[S.shape].code, size, light, MOUNTS[S.mount].code, FINISHES[S.finish].code].join('-');
	}

	function specText() {
		return specRows().map(function (r) { return r[0] + ': ' + r[1]; }).join('\n');
	}

	/* ------------------------------------------------------------ share link */

	var KEYS = ['type', 'shape', 'w', 'h', 'r', 'd', 'band', 'cw', 'ch', 'depth', 'engine', 'light', 'cct', 'control', 'graphic', 'mount', 'finish', 'ral', 'qty'];
	function toHash() {
		var q = new URLSearchParams();
		KEYS.forEach(function (k) { if (String(S[k]) !== String(DEFAULTS[k]) || k === 'type') { q.set(k, S[k]); } });
		if (S.depthManual) { q.set('dm', '1'); }
		if (S.engineManual) { q.set('em', '1'); }
		return q.toString();
	}
	function fromHash() {
		var raw = (location.hash || '').replace(/^#/, '');
		if (!raw || raw.indexOf('type=') < 0) { return; }
		var q = new URLSearchParams(raw);
		KEYS.forEach(function (k) {
			if (!q.has(k)) { return; }
			var v = q.get(k);
			S[k] = typeof DEFAULTS[k] === 'number' ? num(v, DEFAULTS[k]) : v;
		});
		if (!TYPES[S.type]) { S.type = 'seg'; }
		if (!SHAPES[S.shape]) { S.shape = 'rect'; }
		if (!LIGHTS[S.light]) { S.light = 'white'; }
		if (!GRAPHICS[S.graphic]) { S.graphic = 'print'; }
		if (!MOUNTS[S.mount]) { S.mount = 'wall'; }
		if (!FINISHES[S.finish]) { S.finish = 'silver'; }
		if (!CONTROLS[S.control]) { S.control = 'onoff'; }
		S.depthManual = q.get('dm') === '1';
		S.engineManual = q.get('em') === '1';
	}
	function shareUrl() { return location.href.split('#')[0] + '#' + toHash(); }
	function copyLink(e) {
		var btn = e.currentTarget, url = shareUrl();
		function done() { btn.textContent = 'Link copied ✓'; setTimeout(function () { btn.textContent = 'Copy link'; }, 2200); }
		if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copy this link:', url); }); }
		else { window.prompt('Copy this link:', url); }
	}

	/* ---------------------------------------------------------------- render */

	var lastNotes = [];
	function update(rebuildControls, focusId) {
		var notes = normalise();
		if (notes.length) { lastNotes = notes; } else if (rebuildControls) { lastNotes = []; }
		if (rebuildControls) { renderControls(focusId); }
		render();
		try { history.replaceState(null, '', '#' + toHash()); } catch (e) { /* ignore */ }
	}

	function render() {
		els.stage.innerHTML = previewSVG();
		els.stage.className = 'lbx-stage' + (LIT ? ' is-lit' : '');
		els.toggle.textContent = LIT ? '☾ Lights off' : '☀ Lights on';
		els.toggle.setAttribute('aria-pressed', LIT ? 'true' : 'false');

		els.notes.innerHTML = '';
		lastNotes.forEach(function (t) { els.notes.appendChild(h('p', { text: t })); });

		els.spec.innerHTML = '';
		specRows().forEach(function (r) {
			els.spec.appendChild(h('dt', { text: r[0] }));
			els.spec.appendChild(h('dd', { text: r[1] }));
		});
		els.code.textContent = 'Build code: ' + buildCode();
		var quick = (isRound() ? 'Ø ' + fmt(S.d) : fmt(S.w) + ' × ' + fmt(S.h)) + ' mm · ' + S.depth + ' mm deep · ' + LIGHTS[S.light].name + (S.light === 'white' ? ' ' + S.cct + 'K' : '');
		els.quick.textContent = quick;
		if (els.formSpec) { els.formSpec.textContent = buildCode() + ' · ' + quick + ' · qty ' + S.qty; }
		queue3d();
	}

	/* --------------------------------------------------------------- 3D view */

	var VIEW3D = null, VIEW = '3d', q3d = 0;

	function model3d() {
		return {
			geo: { kind: S.shape, w: S.w, h: S.h, r: S.shape === 'custom' ? customR() : S.r, d: S.d, band: S.band, cw: S.cw, ch: S.ch, cr: cutR() },
			depth: S.depth, face: profile().face || 20, sides: TYPES[S.type].sides, mount: S.mount,
			finishColor: FINISHES[S.finish].color, metal: S.finish === 'silver',
			light: S.light, cct: S.cct, cctColor: CCT_COL[S.cct] || '#ffffff', graphic: S.graphic, art: S.graphic === 'print' ? ART : null, lit: LIT
		};
	}
	function queue3d() {
		if (!VIEW3D || q3d) { return; }
		q3d = requestAnimationFrame(function () { q3d = 0; VIEW3D.update(model3d()); });
	}
	function setView(v) {
		if (v === '3d' && !VIEW3D) { v = '2d'; }
		VIEW = v;
		els.view.className = 'lbx-view is-' + v;
		els.tab3d.className = 'lbx-tab' + (v === '3d' ? ' is-on' : '');
		els.tab2d.className = 'lbx-tab' + (v === '2d' ? ' is-on' : '');
	}
	function webglOK() {
		try { var c = document.createElement('canvas'); return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl'))); } catch (e) { return false; }
	}
	function load3d() {
		var url = root.getAttribute('data-three');
		if (!url || !webglOK()) { setView('2d'); els.tab3d.hidden = true; return; }
		import(url).then(function (mod) {
			els.stage3d.innerHTML = '';
			VIEW3D = mod.create(els.stage3d);
			VIEW3D.update(model3d());
			setView(VIEW);
		}).catch(function () { setView('2d'); els.tab3d.hidden = true; });
	}

	/* -------------------------------------------------------- features band */

	function features() {
		var items = [
			['Seamless fabric face', 'The graphic is tensioned edge to edge, with no visible frame from the front.'],
			['Change graphics in minutes', 'The silicone-edged fabric pulls out and pushes back in. No tools needed.'],
			['Any size, any shape', 'Circles, rings, cut-outs and runs over 20 m, built in sections and joined on site.'],
			['White to full-colour', 'Static white, tunable white, RGBW, or pixel lighting for moving skies and content.'],
			['Flame-retardant fabric', '100% FR polyester with dye-sublimation print, so colours stay vivid.'],
			['3-year warranty', 'CE and RoHS compliant.']
		];
		return h('section', { class: 'lbx-feat' }, items.map(function (i) {
			return h('div', null, [h('strong', { text: i[0] }), h('p', { text: i[1] })]);
		}));
	}

	/* ------------------------------------------------------------ quote form */

	function quoteForm() {
		var f = h('form', { class: 'lbx-form', id: 'lbx-quote', novalidate: true });
		els.formSpec = h('p', { class: 'lbx-form-spec' });
		var inp = function (name, label, type, req, ac) {
			return h('label', { class: 'rm-tradeform-label' }, [label + (req ? ' *' : ''), h('input', { type: type || 'text', name: name, autocomplete: ac || null })]);
		};
		var tl = h('select', { name: 'timeline' }, ['Just exploring', 'ASAP', 'Within 1 to 3 months', 'In 3 to 6 months', 'Over 6 months'].map(function (t) { return h('option', { text: t }); }));
		var msg = h('p', { class: 'rm-tradeform-msg', role: 'status', hidden: true });
		var btn = h('button', { type: 'submit', class: 'btn btn-blue rm-tradeform-go', text: 'Send my design for a quote →' });
		f.appendChild(h('div', { 'aria-hidden': 'true', style: 'position:absolute;left:-9999px;top:-9999px' }, [h('label', null, ['Website', h('input', { type: 'text', name: 'rm_hp', tabindex: '-1', autocomplete: 'off' })])]));
		f.appendChild(h('h2', { class: 'rm-tradeform-h', text: 'Get a quote for this light box' }));
		f.appendChild(h('p', { class: 'lbx-hint', text: 'We will check your design, send a quote and drawings, and recommend the best build for your space. There is no obligation.' }));
		f.appendChild(els.formSpec);
		f.appendChild(h('div', { class: 'rm-tradeform-row' }, [inp('reqname', 'Name', 'text', true, 'name'), inp('company', 'Company', 'text', false, 'organization')]));
		f.appendChild(h('div', { class: 'rm-tradeform-row' }, [inp('email', 'Email', 'email', true, 'email'), inp('phone', 'Phone', 'tel', false, 'tel')]));
		f.appendChild(h('div', { class: 'rm-tradeform-row' }, [inp('project', 'Project / location'), h('label', { class: 'rm-tradeform-label' }, ['Timeline', tl])]));
		f.appendChild(h('label', { class: 'rm-tradeform-label' }, ['Anything else? Tell us about the space, the artwork or the install', h('textarea', { name: 'notes', rows: 3 })]));
		f.appendChild(msg);
		f.appendChild(h('div', { class: 'rm-tradeform-actions' }, [btn]));

		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var g = function (n) { var x = f.querySelector('[name=' + n + ']'); return x ? (x.value || '').trim() : ''; };
			if (g('rm_hp')) { return; }
			function err(t) { msg.hidden = false; msg.className = 'rm-tradeform-msg rm-tradeform-msg--err'; msg.textContent = t; }
			if (!g('reqname') || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(g('email'))) { err('Please enter your name and a valid email.'); return; }
			var body = new URLSearchParams();
			body.set('action', 'rm_lightbox');
			body.set('ts', root.getAttribute('data-ts') || '');
			['reqname', 'company', 'email', 'phone', 'project', 'timeline', 'notes', 'rm_hp'].forEach(function (n) { body.set(n, g(n)); });
			body.set('code', buildCode());
			body.set('spec', specText());
			body.set('link', shareUrl());
			btn.disabled = true;
			fetch(root.getAttribute('data-ajax'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					if (r && r.success) {
						var ref = r.data && r.data.ref ? ' Your reference is ' + r.data.ref + '.' : '';
						f.innerHTML = '';
						f.appendChild(h('div', { class: 'rm-tradeform-done' }, [
							h('span', { class: 'rm-tradeform-tick', 'aria-hidden': 'true' }),
							h('h2', { class: 'rm-tradeform-h', text: 'Thanks. Your light box design is with our team.' }),
							h('p', { text: 'We will be in touch with a quote and drawings.' + ref + ' We have also emailed you a copy of your spec.' })
						]));
						if (window.dataLayer) { window.dataLayer.push({ event: 'lightbox_quote', lightbox_code: buildCode() }); }
					} else {
						btn.disabled = false;
						err((r && r.data && r.data.msg) || 'Sorry, something went wrong. Please try again.');
					}
				})
				.catch(function () { btn.disabled = false; err('Sorry, something went wrong. Please try again.'); });
		});
		return f;
	}

	/* ------------------------------------------------------------------ boot */

	fromHash();
	build();
	update(true);
})();
