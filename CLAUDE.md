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
  - **Editable category SEO + ordering:** each product category has editable SEO
    copy (intro above the grid + body/FAQ below) for keyword-rich landing pages
    (e.g. Linear Lighting), plus a manual "Display order" that arranges categories
    across the mega menu, /products/ tiles and homepage range grid; products
    within a category reorder via their native "Order" attribute. A one-click
    **Category SEO** admin page (Ricoman → Category SEO) seeds hand-written
    starter intro + FAQ copy for the core ranges (linear, downlights, panels,
    track, emergency, battens, high bay, floods, bulkheads, spots, strip,
    exterior) — fills empty fields only, never overwrites edits. The category body
    runs the [ricoman_faq] shortcode, so seeded FAQs also emit FAQPage schema. The
    same page edits/seeds an SEO body/FAQ for the main /products/ archive (option
    rm_products_seo_body, rendered by [ricoman_products_seo] below the tiles).
    - **Per-page SEO seeding (roadmap step 2):** hand-written, keyword-targeted SEO
    titles + meta descriptions for every core + feature page live in
    `inc/page-seo-seed.php` (`ricoman_page_seo_library()`), written to the same
    `_ricoman_seo_title`/`_ricoman_seo_desc` meta the SEO box (inc/seo.php) reads.
    A one-click **Page SEO** admin page (Ricoman → Page SEO), the create-once
    installer AND the Page Designs refresh all run `ricoman_page_seo_seed_all()`,
    which fills empty fields only — never overwrites edited copy. Mirrors the
    Category SEO seeder. The SEO engine already does title/desc/canonical/robots/
    OG/Twitter + a full JSON-LD @graph (Org, WebSite+Search, LocalBusiness+Geo,
    Product, Article, BreadcrumbList, CollectionPage) and is Yoast-aware.
    Page SEO screen also has an additive **footer link top-up**
    (`ricoman_footer_links_topup()`) that appends missing feature-page links to
    foot_col_other without removing edits (for existing staging). OG/social image
    (`ricoman_seo_image`) now falls back to a page's first content image (hero)
    when no featured image is set, before the site default. Focus keyphrases are
    seeded too (`ricoman_page_focus_library()`, chosen to appear in each seeded
    title so the SEO scorer passes). A one-time `admin_init` migration
    (`ricoman_seo_seeded_v1`) auto-applies page SEO + category SEO + footer
    link top-up once on existing sites (fills empties only) so SEO copy lands
    without anyone clicking a button. **Roadmap step 2 (SEO) is complete**;
    remaining SEO work is launch-time (301 redirect map from the old URL list).
- **Image alt text (SEO + a11y):** new uploads auto-get alt from a tidied title;
    a batched, resumable backfill (Ricoman → Image Alt Text) fills the migrated
    library, deriving alt from each image's title or its parent product/post, and
    marks scanned images (`_rm_alt_scanned`) so the loop terminates. Product JSON-LD
    is enriched from the real product-cat + precomputed variant metrics (lumens/
    watts/features) since the migrated catalogue keeps specs in ACF, not meta.
  - **Feature / enhanced landing pages (roadmap step 4):** Casambi, Human Centric
    Lighting, Antimicrobial Protection, Fire Safety, Sustainability, Made in Britain,
    Trade and Where to Buy are now rich, editable landing pages (split header,
    benefits, image+checklist, an FAQ that emits FAQPage schema via [ricoman_faq],
    conversion CTA) — built from a shared $feature generator as core-block patterns
    (＋ → Patterns → "Ricoman — Page", slugs feat-*) with block fns
    ricoman_*_blocks(). Wired into the create-once installer AND the Page Designs
    refresh tool (Ricoman → Page Designs) so existing staging pages can opt-in to
    the new design. The old thin info-page versions were removed from
    ricoman_info_pages() (which now keeps only News + legal/warranty pages).
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

## Marketing wishlist (roadmap step 4 — enhanced pages) — TRACKED
From Marketing's email for the new website. Status of each request:
- [x] **Breadcrumbs from products** — DONE (visible `Home / Products / Cat / …`
  + BreadcrumbList schema). Fixes their "not set up correctly on current site".
- [x] **Backend with design freedom** — DONE (Product Page Builder, Page Designs
  refresh, Category SEO + Page SEO editors — team edits without a developer).
