/* Purchasing Bot — Unleashed adapter.

   This is the single seam between the engines and Unleashed. It deliberately
   exposes the *minimum* surface the scoping doc needs:

     - listProducts()      read products + buy/sell prices
     - listStockOnHand()   current holdings
     - listSalesOrders()   outstanding demand (supports modifiedSince server-side)
     - getProduct(guid)    full record for read-modify-write
     - updateProduct(p)    write a changed sell price back (whole record)
     - createPurchaseOrder(po)  create a PO (Parked in v1, Placed in v2)

   Two modes:

     DEMO  — no proxy configured. Reads from sample-data.js and keeps writes in
             memory so the whole flow (flag -> approve -> write -> ledger) is
             demonstrable offline, the same way Bim Builder ships a sample LDT.

     LIVE  — a proxyBase is configured in Settings. Every call goes to that
             server-side proxy, which holds the Unleashed API id/key and does the
             HMAC-SHA256 signing. The browser NEVER sees the secret — that is why
             the scoping doc insists this is "a proper service, not a script".

   Unleashed specifics baked into the contract here, straight from the docs:
     - Pull only, no webhooks: callers poll on a timer.
     - You cannot post multiple orders in one request: createPurchaseOrder is one
       PO per call; the engine loops per supplier.
     - Updates overwrite: updateProduct expects the FULL record, so callers must
       read-modify-write.
     - Calculated fields (line totals, subtotal, tax) are not computed for you:
       the engine fills them before createPurchaseOrder. */

import { DEMO_PRODUCTS, DEMO_STOCK, DEMO_SALES_ORDERS } from './sample-data.js';

// In-memory overlay so DEMO writes (sell-price changes, created POs) persist for
// the life of the page and read back like a real backend would return them.
const demoState = {
  products: structuredClone(DEMO_PRODUCTS),
  createdPOs: [],
};

let config = { proxyBase: '', currency: 'GBP' };

export function configure(cfg) { config = { ...config, ...cfg }; }
export function mode() { return config.proxyBase ? 'live' : 'demo'; }

async function proxy(path, opts = {}) {
  const url = config.proxyBase.replace(/\/$/, '') + path;
  const res = await fetch(url, {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    ...opts,
  });
  if (!res.ok) {
    let detail = '';
    try { detail = (await res.json()).message || ''; } catch { /* ignore */ }
    throw new Error(`Unleashed proxy ${res.status} on ${path}${detail ? ': ' + detail : ''}`);
  }
  return res.json();
}

// ---- reads -----------------------------------------------------------------
export async function listProducts() {
  if (mode() === 'demo') return structuredClone(demoState.products);
  return (await proxy('/products')).items || [];
}

export async function listStockOnHand() {
  if (mode() === 'demo') return structuredClone(DEMO_STOCK);
  return (await proxy('/stock-on-hand')).items || [];
}

export async function listSalesOrders({ modifiedSince } = {}) {
  if (mode() === 'demo') return structuredClone(DEMO_SALES_ORDERS);
  const q = modifiedSince ? `?modifiedSince=${encodeURIComponent(modifiedSince)}` : '';
  return (await proxy('/sales-orders' + q)).items || [];
}

export async function getProduct(guid) {
  if (mode() === 'demo') return structuredClone(demoState.products.find((p) => p.guid === guid) || null);
  return proxy('/products/' + encodeURIComponent(guid));
}

// ---- writes ----------------------------------------------------------------
// updateProduct takes the WHOLE record (updates overwrite). Callers must have
// done getProduct first and only changed the fields they mean to change.
export async function updateProduct(product) {
  if (mode() === 'demo') {
    const i = demoState.products.findIndex((p) => p.guid === product.guid);
    if (i >= 0) demoState.products[i] = structuredClone(product);
    return structuredClone(product);
  }
  return proxy('/products/' + encodeURIComponent(product.guid), {
    method: 'PUT',
    body: JSON.stringify(product),
  });
}

// One PO per call (Unleashed cannot batch order creation). status is 'Parked'
// (v1, draft for sign-off) or 'Placed' (v2, trusted auto).
export async function createPurchaseOrder(po) {
  if (mode() === 'demo') {
    const created = { ...structuredClone(po), guid: 'po-' + (demoState.createdPOs.length + 1), createdAt: new Date().toISOString() };
    demoState.createdPOs.push(created);
    return created;
  }
  return proxy('/purchase-orders', { method: 'POST', body: JSON.stringify(po) });
}

export function demoCreatedPOs() { return structuredClone(demoState.createdPOs); }

// A cheap reachability probe for the Settings panel.
export async function ping() {
  if (mode() === 'demo') return { ok: true, mode: 'demo', message: 'Demo dataset — no proxy configured.' };
  try {
    await proxy('/ping');
    return { ok: true, mode: 'live', message: 'Proxy reachable.' };
  } catch (e) {
    return { ok: false, mode: 'live', message: e.message };
  }
}
