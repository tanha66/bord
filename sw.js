const CACHE = 'bordkhan-pwa-v2';
const ASSETS = ['/', '/assets/style.css', '/assets/icon-192.png', '/assets/icon-512.png'];

// صفحاتی که محتوای شخصی دارند یا فرم دارند هرگز کش نمی‌شوند
const NO_CACHE_PREFIXES = ['/api/', '/admin', '/wallet', '/serve', '/tickets', '/notifications', '/settings', '/profile', '/upload', '/my-', '/bookmarks', '/favorites', '/login', '/register', '/verify', '/forgot', '/contact'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (NO_CACHE_PREFIXES.some(p => url.pathname.startsWith(p) || url.pathname === p.slice(0, -1))) return;

  // فایل‌های استاتیک: cache-first
  if (url.pathname.startsWith('/assets/')) {
    e.respondWith(
      caches.match(req).then(r => r || fetch(req).then(res => {
        const copy = res.clone();
        caches.open(CACHE).then(c => c.put(req, copy));
        return res;
      }))
    );
    return;
  }

  // صفحات HTML: شبکه-اول، در نبود شبکه از کش
  e.respondWith(
    fetch(req).then(res => {
      const copy = res.clone();
      caches.open(CACHE).then(c => c.put(req, copy));
      return res;
    }).catch(() => caches.match(req).then(r => r || caches.match('/')))
  );
});
