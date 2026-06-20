# Ricoman staging — test log

Things to verify manually (I can't test logged-in / email flows from here). Tick
them off as you go. Grouped by feature. Staging: https://staging.ricoman.com

> Note: staging may have outbound email disabled for safety — if the "email"
> checks don't arrive, confirm WP Mail SMTP / the server mailer is enabled on
> staging (or just verify on live). Leads are logged regardless of email.

## Lead-gen — download gate (logged-out)
- [ ] In a private window, open a product page and click a datasheet/brochure link.
- [ ] Popup appears asking Name + Email + Customer type (or Sign in / Create account).
- [ ] Submitting opens the file AND the popup doesn't reappear on the next download
      (cookie `rm_dl_gate`, 30 days).
- [ ] A new entry appears in **Leads** (wp-admin) with Source "Download gate".
- [ ] (If email on) nothing is emailed for the plain gate — it just logs.

## Lead-gen — BIM request (logged-in or out)
- [ ] Product page → Downloads → "BIM / Revit — request".
- [ ] Popup asks Name + Email (+ Company); product is captured automatically.
- [ ] Requester receives a "thanks for your request" email.
- [ ] lightingdesign@ricoman.com receives the request (product + requester details).
- [ ] Lead logged with Type "BIM request" and the product name.

## Accounts
- [ ] Registration form (wp-login.php?action=register) shows First name, Last name,
      Customer type — and requires first name + type.
- [ ] After registering, the profile stores the customer type (Users → profile).
- [ ] Branded login screen (dark, logo, black button) shows.
- [ ] After login a normal customer lands on the homepage (not wp-admin).
- [ ] Logged-in: the download gate no longer appears; downloads open instantly.

## Add to Project — guest auth prompt (NEW behaviour)
- [ ] Logged OUT, click "Add to My Project" on a product → popup prompts Sign in /
      Create account (no more silent local list).
- [ ] After signing in you're returned to the product AND it's auto-added to your
      project (pending-add cookie). Check the header count.
- [ ] Guest My Project page shows a "Start a project / Sign in / Create account"
      prompt rather than an empty list.

## Add to Project — choose a project (NEW)
- [ ] With ONE project, "Add to My Project" adds straight away.
- [ ] With TWO+ projects, it opens a picker: choose a project or "＋ New project".
- [ ] Picking / creating adds the product to that project and updates the count.

## Project rename (NEW)
- [ ] On My Project, click the pencil next to the project name → the name field
      focuses; edit it → it saves (persists on reload).

## Flow+ Designer → Save to My Project (NEW)
- [ ] Open /flow-designer/, add parts, click "Save to My Project".
- [ ] Logged in: a "Flow+ run — <date>" custom line appears in your active project
      (My Project page), with qty + remove; tool shows "Saved ✓".
- [ ] Logged out: you're prompted to sign in; after signing in the design is added
      to your project automatically.
- [ ] The design appears in the project-pack ZIP as a "design-spec.txt" with the
      take-off summary.

## Saved projects (logged-in)
- [ ] Header shows "My Project" with a count, plus your name + Log out.
- [ ] On a product, "Add to My Project" → button shows "✓ Added to project" and the
      header count goes up.
- [ ] My Project page: the active project lists the product with a qty field + remove.
- [ ] Change qty → persists on reload. Remove → row goes.
- [ ] "＋ New project" → creates a second project; switch between them (tabs).
- [ ] Rename a project (edit the title) → persists.
- [ ] Delete a project → confirms and removes; a default remains.
- [ ] Guest (logged-out) still gets the simple local list + enquiry form.

## Project packs (logged-in)
- [ ] On a project with products, "Download project pack" downloads a ZIP.
- [ ] ZIP contains a "00 - Product list.txt" index (products, qty, links) and a
      folder per product with its datasheet/instructions/IES-LDT files.
- [ ] Products with no docs are noted in the index (no empty folder errors).
- [ ] (Needs server Zip extension — if it errors, enable PHP zip on the host.)

## Media cleanup (wp-admin → Ricoman → Media cleanup)
- [ ] Top stats: Images / Indexed / Duplicate groups / Removable.
- [ ] Step 3b "Permanently delete merged duplicates" shows the count in Trash.
- [ ] BACK UP first, then type DELETE and run — watch "~X GB freed" climb; it should
      be resumable and not time out.
- [ ] Re-run the Plesk backup afterwards — it should complete and be much smaller.

## General / launch
- [ ] Spot-check images load across product, category, news, project pages.
- [ ] Mobile menu: search box is a normal field (not a big white block).
- [ ] Mobile product gallery: image shows at a sensible height in All/Studio/In-situ.
- [ ] Products landing: no big gap under the hero.
- [ ] Single consolidated "Ricoman" admin menu (no duplicate).
- [ ] Friendly branded error page (only shows on a real fatal).

---

## Automated checks already run (by me)
- All key templates return HTTP 200 with no fatal (home, product, category,
  my-project, Estrella, Flow+).
- Estrella Pro product pages (previously 500) all return 200.
- Full sitemap crawl results: see notes appended below.

## Issues found & fixed (this session)
- **All Project case-study pages were 500** (34 of them) — `get_the_excerpt()`
  recursion in the project `the_content` filter. FIXED (raw excerpt). Re-crawl clean.
- **BIM request modal unstyled on mobile** — used `.rm-bim-form` which had no CSS.
  FIXED (shares gate-form styles + mobile rule).
- Sitemap crawl: 294 URLs checked (all pages, categories, news, projects + product
  sample). After fixes, the only issues were the project 500s, now resolved.

## Crawl status (after fixes)
- ✅ All 86 project pages: 200 (were all 500).
- ✅ Pages, products (247), product categories, application types, project
  categories, news: all clean.
- ✅ 404 page is branded (header/footer present).
- Legal pages present: /privacy-policy/, /cookie-policy/, /terms/, /contact/,
  /about/, /downloads/, /lighting-design/.
- ⚠️ `/trade/` 404 and `/terms-and-conditions/` 404 — confirm intended slugs / whether
  a Trade page is needed (the "Trade" template is still on the Step-1 checklist).

## Launch / SEO to decide (flagged, not changed)
- **Two SEO systems active**: Yoast SEO *and* the theme's SEO module. `robots.txt`
  has two blocks and references two sitemaps (`wp-sitemap.xml` + Yoast
  `sitemap_index.xml`). Pick one before launch to avoid conflicts.
- **Staging is indexable** (robots allows all, no `noindex`). Set staging to
  "Discourage search engines" so Google doesn't index the staging copy.
- **Missing meta descriptions** on Home / Products archive / News (products have
  them) — handle in the SEO pass (likely Yoast settings).
- **Email deliverability**: confirm SMTP so gate/BIM/lead emails actually send.
