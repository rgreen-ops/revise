/* Product spec registry for parametric / made-to-order ranges. For Flow the
   luminaire is a constant LED profile bent to a chosen radius and arc angle, so
   everything (length, total lumens, wattage, photometry) is derived from the
   per-metre figures here plus the two parameters the customer gives.

   These are starting defaults and are EDITABLE in the UI — confirm the real
   Ricoman per-metre output / load / cross-section and they become exact. */

export const FLOW_VARIANTS = {
  'flow-15-diffused': {
    label: 'Flow 15 — diffused',
    profileWidth: 15, profileHeight: 45,   // mm cross-section (bend section)
    lumensPerMetre: 800, wattsPerMetre: 9, cri: 90,
    ccts: [2700, 3000, 4000], defaultCct: 3000,
  },
  'flow-15-opal': {
    label: 'Flow 15 — opal',
    profileWidth: 15, profileHeight: 45,
    lumensPerMetre: 700, wattsPerMetre: 9, cri: 90,
    ccts: [2700, 3000, 4000], defaultCct: 3000,
  },
  'flow-30-direct': {
    label: 'Flow 30 — direct',
    profileWidth: 30, profileHeight: 50,
    lumensPerMetre: 1400, wattsPerMetre: 15, cri: 90,
    ccts: [2700, 3000, 4000], defaultCct: 4000,
  },
};

/* Given a variant spec + customer parameters, derive the made-to-order
   luminaire: arc length, total flux, wattage, efficacy and a stable order code.
   dimension is the chosen size in mm; dimensionMode is 'diameter' or 'radius'.
   sweep is the arc angle in degrees. */
export function deriveFlow(spec, p) {
  const radiusMm = p.dimensionMode === 'diameter' ? p.dimension / 2 : p.dimension;
  const sweepRad = (p.sweep * Math.PI) / 180;
  const arcLenMm = radiusMm * sweepRad;
  const arcLenM = arcLenMm / 1000;

  const luminousFlux = Math.round(spec.lumensPerMetre * arcLenM);
  const wattage = round(spec.wattsPerMetre * arcLenM, 1);
  const efficacy = wattage > 0 ? round(luminousFlux / wattage, 1) : 0;

  const diameterMm = p.dimensionMode === 'diameter' ? p.dimension : p.dimension * 2;
  const code = p.code || `FLW-${Math.round(diameterMm)}-${Math.round(p.sweep)}-${p.cct}`;

  return {
    radiusMm, arcLenMm, arcLenM, diameterMm,
    luminousFlux, wattage, efficacy,
    cct: p.cct, cri: spec.cri,
    code,
  };
}

function round(n, d) { const f = Math.pow(10, d); return Math.round((Number(n) || 0) * f) / f; }
