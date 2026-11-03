# Ricoman Launch Runbook — Staging → Live

*Step 3 of the roadmap. Work top to bottom. Don't skip the pre-flight section —
most launch disasters are avoidable backups-and-redirects problems.*

> **Confirm before you start (hosting specifics):** how does staging become live?
> The three common routes on Plesk are:
> 1. **Same server, swap document root / domain** (staging is a subdomain or
>    subfolder of the same hosting account) — fastest, no data move.
> 2. **Export/import** — copy the database + `wp-content` from staging to the live
>    account.
> 3. **Clone/migration plugin** (e.g. All-in-One WP Migration / Plesk's WP Toolkit
>    clone).
>
> This runbook is written to be route-agnostic; where a step depends on the route,
> it says so. Pick the route first.

---

## A. Pre-flight (do these on STAGING, before touching live)

### Content & sign-off
- [ ] Every template in the Step 1 checklist is **signed off as pixel-matching**
      live (header/footer, home, product category, product singles, news, project
      singles, about, contact, feature pages, legal). See `CLAUDE.md`.
- [ ] **Ricoman → Page Designs → re-apply** has been run so all pages use the
      latest theme designs.
- [ ] **Ricoman → Page SEO** run (titles/descriptions seeded) and **footer link
      top-up** applied.
- [ ] **Ricoman → Category SEO** run (category intro/FAQ copy seeded).
- [ ] **Ricoman → SEO Audit** reviewed — no pages stuck below ~70% without reason;
      no important page accidentally set to **noindex**.
- [ ] Spot-check key pages on mobile and desktop.

### Media & backups
- [ ] **Take a full, verified backup of staging** (DB + files). Confirm it
      actually completed — backups were historically failing.
- [ ] **Media Cleanup** duplicate merge has been run.
- [ ] **Media Cleanup → permanent delete (Step 3b)** — run this **only after** a
      confirmed backup, to reclaim the 248 GB. (Gated by typing DELETE.)
- [ ] Confirm backups now complete cleanly and at a sane size.

### Technical
- [ ] **RICOBOT is still parked** (`ricoman_use_product_api` = false) — product
      pages render from the migrated ACF data. Do **not** flip this on at launch;
      it's roadmap step 5.
- [ ] No "Discourage search engines" setting left on for the live site (Settings →
      Reading) — but keep it **ON for staging** so staging never gets indexed.
- [ ] `robots.txt` doesn't block the live site; XML sitemap resolves
      (`/wp-sitemap.xml`).
- [ ] All forms tested (enquiry, download gate, BIM request, newsletter, callback)
      and leads arrive in **Ricoman → Leads** (and the Sheets/CRM sync if used).
- [ ] Tracking/analytics (GA4/GTM) configured in **Ricoman → Tracking** and ready
      to point at the live property.
- [ ] SSL certificate is valid for the live domain.

---

## B. Redirects — protect the SEO you already have

The single biggest launch risk is losing rankings because old URLs 404. Before
launch:

- [ ] Export the **current live site's URL list** (from Google Search Console
      "Pages", an existing sitemap, or a crawl with Screaming Frog).
- [ ] Compare against the new site's URLs. For every old URL whose path **changed**,
      add a **301 redirect** to the new equivalent in **Ricoman → Links &
      Redirects**.
- [ ] Pay special attention to: product URLs, product categories, news article
      slugs, project slugs, and any old Elementor/landing page paths.
- [ ] Leave the **404 watch** (in Links & Redirects) on after launch to catch any
      misses.

---

## C. Go-live

> Time this for a low-traffic window. Tell the team it's happening.

**If route 1 (swap on same server):**
1. [ ] Final content freeze on staging.
2. [ ] Point the live domain's document root at the new site (or rename
       staging → primary domain in Plesk / WP Toolkit).
3. [ ] Update **WordPress Address & Site Address** (Settings → General) to the
       live domain, or run a search-replace of the staging URL → live URL across
       the database (use WP-CLI `wp search-replace` or a migration tool — this
       handles serialized data safely; a raw SQL find/replace does not).

**If route 2/3 (export/import or clone):**
1. [ ] Final content freeze + fresh staging backup.
2. [ ] Import DB + `wp-content` into the live account.
3. [ ] **Search-replace staging URL → live URL** (`wp search-replace` or the
       migration tool's built-in step). Don't forget `https://`.
4. [ ] Set correct file permissions and confirm `wp-config.php` points at the live
       database.

**Both routes:**
5. [ ] Turn **OFF** "Discourage search engines" on the live site (Settings →
       Reading) — this is the step everyone forgets.
6. [ ] Flush permalinks (Settings → Permalinks → Save) and any caching.
7. [ ] Confirm SSL + force HTTPS.

---

## D. Post-launch verification (first 30 minutes)

- [ ] Home, a product, a product category, a news article, a project, contact —
      all load correctly over **https://** on the live domain.
- [ ] Header mega-menu, footer links, and search all work.
- [ ] Submit a **test lead** through a form → confirm it lands in Ricoman → Leads.
- [ ] Test a **document download** + the download gate.
- [ ] Check 5–10 of the **301 redirects** from section B actually redirect.
- [ ] `view-source` on a page: confirm `<title>`, meta description, canonical and
      JSON-LD are present and reference the **live** domain (not staging).
- [ ] No mixed-content warnings (http assets on an https page).
- [ ] Mobile check on a real phone.

---

## E. Post-launch (first 24–48 hours)

- [ ] **Google Search Console:** add/verify the live property, **submit the XML
      sitemap**, and request indexing of the home + top pages.
- [ ] Point **analytics/GTM** at the live property and confirm hits are recording.
- [ ] Watch the **404 watch** (Links & Redirects) and add redirects for anything
      that slipped through.
- [ ] Take a **fresh backup of the now-live site**.
- [ ] Monitor leads inbox + form deliverability for the first day.

---

## F. Rollback plan

If something is badly wrong and can't be fixed quickly:

- [ ] Route 1: point the document root / domain back to the previous site.
- [ ] Route 2/3: restore the pre-launch backup of the live account.
- [ ] Because you took a **verified backup** in section A and D, rollback is a
      restore — not a rebuild. This is exactly why the backup step is non-negotiable.

---

## Later (NOT part of launch)

- **Roadmap step 5 — reconnect RICOBOT:** once live and stable, flip
  `ricoman_use_product_api` to true to re-enable live product data. Do this as a
  deliberate, separate change with its own testing — never on launch day.
