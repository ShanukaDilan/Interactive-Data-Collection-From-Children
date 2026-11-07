import { pick, storage } from './util.js';

const POOL = ['🐱', '🐶', '🐢', '🦄', '🚀', '🍩', '🦋', '🐙', '🦁', '🐼', '🌈', '🍓',
    '🐸', '🦖', '🐝', '🌻', '🍉', '🐧', '🚂', '🎈', '🐳', '🦊', '🍦', '⭐'];
const BG = ['#FFF0F0', '#FFFDF0', '#F0FFF4', '#F0F4FF', '#F8F0FF', '#FFF8F0'];

const keyFor = (participantId) => `learningfun.stickers.${participantId ?? 'anon'}`;

/** Give one new sticker for a finished session and return the whole collection. */
export function awardSticker(participantId) {
    const owned = storage.get(keyFor(participantId), []);
    const fresh = POOL.filter((s) => !owned.includes(s));
    const sticker = pick(fresh.length ? fresh : POOL);
    const collection = [...owned, sticker].slice(-POOL.length);
    storage.set(keyFor(participantId), collection);

    return { collection, sticker };
}

export function renderStickerBook(grid, counter, { collection, sticker }) {
    const slots = Math.max(12, Math.ceil(collection.length / 4) * 4);
    grid.innerHTML = '';
    counter.textContent = `${collection.length} / ${Math.max(slots, 12)}`;

    for (let i = 0; i < slots; i++) {
        const cell = document.createElement('div');
        const emoji = collection[i];
        if (!emoji) {
            cell.className = 'sticker empty';
        } else {
            const isNew = i === collection.length - 1 && emoji === sticker;
            cell.className = `sticker${isNew ? ' new' : ''}`;
            cell.style.background = isNew ? '' : BG[i % BG.length];
            cell.textContent = emoji;
            if (isNew) {
                const tag = document.createElement('span');
                tag.className = 'sticker-tag';
                tag.textContent = 'NEW';
                cell.appendChild(tag);
            }
        }
        grid.appendChild(cell);
    }
}
