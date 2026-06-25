/* Parametric geometry for product families that are built from a constant
   cross-section swept along a path on the ceiling plane:
   - Estrella: straight linear runs joined into lines, L/U shapes, rectangles, crosses.
   - Flow: arcs joined into single arcs, semicircles, circles, S-curves and waves.

   We sweep a rectangular profile (width across the run, height hanging down) along
   one or more 2D paths and return a flat triangle mesh (vertices + indices, in mm)
   ready for both the three.js preview and the IFC exporter. No 3D upload needed.

   All paths live on the horizontal X/Y plane (the ceiling); the profile height
   extrudes downward in -Z. Units are millimetres throughout. */

// ---- preset catalogue -------------------------------------------------
export const FAMILIES = {
  estrella: {
    label: 'Estrella — linear',
    profile: { width: 70, height: 70 },     // typical extrusion section (mm)
    shapes: {
      line: { label: 'Single line', params: { length: 2400 } },
      l: { label: 'L-shape', params: { armA: 1800, armB: 1200 } },
      u: { label: 'U-shape', params: { width: 2400, depth: 1200 } },
      rectangle: { label: 'Rectangle / square', params: { width: 2400, depth: 1600 } },
      cross: { label: 'Cross', params: { armX: 2400, armY: 1800 } },
    },
  },
  flow: {
    label: 'Flow — arcs',
    profile: { width: 60, height: 60 },
    shapes: {
      arc: { label: 'Single arc', params: { radius: 1200, sweep: 120 } },
      semicircle: { label: 'Semicircle', params: { radius: 1200 } },
      circle: { label: 'Circle / ring', params: { radius: 1200 } },
      s: { label: 'S-curve', params: { radius: 900, sweep: 120 } },
      wave: { label: 'Wave', params: { radius: 700, sweep: 90, repeats: 3 } },
    },
  },
};

const ARC_STEP_DEG = 3; // arc tessellation (smaller = smoother curves)

// ---- path generators (return { paths:[[ [x,y], ... ] ...], closed:bool }) ----
export function buildPaths(family, shape, p) {
  if (family === 'estrella') return estrellaPaths(shape, p);
  if (family === 'flow') return flowPaths(shape, p);
  throw new Error('Unknown family ' + family);
}

function estrellaPaths(shape, p) {
  switch (shape) {
    case 'line':
      return { paths: [[[0, 0], [p.length, 0]]], closed: false };
    case 'l':
      return { paths: [[[0, 0], [p.armA, 0], [p.armA, p.armB]]], closed: false };
    case 'u':
      return { paths: [[[0, 0], [0, p.depth], [p.width, p.depth], [p.width, 0]]], closed: false };
    case 'rectangle': {
      const w = p.width, d = p.depth;
      return { paths: [[[0, 0], [w, 0], [w, d], [0, d], [0, 0]]], closed: true };
    }
    case 'cross': {
      const ax = p.armX / 2, ay = p.armY / 2;
      return { paths: [[[-ax, 0], [ax, 0]], [[0, -ay], [0, ay]]], closed: false };
    }
    default:
      throw new Error('Unknown Estrella shape ' + shape);
  }
}

function flowPaths(shape, p) {
  switch (shape) {
    case 'arc':
      return { paths: [arc(0, 0, p.radius, -p.sweep / 2, p.sweep / 2)], closed: false };
    case 'semicircle':
      return { paths: [arc(0, 0, p.radius, -90, 90)], closed: false };
    case 'circle':
      return { paths: [arc(0, 0, p.radius, 0, 360)], closed: true };
    case 's': {
      // two opposite arcs meeting tangentially
      const a1 = arc(0, 0, p.radius, 180 - p.sweep, 180);            // lower bend
      const end = a1[a1.length - 1];
      const cx2 = end[0], cy2 = end[1] + p.radius;                   // mirror centre above
      const a2 = arc(cx2, cy2, p.radius, -90, -90 + p.sweep);
      return { paths: [a1.concat(a2)], closed: false };
    }
    case 'wave': {
      const pts = [];
      let cx = 0;
      const span = p.radius * 2 * Math.sin((p.sweep * Math.PI / 180) / 2); // chord per bump
      for (let r = 0; r < p.repeats; r++) {
        const up = r % 2 === 0;
        const cy = up ? p.radius : -p.radius;
        const a = up ? arc(cx, cy, p.radius, 270 - p.sweep / 2, 270 + p.sweep / 2)
                     : arc(cx, cy, p.radius, 90 - p.sweep / 2, 90 + p.sweep / 2);
        const seg = up ? a : a.reverse();
        if (pts.length) seg.shift();
        pts.push(...seg);
        cx += span;
      }
      return { paths: [pts], closed: false };
    }
    default:
      throw new Error('Unknown Flow shape ' + shape);
  }
}

function arc(cx, cy, r, deg0, deg1) {
  const pts = [];
  const steps = Math.max(2, Math.ceil(Math.abs(deg1 - deg0) / ARC_STEP_DEG));
  for (let i = 0; i <= steps; i++) {
    const a = (deg0 + (deg1 - deg0) * (i / steps)) * Math.PI / 180;
    pts.push([cx + Math.cos(a) * r, cy + Math.sin(a) * r]);
  }
  return pts;
}

