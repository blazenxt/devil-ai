/* Devil AI service worker: lightweight PWA shell support. */
const DEVIL_CACHE = 'devil-ai-shell-v3';
const SHELL = [
  './',
  './index.php',
  './login.php',
  './app.php',
  './assets/logo.svg',
  './manifest.webmanifest'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(DEVIL_CACHE).then(cache => cache.addAll(SHELL)).catch(() => null));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key !== DEVIL_CACHE).map(key => caches.delete(key)))));
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const req = event.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin) { return; }
  if (url.pathname.includes('/api.php') || url.pathname.includes('/v1/')) { return; }
  event.respondWith(fetch(req).then(res => {
    const copy = res.clone();
    caches.open(DEVIL_CACHE).then(cache => cache.put(req, copy)).catch(() => null);
    return res;
  }).catch(() => caches.match(req).then(cached => cached || caches.match('./index.php'))));
});
