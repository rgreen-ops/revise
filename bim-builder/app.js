/* Bim Builder — orchestration. Wires the three drop zones (datasheet, 3D model,
   LDT) to the parser, previews and the IFC exporter. Vanilla ES modules, no
   build step, to match the rest of the repo. */

import { parseLdt, polarSamples } from './ldt.js';
import { buildIfc } from './ifc.js';
import { FAMILIES, buildMesh, meshBounds } from './shapes.js';
import { extractFromFile } from './datasheet.js';

// three.js preview is optional (needs network for the CDN). Loaded lazily so the
// core LDT -> IFC pipeline works even offline / if the CDN is blocked.
let modelMod = null;

const state = {
  ldt: null,
  ldtFileName: '',
  mesh: null,
  modelObject: null,
  datasheetName: '',
  shapeInfo: null,   // { family, shape, dims } when built from a preset
};

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => Array.from(document.querySelectorAll(sel));

function setStatus(el, ok, text) {
  el.textContent = text;
  el.className = 'dz-status ' + (ok ? 'ok' : 'err');
}

// ---- file wiring ------------------------------------------------------
function wireDropZone(zoneId, inputId, onFile) {
  const zone = $('#' + zoneId);
  const input = $('#' + inputId);
  zone.addEventListener('click', () => input.click());
  zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('drag'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('drag');
    if (e.dataTransfer.files[0]) onFile(e.dataTransfer.files[0]);
  });
  input.addEventListener('change', () => { if (input.files[0]) onFile(input.files[0]); });
}

async function handleLdt(file) {
  const statusEl = $('#ldt-status');
  try {
    const text = await file.text();
    state.ldt = parseLdt(text);
    state.ldtFileName = file.name;
    setStatus(statusEl, true, file.name);
    populateFromLdt(state.ldt);
    drawPolar(state.ldt);
    $('#review').hidden = false;
    refreshExport();
  } catch (err) {
    setStatus(statusEl, false, 'Could not parse LDT: ' + err.message);
  }
}

async function handleModel(file) {
  const statusEl = $('#model-status');
  setStatus(statusEl, true, 'Loading ' + file.name + '…');
  try {
    if (!modelMod) modelMod = await import('./model.js');
    const { object, mesh } = await modelMod.loadModel(file);
    state.mesh = mesh;
    state.modelObject = object;
    const tris = mesh.indices.length / 3;
    setStatus(statusEl, true, `${file.name} · ${tris.toLocaleString()} triangles`);
    $('#model-card').hidden = false;
    modelMod.preview(object, $('#model-canvas'));
    refreshExport();
  } catch (err) {
    setStatus(statusEl, false, err.message);
  }
}

async function handleDatasheet(file) {
  state.datasheetName = file.name;
  $('#meta-datasheet').value = file.name;
  const statusEl = $('#datasheet-status');
  setStatus(statusEl, true, 'Reading ' + file.name + '…');
  try {
    const { fields, note } = await extractFromFile(file);
    const fillIf = (id, v) => { const el = $('#' + id); if (el && v && !el.value) el.value = v; };
    fillIf('meta-reference', fields.reference);
    fillIf('meta-model', fields.model);
    fillIf('meta-manufacturer', fields.manufacturer);
    fillIf('meta-year', fields.year);
    const got = Object.keys(fields).length;
    setStatus(statusEl, true, note || (got ? `${file.name} · read ${got} field${got === 1 ? '' : 's'}` : `${file.name} · no fields auto-detected, fill manually`));
    $('#review').hidden = false;
  } catch (err) {
    setStatus(statusEl, false, 'Read failed: ' + err.message + ' — fill fields manually');
  }
}

// ---- populate the metadata form from the LDT -------------------------
function populateFromLdt(ldt) {
  const setIf = (id, val) => { const el = $('#' + id); if (el && !el.value) el.value = val || ''; };
  setIf('meta-manufacturer', ldt.company);
  setIf('meta-model', ldt.luminaireName);
  setIf('meta-reference', ldt.luminaireNumber);

  const d = ldt.derived;
  $('#m-flux').textContent = num(d.luminousFlux) + ' lm';
  $('#m-watt').textContent = num(d.wattage) + ' W';
  $('#m-eff').textContent = (d.efficacy ? d.efficacy.toFixed(1) : '–') + ' lm/W';
  $('#m-cct').textContent = d.colorTemp ? d.colorTemp + ' K' : '–';
  $('#m-cri').textContent = d.cri ? 'Ra ' + d.cri : '–';
  const dim = ldt.dimensions;
  $('#m-dim').textContent = `${num(dim.length)} × ${num(dim.width)} × ${num(dim.height)} mm`;
  $('#m-sym').textContent = symLabel(ldt.Isym);
}

