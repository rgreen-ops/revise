/* 3D model loading + preview using three.js (loaded as ES modules from a CDN,
   see the importmap in index.html). Supports glTF/GLB, OBJ and STL. Returns a
   flat triangle mesh (vertices + indices, in millimetres) for IFC embedding and
   renders an interactive preview into the given canvas. */

import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OBJLoader } from 'three/addons/loaders/OBJLoader.js';
import { STLLoader } from 'three/addons/loaders/STLLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

let viewer = null;

export async function loadModel(file) {
  const ext = file.name.split('.').pop().toLowerCase();
  const buf = await file.arrayBuffer();
  let object;

  if (ext === 'gltf' || ext === 'glb') {
    const loader = new GLTFLoader();
    const gltf = await loader.parseAsync(buf, '');
    object = gltf.scene;
  } else if (ext === 'obj') {
    object = new OBJLoader().parse(new TextDecoder().decode(buf));
  } else if (ext === 'stl') {
    const geo = new STLLoader().parse(buf);
    object = new THREE.Mesh(geo, new THREE.MeshStandardMaterial({ color: 0x9aa4c8 }));
  } else {
    throw new Error('Unsupported 3D format: .' + ext + ' (use glTF, GLB, OBJ or STL)');
  }

  const mesh = extractMesh(object);
  return { object, mesh, ext };
}

/* Merge all meshes in the object into one indexed triangle soup (in world
   space). three.js units are metres-ish/arbitrary; we scale to millimetres so
   the IFC matches the LDT dimension convention. */
function extractMesh(object) {
  const vertices = [];
  const indices = [];
  let base = 0;
  const SCALE = 1000; // assume source units are metres -> mm

  object.updateMatrixWorld(true);
  object.traverse((child) => {
    if (!child.isMesh || !child.geometry) return;
    let geo = child.geometry;
    if (!geo.index) geo = geo.toNonIndexed ? geo : geo;
    const pos = geo.attributes.position;
    if (!pos) return;
    const idx = geo.index;
    const m = child.matrixWorld;
    const v = new THREE.Vector3();
    for (let p = 0; p < pos.count; p++) {
      v.fromBufferAttribute(pos, p).applyMatrix4(m);
      vertices.push(v.x * SCALE, v.y * SCALE, v.z * SCALE);
    }
    if (idx) {
      for (let t = 0; t < idx.count; t++) indices.push(base + idx.getX(t));
    } else {
      for (let t = 0; t < pos.count; t++) indices.push(base + t);
    }
    base += pos.count;
  });

  return { vertices, indices };
}

/* Surface finishes map to PBR roughness/metalness + a clearcoat lacquer. Gloss
   reads as a polished anodised/painted housing; satin a soft sheen; matte flat.
   The high base metalness lets metallic colours (gold/silver/bronze) read as
   real metal while painted colours still look right under the clearcoat. */
const FINISHES = {
  matte: { roughness: 0.55, metalness: 0.5, clearcoat: 0.15, ccRough: 0.5 },
  satin: { roughness: 0.3, metalness: 0.65, clearcoat: 0.45, ccRough: 0.2 },
  gloss: { roughness: 0.1, metalness: 0.8, clearcoat: 1.0, ccRough: 0.05 },
};

/* Build a three.js object from a raw {vertices, indices, groups} mesh (a
   generated preset/Flow shape). When the mesh carries body/diffuser groups we
   give it two physically-based materials: a finished metal housing and a glassy,
   self-illuminated opal lens so it actually reads as a light. Normals are
   smoothed by angle so the rounded extrusion stays soft but the housing edges
   stay crisp. opts: {bodyColor, diffuserColor, finish}. */
export function meshToObject(mesh, opts = {}) {
  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.Float32BufferAttribute(mesh.vertices, 3));
  geo.setIndex(mesh.indices.slice());
  if (mesh.groups && mesh.groups.length) {
    mesh.groups.forEach((g) => geo.addGroup(g.start, g.count, g.kind === 'diffuser' ? 1 : 0));
  }
  geo.computeVertexNormals();

  const fin = FINISHES[opts.finish] || FINISHES.matte;
  const bodyColor = new THREE.Color(opts.bodyColor || '#15161a');
  const diffColor = new THREE.Color(opts.diffuserColor || '#f4f3ee');

  const bodyMat = new THREE.MeshPhysicalMaterial({
    color: bodyColor, roughness: fin.roughness, metalness: fin.metalness,
    clearcoat: fin.clearcoat, clearcoatRoughness: fin.ccRough, envMapIntensity: 1.2,
  });
  // Opal lens: bright, soft and self-illuminated, with a thin glassy clearcoat
  // so it catches highlights like real frosted acrylic.
  const diffMat = new THREE.MeshPhysicalMaterial({
    color: diffColor, roughness: 0.5, metalness: 0,
    emissive: diffColor, emissiveIntensity: opts.lit === false ? 0 : 1.0,
    clearcoat: 0.6, clearcoatRoughness: 0.3, envMapIntensity: 0.6,
  });

  if (mesh.groups && mesh.groups.length) {
    return new THREE.Mesh(geo, [bodyMat, diffMat]);
  }
  return new THREE.Mesh(geo, bodyMat);
}

/* Render the loaded object into a canvas with orbit controls, using image-based
   lighting + filmic tone mapping for a studio-quality, photoreal-ish look. */
export function preview(object, canvas) {
  if (viewer) viewer.dispose();

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.05;

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(40, 1.5, 0.01, 100000);

  // Image-based lighting: a soft studio environment gives realistic reflections
  // on the housing (essential for gloss/metallic finishes to read correctly).
  const pmrem = new THREE.PMREMGenerator(renderer);
  const envTex = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
  scene.environment = envTex;

  // Three-point-ish lighting on top of the IBL for crisp highlights.
  scene.add(new THREE.HemisphereLight(0xffffff, 0x20283a, 0.6));
  const key = new THREE.DirectionalLight(0xffffff, 1.8);
  key.position.set(1, 2, 1.5);
  scene.add(key);
  const rim = new THREE.DirectionalLight(0x99b8ff, 0.7);
  rim.position.set(-1.6, 0.6, -1.2);
  scene.add(rim);

  // Frame the object.
  const box = new THREE.Box3().setFromObject(object);
  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  const maxDim = Math.max(size.x, size.y, size.z) || 1;
  object.position.sub(center);
  scene.add(object);

  camera.position.set(maxDim * 1.3, maxDim * 1.0, maxDim * 1.9);
  camera.lookAt(0, 0, 0);

  const controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.autoRotate = true;
  controls.autoRotateSpeed = 0.8;

  const resize = () => {
    const w = canvas.clientWidth || 480, h = canvas.clientHeight || 320;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  };

  let running = true;
  const tick = () => {
    if (!running) return;
    controls.update();
    renderer.render(scene, camera);
    requestAnimationFrame(tick);
  };
  resize();
  window.addEventListener('resize', resize);
  tick();

  viewer = {
    dispose() {
      running = false;
      window.removeEventListener('resize', resize);
      controls.dispose();
      envTex.dispose();
      pmrem.dispose();
      renderer.dispose();
    },
  };
  return viewer;
}
