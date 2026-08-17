const CACHE = 'bordkhan-pwa-v1';
const ASSETS = ['/', '/assets/style.css', '/assets/icon-192.png', '/assets/icon-512.png'];
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS))); self.skipWaiting(); });
self.addEventListener('activate', e => self.clients.claim());
self.addEventListener('fetch', e => {
  const req = e.request;
  const url = new URL(req.url);
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/admin') || url.pathname.startsWith('/wallet') || url.pathname.startsWith('/serve')) return;
  e.respondWith(
    caches.match(req).then(r => r || fetch(req).catch(() => caches.match('/')))
  );
});
