const CACHE = 'bordkhan-pwa-v5';
const ASSETS = ['/assets/style.css', '/assets/icon-192.png', '/assets/icon-512.png'];

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

  // v5.5: صفحات HTML هرگز کش نمی‌شوند (مشکل «لاگ‌اوت نمی‌شود» به‌خاطر کش صفحهٔ خانه بود)
  // فقط فایل‌های استاتیک کش می‌شوند.
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

  // بقیهٔ درخواست‌ها: مستقیم شبکه؛ fallback آفلاین فقط برای ریشه
  e.respondWith(
    fetch(req).catch(() => caches.match('/').then(r => r || Response.error()))
  );
});
