/*
 * DigitalNest service worker.
 * - Static assets (/build, /fonts, /icons): cache first, they are versioned or immutable.
 * - Pages: always from the network (the site sends Cache-Control: no-store), with /offline as fallback.
 * - Never touched: non-GET requests, other origins, the admin panel, outbound /go links, Livewire and cookies.
 */
const VERSION = 'nest-v2';
const STATIC_CACHE = `${VERSION}-static`;
const PAGE_CACHE = `${VERSION}-pages`;
const OFFLINE_URL = '/offline';
const BYPASS = [/^\/admin/, /^\/go\//, /^\/livewire/, /^\/cookies/, /^\/filament/, /^\/storage\/.*\.(zip|pdf)$/];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(PAGE_CACHE).then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' }))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || BYPASS.some((pattern) => pattern.test(url.pathname))) {
        return;
    }

    if (/^\/(build|fonts|icons)\//.test(url.pathname)) {
        event.respondWith(
            caches.match(request).then((cached) => cached ?? fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
    }
});
