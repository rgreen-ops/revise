# Ricoman site — working backlog (Richard's requests)

_Running log so nothing gets lost. ✅ done · 🔧 in progress · ⏳ needs server/user action · ☐ to do_

## Open
- ☐ **Image count discrepancy** — Media Library shows ~42,526 items but the cleanup tool counts 6,596 / 0 duplicates. Repeated "‑N.webp" filenames suggest a content/import process re‑uploading the same images; the hash tool isn't seeing them. Investigate source + extend cleanup to catch them.
- ☐ **Downloads page wrong** — shows products by category instead of a document library (Brochures / Data Sheets / Instructions / LDT, like live ricoman.com/downloads). Rebuild `ricoman_downloads_blocks()` (downloads-grid).
- ☐ **Broken-links banner not auto-clearing** — the 404 resolver redirects them, but the log/banner stays at 35. Make the log auto-prune resolved entries.
- ☐ **Homepage projects tiles** — titles wrap letter-by-letter ("Alli an z"). Fix project-tile title CSS.
- ☐ **Contact page layout** — messy: big gaps, run-together text (e.g. "Monday8:30"), no proper two-column. Redesign/fix.
- ☐ **Reorder products & categories** — simpler back-end UX (see "How to" below); consider a drag-drop tool.

## Done this session
- ✅ **About page** — redesigned as a specifier-focused "designer" layout (needs Page Designs → re-apply to push onto the live About page).
- ✅ Header on projects archive (was white-on-white) → solid dark header.
- ✅ Global zoom to 85%.
- ✅ Giant product CTA image; gallery shows whole product (contain); white-bg images blend; grey overlay removed then #f7f7f7 panel restored; #e4e4e4 image frame restored.
- ✅ Mega menu (2-col categories, no blank gap, Flow Designer, redundant labels removed).
- ✅ Variant images (staging host, no -1024x1024) + broken-thumb fallback; Estrella "Link variants" tool; variant table 10 + show-more, filterable.
- ✅ "Downloads" heading removed from product hero.
- ✅ Old-URL 404 resolver + content link rewriter.
- ✅ SEO step 2 (titles/desc/focus seed, category copy, footer links, audit) + WebP build (disk-guarded).
- ✅ Mobile hero-video skip (homepage 2.8MB → 933KB); content-video preload off.
- ✅ Server: disk rescued (backups 248GB → 69GB), backups capped, PHP-FPM restarted, PHP 256M/120s.

## Pending verification (server)
- ⏳ Run **Media Cleanup → Step 3b** (disk now has room) so WebP can generate; then re-check.
- ⏳ Re-run **Lighthouse** across pages once page-cache + WebP settle; update the deck.

## How to (answers given)
- **Reorder categories:** Products → Categories → edit a category → "Display order" (controls mega menu, /products/ tiles, homepage grid).
- **Reorder products in a category:** the product's native "Order" attribute.
- **Edit any page (About/feature pages):** Pages → [page] → block editor (all editable blocks). Patterns live under ＋ → Patterns → "Ricoman — Page". The layout *generator* is theme code: `inc/page-patterns.php` (`$feature`). Push a refreshed design with Ricoman → Page Designs → re-apply.
