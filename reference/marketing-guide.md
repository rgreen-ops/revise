# Ricoman website — simple guide for the Marketing team

You don't need to be technical, and you don't need Plesk or GitHub. You just talk
to Claude in plain English and it makes the changes. This page explains what's
safe, how things reach the live site, and what to do if something looks off.

## The two kinds of change (this is the only concept to remember)

**1. Content** — words, images, products, news posts, prices‑free spec text,
colours, FAQs, page sections. This is the day‑to‑day stuff.

**2. Features / layout / templates** — how a page type is built, new tools, design
changes. This is occasional, bigger work.

They reach the live site in different ways (below). If you're not sure which one
your request is, just ask Claude — it'll tell you.

## How to make a change

Just describe what you want, e.g.:
- "Change the headline on the Linear Lighting page to …"
- "Add a news post titled … with this text and image."
- "Swap the third colour swatch on the Estrella Pro Opal to matte bronze."
- "Add an FAQ to the Neptune downlight."

Claude does it, shows you, and you check it.

## Safe by design — how it goes live

### Content → edit on the LIVE site, with Preview first
For everyday content, Claude makes the change as a **Draft** (or you preview it),
you look at it, and only when you're happy does it get **Published**. That preview
*is* your safety net — nothing is public until you say so. No second site to sync,
nothing to "push", nothing to break.

### Features / layout → tested on STAGING first, then pushed
Bigger changes are built on the **staging** site first (a private copy) so you can
see them safely. When you approve, Claude pushes them to live for you with one
action. You don't touch GitHub or Plesk — you just say **"push it live"** and
Claude runs the deploy. Live keeps all its real data (customer leads, accounts) —
the push only updates the website's code, never your data.

## "Push to live" — the sign-off screen

When a staging change is approved, go to **Ricoman → 🚀 Push to Live** in the WP
admin menu. It's a deliberate, can't-do-it-by-accident screen:

1. Tick the two boxes (you've checked it on staging; you understand it goes live).
2. Type **PUBLISH** in the box.
3. Click **Publish to LIVE now** and confirm the final prompt.

The live site updates within a minute or two and clears its own cache — you never
touch Plesk. (You can also just ask Claude to "push the latest to live" and it
does the same thing.)

## If something looks wrong — don't worry

- **Nothing is lost.** Content uses Draft/Preview, so mistakes don't go public.
- **Code can be rolled back.** If a feature push causes a problem, tell Claude
  "roll back the last live deploy" and it re‑deploys the previous good version.
- **Live customer data is never overwritten** by a normal change or push.
- If a page ever looks stale, tell Claude "clear the caches" — it can do it; you
  never need Plesk.

When in doubt, just describe what you're seeing to Claude. There's no question
that's too basic.

## What needs a person (rare, one‑offs)

A few things are one‑time setup that a developer/admin does, not daily marketing:
- The very first **launch** (putting the whole new site live the first time).
- Adding the live server's login details once, so "push to live" works.
- Big infrastructure jobs (hosting, backups).

Everything else is just: tell Claude → preview → approve.
