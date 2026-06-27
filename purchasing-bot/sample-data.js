/* Purchasing Bot — demo Unleashed dataset.

   The Unleashed API is HMAC-signed and pull-only, and the secret API key must
   never sit in a browser. So the live adapter (unleashed.js) talks to a
   server-side proxy. With no proxy configured the app runs against this sample
   dataset instead — same shape the proxy returns — so the engines, queues and
   ledger are fully demonstrable offline, exactly like Bim Builder ships with a
   sample LDT. Field names mirror the Unleashed REST resources.

   Numbers are deliberately chosen to exercise every branch: a price below 3.5x,
   one exactly on the line, one comfortably above; shortfalls that need MOQ
   rounding; and a supplier order that lands just under its free-carriage
   threshold so the top-up logic has something to chew on. */

const SUPPLIER_NAMES = {
  'SUP-LUMEX': 'Lumex Optoelectronics Ltd',
  'SUP-MERIDIAN': 'Meridian Components',
  'SUP-EXTRUDE': 'Extrude & Co',
};

// Three buy-price fields are carried per product because the scoping doc flags
// (open question #2) that they differ and the choice matters: defaultPurchasePrice
// (standard cost), lastCost (most recent landed), averageLandedCost.
export const DEMO_PRODUCTS = [
  // code, desc, supplier, MOQ, the three buy prices, current trade/sell price
  p('LED-AUR-600', 'Aurora 600 LED downlight', 'SUP-LUMEX', 12, 8.40, 9.10, 8.75, 29.95),
  p('LED-AUR-900', 'Aurora 900 LED downlight', 'SUP-LUMEX', 12, 12.10, 13.80, 12.90, 41.50), // 13.80*3.5=48.30 > 41.50 -> flag
  p('LED-EST-1200', 'Estrella 1200 linear', 'SUP-LUMEX', 6, 22.00, 22.00, 22.30, 77.00),     // exactly 3.5x on default -> ok
  p('DRV-CC-700', 'Constant-current driver 700mA', 'SUP-MERIDIAN', 25, 3.20, 3.95, 3.60, 11.20), // 3.95*3.5=13.825 -> flag
  p('DRV-CC-1050', 'Constant-current driver 1050mA', 'SUP-MERIDIAN', 25, 3.80, 4.10, 3.95, 12.60),
  p('CONN-IP67-3', '3-pole IP67 connector', 'SUP-MERIDIAN', 100, 0.62, 0.70, 0.66, 1.95),
  p('PROF-ALU-2M', 'Aluminium profile 2m', 'SUP-EXTRUDE', 10, 6.50, 7.40, 6.90, 19.50),        // 7.40*3.5=25.90 -> flag
  p('PROF-LENS-2M', 'Opal lens 2m', 'SUP-EXTRUDE', 10, 2.10, 2.10, 2.05, 8.95),
  p('ENC-WALL-S', 'Wall enclosure small', 'SUP-EXTRUDE', 5, 14.00, 15.50, 14.70, 52.00),
  p('FIX-BRKT-STD', 'Standard fixing bracket', 'SUP-MERIDIAN', 50, 0.95, 1.05, 1.00, 3.60),
];

function p(code, desc, supplierCode, moq, defaultPurchasePrice, lastCost, averageLandedCost, sell) {
  return {
    guid: 'prod-' + code.toLowerCase(),
    productCode: code,
    productDescription: desc,
    supplierCode,
    supplierName: SUPPLIER_NAMES[supplierCode] || supplierCode,
    minimumOrderQuantity: moq,
    defaultPurchasePrice,
    lastCost,
    averageLandedCost,
    sellPriceTier1: sell, // "trade" price for the 3.5x rule
    taxRate: 0.20,
    currency: 'GBP',
  };
}

// Stock on hand — qtyOnHand is what we physically hold; qtyAllocated is reserved
// against open sales orders. The replenish engine works from net shortfall.
export const DEMO_STOCK = [
  s('LED-AUR-600', 40, 30),
  s('LED-AUR-900', 6, 18),     // short: 18 allocated, only 6 on hand
  s('LED-EST-1200', 2, 9),     // short
  s('DRV-CC-700', 120, 60),
  s('DRV-CC-1050', 10, 40),    // short
  s('CONN-IP67-3', 300, 120),
  s('PROF-ALU-2M', 4, 16),     // short
  s('PROF-LENS-2M', 30, 8),
  s('ENC-WALL-S', 1, 5),       // short
  s('FIX-BRKT-STD', 200, 60),
];

function s(productCode, qtyOnHand, qtyAllocated) {
  return { productCode, qtyOnHand, qtyAllocated, qtyAvailable: qtyOnHand - qtyAllocated };
}

// Outstanding sales orders — only lines not yet fully shipped drive demand.
export const DEMO_SALES_ORDERS = [
  so('SO-10421', 'Brightway Contractors', '2026-07-10', [
    line('LED-AUR-900', 12, 0),
    line('LED-EST-1200', 6, 0),
    line('DRV-CC-1050', 24, 0),
  ]),
  so('SO-10422', 'Northgate Electrical', '2026-07-04', [
    line('PROF-ALU-2M', 12, 0),
    line('ENC-WALL-S', 4, 0),
    line('LED-AUR-900', 6, 0),
  ]),
  so('SO-10423', 'Halo Lighting Design', '2026-07-18', [
    line('LED-EST-1200', 3, 0),
    line('DRV-CC-1050', 16, 6),
  ]),
];

function so(orderNumber, customer, requiredDate, lines) {
  return { orderNumber, customer, orderStatus: 'Backordered', requiredDate, lines };
}
function line(productCode, orderQuantity, qtyShipped) {
  return { productCode, orderQuantity, qtyShipped };
}

// Supplier settings — carriage thresholds, lead times. The scoping doc calls out
// that this data may only live in the departing manager's head, so the module
// owns it as an editable table (see suppliers.js). These are seed defaults.
export const DEMO_SUPPLIERS = [
  { supplierCode: 'SUP-LUMEX', supplierName: 'Lumex Optoelectronics Ltd', freeCarriageThreshold: 500, carriageCharge: 18.5, leadDays: 10, currency: 'GBP' },
  { supplierCode: 'SUP-MERIDIAN', supplierName: 'Meridian Components', freeCarriageThreshold: 120, carriageCharge: 9.95, leadDays: 5, currency: 'GBP' },
  { supplierCode: 'SUP-EXTRUDE', supplierName: 'Extrude & Co', freeCarriageThreshold: 400, carriageCharge: 25, leadDays: 14, currency: 'GBP' },
];
