/* Purchasing Bot — app shell / orchestration.

   Wires the left-hand Purchasing Section nav to the engines and renders each
   view. Vanilla ES modules, no build step, to match the rest of the repo. The
   engines (pricing.js, replenish.js) hold all the logic; this file is glue + DOM.

   Build order follows the scoping doc exactly:
     Phase 1  Price Maintenance      (built, default landing engine)
     Phase 2  Replenishment drafts   (built — Parked POs in the approval queue)
     Phase 3  Promote to auto        (one flag in Settings flips Parked -> Placed)
     Phase 4  Goods In (WhatsApp/OCR) (scoped placeholder, never blocks the rest) */

import * as store from './store.js';
import * as unleashed from './unleashed.js';
import * as pricing from './pricing.js';
import * as replenish from './replenish.js';
import { DEMO_SUPPLIERS } from './sample-data.js';
import { ROUNDING } from './util.js';
import { money, fmtDate, fmtDateTime, esc, round2 } from './util.js';
import { draftSupplierEmail } from './replenish.js';
import * as suppliersMod from './suppliers.js';

// ---- boot ------------------------------------------------------------------
const settings = store.getSettings();
unleashed.configure({ proxyBase: settings.proxyBase, currency: settings.currency });
store.seedSuppliersIfEmpty(DEMO_SUPPLIERS); // give the supplier table sensible defaults

const VIEWS = [
  { id: 'dashboard', label: 'Dashboard', ic: '◧' },
  { id: 'price', label: 'Price Maintenance', ic: '£', phase: 'P1', badge: () => store.getPriceQueue().length },
  { id: 'replenish', label: 'Replenishment', ic: '⤵', phase: 'P2', badge: () => store.getPoQueue().length },
  { id: 'goodsin', label: 'Goods In', ic: '⊞', phase: 'P4' },
  { id: 'suppliers', label: 'Suppliers', ic: '⚑' },
  { id: 'ledger', label: 'Price Ledger', ic: '▤' },
  { id: 'settings', label: 'Settings', ic: '⚙' },
];

let current = 'dashboard';

const $view = document.getElementById('view');
const $nav = document.getElementById('nav');
const $toast = document.getElementById('toast');

function renderNav() {
  $nav.innerHTML = VIEWS.map((v) => {
    const n = v.badge ? v.badge() : 0;
    const badge = n ? `<span class="badge">${n}</span>` : (v.phase ? `<span class="phase">${v.phase}</span>` : '');
    return `<button data-view="${v.id}" class="${current === v.id ? 'active' : ''}">
      <span class="ic">${v.ic}</span>${esc(v.label)}${badge}</button>`;
  }).join('');
  $nav.querySelectorAll('button').forEach((b) => b.addEventListener('click', () => go(b.dataset.view)));
}

function go(id) { current = id; renderNav(); render(); window.scrollTo(0, 0); }

function refreshModePill() {
  const pill = document.getElementById('mode-pill');
  const m = unleashed.mode();
  pill.textContent = m === 'live' ? 'Live · Unleashed' : 'Demo data';
  pill.className = 'modepill ' + m;
}

let toastT;
function toast(msg, isErr = false) {
  $toast.textContent = msg;
  $toast.className = 'toast show' + (isErr ? ' err' : '');
  clearTimeout(toastT);
  toastT = setTimeout(() => { $toast.className = 'toast'; }, 3200);
}

// Run an async action with a busy button + error toast.
async function act(btn, fn) {
  const old = btn ? btn.textContent : '';
  if (btn) { btn.disabled = true; btn.textContent = 'Working…'; }
  try { await fn(); }
  catch (e) { toast(e.message || String(e), true); }
  finally { if (btn) { btn.disabled = false; btn.textContent = old; } }
}

// ---- views -----------------------------------------------------------------
function render() {
  refreshModePill();
  ({
    dashboard: viewDashboard,
    price: viewPrice,
    replenish: viewReplenish,
    goodsin: viewGoodsIn,
    suppliers: viewSuppliers,
    ledger: viewLedger,
    settings: viewSettings,
  }[current] || viewDashboard)();
}

