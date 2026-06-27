/* Purchasing Bot — replenishment engine (Part 1 of the scoping doc).

   Drafts-first. v1 builds candidate POs and parks them in an approval queue; a
   human reviews, adjusts and places them. v2 flips ONE flag (settings.autoPlace)
   so the engine creates Placed POs and sends the email itself. The logic below is
   identical for both — only the final status and the send step change.

   Engine steps (mirroring the doc):
     1. Poll outstanding sales orders + current stock. Net shortfall per product =
        needed − on-hand − on-order.
     2. Map each short product to its default supplier.
     3. Group shortfalls by supplier into a candidate PO.
     4. Apply MOQs: round each line up to the minimum order quantity.
     5. Carriage: if below the free-carriage threshold, flag + suggest top-ups.
     6. Compute line totals, subtotal and tax (Unleashed won't do this for us).
     7. Set expected delivery date; record the sales orders to attach to.
     8. v2 drafts+sends the supplier email; v1 drafts it and leaves it queued. */

import { listProducts, listStockOnHand, listSalesOrders, createPurchaseOrder } from './unleashed.js';
import * as store from './store.js';
import { suggestTopUps } from './topups.js';
import { round2, money, isoDate, addDays, uid, groupBy, sum } from './util.js';

// Net shortfall per product across all outstanding sales orders.
export function computeShortfalls(salesOrders, stock, onOrder) {
  const stockByCode = new Map(stock.map((s) => [s.productCode, s]));
  const demand = new Map();      // code -> needed qty
  const ordersFor = new Map();   // code -> Set(orderNumbers) that need it

  for (const so of salesOrders) {
    for (const l of so.lines || []) {
      const outstanding = Math.max(0, Number(l.orderQuantity) - Number(l.qtyShipped || 0));
      if (outstanding <= 0) continue;
      demand.set(l.productCode, (demand.get(l.productCode) || 0) + outstanding);
      if (!ordersFor.has(l.productCode)) ordersFor.set(l.productCode, new Set());
      ordersFor.get(l.productCode).add(so.orderNumber);
    }
  }

  const shortfalls = [];
  for (const [code, needed] of demand) {
    const onHand = Number(stockByCode.get(code)?.qtyOnHand || 0);
    const already = Number(onOrder[code] || 0);
    const shortfall = needed - onHand - already;
    if (shortfall > 0) {
      shortfalls.push({ productCode: code, needed, onHand, onOrder: already, shortfall, orders: [...ordersFor.get(code)] });
    }
  }
  return shortfalls;
}

// Build candidate POs grouped by supplier, with MOQ rounding, totals, tax,
// expected date and carriage assessment. Pure (no writes) so it can preview.
export function buildCandidatePOs(shortfalls, products, settings) {
  const byCode = new Map(products.map((p) => [p.productCode, p]));
  const moqOverrides = store.getProductMoq();

  // Attach supplier + costs to each shortfall, dropping any product we can't map.
  const enriched = shortfalls
    .map((sf) => {
      const p = byCode.get(sf.productCode);
      if (!p) return null;
      const moq = Number(moqOverrides[sf.productCode] ?? p.minimumOrderQuantity ?? 1) || 1;
      const orderQty = Math.max(sf.shortfall, moq); // round up to MOQ
      const unitCost = Number(p[settings.buyPriceField] ?? p.lastCost ?? p.defaultPurchasePrice ?? 0);
      return {
        ...sf,
        supplierCode: p.supplierCode,
        supplierName: p.supplierName,
        productDescription: p.productDescription,
        moq,
        quantity: orderQty,
        moqBumped: orderQty > sf.shortfall,
        unitCost,
        lineTotal: round2(orderQty * unitCost),
        taxRate: Number(p.taxRate ?? settings.defaultTaxRate),
      };
    })
    .filter(Boolean);

  const pos = [];
  for (const [supplierCode, lines] of groupBy(enriched, (x) => x.supplierCode)) {
    const supplier = store.getSupplier(supplierCode) || { supplierCode, supplierName: lines[0].supplierName, freeCarriageThreshold: 0, carriageCharge: 0, leadDays: 7 };
    const subtotal = round2(sum(lines, (l) => l.lineTotal));
    const tax = round2(sum(lines, (l) => l.lineTotal * l.taxRate));
    const linkedOrders = [...new Set(lines.flatMap((l) => l.orders))];

    const po = {
      id: uid('po'),
      supplierCode,
      supplierName: supplier.supplierName,
      currency: settings.currency,
      lines: lines.map((l) => ({
        productCode: l.productCode,
        productDescription: l.productDescription,
        quantity: l.quantity,
        moq: l.moq,
        moqBumped: l.moqBumped,
        shortfall: l.shortfall,
        unitCost: l.unitCost,
        lineTotal: l.lineTotal,
        taxRate: l.taxRate,
      })),
      subtotal,
      tax,
      total: round2(subtotal + tax),
      expectedDate: isoDate(addDays(new Date(), Number(supplier.leadDays || 7))),
      linkedOrders,
      status: settings.autoPlace ? 'Placed' : 'Parked',
    };

    // Carriage assessment + top-up suggestions (judgement call, human approves).
    const sameSupplierCatalogue = lines.map((l) => ({ productCode: l.productCode, unitCost: l.unitCost }));
    po.carriage = suggestTopUps(po, supplier, sameSupplierCatalogue);
    pos.push(po);
  }
  return pos;
}

