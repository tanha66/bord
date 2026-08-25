// Bordkhan Service Worker v8
const CACHE = 'bk-v8';
const PRECACHE = [
  '/',
  '/assets/style.css',
  '/assets/icon-192.png',
  '/assets/icon-512.png',
  '/manifest.webmanifest'
];

// Install: cache essential files
self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(cache => {
      return cache.addAll(PRECACHE).catch(err => {
        console.log('Precache failed (non-fatal):', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// Activate: clean old caches
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)));
    }).then(() => self.clients.claim())
  );
});

// Fetch: network-first with cache fallback
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  // Skip non-http(s) and cross-origin
  if (!e.request.url.startsWith('http')) return;
  
  e.respondWith(
    fetch(e.request).then(response => {
      // Cache successful responses
      if (response && response.status === 200) {
        const clone = response.clone();
        caches.open(CACHE).then(cache => cache.put(e.request, clone));
      }
      return response;
    }).catch(() => {
      // Fallback to cache
      return caches.match(e.request).then(cached => {
        return cached || (e.request.mode === 'navigate' ? caches.match('/') : new Response('Offline'));
      });
    })
  );
});

// Push notification display
self.addEventListener('push', e => {
  let data = {};
  try { data = e.data ? e.data.json() : {}; } catch(_) {}
  const title = data.title || 'بردخان';
  const options = {
    body: data.body || '',
    icon: data.icon || '/assets/icon-512.png',
    badge: '/assets/icon-192.png',
    vibrate: [100, 50, 100],
    data: { url: data.link || '/' },
    dir: 'rtl',
    lang: 'fa',
    tag: data.tag || ('bk-' + Date.now())
  };
  e.waitUntil(self.registration.showNotification(title, options));
});

// Notification click
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const c of list) {
        if (c.url.includes(url) && 'focus' in c) return c.focus();
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});
