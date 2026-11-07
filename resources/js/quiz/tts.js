/**
 * Speech: uploaded question audio when present, otherwise the browser's text-to-speech
 * in the study's language. Ported from useTTS / playQuestion in legacy/index.php.
 */
import { getPrefs, isCalm } from './prefs.js';

export function createSpeaker(locale = 'en-US') {
    let currentAudio = null;

    function voiceFor(lang) {
        const voices = window.speechSynthesis?.getVoices() ?? [];
        const base = lang.split('-')[0];

        return voices.find((v) => v.lang === lang)
            ?? voices.find((v) => v.lang.replace('_', '-').startsWith(base))
            ?? null;
    }

    function stop() {
        window.speechSynthesis?.cancel();
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }
    }

    function say(text, onEnd) {
        if (!window.speechSynthesis || !text) {
            onEnd?.();
            return;
        }

        window.speechSynthesis.cancel();
        const utt = new SpeechSynthesisUtterance(text);
        utt.rate = 0.85;
        utt.pitch = 1.2;
        utt.volume = getPrefs().volume;
        utt.lang = locale;
        const voice = voiceFor(locale);
        if (voice) {
            utt.voice = voice;
        }
        utt.onend = () => onEnd?.();
        utt.onerror = () => onEnd?.();
        window.speechSynthesis.speak(utt);
    }

    /** Short praise / encouragement phrases; always English for now. */
    function cheer(text) {
        if (!window.speechSynthesis) {
            return;
        }
        window.speechSynthesis.cancel();
        const utt = new SpeechSynthesisUtterance(text);
        utt.rate = 0.9;
        utt.pitch = isCalm() ? 1.05 : 1.2;
        utt.volume = getPrefs().volume * (isCalm() ? 0.6 : 1);
        utt.lang = 'en-US';
        window.speechSynthesis.speak(utt);
    }

    /**
     * @param {(() => void)=} onEnd Called once when the prompt has finished (or could not play).
     */
    function playQuestion(question, button, onEnd) {
        stop();
        button?.classList.add('speaking');
        let finished = false;
        const done = () => {
            button?.classList.remove('speaking');
            if (!finished) {
                finished = true;
                onEnd?.();
            }
        };

        if (question.audio) {
            const audio = new Audio(question.audio);
            currentAudio = audio;
            audio.volume = getPrefs().volume;
            audio.onended = done;
            let fellBack = false;
            const fallback = () => {
                if (fellBack) {
                    return;
                }
                fellBack = true;
                currentAudio = null;
                say(question.speak, done);
            };
            audio.onerror = fallback;
            audio.play().catch(fallback);
            return;
        }

        say(question.speak, done);
    }

    return { say, cheer, playQuestion, stop };
}
