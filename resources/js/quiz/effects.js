/** Visual effects: bubbles, confetti, celebration overlay. */
import { isCalm } from './prefs.js';

const COLORS = ['#FF6B6B', '#4ECDC4', '#FFE66D', '#A8E6CF', '#C9B1FF', '#FFB347'];

export function buildBubbles(container) {
    for (let i = 0; i < 14; i++) {
        const b = document.createElement('div');
        b.className = 'bubble';
        const size = 40 + Math.random() * 120;
        b.style.cssText = `
            width:${size}px; height:${size}px;
            left:${Math.random() * 100}%;
            bottom:-20%;
            background:${COLORS[i % COLORS.length]};
            animation-duration:${8 + Math.random() * 12}s;
            animation-delay:${Math.random() * -15}s;
        `;
        container.appendChild(b);
    }
}

export function launchConfetti(fromElement) {
    if (isCalm()) {
        return;
    }

    const rect = fromElement.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;

    for (let i = 0; i < 24; i++) {
        const p = document.createElement('div');
        p.className = 'confetti-piece';
        const angle = Math.random() * 360;
        const dist = 60 + Math.random() * 120;
        const dx = Math.cos((angle * Math.PI) / 180) * dist;
        const dy = Math.sin((angle * Math.PI) / 180) * dist - 80;
        p.style.cssText = `
            left:${cx}px; top:${cy}px;
            background:${COLORS[i % COLORS.length]};
            transform:translate(${dx}px,${dy}px) rotate(${Math.random() * 360}deg);
            animation-duration:${0.8 + Math.random() * 0.5}s;
        `;
        document.body.appendChild(p);
        setTimeout(() => p.remove(), 1400);
    }
}

export function createFeedback(element) {
    const text = element.querySelector('.feedback-text') ?? element;
    return {
        show(message, cls) {
            text.textContent = message;
            element.className = `feedback-msg ${cls} show`;
        },
        hide() {
            element.classList.remove('show');
        },
    };
}

export function shake(element) {
    element.animate?.(
        [
            { transform: 'translateX(-6px)' },
            { transform: 'translateX(6px)' },
            { transform: 'translateX(-4px)' },
            { transform: 'translateX(4px)' },
            { transform: 'translateX(0)' },
        ],
        { duration: 350, easing: 'ease' },
    );
}