function phasebar(active) {
  const phases = [
    ['P1', 'Price maintenance'],
    ['P2', 'Replenishment drafts'],
    ['P3', 'Auto-place'],
    ['P4', 'Goods in'],
  ];
  const autoOn = store.getSettings().autoPlace;
  return `<div class="phasebar">${phases.map(([k, label]) => {
    let cls = '';
    if (k === 'P1' || k === 'P2') cls = 'done';
    if (k === 'P3') cls = autoOn ? 'done' : '';
    if (k === active) cls = 'now';
    return `<span class="p ${cls}"><b>${k}</b> · ${label}</span>`;
  }).join('')}</div>`;
}

// ---- Dashboard -------------------------------------------------------------
function viewDashboard() {
  const pq = store.getPriceQueue();
  const poq = store.getPoQueue();
  const ledger = store.getLedger();
  const activity = store.getActivity();
  const s = store.getSettings();

  $view.innerHTML = `
    <h1>Purchasing Bot</h1>
    <p class="sub">Replenishment and price maintenance for Ricobot. Drafts-first: the bot proposes,
      a human approves, then it writes back to Unleashed. Currently
      <b>${unleashed.mode() === 'live' ? 'connected to Unleashed' : 'running on demo data'}</b>.</p>
    ${phasebar(null)}
    <div class="stats">
      <div class="stat"><div class="k">Prices below ${s.priceMultiplier}×</div><div class="v ${pq.length ? 'red' : 'green'}">${pq.length}</div></div>
      <div class="stat"><div class="k">Candidate POs</div><div class="v ${poq.length ? 'gold' : ''}">${poq.length}</div></div>
      <div class="stat"><div class="k">Price changes logged</div><div class="v">${ledger.length}</div></div>
      <div class="stat"><div class="k">Auto-place (v2)</div><div class="v ${s.autoPlace ? 'green' : 'faint'}">${s.autoPlace ? 'On' : 'Off'}</div></div>
    </div>
    <div class="card">
      <div class="spread"><h2>Run the engines</h2></div>
      <div class="row">
        <button class="btn" id="run-price">Run price check</button>
        <button class="btn" id="run-replenish">Run replenishment</button>
        <span class="hint">Both poll Unleashed and stage drafts for approval. Nothing is written without sign-off.</span>
      </div>
    </div>
    <div class="card">
      <h2>Recent activity <span class="count">· ${activity.length}</span></h2>
      ${activity.length ? `<table><tbody>${activity.slice(0, 12).map((a) => `
        <tr><td style="white-space:nowrap;color:var(--faint)">${fmtDateTime(a.at)}</td>
        <td><span class="tag info">${esc(a.kind)}</span></td><td>${esc(a.message)}</td></tr>`).join('')}</tbody></table>`
      : `<div class="empty"><div class="ic">◔</div><p>No runs yet. Kick off a price check or replenishment above.</p></div>`}
    </div>`;

  document.getElementById('run-price').addEventListener('click', (e) => act(e.target, async () => {
    const r = await pricing.runPriceCheck();
    toast(`${r.flagged.length} price${r.flagged.length === 1 ? '' : 's'} below ${s.priceMultiplier}×.`);
    go('price');
  }));
  document.getElementById('run-replenish').addEventListener('click', (e) => act(e.target, async () => {
    const r = await replenish.runReplenishment();
    toast(`${r.pos.length} candidate PO${r.pos.length === 1 ? '' : 's'} across ${r.pos.length} supplier(s).`);
    go('replenish');
  }));
}

