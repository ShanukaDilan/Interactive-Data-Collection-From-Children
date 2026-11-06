import { createFeedback, launchConfetti } from './effects.js';
import { buildNumberKeypad } from './keypad.js';
import { pick, shuffle, uuid } from './util.js';

const PRAISE = ['Wonderful!', 'Amazing!', 'Super!', 'You got it!'];

/**
 * Runs one quiz session on the quiz screen. Ported from loadQuestion / handleAnswer /
 * submitNumberAnswer / showComplete in legacy/index.php.
 *
 * Modes:
 *  - "correct": wrong taps shake and retry; one answer is recorded when the child gets it right,
 *    with the number of attempts.
 *  - "free": the first tap is recorded and the quiz moves on, with no right/wrong feedback.
 */
export class QuizEngine {
    /**
     * @param {{ speaker: ReturnType<import('./tts.js').createSpeaker>, onAnswer: (response: object) => void, onComplete: (summary: {score: number, total: number}) => void }} options
     */
    constructor({ speaker, onAnswer, onComplete }) {
        this.speaker = speaker;
        this.onAnswer = onAnswer;
        this.onComplete = onComplete;
        this.timers = [];
        this.playToken = 0;

        this.el = {
            progressFill: document.getElementById('progressFill'),
            progressLabel: document.getElementById('progressLabel'),
            starsScore: document.getElementById('starsScore'),
            modePill: document.getElementById('modePill'),
            speakBtn: document.getElementById('speakBtn'),
            questionImage: document.getElementById('questionImage'),
            questionEmoji: document.getElementById('questionEmojiDisplay'),
            questionText: document.getElementById('questionText'),
            categoryBadge: document.getElementById('categoryBadge'),
            optionsGrid: document.getElementById('optionsGrid'),
            numberWrap: document.getElementById('numberInputWrap'),
            numberDisplay: document.getElementById('numberDisplay'),
            numberKeypad: document.getElementById('numberKeypad'),
            completeEmoji: document.getElementById('completeEmoji'),
            completeTitle: document.getElementById('completeTitle'),
            scoreDisplay: document.getElementById('scoreDisplay'),
            scoreStars: document.getElementById('scoreStars'),
        };
        this.feedback = createFeedback(document.getElementById('feedbackMsg'));

        this.el.speakBtn.addEventListener('click', () => this.replay());
    }

    /**
     * @param {{ questions: object[], mode: 'correct'|'free', shuffleOptions: boolean }} settings
     */
    start({ questions, mode, shuffleOptions }) {
        this.stop();
        this.state = {
            questions,
            mode,
            shuffleOptions,
            current: 0,
            score: 0,
            attempts: 0,
            answered: false,
            questionStartTime: 0,
        };

        const pill = this.el.modePill;
        pill.textContent = mode === 'free' ? '📝 Free Choice' : '🎯 Find the Answer';
        pill.className = `mode-pill ${mode === 'free' ? 'mode-pill-free' : 'mode-pill-correct'}`;

        this.loadQuestion();
    }

    /** Cancel pending transitions and speech (e.g. when the adult exits). */
    stop() {
        this.timers.forEach(clearTimeout);
        this.timers = [];
        this.feedback.hide();
        this.speaker.stop();
    }

    later(fn, ms) {
        this.timers.push(setTimeout(fn, ms));
    }

    get question() {
        return this.state.questions[this.state.current];
    }

    /**
     * Play the spoken prompt. Manual taps on 🔊 count as replays; the first time the prompt
     * finishes is recorded so taps can be classed as "before the question was fully heard".
     */
    replay(isAutomatic = false) {
        const s = this.state;
        if (!s || !this.question) {
            return;
        }
        if (!isAutomatic && !s.answered) {
            s.replays++;
        }

        const token = ++this.playToken;
        const questionIndex = s.current;
        this.speaker.playQuestion(this.question, this.el.speakBtn, () => {
            // Ignore end events from prompts that were cancelled or belong to an earlier question.
            if (token !== this.playToken || questionIndex !== s.current) {
                return;
            }
            s.promptEndedMs ??= Date.now() - s.questionStartTime;
        });
    }

    elapsed() {
        return Date.now() - this.state.questionStartTime;
    }

    /** Log one tap / keypad submission for behavioural analysis. */
    logTap({ label, number = null, position = null, isCorrect }) {
        this.state.taps.push({
            selected_label: label,
            selected_number: number,
            screen_position: position,
            is_correct: isCorrect,
            elapsed_ms: this.elapsed(),
            during_prompt: this.state.promptEndedMs === null,
        });
    }

