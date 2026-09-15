# Ricoman — Product model & website requirements

Derived from the **RICOBOT** pricing-engine / BOM data (e.g. the *Estrella Pro*
BOM rules and the SKU/family audits) plus the live ricoman.com catalogue. This
defines what the website/theme must model. RICOBOT itself isn't reachable from
the build environment — if it can export a product feed (CSV/JSON/API), that
becomes the single source of truth for the configurator (see "Integration").

## 1. The data model

```
Family (range)            e.g. Estrella Pro, Flow, Neptune
  └─ Base product          base code R340501 — "Estrella Pro 532mm Linear Opal"
       ├─ Variant axes      options the specifier chooses (below)
       ├─ Order code        built from suffixes: R340501/BK/4K
       ├─ Price             from the engine, reconciled to Unleashed
       └─ BOM components    C34-* (drivers, diffusers, EM packs) — INTERNAL ONLY
```

- **Family** maps to the existing `Applications`/category structure and the
  marketing ranges (Linear, Downlights, Pendants, Biophilic, Acoustic, Track,
  Amenity & Outdoor, Modular Recessed).
- **Base product** = the page you specify and add to a project.
- **BOM components are not shown** on the website; they only prove the
  configurator/pricing logic.

### Configurable variant axes (confirmed in RICOBOT variant filters)
| Axis | Example values | Notes |
|---|---|---|
| Length / size | 532 / 1064 / 1596 / 2128 mm | drives wattage & lumens |
| Optic / diffuser | Opal, UGR Microprismatic, Wallwash, White Louvre | |
| Body colour / finish | White, Black, any RAL | suffix `/WT` `/BK` |
| Colour temperature | 3000K, 4000K (tunable where offered) | suffix `/3K` `/4K` |
| Wattage / output | 7–52W (scales with length) | |
| Dimming | DALI / DALI-2, phase, 1–10V, non-dim | codes DD/DH/CC/DA |
| Emergency | maintained / non-maintained | flag `E` |
| Indirect / up-light | on / off | flag `UP` |
| IP rating | IP20 … IP65 | |
| Connectors | e.g. CON3 | |

## 2. What each PRODUCT page must include

**Identity & marketing**
- Family + product name, short description, hero image, image/render gallery
- Applications / sectors (retail, office, hospitality, healthcare, education…)
- Project references using this product

**Configurator (the core requirement)**
- Pick the variant axes above → resolve a **live order code**
- Show **specs that update with the selection** (W, lm, lm/W, CCT, CRI, beam, IP,
  dimming, dimensions, weight)
- Show **price / "request price"** and **stock/lead time** (from Unleashed/engine)
- **Add to My Project** (the spec list we built) at the configured code
- Validation so only valid combinations are selectable (mirror the BOM rules)

**Downloads / specification assets** (specifier essentials)
- **Datasheet (PDF)** — ideally generated per configured code
- **BIM / Revit** family + **photometric files (IES/LDT)**
- Installation instructions, DIALux/Relux objects, declarations (CE/UKCA), warranty

## 3. What the SITE must include (beyond product pages)

- **Range / family landing pages** with filtering by application, mounting, optic
- **Projects / case studies** (Flow at BetFred, King's Gate, Flour Patisserie…)
- **Lighting design service** (free scheme design, Relux/DIALux, BIM)
- **Manufacturing / Made in Britain** (in-house, UK-first automated bending machine)
- **My Project / Toolbox** — multi-product spec list → export / request quote + BIM
- **Resources** — datasheets, BIM, photometry, brochures, guides hub
- **Contact / quote** lead capture (already built) wired to the team
- **Search & filter** across a large catalogue (hundreds of bases, 1000s of SKUs)
- SEO/GEO: Product schema with the spec PropertyValues, breadcrumbs (already built)

## 4. Integration with RICOBOT / Unleashed (recommended)

The website should not re-key product data. Best architecture:

1. **RICOBOT/PIM = source of truth** for bases, variant axes, order-code rules,
   specs and (via Unleashed) price/stock.
2. Expose a **product feed or API** (CSV/JSON nightly, or a REST endpoint).
3. The theme/configurator consumes it: builds the order code, looks up spec +
   price/stock, and the datasheet/BIM links — so the site always matches the
   engine and no one maintains specs twice.

**To move this forward I need one of:**
- a RICOBOT/PIM **export** (CSV/JSON) of families, bases, variant options and
  attributes — drop it in `reference/`, or
- read access to the export it already writes (Drive/Sheet), or
- the **ACF field groups + CPT/taxonomy export** from the live WordPress, so I
  map the theme to however products are modelled there today.

## 5. Open questions for Ricoman

- Does RICOBOT expose an API/feed the website can read live, or is it batch?
- Should prices show publicly, be trade-login gated, or "request a price"?
- Per-configured-code datasheets/BIM auto-generated, or a fixed library per base?
- Which families launch first on the new site?
