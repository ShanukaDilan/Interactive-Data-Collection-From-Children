/**
 * Offline support: registers the service worker and pre-downloads question pictures and
 * sounds so a quiz can run with no connection.
 */
const MEDIA_CACHE = 'lf-media';

export async function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) {
        return false; // needs https (or localhost)
    }

    try {
        await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        return true;
    } catch (error) {
        console.warn('Service worker registration failed', error);
        return false;
    }
}

/** All picture / audio URLs a study bundle needs. */
export function mediaUrls(bundle) {
    const urls = new Set();
    for (const question of bundle?.questions ?? []) {
        [question.image, question.audio, ...(question.options ?? []).map((option) => option.image)]
            .filter(Boolean)
            .forEach((url) => urls.add(url));
    }
    return [...urls];
}

/**
 * Make sure every media file is in the offline cache. Returns how many are available offline.
 */
export async function cacheMedia(urls) {
    if (!('caches' in window) || urls.length === 0) {
        return { cached: 0, total: urls.length };
    }

    const cache = await caches.open(MEDIA_CACHE);
    let cached = 0;

    await Promise.all(urls.map(async (url) => {
        try {
            if (await cache.match(url)) {
                cached++;
                return;
            }
            const response = await fetch(url, { credentials: 'same-origin' });
            if (response.ok) {
                await cache.put(url, response);
                cached++;
            }
        } catch {
            // offline or missing file — the quiz falls back to emoji / text-to-speech
        }
    }));

    return { cached, total: urls.length };
}
