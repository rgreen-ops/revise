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
  const profile = housingProfile(base.width, base.height);
  const { paths, closed } = buildPaths(family, shape, params);

  const vertices = [];
  const body = [];
  const diffuser = [];
  for (const path of paths) sweepInto(path, profile, closed, vertices, body, diffuser);
  // Body (housing) triangles first, then the down-facing lit lens (diffuser), so
  // the preview can give each its own material via geometry groups. The IFC
  // exporter just uses the full `indices` list and is unaffected.
  const indices = body.concat(diffuser);
  const groups = [
    { start: 0, count: body.length, kind: 'body' },
    { start: body.length, count: diffuser.length, kind: 'diffuser' },
  ];
  return { vertices, indices, groups, profile: base, closed };
}

/* A realistic extruded-aluminium cross-section: a rounded-rectangle housing with
   a central lens band across the bottom. Returns ordered 2D points (u across the
   width, v vertical with 0 at the ceiling face and -height at the bottom) plus
   the material kind of the edge leaving each point — the single bottom-centre
   edge is the lit lens ('diffuser'), everything else is housing ('body'). */
function housingProfile(width, height) {
  const hw = width / 2;
  const r = Math.min(hw, height) * 0.3;          // corner radius
  const lip = Math.max(2, (hw - r) * 0.35);      // housing lip beside the lens
  const seg = 4;                                 // points per rounded corner
  const pts = [];
  const arc = (cu, cv, a0, a1, skipFirst) => {
    for (let i = 0; i <= seg; i++) {
      if (skipFirst && i === 0) continue;
      const a = (a0 + (a1 - a0) * (i / seg)) * Math.PI / 180;
      pts.push([cu + Math.cos(a) * r, cv + Math.sin(a) * r]);
    }
  };
  pts.push([-hw, -r]);                            // left wall, top
  pts.push([-hw, -(height - r)]);                 // left wall, bottom
  arc(-hw + r, -(height - r), 180, 270, true);    // bottom-left corner -> (-hw+r,-height)
  pts.push([-hw + r + lip, -height]);             // lip; the lens starts at this point
  const lensStart = pts.length - 1;
  pts.push([hw - r - lip, -height]);              // lens span ends
  pts.push([hw - r, -height]);                    // right lip
  arc(hw - r, -(height - r), 270, 360, true);     // bottom-right corner -> (hw,-(height-r))
  pts.push([hw, -r]);                             // right wall, top
  arc(hw - r, -r, 0, 90, true);                   // top-right corner -> (hw-r,0)
  pts.push([-hw + r, 0]);                         // top edge
  arc(-hw + r, -r, 90, 180, true);                // top-left corner -> (-hw,-r) (== pts[0])
  pts.pop();                                      // drop the duplicate closing point

  const kinds = new Array(pts.length).fill('body');
  kinds[lensStart] = 'diffuser';                  // the central bottom edge is the lens
  return { points: pts.map(([u, v]) => ({ u, v })), kinds };
}

/* Sweep the profile along one path: at each path point lay down a ring of profile
   vertices, offset along the in-plane normal and mitred at corners so the width
   stays constant, then stitch a quad strip per profile edge between consecutive
   rings (routing the lens edge to the diffuser group) and fan-cap open ends. */
function sweepInto(path, profile, closed, vertices, body, diff) {
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

  const tri = (arr, a, b, c) => arr.push(a, b, c);
  const quad = (arr, a, b, c, d) => { arr.push(a, b, c, a, c, d); };
  for (let i = 0; i < n - 1; i++) {
    const A = rings[i], B = rings[i + 1];
    for (let j = 0; j < m; j++) {
      const j2 = (j + 1) % m;
      quad(K[j] === 'diffuser' ? diff : body, A + j, A + j2, B + j2, B + j);
    }
  }
  if (!closed) {
    cap(P, rings[0], vertices, body, true, tri);
    cap(P, rings[n - 1], vertices, body, false, tri);
  }
}

/* Triangulate one end of the sweep as a fan from the ring centroid (the profile
   is convex, so the fan is watertight). */
function cap(P, ringBase, vertices, body, flip, tri) {
  const m = P.length;
  let cx = 0, cy = 0, cz = 0;
  for (let j = 0; j < m; j++) {
    const k = (ringBase + j) * 3;
    cx += vertices[k]; cy += vertices[k + 1]; cz += vertices[k + 2];
  }
  const c = vertices.length / 3;
  vertices.push(cx / m, cy / m, cz / m);
  for (let j = 0; j < m; j++) {
    const j2 = (j + 1) % m;
    if (flip) tri(body, c, ringBase + j2, ringBase + j);
    else tri(body, c, ringBase + j, ringBase + j2);
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
