# Ricoman project — working plan & memory

## The agreed roadmap (work off this, in order)
1. **Recreate ricoman.com exactly on staging, classic (no headless/Laravel/JS).**
   Same look/format as the current live site, rendered natively in WordPress from
   the migrated database (the old site's ACF data). We must be able to drop our
   patterns into existing pages to add content. **RICOBOT sync is PARKED for now**
   — use the migrated database data instead.
2. **Check & enhance SEO.**
3. **Launch: migrate staging → live.**
4. **Create feature pages & enhanced pages.**
5. **Sync RICOBOT** (re-enable live product data last).

## Current focus (Step 1)
Make **every** page on staging **pixel-for-pixel identical** to current ricoman.com
— every link, image, page, template. **DECISION (confirmed): rebuild every page
natively in OUR theme (Ricoman).** No Elementor (too slow), no old `ricomanled`
theme, RICOBOT parked — render from the migrated ACF database. Method:
- Build a list of every page/template (below).
- Work through them one by one in our theme.
- Richard signs off each **template** (e.g. news master, news article, product
  page, product category) only when it's confirmed pixel-for-pixel.
- Reference for pixel matching = the live ricoman.com pages (screenshots from
  Richard + live HTML), since the old theme renders via Elementor/ACF.

## Template sign-off checklist (Step 1) — status
GLOBAL: [ ] Header / mega-menu  [ ] Footer
[ ] Home  [ ] Product category (product-cat)  [ ] Family category page
[ ] Product single (simple)  [ ] Product single (Flow/Astrowave)
[ ] Product single (Fire-rated)  [ ] News master (listing)  [ ] News article
[ ] Project listing  [ ] Project single (short)  [ ] Project single (long)
[ ] About  [ ] Contact  [ ] Casambi  [ ] Sustainability  [ ] Human Centric
[ ] Antimicrobial  [ ] Fire Safety  [ ] I-Joist Ceilings  [ ] Made in Britain
[ ] Custom Lighting  [ ] Lighting Design (leadgen)  [ ] Trade  [ ] Downloads
[ ] Catalogue  [ ] Our Showroom  [ ] Our Vision  [ ] Where To Buy
[ ] Stock & Availability  [ ] Our Services  [ ] UAE Exports  [ ] UK Manufacturer
[ ] Thank You  [ ] Dashboard  [ ] Create Project  [ ] Login / Registration
[ ] Site map  [ ] Legal (Privacy, Cookie, Terms, Email Notice, Slavery, Warranty)


## Key facts established
- **MARKETING STATS (for Richard's report on the new website):**
  - Old media library held **75,911 images, of which 69,315 (91%) are exact
    duplicates** — from the old WooCommerce variant import adding a new image per
    variant. Removed via the new site's Media Cleanup tool.
  - That bloat also **broke the hosting backups**: Plesk scheduled backups had
    been **failing since ~Oct 2025**, ballooned to **248 GB**. Cleaning the
    duplicates shrinks backups and makes them reliable again.
  - Angle: the new website doesn't just look better — it **fixes years of hidden
    technical debt** (storage bloat, failing backups), and is **faster, cheaper
    to host, and more reliable** as a result.
  - **Self-serve product pages:** custom back-end Product Page Builder lets the
    team edit the spec/variant tables (incl. choosing which columns show) and
    drop content elements into product pages — no developer needed.
  - **Automatic SEO link-building + tagging:** news articles and project case
    studies auto-link relevant terms to product / category / sector pages
    (internal backlinks for SEO + conversion); news auto-tags by Topic and
    projects link to their sector hubs — all automatic.
  - **Spec filtering:** category pages let customers filter products by light
    output (lumens) and power (wattage), pulled live from the real variant data
    — easier for specifiers to find the right fitting.
- Old products store everything in **ACF**; post types: `product`, `project`,
  `news`, `home_slider`, `lighting_sectors`, `variant-product`, `lead`.
- Real taxonomies: `product-cat`, `applycation-type`, `project-cat` (+ many
  variant axis taxonomies: color, wattage, temperature, size, beam-angle, …).
- The 53 original ACF field groups are bundled in `inc/acf/ricoman-fields.json`
  and registered by `inc/acf-fields.php` (recreates the editing back-end + lets
  repeaters resolve on the front end).
- Order-code / Configure-table rows are separate **variant-product** posts linked
  to their parent via the ACF `parent_product` field.
- Branch: `claude/wordpress-theme-s5u19q`. NO PRICING anywhere. Don't put the
  model id in commits/PRs/code.

## Lead-gen system (built — `inc/lead-gate.php`, `accounts.php`, `project-lists.php`)
- **Download gate** (`inc/lead-gate.php` + `assets/js/lead-gate.js`): logged-out
  visitors clicking any doc download get a popup (name + email + customer type, or
  sign in / register). Captured once per visitor (cookie `rm_dl_gate`, 30 days) →
  feeds the `lead` post type + `ricoman_lead_captured` hook (Sheets sync). Gating is
  client-side by file extension / downloads-area, so it covers all links.
- **Customer types**: `ricoman_customer_types()` (filterable) — shared by gate,
  registration and enquiry form.
- **BIM is request-only** (files don't exist yet): every product shows "BIM / Revit
  (RFA) — request"; submitting logs a lead, emails the requester a thank-you, and
  emails **lightingdesign@ricoman.com** (`ricoman_bim_inbox()` filter) the product +
  details. Works logged-in or out.
- **Accounts** (`inc/accounts.php`): first/last name + customer type on register +
  profile (`_ricoman_customer_type`); logged-in users bypass the gate; non-admins
  redirect home after login. Branded wp-login in `inc/login.php`.
- **Saved projects** (`inc/project-lists.php` + `assets/js/projects.js`): logged-in
  users get multiple named/renamable projects (user meta `_ricoman_projects`,
  `_ricoman_active_project`); My Project page is the manager; "Add to My Project"
  routes to the active server project (guests keep localStorage list in
  `my-project.js`, which is NOT loaded for logged-in users).
- **Project packs**: `?rm_pack=<projectId>` streams a ZIP (per-product folders of
  datasheet/instructions/IES-LDT files + a product-list index). Header has a My
  Project link w/ count + account/log-out.
- **Leads log**: `lead` CPT now has admin columns (Type · Email · Product/Company ·
  Source · Received).

## Media Cleanup (`inc/media-cleanup.php`) — now fast
- Merge dedup was ~30h; fixed via: one-time referenced-ID index (skip the
  per-duplicate postmeta/post_content scan for unreferenced orphans), bulk-trash via
  set-based SQL (not wp_trash_post per item), accurate per-batch remaining count.
- **Step 3b — permanent delete**: batched, resumable, gated by typing DELETE.
  Deletes merged dupes' own files (incl. sub-sizes) to reclaim space; preserves any
  file a kept attachment still shares. (Run after a backup to shrink the 248 GB.)
- Trashed attachments excluded from the image count.

## RICOBOT parked (roadmap step 5) — switch to re-enable
- The old "product API" + family loader are gated behind `apply_filters(
  'ricoman_use_product_api', false )`. While off, product pages render from migrated
  ACF (`ricoman_pf_sections`) and `[ricoman_family]` renders the migrated variant
  table. Flip the filter true when RICOBOT is reconnected.
- `the_content` product render runs at **priority 11** (after wpautop) to avoid
  stray `<p>` wrapping the gallery image.

## Constraints
- Develop/push only to the branch above; never create PRs unless asked.
- Content must persist across theme updates (installer is create-once).