// ---- sweep a rectangular profile along the path(s) -> triangle mesh ----
export function buildMesh(family, shape, params) {
  const base = { ...FAMILIES[family].profile };
  if (params.profileWidth) base.width = params.profileWidth;
  if (params.profileHeight) base.height = params.profileHeight;
  const profile = housingProfile(base.width, base.height, params.lip);
  const { paths, closed } = buildPaths(family, shape, params);

  const vertices = [];
  const uvs = [];
  const body = [];
  const diffuser = [];
  const caps = [];
  for (const path of paths) sweepInto(path, profile, closed, vertices, uvs, body, diffuser, caps, base.width, base.height);
  // Group order: body (housing), diffuser (lit lens), cap (flat end plates — own
  // group so they can carry the engraved logo). The IFC exporter uses the full
  // `indices` list and is unaffected.
  const indices = body.concat(diffuser, caps);
  const groups = [
    { start: 0, count: body.length, kind: 'body' },
    { start: body.length, count: diffuser.length, kind: 'diffuser' },
    { start: body.length + diffuser.length, count: caps.length, kind: 'cap' },
  ];
  return { vertices, uvs, indices, groups, profile: base, closed };
}

/* The Flow aluminium extrusion cross-section: a square body with a recessed
   mounting channel along the TOP (ceiling) face — where the suspension wires /
   mounting accessories clip in — and a wide lit lens across the BOTTOM face.
   Returns ordered 2D points (u across the width, v vertical: 0 = top/ceiling
   face, -height = bottom) plus the material kind of the edge leaving each point. */
function housingProfile(width, height, lip) {
  const hw = width / 2;
  const sideLip = Math.min(Math.max(lip == null ? 2 : lip, 0), hw - 1);   // lip each side of the lens
  const chHalf = Math.min(width * 0.065, hw - 1);         // mounting-channel half width (slim)
  const chDepth = Math.min(height * 0.085, height - 1);   // mounting-channel depth (shallow)
  const pts = [];
  // Top face, left -> right, dipping into the central mounting channel.
  pts.push([-hw, 0]);                 // 0 top-left
  pts.push([-chHalf, 0]);             // 1 channel mouth (left)
  pts.push([-chHalf, -chDepth]);      // 2 channel floor (left)
  pts.push([chHalf, -chDepth]);       // 3 channel floor (right)
  pts.push([chHalf, 0]);              // 4 channel mouth (right)
  pts.push([hw, 0]);                  // 5 top-right
  pts.push([hw, -height]);            // 6 bottom-right
  // Bottom face, right -> left: lip, lens, lip.
  pts.push([hw - sideLip, -height]);  // 7 right lip — the lens starts here
  const lensStart = pts.length - 1;
  pts.push([-hw + sideLip, -height]); // 8 lens end (left)
  pts.push([-hw, -height]);           // 9 bottom-left (left wall closes back to 0)

  const kinds = new Array(pts.length).fill('body');
  kinds[lensStart] = 'diffuser';      // the wide bottom-centre edge is the lens
  // capOutline = the four outer corners (top-left, top-right, bottom-right,
  // bottom-left) used to fill the flat end plate as a solid square.
  return { points: pts.map(([u, v]) => ({ u, v })), kinds, capOutline: [0, 5, 6, 9] };
}

/* Sweep the profile along one path: at each path point lay down a ring of profile
   vertices, offset along the in-plane normal and mitred at corners so the width
   stays constant, then stitch a quad strip per profile edge between consecutive
   rings (routing the lens edge to the diffuser group) and fan-cap open ends. */
