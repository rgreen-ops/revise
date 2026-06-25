/* Service worker: offline support for the app shell. Network-first so updates
   land immediately when online; falls back to cache offline. CDN-loaded three.js
   is cross-origin and not pre-cached, so 3D preview needs a connection.
   Bump CACHE on each deploy to purge old files. */
var CACHE = 'bimbuilder-v3';
var ASSETS = ['./', './index.html', './app.js', './ldt.js', './ifc.js', './model.js',
  './shapes.js', './datasheet.js', './batch.js', './zip.js', './products.js',
  './ldt-write.js', './flow-custom.js', './manifest.json', './icon.svg'];

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(CACHE).then(function (c) { return c.addAll(ASSETS); }).then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (ks) {
      return Promise.all(ks.map(function (k) { if (k !== CACHE) return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;
  if (new URL(req.url).origin !== self.location.origin) return; // let CDN requests pass through
  e.respondWith(
    fetch(req).then(function (res) {
      var copy = res.clone();
      caches.open(CACHE).then(function (c) { c.put(req, copy); });
      return res;
    }).catch(function () {
      return caches.match(req).then(function (r) { return r || caches.match('./index.html'); });
    })
  );
});
