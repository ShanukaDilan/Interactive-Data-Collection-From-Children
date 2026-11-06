import { storage } from './util.js';

const DEVICE_KEY = 'learningfun.device';

/**
 * This browser's registration as a classroom device: { id, name, token, userId }.
 * The token lets the tablet sync after the browser session has expired.
 */
export function getDevice() {
    return storage.get(DEVICE_KEY);
}

export function forgetDevice() {
    try {
        window.localStorage.removeItem(DEVICE_KEY);
    } catch {
        // storage unavailable — nothing to forget
    }
}

/**
 * Register this browser for the signed-in user (session + CSRF) and keep the token.
 */
export async function registerDevice({ url, name, csrfToken, userId }) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken ?? '',
        },
        body: JSON.stringify({ name }),
    });

    if (!response.ok) {
        throw new Error(response.status === 419 || response.status === 401 ? 'signed_out' : `HTTP ${response.status}`);
    }

    const { device, token } = await response.json();
    const record = { id: device.id, name: device.name, token, userId };

    if (!storage.set(DEVICE_KEY, record)) {
        throw new Error('storage');
    }

    return record;
}

/** A sensible default name, e.g. "Android tablet" or "iPad". */
export function suggestDeviceName() {
    const ua = navigator.userAgent;
    if (/iPad/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1)) {
        return 'iPad';
    }
    if (/iPhone/.test(ua)) {
        return 'iPhone';
    }
    if (/Android/.test(ua)) {
        return /Mobile/.test(ua) ? 'Android phone' : 'Android tablet';
    }
    return 'Classroom computer';
}
