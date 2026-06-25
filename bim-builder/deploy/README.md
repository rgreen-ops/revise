# Hosting Bim Builder on Synology (auto-updating)

Goal: host the app on your Synology and have it **update itself** whenever a new
commit lands on `main` — so you never drop a zip in again.

How it works: the NAS **pulls** the latest app from GitHub on a schedule using
`synology-deploy.sh` and serves it with Web Station. Nothing on the NAS is
exposed to the internet; only outbound HTTPS to GitHub is used.

## One-time setup

### 1. Choose a web folder
Pick a **dedicated** folder for the app, e.g. `/volume1/web/bim-builder`.
Don't reuse a folder with other files — the deploy mirrors with `--delete`.

### 2. GitHub access token (private repo)
1. GitHub → Settings → Developer settings → **Fine-grained tokens** → Generate.
2. Repository access: only `rgreen-ops/revise`. Permissions: **Contents → Read-only**.
3. On the NAS, save the token to a file and lock it down:
   ```sh
   echo 'github_pat_xxx' > /volume1/web/.revise_token
   chmod 600 /volume1/web/.revise_token
   ```

### 3. Add the deploy task
DSM → **Control Panel → Task Scheduler → Create → Scheduled Task → User-defined script**.
- **User:** `root` (so it can write the web folder)
- **Schedule:** every 5 minutes (or whatever cadence you like)
- **Run command:** paste the contents of `synology-deploy.sh`, *or* if you keep a
  copy on the NAS:
  ```sh
  sh /volume1/web/synology-deploy.sh
  ```
Edit the `TARGET` / `TOKEN_FILE` values at the top of the script if your paths differ.
Click **Run** once to seed the folder, and check the task's log for `Deployed to ...`.

### 4. Serve it with Web Station
DSM → **Web Station**:
1. **Web Service** → Create → *Static website* → Document root = your `TARGET` folder.
2. **Web Portal** → Create → bind that service to a port (e.g. `http://nas-ip:8080`)
   or a name-based host (e.g. `http://bim.your-nas`).

That URL is your internal test link. Open it on any device on the network.

## The new workflow
1. I push a change to `main`.
2. Within the schedule interval, the NAS pulls it and the site updates.
3. You just refresh the page — no zip, no drop.

Want it faster than the interval? Just hit **Run** on the task, or set a shorter
schedule (1–5 min is fine; it's a tiny download).

## Notes
- HTTPS/service worker: the offline cache + “install as app” only work over
  HTTPS (or `localhost`). Plain `http://nas-ip` works fine for testing; for HTTPS
  use a Web Portal with a Let's Encrypt or self-signed cert.
- The 3D preview and PDF datasheet reading load three.js / pdf.js from a CDN, so
  the NAS clients need outbound internet. (Ask me to vendor those libraries if you
  need it to work fully offline.)