// ---- Price Maintenance -----------------------------------------------------
function viewPrice() {
  const s = store.getSettings();
  const queue = store.getPriceQueue();
  $view.innerHTML = `
    <h1>Price Maintenance</h1>
    <p class="sub">Trade price must be at least <b>${s.priceMultiplier}× the buy price</b>
      (buy = <code>${esc(s.buyPriceField)}</code>). The bot flags breaches, proposes a corrected price
      rounded by your convention, and writes it back on approval.</p>
    ${phasebar('P1')}
    <div class="card">
      <div class="row">
        <button class="btn" id="scan">Poll Unleashed &amp; flag</button>
        ${queue.length ? `<button class="ghost" id="approve-all">Approve all (${queue.length})</button>
          <button class="ghost" id="copy-note">Copy team note</button>` : ''}
        <span class="hint">Source buy field & rounding are set in Settings.</span>
      </div>
    </div>
    <div class="card">
      <h2>Approval queue <span class="count">· ${queue.length}</span></h2>
      ${queue.length ? priceTable(queue, s) : `<div class="empty"><div class="ic">✓</div>
        <p>Nothing flagged. Run a poll, or every product already meets the ${s.priceMultiplier}× rule.</p></div>`}
    </div>`;

  document.getElementById('scan').addEventListener('click', (e) => act(e.target, async () => {
    const r = await pricing.runPriceCheck();
    toast(`Scanned ${r.total} products — ${r.flagged.length} flagged.`);
    viewPrice();
  }));
  if (queue.length) {
    document.getElementById('approve-all').addEventListener('click', (e) => act(e.target, async () => {
      let n = 0;
      for (const row of store.getPriceQueue()) { await pricing.approvePriceChange(row.guid, s.approver); n++; }
      toast(`Approved & wrote ${n} price change(s).`);
      renderNav(); viewPrice();
    }));
    document.getElementById('copy-note').addEventListener('click', () => {
      copy(pricing.teamNote(store.getPriceQueue()));
      toast('Team note copied to clipboard.');
    });
    $view.querySelectorAll('[data-approve]').forEach((b) => b.addEventListener('click', (e) => act(e.target, async () => {
      await pricing.approvePriceChange(b.dataset.approve, s.approver);
      toast('Price written to Unleashed & logged.');
      renderNav(); viewPrice();
    })));
    $view.querySelectorAll('[data-reject]').forEach((b) => b.addEventListener('click', () => {
      pricing.rejectPriceChange(b.dataset.reject);
      renderNav(); viewPrice();
    }));
  }
}

function priceTable(queue, s) {
  return `<table>
    <thead><tr><th>Product</th><th class="num">Buy</th><th class="num">Trade now</th>
      <th class="num">Now ×</th><th class="num">Proposed</th><th class="num">Increase</th><th></th></tr></thead>
    <tbody>${queue.map((r) => `<tr>
      <td><b>${esc(r.productCode)}</b><br><span class="faint">${esc(r.productDescription)}</span></td>
      <td class="num">${money(r.buyPrice, r.currency)}</td>
      <td class="num red">${money(r.currentTrade, r.currency)}</td>
      <td class="num">${r.multipleNow}×</td>
      <td class="num green">${money(r.proposedTrade, r.currency)}</td>
      <td class="num">+${money(r.increase, r.currency)}</td>
      <td class="num" style="white-space:nowrap">
        <button class="ghost sm ok" data-approve="${r.guid}">Approve</button>
        <button class="ghost sm" data-reject="${r.guid}">Dismiss</button></td>
    </tr>`).join('')}</tbody></table>
    <p class="hint">Approve does a read-modify-write (updates overwrite in Unleashed) and appends to the price ledger.</p>`;
}

// ---- Replenishment ---------------------------------------------------------
function viewReplenish() {
  const s = store.getSettings();
  const queue = store.getPoQueue();
  $view.innerHTML = `
    <h1>Replenishment</h1>
    <p class="sub">Polls outstanding sales orders and stock, works out net shortfall
      (needed − on hand − on order), groups by supplier, rounds to MOQ, and checks carriage.
      ${s.autoPlace ? '<b class="green">v2: POs are created as Placed and emails sent.</b>'
        : '<b>v1: POs are created as Parked drafts for sign-off.</b>'}</p>
    ${phasebar('P2')}
    <div class="card">
      <div class="row">
        <button class="btn" id="run">Poll &amp; build candidate POs</button>
        <span class="hint">Flip to auto-place (v2) in Settings once drafts are trusted.</span>
      </div>
    </div>
    ${queue.length ? queue.map((po) => poCard(po, s)).join('')
      : `<div class="card"><div class="empty"><div class="ic">⤵</div>
        <p>No candidate POs. Run a poll — or everything short is already on order.</p></div></div>`}`;

  document.getElementById('run').addEventListener('click', (e) => act(e.target, async () => {
    const r = await replenish.runReplenishment();
    toast(`${r.shortfalls.length} short product(s) → ${r.pos.length} candidate PO(s).`);
    renderNav(); viewReplenish();
  }));

  $view.querySelectorAll('[data-place]').forEach((b) => b.addEventListener('click', (e) => act(e.target, async () => {
    const accepted = collectAcceptedTopUps(b.dataset.place);
    const r = await replenish.placePO(b.dataset.place, { applyTopUps: accepted });
    toast(r.sent ? 'PO Placed in Unleashed & email sent.' : 'PO created as Parked draft in Unleashed.');
    renderNav(); viewReplenish();
  })));
  $view.querySelectorAll('[data-dismiss]').forEach((b) => b.addEventListener('click', () => {
    replenish.dismissPO(b.dataset.dismiss); renderNav(); viewReplenish();
  }));
  $view.querySelectorAll('[data-email]').forEach((b) => b.addEventListener('click', () => {
    const po = store.getPoQueue().find((x) => x.id === b.dataset.email);
    copy(draftSupplierEmail(po, s)); toast('Supplier email draft copied.');
  }));
}

