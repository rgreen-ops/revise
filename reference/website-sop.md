# Ricoman website — Standard Operating Procedures (SOP)

How to do everything on the site. Plain steps for the marketing team. Each
function has a **[SCREENSHOT: …]** slot — drop in a screenshot of that screen when
you move this into Google Docs.

> Golden rules
> - **Content** (text, images, products, news) → edit on the **live** site, using
>   **Draft → Preview → Publish**. Nothing is public until you Publish.
> - **Features / templates** → built on staging by Claude, then **🚀 Push to Live**.
> - You never need Plesk. If something looks stale, Ricoman → Go Live → "Run launch
>   finalisation" clears caches.

---

## 1. Editing a page
1. Pages → find the page → Edit.
2. Change text/images in the block editor.
3. **Preview** (top right) to check. **Update/Publish** when happy.
[SCREENSHOT: a page open in the block editor with the Preview button]

## 2. Adding a section (pattern) to a page
1. In the editor, click the **＋** where you want it.
2. **Patterns** tab → category **"Ricoman — Page"** → pick one (e.g. Home ·
   Testimonials, Team · Grid, Feature · Casambi).
3. Edit the placeholder text/images. Update.
[SCREENSHOT: the ＋ inserter showing the Ricoman — Page patterns]

## 3. Products — the Product Page Builder
1. Products → All Products → open a product → **Open Product Page Editor**
   (or Ricoman → Product Page Editor).
2. Left = edit, right = live preview. Click a section to edit it.
3. **Save**.
[SCREENSHOT: Product Page Editor, edit-left / preview-right]

### 3a. Configure table — which columns + filters
- In the editor, the **Configure & order codes** panel: tick which spec **columns**
  show; the **eye/filter** toggle turns each column's **filter drop‑down** on/off
  (the data still shows). "Apply to all products" sets the default for every product.
[SCREENSHOT: Configure & order codes panel with column ticks + filter toggles]

### 3b. Colour finishes (the chips on the image)
- On the product edit screen, **Colour finishes (image chips)** box: each row =
  name + swatch + main image. ↑↓ to reorder, ✕ to remove, ＋ Add finish.
[SCREENSHOT: Colour finishes box with two rows]

### 3c. Product FAQs
- **Product FAQs** box: one Q/A per pair — `Q: …` then `A: …`. Shows as an FAQ
  section + adds FAQ schema for Google.
[SCREENSHOT: Product FAQs box]

### 3d. Variants & cut‑out (CSV)
- Variant Products → Import/Export. Export, fill columns (incl. `cut_out` in mm),
  re‑import. Rows with an `id` update; blank `id` creates.
[SCREENSHOT: Variant CSV import screen]

### 3e. Configure families (one table across a range, e.g. Estrella)
- Products → Configure Families → add a family (e.g. "Estrella") → assign the
  member products. Their Configure table then spans the range with a **Type** filter.
[SCREENSHOT: Configure Families term + a product assigned]

## 4. Categories
- Products → Categories → open a term:
  - **Category images** (In‑situ + Studio), **Display order**, **SEO copy**
    (intro + body/FAQ). Update.
- One‑click starter SEO: Ricoman → Category SEO.
[SCREENSHOT: Category edit screen — images + SEO copy]

## 5. News
1. News → Add New. Title + content + Featured image.
2. **Article content & conversion** box: standfirst, key takeaways, products,
   **Byline** (Writer / role / contributor), CTA.
3. Topics auto‑tag; set/adjust if needed. Publish.
[SCREENSHOT: News editor with the byline + content box]

## 6. Projects
1. Projects → Add New. Title, content, gallery, banner.
2. Tag the **Sector** and the **Consultant (SPC)**; tick the **Ricoman staff** involved.
3. Publish. (Project shows the consultant + people credits.)
[SCREENSHOT: Project editor with Sector / Consultant / Staff boxes]

## 7. Team / staff profiles
1. Team → Add staff member. Name (title) + photo (Featured image) + **Profile
   details** (job title, email, phone) + bio (editor — build a full profile here).
2. Tag them on news/projects (step 5/6). Their name/photo links to `/team/<name>/`,
   which lists all their content.
3. Drop on a page with `[ricoman_staff id="ID" fields="photo,name,title,email"]`
   or the whole team with `[ricoman_team]`.
[SCREENSHOT: a staff profile page]

## 8. Downloads, BIM & leads
- Products carry datasheet / instructions / IES‑LDT; BIM is "request" (logs a lead).
- Logged‑out visitors hit the **download gate** (name/email/type) → becomes a lead.
- **Ricoman → Leads** = every captured lead (type, email, source, status).
[SCREENSHOT: Ricoman → Leads list]

## 9. SEO tools
- Ricoman → **Page SEO** / **Category SEO** — seed/refresh titles, descriptions,
  intro/FAQ copy (fills blanks, never overwrites your edits).
- Per‑page SEO box on each page/post for manual title/description.
[SCREENSHOT: Page SEO admin screen]

## 10. Links & redirects (301s)
- Ricoman → **Links & Redirects**: add old‑URL → new‑URL redirects; the **404 log**
  shows misses to fix.
[SCREENSHOT: Links & Redirects screen]

## 11. Media cleanup & images
- Ricoman → **Media Cleanup** (de‑duplicate), **Image Alt Text** (backfill alt),
  **Studio/In‑situ sorter** (classify gallery images).
[SCREENSHOT: Media Cleanup screen]

## 12. Publishing changes
### Content → live now
- Edit on live → Preview → Publish. Done.
### Features/templates → via staging
- Claude builds on staging → you approve → **Ricoman → 🚀 Push to Live** → tick the
  two boxes, type **PUBLISH**, confirm.
[SCREENSHOT: Push to Live sign‑off screen]

## 13. If something looks stale or wrong
- Ricoman → **🚀 Go Live → Run launch finalisation** clears caches + refreshes links.
- Still wrong → tell Claude exactly what you see. Code changes can be rolled back;
  content changes are protected by Draft/Preview.
