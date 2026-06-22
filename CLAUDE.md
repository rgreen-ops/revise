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
- [ ] **News: writer / contributor byline area** — PARTIAL; needs an author/
  contributor display on news articles.
- [ ] **About page split: Why us / About us / Sustainability / Team & culture**
  — PARTIAL: About exists, Sustainability is already a full landing page; add
  "Why us" + "Team & culture" sections/blocks.
- [ ] **FAQ area on product pages** — PARTIAL: FAQ engine + FAQPage schema exist
  on category/feature pages ([ricoman_faq]); extend to product pages via builder.
- [ ] **SPC area on project pages + a page per SPC** (e.g. Neasha, Colin) listing
  all that consultant's projects — NEW. Needs a "consultant/specifier" profile
  (CPT or taxonomy) linked to projects + an archive page per person.
- [ ] **Homepage stats band** (countries supplied, # of projects) — NEW.
- [ ] **Partner logos + partner feedback/testimonials area** — NEW.
- Sequencing: do quick wins first (FAQ on products, homepage stats band, partner
  logos strip), then the SPC consultant pages (largest). All AFTER the current
  image/launch issues are settled. Content persists (installer is create-once).

## Category filter enhancements — TRACKED (backlog)
- [ ] **Cut-out size slider on Downlights** (and any cut-out category): add a
  range slider like the existing Light output (lumens) / Power (wattage) sliders.
  Cut-out diameter (mm) lives in the variant spec/ACF; extend the precomputed
  variant metrics + `inc/product-filter.php` to emit a `cutout` min/max and a
  slider. Make it CONDITIONAL — only render when the category's products actually
  have cut-out data (so it won't show on pendants/linear etc.). Same UI/behaviour
  as the lumens/watts sliders.

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
- **PHP OPcache is stale** — restart PHP-FPM in Plesk (Tools & Settings → Services
  Management) to release all PHP changes. CSS/JS already go live (W3Speedster purged).
- **Disk is FULL** ("No space left on device" — backups failing). Run Ricoman →
  Media Cleanup → Step 3b (permanent delete) to reclaim the 248GB of duplicates.
  WebP generation stays dormant until space is freed.

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
