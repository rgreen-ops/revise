# Bim Builder — Lighting BIM

Turn a luminaire's **datasheet + 3D model + LDT photometric file** into an **IFC**
BIM file that an architect or interior designer can import straight into Revit,
ArchiCAD or any other BIM tool — carrying the 3D geometry plus full electrical and
lighting data.

It's a zero-build, vanilla-JS Progressive Web App (same approach as the `Revise`
app at the repo root). Open `index.html` and go.

## Why IFC and not `.rvt` / `.rfa`?

Native Revit files are a **closed, proprietary binary format** that can only be
written by Revit itself — no third-party tool (web or desktop) can generate a
genuine `.rvt`/`.rfa` from scratch. **IFC** (Industry Foundation Classes) is the
open BIM standard that *every* major authoring tool imports, including Revit, and
it carries geometry **and** property sets (electrical, photometric, manufacturer
data). So Bim Builder targets IFC4.

In Revit, import with **Insert → Link IFC** or **Open → IFC**.

> Want a true loadable Revit family (`.rfa`) one day? The same parsed package
> (geometry + photometric data) can be fed to a Revit add-in or a Dynamo/Python
> script that builds the family *inside* Revit. The web app produces the portable
> IFC; a Revit-side step is the only way to emit native `.rfa`.

## Build from a preset shape (no 3D upload)

For product families that are a constant cross-section run along a path, you don't
need to upload a model at all — pick a **preset shape**, size it in millimetres and
Bim Builder generates the geometry:

- **Estrella (linear)** — single line, L-shape, U-shape, rectangle/square, cross.
- **Flow (arcs)** — single arc, semicircle, circle/ring, S-curve, wave.

The generated geometry exports to IFC on its own (drop it into a space to check
fit) or combined with an LDT for the full photometric/electrical data. See
`shapes.js`.

## Auto-photometry from length (no per-size LDT)

Linear LED products don't need a measured LDT for every size. Enter **lumens per
metre** (and optionally W/m, CRI); Bim Builder works out the lit run length from
the geometry — exact for presets, estimated from the longest extent for uploaded
models — multiplies it out, and **generates a matching LDT** that's bundled with
the IFC as a ZIP. The Flow custom tab does the same from size + arc angle. See
`photometry.js` / `ldt-write.js`.

## Photometry & curved products (important)

A photometric file (LDT/IES) describes light from a **single reference point with
one orientation** — it cannot, by itself, represent light emitted *along a curve*.
So for a Flow **arc**:

- The **geometry** is always a correct arc (it's the real mesh) — it never looks
  wrong in the model.
- A **single** LDT attached to it would calculate as if all the light came from
  one point in one direction — fine for total lumens / load / visual fit, but
  **not** an accurate simulation of light following the curve.
- The correct fix for accurate curved-light simulation is **segmenting**:
  distribute several short emitters along the path, each carrying a slice of the
  output oriented to the local tangent. That's on the roadmap (segmented
  `IfcLightSourceGoniometric` / per-segment photometry) — note most BIM authoring
  tools read geometry + data from IFC and run the actual lighting calc from the
  linked LDT/IES, so segmenting matters most for the calc tools.

In short: shape is always right; for **accurate curved photometric calcs**, use
segmenting (roadmap) rather than one LDT.

## How it works

1. **Choose geometry** — either *Build from a preset shape* above, or **drop files**:
   a datasheet (optional), a 3D model (optional: glTF/GLB/OBJ/STL) and an **LDT**
   Eulumdat photometric file (for the lighting data).
2. **Review** — the app parses the LDT and shows luminous flux, wattage, efficacy,
   colour temperature, CRI, dimensions and a polar intensity diagram, plus an
   interactive 3D preview of the model.
3. **Export IFC** — generates an `IfcLightFixture` (+ `IfcLightFixtureType`) inside
   a Project › Site › Building › Storey structure, with:
   - geometry as an `IfcTriangulatedFaceSet` from your mesh (or a dimensioned box
     from the LDT luminaire dimensions if no model is supplied), and
   - property sets: `Pset_LightFixtureTypeCommon`,
     `Pset_ManufacturerTypeInformation` and a `BimBuilder_Photometric` set
     (flux, wattage, efficacy, CCT, CRI, lamp type, DFF, LOR, symmetry, source
     file references).

## Files

| File | Purpose |
|------|---------|
| `index.html` | App shell, styling, drop zones, review UI |
| `app.js` | Orchestration — wires drop zones to parser/preview/export |
| `shapes.js` | Parametric preset geometry (Estrella linear, Flow arcs) |
| `ldt.js` | Eulumdat `.ldt` parser → photometric + electrical data |
| `model.js` | 3D model loading + preview (three.js via CDN) |
| `ifc.js` | IFC4 STEP-file exporter |
| `manifest.json`, `sw.js`, `icon.svg` | PWA install + offline shell |
| `samples/aurora600.ldt` | A sample LED downlight LDT for testing |

## Notes & limits (current MVP)

- The 3D preview / mesh embedding uses **three.js loaded from a CDN**, so it needs
  a network connection; the core LDT → IFC pipeline works offline.
- Model units are assumed to be **metres** and scaled to millimetres for IFC. If a
  model imports at the wrong scale, that assumption is the place to adjust
  (`SCALE` in `model.js`).
- The datasheet is attached as a **reference** (filename written into the IFC);
  automatic field extraction from PDFs is not done yet — fill the metadata fields
  in the review step.
- LDT lamp data uses the **first** standard lamp set as the rated values.

## Roadmap

**Product types** — the exporter is structured so new product categories just
register additional property sets; geometry/export is shared:

- **Acoustic lighting** — add a `Pset_..._Acoustic` set: sound absorption
  coefficient per octave band (125 Hz–4 kHz), NRC / αw, absorption Class A–E,
  backing/material. This is what acousticians read, carried alongside the lighting.
- **Biophilic lighting** — add a circadian/wellbeing set: melanopic ratio / EML,
  spectral notes, material/finish.

**Other**

- PDF datasheet text extraction to auto-fill metadata.
- Embed the IES/LDT photometric web into the IFC as an `IfcLightSource` /
  light-distribution definition so lighting calcs survive the round-trip.
- More preset shapes / a freehand path editor for bespoke runs.
- A companion Dynamo/Revit add-in that consumes the package and builds a native
  `.rfa` family.
- Multi-luminaire / schedule export.