function poCard(po, s) {
  const c = po.carriage || { needed: false };
  return `<div class="po">
    <div class="po-head">
      <div><b>${esc(po.supplierName)}</b> <span class="tag ${po.status === 'Placed' ? 'placed' : 'parked'}">${po.status}</span>
        <div class="faint" style="font-size:12.5px;margin-top:3px">
          Expected ${fmtDate(po.expectedDate)} · against ${po.linkedOrders.length ? esc(po.linkedOrders.join(', ')) : 'stock'}</div></div>
      <div style="text-align:right">
        <div style="font-size:20px;font-weight:800">${money(po.total, po.currency)}</div>
        <div class="faint" style="font-size:12px">${money(po.subtotal, po.currency)} + ${money(po.tax, po.currency)} VAT</div></div>
    </div>
    <table>
      <thead><tr><th>Line</th><th class="num">Short</th><th class="num">MOQ</th><th class="num">Order</th>
        <th class="num">Unit</th><th class="num">Line total</th></tr></thead>
      <tbody>${po.lines.map((l) => `<tr>
        <td><b>${esc(l.productCode)}</b><br><span class="faint">${esc(l.productDescription)}</span></td>
        <td class="num">${l.shortfall}</td>
        <td class="num">${l.moq}${l.moqBumped ? ' <span class="tag warn">↑</span>' : ''}</td>
        <td class="num"><b>${l.quantity}</b></td>
        <td class="num">${money(l.unitCost, po.currency)}</td>
        <td class="num">${money(l.lineTotal, po.currency)}</td></tr>`).join('')}</tbody>
    </table>
    ${c.needed ? carriageBlock(po, c) : `<div class="carriage ok">✓ Clears the free-carriage threshold.</div>`}
    <div class="row" style="margin-top:12px">
      <button class="btn sm" data-place="${po.id}">${s.autoPlace ? 'Place & send' : 'Create Parked PO'}</button>
      <button class="ghost sm" data-email="${po.id}">Copy email</button>
      <button class="ghost sm danger" data-dismiss="${po.id}">Dismiss</button>
    </div>
  </div>`;
}

function carriageBlock(po, c) {
  const pct = Math.min(100, Math.round((po.subtotal / c.threshold) * 100));
  return `<div class="carriage warn">
    <div class="spread"><span>⚠ ${esc(c.note)}</span><span class="faint">${pct}% of ${money(c.threshold, po.currency)}</span></div>
    <div class="bar"><span style="width:${pct}%"></span></div>
    ${c.suggestions.length ? `<div style="margin-top:10px">
      <div class="faint" style="font-size:12px;margin-bottom:6px">Suggested top-ups${c.viaLLM ? ' (LLM)' : ''} — tick to include:</div>
      ${c.suggestions.map((sug, i) => `<label style="display:flex;gap:8px;align-items:center;margin:4px 0;font-size:13px">
        <input type="checkbox" style="width:auto" data-topup="${po.id}" data-ti="${i}"
          data-payload='${esc(JSON.stringify(sug))}'>
        +${sug.addQty} × ${esc(sug.productCode)} (${money(sug.addValue, po.currency)}) — <span class="faint">${esc(sug.reason)}</span>
      </label>`).join('')}</div>` : ''}
  </div>`;
}

