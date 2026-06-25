/* Flow Custom: made-to-order arc configurator. Give a size and an arc angle and
   it derives the whole luminaire (length, lumens, wattage, photometry) from the
   per-metre spec and produces the BIM file — geometry (IFC) plus a length-matched
   LDT — in one click, no uploads. The per-metre figures pre-fill from the variant
   but stay editable so output is correct even before the specs are confirmed. */

import { deriveFlow } from './products.js';
import { buildMesh, meshBounds } from './shapes.js';
import { buildIfc } from './ifc.js';
import { writeEulumdat } from './ldt-write.js';
import { makeZip } from './zip.js';

// Single Flow product — one standard silicone diffuser on all of them, so there's
// no variant to choose. Per-metre figures stay editable in the UI.
const FLOW_SPEC = {
  lumensPerMetre: 800, wattsPerMetre: 9, cri: 90,
  ccts: [2700, 3000, 4000, 5000], defaultCct: 3000,
};

let modelMod = null;
let lastMesh = null;
let bgMode = 'dark';   // preview background: 'dark' | 'light'

export function initFlow(root) {
  root.innerHTML = `
    <div class="card">
      <h2>Flow custom arc <span class="faint" style="font-weight:400;font-size:13px">— parameters in, BIM file out</span></h2>
      <p class="muted" style="margin:0 0 14px;font-size:13.5px">Pick the variant, set the size and arc angle, and Bim Builder derives the length, output and photometry and generates the IFC + a matching LDT. No datasheet, model or LDT needed.</p>
      <div class="fields">
        <div><label>Colour temperature</label><select id="fc-cct"></select></div>
        <div><label>Size mode</label><select id="fc-mode"><option value="diameter">Diameter</option><option value="radius">Radius</option></select></div>
        <div><label>Size (mm)</label><input id="fc-size" type="number" value="800" min="50" step="10"></div>
        <div><label>Arc angle (°)</label><input id="fc-sweep" type="number" value="45" min="1" max="360" step="1"></div>
        <div><label>Order code <span class="faint">(auto if blank)</span></label><input id="fc-code" placeholder="FLW-800-45-3000"></div>
        <div><label>Lumens per metre</label><input id="fc-lpm" type="number" min="1" step="10"></div>
        <div><label>Watts per metre</label><input id="fc-wpm" type="number" min="0" step="0.5"></div>
        <div><label>CRI (Ra)</label><input id="fc-cri" type="number" min="0" max="100" step="1"></div>
        <div><label>Profile W × H (mm)</label>
          <div style="display:flex;gap:8px"><input id="fc-pw" type="number" min="1" step="1" style="flex:1">
          <input id="fc-ph" type="number" min="1" step="1" style="flex:1"></div></div>
        <div><label>Lens side lip (mm) <span class="faint">smaller = wider lens</span></label>
          <input id="fc-lip" type="number" value="2" min="0" step="0.5"></div>
        <div><label>Body colour</label><select id="fc-body">
          <option value="#121212">Black (RAL 9005)</option>
          <option value="#2b2d31">Anthracite</option>
          <option value="#f1f1ea">White (RAL 9016)</option>
          <option value="#c7ccd4">Silver</option>
          <option value="#c8a24b">Gold</option>
          <option value="#8a6a3f">Bronze</option>
        </select></div>
        <div><label>Finish</label><select id="fc-fin">
          <option value="matte">Matte</option>
          <option value="satin">Satin</option>
          <option value="gloss">Gloss</option>
        </select></div>
      </div>

      <div class="metrics" id="fc-summary" style="margin-top:16px"></div>

      <div class="export-bar">
        <button class="btn" id="fc-generate">⬇ Generate BIM file (IFC + LDT)</button>
        <button class="btn ghost" id="fc-preview">Preview 3D</button>
        <span class="hint" id="fc-hint"></span>
      </div>
    </div>
    <div class="card section" id="fc-preview-card" hidden>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <span class="faint" style="font-size:12.5px">3D preview — drag to orbit</span>
        <button class="btn ghost" id="fc-bg-toggle" type="button" style="padding:6px 12px">◐ Light background</button>
      </div>
      <canvas id="fc-canvas" style="width:100%;height:300px;border-radius:10px;background:#0c1120"></canvas>
    </div>`;

  const applyDefaults = () => {
    root.querySelector('#fc-lpm').value = FLOW_SPEC.lumensPerMetre;
    root.querySelector('#fc-wpm').value = FLOW_SPEC.wattsPerMetre;
    root.querySelector('#fc-cri').value = FLOW_SPEC.cri;
    root.querySelector('#fc-pw').value = 35;   // default the Flow profile to 35 × 35 mm
    root.querySelector('#fc-ph').value = 35;
    const cctSel = root.querySelector('#fc-cct');
    cctSel.innerHTML = '';
    FLOW_SPEC.ccts.forEach((c) => cctSel.add(new Option(c + ' K', c)));
    cctSel.value = FLOW_SPEC.defaultCct;
    update(root);
  };

  root.querySelectorAll('#fc-cct,#fc-mode,#fc-size,#fc-sweep,#fc-lpm,#fc-wpm,#fc-cri,#fc-pw,#fc-ph,#fc-lip,#fc-code')
    .forEach((el) => el.addEventListener('input', () => { update(root); refreshPreview(root); }));
  // Appearance controls only affect the render, so re-skin the live preview
  // (if open) without recomputing photometry.
  root.querySelectorAll('#fc-body,#fc-fin')
    .forEach((el) => el.addEventListener('change', () => refreshPreview(root)));
  const bgBtn = root.querySelector('#fc-bg-toggle');
  bgBtn.addEventListener('click', () => {
    bgMode = bgMode === 'dark' ? 'light' : 'dark';
    bgBtn.textContent = bgMode === 'dark' ? '◐ Light background' : '◐ Dark background';
    refreshPreview(root);
  });
  root.querySelector('#fc-generate').addEventListener('click', () => generate(root));
  root.querySelector('#fc-preview').addEventListener('click', () => doPreview(root));

  applyDefaults();
}

