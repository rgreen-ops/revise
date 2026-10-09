#!/usr/bin/env bash
#
# clone-ricoman.sh — clone a live, self-hosted WordPress install to a staging
# copy on the SAME VPS, then harden it for safe testing.
#
# YOU run this on your VPS over SSH. Review every value in the CONFIG block
# first. Nothing here can run from outside your server.
#
# Requirements: shell access, WP-CLI (https://wp-cli.org), and permission to
# create a database + a web-server vhost. Tested with bash on Debian/Ubuntu.
#
# What it does:
#   1. Copies the live web root to a staging directory
#   2. Creates a fresh staging database
#   3. Exports the live DB and imports it into staging
#   4. Rewrites URLs (live -> staging) safely (serialized-data aware)
#   5. Hardens staging: discourage search engines, add a "staging" flag
#
# What it does NOT do (you finish these once, by hand):
#   - Create the DNS record / vhost / SSL for the staging hostname
#   - Disable outgoing email (see notes at the end)
#
set -euo pipefail

############################  CONFIG — EDIT THESE  ############################
LIVE_PATH="/var/www/ricoman"                 # live WordPress web root
STAGING_PATH="/var/www/staging-ricoman"      # new staging web root (must not exist)
LIVE_URL="https://ricoman.com"               # live site URL (no trailing slash)
STAGING_URL="https://staging.ricoman.com"    # staging URL — or a nip.io host, see README
STG_DB_NAME="ricoman_stg"
STG_DB_USER="ricoman_stg"
STG_DB_PASS="CHANGE-ME-strong-password"
WP_CLI="wp"                                  # path to wp-cli if not on PATH
##############################################################################

confirm() { read -r -p "$1 [y/N] " a; [[ "$a" == "y" || "$a" == "Y" ]]; }

echo "Clone:  $LIVE_PATH ($LIVE_URL)"
echo "   ->   $STAGING_PATH ($STAGING_URL)"
confirm "Proceed?" || { echo "Aborted."; exit 1; }

[[ -d "$LIVE_PATH" ]] || { echo "Live path not found: $LIVE_PATH"; exit 1; }
[[ -e "$STAGING_PATH" ]] && { echo "Staging path already exists: $STAGING_PATH"; exit 1; }

echo "==> 1/5 Copying files (this can take a while)…"
cp -a "$LIVE_PATH" "$STAGING_PATH"

echo "==> 2/5 Creating staging database…"
mysql -e "CREATE DATABASE IF NOT EXISTS \`${STG_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${STG_DB_USER}'@'localhost' IDENTIFIED BY '${STG_DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${STG_DB_NAME}\`.* TO '${STG_DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

echo "==> 3/5 Exporting live DB and importing into staging…"
"$WP_CLI" --path="$LIVE_PATH" db export /tmp/ricoman-live.sql --skip-lock-tables
"$WP_CLI" --path="$STAGING_PATH" config set DB_NAME "$STG_DB_NAME"
"$WP_CLI" --path="$STAGING_PATH" config set DB_USER "$STG_DB_USER"
"$WP_CLI" --path="$STAGING_PATH" config set DB_PASSWORD "$STG_DB_PASS"
"$WP_CLI" --path="$STAGING_PATH" db import /tmp/ricoman-live.sql
rm -f /tmp/ricoman-live.sql

echo "==> 4/5 Rewriting URLs ($LIVE_URL -> $STAGING_URL)…"
"$WP_CLI" --path="$STAGING_PATH" search-replace "$LIVE_URL" "$STAGING_URL" --all-tables --skip-columns=guid
"$WP_CLI" --path="$STAGING_PATH" cache flush || true

echo "==> 5/5 Hardening staging…"
"$WP_CLI" --path="$STAGING_PATH" option update blog_public 0          # discourage search engines
"$WP_CLI" --path="$STAGING_PATH" option update blogname "[STAGING] Ricoman" || true
# Optional: turn off plugins that misbehave on staging (uncomment as needed)
# "$WP_CLI" --path="$STAGING_PATH" plugin deactivate wordfence wp-mail-smtp || true

cat <<EOF

============================================================
 Staging files + database are ready at:
   $STAGING_PATH   ->   $STAGING_URL

 STILL TO DO (once, by hand):
 1) DNS: point the staging host at this server's IP (or use nip.io — see README)
 2) Web server: add a vhost/server-block for $STAGING_URL -> $STAGING_PATH
 3) SSL:  sudo certbot --nginx -d $(echo "$STAGING_URL" | sed -E 's#https?://##')
 4) Protect it with HTTP Basic Auth so it's private
 5) Stop staging emailing real people:
      - point WP Mail SMTP at a test inbox, OR
      - install a mail-catcher / set wp_mail to log only
 6) Install the new theme:
      cp -r /path/to/ricoman  $STAGING_PATH/wp-content/themes/
      $WP_CLI --path="$STAGING_PATH" theme activate ricoman
============================================================
EOF
