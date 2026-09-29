/**
 * Light Box Builder — 3D view (three.js). SOURCE: bundle to assets/js/lightbox-3d.js with
 *   npx esbuild assets/js/src/lightbox-3d.src.js --bundle --minify --format=esm --outfile=assets/js/lightbox-3d.js
 * (three must be installed where esbuild can resolve it).
 *
 * create(container) -> { update(model), destroy() }
 * model = {
 *   geo: { kind, w, h, r, d, band, cw, ch, cr },   // mm; kind: rect|rounded|circle|ring|cutout|custom
 *   depth, face, sides, mount, finishColor, metal,  // mm / hex / bool
 *   light, cct, cctColor, graphic, art, lit          // light: white|tunable|rgb|rgbw|pixel
 * }
 */
import {
	WebGLRenderer, Scene, PerspectiveCamera, Group, Shape, Path, ExtrudeGeometry, ShapeGeometry,
	MeshStandardMaterial, MeshBasicMaterial, Mesh, PlaneGeometry, CylinderGeometry, SphereGeometry,
	CapsuleGeometry, BoxGeometry, AmbientLight, HemisphereLight, DirectionalLight, PointLight, CanvasTexture, TextureLoader,
	Color, SRGBColorSpace, DoubleSide, AdditiveBlending, Vector3, BufferAttribute, ACESFilmicToneMapping, MathUtils
} from 'three';

const MM = 0.001;

/* ------------------------------------------------------------ outlines */

function rrPath(p, x, y, w, h, r) {
	r = Math.max(0, Math.min(r, w / 2, h / 2));
	p.moveTo(x + r, y);
	p.lineTo(x + w - r, y);
	if (r) { p.absarc(x + w - r, y + r, r, -Math.PI / 2, 0, false); }
	p.lineTo(x + w, y + h - r);
	if (r) { p.absarc(x + w - r, y + h - r, r, 0, Math.PI / 2, false); }
	p.lineTo(x + r, y + h);
	if (r) { p.absarc(x + r, y + h - r, r, Math.PI / 2, Math.PI, false); }
	p.lineTo(x, y + r);
	if (r) { p.absarc(x + r, y + r, r, Math.PI, Math.PI * 1.5, false); }
	return p;
}
function circPath(p, R) { p.absarc(0, 0, Math.max(R, 1e-4), 0, Math.PI * 2, false); return p; }

/**
 * Loops of the outline in metres, centred on 0,0.
 * outer(i): outer boundary shrunk by i; holes(i): each hole grown by i.
 */
function loops(g) {
	const W = g.w * MM, H = g.h * MM, R = g.r * MM, D = g.d * MM;
	switch (g.kind) {
		case 'circle': return { bw: D, bh: D, outer: (P, i) => circPath(P, D / 2 - i), holes: [] };
		case 'ring': return { bw: D, bh: D, outer: (P, i) => circPath(P, D / 2 - i), holes: [(P, i) => circPath(P, D / 2 - g.band * MM + i)] };
		case 'cutout': {
			const cw = g.cw * MM, ch = g.ch * MM, cr = g.cr * MM;
			return { bw: W, bh: H, outer: (P, i) => rrPath(P, -W / 2 + i, -H / 2 + i, W - 2 * i, H - 2 * i, R - i),
				holes: [(P, i) => rrPath(P, -cw / 2 - i, -ch / 2 - i, cw + 2 * i, ch + 2 * i, cr + i)] };
		}
		case 'rounded':
		case 'custom':
			return { bw: W, bh: H, outer: (P, i) => rrPath(P, -W / 2 + i, -H / 2 + i, W - 2 * i, H - 2 * i, R - i), holes: [] };
		default: return { bw: W, bh: H, outer: (P, i) => rrPath(P, -W / 2 + i, -H / 2 + i, W - 2 * i, H - 2 * i, 0), holes: [] };
	}
}