function readAppearance(root) {
  const cct = parseInt(root.querySelector('#fc-cct').value, 10) || 3000;
  return {
    bodyColor: root.querySelector('#fc-body').value,
    diffuserColor: '#f4f3ee',   // single silicone opal diffuser
    finish: root.querySelector('#fc-fin').value,
    emissiveColor: cctToHex(cct),
  };
}

/* Approximate the lens glow colour from the colour temperature, interpolating
   between anchors so 2700K reads warm/amber and 5000K+ reads cool/blue-white. */
const CCT_ANCHORS = [
  [2200, [255, 157, 84]], [2700, [255, 180, 120]], [3000, [255, 197, 143]],
  [4000, [255, 224, 189]], [5000, [222, 231, 255]], [6500, [201, 220, 255]],
];
function cctToHex(k) {
  const a = CCT_ANCHORS;
  const hex = (c) => '#' + c.map((v) => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0')).join('');
  if (k <= a[0][0]) return hex(a[0][1]);
  if (k >= a[a.length - 1][0]) return hex(a[a.length - 1][1]);
  for (let i = 0; i < a.length - 1; i++) {
    if (k >= a[i][0] && k <= a[i + 1][0]) {
      const t = (k - a[i][0]) / (a[i + 1][0] - a[i][0]);
      return hex(a[i][1].map((v, j) => v + (a[i + 1][1][j] - v) * t));
    }
  }
  return hex(a[0][1]);
}

// Re-render the preview only if it is already on screen.
function refreshPreview(root) {
  if (!root.querySelector('#fc-preview-card').hidden) doPreview(root);
}

function readState(root) {
  const val = (id) => root.querySelector('#' + id).value;
  const numv = (id) => parseFloat(val(id)) || 0;
  const spec = {
    lumensPerMetre: numv('fc-lpm'), wattsPerMetre: numv('fc-wpm'), cri: numv('fc-cri'),
    profileWidth: numv('fc-pw'), profileHeight: numv('fc-ph'),
  };
  const params = {
    dimensionMode: val('fc-mode'), dimension: numv('fc-size'),
    sweep: numv('fc-sweep'), cct: parseInt(val('fc-cct'), 10) || 3000,
    code: val('fc-code').trim(),
  };
  const d = deriveFlow(spec, params);
  return { spec, params, d };
}

function update(root) {
  const { spec, params, d } = readState(root);
  const m = (k, v) => `<div class="metric"><div class="k">${k}</div><div class="v" style="font-size:16px">${v}</div></div>`;
  root.querySelector('#fc-summary').innerHTML =
    m('Order code', d.code) +
    m('Arc length', d.arcLenMm.toFixed(0) + ' mm') +
    m('Total output', d.luminousFlux.toLocaleString() + ' lm') +
    m('Wattage', d.wattage + ' W') +
    m('Efficacy', (d.efficacy || 0).toFixed(0) + ' lm/W') +
    m('Profile', `${spec.profileWidth} × ${spec.profileHeight} mm`);
  return { spec, params, d };
}

function buildArtifacts(root) {
  const { spec, params, d } = readState(root);
  const mesh = buildMesh('flow', 'arc', {
    radius: d.radiusMm, sweep: params.sweep,
    profileWidth: spec.profileWidth, profileHeight: spec.profileHeight,
    lip: parseFloat(root.querySelector('#fc-lip').value) || 0,
  });
  lastMesh = mesh;
  const dims = meshBounds(mesh);
  const manufacturer = 'Ricoman';
  const model = 'Flow arc';

  const synthLdt = {
    company: manufacturer, luminaireName: model, luminaireNumber: d.code,
    fileName: d.code + '.ldt', Isym: 1, dff: 100, lorl: 100,
    dimensions: { length: dims.length, width: dims.width, height: dims.height, circular: false },
    lampSets: [{ count: '1', type: 'LED', flux: d.luminousFlux, wattage: d.wattage }],
    derived: {
      luminousFlux: d.luminousFlux, wattage: d.wattage, efficacy: d.efficacy,
      colorTemp: d.cct, cri: d.cri, lampType: 'LED',
    },
  };
  const meta = {
    manufacturer, model, reference: d.code, mounting: 'Suspended',
    ldtFileName: d.code + '.ldt', now: new Date(),
  };

  const ifc = buildIfc({ ldt: synthLdt, mesh, meta });
  const ldt = writeEulumdat({
    company: manufacturer, name: model, number: d.code, fileName: d.code + '.ldt',
    lengthMm: dims.length, widthMm: dims.width, heightMm: dims.height,
    flux: d.luminousFlux, wattage: d.wattage, cct: d.cct, cri: d.cri,
  });
  return { d, ifc, ldt };
}

function generate(root) {
  const hint = root.querySelector('#fc-hint');
  try {
    const { d, ifc, ldt } = buildArtifacts(root);
    const blob = makeZip([
      { name: d.code + '.ifc', text: ifc },
      { name: d.code + '.ldt', text: ldt },
    ]);
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = d.code + '.zip';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1500);
    hint.textContent = `Generated ${d.code} — IFC + LDT (${d.luminousFlux.toLocaleString()} lm, ${d.wattage} W).`;
  } catch (err) {
    hint.textContent = 'Failed: ' + err.message;
  }
}

async function doPreview(root) {
  const hint = root.querySelector('#fc-hint');
  const { spec, params, d } = readState(root);
  const mesh = buildMesh('flow', 'arc', {
    radius: d.radiusMm, sweep: params.sweep,
    profileWidth: spec.profileWidth, profileHeight: spec.profileHeight,
    lip: parseFloat(root.querySelector('#fc-lip').value) || 0,
  });
  root.querySelector('#fc-preview-card').hidden = false;
  try {
    if (!modelMod) modelMod = await import('./model.js');
    const bg = bgMode === 'light' ? '#e9ebee' : '#0b0e16';
    modelMod.preview(modelMod.meshToObject(mesh, readAppearance(root)), root.querySelector('#fc-canvas'), { background: bg });
  } catch (err) {
    root.querySelector('#fc-preview-card').hidden = true;
    hint.textContent = 'Preview unavailable (needs internet for the 3D engine) — the BIM file still generates fine.';
  }
}