function sweepInto(path, profile, closed, vertices, uvs, body, diff, caps, width, height) {
  const n = path.length;
  if (n < 2) return;
  const P = profile.points;
  const K = profile.kinds;
  const m = P.length;

  const sub = (a, b) => [a[0] - b[0], a[1] - b[1]];
  const norm = (v) => { const d = Math.hypot(v[0], v[1]) || 1; return [v[0] / d, v[1] / d]; };

  const rings = [];
  let cum = 0;   // distance along the run, for the brushed-grain U coordinate
  for (let i = 0; i < n; i++) {
    if (i > 0) cum += Math.hypot(path[i][0] - path[i - 1][0], path[i][1] - path[i - 1][1]);
    const prev = path[i - 1] ?? (closed ? path[n - 2] : null);
    const nextP = path[i + 1] ?? (closed ? path[1] : null);
    const dIn = prev ? norm(sub(path[i], prev)) : null;
    const dOut = nextP ? norm(sub(nextP, path[i])) : null;
    const dir = dIn && dOut ? norm([dIn[0] + dOut[0], dIn[1] + dOut[1]]) : (dOut || dIn);
    const normal = [-dir[1], dir[0]];                       // in-plane left normal
    const segN = dOut ? [-dOut[1], dOut[0]] : [-dIn[1], dIn[0]];
    const cos = Math.max(0.35, Math.abs(normal[0] * segN[0] + normal[1] * segN[1]));
    const miter = 1 / cos;                                  // keep width constant at corners
    const [x, y] = path[i];
    const ru = cum / 40;                                   // brushed grain repeats every 40mm along the run
    const baseK = vertices.length / 3;
    for (let j = 0; j < m; j++) {
      const u = P[j].u * miter;
      vertices.push(x + normal[0] * u, y + normal[1] * u, P[j].v);
      uvs.push(ru, j / (m - 1));                            // U along the run, V around the profile
    }
    rings.push(baseK);
  }

  const quad = (arr, a, b, c, d) => { arr.push(a, b, c, a, c, d); };
  for (let i = 0; i < n - 1; i++) {
    const A = rings[i], B = rings[i + 1];
    for (let j = 0; j < m; j++) {
      const j2 = (j + 1) % m;
      quad(K[j] === 'diffuser' ? diff : body, A + j, A + j2, B + j2, B + j);
    }
  }
  if (!closed) {
    const dS = norm(sub(path[1], path[0]));            // run direction at the start
    const dE = norm(sub(path[n - 1], path[n - 2]));    // run direction at the end
    // The two caps face opposite ways: the end cap comes out mirrored, so flip its
    // logo horizontally; the start cap already reads correctly.
    cap(profile.capOutline, rings[0], vertices, uvs, body, caps, [-dS[0], -dS[1]], width, height, false);
    cap(profile.capOutline, rings[n - 1], vertices, uvs, body, caps, [dE[0], dE[1]], width, height, true);
  }
}

/* Solid 3 mm end plate: a separate rectangular cap that covers the open end of
   the extrusion and stands proud by its thickness, so it reads as a real
   bolted-on plate. The OUTER face carries the engraved logo + countersunk screws
   (textured, 'cap' group); the inner face and the four edge walls (the visible
   thickness) are plain metal ('body' group). `outward` is the unit run direction
   the plate stands proud along (in the X/Y plane). */
const CAP_THICKNESS = 3;   // mm
function cap(outline, ringBase, vertices, uvs, body, caps, outward, width, height, flip) {
  const base = vertices.length / 3;
  const ox = outward[0] * CAP_THICKNESS, oy = outward[1] * CAP_THICKNESS;
  const CORNER_UV = [[0, 1], [1, 1], [1, 0], [0, 0]];   // outline order: TL, TR, BR, BL
  // Inner corners (base+0..3) at the body end — plain; outer corners (base+4..7)
  // proud by the thickness — carry the texture UVs (flipped 180° on one cap).
  for (let c = 0; c < 4; c++) {
    const k = (ringBase + outline[c]) * 3;
    vertices.push(vertices[k], vertices[k + 1], vertices[k + 2]);
    uvs.push(0, 0);
  }
  for (let c = 0; c < 4; c++) {
    const k = (ringBase + outline[c]) * 3;
    vertices.push(vertices[k] + ox, vertices[k + 1] + oy, vertices[k + 2]);
    const cu = flip ? 1 - CORNER_UV[c][0] : CORNER_UV[c][0];   // horizontal mirror only
    uvs.push(cu, CORNER_UV[c][1]);
  }
  const I = (c) => base + c, O = (c) => base + 4 + c;
  caps.push(O(0), O(1), O(2), O(0), O(2), O(3));        // outer engraved face
  body.push(I(0), I(2), I(1), I(0), I(3), I(2));        // inner face (seals the end)
  for (let c = 0; c < 4; c++) {                          // four edge walls = the 3 mm thickness
    const d = (c + 1) % 4;
    body.push(I(c), I(d), O(d), I(c), O(d), O(c));
  }
}

/* Total centreline length (mm) of a preset — i.e. how much lit profile the run
   uses. This is what drives auto-photometry (length × lumens/metre). */
export function pathLength(family, shape, params) {
  const { paths } = buildPaths(family, shape, params);
  let total = 0;
  for (const path of paths) {
    for (let i = 1; i < path.length; i++) {
      total += Math.hypot(path[i][0] - path[i - 1][0], path[i][1] - path[i - 1][1]);
    }
  }
  return total;
}

/* Bounding-box dimensions (mm) of a generated mesh, for the IFC product data. */
export function meshBounds(mesh) {
  let minX = Infinity, minY = Infinity, minZ = Infinity, maxX = -Infinity, maxY = -Infinity, maxZ = -Infinity;
  for (let i = 0; i < mesh.vertices.length; i += 3) {
    minX = Math.min(minX, mesh.vertices[i]); maxX = Math.max(maxX, mesh.vertices[i]);
    minY = Math.min(minY, mesh.vertices[i + 1]); maxY = Math.max(maxY, mesh.vertices[i + 1]);
    minZ = Math.min(minZ, mesh.vertices[i + 2]); maxZ = Math.max(maxZ, mesh.vertices[i + 2]);
  }
  return {
    length: Math.round(maxX - minX),
    width: Math.round(maxY - minY),
    height: Math.round(maxZ - minZ),
  };
}