function collectAcceptedTopUps(poId) {
  return Array.from($view.querySelectorAll(`[data-topup="${poId}"]:checked`))
    .map((el) => JSON.parse(el.dataset.payload));
}

// ---- Goods In (Phase 4 — scoped placeholder) -------------------------------
function viewGoodsIn() {
  $view.innerHTML = `
    <h1>Goods In</h1>
    <p class="sub">The fiddliest piece, kept deliberately separate so it never blocks the reorder engine.</p>
    ${phasebar('P4')}
    <div class="card">
      <h2>How it will work</h2>
      <ol class="muted" style="margin:0;padding-left:18px;line-height:1.9">
        <li>A delivery-note photo lands on the shared WhatsApp.</li>
        <li>Purchasing replies with the order number (or the bot OCRs the note).</li>
        <li>The bot matches it to the open PO and checks the order in against Unleashed.</li>
      </ol>
      <p class="hint">Needs either the WhatsApp Business API or a parser on the shared account, plus OCR.
        Recommended as a fast-follow once the reorder engine is proven — see open question #5 in the scope.</p>
    </div>
    <div class="card">
      <h2>Manual check-in (interim)</h2>
      <p class="muted">Until the WhatsApp path is built, match a delivery note to an on-order item by hand.</p>
      <div class="grid2">
        <label class="field"><span class="lab">Order / PO number</span><input id="gi-order" placeholder="e.g. PO-1"></label>
        <label class="field"><span class="lab">Product code</span><input id="gi-code" placeholder="e.g. LED-AUR-900"></label>
      </div>
      <label class="field"><span class="lab">Quantity received</span><input id="gi-qty" type="number" min="1" placeholder="e.g. 12" style="max-width:160px"></label>
      <button class="btn" id="gi-checkin">Check in</button>
      <p class="hint">Interim check-in just clears the quantity from on-order tracking and logs it; the WhatsApp/OCR pipeline is the real Phase 4.</p>
    </div>`;

  document.getElementById('gi-checkin').addEventListener('click', (e) => act(e.target, async () => {
    const code = document.getElementById('gi-code').value.trim();
    const qty = Number(document.getElementById('gi-qty').value);
    const order = document.getElementById('gi-order').value.trim();
    if (!code || !qty) throw new Error('Enter a product code and quantity.');
    const on = store.getOnOrder();
    const remaining = Math.max(0, (on[code] || 0) - qty);
    if (remaining) store.addOnOrder(code, -qty); else store.clearOnOrder(code);
    store.logActivity({ kind: 'goods-in', message: `Checked in ${qty} × ${code}${order ? ' against ' + order : ''}. On order now ${remaining}.` });
    toast(`Checked in ${qty} × ${code}.`);
  }));
}

