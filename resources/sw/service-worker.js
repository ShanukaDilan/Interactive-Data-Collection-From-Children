/*
 * Learning Fun! offline service worker.
 * Served by ServiceWorkerController, which fills in __VERSION__ and __PRECACHE__.
 *
 *  - /play (quiz page):        network first, cached copy when offline
 *  - /build/* (hashed assets): cache first (precached at install)
 *  - question media:           cache first (warmed by the quiz page)
 *  - study bundles / config:   network first, cached copy when offline
 *  - everything else:          untouched (admin, sync POSTs, login …)
 */
const VERSION = '__VERSION__';
const PRECACHE = __PRECACHE__;

const SHELL_CACHE = `lf-shell-${VERSION}`;
const PAGE_CACHE = 'lf-pages';
const DATA_CACHE = 'lf-data';
const MEDIA_CACHE = 'lf-media';

self.addEventListener('install', (event) => {
    // Cache each asset on its own (not cache.addAll): one failed font must not stop the quiz
    // from being available offline, and addAll is all-or-nothing.
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => Promise.allSettled(PRECACHE.map(async (url) => {
                if (await cache.match(url)) {
                    return;
                }
                const response = await fetch(url, { cache: 'no-cache' });
                if (response.ok) {
                    await cache.put(url, response);
                }
            })))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key.startsWith('lf-shell-') && key !== SHELL_CACHE).map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname === '/play' || url.pathname === '/play/') {
        event.respondWith(quizPage(request));
    } else if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request, SHELL_CACHE));
    } else if (url.pathname.startsWith('/storage/') || url.pathname.startsWith('/uploads/')) {
        event.respondWith(cacheFirst(request, MEDIA_CACHE));
    } else if (/^\/(api\/v1\/)?play\/(config|studies\/\d+\/bundle)$/.test(url.pathname) || /^\/api\/v1\/studies\/\d+\/bundle$/.test(url.pathname)) {
        event.respondWith(networkFirst(request, DATA_CACHE));
    }
});

/**
 * Store a copy without ever breaking the response: if the device's storage is full or
 * unavailable, the user still gets the network response.
 */
async function remember(cache, key, response) {
    try {
        await cache.put(key, response.clone());
    } catch (error) {
        console.warn('Learning Fun: could not cache', key, error);
    }
}

/** Quiz page: always try the network (fresh studies + CSRF); fall back to the last good copy. */
async function quizPage(request) {
    const cache = await caches.open(PAGE_CACHE);

    let response;
    try {
        response = await fetch(request);
    } catch {
        const cached = await cache.match('/play');
        return cached ?? offlineFallback();
    }

    // Only keep the real quiz page — never a redirect to the login screen.
    if (response.ok && !response.redirected && new URL(response.url).pathname.replace(/\/$/, '') === '/play') {
        await remember(cache, '/play', response);
    }
    return response;
}

async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request, { ignoreVary: true });
    if (cached) {
        return cached;
    }

    let response;
    try {
        response = await fetch(request);
    } catch {
        return new Response('', { status: 504, statusText: 'Offline' });
    }

    if (response.ok) {
        await remember(cache, request, response);
    }
    return response;
}

async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);

    let response;
    try {
        response = await fetch(request);
    } catch {
        response = null;
    }

    if (response) {
        if (response.ok) {
            await remember(cache, request, response);
        }
        return response;
    }

    const cached = await cache.match(request, { ignoreVary: true });
    return cached ?? new Response(JSON.stringify({ message: 'Offline' }), {
        status: 503,
        headers: { 'Content-Type': 'application/json' },
    });
}

function offlineFallback() {
    return new Response(
        '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        + '<title>Learning Fun! — offline</title>'
        + '<body style="font-family:system-ui,sans-serif;background:#FFF5E4;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center;padding:24px">'
        + '<div><div style="font-size:64px">🌟</div><h1 style="color:#FF6B6B">You are offline</h1>'
        + '<p>Open the quiz once while online on this device, then it will work without internet.</p></div>',
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } },
    );
}
