# Ricoman — WordPress Block Theme

A modern **block (Full Site Editing) theme** for **Ricoman**, a UK manufacturer of
commercial **interior** LED lighting. It's written for Ricoman's audience —
**architects, interior designers, design & build teams and electrical contractors** —
with a clean editorial layout, the brand red, and a warm "light" amber accent.

Everything is editable visually in **Appearance → Editor** (the Site Editor); no
PHP changes are needed for day-to-day content.

## Requirements

- WordPress **6.5+**
- PHP **7.4+**

## Installation

1. Copy the `ricoman` folder into `wp-content/themes/` (or zip the folder and
   upload it via **Appearance → Themes → Add New → Upload Theme**).
2. Activate **Ricoman** under **Appearance → Themes**.
3. (Recommended) Set the homepage: **Settings → Reading → Your homepage displays
   → A static page**, and pick a page. The bundled `front-page.html` template will
   render the marketing homepage automatically.
4. Build your menu and add a logo in **Appearance → Editor → Navigation / Styles**.

## What's included

### Templates (`/templates`)
| Template | Used for |
|---|---|
| `front-page.html` | Marketing homepage (hero → stats → products → audience → why → CTA) |
| `single-product.html` | **Full product page** (default): large image, specs, variants/ordering, body content, CTA |
| `single-product-simple.html` | **Simple product page** (custom template): just basic info, an excerpt, actions and the specs table — low on images and extra sections |
| `index.html` | Blog listing / fallback |
| `single.html` | Single post (with comments + prev/next) |
| `page.html` | Standard page |
| `page-no-title.html` | Page with no title (custom template) |
| `page-wide.html` | Full-width page (custom template) |
| `archive.html` | Category / tag / date archives |
| `search.html` | Search results |
| `404.html` | Not-found page |

> **Choosing a product layout:** every product uses the **Full product page** by
> default. To make a given product simple, open it in the editor and, in the
> **Post → Template** panel on the right, switch its template to **"Product,
> Simple"**. Switch back to the default any time — it's per-product.

### Template parts (`/parts`)
- `header.html` — sticky header with logo, navigation and a "Get a quote" button.
- `footer.html` — dark footer with product/company link columns and socials.

### Patterns (`/patterns`) — find these under the **Ricoman** category in the inserter
- `hero` — dark hero with brand glow and CTAs.
- `stats` — headline figures band (delivery, stock, warranty, sectors).
- `product-categories` — six interior product ranges as cards.
- `audience` — cards for architects, interior designers, design & build, contractors.
- `features` — "why specify Ricoman" benefit grid.
- `cta` — brand-coloured call-to-action band.

### Built-in features (the project scope)

This theme ships the structural pieces of the rebuild scope so the site works
without a pile of extra plugins:

| Scope item | Where it lives | What you get |
|---|---|---|
| **Editable product / project / page templates** | `inc/post-types.php`, `templates/single-product.html`, `archive-product.html`, `single-project.html`, `archive-project.html` | `Product` and `Project` custom post types with block templates you edit visually. Taxonomies: Product Categories + Applications. |
| **Product data / variant structure** | `inc/meta.php` | A "Product details & variants" panel on each product: SKU, wattage, lumens, CCT, CRI, IP, beam, dimensions, warranty + a variant table (SKU \| Description \| Watts \| Lumens \| CCT). Exposed in the REST API. |
| **Dynamic PDF datasheet generation** | `inc/datasheet.php` | Every product has a print-ready A4 datasheet at `…/products/slug/?datasheet=1` (specs, variants, image, branding). "Save as PDF" in any browser. If [Dompdf](https://github.com/dompdf/dompdf) is autoloaded, it streams a real downloadable PDF instead. |
| **Lead capture + Google Sheets automation** | `inc/lead-capture.php` | `[ricoman_lead_form]` shortcode / "Lead capture section" pattern. Submissions are validated (nonce + honeypot), saved as `Lead` posts, emailed to the admin, and POSTed to a Google Sheets webhook. |
| **SEO / GEO: schema + breadcrumbs** | `inc/seo.php` | JSON-LD for Organization, WebSite, **Product** (with spec PropertyValues) and **BreadcrumbList**, plus a `[ricoman_breadcrumbs]` shortcode used across product/project templates. |

#### My Project / Toolbox (`inc/my-project.php`, `assets/js/my-project.js`)
A specification list, like Unios's Toolbox / your site's "Include in My Project":
- **`[ricoman_add_to_project]`** — an "Add to My Project" button (already on the
  product template). Saves the product to a browser-stored list.
- **`[ricoman_project_count]`** — a live "My Project (n)" badge (already in the header).
- **`[ricoman_my_project]`** — the list itself: review items, change quantities,
  remove/clear, then submit as an enquiry.

To set it up, create a **Page** with the slug `my-project` and either insert the
**"My Project page"** pattern or just the `[ricoman_my_project]` shortcode.
Submissions arrive as **Leads** (with the full item list attached) and flow through
the same Google Sheets webhook as other enquiries. No login required — the list
lives in the visitor's browser until they send it.

#### Product display shortcodes (`inc/shortcodes.php`)
Drop these into any template/page via the **Shortcode block**:
- `[ricoman_product_specs]` — specification table
- `[ricoman_product_variants]` — variant/ordering table
- `[ricoman_datasheet_button]` — "Download datasheet (PDF)" button
- `[ricoman_lead_form]` — the enquiry form
- `[ricoman_breadcrumbs]` — breadcrumb trail

#### Enabling the Google Sheets sync
1. In a Google Sheet, **Extensions → Apps Script**, paste a handler that appends
   `JSON.parse(e.postData.contents)` as a row, and **Deploy → Web App**
   (execute as you, access "Anyone").
2. Copy the Web App URL into **Settings → General → "Leads webhook URL"**, or
   add `define( 'RICOMAN_SHEETS_WEBHOOK', '…/exec' );` to `wp-config.php`.

Leads are also always viewable under **Leads** in wp-admin, regardless of the sync.

#### Real server-side PDFs (optional)
The datasheet is print-to-PDF ready out of the box. For automatic downloadable
PDFs, install Dompdf so it's autoloaded (e.g. via a small mu-plugin or
`composer require dompdf/dompdf`); `inc/datasheet.php` detects it automatically.

### Custom block styles
- **Group → Card** — bordered, rounded card with hover lift.
- **Button → Pill** — fully rounded button.
- **Button → Outline (light)** — transparent outline button for dark sections.

## Brand tokens (edit in `theme.json` or Site Editor → Styles)

| Token | Value | Use |
|---|---|---|
| Brand Red | `#d81f26` | Primary actions, accents |
| Brand Red Dark | `#a3151b` | Hover/active |
| Light Amber | `#ffb400` | "Light" accent, eyebrows |
| Ink | `#0e1726` | Headings, dark sections |
| Surface | `#f5f7fa` | Section backgrounds |

Fonts: **Inter** (body) and **Inter Tight** (headings), loaded from Google Fonts.

## Customising

- **Colours & type:** Site Editor → **Styles**, or edit `theme.json`.
- **Homepage sections:** edit `templates/front-page.html`, or open the homepage in
  the Site Editor and add/remove Ricoman patterns.
- **Replace placeholder content:** product links, phone (`tel:`) and email
  (`mailto:`) in `patterns/cta.php` and `parts/footer.html` are placeholders —
  update them with real details.

## Notes

This theme ships content/copy tailored to interior commercial lighting; swap in
real product photography (featured images) and project case studies as posts.
