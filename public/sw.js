/*
 * Bahga service worker: makes the staff app installable and shows a friendly
 * page when offline.
 *
 * Privacy rule: pages and API responses carry children's data, so they are
 * NEVER cached. Only static files (built assets, icons, the offline page) are.
 */
const VERSION = 'bahga-v1';
const STATIC_CACHE = `${VERSION}-static`;
const PRECACHE = ['/offline.html', '/icons/icon-192.png', '/icons/icon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

const isStatic = (url) => url.origin === self.location.origin
    && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/offline.html');

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Pages: always from the network; the offline page when there is none.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    // Fingerprinted build assets and icons: cache first.
    if (isStatic(url)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
    }
    // Everything else (API, data): straight to the network, never stored.
});

// Web push (FCM "notification" payload): show it, and open the app on tap.
self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { notification: { body: event.data ? event.data.text() : '' } };
    }

    const notification = payload.notification || {};
    const data = payload.data || {};

    event.waitUntil(self.registration.showNotification(notification.title || 'بهجة', {
        body: notification.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        dir: 'rtl',
        lang: 'ar',
        tag: data.notification_id || undefined,
        data,
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = event.notification.data && event.notification.data.screen === 'attendance' ? '/app/attendance' : '/app';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((client) => new URL(client.url).pathname.startsWith('/app'));
            return open
                ? open.focus().then((client) => (client && 'navigate' in client ? client.navigate(target) : client))
                : self.clients.openWindow(target);
        }),
    );
});
