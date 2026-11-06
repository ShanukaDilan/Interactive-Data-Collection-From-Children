/**
 * RFC 4122 v4 UUID. `crypto.randomUUID` only exists in secure contexts (https / localhost),
 * so classroom tablets on a plain-http LAN address need the getRandomValues fallback.
 */
export function uuid() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    const bytes = window.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/** Fisher–Yates shuffle, in place. */
export function shuffle(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }

    return arr;
}

export function pick(items) {
    return items[Math.floor(Math.random() * items.length)];
}

/** localStorage that never throws (private mode, blocked storage, quota). */
export const storage = {
    get(key, fallback = null) {
        try {
            const raw = window.localStorage.getItem(key);
            return raw === null ? fallback : JSON.parse(raw);
        } catch {
            return fallback;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch {
            return false;
        }
    },
};
