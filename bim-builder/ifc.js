/* Minimal but valid IFC4 (ISO-10303-21 / STEP) exporter for a single lighting
   luminaire. Produces an .ifc file that Revit, ArchiCAD, Tekla etc. can import,
   carrying the 3D geometry plus electrical / photometric property sets.

   We build the spatial structure Project > Site > Building > Storey and place a
   single IfcLightFixture (+ IfcLightFixtureType) inside it. Geometry is an
   IfcTriangulatedFaceSet when a mesh is supplied, otherwise a dimensioned box
   extruded from the LDT luminaire dimensions. */

export function buildIfc(opts) {
  const { ldt, meta, mesh } = opts;
  const lines = [];
  let id = 0;
  const ref = (n) => '#' + n;
  const add = (type, body) => {
    id += 1;
    lines.push(`#${id}=${type}(${body});`);
    return id;
  };
  const S = (s) => "'" + String(s == null ? '' : s).replace(/\\/g, '\\\\').replace(/'/g, "''") + "'";
  const ENUM = (e) => '.' + e + '.';
  const $ = '$';

  // ---- owner history / application -------------------------------------
  const person = add('IFCPERSON', `${$},${S(meta.author || 'Bim Builder')},${$},${$},${$},${$},${$},${$}`);
  const org = add('IFCORGANIZATION', `${$},${S(meta.manufacturer || 'Bim Builder')},${$},${$},${$}`);
  const personOrg = add('IFCPERSONANDORGANIZATION', `${ref(person)},${ref(org)},${$}`);
  const appOrg = add('IFCORGANIZATION', `${$},${S('Bim Builder')},${$},${$},${$}`);
  const app = add('IFCAPPLICATION', `${ref(appOrg)},${S('1.0')},${S('Bim Builder')},${S('BimBuilder')}`);
  const ts = Math.floor(timestamp(meta.now) / 1000);
  const ownerHistory = add('IFCOWNERHISTORY', `${ref(personOrg)},${ref(app)},${$},.ADDED.,${$},${$},${$},${ts}`);

  // ---- units -----------------------------------------------------------
  const lenUnit = add('IFCSIUNIT', `*,.LENGTHUNIT.,.MILLI.,.METRE.`);
  const areaUnit = add('IFCSIUNIT', `*,.AREAUNIT.,${$},.SQUARE_METRE.`);
  const volUnit = add('IFCSIUNIT', `*,.VOLUMEUNIT.,${$},.CUBIC_METRE.`);
  const lumFluxUnit = add('IFCSIUNIT', `*,.LUMINOUSFLUXUNIT.,${$},.LUMEN.`);
  const powerUnit = add('IFCSIUNIT', `*,.POWERUNIT.,${$},.WATT.`);
  const unitAssignment = add('IFCUNITASSIGNMENT',
    `(${[lenUnit, areaUnit, volUnit, lumFluxUnit, powerUnit].map(ref).join(',')})`);

  // ---- geometric representation context --------------------------------
  const originPt = add('IFCCARTESIANPOINT', '(0.,0.,0.)');
  const axisZ = add('IFCDIRECTION', '(0.,0.,1.)');
  const axisX = add('IFCDIRECTION', '(1.,0.,0.)');
  const worldCS = add('IFCAXIS2PLACEMENT3D', `${ref(originPt)},${ref(axisZ)},${ref(axisX)}`);
  const context = add('IFCGEOMETRICREPRESENTATIONCONTEXT',
    `${$},${S('Model')},3,1.E-05,${ref(worldCS)},${$}`);

  // ---- project + spatial structure ------------------------------------
  const project = add('IFCPROJECT',
    `${S(guid())},${ref(ownerHistory)},${S(meta.model || 'Luminaire')},${$},${$},${$},${$},(${ref(context)}),${ref(unitAssignment)}`);

  const sitePlacement = localPlacement(add, ref, worldCS, $);
  const site = add('IFCSITE',
    `${S(guid())},${ref(ownerHistory)},${S('Site')},${$},${$},${ref(sitePlacement)},${$},${$},.ELEMENT.,${$},${$},${$},${$},${$}`);
  const buildingPlacement = localPlacement(add, ref, worldCS, $, sitePlacement);
  const building = add('IFCBUILDING',
    `${S(guid())},${ref(ownerHistory)},${S('Building')},${$},${$},${ref(buildingPlacement)},${$},${$},.ELEMENT.,${$},${$},${$}`);
  const storeyPlacement = localPlacement(add, ref, worldCS, $, buildingPlacement);
  const storey = add('IFCBUILDINGSTOREY',
    `${S(guid())},${ref(ownerHistory)},${S('Level 0')},${$},${$},${ref(storeyPlacement)},${$},${$},.ELEMENT.,0.`);

  // ---- the luminaire ---------------------------------------------------
  const shape = buildShape(add, ref, S, $, context, ldt, mesh);
  const productShape = add('IFCPRODUCTDEFINITIONSHAPE', `${$},${$},(${ref(shape.rep)})`);
  const fixturePlacement = localPlacement(add, ref, worldCS, $, storeyPlacement);
  const fixture = add('IFCLIGHTFIXTURE',
    `${S(guid())},${ref(ownerHistory)},${S(meta.model || ldt.luminaireName || 'Luminaire')},${$},${S(meta.reference || '')},${ref(fixturePlacement)},${ref(productShape)},${S(meta.tag || '')},.USERDEFINED.`);

  // ---- type object (so it imports as a real family/type) ---------------
  const propSets = buildPropertySets(add, ref, S, $, ownerHistory, ldt, meta);
  const fixtureType = add('IFCLIGHTFIXTURETYPE',
    `${S(guid())},${ref(ownerHistory)},${S(meta.model || 'Luminaire')},${$},${$},${$},${$},${$},${S(meta.reference || '')},.USERDEFINED.`);
  add('IFCRELDEFINESBYTYPE',
    `${S(guid())},${ref(ownerHistory)},${$},${$},(${ref(fixture)}),${ref(fixtureType)}`);

  // ---- relationships ---------------------------------------------------
  add('IFCRELAGGREGATES', `${S(guid())},${ref(ownerHistory)},${$},${$},${ref(project)},(${ref(site)})`);
  add('IFCRELAGGREGATES', `${S(guid())},${ref(ownerHistory)},${$},${$},${ref(site)},(${ref(building)})`);
  add('IFCRELAGGREGATES', `${S(guid())},${ref(ownerHistory)},${$},${$},${ref(building)},(${ref(storey)})`);
  add('IFCRELCONTAINEDINSPATIALSTRUCTURE',
    `${S(guid())},${ref(ownerHistory)},${$},${$},(${ref(fixture)}),${ref(storey)}`);

  for (const ps of propSets) {
    add('IFCRELDEFINESBYPROPERTIES',
      `${S(guid())},${ref(ownerHistory)},${$},${$},(${ref(fixture)}),${ref(ps)}`);
  }

  // ---- assemble STEP file ---------------------------------------------
  const stamp = isoTimestamp(meta.now);
  const header = [
    'ISO-10303-21;',
    'HEADER;',
    `FILE_DESCRIPTION(('ViewDefinition [ReferenceView_V1.2]'),'2;1');`,
    `FILE_NAME('${(meta.model || 'luminaire').replace(/'/g, '')}.ifc','${stamp}',('${(meta.author || 'Bim Builder').replace(/'/g, '')}'),('${(meta.manufacturer || '').replace(/'/g, '')}'),'Bim Builder','Bim Builder','');`,
    `FILE_SCHEMA(('IFC4'));`,
    'ENDSEC;',
    'DATA;',
  ].join('\n');

  return header + '\n' + lines.join('\n') + '\nENDSEC;\nEND-ISO-10303-21;\n';
}

function localPlacement(add, ref, worldCS, $, relTo) {
  return add('IFCLOCALPLACEMENT', `${relTo ? ref(relTo) : $},${ref(worldCS)}`);
}

/* Geometry: triangulated face set from the supplied mesh, else an extruded box
   sized from the LDT luminaire dimensions (mm). */
function buildShape(add, ref, S, $, context, ldt, mesh) {
  if (mesh && mesh.vertices && mesh.vertices.length >= 9 && mesh.indices && mesh.indices.length >= 3) {
    const coords = [];
    for (let v = 0; v < mesh.vertices.length; v += 3) {
      coords.push(`(${fmt(mesh.vertices[v])},${fmt(mesh.vertices[v + 1])},${fmt(mesh.vertices[v + 2])})`);
    }
    const pointList = add('IFCCARTESIANPOINTLIST3D', `(${coords.join(',')})`);
    const tris = [];
    for (let t = 0; t < mesh.indices.length; t += 3) {
      // IFC indices are 1-based.
      tris.push(`(${mesh.indices[t] + 1},${mesh.indices[t + 1] + 1},${mesh.indices[t + 2] + 1})`);
    }
    const faceSet = add('IFCTRIANGULATEDFACESET',
      `${ref(pointList)},${$},.T.,(${tris.join(',')}),${$}`);
    const rep = add('IFCSHAPEREPRESENTATION',
      `${ref(context)},${S('Body')},${S('Tessellation')},(${ref(faceSet)})`);
    return { rep };
  }

  // Fallback: extruded box from luminaire dimensions (default 100mm cube).
  const d = ldt && ldt.dimensions ? ldt.dimensions : {};
  const lx = (d.length || 100);
  const ly = (d.width || d.length || 100);
  const lz = (d.height || 50);
  const p0 = add('IFCCARTESIANPOINT', '(0.,0.,0.)');
  const dz = add('IFCDIRECTION', '(0.,0.,1.)');
  const dx = add('IFCDIRECTION', '(1.,0.,0.)');
  const profPos = add('IFCAXIS2PLACEMENT2D', `${add('IFCCARTESIANPOINT', '(0.,0.)')},${add('IFCDIRECTION', '(1.,0.)')}`);
  const profile = add('IFCRECTANGLEPROFILEDEF', `.AREA.,${S('Luminaire')},${ref(profPos)},${fmt(lx)},${fmt(ly)}`);
  const solidPos = add('IFCAXIS2PLACEMENT3D', `${ref(p0)},${ref(dz)},${ref(dx)}`);
  const solid = add('IFCEXTRUDEDAREASOLID', `${ref(profile)},${ref(solidPos)},${ref(dz)},${fmt(lz)}`);
  const rep = add('IFCSHAPEREPRESENTATION',
    `${ref(context)},${S('Body')},${S('SweptSolid')},(${ref(solid)})`);
  return { rep };
}

/* Property sets: standard Pset_LightFixtureTypeCommon + manufacturer info +
   a Bim Builder photometric set carrying the LDT-derived data. */
function buildPropertySets(add, ref, S, $, ownerHistory, ldt, meta) {
  const sets = [];
  const dv = ldt.derived;

  const single = (name, ifcType, value) => {
    let v;
    if (ifcType === 'TEXT' || ifcType === 'LABEL' || ifcType === 'IDENTIFIER') v = `IFC${ifcType}(${S(value)})`;
    else v = `IFC${ifcType}(${fmt(value)})`;
    return add('IFCPROPERTYSINGLEVALUE', `${S(name)},${$},${v},${$}`);
  };
  const pset = (name, props) => add('IFCPROPERTYSET',
    `${S(guid())},${ref(ownerHistory)},${S(name)},${$},(${props.map(ref).join(',')})`);

  sets.push(pset('Pset_LightFixtureTypeCommon', [
    single('Reference', 'IDENTIFIER', meta.reference || ''),
    single('Status', 'LABEL', 'New'),
    single('NumberOfSources', 'INTEGER', ldt.lampSets.length),
    single('TotalWattage', 'REAL', dv.wattage),
    single('LightFixtureMountingType', 'LABEL', meta.mounting || 'Recessed'),
    single('MaintenanceFactor', 'REAL', meta.maintenanceFactor || 0.8),
  ]));

  sets.push(pset('Pset_ManufacturerTypeInformation', [
    single('Manufacturer', 'LABEL', meta.manufacturer || ldt.company || ''),
    single('ModelLabel', 'LABEL', meta.model || ldt.luminaireName || ''),
    single('ModelReference', 'LABEL', meta.reference || ldt.luminaireNumber || ''),
    single('ProductionYear', 'LABEL', meta.year || ''),
  ]));

  sets.push(pset('BimBuilder_Photometric', [
    single('LuminousFlux', 'REAL', dv.luminousFlux),
    single('LuminaireWattage', 'REAL', dv.wattage),
    single('LuminousEfficacy', 'REAL', round(dv.efficacy, 1)),
    single('ColorTemperature', 'REAL', dv.colorTemp),
    single('ColorRenderingIndex', 'INTEGER', dv.cri),
    single('LampType', 'LABEL', dv.lampType || ''),
    single('DownwardFluxFraction', 'REAL', ldt.dff),
    single('LightOutputRatio', 'REAL', ldt.lorl),
    single('PhotometricSymmetry', 'INTEGER', ldt.Isym),
    single('LDTFileName', 'LABEL', meta.ldtFileName || ldt.fileName || ''),
    single('DatasheetReference', 'LABEL', meta.datasheetName || ''),
  ]));

  return sets;
}

// ---- helpers ----------------------------------------------------------
function fmt(n) {
  const v = Number(n) || 0;
  // STEP reals must contain a decimal point.
  return Number.isInteger(v) ? v + '.' : String(v);
}
function round(n, d) { const f = Math.pow(10, d); return Math.round((Number(n) || 0) * f) / f; }
function timestamp(now) { return now instanceof Date ? now.getTime() : Date.now(); }
function isoTimestamp(now) { return (now instanceof Date ? now : new Date()).toISOString().replace(/\.\d+Z$/, ''); }

/* IFC compressed 22-char GUID from 16 random bytes. */
export function guid() {
  const b = new Uint8Array(16);
  globalThis.crypto.getRandomValues(b);
  const chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_$';
  // Encode 16 bytes as 22 base64-ish chars in 6-bit groups (IFC scheme).
  let out = '';
  let acc = 0, bits = 0;
  for (let k = 0; k < 16; k++) {
    acc = (acc << 8) | b[k];
    bits += 8;
    while (bits >= 6) {
      bits -= 6;
      out += chars[(acc >> bits) & 0x3f];
    }
  }
  if (bits > 0) out += chars[(acc << (6 - bits)) & 0x3f];
  return out.slice(0, 22).padEnd(22, '0');
}
