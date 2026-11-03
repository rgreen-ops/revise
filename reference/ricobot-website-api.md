# RICOBOT public API — what the Ricoman website needs

The website mirrors the live RICOBOT family → configure UX. It stores **nothing**
variant-level: families/products/variants are pulled live and cached
(priced/poa 1 h; qty-dependent never). Auth: `Authorization: Bearer <token>`,
all under `/api/public/*`, IP-allow-listed to the web server.

This is the data contract the public site consumes. Items marked **NEW** are
requested additions; the rest confirm existing endpoints.

---

## 1. Families (for the ~50 family pages + nav)

**NEW** `GET /api/public/families`
```json
[ { "family": "Estrella", "category": "Linear", "count": 40, "heroUrl": "…" } ]
```

**NEW** `GET /api/public/families/{family}`
```json
{
  "family": "Estrella", "category": "Linear", "count": 40, "heroUrl": "…",
  "facets": [
    { "key": "optic",  "label": "Optic",  "choices": [ { "value": "opal", "label": "Opal", "count": 7 } ] },
    { "key": "shape",  "label": "Shape",  "choices": [ { "value": "linear", "label": "Linear", "count": 4 } ] },
    { "key": "length", "label": "Length", "choices": [ { "value": "1064", "label": "1064mm", "count": 1 } ] }
  ],
  "products": [
    { "code": "R341101", "name": "Estrella Pro — L1064mm, Opal",
      "attributes": { "optic": "opal", "shape": "linear", "length": "1064" },
      "heroUrl": "…" }
  ]
}
```
> Powers the FILTER chips (with counts, "X of Y match") that narrow to a product code.
> If `GET /api/public/products` already returns each item's `family` + `attributes`,
> the site can build family pages by grouping and #1–2 are optional.

---

## 2. Product (the configurable unit, e.g. R341101)

`GET /api/public/products`  — confirm each item includes:
`{ code, name, family, attributes:{optic,shape,length,…}, heroUrl, basePrice }`
(and that it pages: `?page=` with `{ products, total, page }`)

`GET /api/public/products/{code}` — confirm/add:
```json
{
  "code": "R341101", "name": "…", "family": "Estrella", "category": "Linear",
  "options": [                                     // "Build a variant" axes — EXISTS
    { "key": "bodyColour", "label": "Body Colour", "optional": false,
      "choices": [ { "code": "WT", "label": "White RAL 9016", "priceAddition": 0, "isDefault": true } ] }
  ],
  "technicalData": [                               // NEW — the spec table
    { "section": "Physical data",
      "rows": [ { "label": "Construction Material", "value": "Aluminium", "auto": false },
                { "label": "Wattage", "value": "14W", "auto": true } ] }
  ],
  "accessories": [                                 // NEW — "ADDS £x"
    { "code": "R33-07", "name": "Estrella Pro 3m suspension kit", "price": 22.99 }
  ],
  "documents": [                                   // NEW — approved PDFs
    { "type": "datasheet", "title": "Estrella datasheet", "url": "…", "rev": "Rev 4" }
  ],
  "photometric": [                                 // NEW — .LDT files
    { "name": "R341101-14W-WT-Opal…LDT", "url": "…", "appliesTo": "all" }
  ],
  "gallery": [                                      // NEW — typed + variant-aware
    { "url": "…", "type": "studio", "caption": "White housing",
      "appliesTo": { "bodyColour": "WT" } },
    { "url": "…", "type": "insitu", "caption": "Office install", "appliesTo": "all" }
  ]
}
```
`gallery.type` ∈ `studio | insitu | dimension | diagram`. `appliesTo` lets the
site show **Studio / In-situ** tabs and swap shots for the selected variant.

---

## 3. Price (variant resolver) — EXISTS

`GET /api/public/price?sku=R341101/WT/4K/14W/DD/CON2`
```json
{ "status": "priced", "price": 0, "currency": "GBP",
  "breakdown": { "base": 0, "addons": [ { "label": "DALI Dimming", "amount": 75 } ], "total": 0 },
  "datasheetUrl": "…", "discontinued": false }
```
Other statuses: `poa{reason}`, `qty-dependent{qty1Price,formula}`,
`configurator-partial`, `unknown-suffix|no-variant|not-found`.
Cache priced/poa 1 h; **never** cache qty-dependent.

---

## Website page = (all live, cached, nothing stored)
1. **Family page** — FILTER facets (Optic/Shape/Length) → narrow → pick a product.
2. **Build a variant** — option axes → live SKU + BASE/ADDONS/TOTAL.
3. **Gallery** — Studio / In-situ tabs, variant-aware.
4. **Technical data** table · **Compatible accessories** · **Downloads** (documents + .LDT photometric).

Admin-only in RICOBOT (NOT on the website): uploads, suggestions/comments queue.
