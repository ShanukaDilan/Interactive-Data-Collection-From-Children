import { storage } from './util.js';

const KEY = 'learningfun.prefs';

/** Per-device settings an adult changes from the parent sheet. */
const prefs = { calm: false, volume: 1, ...storage.get(KEY, {}) };

export function getPrefs() {
    return prefs;
}

export function setPref(name, value) {
    prefs[name] = value;
    storage.set(KEY, prefs);
    applyPrefs();
}

/** Calm mode: less motion, no confetti, softer cheers (CSS keys off `body.calm`). */
export function applyPrefs() {
    document.body.classList.toggle('calm', !!prefs.calm);
}

export function isCalm() {
    return !!prefs.calm || !!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
}
