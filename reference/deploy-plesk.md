# Go-live plan — re-theming ricoman.com on Plesk (same WordPress)

The new **Ricoman** block theme goes on the **existing** ricoman.com WordPress.
The content (pages, posts, products, media) is already in the database, so this
is a **re-theme**, not a data migration. Do it on a **staging clone**, never on
live.

## 1. Clone live → staging (Plesk)
1. Plesk → **WordPress Toolkit** → select ricoman.com → **Clone**.
2. Target a subdomain, e.g. `staging.ricoman.com` (Toolkit copies the files +
   DB and runs the URL search-replace automatically).
3. Confirm staging loads and is **noindexed**: Settings → Reading →
   "Discourage search engines" (the theme's robots output respects this).

## 2. Install + activate the theme on staging
1. Upload `ricoman.zip` via Appearance → Themes → Add New → Upload, **or** drop
   the `ricoman/` folder in `wp-content/themes/` (Git deploy is fine too).
2. **Activate** Ricoman.
3. Appearance → Editor → set the **Navigation** (block themes use a Navigation
   block, not classic menus — rebuild the menu here once).
4. Settings → Reading → set the **homepage** to your front page.

## 3. Re-point content to the new templates
Because the content already exists, most pages "just work". Then:
- For key pages, open each and set **Page → Template** (Landing, With Sidebar,
  Contact, Blank Canvas, etc.).
- Products use **Full** or **Product, Simple**; Projects the same.
- Fill the **SEO box** (or leave it — descriptions auto-generate) and a focus
  keyphrase; watch the live score.

### Page-builder pages (Elementor / WPBakery / Divi / etc.)
If pages were built with a page builder, their content is stored as builder
markup. Two options:
- **Keep the builder plugin active** — those pages keep rendering via the
  builder (they won't use the new block design, but they won't break), and you
  convert them over time; **or**
- **Convert to blocks** with the Content Transporter's **REST mode** pointed at
  *this same site*: it pulls each page's server-rendered HTML and rewrites it as
  clean blocks, sending leftover builder wrappers to the "Unsorted" box. Then
  you remove the builder plugin once nothing depends on it.

## 4. Settings to set once
- **Settings → Ricoman SEO**: logo, default share image, social profiles,
  address, phone, founding year (1999), **Allow AI crawlers** (on), and a
  PageSpeed API key if you have one.
- **Regenerate thumbnails** (any "Regenerate Thumbnails" plugin) so existing
  media gets the new AVIF/WebP sub-sizes + responsive crops.
- Flush permalinks (Settings → Permalinks → Save).

## 5. Performance (server side — the biggest remaining speed wins)
On the VPS/Plesk:
- PHP **8.2/8.3** + **OPcache** on.
- **Redis object cache** (Plesk → WordPress Toolkit, or the Redis Object Cache
  plugin).
- **Full-page caching** (Plesk supports nginx caching; or a cache plugin).
- **HTTP/3 + Brotli** (Plesk → Apache & nginx settings / domain settings).
- A **CDN** for static assets.
- Long-lived cache headers on `wp-content/uploads` and theme assets.

## 6. Verify, then go live
1. Tools → **SEO & Speed** → run PageSpeed (works on the public staging URL).
2. Check internal links, nav, forms (lead capture / My Project), datasheets.
3. When happy: Plesk **WordPress Toolkit → Copy Data / Push to production**
   (staging → live), or switch the domain to the staged copy.
4. On production: re-enable search indexing, submit the sitemap
   (`/wp-sitemap.xml`) + `/llms.txt` in Search Console.

## Notes
- Always work on staging; only push to live once verified.
- Keep a Plesk backup/snapshot before the production switch.
