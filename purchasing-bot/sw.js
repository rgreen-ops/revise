/* Service worker: offline support for the app shell. Network-first so updates
   land immediately when online; falls back to cache offline. Live Unleashed
   calls go to a cross-origin proxy and are deliberately NOT cached. Bump CACHE
   on each deploy to purge old files. */
var CACHE = 'purchasing-v1';
var ASSETS = ['./', './index.html', './app.js', './util.js', './store.js',
  './unleashed.js', './pricing.js', './replenish.js', './topups.js',
  './suppliers.js', './sample-data.js', './manifest.json', './icon.svg'];

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
  if (req.method !== 'GET') return;                                  // never cache writes to Unleashed
  if (new URL(req.url).origin !== self.location.origin) return;      // let the proxy / CDN requests pass through
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
