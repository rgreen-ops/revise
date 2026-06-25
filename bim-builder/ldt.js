/* Eulumdat (.ldt) photometric file parser.
   Eulumdat is a line-based format (one value per line). Reference:
   https://en.wikipedia.org/wiki/EULUMDAT  and the original Stockmar spec.
   We parse the fixed header, the lamp sets, the C/gamma angle arrays and the
   luminous-intensity matrix, then derive the headline electrical/photometric
   figures Bim Builder needs for the BIM property sets. */

export function parseLdt(text) {
  // Eulumdat is CRLF-delimited but we tolerate either. Keep blank lines —
  // positions are significant.
  const L = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
  let i = 0;
  const next = () => (i < L.length ? L[i++] : '').trim();
  const num = (v) => {
    const n = parseFloat(String(v).replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
  };

  const company = next();              // 1  identification / company
  const Ityp = num(next());            // 2  type indicator
  const Isym = num(next());            // 3  symmetry indicator
  const Mc = num(next());              // 4  number of C-planes (0..360)
  const Dc = num(next());              // 5  distance between C-planes
  const Ng = num(next());              // 6  number of intensities per C-plane
  const Dg = num(next());              // 7  distance between intensities
  const reportNo = next();             // 8  measurement report number
  const luminaireName = next();        // 9  luminaire name
  const luminaireNumber = next();      // 10 luminaire number
  const fileName = next();             // 11 file name
  const dateUser = next();             // 12 date / user

  const lenLuminaire = num(next());    // 13 length / diameter (mm)
  const widthLuminaire = num(next());  // 14 width (mm), 0 = circular
  const heightLuminaire = num(next()); // 15 height (mm)
  const lenLuminous = num(next());     // 16 luminous area length / diameter
  const widthLuminous = num(next());   // 17 luminous area width
  const hLum0 = num(next());           // 18 luminous height C0
  const hLum90 = num(next());          // 19 luminous height C90
  const hLum180 = num(next());         // 20 luminous height C180
  const hLum270 = num(next());         // 21 luminous height C270

  const dff = num(next());             // 22 downward flux fraction (%)
  const lorl = num(next());            // 23 light output ratio luminaire (%)
  const convFactor = num(next());      // 24 conversion factor for intensities
  const tilt = num(next());            // 25 tilt during measurement

  const nLampSets = Math.abs(num(next())) || 1; // 26 number of standard lamp sets
  const absolute = num(L[i - 1]) < 0;  // negative => absolute photometry

  const lampSets = [];
  for (let s = 0; s < nLampSets; s++) {
    lampSets.push({
      count: next(),                   // number of lamps (may be a string like "1xLED")
      type: next(),                    // type of lamps
      flux: num(next()),               // total luminous flux of lamps (lm)
      colorTemp: next(),               // color appearance / temperature
      cri: next(),                     // colour rendering index
      wattage: num(next()),            // wattage incl. ballast (W)
    });
  }

  // 10 direct-ratio values for room indices k = 0.6 .. 5
  const directRatios = [];
  for (let d = 0; d < 10; d++) directRatios.push(num(next()));

  // C-plane angles (Mc values) and gamma angles (Ng values)
  const cAngles = [];
  for (let c = 0; c < Mc; c++) cAngles.push(num(next()));
  const gAngles = [];
  for (let g = 0; g < Ng; g++) gAngles.push(num(next()));

  // Number of stored C-planes depends on the symmetry indicator.
  let planesStored;
  switch (Isym) {
    case 1: planesStored = 1; break;        // rotational symmetry
    case 2: planesStored = Mc / 2 + 1; break;
    case 3: planesStored = Mc / 2 + 1; break;
    case 4: planesStored = Mc / 4 + 1; break;
    default: planesStored = Mc;             // Isym 0: all planes
  }
  planesStored = Math.max(1, Math.round(planesStored));

  // Luminous-intensity matrix: planesStored columns x Ng rows (cd/klm).
  const intensities = [];
  for (let p = 0; p < planesStored; p++) {
    const plane = [];
    for (let g = 0; g < Ng; g++) plane.push(num(next()));
    intensities.push(plane);
  }

  // Derived headline figures (use the first lamp set as the rated set).
  const set = lampSets[0] || { flux: 0, wattage: 0, colorTemp: '', cri: '', type: '' };
  const totalFlux = set.flux;
  const totalWatts = set.wattage;
  const efficacy = totalWatts > 0 ? totalFlux / totalWatts : 0;

  return {
    company, Ityp, Isym, Mc, Dc, Ng, Dg,
    reportNo, luminaireName, luminaireNumber, fileName, dateUser,
    dimensions: {
      length: lenLuminaire, width: widthLuminaire, height: heightLuminaire,
      luminousLength: lenLuminous, luminousWidth: widthLuminous,
      circular: widthLuminaire === 0,
    },
    dff, lorl, convFactor, tilt, absolute,
    lampSets,
    directRatios,
    cAngles, gAngles, intensities,
    derived: {
      luminousFlux: totalFlux,
      wattage: totalWatts,
      efficacy,
      colorTemp: parseColorTemp(set.colorTemp),
      cri: parseCri(set.cri),
      lampType: set.type,
    },
  };
}

// Eulumdat often encodes colour appearance like "4000K" or a 3-digit code
// where the last two digits are the CCT in hundreds (e.g. "840" => 4000K).
function parseColorTemp(raw) {
  if (!raw) return 0;
  const s = String(raw).trim();
  const k = s.match(/(\d{3,5})\s*k/i);
  if (k) return parseInt(k[1], 10);
  const code = s.match(/^\d(\d{2})$/);          // 3-digit lamp code e.g. 840
  if (code) return parseInt(code[1], 10) * 100;
  const n = parseInt(s, 10);
  if (Number.isFinite(n) && n >= 1000) return n; // already a kelvin value
  return 0;
}

function parseCri(raw) {
  if (!raw) return 0;
  const s = String(raw).trim();
  const ra = s.match(/(\d{2,3})/);              // first 2-3 digit group
  if (ra) {
    const v = parseInt(ra[1], 10);
    if (v >= 0 && v <= 100) return v;
    if (v >= 800 && v <= 999) return parseInt(String(v)[0], 10) * 10; // "8xx" => 80
  }
  return 0;
}

/* Return the C0 (and mirrored C180) gamma/intensity samples for a polar plot. */
export function polarSamples(ldt) {
  const g = ldt.gAngles;
  const c0 = ldt.intensities[0] || [];
  return g.map((angle, idx) => ({ angle, intensity: c0[idx] || 0 }));
}