    loadQuestion() {
        const s = this.state;
        const q = this.question;
        s.questionStartTime = Date.now();
        s.attempts = 0;
        s.answered = false;
        s.taps = [];
        s.replays = 0;
        s.promptEndedMs = null;
        s.optionOrder = null;
        s.selectedPosition = null;

        this.el.progressFill.style.width = `${(s.current / s.questions.length) * 100}%`;
        this.el.progressLabel.textContent = `${s.current + 1} / ${s.questions.length}`;
        this.updateStars();

        this.el.questionText.textContent = q.hint || q.speak;
        this.el.categoryBadge.textContent = q.category;

        const img = this.el.questionImage;
        if (q.image) {
            img.src = q.image;
            img.style.display = '';
        } else {
            img.style.display = 'none';
            img.removeAttribute('src');
        }

        const emoji = this.el.questionEmoji;
        if (q.question_emoji) {
            emoji.textContent = q.question_emoji;
            emoji.style.display = '';
            const codepoints = [...q.question_emoji].length;
            emoji.className = `question-emoji-display ${codepoints <= 1 ? 'qemoji-letter' : 'qemoji-count'}${codepoints >= 6 ? ' qemoji-many' : ''}`;
        } else {
            emoji.style.display = 'none';
            emoji.textContent = '';
        }

        const isNumber = q.type === 'number-input';
        this.el.optionsGrid.style.display = isNumber ? 'none' : '';
        this.el.numberWrap.style.display = isNumber ? '' : 'none';

        if (isNumber) {
            this.buildKeypad(q);
        } else {
            this.buildOptions(q);
        }

        this.later(() => this.replay(true), 600);
    }

