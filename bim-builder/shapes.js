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

const ARC_STEP_DEG = 6; // arc tessellation

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
  const prof = { ...FAMILIES[family].profile };
  if (params.profileWidth) prof.width = params.profileWidth;
  if (params.profileHeight) prof.height = params.profileHeight;
  const { paths, closed } = buildPaths(family, shape, params);

  const vertices = [];
  const indices = [];
  for (const path of paths) sweepInto(path, prof.width, prof.height, closed, vertices, indices);
  return { vertices, indices, profile: prof, closed };
}

/* Sweep one path. For each path point we build a 4-vertex ring (top/bottom ×
   left/right) offset along the in-plane normal, mitred at corners so width stays
   constant, then stitch quads between consecutive rings and cap open ends. */
function sweepInto(path, width, height, closed, vertices, indices) {
  const n = path.length;
  if (n < 2) return;
  const hw = width / 2;
  const baseV = vertices.length / 3;

  const sub = (a, b) => [a[0] - b[0], a[1] - b[1]];
  const norm = (v) => { const m = Math.hypot(v[0], v[1]) || 1; return [v[0] / m, v[1] / m]; };

  const rings = [];
  for (let i = 0; i < n; i++) {
    const prev = path[i - 1] ?? (closed ? path[n - 2] : null);
    const nextP = path[i + 1] ?? (closed ? path[1] : null);
    let dIn = prev ? norm(sub(path[i], prev)) : null;
    let dOut = nextP ? norm(sub(nextP, path[i])) : null;
    const dir = dIn && dOut ? norm([dIn[0] + dOut[0], dIn[1] + dOut[1]]) : (dOut || dIn);
    const normal = [-dir[1], dir[0]];               // in-plane left normal
    // miter scale so offset edges stay parallel to the segments
    const segN = dOut ? [-dOut[1], dOut[0]] : [-dIn[1], dIn[0]];
    const cos = Math.max(0.35, Math.abs(normal[0] * segN[0] + normal[1] * segN[1]));
    const off = hw / cos;
    const [x, y] = path[i];
    const lx = x + normal[0] * off, ly = y + normal[1] * off;   // left edge
    const rx = x - normal[0] * off, ry = y - normal[1] * off;   // right edge
    // 4 verts: topLeft, topRight, bottomLeft, bottomRight (z down = -height)
    const k = vertices.length / 3;
    vertices.push(lx, ly, 0, rx, ry, 0, lx, ly, -height, rx, ry, -height);
    rings.push(k);
  }

  const quad = (a, b, c, d) => { indices.push(a, b, c, a, c, d); };
  const segCount = closed ? n - 1 : n - 1;
  for (let i = 0; i < segCount; i++) {
    const A = rings[i], B = rings[i + 1];
    const [aTL, aTR, aBL, aBR] = [A, A + 1, A + 2, A + 3];
    const [bTL, bTR, bBL, bBR] = [B, B + 1, B + 2, B + 3];
    quad(aTL, aTR, bTR, bTL);   // top
    quad(aBR, aBL, bBL, bBR);   // bottom
    quad(aBL, aTL, bTL, bBL);   // left side
    quad(aTR, aBR, bBR, bTR);   // right side
  }
  if (!closed) {
    const S = rings[0], E = rings[n - 1];
    quad(S + 2, S + 3, S + 1, S);            // start cap
    quad(E, E + 1, E + 3, E + 2);            // end cap
  }
  return baseV;
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
