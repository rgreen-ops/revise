/* Shared "length → light output" helper. Linear LED product? You don't need a
   per-size LDT: measure the lit run length, multiply by the per-metre figures,
   and synthesise the photometry. Works for presets (exact path length) and for
   uploaded models (estimated from the longest horizontal extent). */

import { writeEulumdat } from './ldt-write.js';
import { meshBounds } from './shapes.js';

/* Rough run length (mm) for an uploaded model: the longest horizontal extent.
   Good for straight/L runs; for tightly curved imports prefer a preset or the
   Flow custom tab where the true centreline length is known. */
export function lengthFromMesh(mesh) {
  const b = meshBounds(mesh);
  return Math.max(b.length, b.width);
}

/* Derive photometric/electrical figures from a run length and per-metre spec,
   and provide a matching LDT generator. */
export function autoPhotometry({ lengthMm, lumensPerMetre, wattsPerMetre, cri, cct }) {
  const m = (lengthMm || 0) / 1000;
  const luminousFlux = Math.round((lumensPerMetre || 0) * m);
  const wattage = round((wattsPerMetre || 0) * m, 1);
  const efficacy = wattage > 0 ? round(luminousFlux / wattage, 1) : 0;
  const derived = { luminousFlux, wattage, efficacy, colorTemp: cct || 0, cri: cri || 0, lampType: 'LED' };

  return {
    derived,
    lengthMm,
    makeLdt(dims, names) {
      return writeEulumdat({
        company: names.manufacturer, name: names.model, number: names.reference,
        fileName: (names.reference || 'luminaire') + '.ldt',
        lengthMm: dims.length, widthMm: dims.width, heightMm: dims.height,
        flux: luminousFlux, wattage, cct, cri,
      });
    },
  };
}

function round(n, d) { const f = Math.pow(10, d); return Math.round((Number(n) || 0) * f) / f; }
