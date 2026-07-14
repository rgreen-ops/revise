#!/usr/bin/env bash
# Assembles the deployable demo site into marketing/demo-site/ :
# a landing hub plus every theme demo, stripped of dev files.
# Deploy: drag the demo-site folder into https://app.netlify.com/drop
set -euo pipefail
cd "$(dirname "$0")/.."

OUT=marketing/demo-site
THEMES="forgeline sideline terrace clarion vermilion evergreen amberline skyline tangerine midnight canary candystripe callout callout-plumbing callout-building callout-landscaping callout-roofing"

rm -rf "$OUT"
mkdir -p "$OUT"
cp marketing/demo-hub-index.html "$OUT/index.html"

for t in $THEMES; do
  mkdir -p "$OUT/$t"
  # ship only what a visitor needs — no sources, no tooling
  cp -r themes/"$t"/. "$OUT/$t"/
  rm -rf "$OUT/$t"/node_modules "$OUT/$t"/src "$OUT/$t"/package.json \
    "$OUT/$t"/package-lock.json "$OUT/$t"/tailwind.config.js \
    "$OUT/$t"/.gitignore "$OUT/$t"/README.md
done

echo "Demo site assembled at $OUT ($(du -sh "$OUT" | cut -f1))."
echo "Deploy: https://app.netlify.com/drop — drag the demo-site folder in."