// ---- Suppliers -------------------------------------------------------------
function viewSuppliers() {
  const suppliers = store.getSuppliers();
  $view.innerHTML = `
    <h1>Supplier Settings</h1>
    <p class="sub">Carriage thresholds, charges and lead times per supplier. This data drives the
      carriage judgement and the expected delivery date — and the scoping doc flags it as time-critical
      to capture from the departing manager while it's still in his head.</p>
    <div class="card">
      <div class="row" style="margin-bottom:6px">
        <button class="ghost sm" id="sup-import">Import from Unleashed products</button>
        <span class="hint">Seeds a row for every supplier in the catalogue; never overwrites captured thresholds.</span>
      </div>
    </div>
    <div class="card">
      <h2>Suppliers <span class="count">· ${suppliers.length}</span></h2>
      <table>
        <thead><tr><th>Supplier</th><th class="num">Free carriage ≥</th><th class="num">Carriage charge</th>
          <th class="num">Lead days</th><th></th></tr></thead>
        <tbody>${suppliers.map((s) => `<tr data-sup="${esc(s.supplierCode)}">
          <td><b>${esc(s.supplierName)}</b><br><span class="faint">${esc(s.supplierCode)}</span></td>
          <td class="num"><input class="inline-edit" type="number" data-f="freeCarriageThreshold" value="${s.freeCarriageThreshold}"></td>
          <td class="num"><input class="inline-edit" type="number" step="0.01" data-f="carriageCharge" value="${s.carriageCharge}"></td>
          <td class="num"><input class="inline-edit" type="number" data-f="leadDays" value="${s.leadDays}"></td>
          <td class="num">${suppliersMod.isIncomplete(s) ? '<span class="tag warn">incomplete</span> ' : ''}<button class="ghost sm" data-save="${esc(s.supplierCode)}">Save</button></td>
        </tr>`).join('')}</tbody>
      </table>
      <p class="hint">MOQ is read per product from Unleashed; override individual products in the next run if needed.
        Rows tagged <span class="tag warn">incomplete</span> still need carriage data — capture it before the manager leaves.</p>
    </div>`;

  document.getElementById('sup-import').addEventListener('click', (e) => act(e.target, async () => {
    const r = await suppliersMod.importSuppliersFromProducts();
    toast(`${r.added} new supplier(s) added from ${r.referenced} referenced.`);
    viewSuppliers();
  }));

  $view.querySelectorAll('[data-save]').forEach((b) => b.addEventListener('click', () => {
    const tr = $view.querySelector(`tr[data-sup="${b.dataset.save}"]`);
    const patch = { supplierCode: b.dataset.save };
    tr.querySelectorAll('input[data-f]').forEach((inp) => { patch[inp.dataset.f] = Number(inp.value); });
    store.upsertSupplier(patch);
    toast(`Saved ${b.dataset.save}.`);
  }));
}

// ---- Price Ledger ----------------------------------------------------------
function viewLedger() {
  const ledger = store.getLedger().slice().reverse();
  $view.innerHTML = `
    <h1>Price Ledger</h1>
    <p class="sub">Append-only audit trail of every approved price change — what changed, when,
      and who approved it. Mirrors the price-ledger pattern used elsewhere in Ricobot.</p>
    <div class="card">
      <div class="spread"><h2>Changes <span class="count">· ${ledger.length}</span></h2>
        ${ledger.length ? `<button class="ghost sm" id="ledger-note">Copy team note</button>` : ''}</div>
      ${ledger.length ? `<table>
        <thead><tr><th>When</th><th>Product</th><th class="num">Old</th><th class="num">New</th>
          <th>Buy field</th><th>Approved by</th></tr></thead>
        <tbody>${ledger.map((e) => `<tr>
          <td style="white-space:nowrap;color:var(--faint)">${fmtDateTime(e.at)}</td>
          <td><b>${esc(e.productCode)}</b><br><span class="faint">${esc(e.productDescription)}</span></td>
          <td class="num">${money(e.oldTrade)}</td>
          <td class="num green">${money(e.newTrade)}</td>
          <td><span class="faint">${esc(e.buyField)}</span> @ ${money(e.buyPrice)}</td>
          <td>${esc(e.approvedBy)}</td></tr>`).join('')}</tbody></table>`
        : `<div class="empty"><div class="ic">▤</div><p>No price changes logged yet.</p></div>`}
    </div>`;
  if (ledger.length) document.getElementById('ledger-note').addEventListener('click', () => {
    copy(pricing.teamNote(store.getLedger().slice().reverse(), { fromLedger: true }));
    toast('Team note copied.');
  });
}

