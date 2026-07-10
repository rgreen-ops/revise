# Skyline — Football Club HTML Template

A premium, responsive HTML + Tailwind CSS template for football clubs,
academies and grassroots teams. Ships with realistic demo content for a
fictional community club — fixtures, results, a league table, squad
profiles with stats, match reports and academy trial registration — so
buyers see a real club website, not lorem ipsum.

**No build step is required to use it** — the compiled CSS is included.
You only need Node.js if you want to change the club colours or fonts.

---

## What's included

| File | Page |
|---|---|
| `index.html` | Home — hero with live kick-off countdown, match centre, news, table, teams, sponsors |
| `club.html` | Club — history, honours, the ground, committee |
| `fixtures.html` | Match centre — fixtures / results / league table in accessible tabs |
| `squad.html` | Squad grid with position filter and player stat cards |
| `player-single.html` | Player profile — stats, season-by-season record, sponsor CTA |
| `news.html` | News listing with featured post and newsletter signup |
| `news-single.html` | Match report layout with scoreboard strip and teams list |
| `contact.html` | Contact cards, enquiry form, matchday directions, clubhouse hire |
| `academy.html` | Academy — age groups, trials registration form, parents' FAQ |

Plus:

- `assets/css/main.css` — compiled, minified stylesheet (~29 KB)
- `assets/js/main.js` — dependency-free vanilla JS (nav, countdown, tabs, filter, accordion, forms)
- `assets/img/` — SVG demo imagery (replace with your match photography)
- `src/input.css` — theme source with all club-colour variables
- `tailwind.config.js`, `package.json` — rebuild tooling

## Quick start

1. Unzip anywhere.
2. Open `index.html` in a browser. That's it — no server needed.
3. To publish, upload the folder (minus `src/`, `node_modules/`,
   `package.json`, `tailwind.config.js` if you like) to any static host:
   Netlify, Vercel, GitHub Pages, cPanel…

## Your club's colours

This edition ships in **sky blue & gold** end to end — dark sections,
buttons, tables, form badges and the club crest all match out of the box.

For exact club colours, every colour and font resolves to CSS variables
declared at the top of `src/input.css`. Change those values, rebuild, and
the whole site is in your colours — **you never edit HTML to rebrand**.

```css
:root {
  --tc-primary: 96 22 40;      /* claret  — dark sections, headings */
  --tc-accent: 125 211 252;    /* sky     — buttons, highlights     */
  --tc-win: 22 163 74;         /* W/D/L badge colours               */
  --tc-font-display: "Khand", ...;
  --tc-font-body: "Inter", ...;
}
```

Colour values are space-separated RGB channels (`96 22 40` = `#601628`)
so Tailwind's opacity modifiers keep working.

Rebuild after editing:

```bash
npm install        # once
npm run build      # writes assets/css/main.css
npm run watch      # optional: rebuild on save while you work
```

To change fonts, also update the Google Fonts `<link>` in the `<head>`
of each page, or remove it to use the system-font fallbacks.

## Editing content

- **Club name / crest** — the crest is an inline SVG in the header and
  footer of every page; swap it for your own. Find-and-replace
  `Easthaven Rovers` across the HTML files for the name.
- **Kick-off countdown** — set the date on the hero's
  `data-countdown="2026-08-08T15:00:00"` attribute. Past kick-off it
  shows the message in `data-countdown-done`.
- **League table** — plain HTML table. Mark your own club's row with the
  `data-us` attribute to highlight it.
- **Fixtures & results** — each match is one self-contained `<li>` card;
  copy, paste, edit. W/D/L badges use the `.form-badge` classes.
- **Images** — demo images are SVGs in `assets/img/`. Replace with your
  photos (keep `width`/`height` attributes to avoid layout shift).
- **Favicon** — `assets/img/favicon.svg`.

## Forms

The trials, contact and newsletter forms use a demo handler
(`data-demo-form` in `assets/js/main.js`) that validates input and shows
the success message without sending anything. To go live, point each
`<form>` at your endpoint — Formspree, Netlify Forms, or your club's
system — and remove the `data-demo-form` attribute.

## JavaScript behaviours

All optional, all vanilla, all in `assets/js/main.js`:

- Mobile navigation (Escape to close, `aria-expanded` managed)
- Kick-off countdown (days/hours/minutes, updates every 30s)
- Accessible tabs with arrow-key support (fixtures page)
- Squad position filter
- One-open accordion built on native `<details>` (academy FAQ)
- Scroll-reveal animations (fully disabled under `prefers-reduced-motion`)
- Sticky header shadow, footer year

## Accessibility

Built to WCAG 2.1 AA: semantic landmarks, skip link, visible focus
states, labelled form controls, `aria-current` navigation,
keyboard-operable tabs and accordion, table captions for screen readers,
AA-checked colour contrast, and reduced-motion support.

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
- [Khand](https://fonts.google.com/specimen/Khand) &
  [Inter](https://fonts.google.com/specimen/Inter) via Google Fonts (OFL)
- Demo photographs from [Unsplash](https://unsplash.com/license) (free
  for commercial use, no attribution required). They are placeholders —
  replace them with your club's own consented photography before launch,
  especially anything showing junior players.
- All demo copy, club names, people and badges are fictional.

Licence per the marketplace you purchased from (single-site licence
unless stated otherwise). Thanks for buying Skyline — up the Rovers.