- [x] **Downloads → a "lighting" resource area** (brochures + lighting guides +
  BIM + instructions, as a menu of options) — LARGELY DONE: gated Downloads,
  per-product datasheet/instructions/IES-LDT, BIM/Revit request-on-demand,
  project-pack ZIP. TODO: surface "lighting guides" as a distinct download type.
- [x] **News: choose between topics** — DONE (Topic taxonomy + auto-tagging +
  auto internal linking). TODO below for the writer/contributor byline.
- [x] **News: writer / contributor byline area** — DONE. Writer / Writer role /
  Contributor fields in the news metabox; single-article byline shows
  "By <writer>, <role>" (falls back to the WP author) + date + read time +
  optional contributor credit.
- [x] **About page split: Why us / About us / Sustainability / Team & culture**
  — DONE. Added "About · Why us" + "About · Team & culture" patterns; About us +
  Sustainability already existed (Sustainability is a full page).
- [x] **FAQ area on product pages** — DONE. New "Product FAQs" metabox (Q:/A:
  text) on the product edit screen; renders as a product **FAQ section** (with
  FAQPage schema) via ricoman_pf_sections + a `faq` section block. Independent of
  the visual builder so it always works.
- [x] **SPC area on project pages + a page per SPC** (e.g. Neasha, Colin) — DONE.
  New `spc` taxonomy on projects (Projects → Consultants); tag projects with their
  consultant. Each gets a public archive /spc/<name>/ (taxonomy-spc.html) listing
  their projects, and project pages show a linked "Specification consultant"
  credit. Setup needed by the team: create consultants + assign to projects.
