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
  const capTris = triangulate(profile.points);   // flat end-cap triangulation (handles the concave channel)
  const { paths, closed } = buildPaths(family, shape, params);

  const vertices = [];
  const body = [];
  const diffuser = [];
  const caps = [];
  for (const path of paths) sweepInto(path, profile, capTris, closed, vertices, body, diffuser, caps);
  // Group order: body (housing), diffuser (lit lens), cap (flat end plates — own
  // group so they can carry the engraved logo). The IFC exporter uses the full
  // `indices` list and is unaffected.
  const indices = body.concat(diffuser, caps);
  const groups = [
    { start: 0, count: body.length, kind: 'body' },
    { start: body.length, count: diffuser.length, kind: 'diffuser' },
    { start: body.length + diffuser.length, count: caps.length, kind: 'cap' },
  ];
  return { vertices, indices, groups, profile: base, closed };
}

/* The Flow aluminium extrusion cross-section: a square body with a recessed
   mounting channel along the TOP (ceiling) face — where the suspension wires /
   mounting accessories clip in — and a wide lit lens across the BOTTOM face.
   Returns ordered 2D points (u across the width, v vertical: 0 = top/ceiling
   face, -height = bottom) plus the material kind of the edge leaving each point. */
function housingProfile(width, height, lip) {
  const hw = width / 2;
  const sideLip = Math.min(Math.max(lip == null ? 2 : lip, 0), hw - 1);   // lip each side of the lens
  const chHalf = Math.min(width * 0.22, hw - 1);          // mounting-channel half width
  const chDepth = Math.min(height * 0.28, height - 1);    // mounting-channel depth
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
  return { points: pts.map(([u, v]) => ({ u, v })), kinds };
}

/* Ear-clipping triangulation of the (possibly concave, due to the channel)
   cross-section, used to fill the flat end caps. Returns triangles as index
   triples into `points`. */
function triangulate(points) {
  const n = points.length;
  const V = [...Array(n).keys()];
  let area = 0;
  for (let i = 0; i < n; i++) {
    const a = points[i], b = points[(i + 1) % n];
    area += a.u * b.v - b.u * a.v;
  }
  if (area < 0) V.reverse();                       // normalise to CCW
  const cross = (o, a, b) => (a.u - o.u) * (b.v - o.v) - (a.v - o.v) * (b.u - o.u);
  const inside = (p, a, b, c) => {
    const d1 = cross(a, b, p), d2 = cross(b, c, p), d3 = cross(c, a, p);
    return !((d1 < 0 || d2 < 0 || d3 < 0) && (d1 > 0 || d2 > 0 || d3 > 0));
  };
  const tris = [];
  let guard = n * n + 10;
  while (V.length > 3 && guard-- > 0) {
    let clipped = false;
    for (let i = 0; i < V.length; i++) {
      const i0 = V[(i - 1 + V.length) % V.length], i1 = V[i], i2 = V[(i + 1) % V.length];
      const a = points[i0], b = points[i1], c = points[i2];
      if (cross(a, b, c) <= 0) continue;           // reflex/collinear — not an ear
      let ear = true;
      for (let j = 0; j < V.length; j++) {
        const vj = V[j];
        if (vj === i0 || vj === i1 || vj === i2) continue;
        if (inside(points[vj], a, b, c)) { ear = false; break; }
      }
      if (!ear) continue;
      tris.push([i0, i1, i2]);
      V.splice(i, 1);
      clipped = true;
      break;
    }
    if (!clipped) break;
  }
  if (V.length === 3) tris.push([V[0], V[1], V[2]]);
  return tris;
}

/* Sweep the profile along one path: at each path point lay down a ring of profile
   vertices, offset along the in-plane normal and mitred at corners so the width
   stays constant, then stitch a quad strip per profile edge between consecutive
   rings (routing the lens edge to the diffuser group) and fan-cap open ends. */
function sweepInto(path, profile, capTris, closed, vertices, body, diff, caps) {
  const n = path.length;
  if (n < 2) return;
  const P = profile.points;
  const K = profile.kinds;
  const m = P.length;

  const sub = (a, b) => [a[0] - b[0], a[1] - b[1]];
  const norm = (v) => { const d = Math.hypot(v[0], v[1]) || 1; return [v[0] / d, v[1] / d]; };

  const rings = [];
  for (let i = 0; i < n; i++) {
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
    const baseK = vertices.length / 3;
    for (let j = 0; j < m; j++) {
      const u = P[j].u * miter;
      vertices.push(x + normal[0] * u, y + normal[1] * u, P[j].v);
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
    cap(m, capTris, rings[0], vertices, caps);
    cap(m, capTris, rings[n - 1], vertices, caps);
  }
}

/* Flat end plate: duplicate the ring into its own vertices (so the cap gets a
   single flat normal rather than smearing into the side walls and looking domed),
   then emit the precomputed cross-section triangulation. Double-sided materials
   make the winding irrelevant. */
function cap(m, capTris, ringBase, vertices, caps) {
  const base = vertices.length / 3;
  for (let j = 0; j < m; j++) {
    const k = (ringBase + j) * 3;
    vertices.push(vertices[k], vertices[k + 1], vertices[k + 2]);
  }
  for (const t of capTris) caps.push(base + t[0], base + t[1], base + t[2]);
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
