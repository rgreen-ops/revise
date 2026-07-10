# Sideline — Grassroots Junior Football Club HTML Template

A premium, responsive HTML + Tailwind CSS template built specifically for
**junior grassroots clubs** — the ones with 20+ teams across U6 to U16,
multiple squads per age group, boys' and girls' pathways, and no senior
team. The site is organised the way those clubs actually work: around a
teams directory, registration, volunteers and safeguarding — not around a
"first team" the club doesn't have.

**No build step is required to use it** — the compiled CSS is included.
You only need Node.js if you want to change the club colours or fonts.

---

## What's included

| File | Page |
|---|---|
| `index.html` | Home — hero, age-group finder, club promises, pathways, news, volunteer band |
| `teams.html` | **Teams directory** — 24 team cards with boys/girls/mixed filter, training times, coach and space availability |
| `team-single.html` | Single team page — training, matchday, coaches, what to bring, safeguarding-aware |
| `join.html` | Registration — three-step joining, fees table, hardship fund, form, parents' FAQ |
| `volunteers.html` | Volunteer recruitment — six role cards with time commitment and funded training |
| `news.html` | News listing with featured post and newsletter signup |
| `news-single.html` | Article layout (registration announcement) |
| `contact.html` | Safeguarding-first contact page, two venues, committee, enquiry form |

Plus:

- `assets/css/main.css` — compiled, minified stylesheet
- `assets/js/main.js` — dependency-free vanilla JS (nav, team filter, accordion, forms, reveal)
- `assets/img/` — demo photography (see credits below)
- `src/input.css` — theme source with all club-colour variables
- `tailwind.config.js`, `package.json` — rebuild tooling

## Built for multi-team junior clubs

The parts generic sports themes get wrong, done right here:

- **Team cards, not player pages.** Each of the 24 demo teams shows age
  group, pathway (boys / girls / mixed), league and format (4v4 → 11v11),
  training slot, coach and live space availability ("spaces" / "waiting
  list"). Copy a card, edit six lines, done.
- **Pathway filter.** The teams grid filters by Boys / Girls / Mixed with
  a live count — parents find the right squad in one tap.
- **Girls' football is first-class**, not an afterthought: its own chip
  colour, its own league in the demo content, Wildcats sessions in the
  news.
- **Safeguarding is structural.** Welfare contact is the first block on
  the contact page, junior players are never named, the team page models
  photo-consent language, and coach contact goes via the club office.
- **Volunteer recruitment page** — because the real bottleneck at every
  junior club is coaches, not players.

## Your club's colours

Every colour and font resolves to CSS variables at the top of
`src/input.css` — including the boys/girls/mixed chip colours. Change the
values, rebuild, and the whole site (badge included) is in your colours.

```css
:root {
  --sl-primary: 21 94 60;    /* pitch green — dark sections, headings */
  --sl-accent: 253 199 44;   /* sunshine yellow — CTAs, highlights    */
  --sl-boys: 2 112 181;      /* pathway chip colours                  */
  --sl-girls: 190 24 93;
  --sl-mixed: 109 40 217;
  --sl-font-display: "Nunito", ...;
  --sl-radius: 0.75rem;      /* corner rounding                       */
}
```

Rebuild after editing:

```bash
npm install        # once
npm run build      # writes assets/css/main.css
npm run watch      # optional: rebuild on save
```

You can also override the variables in a small extra stylesheet loaded
after `main.css` — no rebuild needed (values are read at runtime).

## Editing content

- **Club name / badge** — the badge is an inline SVG in the header and
  footer (it recolours with your variables automatically); swap it for
  your own crest if you have one. Find-and-replace `Millbrook Juniors`.
- **Teams** — each team is one self-contained `<article>` card in
  `teams.html` with a `data-category="boys|girls|mixed"` attribute for
  the filter. Duplicate and edit.
- **Fees table** — plain HTML table in `join.html`.
- **Forms** — demo handler only (`data-demo-form`); point each form at
  Formspree / Netlify Forms / your club system and remove the attribute.
- **Images** — replace the demo photos in `assets/img/` with your club's
  own **consented** photography (keep `width`/`height` attributes to
  avoid layout shift). Never publish junior players' photos without
  signed parental consent.

## Mobile & iPhone

Tested at iPhone viewports (390/375px): no horizontal scroll on any
page, hamburger navigation with Escape-to-close, 16px form inputs (so
iOS Safari never zoom-jumps on focus), `apple-touch-icon` included, and
tap targets sized for thumbs.

## Accessibility

WCAG 2.1 AA patterns throughout: semantic landmarks, skip link, visible
focus states, labelled controls, `aria-current` nav, `aria-pressed`
filter states with a live results count, one-open accordion on native
`<details>`, table captions, and reduced-motion support.

## Credits & licence

- [Tailwind CSS](https://tailwindcss.com) (MIT) — build-time only
- [Nunito](https://fonts.google.com/specimen/Nunito) &
  [Inter](https://fonts.google.com/specimen/Inter) via Google Fonts (OFL)
- Demo photographs from [Unsplash](https://unsplash.com/license) (free
  for commercial use, no attribution required). They are placeholders —
  replace them with your club's own consented photos before launch.
- All demo copy, club names, people and badges are fictional.

Licence per the marketplace you purchased from (single-site licence
unless stated otherwise). Thanks for buying Sideline — everyone plays.
