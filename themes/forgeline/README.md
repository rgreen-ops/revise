# Forgeline — Industrial & Trade Supplier HTML Template

A premium, responsive HTML + Tailwind CSS template for industrial and trade
businesses: electrical wholesalers, lighting suppliers, manufacturers and
engineering firms. Ships with realistic demo content for a fictional UK
commercial lighting distributor so buyers see their own world, not
lorem ipsum.

**No build step is required to use it** — the compiled CSS is included.
You only need Node.js if you want to change the brand colours or fonts.

---

## What's included

| File | Page |
|---|---|
| `index.html` | Home — hero, categories, featured products, services, case study, blog, CTA |
| `about.html` | About — story, values, timeline, team |
| `products.html` | Product grid with category filter and pagination |
| `product-single.html` | Single product — gallery, spec tables, downloads tab, quote form |
| `services.html` | Services grid and four-step process |
| `blog.html` | Blog listing with featured post and newsletter signup |
| `blog-single.html` | Article layout with author box and related posts |
| `contact.html` | Contact cards, trade enquiry form, map placeholder, FAQ accordion |

Plus:

- `assets/css/main.css` — compiled, minified stylesheet (~26 KB)
- `assets/js/main.js` — dependency-free vanilla JS (nav, tabs, accordion, filter, gallery, forms)
- `assets/img/` — SVG demo imagery (replace with your photography)
- `src/input.css` — theme source with all brand variables
- `tailwind.config.js`, `package.json` — rebuild tooling

## Quick start

1. Unzip anywhere.
2. Open `index.html` in a browser. That's it — no server needed.
3. To publish, upload the folder (minus `src/`, `node_modules/`,
   `package.json`, `tailwind.config.js` if you like) to any static host:
   Netlify, Vercel, GitHub Pages, cPanel, S3…

## Rebranding — colours & fonts

Every colour and font in the theme resolves to CSS variables declared at the
top of `src/input.css`. Change those values, rebuild, and the whole site
updates — **you never edit HTML to rebrand**.

```css
:root {
  --fl-primary: 16 24 40;   /* deep navy   — headings, dark sections   */
  --fl-accent: 245 158 11;  /* amber       — buttons, highlights       */
  --fl-font-display: "Archivo", ...;
  --fl-font-body: "Inter", ...;
  --fl-radius: 0.625rem;    /* corner rounding across cards & buttons  */
}
```

Colour values are space-separated RGB channels (`16 24 40` = `#101828`) so
Tailwind's opacity modifiers keep working.

Rebuild after editing:

```bash
npm install        # once
npm run build      # writes assets/css/main.css
npm run watch      # optional: rebuild on save while you work
```

To change fonts, also update the Google Fonts `<link>` in the `<head>` of
each page (a find-and-replace across the eight HTML files takes seconds),
or remove it entirely to use the system-font fallbacks.

## Editing content

- **Company name / contact details** — appear in the top bar, header and
  footer of every page. Find-and-replace `Calder Industrial`,
  `0113 496 0000` and `sales@calderindustrial.co.uk` across all HTML files.
- **Logo** — the logo is an inline SVG in the header and footer of each
  page; swap it for your own `<img>` or SVG mark.
- **Images** — demo images are SVGs in `assets/img/`. Replace them with
  your photos (keep the same filenames, or update the `src` attributes).
  Keep `width`/`height` attributes to avoid layout shift.
- **Favicon** — `assets/img/favicon.svg`.

## Forms

The enquiry, quote and newsletter forms use a demo handler
(`data-demo-form` in `assets/js/main.js`) that validates input and shows
the success message without sending anything. To go live, point each
`<form>` at your endpoint — Formspree, Netlify Forms, Basin, or your own
CRM — and remove the `data-demo-form` attribute.

## JavaScript behaviours

All optional, all vanilla, all in `assets/js/main.js`:

- Mobile navigation (Escape to close, `aria-expanded` managed)
- Sticky header shadow on scroll
- Scroll-reveal animations (fully disabled under `prefers-reduced-motion`)
- Accessible tabs with arrow-key support (product page)
- One-open accordion built on native `<details>` (contact FAQ)
- Client-side category filter demo (products page)
- Product image gallery thumbnails

## Accessibility

Built to WCAG 2.1 AA: semantic landmarks, skip link, visible focus states,
labelled form controls, `aria-current` navigation, keyboard-operable tabs
and accordion, AA-checked colour contrast, and reduced-motion support.

## Mobile & iPhone

Tested at iPhone viewports (390/375px): no horizontal scroll on any
page, hamburger navigation with Escape-to-close, 16px form inputs (so
iOS Safari never zoom-jumps on focus), and an `apple-touch-icon` for
home-screen bookmarks.

## Browser support

All modern evergreen browsers (Chrome, Edge, Firefox, Safari — last two
versions). No IE11.

## Credits & licence

- [Tailwind CSS](https://tailwindcss.com) (MIT) — build-time only
- [Archivo](https://fonts.google.com/specimen/Archivo) &
  [Inter](https://fonts.google.com/specimen/Inter) via Google Fonts (OFL)
- Demo photographs from [Unsplash](https://unsplash.com/license) (free
  for commercial use, no attribution required); product images are
  original SVG technical renders included with the theme.
- All demo copy, company names and people are fictional.

Licence per the marketplace you purchased from (single-site licence unless
stated otherwise). Thanks for buying Forgeline — build something solid.