- [x] **Homepage stats band** (countries supplied, # of projects) — DONE as an
  editable pattern (Home · Reach stats). Drop it on the homepage and edit numbers.
- [x] **Partner logos + partner feedback/testimonials area** — DONE as editable
  patterns (Home · Partner logos = placeholder boxes to swap for logos; Home ·
  Testimonials = feedback cards). Drop onto the homepage and edit inline.
- Sequencing: do quick wins first (FAQ on products, homepage stats band, partner
  logos strip), then the SPC consultant pages (largest). All AFTER the current
  image/launch issues are settled. Content persists (installer is create-once).

## Category filter enhancements — TRACKED (backlog)
- [x] **Cut-out size slider on Downlights** — DONE. New `cut_out` variant CSV
  column + spec def (shows in the configure table/datasheet). Variant metrics
  aggregate a max cut-out (mm) per product (parsed from variant meta + parent
  text); the category filter renders a dual-range "Cut-out … mm" slider
  CONDITIONALLY (only when the category's products have cut-out data), matching
  the lumens/wattage sliders. Populate via the variant CSV (cut_out column).

## Configure-table enhancements — TRACKED (backlog)
- [x] **Whole-family configure table (Estrella)** — DONE. New `config-family`
  taxonomy (Products → Configure Families); tag member products into one family.
  `ricoman_pf_variant_table()` then loads variants across all family members and
  adds a **Type** column + filter drop-down (labels auto-derived from member
  titles with the shared prefix stripped, e.g. Opal / Wallwasher / Square
  aperture / Black louvre / White louvre / Microprismatic). Setup needed by the
  team: create the family term and assign the products. Original plan below:
- [ ] (superseded) **Whole-family configure table (Estrella)**: the "Configure Your Product"
  table currently loads only `variant-product` posts where `parent_product` =
  THIS product (e.g. Estrella Pro Opal). Marketing want ONE table spanning the
  whole Estrella range — Opal, Wallwasher, Square aperture, Black louvre, White
  louvre, Microprismatic. Plan: group the Estrella member products into a
  "configure family" (shared family key/taxonomy on the products, or re-link all
  their variants to one parent), make `ricoman_pf_variant_table()` query variants
  across all family members, and add an **"Aperture / Type"** filter column
  (Opal / Wallwasher / Square aperture / Black louvre / White louvre /
  Microprismatic) alongside the existing Lumens/Wattage/etc. dropdowns.
  DECISION (confirmed): family membership via a **shared family field/taxonomy**
  on products (tag Estrella types into it) — reusable for other ranges (Flow,
  etc.), no variant re-linking. NOTE: this is a NEW grouping for the configure
  table; do not confuse with the old retired "Assign Family" category ACF group.

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
- **Leads CRM** (`inc/leads-admin.php` — single source of truth for the Leads list;
  the old duplicate columns in `lead-capture.php` were removed so they don't clobber
  it): rich columns (Type · Name · Role · Email · Location · Sales rep · Source ·
  New lead · Pipedrive · Status · Comment · Page · Received), a
  gamified dashboard, status workflow + source attribution, filters and CSV
  export. **Extra editable CRM fields** (`ricoman_lead_crm_fields()`): Location,
  Sales rep, New lead, On Pipedrive, Comment — all **inline-editable
  from the list** (toggle / text, AJAX `rm_lead_setfield`, per-lead nonce), also on
  the lead screen metabox, and round-tripped via **CSV import** (matched by the ID
  column; updates only the editable fields + Status). Auto-fill on capture
  (`ricoman_lead_autofill_crm` on `ricoman_lead_captured`): **New lead** = this
  customer's email has never appeared in our leads list before (i.e. never enquired
  OR downloaded with us — the separate "New download" flag was merged into this);
  **Location** = Cloudflare `HTTP_CF_IPCOUNTRY` → country name (filter
  `ricoman_lead_location`), all still hand-editable. Filters added for Sales rep /
  Location / New lead / Pipedrive.
  CSV Export/Import toolbar renders via `admin_notices` (outside the posts-filter
  form, so the upload form isn't nested).
- **Newsletter capture** (`inc/lead-capture.php`): `[ricoman_newsletter]` shortcode +
  a footer signup AND an end-of-news-article signup (email-only, low friction) logs
  "Newsletter" leads into the same CPT/CRM/Sheets pipeline; AJAX `rm_newsletter`,
  nonce + honeypot + spam-screened, de-duped per email. Base CSS is light-context;
  `.rm-foot-news` overrides for the dark footer.
- **Request a callback** (`inc/lead-capture.php`): `[ricoman_callback]` shortcode
  (name + phone + best-time) on the Contact page sidebar; AJAX `rm_callback` logs a
  "Callback request" lead, emails admin (time-sensitive), spam-screened. Drop the
  shortcode anywhere for another low-friction lead source.
- **Spam protection** (`ricoman_lead_is_spam()` in `inc/lead-capture.php`): shared
  screen on EVERY capture point (enquiry form, download gate, BIM request,
  newsletter) on top of the per-form nonce + honeypot — per-IP rate limit
  (transient, filterable `ricoman_lead_rate_limit`/`_window`), a time-trap (hidden
  `rm_t` render time; lower-bound only so caching can't false-positive), link-
  stuffing + spam-term checks. Spam is silently accepted (no bot feedback) but
  stored/forwarded to nothing. Security across capture points: nonces, capability
  checks on admin AJAX, sanitize on input + escape on output, prepared SQL.

## Media Cleanup (`inc/media-cleanup.php`) — now fast
- Merge dedup was ~30h; fixed via: one-time referenced-ID index (skip the
  per-duplicate postmeta/post_content scan for unreferenced orphans), bulk-trash via
  set-based SQL (not wp_trash_post per item), accurate per-batch remaining count.
- **Step 3b — permanent delete**: batched, resumable, gated by typing DELETE.
  Deletes merged dupes' own files (incl. sub-sizes) to reclaim space; preserves any
  file a kept attachment still shares. (Run after a backup to shrink the 248 GB.)
- Trashed attachments excluded from the image count.

## Product images & old-URL handling (this session)
- **Migrated image URLs:** ACF stored absolute old-domain URLs with a `-1024x1024`
  sub-size that was never generated. `ricoman_norm_img_url()` (inc/image-fallback.php)
  re-hosts any /wp-content/uploads/ URL onto this site + drops a missing size suffix
  to the original, then defers to the live-origin fallback. Wired into
  `ricoman_pf_imgurl`.
- **WebP (URL-based):** inc/webp-convert.php now also has `ricoman_webp_for_url()` /
  `ricoman_webp_make_file()` serving a `-rmwebp.webp` twin for raw uploads PNG/JPG
  URLs (migrated product images bypass the attachment converter). **Disk-guarded**
  (only writes when >150MB free — the staging disk is FULL, see below), throttled,
  cached; `the_content` swaps img src/srcset; `ricoman_pf_imgurl` prefers the twin.
- **Old permalinks (inc/redirects.php):** general 404 resolver `ricoman_resolve_old_path()`
  maps any old base (/product/ singular, /track-lighting/{slug}, renamed slugs) to the
  canonical product/project/news/page by slug → `_wp_old_slug` → taxonomy term, 301s,
  and feeds the 404-log "resolved?" check. PLUS a `the_content` rewriter that fixes old
  links at the source.
- **Gallery:** main image is `object-fit:contain` (was cover, which cropped wide
  fittings); white-background photos get a white frame (JS `rm-viz-white`) so they
  blend instead of showing a white box on the grey `#e4e4e4` frame; transparent
  cut-outs keep grey. Mega menu drops redundant "See All Products"/"Categories"
  labels (mobile + desktop).
- **Variants:** `Estrella Pro …` rows exist but weren't linked — new "Link variants
  to a parent" tool (Variant Products → Import/Export). Variant table loads the whole
  family (2000) but shows 10 + Show-more, fully filterable; thumbs fall back to the
  parent image.

## ⚠️ OPEN BLOCKERS (staging) — needed before the above PHP fully works
- **OPcache flush is AUTOMATIC — do NOT tell the user to restart PHP-FPM.** Every
  push to the working branch runs `.github/workflows/deploy-staging.yml`, which
  FTPS-uploads the theme then curls `ricoman/opcache-flush.php?key=ricoman-flush-2026`
  to run `opcache_reset()`. Verified in the job logs ("OPcache flushed. The latest
  PHP is now live"). So PHP changes (incl. NEW files via require_once) go live on
  the next request — just reload. Section caches are WP transients keyed by version
  (e.g. `rm_pfsec_m8`, `rm_cfgimg_ver`); bumping the key auto-invalidates them,
  independent of OPcache. Manual Plesk restart is only a fallback if opcache_reset
  is disabled or the flush curl can't reach staging.
- **Disk is FULL** ("No space left on device" — backups failing). Run Ricoman →
  Media Cleanup → Step 3b (permanent delete) to reclaim the 248GB of duplicates.
  WebP generation stays dormant until space is freed.

## Colour finishes / variants manager (built)
- The chips on the product image (name + swatch + main photo) are managed by a
  **Colour finishes (image chips)** metabox on the product edit screen
  (inc/product-sections.php). Each row = name + swatch image + main image, with
  add / remove / reorder; saves a clean JSON list to `_ricoman_finishes`, which
  `ricoman_pf_color_variants()` reads FIRST (falling back to the migrated
  "Product Variation By Color" ACF field). Image values are an attachment ID or a
  URL — both resolve via `ricoman_pf_imgurl`. Opening a product with no saved
  finishes seeds the box from the migrated data so the team can edit + Update.

## RICOBOT parked (roadmap step 5) — switch to re-enable
- The old "product API" + family loader are gated behind `apply_filters(
  'ricoman_use_product_api', false )`. While off, product pages render from migrated
  ACF (`ricoman_pf_sections`) and `[ricoman_family]` renders the migrated variant
  table. Flip the filter true when RICOBOT is reconnected.
- `the_content` product render runs at **priority 11** (after wpautop) to avoid
  stray `<p>` wrapping the gallery image.

## Push to live — in-WP sign-off screen (non-technical, no Plesk)
- **Ricoman → 🚀 Push to Live** (`inc/push-live.php`): a deliberate sign-off page —
  two tick-boxes + type `PUBLISH` + a final confirm() — that triggers the
  deploy-live GitHub Action via the API (workflow_dispatch, inputs.confirm=DEPLOY).
  CODE ONLY; never touches live data/leads. Capability `ricoman_push_live_cap`
  (default manage_options). One-time setup: `define('RICOMAN_GH_TOKEN', …)` in
  wp-config (fine-grained PAT, Actions read/write) — or paste a token on the page;
  repo/branch default to rgreen-ops/revise + the working branch, editable there.
- Deploys **auto-flush OPcache** (curl the flush URL) so NO Plesk is ever needed;
  staging flush is automatic, live flush runs when the LIVE_URL secret is set.
- Plain-English handover: `reference/marketing-guide.md`.

## Push to live (code/theme) — built, manual-only
- `.github/workflows/deploy-live.yml`: a **manual** (workflow_dispatch) GitHub
  Action that FTP-deploys the `ricoman/` theme to the LIVE site. CODE ONLY — it
  never touches the live DB / content / uploads / users / captured leads. Requires
  typing `DEPLOY` to run, so it can't fire by accident or before launch.
- One-time setup: add repo secrets FTP_LIVE_SERVER / FTP_LIVE_USERNAME /
  FTP_LIVE_PASSWORD / FTP_LIVE_SERVER_DIR (the live theme dir). After a run, clear
  the live OPcache (restart PHP-FPM on live, or hit opcache-flush.php on the live
  domain) + purge any live page cache.
- WHY code-only: live captures real leads/users on the front end; a full-DB push
  would wipe them. The one-time staging→live **launch** (whole DB+uploads, roadmap
  step 3) is a separate migration job, not this button. Selective content sync can
  be added later if needed.

## Team / staff profiles (built — inc/staff.php)
- `staff` post type (menu: **Team**): name (title) + photo (featured image) + bio
  (editor) + **Profile details** metabox (job title, email, phone). Public profile
  at /team/<name>/.
- **Tagging:** a "Ricoman staff" metabox on news + projects (multi-select) stores
  `_ricoman_staff` meta rows. Tagged people show as a "People on this" credit at
  the end of the article/project; their profile lists all content they're tagged on.
- **Insert anywhere with chosen fields:** `[ricoman_staff id="123"
  fields="photo,name,title,email,phone,bio"]` (one person) and `[ricoman_team
  fields="…"]` (grid of all). Patterns: "Team · Grid" + "Team · Single member".
  Name/photo link to the profile page (= their content listing).

## Go-Live (launch) tooling — built
- `reference/launch-guide.md`: step-by-step one-time staging→live launch for the
  SAME Plesk server — free space (clear old backups), back up live (rollback),
  WordPress Toolkit **Copy Data** (staging→live, auto URL-replace), then finish in
  WP. Reassures re: size (you copy the CLEANED staging over live; old bloat is
  replaced, not carried). NEVER copy staging over live again after launch (it
  would wipe live leads) — use 🚀 Push to Live for ongoing code.
- **Ricoman → 🚀 Go Live** (`inc/go-live.php`): pre-flight checklist (indexable,
  permalinks, disk space, SEO seeded, caches, homepage) + one **"Run launch
  finalisation"** button (OPcache + permalinks + product caches + page/category
  SEO seeders + footer links). Safe/idempotent; fills empties, deletes nothing.

## Control Center (inc/admin.php)
- Ricoman → Control Center is the back-office hub. Top card is a **"Things to do"**
  launch checklist + progress bar (`ricoman_admin_status()` returns
  `key => [label, done, fix-link]`): homepage front page, logo, pretty permalinks,
  SEO details, Page SEO seeded (`ricoman_seo_seeded_v1`), analytics/tracking
  installed (`ricoman_tracking` option non-empty), products + projects added.
  Mirrored in the dashboard widget.
- Tool tiles are grouped Content / Design / **Growth & marketing** (Leads,
  Tracking & Scripts, SEO & Speed, SEO Settings, Page SEO, Category SEO) /
  **Catalogue & media** (Configurator Images, Reorder Categories/Products, Image
  Alt Text, Media Cleanup, RICOBOT) / **Launch** (Links & Redirects, Content
  Transporter, Go Live, Push to Live). New links live in `ricoman_admin_links()`.

## Visual configurator (built — inc/configurator-visual.php)
- A tap-through alternative to the variant table (like unios.com/configurator):
  each spec axis (Type/Model/Colour Temp/Wattage/Finish/Beam…) is a row of option
  tiles; picking one **narrows** the rest (unavailable options grey out); when one
  variant is pinned it shows full details + datasheet/LDT. Runs on the SAME variant
  data as the table (family-aware). `assets/js/visual-config.js` renders from an
  embedded JSON blob (axes + variants).
- **Per-product toggle:** product edit screen → "Configure display" box →
  Table (default) vs Visual. Meta `_ricoman_config_visual`; ricoman_pf_sections
  swaps the table for `ricoman_pf_visual_config()` when on (falls back to the table
  if there's nothing to configure).
- Option tiles auto-use each option's representative variant image where the image
  varies by that axis (Finish/Model); text tiles otherwise.
- **Per-option tile images (`inc/configurator-images.php`)** — DONE: deliberate
  image per option value, resolved per-product → master → variant photo
  (`ricoman_config_option_image()`). A **per-product** "Configurator option images"
  metabox auto-lists the product's axis values (family-aware, cached) each with a
  WP media picker (saved to `_ricoman_config_opt_images`); a **master** page
  (Ricoman → Configurator Images) maps axis|value → image globally (option
  `rm_config_opt_images`). Setting any image forces that axis to render image
  tiles. Master saves bump `rm_cfgimg_ver` (in the section-cache key) to refresh
  all products without thrashing the cache.

## Constraints
- Develop/push only to the branch above; never create PRs unless asked.
- Content must persist across theme updates (installer is create-once).
