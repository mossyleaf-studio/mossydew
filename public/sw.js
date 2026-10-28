const STATIC_CACHE = 'mossydew-static-v2';
const SHELL_CACHE = 'mossydew-shell-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => ![STATIC_CACHE, SHELL_CACHE].includes(key)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

async function cacheFirst(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok) cache.put(request, response.clone());
    return response;
}

async function networkFirst(request) {
    const cache = await caches.open(SHELL_CACHE);
    try {
        const response = await fetch(request);
        if (response.ok && !response.redirected) cache.put(request, response.clone());
        return response;
    } catch (error) {
        return (await cache.match(request)) ?? (await cache.match('/')) ?? Response.error();
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(cacheFirst(request));
    } else if (request.mode === 'navigate' && !url.pathname.startsWith('/login')) {
        event.respondWith(networkFirst(request));
    }
});
