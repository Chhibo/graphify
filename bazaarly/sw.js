/* Bazaarly service worker — makes the site installable and keeps recent pages available offline. */
const VERSION = 'bzr-v1.1.0';
const SCOPE = self.registration.scope;
const OFFLINE = SCOPE + 'offline.php';
const STATIC_CACHE = VERSION + '-static';
const PAGE_CACHE = VERSION + '-pages';
const IMG_CACHE = VERSION + '-img';

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC_CACHE).then((c) => c.addAll([OFFLINE])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

async function trim(cacheName, max) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - max; i++) await cache.delete(keys[i]);
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin || !req.url.startsWith(SCOPE)) return;
  const path = url.pathname;
  // never cache private areas or dynamic endpoints
  if (/\/(admin|install)\//.test(path) || /(ajax|oauth|cron|logout|verify)\.php$/.test(path)) return;

  if (req.mode === 'navigate') {
    // pages: network first, fall back to the cached copy, then to the offline page
    const isPrivate = /\/dashboard\//.test(path);
    event.respondWith(
      fetch(req).then((res) => {
        if (res.ok && !isPrivate) {
          const copy = res.clone();
          caches.open(PAGE_CACHE).then((c) => c.put(req, copy)).then(() => trim(PAGE_CACHE, 30));
        }
        return res;
      }).catch(() => caches.match(req).then((r) => r || caches.match(OFFLINE)))
    );
    return;
  }

  if (/\/assets\//.test(path)) {
    // css, js, fonts: stale-while-revalidate
    event.respondWith(
      caches.open(STATIC_CACHE).then((cache) => cache.match(req).then((hit) => {
        const net = fetch(req).then((res) => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
        return hit || net;
      }))
    );
    return;
  }

  if (/\/uploads\//.test(path) && req.destination === 'image') {
    // photos never change once uploaded: cache first
    event.respondWith(
      caches.open(IMG_CACHE).then((cache) => cache.match(req).then((hit) => hit || fetch(req).then((res) => {
        if (res.ok) { cache.put(req, res.clone()); trim(IMG_CACHE, 120); }
        return res;
      })))
    );
  }
});