/** Shape bounded by outer(i) with holes(i) (i = inset). */
function faceShape(L, i) {
	const s = L.outer(new Shape(), i);
	L.holes.forEach((h) => s.holes.push(h(new Path(), i)));
	return s;
}
/** Frame bands: outer edge band + a band round each hole. */
function frameShapes(L, face) {
	const out = [];
	const a = L.outer(new Shape(), 0);
	a.holes.push(L.outer(new Path(), face));
	out.push(a);
	L.holes.forEach((h) => { const s = h(new Shape(), face); s.holes.push(h(new Path(), 0)); out.push(s); });
	return out;
}
/** Planar UVs across the bounding box so textures map 0..1. */
function planarUV(geo, bw, bh) {
	const pos = geo.attributes.position, uv = new Float32Array(pos.count * 2);
	for (let k = 0; k < pos.count; k++) {
		uv[k * 2] = pos.getX(k) / bw + 0.5;
		uv[k * 2 + 1] = pos.getY(k) / bh + 0.5;
	}
	geo.setAttribute('uv', new BufferAttribute(uv, 2));
	return geo;
}

/* ------------------------------------------------------------ face canvas */

function hsl(h, s, l) { return 'hsl(' + ((h % 360) + 360) % 360 + ',' + s + '%,' + l + '%)'; }

function makePainter(model, cv) {
	const ctx = cv.getContext('2d'), W = cv.width, H = cv.height, big = Math.max(W, H);
	const m = model;
	return function paint(t) {
		ctx.globalCompositeOperation = 'source-over';
		if (m.graphic === 'print' && !m.art) {
			ctx.fillStyle = m.light === 'white' ? m.cctColor : '#ffffff';
			ctx.fillRect(0, 0, W, H);
		}
		if (m.graphic === 'sky') {
			const g = ctx.createLinearGradient(0, 0, 0, H);
			g.addColorStop(0, '#5f9fe6'); g.addColorStop(1, '#a9d1f7');
			ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
			[[.22, .3], [.6, .55], [.82, .22], [.38, .82], [.1, .7]].forEach((c, k) => {
				const x = ((c[0] * W + t * 8 * (k + 1)) % (W * 1.3)) - W * 0.15, y = c[1] * H, r = big * 0.14;
				const rg = ctx.createRadialGradient(x, y, 0, x, y, r);
				rg.addColorStop(0, 'rgba(255,255,255,.95)'); rg.addColorStop(1, 'rgba(255,255,255,0)');
				ctx.fillStyle = rg; ctx.beginPath(); ctx.ellipse(x, y, r * 1.6, r * 0.7, 0, 0, Math.PI * 2); ctx.fill();
			});
			return;
		}
		if (m.graphic === 'white' || (m.graphic === 'print' && !m.art)) {
			if (!m.lit) { ctx.fillStyle = '#ecebe8'; ctx.fillRect(0, 0, W, H); }
			else if (m.light === 'white') { ctx.fillStyle = m.cctColor; ctx.fillRect(0, 0, W, H); }
			else if (m.light === 'tunable') {
				const k = (Math.sin(t * 0.5) + 1) / 2;
				const c = new Color('#ffd9a0').lerp(new Color('#dfe9ff'), k);
				ctx.fillStyle = '#' + c.getHexString(); ctx.fillRect(0, 0, W, H);
			} else if (m.light === 'rgb') {
				ctx.fillStyle = hsl(t * 25, 95, 55); ctx.fillRect(0, 0, W, H);
			} else if (m.light === 'rgbw') {
				const k = (Math.sin(t * 0.35) + 1) / 2;
				ctx.fillStyle = hsl(t * 20, 90, 55 + k * 30); ctx.fillRect(0, 0, W, H);
			} else if (m.light === 'pixel') {
				// Addressable pixels: each LED its own colour — rainbow wave + drifting blobs.
				const g = ctx.createLinearGradient(0, 0, W, H);
				for (let s = 0; s <= 6; s++) { g.addColorStop(s / 6, hsl(t * 30 + s * 60, 90, 55)); }
				ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
				for (let k = 0; k < 5; k++) {
					const x = W * (0.5 + 0.42 * Math.sin(t * (0.3 + k * 0.07) + k * 2));
					const y = H * (0.5 + 0.42 * Math.cos(t * (0.25 + k * 0.05) + k));
					const r = big * (0.22 + 0.05 * k);
					const rg = ctx.createRadialGradient(x, y, 0, x, y, r);
					rg.addColorStop(0, hsl(t * 40 + k * 72 + 180, 100, 65)); rg.addColorStop(1, 'rgba(0,0,0,0)');
					ctx.fillStyle = rg; ctx.fillRect(0, 0, W, H);
				}
			}
			if (m.graphic === 'print') { placeholder(ctx, W, H, big, m.lit); }
		}
	};
}

