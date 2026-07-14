# Marketplace listings — copy-paste pack

Ready-to-use listing copy for Gumroad (use as-is) and ThemeForest (adapt
to their form fields). Swap `[DEMO-URL]` for your Netlify demo domain
after deploying `marketing/demo-site/`.

**Suggested launch pricing (Gumroad):**
Forgeline £39 · Sideline £35 · each football edition £29 · football
"any-colour" flagship Terrace £35. Bundle all ten football editions £79.
Raise prices once each product has 2–3 sales/reviews.

**Shared tags:** `html template`, `tailwind css`, `landing page`,
`small business website`, plus the per-product tags below.

---

## Forgeline — Industrial & Trade Supplier HTML Template — £39

**One-liner:** A premium website template that actually understands the
trade — datasheets, photometry downloads, quote requests and next-day
delivery messaging, not another generic "business theme".

**Description:**

Most templates make an electrical wholesaler look like a startup.
Forgeline was designed *from inside the industry*: the demo content is a
believable UK commercial lighting distributor, with product spec tables
(IP ratings, lumen output, efficacy), a downloads tab for datasheets and
LDT/IES photometric files, trade-account CTAs, volume-break pricing and
a "send us your bill of materials" quote flow.

- 8 pages: Home, About, Products (filterable grid), Single Product,
  Services, Blog, Article, Contact
- HTML + compiled Tailwind CSS — open index.html and it just works,
  no build step
- Rebrand in minutes: every colour and font is a CSS variable
- Real photography included; product images as clean technical renders
- Vanilla JS only: tabs, filters, gallery, accordion, working demo forms
- WCAG-AA accessibility patterns, tested at iPhone widths, no horizontal
  scroll anywhere
- Plain-English README covering setup, rebranding and going live

Perfect for: electrical wholesalers, lighting suppliers, manufacturers,
engineering firms, industrial distributors, builders' merchants.

**Live demo:** [DEMO-URL]/forgeline/
**Tags:** industrial website, trade supplier, wholesale, manufacturing,
b2b template

---

## Sideline — Grassroots Junior Football Club Template — £35

**One-liner:** The first club website template built for how junior
clubs actually work — 20+ teams, boys' and girls' pathways, volunteers
and safeguarding, no "first team" your club doesn't have.

**Description:**

Junior clubs don't need a fixtures-and-league-table site — they need
parents to find the right team, register in five minutes, and trust the
setup. Sideline is organised around exactly that:

- A **teams directory** with 24 demo teams across U6–U16, filterable by
  Boys / Girls / Mixed, each card showing league, format (4v4 → 11v11),
  training night, coach and live space availability
- Registration page with a fees table, sibling discounts and hardship
  fund — plus a parents' FAQ that answers the questions you get every July
- A volunteers page, because the real shortage is coaches, not players
- Safeguarding built in structurally: welfare contact first on the
  contact page, no junior players named, photo-consent language modelled
- 8 pages, HTML + compiled Tailwind, vanilla JS, WCAG-AA, iPhone-tested

**Live demo:** [DEMO-URL]/sideline/
**Tags:** football club, soccer club, youth sports, junior football,
grassroots, sports team website

---

## The football club editions — shared description core

> Use this block for all ten, changing the name/colours/club lines.

**One-liner ([NAME] edition):** A complete football club website in
[COLOURS] — fixtures, results, league table, squad profiles, match
reports and academy trials, styled for your colours out of the box.

**Description:**

Your club's website shouldn't look like a corporate brochure with a
ball photo. [NAME] is a complete club site in **[COLOURS]** — the demo
club ([DEMO CLUB], est. [EST]) wears your end of the colour spectrum so
you can picture your own badge on it immediately.

- **Match centre**: fixtures, results with scorelines and W/D/L badges,
  and a league table with your club's row highlighted — all plain HTML,
  edited by copy-paste
- **Live kick-off countdown** on the homepage (set one date attribute)
- **Squad grid** with position filter and player profile page (with a
  "sponsor this player" revenue CTA)
- Match report layout, club history/honours, academy trials form with
  parents' FAQ, contact with matchday directions
- A proper **club crest** included as a recolourable SVG — swap in your
  own badge whenever you're ready
- 9 pages, HTML + compiled Tailwind CSS, no build step, vanilla JS,
  WCAG-AA patterns, tested at iPhone widths
- Every colour is a CSS variable — matching your exact club colours is
  a five-minute job, documented in the README

Perfect for: non-league and semi-pro clubs, community clubs, Sunday
league sides, club volunteers who got handed "the website job".

**Editions / demo clubs:**

| Edition | Colours | Demo club | Est. | Demo URL |
|---|---|---|---|---|
| Terrace (flagship) | Claret & sky + 5-way switcher | Ravenshaw Rovers | 1921 | [DEMO-URL]/terrace/ |
| Clarion | Royal blue & gold | Kingsfield Rovers | 1907 | [DEMO-URL]/clarion/ |
| Vermilion | Scarlet & gold | Redbrook Rovers | 1911 | [DEMO-URL]/vermilion/ |
| Evergreen | Forest green & gold | Oakhurst Rovers | 1924 | [DEMO-URL]/evergreen/ |
| Amberline | Black & amber | Blackwick Rovers | 1899 | [DEMO-URL]/amberline/ |
| Skyline | Sky blue & gold | Easthaven Rovers | 1902 | [DEMO-URL]/skyline/ |
| Tangerine | Orange & black | Seabrook Rovers | 1912 | [DEMO-URL]/tangerine/ |
| Midnight | Navy & sky | Northgate Rovers | 1905 | [DEMO-URL]/midnight/ |
| Canary | Yellow & slate | Marshside Rovers | 1926 | [DEMO-URL]/canary/ |
| Candystripe | Red & white stripes | Westport Rovers | 1889 | [DEMO-URL]/candystripe/ |

**Terrace-only extra paragraph:** Can't see your colours? Terrace is the
any-colour edition: five ready-made colourways ship in the box (claret,
royal, scarlet, forest, black & amber) switchable with one line of HTML —
plus a live preview page — and the README shows how to hit your exact
colours with one CSS block.

**Candystripe-only extra paragraph:** The stripes aren't a gimmick —
every dark section wears the candy stripes, woven into the design the
way your fans expect.

**Tags:** football club template, soccer website, sports club, fixtures,
league table, [colour] football, non-league

---

## Gumroad setup checklist (per product)

1. Product type: digital product, single file — upload the zip from
   `marketing/dist/`
2. Cover images: use the marketing screenshots (hero crop first — the
   thumbnail sells the theme), then the family sheet on football
   editions so buyers see the range
3. URL slug: the theme name (e.g. `/l/forgeline`)
4. Add a licence line: "One licence = one club/site. Grab it again for
   a second site."
5. Cross-link: every football edition's description should end with
   "Different colours? See the full range: [shop link]"

## ThemeForest notes

- Category: Site Templates → Nonprofit (clubs) / Corporate (Forgeline)
- They require: live preview URL, thumbnail 80×80, inline preview
  590×300, screenshots. Reuse the Netlify demo + marketing shots.
- Submit Forgeline and Terrace first (strongest, most distinct); add
  editions once approved — reviewers reject near-duplicates listed
  simultaneously, so space the colour editions out or sell them as a
  single "10 editions included" item there and keep per-edition listings
  for Gumroad.
