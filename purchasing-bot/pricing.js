/* Purchasing Bot — price maintenance engine (Part 2 of the scoping doc).

   The rule: trade (sell) price on Unleashed must be at least 3.5x the buy price.
   This is the smallest, lowest-risk deliverable and is built first because it
   proves the Unleashed read AND write path before the heavier reorder engine
   sits on top.

   Steps (mirroring the doc):
     1. Poll products; read buy price + current trade price.
     2. Flag any product where trade < multiplier x buy.
     3. Propose corrected trade price (buy x multiplier, rounded per convention).
     4. Present flagged list for human approval (drafts-first).
     5. On approval, read-modify-write the sell price back to Unleashed.
     6. Generate a team note of what changed.
     7. Append to the price ledger (audit trail). */

import { listProducts, getProduct, updateProduct } from './unleashed.js';
import * as store from './store.js';
import { round2, roundPrice, money, isoDate, fmtDate } from './util.js';

// Which field is the source of truth for "buy price" is open question #2 — the
// fields genuinely differ, so it's a setting, not a constant.
export function buyPriceOf(product, field) {
  const v = product[field];
  return (v == null || Number.isNaN(Number(v))) ? null : Number(v);
}

// Pure scan: given products + settings, return the rows that breach the rule and
// the proposed corrected price. No side effects, so it's easy to test and to
// re-run on a timer.
export function scanPrices(products, settings) {
  const { buyPriceField, priceMultiplier, roundingMode } = settings;
  const flagged = [];
  let okCount = 0;
  let missingBuy = 0;

  for (const p of products) {
    const buy = buyPriceOf(p, buyPriceField);
    const trade = Number(p.sellPriceTier1);
    if (buy == null || !buy) { missingBuy += 1; continue; }
    const floor = round2(buy * priceMultiplier);
    if (trade + 1e-6 >= floor) { okCount += 1; continue; }

    const proposed = roundPrice(buy * priceMultiplier, roundingMode);
    flagged.push({
      guid: p.guid,
      productCode: p.productCode,
      productDescription: p.productDescription,
      currency: p.currency || settings.currency,
      buyField: buyPriceField,
      buyPrice: buy,
      currentTrade: trade,
      ruleFloor: floor,
      proposedTrade: proposed,
      multipleNow: trade && buy ? round2(trade / buy) : 0,
      increase: round2(proposed - trade),
    });
  }
  return { flagged, okCount, missingBuy, total: products.length };
}

// Poll + scan, then stage the flagged rows into the approval queue (drafts-first).
export async function runPriceCheck() {
  const settings = store.getSettings();
  const products = await listProducts();
  const result = scanPrices(products, settings);
  store.setPriceQueue(result.flagged);
  store.logActivity({ kind: 'price-check', message: `Scanned ${result.total} products — ${result.flagged.length} below ${settings.priceMultiplier}x, ${result.okCount} ok, ${result.missingBuy} missing buy price.` });
  return result;
}

// Approve ONE queued change: read the full product, change only the sell price,
// write it back (updates overwrite), append to the ledger, drop from the queue.
export async function approvePriceChange(guid, approver) {
  const queued = store.getPriceQueue().find((x) => x.guid === guid);
  if (!queued) throw new Error('Price change not in queue.');

  const full = await getProduct(guid);
  if (!full) throw new Error('Product not found in Unleashed.');

  const oldPrice = Number(full.sellPriceTier1);
  full.sellPriceTier1 = queued.proposedTrade; // change only this field
  await updateProduct(full);

  const entry = {
    at: new Date().toISOString(),
    effectiveDate: isoDate(),
    productCode: queued.productCode,
    productDescription: queued.productDescription,
    buyField: queued.buyField,
    buyPrice: queued.buyPrice,
    oldTrade: oldPrice,
    newTrade: queued.proposedTrade,
    multiplier: store.getSettings().priceMultiplier,
    approvedBy: approver || store.getSettings().approver || 'unknown',
  };
  store.appendLedger(entry);
  store.removeFromPriceQueue(guid);
  store.logActivity({ kind: 'price-write', message: `${queued.productCode}: trade ${money(oldPrice)} → ${money(queued.proposedTrade)} (by ${entry.approvedBy}).` });
  return entry;
}

export function rejectPriceChange(guid) {
  store.removeFromPriceQueue(guid);
  store.logActivity({ kind: 'price-reject', message: `Dismissed price change for ${guid}.` });
}

// The team note: what to share when prices go up. Built from queued items (a
// preview before approval) or from ledger entries (what actually changed).
export function teamNote(rows, { fromLedger = false } = {}) {
  if (!rows.length) return 'No price changes to report.';
  const eff = fromLedger ? (rows[0].effectiveDate || isoDate()) : isoDate();
  const lines = rows.map((r) => {
    const oldP = fromLedger ? r.oldTrade : r.currentTrade;
    const newP = fromLedger ? r.newTrade : r.proposedTrade;
    const pct = oldP ? Math.round(((newP - oldP) / oldP) * 100) : 0;
    return `• ${r.productCode} — ${r.productDescription}: ${money(oldP)} → ${money(newP)} (+${pct}%)`;
  });
  return [
    `Price update — effective ${fmtDate(eff)}`,
    `The following trade prices have increased to keep the 3.5× margin over buy cost:`,
    '',
    ...lines,
    '',
    `Please use the new prices on quotes from the effective date.`,
  ].join('\n');
}
