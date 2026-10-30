# The new Ricoman website — what's changed & why it matters

A summary for the team and management. Drop screenshots into the **[SCREENSHOT: …]**
slots. Where it says **[MEASURE]**, run the figure on launch day (it can't be
faked) — instructions are in the Performance section.

---

## In one line
The new ricoman.com isn't just a redesign — it's **built natively in WordPress**
(no slow page‑builder), **fixes years of hidden technical debt**, gives the team
**self‑serve control** of products and pages, and adds **lead‑capture, SEO and
specifier tools** the old site never had.

---

## 1. It fixes serious hidden problems
- **75,911 images in the old library — 69,315 (91%) were exact duplicates**, created
  by the old WooCommerce variant import (a new copy per variant).
- That bloat **broke the hosting backups** — Plesk backups had been **failing since
  ~Oct 2025** and ballooned to **248 GB**.
- The new site is built from a **cleaned, de‑duplicated** library, so backups work
  again and hosting is **smaller, cheaper and more reliable**.
[SCREENSHOT: Media Cleanup screen showing the duplicate count]

## 2. It's faster (and built to stay fast)
Performance features baked in:
- **No page builder** (Elementor removed) — pages render natively.
- **WebP images**, lazy‑loading, and a graceful image fallback.
- **Lean product tables** — big variant ranges no longer dump thousands of hidden
  rows into the page.
- **Caching** + auto cache‑clearing on deploy.

**[MEASURE] on launch day:** run the homepage, a category and a product page through
**Google PageSpeed Insights** (pagespeed.web.dev). Record Mobile + Desktop scores
and load time, before (old site) vs after (new site):

| Page | Old (mobile) | New (mobile) | Old (desktop) | New (desktop) |
|---|---|---|---|---|
| Homepage | [MEASURE] | [MEASURE] | [MEASURE] | [MEASURE] |
| Category | [MEASURE] | [MEASURE] | [MEASURE] | [MEASURE] |
| Product | [MEASURE] | [MEASURE] | [MEASURE] | [MEASURE] |
[SCREENSHOT: PageSpeed Insights result for the homepage]

## 3. The team can run it themselves (no developer)
- **Product Page Builder** — edit spec/variant tables, choose which columns show,
  toggle which filters appear, drop content blocks in — live preview.
- **Configure families** — one configurable table across a whole range (e.g. all
  Estrella apertures) with a Type filter.
- **Colour finishes manager** — manage the colour chips on each product image.
- **Editable patterns** — homepage, About, feature pages all editable as blocks.
[SCREENSHOT: Product Page Editor (edit left / preview right)]

## 4. It generates leads (the old site didn't)
- **Download gate** — datasheets/instructions captured behind name + email + type.
- **BIM / Revit request**, **enquiry form**, **newsletter**, **callback request** —
  all log to one **Leads** CRM with status tracking + Google Sheets sync.
- **Accounts + "My Project"** — customers save products into named projects and
  download a **project pack ZIP** of all the docs.
[SCREENSHOT: Ricoman → Leads list]

## 5. It's built for specifiers
- **Spec filtering** on category pages — filter by **lumens, wattage** (and
  **cut‑out size** on downlights), pulled from the real variant data.
- **Breadcrumbs** on products (the old site's were broken) + breadcrumb schema.
- **Configure / order‑code tables**, datasheets, IES‑LDT and BIM for spec packs.
- **Flow+ designer** tool for curved linear runs.
[SCREENSHOT: a category page with the filter sliders]

## 6. SEO is built in
- Per‑page **titles, meta descriptions, focus keyphrases** (auto‑seeded, editable).
- Full **schema** (Organisation, LocalBusiness, Product, Article, FAQ, Breadcrumb).
- **Auto internal linking** — news/projects auto‑link to product/category/sector
  pages (backlinks for SEO + conversion); news auto‑tags by Topic.
- **Editable category landing copy** (intro + FAQ) for keyword‑rich pages.
- **301 redirect** manager + 404 watch to protect rankings at launch.
[SCREENSHOT: Page SEO / Category SEO screen]

## 7. Richer content types
- **News** with topics, byline (writer/role/contributor) and key‑takeaway boxes.
- **Projects** with sector hubs and **specification‑consultant (SPC)** profile pages.
- **Team profiles** — staff pages (photo, role, contact, bio) that list everything
  they're tagged on.
- **Feature/landing pages** — Casambi, Human Centric, Antimicrobial, Fire Safety,
  Sustainability, Made in Britain, Trade, Where to Buy.
[SCREENSHOT: a Team profile page]

## 8. Safe, simple publishing
- **Content** → edit live with **Draft → Preview → Publish**.
- **Features** → built on staging, then a deliberate **🚀 Push to Live** sign‑off
  (type PUBLISH). Live customer data is never overwritten.
- Caches clear automatically; no Plesk needed for day‑to‑day.
[SCREENSHOT: Push to Live sign‑off screen]

---

### Headline takeaways for management
- **Reliability:** backups fixed; storage cut dramatically.
- **Cost:** smaller, faster hosting; no page‑builder licences.
- **Growth:** lead capture + SEO the old site lacked.
- **Independence:** the team runs products, pages and content without a developer.
