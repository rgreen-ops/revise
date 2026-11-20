# Staging a copy of ricoman.com

This folder helps you stand up a **staging clone** of the live, self-hosted
WordPress site so we can test the new theme safely. It must run on your VPS —
nobody can clone your server from outside it.

## Option A — VPS + WP-CLI (recommended)

1. SSH into the VPS.
2. Copy `clone-ricoman.sh` onto the server and **edit the CONFIG block** at the
   top (paths, URLs, DB password).
3. Run it:
   ```bash
   chmod +x clone-ricoman.sh
   ./clone-ricoman.sh
   ```
4. Finish the manual steps it prints: DNS, vhost, SSL, Basic Auth, disable email.

### No DNS? Use nip.io
If you don't want to touch your DNS zone, set in the CONFIG:
```
STAGING_URL="https://staging.<YOUR.VPS.IP>.nip.io"
```
`nip.io` resolves any `*.<ip>.nip.io` host to that IP automatically, and
certbot can still issue a real certificate for it. No DNS record needed.

## Option B — No shell access (migration plugin)

1. On live: install **All-in-One WP Migration** (or **Duplicator**) → Export the
   whole site to a file.
2. Spin up a blank WordPress at `staging.ricoman.com` (subdomain + empty DB).
3. Import the file. Done.

## Safety checklist (important — it's real customer data)

- [ ] **HTTP Basic Auth** in front of staging (so customers/Google never see it)
- [ ] `blog_public = 0` (the script sets this) to discourage indexing
- [ ] **Outgoing email disabled / redirected** — otherwise test form submissions
      and WooCommerce-style emails can hit real people
- [ ] Wordfence set to learning/off on staging to avoid lockouts
- [ ] Use throwaway API keys for anything sensitive

## After staging is up — installing the new theme

```bash
cp -r /path/to/ricoman  /var/www/staging-ricoman/wp-content/themes/
wp --path=/var/www/staging-ricoman theme activate ricoman
```

⚠️ The theme registers its **own** product/project types. Your catalogue
(Products, Variant Products, Projects, Applications…) is ACF-driven, so it won't
appear until the theme is remapped to your fields. To do that, send me:

1. **ACF export** — `ACF → Tools → Export Field Groups → Export As JSON`
2. **CPT/taxonomy slugs** — `CPT UI → Tools → Export` (or just the slugs)

Drop those in a `reference/` folder in this repo (or paste them) and I'll wire
the theme to your real content.
