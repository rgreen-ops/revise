/* Purchasing Bot — supplier settings domain helpers.

   The carriage logic is only as good as the data behind it: per supplier we need
   the free-carriage threshold (or charge bands), MOQ, and lead times. The scoping
   doc flags that a lot of this lives in the departing manager's head, so the
   module owns an editable supplier settings table (persisted in store.js). This
   file is the domain logic around that table: deriving the supplier list from the
   products feed, and merging in saved overrides without clobbering them. */

import { listProducts } from './unleashed.js';
import * as store from './store.js';

// Derive the distinct suppliers referenced by the product catalogue. Useful when
// connecting a live Unleashed account for the first time: it seeds rows for every
// supplier so they can have carriage/lead-time data filled in, WITHOUT overwriting
// any thresholds already captured.
export async function importSuppliersFromProducts() {
  const products = await listProducts();
  const seen = new Map();
  for (const p of products) {
    if (!p.supplierCode || seen.has(p.supplierCode)) continue;
    seen.set(p.supplierCode, { supplierCode: p.supplierCode, supplierName: p.supplierName || p.supplierCode });
  }

  const existing = new Map(store.getSuppliers().map((s) => [s.supplierCode, s]));
  let added = 0;
  for (const [code, base] of seen) {
    if (existing.has(code)) continue; // keep captured thresholds untouched
    store.upsertSupplier({ ...base, freeCarriageThreshold: 0, carriageCharge: 0, leadDays: 7, currency: 'GBP' });
    added += 1;
  }
  store.logActivity({ kind: 'suppliers', message: `Imported ${added} new supplier(s) from the product feed (${seen.size} referenced).` });
  return { referenced: seen.size, added };
}

// A supplier row is "incomplete" if it can't drive the carriage judgement yet —
// surfaced so whoever inherits the desk knows what's still missing from the
// manager's head.
export function isIncomplete(s) {
  return !s.freeCarriageThreshold && !s.carriageCharge;
}
