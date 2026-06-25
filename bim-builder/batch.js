/* Batch mode: queue up many products, drop three files into each, let the app
   auto-read the datasheet + LDT, then export every IFC in one ZIP. Built for
   volume — add a product, drop its files, move straight on to the next while the
   reads happen in the background. */

import { parseLdt } from './ldt.js';
import { buildIfc } from './ifc.js';
import { extractFromFile } from './datasheet.js';
import { makeZip } from './zip.js';

let modelMod = null;          // lazy three.js (only if a model needs embedding)
let seq = 0;
const rows = new Map();       // id -> row state

export function initBatch(root) {
  root.innerHTML = `
    <div class="card">
      <div class="batch-bar">
        <button class="btn ghost" id="batch-add">＋ New product</button>
        <button class="btn" id="batch-export" disabled>⬇ Export all (ZIP)</button>
        <span class="hint" id="batch-status">No products yet.</span>
      </div>
    </div>
    <div id="batch-list"></div>`;
  root.querySelector('#batch-add').addEventListener('click', () => addRow(root));
  root.querySelector('#batch-export').addEventListener('click', () => exportAll(root));
  addRow(root);
}

function addRow(root) {
  const id = 'p' + (++seq);
  const row = { id, code: '', desc: '', manufacturer: '', ldt: null, mesh: null, fields: {}, files: {} };
  rows.set(id, row);

  const el = document.createElement('div');
  el.className = 'card batch-row';
  el.dataset.id = id;
  el.innerHTML = `
    <div class="batch-head">
      <span class="batch-idx">#${seq}</span>
      <input class="b-code" placeholder="Part code (auto from datasheet)">
      <input class="b-desc" placeholder="Description (auto from datasheet)">
      <button class="b-remove" title="Remove">✕</button>
    </div>
    <div class="batch-files">
      ${fileBtn(id, 'datasheet', '📄 Datasheet', '.pdf,.txt,.csv')}
      ${fileBtn(id, 'model', '🧊 3D model', '.gltf,.glb,.obj,.stl')}
      ${fileBtn(id, 'ldt', '💡 LDT', '.ldt')}
    </div>
    <div class="batch-derived" data-derived></div>`;
  root.querySelector('#batch-list').appendChild(el);

  el.querySelector('.b-remove').addEventListener('click', () => { rows.delete(id); el.remove(); refresh(root); });
  el.querySelector('.b-code').addEventListener('input', (e) => { row.code = e.target.value; refresh(root); });
  el.querySelector('.b-desc').addEventListener('input', (e) => { row.desc = e.target.value; });
  el.querySelectorAll('input[type=file]').forEach((inp) => {
    inp.addEventListener('change', () => inp.files[0] && onFile(root, id, inp.dataset.kind, inp.files[0]));
  });
  refresh(root);
}

function fileBtn(id, kind, label, accept) {
  return `<label class="b-file" data-slot="${kind}">
    <input type="file" data-kind="${kind}" accept="${accept}" hidden>
    <span class="b-file-label">${label}</span>
    <span class="b-file-status"></span>
  </label>`;
}

async function onFile(root, id, kind, file) {
  const row = rows.get(id);
  if (!row) return;
  row.files[kind] = file.name;
  const el = root.querySelector(`.batch-row[data-id="${id}"]`);
  const slot = el.querySelector(`.b-file[data-slot="${kind}"]`);
  const statusEl = slot.querySelector('.b-file-status');
  slot.classList.add('busy'); statusEl.textContent = '…';

  try {
    if (kind === 'datasheet') {
      const { fields } = await extractFromFile(file);
      row.fields = fields;
      if (fields.reference && !row.code) { row.code = fields.reference; el.querySelector('.b-code').value = fields.reference; }
      if (fields.model && !row.desc) { row.desc = fields.model; el.querySelector('.b-desc').value = fields.model; }
      if (fields.manufacturer) row.manufacturer = fields.manufacturer;
    } else if (kind === 'ldt') {
      row.ldt = parseLdt(await file.text());
      if (!row.code && row.ldt.luminaireNumber) { row.code = row.ldt.luminaireNumber; el.querySelector('.b-code').value = row.code; }
      if (!row.desc && row.ldt.luminaireName) { row.desc = row.ldt.luminaireName; el.querySelector('.b-desc').value = row.desc; }
    } else if (kind === 'model') {
      if (!modelMod) modelMod = await import('./model.js');
      const { mesh } = await modelMod.loadModel(file);
      row.mesh = mesh;
    }
    slot.classList.remove('busy'); slot.classList.add('done'); statusEl.textContent = '✓';
  } catch (err) {
    slot.classList.remove('busy'); slot.classList.add('err'); statusEl.textContent = '!';
    statusEl.title = err.message;
  }
  renderDerived(el, row);
  refresh(root);
}

function renderDerived(el, row) {
  const d = row.ldt ? row.ldt.derived : null;
  const bits = [];
  if (d) {
    if (d.wattage) bits.push(`${d.wattage} W`);
    if (d.luminousFlux) bits.push(`${Math.round(d.luminousFlux)} lm`);
    if (d.efficacy) bits.push(`${d.efficacy.toFixed(0)} lm/W`);
    if (d.colorTemp) bits.push(`${d.colorTemp} K`);
    if (d.cri) bits.push(`Ra ${d.cri}`);
  }
  if (row.mesh) bits.push(`${(row.mesh.indices.length / 3).toLocaleString()} tris`);
  el.querySelector('[data-derived]').textContent = bits.join('  ·  ') || (ready(row) ? '' : 'Add an LDT or a 3D model to make this product exportable.');
}

const ready = (row) => !!row.ldt || !!row.mesh;

function refresh(root) {
  const all = [...rows.values()];
  const readyCount = all.filter(ready).length;
  root.querySelector('#batch-export').disabled = readyCount === 0;
  root.querySelector('#batch-status').textContent =
    `${all.length} product${all.length === 1 ? '' : 's'} · ${readyCount} ready to export`;
}

function metaFor(row) {
  const f = row.fields || {};
  return {
    manufacturer: row.manufacturer || f.manufacturer || (row.ldt && row.ldt.company) || '',
    model: row.desc || f.model || (row.ldt && row.ldt.luminaireName) || 'Luminaire',
    reference: row.code || f.reference || (row.ldt && row.ldt.luminaireNumber) || '',
    mounting: 'Recessed',
    datasheetName: row.files.datasheet || '',
    ldtFileName: row.files.ldt || '',
    now: new Date(),
  };
}

function safeName(s, fallback) {
  const base = (s || fallback || 'luminaire').replace(/[^\w.-]+/g, '_').replace(/^_+|_+$/g, '');
  return (base || fallback) + '.ifc';
}

async function exportAll(root) {
  const ready_rows = [...rows.values()].filter(ready);
  if (!ready_rows.length) return;
  const status = root.querySelector('#batch-status');
  status.textContent = `Generating ${ready_rows.length} IFC files…`;

  const used = new Set();
  const files = ready_rows.map((row) => {
    const meta = metaFor(row);
    const text = buildIfc({ ldt: row.ldt, mesh: row.mesh, meta });
    let name = safeName(meta.reference || meta.model, row.id);
    while (used.has(name)) name = name.replace(/(\.ifc)$/, '_' + row.id + '$1');
    used.add(name);
    return { name, text };
  });

  const blob = makeZip(files);
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'bim-builder-export.zip';
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 1500);
  status.textContent = `Exported ${files.length} IFC file${files.length === 1 ? '' : 's'} as ZIP.`;
}
