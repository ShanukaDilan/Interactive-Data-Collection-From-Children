/**
 * Press-and-hold activation for adult-only controls, so a child's quick tap does nothing.
 * The button gets `.holding` while pressed (CSS animates the ring for `ms`).
 */
export function holdToActivate(button, onActivate, ms = 2000) {
    let timer = null;
    button.style.setProperty('--hold-ms', `${ms}ms`);

    const cancel = () => {
        clearTimeout(timer);
        timer = null;
        button.classList.remove('holding');
    };
    const begin = (e) => {
        e.preventDefault();
        if (timer) {
            return;
        }
        button.classList.add('holding');
        timer = setTimeout(() => {
            cancel();
            onActivate();
        }, ms);
    };

    button.addEventListener('pointerdown', begin);
    ['pointerup', 'pointerleave', 'pointercancel'].forEach((ev) => button.addEventListener(ev, cancel));
    button.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            begin(e);
        }
    });
    button.addEventListener('keyup', cancel);
    button.addEventListener('contextmenu', (e) => e.preventDefault());
    button.addEventListener('click', (e) => e.preventDefault());
}
