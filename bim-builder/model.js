/* 3D model loading + preview using three.js (loaded as ES modules from a CDN,
   see the importmap in index.html). Supports glTF/GLB, OBJ and STL. Returns a
   flat triangle mesh (vertices + indices, in millimetres) for IFC embedding and
   renders an interactive preview into the given canvas. */

import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OBJLoader } from 'three/addons/loaders/OBJLoader.js';
import { STLLoader } from 'three/addons/loaders/STLLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

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

/* Build a three.js object from a raw {vertices, indices} mesh (e.g. a generated
   preset shape) so it can go through the same preview path. */
export function meshToObject(mesh) {
  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.Float32BufferAttribute(mesh.vertices, 3));
  geo.setIndex(mesh.indices.slice());
  geo.computeVertexNormals();
  const mat = new THREE.MeshStandardMaterial({ color: 0xcfd6f0, metalness: 0.15, roughness: 0.45 });
  return new THREE.Mesh(geo, mat);
}

/* Render the loaded object into a canvas with orbit controls. */
export function preview(object, canvas) {
  if (viewer) viewer.dispose();

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  const resize = () => {
    const w = canvas.clientWidth || 480, h = canvas.clientHeight || 320;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  };

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(45, 1.5, 0.01, 100000);

  scene.add(new THREE.HemisphereLight(0xffffff, 0x33405e, 1.1));
  const key = new THREE.DirectionalLight(0xffffff, 1.4);
  key.position.set(1, 2, 1.5);
  scene.add(key);

  // Frame the object.
  const box = new THREE.Box3().setFromObject(object);
  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  const maxDim = Math.max(size.x, size.y, size.z) || 1;
  object.position.sub(center);
  scene.add(object);

  camera.position.set(maxDim * 1.4, maxDim * 1.1, maxDim * 1.8);
  camera.lookAt(0, 0, 0);

  const controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;

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
      renderer.dispose();
    },
  };
  return viewer;
}
