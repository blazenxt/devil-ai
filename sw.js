/* Devil AI service worker.
 * - Static files (assets/*, icons, manifest): served from cache instantly, refreshed in the background.
 *   Versioned bundles (?v=...) never go stale, because every deploy changes the URL.
 * - Pages: always from the network (they are per-user). Only the small public shell is
 *   kept for offline use — chat pages are NOT stored (older versions cached every chat page).
 * - API calls: never touched.
 */
const DEVIL_CACHE = 'devil-ai-v8';
const SHELL = ['./', './login.php', './assets/logo.svg', './manifest.webmanifest'];
const MAX_STATIC = 40;

self.addEventListener('install', event => {
  event.waitUntil(caches.open(DEVIL_CACHE).then(cache => cache.addAll(SHELL)).catch(() => null));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(key => key !== DEVIL_CACHE).map(key => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

function isStatic(url) {
  return /\/assets\//.test(url.pathname) || /\.(?:css|js|svg|png|jpe?g|gif|webp|ico|woff2?|webmanifest)$/i.test(url.pathname);
}

async function trimCache(cache) {
  const keys = await cache.keys();
  if (keys.length <= MAX_STATIC) { return; }
  for (const req of keys.slice(0, keys.length - MAX_STATIC)) { await cache.delete(req); }
}

async function staticFirst(event) {
  const req = event.request;
  const cache = await caches.open(DEVIL_CACHE);
  const cached = await cache.match(req);
  const refresh = fetch(req).then(res => {
    if (res.ok && !res.headers.get('X-Devil-AI-Blocked')) {
      cache.put(req, res.clone()).then(() => trimCache(cache)).catch(() => null);
    }
    return res;
  });
  if (cached) {
    /* versioned bundles are immutable: no need to re-download them */
    if (!new URL(req.url).searchParams.has('v')) { event.waitUntil(refresh.catch(() => null)); }
    return cached;
  }
  return refresh;
}

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') { return; }
  const url = new URL(req.url);
  if (url.origin !== location.origin) { return; }
  if (url.pathname.includes('/api.php') || url.pathname.includes('/v1/') || url.pathname.endsWith('/sw.js')) { return; }
  if (/\/developers(?:_|\.php|$)/.test(url.pathname)) { return; }

  if (isStatic(url)) {
    event.respondWith(staticFirst(event).catch(() => caches.match(req)));
    return;
  }
  if (req.mode === 'navigate') {
    /* pages: network only; offline → the cached public shell */
    event.respondWith(fetch(req).catch(() => caches.match(req).then(c => c || caches.match('./'))));
  }
});
