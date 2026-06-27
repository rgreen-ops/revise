# Purchasing Bot — Ricobot replenishment & pricing module

A bolt-on **Purchasing** module for Ricobot. It italicises everything Ricobot
already does — the Unleashed connection, the Synology deployment, the drafts-first
approval style — but sits on its own, alongside the existing PIM, quoting and OTIF
layers. It automates the two rule-based parts of the purchasing manager's job:

1. **Replenishment** — work out what we're short of, raise a PO with the right
   supplier (MOQs and carriage considered), track it back in.
2. **Price maintenance** — keep trade price on Unleashed at least **3.5×** the buy
   price, and tell the team when a price goes up.

It's a **zero-build, vanilla-JS Progressive Web App** (same approach as the
`Revise` app at the repo root and the `bim-builder` module). Open `index.html`
and go. Out of the box it runs on a **demo dataset** so every flow is usable
offline; point it at a live Unleashed proxy in **Settings** to go live.

## Why a module, not an artifact or a Cowork task

Straight from the scoping doc, and the reasons hold here:

- **It runs on a schedule and polls Unleashed.** The Unleashed API is pull-only
  with no webhooks, so the bot checks on a timer rather than being notified.
- **It holds state between runs** — what's on order, what's been flagged, carriage
  progress per supplier, the approval queue, and an append-only price ledger.
- **It writes back to Unleashed reliably**, which means a proper service with
  retries, not a script someone runs by hand.
- **It reuses everything Ricobot already has** — the Unleashed connection, auth,
  the Synology Docker deployment, and the staging + promote workflow.

## Drafts first (and the one flag that flips to auto)

Everything is **drafts-first**: the bot proposes, a human approves, then it writes.

- **Price changes** land in an approval queue; approving does a read-modify-write
  back to Unleashed and appends to the ledger.
- **Replenishment** creates POs as **Parked** (v1) and queues the supplier email.
  Once the drafts have been trusted for a few weeks, a **single flag** in Settings
  (*Auto-place*) flips to v2: POs are created **Placed** and the email is sent. The
  logic is identical — only the final status and the send step change.

## The two engines

### Price maintenance (`pricing.js`) — Phase 1, built first

Polls products, reads the buy price and current trade price, flags anything below
`multiplier × buy`, proposes a corrected price rounded by your convention, and on
approval writes it back and logs it. Built first because it's the smallest, lowest
risk, and proves the Unleashed **read and write** path before the heavier engine
sits on top.

### Replenishment (`replenish.js`) — Phase 2, drafts only

1. Poll outstanding sales orders + stock; net shortfall = needed − on hand − on order.
2. Map each short product to its default supplier.
3. Group by supplier into a candidate PO.
4. Round each line up to its **MOQ**.
5. **Carriage:** if below the free-carriage threshold, flag it and suggest top-ups
   to cross it (`topups.js` — heuristic by default, with an optional LLM hook).
6. Compute line totals, subtotal and tax (Unleashed doesn't calculate these for you).
7. Set the expected delivery date; record the sales orders to attach the PO to.
8. v2 drafts **and sends** the supplier email; v1 drafts it and leaves it queued.

### Goods in (Phase 4) — scoped placeholder

The fiddliest piece (delivery-note photo on WhatsApp → order number → match the PO
→ check in), kept deliberately separate so it never blocks the reorder engine. The
**Goods In** tab documents the flow and offers an interim manual check-in. The real
WhatsApp Business API + OCR pipeline is a fast-follow.

## Live Unleashed: a proxy, never a key in the browser

The Unleashed API is **HMAC-SHA256 signed** and the secret API key must never sit
in a browser. So `unleashed.js` talks to a **server-side proxy** that holds the
id/key and does the signing. Set the proxy base URL in **Settings → Unleashed
connection**; leave it blank to stay on demo data. The proxy belongs on the same
Synology service Ricobot already deploys to (see `deploy/`). This is exactly why
the scoping doc insists it's *a service, not a script*.

The adapter encodes the Unleashed facts that shape the design: pull-only (poll on a
timer), one PO per request (loop per supplier), updates overwrite (read-modify-write),
and calculated fields are the caller's job.

## Open questions surfaced as settings

The scoping doc's open questions aren't buried as constants — they're explicit
choices in **Settings**:

- **Which buy-price field** is the source of truth for the 3.5× rule —
  `defaultPurchasePrice`, `lastCost`, or `averageLandedCost`. (They differ.)
- **Rounding convention** for the proposed sell price (every option rounds **up**,
  so a rounded price never drops back under the rule).
- **Auto-place** on/off (the v1 → v2 phase flip).
- **Approver**, named on every ledger entry — who owns the queue once the manager
  has gone.

Supplier **carriage thresholds, charges and lead times** live in the editable
**Suppliers** table — time-critical to capture from the departing manager while
it's still in his head. Rows missing carriage data are tagged *incomplete*.

## Files

| File | Purpose |
|------|---------|
| `index.html` | App shell, styling, left-hand Purchasing Section nav |
| `app.js` | Orchestration — nav, routing, rendering, wiring engines to the DOM |
| `pricing.js` | Price-maintenance engine (3.5× flag → propose → approve → write → log) |
| `replenish.js` | Replenishment engine (shortfall → supplier → MOQ → carriage → PO) |
| `topups.js` | Carriage top-up suggester (heuristic + optional LLM hook) |
| `suppliers.js` | Supplier settings domain helpers (import from products, completeness) |
| `unleashed.js` | Unleashed adapter — demo mode + live server-side proxy |
| `store.js` | Persistent state — settings, queues, on-order, append-only ledger |
| `sample-data.js` | Demo Unleashed dataset so the whole flow runs offline |
| `util.js` | Money, rounding conventions, dates, ids |
| `manifest.json`, `sw.js`, `icon.svg` | PWA install + offline shell |
| `deploy/` | Synology pull-deploy (matches the Ricobot NAS workflow) |

## Notes & limits (current MVP)

- **Scheduling.** In the full Ricobot service the engines run on a timer
  (cron/the NAS scheduler hitting the proxy). In this front-end you trigger a poll
  from the Dashboard; wiring a periodic background sync is a small addition once a
  live proxy is in place.
- **State** is in `localStorage` (one browser/device). The append-only ledger and
  queues are the records of truth between runs; **Export state (JSON)** in Settings
  dumps everything. Moving state server-side (alongside the proxy) is the next step
  for multi-user use.
- The **LLM top-up** step is optional: a host wires `window.PURCHASING_LLM`; without
  it, the deterministic heuristic is used so nothing is ever blocked on a model.
