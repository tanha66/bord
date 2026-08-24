const CACHE = 'bordkhan-pwa-v8';
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

  // v5.11: رسانه (عکس/ویدیو) را هرگز رهگیری نمی‌کنیم — مستقیم از شبکه تا
  // پخش/جستجوی ویدیو (Range) روی موبایل بدون مشکل کار کند
  if (url.pathname.startsWith('/serve') || url.pathname.startsWith('/uploads/')) return;

  // v5.11: فایل‌های استاتیک stale-while-revalidate — کش قدیمی دیگر گیر نمی‌کند
  if (url.pathname.startsWith('/assets/')) {
    e.respondWith(
      caches.match(req).then(r => {
        const fetchPromise = fetch(req).then(res => {
          const copy = res.clone();
          caches.open(CACHE).then(c => c.put(req, copy));
          return res;
        }).catch(() => r);
        return r || fetchPromise;
      })
    );
    return;
  }

  // صفحات HTML: همیشه شبکه
  e.respondWith(
    fetch(req).catch(() => caches.match('/').then(r => r || Response.error()))
  );
});