function num(n) { return (Math.round((Number(n) || 0) * 10) / 10).toLocaleString(); }
function symLabel(s) {
  return ({ 0: 'None', 1: 'Rotational', 2: 'C0–C180', 3: 'C90–C270', 4: 'C0–C180 & C90–C270' })[s] || '—';
}

// ---- photometric polar diagram (canvas) ------------------------------
function drawPolar(ldt) {
  const canvas = $('#polar-canvas');
  const ctx = canvas.getContext('2d');
  const dpr = Math.min(devicePixelRatio, 2);
  const size = 260;
  canvas.width = size * dpr; canvas.height = size * dpr;
  canvas.style.width = canvas.style.height = size + 'px';
  ctx.scale(dpr, dpr);
  ctx.clearRect(0, 0, size, size);

  const cx = size / 2, cy = size / 2, R = size / 2 - 16;
  const samples = polarSamples(ldt);
  const maxI = Math.max(1, ...samples.map((s) => s.intensity));

  // grid
  ctx.strokeStyle = 'rgba(150,165,210,0.18)';
  ctx.fillStyle = 'rgba(150,165,210,0.55)';
  ctx.font = '10px system-ui';
  for (let r = 1; r <= 3; r++) {
    ctx.beginPath(); ctx.arc(cx, cy, (R * r) / 3, 0, Math.PI * 2); ctx.stroke();
  }
  for (let a = 0; a < 360; a += 30) {
    const rad = (a - 90) * Math.PI / 180;
    ctx.beginPath(); ctx.moveTo(cx, cy);
    ctx.lineTo(cx + Math.cos(rad) * R, cy + Math.sin(rad) * R); ctx.stroke();
  }

  // curve: gamma 0 points straight down (nadir).
  ctx.strokeStyle = '#22d3ee';
  ctx.fillStyle = 'rgba(34,211,238,0.16)';
  ctx.lineWidth = 2;
  const plot = (sign) => {
    ctx.beginPath();
    samples.forEach((s, i) => {
      const rr = (s.intensity / maxI) * R;
      const ang = (90 + sign * s.angle) * Math.PI / 180; // 0 = down
      const x = cx + Math.cos(ang) * rr, y = cy + Math.sin(ang) * rr;
      i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.stroke();
  };
  plot(1); plot(-1);

  $('#polar-max').textContent = Math.round(maxI) + ' cd/klm';
}

// ---- export -----------------------------------------------------------
function refreshExport() {
  const ready = !!state.ldt || !!state.mesh;
  const btn = $('#export-btn');
  btn.disabled = !ready;
  let hint;
  if (!ready) hint = 'Build a preset shape or load a 3D model / LDT to enable export.';
  else if (state.ldt && state.mesh) hint = 'Ready — IFC embeds the geometry + photometric data.';
  else if (state.mesh) hint = 'Ready — geometry only. Add an LDT to include photometric/electrical data.';
  else hint = 'Ready — photometry only. IFC will use a dimensioned box (add a model or shape for geometry).';
  $('#export-hint').textContent = hint;
}

function collectMeta() {
  return {
    manufacturer: $('#meta-manufacturer').value.trim(),
    model: $('#meta-model').value.trim(),
    reference: $('#meta-reference').value.trim(),
    mounting: $('#meta-mounting').value,
    year: $('#meta-year').value.trim(),
    tag: $('#meta-tag').value.trim(),
    author: 'Bim Builder',
    ldtFileName: state.ldtFileName,
    datasheetName: state.datasheetName,
    now: new Date(),
  };
}

function doExport() {
  if (!state.ldt) return;
  const ifc = buildIfc({ ldt: state.ldt, meta: collectMeta(), mesh: state.mesh });
  const name = ($('#meta-model').value.trim() || 'luminaire').replace(/[^\w.-]+/g, '_');
  download(ifc, name + '.ifc', 'application/x-step');
}

function download(text, filename, mime) {
  const blob = new Blob([text], { type: mime });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

// ---- preset shape configurator ---------------------------------------
const PARAM_META = {
  length: ['Length', 'mm'], armA: ['Arm A', 'mm'], armB: ['Arm B', 'mm'],
  width: ['Width', 'mm'], depth: ['Depth', 'mm'], armX: ['Arm X (horizontal)', 'mm'],
  armY: ['Arm Y (vertical)', 'mm'], radius: ['Radius', 'mm'], sweep: ['Sweep angle', '°'],
  repeats: ['Repeats', ''], profileWidth: ['Profile width', 'mm'], profileHeight: ['Profile height', 'mm'],
};

function buildPresetUI() {
  const famSel = $('#preset-family');
  const shapeSel = $('#preset-shape');
  Object.entries(FAMILIES).forEach(([key, f]) => famSel.add(new Option(f.label, key)));

  const fillShapes = () => {
    shapeSel.innerHTML = '';
    const shapes = FAMILIES[famSel.value].shapes;
    Object.entries(shapes).forEach(([key, s]) => shapeSel.add(new Option(s.label, key)));
    fillParams();
  };
  const fillParams = () => {
    const fam = FAMILIES[famSel.value];
    const shape = fam.shapes[shapeSel.value];
    const params = { ...shape.params, profileWidth: fam.profile.width, profileHeight: fam.profile.height };
    $('#preset-params').innerHTML = Object.entries(params).map(([k, v]) => {
      const [label, unit] = PARAM_META[k] || [k, ''];
      return `<div><label>${label}${unit ? ' (' + unit + ')' : ''}</label>
        <input type="number" data-param="${k}" value="${v}" min="1" step="${k === 'sweep' || k === 'repeats' ? 1 : 10}"></div>`;
    }).join('');
  };

  famSel.addEventListener('change', fillShapes);
  shapeSel.addEventListener('change', fillParams);
  $('#preset-generate').addEventListener('click', generateShape);
  fillShapes();
}

function collectParams() {
  const p = {};
  $$('#preset-params input[data-param]').forEach((el) => { p[el.dataset.param] = parseFloat(el.value) || 0; });
  return p;
}

async function generateShape() {
  const family = $('#preset-family').value;
  const shape = $('#preset-shape').value;
  const params = collectParams();
  const hint = $('#preset-hint');
  try {
    const mesh = buildMesh(family, shape, params);
    state.mesh = mesh;
    const dims = meshBounds(mesh);
    state.shapeInfo = { family, shape, dims };
    const tris = mesh.indices.length / 3;
    hint.textContent = `Generated ${FAMILIES[family].label.split(' —')[0]} ${FAMILIES[family].shapes[shape].label} · ${dims.length}×${dims.width}×${dims.height} mm · ${tris.toLocaleString()} triangles`;
    hint.className = 'hint';

    // default product metadata from the preset (don't clobber user edits)
    const famName = FAMILIES[family].label.split(' —')[0];
    if (!$('#meta-model').value) $('#meta-model').value = `${famName} ${FAMILIES[family].shapes[shape].label}`;
    if (!$('#meta-manufacturer').value) $('#meta-manufacturer').value = famName;
    $('#m-dim').textContent = `${dims.length} × ${dims.width} × ${dims.height} mm`;

    $('#review').hidden = false;
    $('#model-card').hidden = false;
    if (!modelMod) modelMod = await import('./model.js');
    modelMod.preview(modelMod.meshToObject(mesh), $('#model-canvas'));
    refreshExport();
  } catch (err) {
    hint.textContent = 'Could not generate: ' + err.message;
    hint.className = 'hint';
  }
}

// ---- tab switching (single / batch) ----------------------------------
let batchReady = false;
function showTab(which) {
  const single = which === 'single';
  $('#single-mode').hidden = !single;
  $('#batch-mode').hidden = single;
  $('#tab-single').classList.toggle('active', single);
  $('#tab-batch').classList.toggle('active', !single);
  if (!single && !batchReady) {
    batchReady = true;
    import('./batch.js').then((m) => m.initBatch($('#batch-root')));
  }
}

// ---- init -------------------------------------------------------------
buildPresetUI();
wireDropZone('datasheet-dz', 'datasheet-input', handleDatasheet);
wireDropZone('model-dz', 'model-input', handleModel);
wireDropZone('ldt-dz', 'ldt-input', handleLdt);
$('#export-btn').addEventListener('click', doExport);
$('#tab-single').addEventListener('click', () => showTab('single'));
$('#tab-batch').addEventListener('click', () => showTab('batch'));
refreshExport();

// register service worker for offline / installable PWA
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('sw.js').catch(() => {});
}
