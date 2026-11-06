/**
 * Phone-style number keypad for counting questions (ported from buildNumberKeypad).
 * Auto-submits once the typed number has as many digits as the correct answer.
 */
const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '⌫', '0', '✓'];

export function buildNumberKeypad({ keypad, display, correctAnswer, isLocked, onSubmit }) {
    let currentInput = '';

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
        setDisabled,
        reset() {
            currentInput = '';
            render();
            setDisabled(false);
        },
    };
}