function placeholder(ctx, W, H, big, lit) {
	ctx.save();
	ctx.globalAlpha = lit ? 0.26 : 0.2;
	ctx.fillStyle = '#004899';
	ctx.beginPath(); ctx.moveTo(0, H * 0.78); ctx.lineTo(W * 0.3, H * 0.45); ctx.lineTo(W * 0.52, H * 0.68); ctx.lineTo(W * 0.72, H * 0.4); ctx.lineTo(W, H * 0.74); ctx.lineTo(W, H); ctx.lineTo(0, H); ctx.fill();
	ctx.beginPath(); ctx.arc(W * 0.78, H * 0.25, big * 0.06, 0, Math.PI * 2); ctx.fill();
	ctx.globalAlpha = lit ? 0.5 : 0.4;
	ctx.font = '600 ' + Math.round(big * 0.06) + 'px Poppins, sans-serif';
	ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
	ctx.fillText('Your artwork', W / 2, H / 2);
	ctx.restore();
}

/** Average colour of the face canvas (for the light it throws on the room). */
function avgColor(cv) {
	const s = document.createElement('canvas'); s.width = s.height = 4;
	const c = s.getContext('2d'); c.drawImage(cv, 0, 0, 4, 4);
	const d = c.getImageData(0, 0, 4, 4).data; let r = 0, g = 0, b = 0;
	for (let k = 0; k < d.length; k += 4) { r += d[k]; g += d[k + 1]; b += d[k + 2]; }
	const n = d.length / 4;
	return new Color(r / n / 255, g / n / 255, b / n / 255);
}

function glowTexture() {
	const cv = document.createElement('canvas'); cv.width = cv.height = 128;
	const c = cv.getContext('2d'), g = c.createRadialGradient(64, 64, 0, 64, 64, 64);
	g.addColorStop(0, 'rgba(255,255,255,.9)'); g.addColorStop(0.4, 'rgba(255,255,255,.35)'); g.addColorStop(1, 'rgba(255,255,255,0)');
	c.fillStyle = g; c.fillRect(0, 0, 128, 128);
	const t = new CanvasTexture(cv); t.colorSpace = SRGBColorSpace; return t;
}

/* ------------------------------------------------------------ the view */