    buildOptions(q) {
        const grid = this.el.optionsGrid;
        grid.innerHTML = '';
        grid.classList.toggle('cols-3', q.options.length === 3 || q.options.length === 6 || q.options.length === 5);

        const options = this.state.shuffleOptions ? shuffle([...q.options]) : [...q.options];
        this.state.optionOrder = options.map((opt) => opt.label);

        options.forEach((opt, position) => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'option-card';
            card.style.background = opt.bg || '#F0F4FF';
            card.setAttribute('aria-label', opt.label);

            if (opt.image) {
                const img = document.createElement('img');
                img.className = 'option-image';
                img.src = opt.image;
                img.alt = '';
                card.appendChild(img);
            } else {
                const emojiEl = document.createElement('div');
                emojiEl.className = 'option-emoji';
                emojiEl.textContent = opt.emoji || '❓';
                card.appendChild(emojiEl);
            }

            const label = document.createElement('div');
            label.className = 'option-label';
            label.textContent = opt.label;
            card.appendChild(label);

            card.addEventListener('pointerdown', (e) => {
                e.preventDefault();
                this.handleChoice(card, opt, position);
            });
            grid.appendChild(card);
        });
    }

    buildKeypad(q) {
        const correctAnswer = String(q.correct_number ?? q.correct_label ?? '');
        this.keypad = buildNumberKeypad({
            keypad: this.el.numberKeypad,
            display: this.el.numberDisplay,
            correctAnswer,
            isLocked: () => this.state.answered,
            onSubmit: (input) => this.handleNumber(input, correctAnswer),
        });
    }

    record(q, { selectedLabel, selectedNumber = null, isCorrect }) {
        const s = this.state;
        const lastTap = s.taps.at(-1);

        this.onAnswer({
            client_uuid: uuid(),
            question_id: q.id,
            question_ref: q.ref,
            question_version: q.version,
            question_text: q.speak,
            category: q.category,
            correct_label: q.type === 'number-input' ? String(q.correct_number ?? q.correct_label ?? '') : q.correct_label,
            selected_label: selectedLabel,
            selected_number: selectedNumber,
            is_correct: isCorrect,
            attempts: this.state.mode === 'free' ? 1 : this.state.attempts,
            response_time_ms: Date.now() - this.state.questionStartTime,
            position: this.state.current,
            answered_at: new Date().toISOString(),
            first_tap_ms: s.taps[0]?.elapsed_ms ?? null,
            prompt_ended_ms: s.promptEndedMs,
            replay_count: s.replays,
            answered_during_prompt: lastTap?.during_prompt ?? false,
            option_order: s.optionOrder,
            selected_position: s.selectedPosition,
            taps: s.taps,
        });
    }

    handleChoice(card, opt, position) {
        const s = this.state;
        if (s.answered) {
            return;
        }
        s.attempts++;
        s.selectedPosition = position;
        this.logTap({ label: opt.label, position, isCorrect: !!opt.correct });
        const q = this.question;
        const allCards = this.el.optionsGrid.querySelectorAll('.option-card');

        if (s.mode === 'free') {
            s.answered = true;
            allCards.forEach((c) => c.classList.add('disabled'));
            card.classList.remove('disabled');
            card.classList.add('chosen');
            if (opt.correct) {
                s.score++;
            }
            this.record(q, { selectedLabel: opt.label, isCorrect: !!opt.correct });
            this.later(() => this.next(), 650);
            return;
        }

        if (opt.correct) {
            s.answered = true;
            card.classList.add('correct');
            allCards.forEach((c) => {
                if (c !== card) {
                    c.classList.add('disabled');
                }
            });
            s.score++;
            this.record(q, { selectedLabel: opt.label, isCorrect: true });
            this.celebrate(card);
        } else {
            card.classList.add('wrong');
            this.later(() => card.classList.remove('wrong'), 500);
            this.encourage();
        }
    }

    handleNumber(input, correctAnswer) {
        const s = this.state;
        if (s.answered) {
            return;
        }
        s.answered = true;
        s.attempts++;
        this.keypad.setDisabled(true);

        const q = this.question;
        const display = this.el.numberDisplay;
        const isCorrect = input === correctAnswer;
        const answer = { selectedLabel: input, selectedNumber: Number.parseInt(input, 10), isCorrect };
        this.logTap({ label: input, number: answer.selectedNumber, isCorrect });

        if (s.mode === 'free') {
            display.className = 'number-display correct-val';
            if (isCorrect) {
                s.score++;
            }
            this.record(q, answer);
            this.later(() => this.next(), 650);
            return;
        }

        if (isCorrect) {
            display.className = 'number-display correct-val';
            s.score++;
            this.record(q, answer);
            this.celebrate(display);
        } else {
            display.className = 'number-display wrong-val';
            this.encourage();
            this.later(() => {
                s.answered = false;
                this.keypad.reset();
            }, 900);
        }
    }

    celebrate(element) {
        this.feedback.show('✅ Great job!', 'correct-msg');
        launchConfetti(element);
        this.speaker.cheer(pick(PRAISE));
        this.later(() => {
            this.feedback.hide();
            this.next();
        }, 1400);
    }

    encourage() {
        this.speaker.cheer('Try again!');
        this.feedback.show('🤔 Try again!', 'wrong-msg');
        this.later(() => this.feedback.hide(), 900);
    }

    next() {
        this.state.current++;
        if (this.state.current < this.state.questions.length) {
            this.loadQuestion();
        } else {
            this.finish();
        }
    }

    updateStars() {
        const container = this.el.starsScore;
        container.innerHTML = '';
        // In free choice mode stars are hidden — no right/wrong is shown to the child.
        if (this.state.mode === 'free' || this.state.questions.length > 15) {
            return;
        }
        for (let i = 0; i < this.state.questions.length; i++) {
            const star = document.createElement('span');
            star.className = `star-icon${i < this.state.score ? ' lit' : ''}`;
            star.textContent = '⭐';
            container.appendChild(star);
        }
    }

    finish() {
        const { score, questions, mode } = this.state;
        const total = questions.length;
        this.el.progressFill.style.width = '100%';

        if (mode === 'free') {
            this.el.completeEmoji.textContent = '🌟';
            this.el.completeTitle.textContent = 'All Done!';
            this.el.scoreDisplay.textContent = `${total} / ${total}`;
            this.el.scoreStars.textContent = '⭐⭐⭐';
            this.speaker.cheer('Well done! All finished!');
        } else {
            const pct = score / total;
            const title = pct >= 0.8 ? 'Amazing!' : pct >= 0.6 ? 'Great Job!' : 'Keep Trying!';
            this.el.completeEmoji.textContent = pct >= 0.8 ? '🏆' : pct >= 0.6 ? '🎉' : '💪';
            this.el.completeTitle.textContent = title;
            this.el.scoreDisplay.textContent = `${score} / ${total}`;
            this.el.scoreStars.textContent = pct >= 0.8 ? '⭐⭐⭐' : pct >= 0.6 ? '⭐⭐' : '⭐';
            this.speaker.cheer(`You got ${score} out of ${total}! ${title}`);
        }

        this.onComplete({ score, total });
    }
}
