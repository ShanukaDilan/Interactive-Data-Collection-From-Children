/**
 * Answer input for counting questions.
 *  - "tiles": one big button per number 1–10; a tap submits (for answers up to 10).
 *  - "keypad": phone-style keypad (ported from buildNumberKeypad) that auto-submits once the
 *    typed number has as many digits as the correct answer.
 */
const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '⌫', '0', '✓'];
const TILE_COLORS = ['#FF6B6B', '#FF8E3C', '#E8B500', '#2EAE6B', '#2BBCB3', '#5B7BE0', '#9B6BE0', '#E05BA6', '#FF6B6B', '#2EAE6B'];

export function buildNumberKeypad({ keypad, display, correctAnswer, isLocked, onSubmit }) {
    const answer = Number.parseInt(correctAnswer, 10);
    const useTiles = Number.isInteger(answer) && answer >= 1 && answer <= 10;
    return useTiles
        ? buildTiles({ keypad, display, isLocked, onSubmit })
        : buildKeys({ keypad, display, correctAnswer, isLocked, onSubmit });
}

function buildTiles({ keypad, display, isLocked, onSubmit }) {
    keypad.innerHTML = '';
    keypad.className = 'number-keypad number-tiles';
    display.hidden = true;

    for (let n = 1; n <= 10; n++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'num-btn num-tile';
        btn.textContent = String(n);
        btn.dataset.value = String(n);
        btn.style.setProperty('--tile', TILE_COLORS[n - 1]);
        btn.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            if (!isLocked() && !btn.classList.contains('faded')) {
                btn.classList.add('picked');
                onSubmit(String(n), btn);
            }
        });
        keypad.appendChild(btn);
    }

    const tiles = () => keypad.querySelectorAll('.num-tile');
    const byValue = (v) => keypad.querySelector(`.num-tile[data-value="${v}"]`);

    return {
        tiles: true,
        setDisabled(disabled) {
            tiles().forEach((b) => { b.disabled = disabled; });
        },
        reset() {
            tiles().forEach((b) => { b.disabled = false; b.classList.remove('picked', 'chosen', 'correct'); });
        },
        markWrong(value) {
            byValue(value)?.classList.add('faded');
        },
        markCorrect(value) {
            byValue(value)?.classList.add('correct');
        },
        markChosen(value) {
            byValue(value)?.classList.add('chosen');
        },
        hint(value) {
            tiles().forEach((b) => b.classList.toggle('faded', b.dataset.value !== value));
            byValue(value)?.classList.add('hinted');
        },
    };
}

function buildKeys({ keypad, display, correctAnswer, isLocked, onSubmit }) {
    let currentInput = '';
    keypad.className = 'number-keypad';
    display.hidden = false;

    const render = () => {
        display.textContent = currentInput || '?';
        display.className = 'number-display' + (currentInput ? ' has-value' : '');
    };

    const setDisabled = (disabled) => {
        keypad.querySelectorAll('.num-btn').forEach((b) => {
            b.disabled = disabled;
        });
    };

    keypad.innerHTML = '';
    render();

    KEYS.forEach((key) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'num-btn';
        btn.textContent = key;
        if (key === '⌫') {
            btn.classList.add('num-del');
            btn.setAttribute('aria-label', 'Delete');
        }
        if (key === '✓') {
            btn.classList.add('num-ok');
            btn.setAttribute('aria-label', 'Done');
        }

        btn.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            if (isLocked()) {
                return;
            }

            if (key === '⌫') {
                currentInput = currentInput.slice(0, -1);
                render();
                return;
            }

            if (key === '✓') {
                if (currentInput) {
                    onSubmit(currentInput);
                }
                return;
            }

            if (currentInput.length >= 2) {
                return;
            }
            currentInput += key;
            render();

            if (currentInput.length >= correctAnswer.length) {
                onSubmit(currentInput);
            }
        });

        keypad.appendChild(btn);
    });

    return {
        tiles: false,
        setDisabled,
        reset() {
            currentInput = '';
            render();
            setDisabled(false);
        },
        markWrong() {},
        markCorrect() {},
        markChosen() {},
        hint() {},
    };
}