export function create(container) {
	const renderer = new WebGLRenderer({ antialias: true, alpha: false, preserveDrawingBuffer: true });
	renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
	renderer.outputColorSpace = SRGBColorSpace;
	renderer.toneMapping = ACESFilmicToneMapping;
	renderer.toneMappingExposure = 1.05;
	container.appendChild(renderer.domElement);
	renderer.domElement.className = 'lbx-3d-canvas';

	const scene = new Scene();
	const camera = new PerspectiveCamera(40, 1, 0.05, 400);
	const hemi = new HemisphereLight(0xffffff, 0x444444, 1);
	const amb = new AmbientLight(0xffffff, 0.2);
	const sun = new DirectionalLight(0xffffff, 1.2);
	sun.position.set(3, 6, 5);
	const glowLight = new PointLight(0xffffff, 0, 0, 1.6);
	scene.add(hemi, amb, sun, glowLight);

	const glowTex = glowTexture();
	let world = null, faceCanvas = null, faceTex = null, paint = null, model = null, center = new Vector3(), fit = 4;
	let faceMats = [], glowMesh = null, lastAvg = 0;

	// Orbit: drag to rotate, wheel / pinch to zoom.
	const orbit = { az: 0.45, el: 0.12, zoom: 1, dragging: false, x: 0, y: 0, touched: false };
	const el = renderer.domElement;
	el.style.touchAction = 'none';
	const ptrs = new Map(); let pinch0 = 0, zoom0 = 1;
	el.addEventListener('pointerdown', (e) => { ptrs.set(e.pointerId, e); orbit.dragging = true; orbit.touched = true; orbit.x = e.clientX; orbit.y = e.clientY; el.setPointerCapture(e.pointerId);
		if (ptrs.size === 2) { const [a, b] = [...ptrs.values()]; pinch0 = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY); zoom0 = orbit.zoom; } });
	el.addEventListener('pointermove', (e) => {
		if (!ptrs.has(e.pointerId)) { return; }
		ptrs.set(e.pointerId, e);
		if (ptrs.size === 2) { const [a, b] = [...ptrs.values()]; const d = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY); orbit.zoom = MathUtils.clamp(zoom0 * pinch0 / Math.max(d, 1), 0.35, 2.5); return; }
		orbit.az -= (e.clientX - orbit.x) * 0.006;
		orbit.el = MathUtils.clamp(orbit.el + (e.clientY - orbit.y) * 0.006, -1.35, 1.35);
		orbit.x = e.clientX; orbit.y = e.clientY;
	});
	const up = (e) => { ptrs.delete(e.pointerId); if (!ptrs.size) { orbit.dragging = false; } };
	el.addEventListener('pointerup', up); el.addEventListener('pointercancel', up);
	el.addEventListener('wheel', (e) => { e.preventDefault(); orbit.zoom = MathUtils.clamp(orbit.zoom * (1 + Math.sign(e.deltaY) * 0.08), 0.35, 2.5); }, { passive: false });

	function resize() {
		const w = container.clientWidth || 600, h = container.clientHeight || 450;
		renderer.setSize(w, h, false);
		camera.aspect = w / h; camera.updateProjectionMatrix();
	}
	const ro = window.ResizeObserver ? new ResizeObserver(resize) : null;
	if (ro) { ro.observe(container); } else { window.addEventListener('resize', resize); }
	resize();

	function disposeWorld() {
		if (!world) { return; }
		world.traverse((o) => { if (o.geometry) { o.geometry.dispose(); } if (o.material) { [].concat(o.material).forEach((m) => { if (m.map && m.map !== glowTex) { m.map.dispose(); } m.dispose(); }); } });
		scene.remove(world); world = null;
	}

	function person(dark) {
		const g = new Group(), mat = new MeshStandardMaterial({ color: dark ? 0x3a3d45 : 0x9a978f, roughness: 0.9 });
		const body = new Mesh(new CapsuleGeometry(0.19, 0.62, 6, 14), mat); body.position.y = 1.18; body.scale.z = 0.6;
		const head = new Mesh(new SphereGeometry(0.11, 18, 14), mat); head.position.y = 1.64;
		[-0.09, 0.09].forEach((x) => { const leg = new Mesh(new CapsuleGeometry(0.07, 0.72, 4, 10), mat); leg.position.set(x, 0.43, 0); g.add(leg); });
		g.add(body, head);
		return g;
	}

	function build(m) {
		disposeWorld();
		world = new Group();
		const L = loops(m.geo), dep = m.depth * MM, face = Math.max(m.face * MM, 0.012);
		const lit = m.lit, dark = lit;
		const box = new Group();

		// Frame (extruded bands) + back plate + fabric face(s).
		const frameMat = new MeshStandardMaterial({ color: new Color(m.finishColor), metalness: m.metal ? 0.75 : 0.15, roughness: m.metal ? 0.32 : 0.55 });
		const frame = new Mesh(new ExtrudeGeometry(frameShapes(L, face), { depth: dep, bevelEnabled: false, curveSegments: 48 }), frameMat);
		box.add(frame);
		if (m.sides === 1) {
			const back = new Mesh(new ExtrudeGeometry(faceShape(L, 0), { depth: 0.002, bevelEnabled: false, curveSegments: 48 }), new MeshStandardMaterial({ color: 0x2a2b2e, roughness: 0.8 }));
			back.position.z = -0.001; box.add(back);
		}

		// Face texture.
		const bw = L.bw, bh = L.bh, ratio = bh / bw;
		faceCanvas = document.createElement('canvas');
		faceCanvas.width = ratio > 1 ? Math.round(512 / ratio) : 512;
		faceCanvas.height = ratio > 1 ? 512 : Math.max(64, Math.round(512 * ratio));
		faceTex = new CanvasTexture(faceCanvas); faceTex.colorSpace = SRGBColorSpace;
		paint = makePainter(m, faceCanvas);
		paint(0);
		let map = faceTex;
		if (m.graphic === 'print' && m.art) {
			map = new TextureLoader().load(m.art, (tx) => { coverFit(tx, bw / bh); });
			map.colorSpace = SRGBColorSpace;
		}
		faceMats = [];
		const faceGeo = planarUV(new ShapeGeometry(faceShape(L, face), 48), bw, bh);
		const mkFace = () => {
			const mat = lit
				? new MeshBasicMaterial({ map: map, color: m.graphic === 'print' && m.art ? new Color(m.light === 'white' ? m.cctColor : '#ffffff') : 0xffffff })
				: new MeshStandardMaterial({ map: map, roughness: 0.95, color: m.graphic === 'print' && m.art ? 0xbdbdbd : 0xffffff });
			if (lit) { mat.toneMapped = false; }
			faceMats.push(mat); return mat;
		};
		const f1 = new Mesh(faceGeo, mkFace()); f1.position.z = dep - 0.003; box.add(f1);
		if (m.sides === 2) { const f2 = new Mesh(faceGeo, mkFace()); f2.rotation.y = Math.PI; f2.position.z = 0.003; box.add(f2); }

		// Soft halo on the surroundings when lit.
		if (lit) {
			glowMesh = new Mesh(new PlaneGeometry(bw * 2.2 + 1, bh * 2.2 + 1), new MeshBasicMaterial({ map: glowTex, transparent: true, opacity: 0.55, depthWrite: false, blending: AdditiveBlending, color: 0xffffff }));
			glowMesh.position.z = m.sides === 1 ? -0.01 : dep / 2;
			if (m.sides === 1) { box.add(glowMesh); }
		} else { glowMesh = null; }

		// Place in the room. Floor at y = 0.
		const roomDark = new MeshStandardMaterial({ color: dark ? 0x17181c : 0xe9e7e2, roughness: 0.95, side: DoubleSide });
		const floorMat = new MeshStandardMaterial({ color: dark ? 0x121316 : 0xd9d6cf, roughness: 0.85 });
		const span = Math.max(bw, bh);
		const floor = new Mesh(new PlaneGeometry(span * 6 + 12, span * 6 + 12), floorMat);
		floor.rotation.x = -Math.PI / 2; world.add(floor);

		let yBottom, man = person(dark);
		const holder = new Group(); holder.add(box);
		box.position.set(0, 0, 0);
		if (m.mount === 'ceiling') {
			const ceilH = Math.max(3.2, 2.6 + dep + 0.2);
			holder.rotation.x = Math.PI / 2; // face points down
			holder.position.set(0, ceilH - 0.15, 0);
			// rotation maps +Z (face) to -Y; the back (z=0) sits at the top.
			const ceil = new Mesh(new PlaneGeometry(span * 4 + 8, span * 4 + 8), roomDark);
			ceil.rotation.x = Math.PI / 2; ceil.position.y = ceilH; world.add(ceil);
			[[-bw * 0.35, 0], [bw * 0.35, 0]].forEach((p) => { const rod = new Mesh(new CylinderGeometry(0.004, 0.004, 0.15, 6), new MeshStandardMaterial({ color: 0x888888 })); rod.position.set(p[0], ceilH - 0.075, p[1]); world.add(rod); });
			if (glowMesh) { glowMesh.position.z = 0.0; }
			center.set(0, ceilH - dep - 0.15, 0);
			man.position.set(Math.min(bw * 0.3, 1.2), 0, bh * 0.25);
			world.add(man);
			fit = Math.max(span * 1.05, 3.4);
			if (!orbit.touched) { orbit.az = 0.5; orbit.el = -0.55; }
			glowLight.position.set(0, ceilH - dep - 0.5, 0);
		} else {
			if (m.mount === 'wall') { yBottom = bh > 2.2 ? 0.25 : Math.max(0.9, 2.1 - bh); }
			else if (m.mount === 'freestanding') { yBottom = 0.08; }
			else { yBottom = Math.max(2.3, 1.2); }
			holder.position.set(0, yBottom + bh / 2, m.sides === 1 ? 0 : -dep / 2);
			if (m.mount === 'wall') {
				const wall = new Mesh(new PlaneGeometry(span * 5 + 10, Math.max(yBottom + bh + 1.5, 4)), roomDark);
				wall.position.set(0, wall.geometry.parameters.height / 2, -0.004); world.add(wall);
			}
			if (m.mount === 'suspended') {
				const top = yBottom + bh + Math.max(1, span * 0.35);
				[-bw * 0.35, bw * 0.35].forEach((x) => { const c = new Mesh(new CylinderGeometry(0.0025, 0.0025, top - yBottom - bh, 6), new MeshStandardMaterial({ color: 0x9a9a9a })); c.position.set(x, yBottom + bh + (top - yBottom - bh) / 2, m.sides === 1 ? dep / 2 : 0); world.add(c); });
			}
			if (m.mount === 'freestanding') {
				[-bw * 0.32, bw * 0.32].forEach((x) => { const ft = new Mesh(new BoxGeometry(0.08, 0.06, Math.max(0.45, bh * 0.25)), frameMat); ft.position.set(x, 0.03, 0); world.add(ft); });
			}
			center.set(0, yBottom + bh / 2, 0);
			man.position.set(bw / 2 + 0.9, 0, 0.25);
			world.add(man);
			fit = Math.max(span, 1.9) * 1.25;
			if (!orbit.touched) { orbit.az = 0.45; orbit.el = 0.1; }
			glowLight.position.set(0, center.y, dep + Math.max(0.4, span * 0.25));
		}
		world.add(holder);
		scene.add(world);

		scene.background = new Color(dark ? 0x0d0e11 : 0xf1efeb);
		hemi.intensity = dark ? 0.3 : 1.1;
		amb.intensity = dark ? 0.05 : 0.35;
		sun.intensity = dark ? 0.05 : 1.3;
		glowLight.intensity = lit ? 3 * Math.max(1, span) : 0;
		glowLight.distance = Math.max(6, span * 4);
	}

	function coverFit(tx, aspect) {
		const ia = tx.image.width / tx.image.height;
		if (ia > aspect) { tx.repeat.set(aspect / ia, 1); tx.offset.set((1 - aspect / ia) / 2, 0); }
		else { tx.repeat.set(1, ia / aspect); tx.offset.set(0, (1 - ia / aspect) / 2); }
		tx.needsUpdate = true;
	}

	let raf = 0, t0 = performance.now(), alive = true, visible = true;
	const io = window.IntersectionObserver ? new IntersectionObserver((es) => { visible = es[0].isIntersecting; }) : null;
	if (io) { io.observe(container); }

	function tick(now) {
		if (!alive) { return; }
		raf = requestAnimationFrame(tick);
		if (!visible || !model) { return; }
		const t = (now - t0) / 1000;
		const animated = model.lit && (model.light !== 'white' || model.graphic === 'sky') && !(model.graphic === 'print' && model.art);
		if (animated && paint) {
			paint(t); faceTex.needsUpdate = true;
			if (now - lastAvg > 120) { lastAvg = now; const c = avgColor(faceCanvas); glowLight.color.copy(c); if (glowMesh) { glowMesh.material.color.copy(c); } }
		}
		const dist = fit * 2.2 * orbit.zoom;
		camera.position.set(
			center.x + dist * Math.sin(orbit.az) * Math.cos(orbit.el),
			center.y + dist * Math.sin(orbit.el),
			center.z + dist * Math.cos(orbit.az) * Math.cos(orbit.el)
		);
		if (camera.position.y < 0.3) { camera.position.y = 0.3; }
		camera.lookAt(center);
		renderer.render(scene, camera);
	}
	raf = requestAnimationFrame(tick);

	return {
		update(m) {
			model = m;
			build(m);
			if (m.lit && faceCanvas) { const c = avgColor(faceCanvas); glowLight.color.copy(c); if (glowMesh) { glowMesh.material.color.copy(c); } }
		},
		resetView() { orbit.touched = false; orbit.zoom = 1; if (model) { build(model); } },
		snapshot() { return renderer.domElement.toDataURL('image/png'); },
		destroy() { alive = false; cancelAnimationFrame(raf); if (ro) { ro.disconnect(); } if (io) { io.disconnect(); } disposeWorld(); renderer.dispose(); el.remove(); }
	};
}
