/* Purchasing Bot — persistent state.

   The scoping doc is explicit that this needs to hold state between runs: what
   is on order, what has been flagged, carriage progress, the approval queue, and
   an append-only price change log. In the full Ricobot service that's SQLite;
   here, in the same zero-build PWA shape as the rest of the repo, it's
   localStorage behind a small typed API so the storage backend can be swapped
   later without touching the engines.

   The price ledger is APPEND-ONLY by design: entries are only ever pushed, never
   edited or removed, so there is a clean audit trail of what changed, when, and
   who approved it (mirroring the price-ledger pattern used elsewhere in Ricobot). */

const KEY = 'ricobot.purchasing.v1';

const DEFAULT_SETTINGS = {
  // Unleashed connection
  proxyBase: '',
  currency: 'GBP',
  // Price rule (open questions #2 and #3 from the scoping doc made configurable)
  buyPriceField: 'lastCost',        // 'defaultPurchasePrice' | 'lastCost' | 'averageLandedCost'
  priceMultiplier: 3.5,
  roundingMode: 'up2',
  // Replenishment phasing. autoPlace is the SINGLE flag that flips v1 -> v2:
  // false = create Parked POs and queue the email (draft); true = create Placed
  // POs and send the email. The logic is otherwise identical.
  autoPlace: false,
  defaultTaxRate: 0.20,
  approver: '',
};

function blank() {
  return {
    settings: { ...DEFAULT_SETTINGS },
    suppliers: [],          // supplier settings table (carriage / MOQ / lead time)
    productMoq: {},         // productCode -> MOQ override
    priceLedger: [],        // append-only
    priceQueue: [],         // pending price changes awaiting approval
    poQueue: [],            // pending purchase orders awaiting approval (v1)
    onOrder: {},            // productCode -> qty currently on order
    activity: [],           // run log (polls, writes)
  };
}

let _state = load();

function load() {
  try {
    const raw = localStorage.getItem(KEY);
    if (!raw) return blank();
    return { ...blank(), ...JSON.parse(raw) };
  } catch {
    return blank();
  }
}

function persist() {
  try { localStorage.setItem(KEY, JSON.stringify(_state)); } catch { /* private mode / quota */ }
}

// ---- settings --------------------------------------------------------------
export function getSettings() { return { ...DEFAULT_SETTINGS, ..._state.settings }; }
export function saveSettings(patch) { _state.settings = { ...getSettings(), ...patch }; persist(); return getSettings(); }

// ---- supplier settings table ----------------------------------------------
export function getSuppliers() { return structuredClone(_state.suppliers); }
export function setSuppliers(list) { _state.suppliers = structuredClone(list); persist(); }
export function upsertSupplier(sup) {
  const i = _state.suppliers.findIndex((s) => s.supplierCode === sup.supplierCode);
  if (i >= 0) _state.suppliers[i] = { ..._state.suppliers[i], ...sup };
  else _state.suppliers.push(sup);
  persist();
}
export function getSupplier(code) { return _state.suppliers.find((s) => s.supplierCode === code) || null; }

// Seed supplier settings the first time (from the demo set or a live import).
export function seedSuppliersIfEmpty(list) {
  if (_state.suppliers.length === 0) { _state.suppliers = structuredClone(list); persist(); }
}

// ---- per-product MOQ overrides ---------------------------------------------
export function getProductMoq() { return { ..._state.productMoq }; }
export function setProductMoq(code, qty) {
  if (qty == null || qty === '') delete _state.productMoq[code];
  else _state.productMoq[code] = Number(qty);
  persist();
}

// ---- on-order tracking -----------------------------------------------------
export function getOnOrder() { return { ..._state.onOrder }; }
export function addOnOrder(code, qty) { _state.onOrder[code] = (_state.onOrder[code] || 0) + Number(qty); persist(); }
export function clearOnOrder(code) { delete _state.onOrder[code]; persist(); }

// ---- price approval queue --------------------------------------------------
export function getPriceQueue() { return structuredClone(_state.priceQueue); }
export function setPriceQueue(items) { _state.priceQueue = structuredClone(items); persist(); }
export function removeFromPriceQueue(guid) { _state.priceQueue = _state.priceQueue.filter((x) => x.guid !== guid); persist(); }

// ---- append-only price ledger ----------------------------------------------
export function getLedger() { return structuredClone(_state.priceLedger); }
export function appendLedger(entry) { _state.priceLedger.push(structuredClone(entry)); persist(); }

// ---- PO approval queue -----------------------------------------------------
export function getPoQueue() { return structuredClone(_state.poQueue); }
export function setPoQueue(items) { _state.poQueue = structuredClone(items); persist(); }
export function removeFromPoQueue(id) { _state.poQueue = _state.poQueue.filter((x) => x.id !== id); persist(); }

// ---- activity log ----------------------------------------------------------
export function getActivity() { return structuredClone(_state.activity); }
export function logActivity(entry) {
  _state.activity.unshift({ at: new Date().toISOString(), ...entry });
  _state.activity = _state.activity.slice(0, 200);
  persist();
}

// ---- danger zone -----------------------------------------------------------
export function resetAll() { _state = blank(); persist(); }
export function exportJson() { return JSON.stringify(_state, null, 2); }