// Full run: poll, compute, build, and stage candidate POs into the queue.
export async function runReplenishment() {
  const settings = store.getSettings();
  const [products, stock, salesOrders] = await Promise.all([
    listProducts(), listStockOnHand(), listSalesOrders(),
  ]);
  const shortfalls = computeShortfalls(salesOrders, stock, store.getOnOrder());
  const pos = buildCandidatePOs(shortfalls, products, settings);
  store.setPoQueue(pos);
  store.logActivity({ kind: 'replenish', message: `Polled ${salesOrders.length} sales orders — ${shortfalls.length} short products across ${pos.length} suppliers.` });
  return { shortfalls, pos };
}

// Draft the supplier email. In v1 this is queued alongside the PO; in v2 it would
// be sent. We only ever return the text — sending is a host concern.
export function draftSupplierEmail(po, settings) {
  const lines = po.lines.map((l) => `  ${l.quantity} × ${l.productCode} — ${l.productDescription}`).join('\n');
  return [
    `To: ${po.supplierName}`,
    `Subject: Purchase order — Ricoman (${po.linkedOrders.join(', ') || 'stock'})`,
    '',
    `Hi,`,
    '',
    `Please supply the following:`,
    '',
    lines,
    '',
    `Expected delivery: ${po.expectedDate}.`,
    `Order value: ${money(po.subtotal, po.currency)} ex VAT.`,
    '',
    `Thanks,`,
    `Ricoman Purchasing`,
  ].join('\n');
}

// Approve / place a queued PO. Creates it in Unleashed (Parked in v1, Placed in
// v2 — Unleashed cannot batch, so this is one PO per call), records the on-order
// quantities so the next poll won't re-order them, and drops it from the queue.
export async function placePO(id, { applyTopUps = [] } = {}) {
  const settings = store.getSettings();
  const po = store.getPoQueue().find((x) => x.id === id);
  if (!po) throw new Error('PO not in queue.');

  // Fold in any accepted top-ups before computing final totals.
  for (const t of applyTopUps) {
    const line = po.lines.find((l) => l.productCode === t.productCode);
    if (line && t.type === 'bump') {
      line.quantity += Number(t.addQty);
      line.lineTotal = round2(line.quantity * line.unitCost);
    } else if (t.type === 'add') {
      po.lines.push({ productCode: t.productCode, productDescription: t.productDescription || t.productCode, quantity: Number(t.addQty), unitCost: t.unitCost, lineTotal: round2(t.addQty * t.unitCost), taxRate: settings.defaultTaxRate });
    }
  }
  po.subtotal = round2(sum(po.lines, (l) => l.lineTotal));
  po.tax = round2(sum(po.lines, (l) => l.lineTotal * (l.taxRate ?? settings.defaultTaxRate)));
  po.total = round2(po.subtotal + po.tax);

  // Build the Unleashed PO payload with all calculated fields filled in.
  const payload = {
    supplierCode: po.supplierCode,
    status: settings.autoPlace ? 'Placed' : 'Parked',
    requiredDate: po.expectedDate,
    comments: po.linkedOrders.length ? `Against ${po.linkedOrders.join(', ')}` : 'Stock replenishment',
    subTotal: po.subtotal,
    taxTotal: po.tax,
    total: po.total,
    lines: po.lines.map((l, i) => ({
      lineNumber: i + 1,
      productCode: l.productCode,
      orderQuantity: l.quantity,
      unitPrice: l.unitCost,
      lineTotal: l.lineTotal,
      taxRate: l.taxRate ?? settings.defaultTaxRate,
    })),
  };

  const created = await createPurchaseOrder(payload);

  for (const l of po.lines) store.addOnOrder(l.productCode, l.quantity);
  store.removeFromPoQueue(id);
  const verb = settings.autoPlace ? 'Placed' : 'Parked';
  store.logActivity({ kind: 'po', message: `${verb} PO to ${po.supplierName} — ${po.lines.length} lines, ${money(po.total, po.currency)} inc VAT. Attached to ${po.linkedOrders.join(', ') || 'stock'}.` });
  return { created, po, emailDraft: draftSupplierEmail(po, settings), sent: settings.autoPlace };
}

export function dismissPO(id) {
  store.removeFromPoQueue(id);
  store.logActivity({ kind: 'po-reject', message: `Dismissed candidate PO ${id}.` });
}
