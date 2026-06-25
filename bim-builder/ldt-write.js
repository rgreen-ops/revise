/* Eulumdat (.ldt) writer. For made-to-order Flow arcs we synthesise a photometric
   file scaled to the part's total lumens so the architect gets usable photometry
   alongside the geometry — no measurement needed per size.

   The distribution is modelled as Lambertian (I(γ) = I0·cos γ), which suits a
   diffused linear LED profile. Intensities are stored as cd/1000lm (Eulumdat
   convention); for Lambertian over the lower hemisphere I0 = 1000/π ≈ 318 cd/klm
   at nadir. Swap in measured per-variant data later for exact photometry. */

const C_STEP = 15;   // -> 24 C-planes
const G_STEP = 5;    // -> 19 gamma angles 0..90

export function writeEulumdat(o) {
  const lines = [];
  const push = (v) => lines.push(String(v));

  const cAngles = range(0, 360, C_STEP, false); // 0..345 (24)
  const gAngles = range(0, 90, G_STEP, true);   // 0..90 (19)
  const Mc = cAngles.length, Ng = gAngles.length;

  push(o.company || 'Ricoman');           // 1 identification
  push(1);                                // 2 Ityp: point source w/ vertical-axis symmetry
  push(1);                                // 3 Isym: rotational symmetry
  push(Mc);                               // 4 number of C-planes
  push(C_STEP);                           // 5 distance between C-planes
  push(Ng);                               // 6 intensities per C-plane
  push(G_STEP);                           // 7 distance between intensities
  push(o.reportNo || 'BIMBUILDER');       // 8 measurement report number
  push(o.name || 'Flow Custom');          // 9 luminaire name
  push(o.number || '');                   // 10 luminaire number
  push(o.fileName || 'flow-custom.ldt');  // 11 file name
  push(o.date || 'Bim Builder');          // 12 date / user
  push(round(o.lengthMm || 0));           // 13 length / diameter
  push(round(o.widthMm || 0));            // 14 width (0 = round)
  push(round(o.heightMm || 0));           // 15 height
  push(round(o.luminousLengthMm || o.lengthMm || 0)); // 16 luminous length / diameter
  push(round(o.luminousWidthMm || o.widthMm || 0));   // 17 luminous width
  push(0); push(0); push(0); push(0);     // 18-21 luminous heights C0/C90/C180/C270
  push(100);                              // 22 downward flux fraction %
  push(100);                              // 23 light output ratio %
  push(1.0);                              // 24 conversion factor
  push(0);                                // 25 tilt
  push(1);                                // 26 number of lamp sets
  push(1);                                // - number of lamps
  push(o.lampType || 'LED');              // - type of lamps
  push(round(o.flux || 0));               // - total luminous flux (lm)
  push(o.cct ? o.cct + 'K' : 'LED');      // - colour appearance
  push(o.cri || 80);                      // - colour rendering index
  push(round(o.wattage || 0, 1));         // - wattage incl. ballast
  for (let d = 0; d < 10; d++) push(0);   // 27 direct ratios

  cAngles.forEach(push);                  // C-plane angles
  gAngles.forEach(push);                  // gamma angles

  // one stored plane (Isym=1): Lambertian cd/klm
  const I0 = 1000 / Math.PI;
  gAngles.forEach((g) => push(round(I0 * Math.cos((g * Math.PI) / 180), 1)));

  return lines.join('\r\n') + '\r\n';
}

function range(start, end, step, inclusive) {
  const out = [];
  for (let v = start; inclusive ? v <= end + 1e-9 : v < end - 1e-9; v += step) out.push(v);
  return out;
}
function round(n, d = 0) { const f = Math.pow(10, d); return Math.round((Number(n) || 0) * f) / f; }
