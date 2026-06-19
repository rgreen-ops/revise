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
— every link, image, page, template. Method:
- Build a list of every page/template.
- Work through them one by one.
- Richard signs off each **template** (e.g. news master, news article, product
  page, product category) only when it's confirmed pixel-for-pixel.

## Key facts established
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

## Constraints
- Develop/push only to the branch above; never create PRs unless asked.
- Content must persist across theme updates (installer is create-once).
