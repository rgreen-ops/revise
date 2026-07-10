/**
 * Generates the demo SVG imagery in assets/img/.
 * Run:  node src/make-images.js
 *
 * Every image shares one visual system — dark engineering-grid backdrop,
 * white/amber line-art — so the demo reads as a coherent product range.
 * Buyers replace these with real photography; the HTML doesn't care.
 */
const fs = require("fs");
const path = require("path");

const OUT = path.join(__dirname, "..", "assets", "img");
fs.mkdirSync(OUT, { recursive: true });

const NAVY = "#101828";
const NAVY2 = "#1e293f";
const AMBER = "#f59e0b";
const LINE = "rgba(255,255,255,0.85)";
const FAINT = "rgba(255,255,255,0.28)";

function frame(w, h, inner, label) {
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" role="img" aria-label="${label}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="${NAVY2}"/><stop offset="1" stop-color="${NAVY}"/>
    </linearGradient>
    <pattern id="grid" width="36" height="36" patternUnits="userSpaceOnUse">
      <path d="M36 0H0v36" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
    </pattern>
    <radialGradient id="glow" cx="0.5" cy="0.45" r="0.6">
      <stop offset="0" stop-color="rgba(245,158,11,0.16)"/><stop offset="1" stop-color="rgba(245,158,11,0)"/>
    </radialGradient>
  </defs>
  <rect width="${w}" height="${h}" fill="url(#bg)"/>
  <rect width="${w}" height="${h}" fill="url(#grid)"/>
  <rect width="${w}" height="${h}" fill="url(#glow)"/>
  ${inner}
</svg>`;
}

// Each product drawing is centred in a 400x300 box.
const products = {
  "product-panel": {
    label: "Recessed LED panel luminaire",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <rect x="80" y="70" width="240" height="160" rx="8"/>
      <rect x="96" y="86" width="208" height="128" rx="4" fill="rgba(245,158,11,0.14)" stroke="${AMBER}" stroke-width="2"/>
    </g>
    <g stroke="${FAINT}" stroke-width="1.5">
      <line x1="112" y1="86" x2="112" y2="214"/><line x1="144" y1="86" x2="144" y2="214"/>
      <line x1="176" y1="86" x2="176" y2="214"/><line x1="208" y1="86" x2="208" y2="214"/>
      <line x1="240" y1="86" x2="240" y2="214"/><line x1="272" y1="86" x2="272" y2="214"/>
    </g>`,
  },
  "product-highbay": {
    label: "Circular LED high bay luminaire",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <line x1="200" y1="34" x2="200" y2="62"/>
      <rect x="188" y="20" width="24" height="16" rx="3"/>
      <path d="M148 62h104l16 56H132z"/>
      <circle cx="200" cy="168" r="66"/>
      <circle cx="200" cy="168" r="48" stroke="${AMBER}" stroke-width="2" fill="rgba(245,158,11,0.14)"/>
    </g>
    <g stroke="${AMBER}" stroke-width="2">
      <line x1="200" y1="252" x2="200" y2="272"/>
      <line x1="156" y1="240" x2="146" y2="258"/><line x1="244" y1="240" x2="254" y2="258"/>
    </g>`,
  },
  "product-batten": {
    label: "Linear LED batten luminaire",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <rect x="52" y="120" width="296" height="46" rx="20"/>
      <rect x="66" y="132" width="268" height="22" rx="11" stroke="${AMBER}" stroke-width="2" fill="rgba(245,158,11,0.14)"/>
      <line x1="120" y1="96" x2="120" y2="118"/><line x1="280" y1="96" x2="280" y2="118"/>
      <rect x="110" y="84" width="20" height="12" rx="3"/><rect x="270" y="84" width="20" height="12" rx="3"/>
    </g>
    <g stroke="${AMBER}" stroke-width="2">
      <line x1="120" y1="182" x2="112" y2="200"/><line x1="200" y1="182" x2="200" y2="202"/><line x1="280" y1="182" x2="288" y2="200"/>
    </g>`,
  },
  "product-floodlight": {
    label: "LED floodlight on mounting bracket",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <rect x="118" y="76" width="164" height="118" rx="10" transform="rotate(-8 200 135)"/>
      <rect x="136" y="92" width="128" height="86" rx="6" transform="rotate(-8 200 135)" stroke="${AMBER}" stroke-width="2" fill="rgba(245,158,11,0.14)"/>
      <path d="M170 208l-26 44h112l-26-44" />
      <rect x="150" y="252" width="100" height="12" rx="4"/>
    </g>
    <g stroke="${AMBER}" stroke-width="2">
      <line x1="292" y1="96" x2="316" y2="84"/><line x1="298" y1="128" x2="324" y2="124"/><line x1="296" y1="160" x2="320" y2="168"/>
    </g>`,
  },
  "product-emergency": {
    label: "Emergency exit bulkhead luminaire",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <rect x="92" y="88" width="216" height="124" rx="10"/>
      <rect x="108" y="104" width="184" height="92" rx="6" stroke="${AMBER}" stroke-width="2" fill="rgba(245,158,11,0.14)"/>
    </g>
    <g fill="${LINE}">
      <circle cx="164" cy="132" r="9"/>
      <path d="M156 146l16-4 14 10-6 8-12-9-14 22-10-6z"/>
    </g>
    <g stroke="${LINE}" stroke-width="4" fill="none">
      <path d="M226 148h44m0 0l-14-13m14 13l-14 13"/>
    </g>
    <circle cx="120" cy="222" r="5" fill="#34d399"/>
    <text x="134" y="227" fill="${FAINT}" font-family="monospace" font-size="12">CHARGE</text>`,
  },
  "product-downlight": {
    label: "Recessed LED downlight",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <ellipse cx="200" cy="120" rx="96" ry="30"/>
      <ellipse cx="200" cy="120" rx="64" ry="19" stroke="${AMBER}" stroke-width="2" fill="rgba(245,158,11,0.14)"/>
      <path d="M118 122v34c0 18 37 32 82 32s82-14 82-32v-34"/>
      <path d="M150 186l-10 26M250 186l10 26" stroke="${FAINT}" stroke-width="2"/>
    </g>
    <g stroke="${AMBER}" stroke-width="2">
      <line x1="176" y1="228" x2="170" y2="248"/><line x1="200" y1="230" x2="200" y2="252"/><line x1="224" y1="228" x2="230" y2="248"/>
    </g>`,
  },
  "product-cable": {
    label: "Armoured cable drum",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <circle cx="200" cy="150" r="92"/>
      <circle cx="200" cy="150" r="64" stroke="${FAINT}" stroke-width="2"/>
      <circle cx="200" cy="150" r="26" stroke="${AMBER}" stroke-width="2" fill="rgba(245,158,11,0.14)"/>
      <line x1="200" y1="58" x2="200" y2="124"/><line x1="200" y1="176" x2="200" y2="242"/>
      <line x1="108" y1="150" x2="174" y2="150"/><line x1="226" y1="150" x2="292" y2="150"/>
      <path d="M292 176c30 8 42 26 30 48" stroke="${AMBER}" stroke-width="3"/>
    </g>`,
  },
  "product-board": {
    label: "Three-phase distribution board",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <rect x="110" y="52" width="180" height="196" rx="8"/>
      <line x1="110" y1="96" x2="290" y2="96" stroke-width="2"/>
    </g>
    <g stroke="${AMBER}" stroke-width="2" fill="rgba(245,158,11,0.14)">
      <rect x="130" y="112" width="20" height="34" rx="3"/><rect x="158" y="112" width="20" height="34" rx="3"/>
      <rect x="186" y="112" width="20" height="34" rx="3"/><rect x="214" y="112" width="20" height="34" rx="3"/>
      <rect x="242" y="112" width="20" height="34" rx="3"/>
      <rect x="130" y="160" width="20" height="34" rx="3"/><rect x="158" y="160" width="20" height="34" rx="3"/>
      <rect x="186" y="160" width="20" height="34" rx="3"/><rect x="214" y="160" width="20" height="34" rx="3"/>
      <rect x="242" y="160" width="20" height="34" rx="3"/>
    </g>
    <circle cx="128" cy="74" r="5" fill="${AMBER}"/>
    <text x="142" y="79" fill="${FAINT}" font-family="monospace" font-size="12">TP-N 250A</text>`,
  },
};

for (const [name, p] of Object.entries(products)) {
  const inner = `<g transform="translate(100,90)">${p.art}</g>
  <rect x="24" y="24" width="120" height="26" rx="4" fill="rgba(255,255,255,0.06)" stroke="rgba(255,255,255,0.15)"/>
  <text x="38" y="41" fill="${FAINT}" font-family="monospace" font-size="12">CAL-${name.split("-")[1].toUpperCase().slice(0, 4)} SERIES</text>`;
  fs.writeFileSync(path.join(OUT, `${name}.svg`), frame(600, 480, inner, p.label));
}

// Wide hero / editorial images ------------------------------------------------
const wide = {
  "hero-warehouse": {
    label: "Warehouse aisle lit by rows of LED high bays",
    art: `
    <g stroke="${FAINT}" stroke-width="2" fill="none">
      <path d="M0 460 L440 240 L880 460"/><path d="M120 520 L440 300 L760 520"/>
      <path d="M60 240 v210 M820 240 v210 M180 280 v190 M700 280 v190 M300 320 v160 M580 320 v160"/>
    </g>
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <path d="M200 120 h480 M240 170 h400 M280 220 h320"/>
    </g>
    <g fill="rgba(245,158,11,0.9)">
      <rect x="300" y="112" width="52" height="12" rx="4"/><rect x="420" y="112" width="52" height="12" rx="4"/><rect x="540" y="112" width="52" height="12" rx="4"/>
      <rect x="330" y="162" width="44" height="12" rx="4"/><rect x="430" y="162" width="44" height="12" rx="4"/><rect x="530" y="162" width="44" height="12" rx="4"/>
      <rect x="360" y="212" width="38" height="12" rx="4"/><rect x="440" y="212" width="38" height="12" rx="4"/><rect x="520" y="212" width="38" height="12" rx="4"/>
    </g>
    <g fill="rgba(245,158,11,0.07)">
      <path d="M300 124 L260 460 L400 460 L352 124z"/><path d="M420 124 L390 460 L510 460 L472 124z"/><path d="M540 124 L510 460 L640 460 L592 124z"/>
    </g>`,
  },
  "about-facility": {
    label: "Distribution facility with loading bays",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <path d="M80 420 V220 L300 140 L520 220 V420"/>
      <path d="M520 260 H800 V420" />
      <rect x="130" y="280" width="90" height="140" rx="4"/>
      <rect x="260" y="280" width="90" height="140" rx="4"/>
      <rect x="390" y="280" width="90" height="140" rx="4"/>
      <line x1="40" y1="420" x2="840" y2="420"/>
    </g>
    <g stroke="${AMBER}" stroke-width="2" fill="none">
      <rect x="560" y="300" width="70" height="60" rx="4"/><rect x="670" y="300" width="70" height="60" rx="4"/>
      <path d="M300 140 v-36 h36" />
    </g>
    <g stroke="${FAINT}" stroke-width="1.5">
      <line x1="130" y1="315" x2="220" y2="315"/><line x1="130" y1="350" x2="220" y2="350"/><line x1="130" y1="385" x2="220" y2="385"/>
      <line x1="260" y1="315" x2="350" y2="315"/><line x1="260" y1="350" x2="350" y2="350"/><line x1="260" y1="385" x2="350" y2="385"/>
      <line x1="390" y1="315" x2="480" y2="315"/><line x1="390" y1="350" x2="480" y2="350"/><line x1="390" y1="385" x2="480" y2="385"/>
    </g>`,
  },
  "blog-compliance": {
    label: "Emergency lighting compliance checklist illustration",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <rect x="290" y="90" width="300" height="360" rx="10"/>
      <path d="M410 90 v-20 h60 v20" stroke-width="2"/>
    </g>
    <g stroke="${FAINT}" stroke-width="2">
      <line x1="360" y1="170" x2="560" y2="170"/><line x1="360" y1="240" x2="560" y2="240"/>
      <line x1="360" y1="310" x2="560" y2="310"/><line x1="360" y1="380" x2="560" y2="380"/>
    </g>
    <g stroke="${AMBER}" stroke-width="3" fill="none">
      <rect x="318" y="152" width="26" height="26" rx="5"/><path d="M323 165l7 7 12-14"/>
      <rect x="318" y="222" width="26" height="26" rx="5"/><path d="M323 235l7 7 12-14"/>
      <rect x="318" y="292" width="26" height="26" rx="5"/><path d="M323 305l7 7 12-14"/>
      <rect x="318" y="362" width="26" height="26" rx="5"/>
    </g>`,
  },
  "blog-warehouse-led": {
    label: "Lux level diagram over a warehouse floor plan",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <rect x="140" y="100" width="600" height="340" rx="8"/>
      <line x1="140" y1="270" x2="740" y2="270" stroke="${FAINT}" stroke-width="2"/>
      <line x1="340" y1="100" x2="340" y2="440" stroke="${FAINT}" stroke-width="2"/>
      <line x1="540" y1="100" x2="540" y2="440" stroke="${FAINT}" stroke-width="2"/>
    </g>
    <g fill="${AMBER}">
      <circle cx="240" cy="185" r="8"/><circle cx="440" cy="185" r="8"/><circle cx="640" cy="185" r="8"/>
      <circle cx="240" cy="355" r="8"/><circle cx="440" cy="355" r="8"/><circle cx="640" cy="355" r="8"/>
    </g>
    <g stroke="rgba(245,158,11,0.5)" stroke-width="1.5" fill="none">
      <circle cx="240" cy="185" r="40"/><circle cx="440" cy="185" r="40"/><circle cx="640" cy="185" r="40"/>
      <circle cx="240" cy="355" r="40"/><circle cx="440" cy="355" r="40"/><circle cx="640" cy="355" r="40"/>
    </g>
    <text x="180" y="135" fill="${FAINT}" font-family="monospace" font-size="14">AVG 312 lx · U0 0.62</text>`,
  },
  "blog-tariff": {
    label: "Line chart of copper price trend",
    art: `
    <g stroke="${FAINT}" stroke-width="2" fill="none">
      <line x1="140" y1="120" x2="140" y2="420"/><line x1="140" y1="420" x2="760" y2="420"/>
      <line x1="140" y1="200" x2="760" y2="200" stroke-dasharray="4 6" stroke-width="1"/>
      <line x1="140" y1="300" x2="760" y2="300" stroke-dasharray="4 6" stroke-width="1"/>
    </g>
    <path d="M140 380 L240 350 L330 365 L420 300 L510 315 L600 240 L690 255 L760 180" stroke="${AMBER}" stroke-width="4" fill="none"/>
    <g fill="${AMBER}"><circle cx="420" cy="300" r="6"/><circle cx="600" cy="240" r="6"/><circle cx="760" cy="180" r="6"/></g>
    <text x="160" y="150" fill="${FAINT}" font-family="monospace" font-size="14">GBP/TONNE · LME</text>`,
  },
  "project-retail": {
    label: "Retail unit shopfront elevation",
    art: `
    <g stroke="${LINE}" stroke-width="3" fill="none">
      <rect x="160" y="140" width="560" height="280" rx="6"/>
      <line x1="160" y1="210" x2="720" y2="210"/>
      <rect x="200" y="250" width="150" height="170" rx="4"/>
      <rect x="390" y="250" width="100" height="170" rx="4"/>
      <rect x="530" y="250" width="150" height="170" rx="4"/>
    </g>
    <g stroke="${AMBER}" stroke-width="2">
      <line x1="200" y1="176" x2="330" y2="176"/><circle cx="420" cy="176" r="6" fill="rgba(245,158,11,0.2)"/>
      <circle cx="470" cy="176" r="6" fill="rgba(245,158,11,0.2)"/><circle cx="520" cy="176" r="6" fill="rgba(245,158,11,0.2)"/>
    </g>`,
  },
};

for (const [name, p] of Object.entries(wide)) {
  fs.writeFileSync(path.join(OUT, `${name}.svg`), frame(880, 560, p.art, p.label));
}

console.log("Wrote", Object.keys(products).length + Object.keys(wide).length, "SVGs to assets/img/");
