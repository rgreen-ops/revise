/* Purchasing Bot — small shared helpers (money, rounding, dates, ids).
   Vanilla ES module, no build step, to match the rest of the repo. */

// ---- money / rounding ------------------------------------------------------
// All prices are held as plain numbers in the product currency. We never trust
// binary floats for display, so money() formats to 2dp and round2() snaps the
// stored value so totals add up.
export function round2(n) { return Math.round((Number(n) + Number.EPSILON) * 100) / 100; }

export function money(n, currency = 'GBP') {
  const v = Number(n) || 0;
  try {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency }).format(v);
  } catch {
    return '£' + round2(v).toFixed(2);
  }
}

// Rounding conventions for a proposed sell price. The 3.5x rule is a *floor*, so
// every convention here rounds UP (or to a value >= the floor) — we must never
// round a proposed price back under buy * multiplier. Open question #3 in the
// scoping doc: the convention is configurable here rather than hard-coded.
export const ROUNDING = {
  up2:    { label: 'Round up to 2 dp',        apply: (v) => Math.ceil(v * 100) / 100 },
  up5p:   { label: 'Round up to nearest 5p',  apply: (v) => Math.ceil(v * 20) / 20 },
  up50p:  { label: 'Round up to nearest 50p', apply: (v) => Math.ceil(v * 2) / 2 },
  upWhole:{ label: 'Round up to whole £',     apply: (v) => Math.ceil(v) },
  end99:  { label: "Round up to a .99 ending", apply: (v) => { const w = Math.ceil(v); return round2(w - 0.01 >= v ? w - 0.01 : w + 0.99); } },
};

export function roundPrice(value, mode = 'up2') {
  const r = ROUNDING[mode] || ROUNDING.up2;
  return round2(r.apply(Number(value)));
}

// ---- dates -----------------------------------------------------------------
// new Date() / Date.now() are fine in the browser; this app is not a workflow
// script. We keep ISO dates (YYYY-MM-DD) for storage and a friendlier display.
export function today() { return new Date(); }

export function isoDate(d = new Date()) { return new Date(d).toISOString().slice(0, 10); }

export function addDays(d, days) {
  const x = new Date(d);
  x.setDate(x.getDate() + Number(days || 0));
  return x;
}

export function fmtDate(d) {
  if (!d) return '—';
  const x = new Date(d);
  return x.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function fmtDateTime(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

// ---- ids -------------------------------------------------------------------
let _seq = 0;
export function uid(prefix = 'id') {
  _seq += 1;
  return `${prefix}_${Date.now().toString(36)}_${_seq.toString(36)}`;
}

// ---- misc ------------------------------------------------------------------
export function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

export function sum(arr, pick = (x) => x) { return arr.reduce((a, x) => a + (Number(pick(x)) || 0), 0); }

export function groupBy(arr, key) {
  const m = new Map();
  for (const item of arr) {
    const k = key(item);
    if (!m.has(k)) m.set(k, []);
    m.get(k).push(item);
  }
  return m;
}
