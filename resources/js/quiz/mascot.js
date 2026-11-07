/**
 * "Sunny", the quiz guide. One inline SVG; its mood is a CSS class on the wrapper:
 *  idle · talk (mouth moves while a prompt plays) · cheer (correct answer) · think (try again)
 *  · wave (loading / setup).
 */
const SVG = `
<svg viewBox="0 0 120 120" aria-hidden="true" focusable="false">
  <g class="m-rays" fill="#FFB347">
    ${Array.from({ length: 10 }, (_, i) => `<rect x="54" y="2" width="12" height="22" rx="6" transform="rotate(${i * 36} 60 60)"/>`).join('')}
  </g>
  <g class="m-body">
    <circle cx="60" cy="60" r="38" fill="#FFE66D" stroke="#F2B53A" stroke-width="3"/>
    <ellipse cx="44" cy="70" rx="7" ry="4.5" fill="#FF9A9A" opacity=".7"/>
    <ellipse cx="76" cy="70" rx="7" ry="4.5" fill="#FF9A9A" opacity=".7"/>
    <g class="m-eyes">
      <ellipse cx="47" cy="54" rx="5.5" ry="7" fill="#3A2E4F"/>
      <ellipse cx="73" cy="54" rx="5.5" ry="7" fill="#3A2E4F"/>
      <circle cx="49" cy="51" r="2" fill="#fff"/>
      <circle cx="75" cy="51" r="2" fill="#fff"/>
    </g>
    <path class="m-smile" d="M48 72 Q60 84 72 72" fill="none" stroke="#3A2E4F" stroke-width="4" stroke-linecap="round"/>
    <ellipse class="m-mouth" cx="60" cy="76" rx="8" ry="6" fill="#3A2E4F"/>
    <path class="m-hmm" d="M51 78 Q60 74 69 78" fill="none" stroke="#3A2E4F" stroke-width="4" stroke-linecap="round"/>
  </g>
</svg>`;

export function mountMascot(element, mood = 'idle') {
    if (!element) {
        return null;
    }
    element.classList.add('mascot');
    element.innerHTML = SVG;
    setMood(element, mood);

    return element;
}

export function setMood(element, mood) {
    if (!element) {
        return;
    }
    element.dataset.mood = mood;
}

/** Mount every `[data-mascot]` placeholder on the page with its initial mood. */
export function mountAllMascots() {
    document.querySelectorAll('[data-mascot]').forEach((el) => mountMascot(el, el.dataset.mascot || 'idle'));
}
