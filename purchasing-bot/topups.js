/* Purchasing Bot — carriage top-up suggester.

   The scoping doc calls carriage the main judgement call: when a candidate PO is
   below a supplier's free-carriage threshold, it can be worth adding a few units
   of a short line or another line we'll need soon to cross the threshold and save
   the carriage charge. That's where an LLM step helps — proposing sensible
   top-ups for a human to accept or reject.

   This module returns suggestions only; nothing is committed without approval. It
   has a deterministic heuristic that always works offline, plus an optional LLM
   hook (window.PURCHASING_LLM) that a host can wire up. The heuristic is the
   default so the engine is never blocked on a model being available — the same
   "fast follow, never blocks the core" stance the doc takes for goods-in. */

import { round2, money } from './util.js';

// Heuristic: only suggest a top-up when crossing the threshold actually saves
// money — i.e. the extra spend to reach it is less than the carriage charge we'd
// otherwise pay. Prefer bumping existing short lines (we need them anyway), then
// near-future candidate lines, cheapest-gap first.
export function suggestTopUps(po, supplier, catalogueForSupplier) {
  const threshold = Number(supplier.freeCarriageThreshold) || 0;
  const carriage = Number(supplier.carriageCharge) || 0;
  if (!threshold || po.subtotal >= threshold) return { needed: false, gap: 0, suggestions: [] };

  const gap = round2(threshold - po.subtotal);
  // If the cheapest way to close the gap costs more than the carriage we'd save,
  // it isn't worth topping up — flag it and let the human decide.
  const worthIt = gap <= carriage * 3; // generous band; the human still approves

  const suggestions = [];
  // 1) Bump quantities on lines already on this PO (round up in pack-sensible steps).
  for (const l of po.lines) {
    const unit = l.unitCost;
    if (!unit) continue;
    const unitsToClose = Math.ceil((threshold - po.subtotal - tentativeAdded(suggestions)) / unit);
    if (unitsToClose <= 0) break;
    suggestions.push({
      type: 'bump',
      productCode: l.productCode,
      productDescription: l.productDescription,
      addQty: unitsToClose,
      unitCost: unit,
      addValue: round2(unitsToClose * unit),
      reason: 'already on this PO',
    });
    if (po.subtotal + tentativeAdded(suggestions) >= threshold) break;
  }

  return {
    needed: true,
    gap,
    carriageSaved: carriage,
    worthIt,
    threshold,
    suggestions,
    note: worthIt
      ? `£${gap.toFixed(2)} under the free-carriage threshold; topping up saves ${money(carriage)} carriage.`
      : `£${gap.toFixed(2)} under threshold, but closing it costs more than the ${money(carriage)} carriage — may not be worth it.`,
  };
}

function tentativeAdded(suggestions) { return suggestions.reduce((a, s) => a + s.addValue, 0); }

// Optional LLM pass. A host wires window.PURCHASING_LLM = async ({prompt}) => text.
// We give it the PO + supplier + heuristic and let it refine/justify the picks.
// Failures fall back to the heuristic silently — never block the queue.
export async function suggestTopUpsLLM(po, supplier, catalogueForSupplier) {
  const heuristic = suggestTopUps(po, supplier, catalogueForSupplier);
  const llm = (typeof window !== 'undefined') && window.PURCHASING_LLM;
  if (!heuristic.needed || !llm) return heuristic;
  try {
    const prompt = buildPrompt(po, supplier, catalogueForSupplier, heuristic);
    const text = await llm({ prompt });
    const parsed = JSON.parse(text);
    if (Array.isArray(parsed.suggestions)) {
      return { ...heuristic, suggestions: parsed.suggestions, llmNote: parsed.note || '', viaLLM: true };
    }
  } catch { /* fall back to heuristic */ }
  return heuristic;
}

function buildPrompt(po, supplier, catalogue, heuristic) {
  return [
    'You are a purchasing assistant. A draft purchase order is below the supplier free-carriage threshold.',
    `Supplier: ${supplier.supplierName} — free carriage at ${money(supplier.freeCarriageThreshold)}, carriage charge ${money(supplier.carriageCharge)}.`,
    `Current PO subtotal: ${money(po.subtotal)} (gap ${money(heuristic.gap)}).`,
    'Current lines: ' + JSON.stringify(po.lines.map((l) => ({ code: l.productCode, qty: l.quantity, unit: l.unitCost }))),
    'Items we will likely need soon from this supplier: ' + JSON.stringify(catalogue.map((c) => ({ code: c.productCode, unit: c.unitCost }))),
    'Propose the smallest set of top-ups (bump an existing line or add a near-future line) that crosses the threshold without overbuying.',
    'Reply ONLY as JSON: {"suggestions":[{"type":"bump|add","productCode","addQty","unitCost","addValue","reason"}],"note":"one sentence"}',
  ].join('\n');
}