// ---- Settings --------------------------------------------------------------
function viewSettings() {
  const s = store.getSettings();
  const roundOpts = Object.entries(ROUNDING).map(([k, v]) => `<option value="${k}" ${s.roundingMode === k ? 'selected' : ''}>${esc(v.label)}</option>`).join('');
  const buyOpts = ['defaultPurchasePrice', 'lastCost', 'averageLandedCost']
    .map((f) => `<option value="${f}" ${s.buyPriceField === f ? 'selected' : ''}>${f}</option>`).join('');
  $view.innerHTML = `
    <h1>Settings</h1>
    <p class="sub">The open questions from the scoping doc live here as explicit choices, not buried constants.</p>
    <div class="card">
      <h2>Unleashed connection</h2>
      <label class="field"><span class="lab">Proxy base URL (server-side, holds the HMAC API key)</span>
        <input id="set-proxy" value="${esc(s.proxyBase)}" placeholder="blank = demo data; e.g. https://nas.local/api/unleashed"></label>
      <div class="row"><button class="ghost sm" id="ping">Test connection</button><span id="ping-out" class="hint"></span></div>
      <p class="hint">The browser never holds the Unleashed API key. Live mode calls a small server-side proxy
        (on the Synology service) that does the HMAC-SHA256 signing — that's why this is a service, not a script.</p>
    </div>
    <div class="card">
      <h2>Price rule</h2>
      <div class="grid2">
        <label class="field"><span class="lab">Buy-price source (open Q2)</span><select id="set-buy">${buyOpts}</select></label>
        <label class="field"><span class="lab">Multiplier</span><input id="set-mult" type="number" step="0.1" value="${s.priceMultiplier}"></label>
        <label class="field"><span class="lab">Rounding convention (open Q3)</span><select id="set-round">${roundOpts}</select></label>
        <label class="field"><span class="lab">Default tax rate</span><input id="set-tax" type="number" step="0.01" value="${s.defaultTaxRate}"></label>
      </div>
    </div>
    <div class="card">
      <h2>Replenishment phasing</h2>
      <label class="field" style="display:flex;align-items:center;gap:10px">
        <input id="set-auto" type="checkbox" style="width:auto" ${s.autoPlace ? 'checked' : ''}>
        <span><b>Auto-place (v2)</b> — create POs as <code>Placed</code> and send supplier emails automatically.
          Off = v1, create <code>Parked</code> drafts in the approval queue.</span></label>
      <label class="field"><span class="lab">Approver (named on ledger entries — open Q4)</span>
        <input id="set-approver" value="${esc(s.approver)}" placeholder="e.g. ${esc(userHint())}"></label>
    </div>
    <div class="card">
      <h2>Data</h2>
      <div class="row">
        <button class="btn" id="set-save">Save settings</button>
        <button class="ghost" id="set-export">Export state (JSON)</button>
        <button class="ghost danger" id="set-reset">Reset all data</button>
      </div>
    </div>`;

  document.getElementById('ping').addEventListener('click', (e) => act(e.target, async () => {
    unleashed.configure({ proxyBase: document.getElementById('set-proxy').value.trim() });
    const r = await unleashed.ping();
    document.getElementById('ping-out').textContent = (r.ok ? '✓ ' : '✕ ') + r.message;
    refreshModePill();
  }));
  document.getElementById('set-save').addEventListener('click', () => {
    store.saveSettings({
      proxyBase: document.getElementById('set-proxy').value.trim(),
      buyPriceField: document.getElementById('set-buy').value,
      priceMultiplier: Number(document.getElementById('set-mult').value) || 3.5,
      roundingMode: document.getElementById('set-round').value,
      defaultTaxRate: Number(document.getElementById('set-tax').value) || 0,
      autoPlace: document.getElementById('set-auto').checked,
      approver: document.getElementById('set-approver').value.trim(),
    });
    unleashed.configure({ proxyBase: store.getSettings().proxyBase });
    refreshModePill(); renderNav();
    toast('Settings saved.');
  });
  document.getElementById('set-export').addEventListener('click', () => { copy(store.exportJson()); toast('State JSON copied to clipboard.'); });
  document.getElementById('set-reset').addEventListener('click', () => {
    if (confirm('Reset all Purchasing Bot data (queues, ledger, supplier settings)? This cannot be undone.')) {
      store.resetAll(); store.seedSuppliersIfEmpty(DEMO_SUPPLIERS);
      toast('All data reset.'); renderNav(); go('dashboard');
    }
  });
}

function userHint() {
  // Cosmetic placeholder only.
  return 'purchasing@ricoman.com';
}

// ---- clipboard -------------------------------------------------------------
function copy(text) {
  if (navigator.clipboard) navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
  else fallbackCopy(text);
}
function fallbackCopy(text) {
  const ta = document.createElement('textarea');
  ta.value = text; document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); } catch { /* ignore */ }
  document.body.removeChild(ta);
}

// ---- service worker --------------------------------------------------------
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('./sw.js').catch(() => {}));
}

// ---- start -----------------------------------------------------------------
renderNav();
render();
