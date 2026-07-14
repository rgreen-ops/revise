#!/usr/bin/env bash
# Builds the sellable product zips into marketing/dist/ .
# Each zip is the complete theme including sources and README —
# exactly what a Gumroad/ThemeForest buyer downloads.
set -euo pipefail
cd "$(dirname "$0")/.."

OUT=marketing/dist
THEMES="forgeline sideline terrace clarion vermilion evergreen amberline skyline tangerine midnight canary candystripe callout callout-plumbing callout-building callout-landscaping callout-roofing"

rm -rf "$OUT"
mkdir -p "$OUT"

for t in $THEMES; do
  (cd themes && zip -qr "../$OUT/$t-theme-v1.0.0.zip" "$t" -x "$t/node_modules/*")
  echo "$(du -h "$OUT/$t-theme-v1.0.0.zip" | cut -f1)  $t-theme-v1.0.0.zip"
done

echo "Product zips ready in $OUT/."
