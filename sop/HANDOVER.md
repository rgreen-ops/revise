# Ricoman R&D — Product Pipeline · Handover & shared-login plan

This folder is a self-contained app for **productsolutions.ricoman.com**: a
stage-gate project manager for new product / component development (idea →
launch) with checked steps and multi-party gate sign-off, in the Ricoman
house style.

It is ready to deploy on the Synology via Docker, and is **pre-wired** to plug
into a shared Ricoman login. The shared-login work itself happens in the
**Ricobot repo (`ricoman-pim`)** — see "Next steps" below.

---

## 1. What's here

| File | Purpose |
|------|---------|
| `index.html` | The whole app (HTML + CSS + JS, no build step). |
| `manifest.json`, `sw.js`, `icon.svg`, `icon-*.png` | PWA install + offline + icon. |
| `Dockerfile`, `nginx.conf`, `docker-compose.yml`, `.dockerignore` | Container packaging. |
| `HANDOVER.md` | This document. |

**Current state:** data is stored in the **browser (localStorage)** and the
user just sets a name locally. There is **no real authentication or shared
data yet** — that is what the steps below add.

---

## 2. Deploy on the Synology (Docker)

1. Copy this `sop/` folder onto the Synology (e.g. `/volume1/docker/productsolutions`).
2. **Container Manager → Project → Create**, point it at this folder (it uses
   `docker-compose.yml`). It builds an nginx image serving the app on host
   port **8093**.
3. In **Control Panel → Login Portal → Advanced → Reverse Proxy**, add:
   - Source: `productsolutions.ricoman.com` (HTTPS, 443)
   - Destination: `http://localhost:8093`
4. Add the DNS record for `productsolutions.ricoman.com` and a certificate,
   the same way the other `*.ricoman.com` apps are set up.

That gives you the app live on its own subdomain — same pattern as the others.

---

## 3. Shared login across *.ricoman.com (the SSO plan)

Goal: **one set of usernames/passwords for production, quotes, ricobot and
productsolutions; a password reset on any one changes it for all.**

Because every app is on a `*.ricoman.com` subdomain, the clean approach is a
single **identity provider** that all apps trust:

- **Ricobot (`ricoman-pim`) becomes the identity provider**, reusing its
  existing accounts as the single source of truth (per your choice "reuse an
  existing one").
- Its login issues a **session cookie scoped to `Domain=.ricoman.com`** so
  the same session is visible to every subdomain (true single sign-on).
- Each app checks that session; if there is none, it redirects the user to the
  Ricobot login page, then back.
- A password reset just updates the one Ricobot user store → it applies
  everywhere automatically.

### What this app already does for that
`index.html` contains an `AUTH` block near the top of the script:

```js
var AUTH = { base:'', mePath:'/api/auth/me', loginPath:'/login', logoutPath:'/logout' };
```

- While `base` is empty, the app runs standalone (local name) — handy for testing now.
- Set `base = 'https://ricobot.ricoman.com'` (and confirm the three paths) and the app will:
  - call `GET {base}/api/auth/me` with `credentials:'include'` on load,
  - use the returned user as the signed-in operator (recorded on every sign-off),
  - redirect to the shared login if there's no valid session,
  - offer **Log out** from the avatar menu.

---

## 4. Next steps — to do in the Ricobot (`ricoman-pim`) session

Start a new session scoped to **`rgreen-ops/ricoman-pim`** and tackle:

1. **Expose the shared-session endpoints** on Ricobot:
   - `GET /api/auth/me` → returns the current user as JSON (`{ name, username, role, ... }`) or 401.
   - `GET /login` (existing) and `GET /logout`, both honouring a `?redirect=` param.
2. **Issue the session cookie on `Domain=.ricoman.com`** (currently it's almost
   certainly scoped to the Ricobot host only) — this is the change that turns
   one login into shared SSO. Keep it `HttpOnly; Secure; SameSite=Lax`.
3. **Confirm CORS**: allow the other subdomains to call `/api/auth/me` with
   credentials (`Access-Control-Allow-Origin` set to the calling subdomain,
   `Access-Control-Allow-Credentials: true`).
4. **Turn this app on**: set `AUTH.base` in `index.html` here to the Ricobot
   origin and redeploy the container.
5. **Wire the other apps** (production, quotes): they aren't in this GitHub —
   add the same lightweight check (call `/api/auth/me`, redirect to login if
   401). The snippet in §3 is all each one needs on the client.
6. **(Optional, recommended) Shared data**: to have everyone see the same
   pipelines across devices, move this app's storage from localStorage to a
   small API (could live in Ricobot or a sibling service) backed by a DB on
   the Synology. Until then, *More → Export/Import* moves data between devices.

---

## 5. Notes
- No secrets live in this repo; cookie keys / DB creds belong in environment
  variables on the Synology, not in git.
- `revise` is a **public** repo. If the pipeline data or wiring should be
  private, move this app into `ricoman-pim` (private) when we centralise.
