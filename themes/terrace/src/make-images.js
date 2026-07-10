/**
 * Generates the demo SVG imagery in assets/img/.
 * Run:  node src/make-images.js
 *
 * Every image shares one visual system — claret gradient backdrop with a
 * terrace-stripe texture, white/sky line-art — so the demo reads as one
 * club's media output. Clubs replace these with real match photography;
 * the HTML doesn't care.
 */
const fs = require("fs");
const path = require("path");

const OUT = path.join(__dirname, "..", "assets", "img");
fs.mkdirSync(OUT, { recursive: true });

const CLARET = "#601628";
const CLARET_D = "#3d0c19";
const SKY = "#7dd3fc";
const LINE = "rgba(255,255,255,0.88)";
const FAINT = "rgba(255,255,255,0.30)";

function frame(w, h, inner, label) {
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" role="img" aria-label="${label}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="${CLARET}"/><stop offset="1" stop-color="${CLARET_D}"/>
    </linearGradient>
    <pattern id="stripes" width="26" height="26" patternUnits="userSpaceOnUse" patternTransform="rotate(-55)">
      <rect width="2" height="26" fill="rgba(255,255,255,0.045)"/>
    </pattern>
    <radialGradient id="glow" cx="0.5" cy="0.4" r="0.65">
      <stop offset="0" stop-color="rgba(125,211,252,0.15)"/><stop offset="1" stop-color="rgba(125,211,252,0)"/>
    </radialGradient>
  </defs>
  <rect width="${w}" height="${h}" fill="url(#bg)"/>
  <rect width="${w}" height="${h}" fill="url(#stripes)"/>
  <rect width="${w}" height="${h}" fill="url(#glow)"/>
  ${inner}
</svg>`;
}

const wide = {
  "hero-stadium": {
    label: "Floodlit football ground with main stand and pitch",
    art: `
    <g stroke="${FAINT}" stroke-width="2" fill="none">
      <path d="M0 470 h880"/>
      <path d="M60 470 L120 330 h640 L820 470"/>
      <path d="M120 330 L150 250 h580 L760 330"/>
    </g>
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <path d="M150 250 h580"/>
      <path d="M170 250 v-60 M710 250 v-60"/>
    </g>
    <g stroke="${SKY}" stroke-width="3" fill="none">
      <path d="M110 190 v-90 m-28 0 h56 M770 190 v-90 m-28 0 h56"/>
    </g>
    <g fill="${SKY}">
      <circle cx="96" cy="92" r="5"/><circle cx="110" cy="86" r="5"/><circle cx="124" cy="92" r="5"/>
      <circle cx="756" cy="92" r="5"/><circle cx="770" cy="86" r="5"/><circle cx="784" cy="92" r="5"/>
    </g>
    <g fill="rgba(125,211,252,0.08)">
      <path d="M96 97 L40 330 L180 330 L124 97z"/><path d="M756 97 L700 330 L840 330 L784 97z"/>
    </g>
    <g stroke="${LINE}" stroke-width="2.5" fill="none">
      <path d="M60 470 C210 440 670 440 820 470"/>
      <circle cx="440" cy="468" r="52" stroke-dasharray="0"/>
      <path d="M330 470 h220"/>
    </g>
    <g stroke="${FAINT}" stroke-width="1.5">
      <path d="M160 300 h560 M175 275 h530"/>
    </g>`,
  },
  "news-match": {
    label: "Striker hitting a shot towards the top corner",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <path d="M560 120 h260 v220" />
      <path d="M560 120 v220 h260"/>
      <path d="M560 340 l-40 60 M820 340 l30 60"/>
    </g>
    <g stroke="${FAINT}" stroke-width="1">
      <path d="M574 134 h232 M574 162 h232 M574 190 h232 M574 218 h232 M574 246 h232 M574 274 h232 M574 302 h232"/>
      <path d="M588 120 v220 M616 120 v220 M644 120 v220 M672 120 v220 M700 120 v220 M728 120 v220 M756 120 v220 M784 120 v220"/>
    </g>
    <g fill="${LINE}">
      <circle cx="235" cy="180" r="16"/>
      <path d="M228 200 l-18 52 22 8 12 -38 26 20 34 -30 -14 -16 -26 22 -20 -16z"/>
      <path d="M212 252 l-30 66 20 10 32 -60z"/>
      <path d="M248 208 l44 -22 8 16 -40 24z"/>
    </g>
    <circle cx="430" cy="150" r="15" fill="${SKY}"/>
    <g stroke="${SKY}" stroke-width="2.5" opacity="0.7">
      <path d="M390 168 l24 -10 M370 186 l30 -14 M350 204 l34 -16"/>
    </g>
    <text x="60" y="480" fill="${FAINT}" font-family="monospace" font-size="15">RAVENSHAW ROVERS FC · MATCHDAY</text>`,
  },
  "news-training": {
    label: "Training pitch with cones and passing drill arrows",
    art: `
    <g stroke="${FAINT}" stroke-width="2" fill="none">
      <rect x="90" y="90" width="700" height="380" rx="6"/>
      <line x1="440" y1="90" x2="440" y2="470"/>
      <circle cx="440" cy="280" r="60"/>
    </g>
    <g fill="${SKY}">
      <path d="M200 350 l16 30 h-32z"/><path d="M280 300 l16 30 h-32z"/><path d="M360 350 l16 30 h-32z"/>
      <path d="M540 210 l16 30 h-32z"/><path d="M620 160 l16 30 h-32z"/><path d="M700 210 l16 30 h-32z"/>
    </g>
    <g stroke="${LINE}" stroke-width="2.5" fill="none" stroke-dasharray="8 8">
      <path d="M216 340 C250 290 260 300 280 296 M296 330 C330 370 340 360 360 346 M556 240 C590 190 600 200 620 196 M636 190 C670 230 680 220 700 206"/>
    </g>
    <circle cx="216" cy="332" r="9" fill="${LINE}"/>
    <circle cx="556" cy="232" r="9" fill="${LINE}"/>
    <text x="110" y="130" fill="${FAINT}" font-family="monospace" font-size="15">TUESDAY SESSION · PASSING PATTERNS</text>`,
  },
  "news-community": {
    label: "Junior players celebrating with a coach",
    art: `
    <g fill="${LINE}">
      <circle cx="300" cy="200" r="20"/>
      <path d="M285 226 h30 l10 70 h-14 l-11 -44 -11 44 h-14z"/>
      <circle cx="420" cy="190" r="24"/>
      <path d="M402 222 h36 l12 84 h-16 l-14 -52 -14 52 h-16z"/>
      <circle cx="545" cy="205" r="19"/>
      <path d="M531 230 h28 l9 66 h-13 l-10 -42 -10 42 h-13z"/>
    </g>
    <g stroke="${LINE}" stroke-width="8" stroke-linecap="round">
      <path d="M292 236 l-28 -34 M338 300 l0 0"/>
      <path d="M310 236 l24 -20"/>
      <path d="M410 230 l-30 -40 M430 230 l30 -40"/>
      <path d="M538 238 l-24 -28 M552 238 l22 -30"/>
    </g>
    <circle cx="352" cy="150" r="13" fill="${SKY}"/>
    <path d="M120 420 h640" stroke="${FAINT}" stroke-width="2"/>
    <text x="120" y="470" fill="${FAINT}" font-family="monospace" font-size="15">JUNIORS FESTIVAL · U7–U12</text>`,
  },
  "academy-pitch": {
    label: "Aerial view of academy pitches with drill zones",
    art: `
    <g stroke="${LINE}" stroke-width="2.5" fill="none">
      <rect x="80" y="80" width="420" height="400" rx="4"/>
      <line x1="80" y1="280" x2="500" y2="280"/>
      <circle cx="290" cy="280" r="48"/>
      <rect x="180" y="80" width="220" height="70"/>
      <rect x="180" y="410" width="220" height="70"/>
    </g>
    <g stroke="${FAINT}" stroke-width="2" fill="none">
      <rect x="560" y="80" width="240" height="184" rx="4"/>
      <rect x="560" y="296" width="240" height="184" rx="4"/>
      <line x1="680" y1="80" x2="680" y2="264"/>
      <line x1="680" y1="296" x2="680" y2="480"/>
    </g>
    <g fill="${SKY}">
      <path d="M600 130 l12 22 h-24z"/><path d="M650 180 l12 22 h-24z"/><path d="M700 130 l12 22 h-24z"/><path d="M750 180 l12 22 h-24z"/>
      <circle cx="620" cy="380" r="7"/><circle cx="680" cy="420" r="7"/><circle cx="740" cy="380" r="7"/>
    </g>
    <text x="560" y="60" fill="${FAINT}" font-family="monospace" font-size="15">ACADEMY · 4G TRAINING GRIDS</text>`,
  },
  "club-history": {
    label: "Club trophy, pennant and commemorative laurels",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <path d="M380 140 h120 v60 a60 60 0 01-120 0z"/>
      <path d="M380 155 h-36 a36 36 0 0036 44 M500 155 h36 a36 36 0 01-36 44"/>
      <path d="M425 262 h30 l8 40 h-46z" fill="rgba(125,211,252,0.12)"/>
      <path d="M405 302 h70"/>
    </g>
    <circle cx="440" cy="185" r="22" stroke="${SKY}" stroke-width="2.5" fill="none"/>
    <text x="440" y="192" text-anchor="middle" fill="${SKY}" font-family="monospace" font-size="18">R</text>
    <g stroke="${FAINT}" stroke-width="2.5" fill="none">
      <path d="M330 360 C360 330 380 330 400 348 M550 360 C520 330 500 330 480 348"/>
      <path d="M310 390 C340 370 360 372 380 388 M570 390 C540 370 520 372 500 388"/>
    </g>
    <g fill="${LINE}">
      <path d="M150 120 l90 0 0 110 -45 -28 -45 28z"/>
      <path d="M640 120 l90 0 0 110 -45 -28 -45 28z" opacity="0.5"/>
    </g>
    <text x="440" y="440" text-anchor="middle" fill="${FAINT}" font-family="monospace" font-size="16">EST. 1921 · FOUR LEAGUE TITLES · TWO COUNTY CUPS</text>`,
  },
  "news-ground": {
    label: "New stand development sketch",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <path d="M120 420 h640"/>
      <path d="M160 420 v-120 l80 -60 h400 l80 60 v120"/>
      <path d="M240 240 v180 M720 240 v180"/>
      <path d="M240 300 h480"/>
    </g>
    <g stroke="${FAINT}" stroke-width="1.5">
      <path d="M270 330 v90 M320 330 v90 M370 330 v90 M420 330 v90 M470 330 v90 M520 330 v90 M570 330 v90 M620 330 v90 M670 330 v90"/>
    </g>
    <g stroke="${SKY}" stroke-width="2.5" fill="none" stroke-dasharray="10 6">
      <path d="M160 300 l-60 -40 M800 300 l60 -40"/>
    </g>
    <text x="120" y="470" fill="${FAINT}" font-family="monospace" font-size="15">EAST STAND PROPOSAL · 850 SEATS</text>`,
  },
};

for (const [name, p] of Object.entries(wide)) {
  fs.writeFileSync(path.join(OUT, `${name}.svg`), frame(880, 560, p.art, p.label));
}

// Player portrait placeholder (tall) ------------------------------------------
function player(num, label) {
  return frame(
    600,
    720,
    `
    <g fill="${LINE}">
      <circle cx="300" cy="200" r="52"/>
      <path d="M225 275 q75 -40 150 0 l22 190 h-42 l-14 -110 -8 230 h-66 l-8 -230 -14 110 h-42z"/>
    </g>
    <text x="300" y="420" text-anchor="middle" fill="${CLARET}" font-family="monospace" font-size="72" font-weight="bold">${num}</text>
    <g stroke="${SKY}" stroke-width="3" fill="none"><path d="M180 640 h240"/></g>
    <text x="300" y="680" text-anchor="middle" fill="${FAINT}" font-family="monospace" font-size="16">${label.toUpperCase()}</text>`,
    label
  );
}
fs.writeFileSync(path.join(OUT, "player-9.svg"), player(9, "Ravenshaw Rovers no. 9"));

console.log("Wrote", Object.keys(wide).length + 1, "SVGs to assets/img/");
