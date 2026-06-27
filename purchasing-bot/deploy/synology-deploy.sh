#!/bin/sh
# Purchasing Bot — Synology auto-deploy (pull model).
#
# Fetches the latest committed app from GitHub and syncs the /purchasing-bot
# folder into your Web Station web root. Run it from DSM > Control Panel > Task
# Scheduler on a schedule (e.g. every 5 minutes) so the site updates itself
# whenever a new commit lands on `main` — no manual zip dropping. Same pull model
# as the bim-builder module so the NAS only makes outbound HTTPS to GitHub.
#
# Requirements (all ship with DSM 7, no Package Center installs needed):
#   curl, tar, rsync
#
# One-time setup:
#   1. Pick a DEDICATED web folder for the app (TARGET below). Don't point it at
#      a shared folder with other files — this script mirrors with --delete.
#   2. Private repo access: create a GitHub fine-grained Personal Access Token
#      with read-only "Contents" on rgreen-ops/revise, save it to TOKEN_FILE
#      (e.g. /volume1/web/.revise_token), and lock it down: chmod 600 TOKEN_FILE.
#   3. Point a Web Station "Web Service" (static website) at TARGET and expose it
#      via a Web Portal (port- or name-based). That URL is your test link.
#
# NOTE on going live with Unleashed: this script deploys the static front-end
# only. The Unleashed proxy (which holds the HMAC API id/key and signs requests)
# is a separate small service on the NAS — keep its secrets in env/secret files,
# never in this web root.

set -eu

# ---- config — edit these for your NAS --------------------------------------
REPO="rgreen-ops/revise"
BRANCH="main"
TARGET="/volume1/web/purchasing-bot"       # Web Station web root for the app
TOKEN_FILE="/volume1/web/.revise_token"    # file containing a GitHub PAT
# ----------------------------------------------------------------------------

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

TOKEN=""
[ -f "$TOKEN_FILE" ] && TOKEN="$(cat "$TOKEN_FILE")"

URL="https://api.github.com/repos/$REPO/tarball/$BRANCH"
log "Fetching $REPO@$BRANCH ..."
if [ -n "$TOKEN" ]; then
  curl -fsSL -H "Authorization: token $TOKEN" -H "User-Agent: synology-deploy" "$URL" -o "$TMP/src.tar.gz"
else
  curl -fsSL -H "User-Agent: synology-deploy" "$URL" -o "$TMP/src.tar.gz"
fi

mkdir -p "$TMP/x"
tar -xzf "$TMP/src.tar.gz" -C "$TMP/x"
SRC="$(ls -d "$TMP"/x/*/ | head -n1)"      # GitHub wraps everything in one top dir

if [ ! -d "${SRC}purchasing-bot" ]; then
  log "ERROR: purchasing-bot/ not found in the downloaded archive — aborting."
  exit 1
fi

mkdir -p "$TARGET"
rsync -a --delete --exclude '.revise_token' "${SRC}purchasing-bot/" "$TARGET/"
log "Deployed to $TARGET"
