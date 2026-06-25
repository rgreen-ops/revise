/* Bim Builder — orchestration. Wires the three drop zones (datasheet, 3D model,
   LDT) to the parser, previews and the IFC exporter. Vanilla ES modules, no
   build step, to match the rest of the repo. */

import { parseLdt, polarSamples } from './ldt.js';
import { buildIfc } from './ifc.js';

// three.js preview is optional (needs network for the CDN). Loaded lazily so the
// core LDT -> IFC pipeline works even offline / if the CDN is blocked.
let modelMod = null;

const state = {
  ldt: null,
  ldtFileName: '',
  mesh: null,
  modelObject: null,
  datasheetName: '',
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

function handleDatasheet(file) {
  state.datasheetName = file.name;
  setStatus($('#datasheet-status'), true, file.name);
  $('#meta-datasheet').value = file.name;
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
  const ready = !!state.ldt;
  const btn = $('#export-btn');
  btn.disabled = !ready;
  $('#export-hint').textContent = ready
    ? (state.mesh ? 'Ready — IFC will embed your 3D mesh + photometric data.'
                  : 'Ready — no 3D model loaded, IFC will use a dimensioned box.')
    : 'Load an LDT photometric file to enable export.';
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

// ---- init -------------------------------------------------------------
wireDropZone('datasheet-dz', 'datasheet-input', handleDatasheet);
wireDropZone('model-dz', 'model-input', handleModel);
wireDropZone('ldt-dz', 'ldt-input', handleLdt);
$('#export-btn').addEventListener('click', doExport);
refreshExport();

// register service worker for offline / installable PWA
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('sw.js').catch(() => {});
}
