import { createFeedback, launchConfetti } from './effects.js';
import { buildNumberKeypad } from './keypad.js';
import { setMood } from './mascot.js';
import { pick, shuffle, uuid } from './util.js';

const PRAISE = ['Wonderful!', 'Amazing!', 'Super!', 'You got it!', 'Great job!'];

/** After this many wrong taps on one question (find-the-answer mode) the right answer glows. */
const HINT_AFTER = 2;

/** Split a string into user-perceived characters (keeps 👩‍👧 and flags together). */
function graphemes(text) {
    if (window.Intl?.Segmenter) {
        return [...new Intl.Segmenter(undefined, { granularity: 'grapheme' }).segment(text)].map((g) => g.segment);
    }

    return [...text];
}

/**
 * Runs one quiz session on the quiz screen. Ported from loadQuestion / handleAnswer /
 * submitNumberAnswer / showComplete in legacy/index.php.
 *
 * Modes:
 *  - "correct": wrong taps wobble and fade (never red) and the child tries again; after
 *    HINT_AFTER wrong taps the right answer glows. One answer is recorded when the child gets
 *    it right, with the number of attempts and every tap.
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
            trail: document.getElementById('trail'),
            mascot: document.getElementById('quizMascot'),
            speakBtn: document.getElementById('speakBtn'),
            questionCard: document.getElementById('questionCard'),
            questionImage: document.getElementById('questionImage'),
            questionEmoji: document.getElementById('questionEmojiDisplay'),
            questionText: document.getElementById('questionText'),
            tapHintText: document.getElementById('tapHintText'),
            optionsGrid: document.getElementById('optionsGrid'),
            numberWrap: document.getElementById('numberInputWrap'),
            numberDisplay: document.getElementById('numberDisplay'),
            numberKeypad: document.getElementById('numberKeypad'),
            completeTitle: document.getElementById('completeTitle'),
            scoreDisplay: document.getElementById('scoreDisplay'),
            scoreStars: document.getElementById('scoreStars'),
        };
        this.quizScreen = document.getElementById('quiz');
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
        this.quizScreen.dataset.mode = mode;

        this.loadQuestion();
    }

    /** Cancel pending transitions and speech (e.g. when the adult exits). */
    stop() {
        this.timers.forEach(clearTimeout);
        this.timers = [];
        this.feedback.hide();
        this.speaker.stop();
        this.quizScreen.classList.remove('speaking');
        setMood(this.el.mascot, 'idle');
    }

    /** Silence the prompt while the parent sheet is open. */
    pause() {
        this.speaker.stop();
        this.quizScreen.classList.remove('speaking');
        setMood(this.el.mascot, 'idle');
    }

    later(fn, ms) {
        this.timers.push(setTimeout(fn, ms));
    }

    get question() {
        return this.state.questions[this.state.current];
    }

    /** Short description of where the session is, for the parent sheet. */
    describe() {
        const s = this.state;
        if (!s || !this.question) {
            return '';
        }

        return `${s.mode === 'free' ? 'Free Choice' : 'Find the Answer'} · ${this.question.category} · Q ${s.current + 1}/${s.questions.length}`;
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
        setMood(this.el.mascot, 'talk');
        this.quizScreen.classList.add('speaking');
        this.speaker.playQuestion(this.question, this.el.speakBtn, () => {
            // Ignore end events from prompts that were cancelled or belong to an earlier question.
            if (token !== this.playToken || questionIndex !== s.current) {
                return;
            }
            this.quizScreen.classList.remove('speaking');
            if (this.el.mascot.dataset.mood === 'talk') {
                setMood(this.el.mascot, 'idle');
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
        s.wrongTaps = 0;

        this.renderTrail();
        setMood(this.el.mascot, 'idle');

        this.el.questionText.textContent = q.hint || q.speak;

        const img = this.el.questionImage;
        if (q.image) {
            img.src = q.image;
            img.style.display = '';
        } else {
            img.style.display = 'none';
            img.removeAttribute('src');
        }

        const isNumber = q.type === 'number-input';
        const emoji = this.el.questionEmoji;
        emoji.innerHTML = '';
        let countable = false;
        if (q.question_emoji) {
            const items = graphemes(q.question_emoji.replace(/\s+/g, ''));
            emoji.style.display = '';
            countable = isNumber && items.length > 1 && items.length <= 20;
            if (countable) {
                this.buildCountables(emoji, items);
            } else {
                emoji.textContent = q.question_emoji;
                emoji.className = `question-emoji-display ${items.length <= 1 ? 'qemoji-letter' : 'qemoji-count'}${items.length >= 6 ? ' qemoji-many' : ''}`;
            }
        } else {
            emoji.style.display = 'none';
        }
        this.el.questionCard.hidden = !q.image && !q.question_emoji;

        this.el.tapHintText.textContent = isNumber
            ? (countable ? 'Tap each one, then pick the number' : 'Pick the number')
            : 'Tap your answer';

        this.el.optionsGrid.style.display = isNumber ? 'none' : '';
        this.el.numberWrap.style.display = isNumber ? '' : 'none';

        if (isNumber) {
            this.buildKeypad(q);
        } else {
            this.buildOptions(q);
        }

        this.later(() => this.replay(true), 600);
    }

    /** Counting: each item is tappable and gets a number badge, like counting with a finger. */
    buildCountables(container, items) {
        container.className = `question-emoji-display qemoji-countables${items.length > 6 ? ' many' : ''}`;
        let counted = 0;
        items.forEach((item) => {
            const cell = document.createElement('button');
            cell.type = 'button';
            cell.className = 'countable';
            cell.textContent = item;
            cell.tabIndex = -1;
            cell.setAttribute('aria-hidden', 'true');
            cell.addEventListener('pointerdown', (e) => {
                e.preventDefault();
                if (cell.dataset.n) {
                    return;
                }
                counted++;
                cell.dataset.n = String(counted);
                const badge = document.createElement('span');
                badge.className = 'count-badge';
                badge.textContent = String(counted);
                cell.appendChild(badge);
                this.speaker.say(String(counted));
            });
            container.appendChild(cell);
        });
    }

    buildOptions(q) {
        const grid = this.el.optionsGrid;
        grid.innerHTML = '';
        grid.classList.toggle('cols-3', q.options.length === 3 || q.options.length === 6 || q.options.length === 5);

        const options = this.state.shuffleOptions ? shuffle([...q.options]) : [...q.options];
        this.state.optionOrder = options.map((opt) => opt.label);
        this.state.correctIndex = options.findIndex((opt) => opt.correct);

        options.forEach((opt, position) => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'option-card';
            card.style.setProperty('--card-bg', opt.bg || '#F0F4FF');
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

            // Letter cards ("a" shown as "a") don't need the same text twice.
            if (opt.image || (opt.emoji || '').trim() !== opt.label.trim()) {
                const label = document.createElement('div');
                label.className = 'option-label';
                label.textContent = opt.label;
                card.appendChild(label);
            } else {
                card.classList.add('letter-card');
            }

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
            onSubmit: (input, tile) => this.handleNumber(input, correctAnswer, tile),
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
        if (s.answered || card.classList.contains('faded')) {
            return;
        }
        s.attempts++;
        s.selectedPosition = position;
        this.logTap({ label: opt.label, position, isCorrect: !!opt.correct });
        const q = this.question;
        const allCards = [...this.el.optionsGrid.querySelectorAll('.option-card')];

        if (s.mode === 'free') {
            s.answered = true;
            allCards.forEach((c) => c.classList.add('disabled'));
            card.classList.remove('disabled');
            card.classList.add('chosen');
            if (opt.correct) {
                s.score++;
            }
            this.record(q, { selectedLabel: opt.label, isCorrect: !!opt.correct });
            this.gotIt();
            return;
        }

        if (opt.correct) {
            s.answered = true;
            card.classList.remove('hinted');
            card.classList.add('correct');
            allCards.forEach((c) => {
                if (c !== card) {
                    c.classList.add('disabled');
                }
            });
            s.score++;
            this.record(q, { selectedLabel: opt.label, isCorrect: true });
            this.celebrate(card);
            return;
        }

        // No red: the card wobbles and fades so the child can try the others.
        card.classList.add('wrong');
        this.later(() => {
            card.classList.remove('wrong');
            card.classList.add('faded');
        }, 450);
        s.wrongTaps++;

        if (s.wrongTaps >= HINT_AFTER) {
            const right = allCards[s.correctIndex];
            this.giveHint(() => {
                allCards.forEach((c) => c.classList.toggle('faded', c !== right));
                right?.classList.add('hinted');
            });
        } else {
            this.encourage();
        }
    }

    handleNumber(input, correctAnswer, tile = null) {
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
            this.keypad.markChosen(input);
            if (isCorrect) {
                s.score++;
            }
            this.record(q, answer);
            this.gotIt();
            return;
        }

        if (isCorrect) {
            display.className = 'number-display correct-val';
            this.keypad.markCorrect(input);
            s.score++;
            this.record(q, answer);
            this.celebrate(tile ?? display);
            return;
        }

        display.className = 'number-display wrong-val';
        this.keypad.markWrong(input);
        s.wrongTaps++;
        const showHint = this.keypad.tiles && s.wrongTaps >= HINT_AFTER;
        if (showHint) {
            this.giveHint(() => this.keypad.hint(correctAnswer));
        } else {
            this.encourage();
        }
        this.later(() => {
            s.answered = false;
            this.keypad.reset();
        }, 900);
    }

    celebrate(element) {
        const praise = pick(PRAISE);
        setMood(this.el.mascot, 'cheer');
        this.feedback.show(praise, 'correct-msg');
        launchConfetti(element);
        this.speaker.cheer(praise);
        this.later(() => {
            this.feedback.hide();
            this.next();
        }, 1500);
    }

    /** Free choice: the same calm "got it" for every pick, so there is no right/wrong signal. */
    gotIt() {
        setMood(this.el.mascot, 'cheer');
        this.later(() => this.next(), 750);
    }

    encourage() {
        setMood(this.el.mascot, 'think');
        this.speaker.cheer(pick(['Try again!', 'Almost! Try another one.', 'Hmm, try again!']));
        this.later(() => setMood(this.el.mascot, 'idle'), 1200);
    }

    giveHint(show) {
        setMood(this.el.mascot, 'think');
        this.speaker.cheer("Let's try together!");
        this.later(show, 450);
    }

    next() {
        this.state.current++;
        if (this.state.current < this.state.questions.length) {
            this.loadQuestion();
        } else {
            this.finish();
        }
    }

    /**
     * Progress as stepping stones: finished steps are stars (in both modes, so free choice still
     * gives no right/wrong signal), the current step is a big numbered stone, 🏁 at the end.
     */
    renderTrail() {
        const { current, questions } = this.state;
        const total = questions.length;
        const compact = total > 12;
        const trail = this.el.trail;
        trail.innerHTML = '';
        trail.classList.toggle('compact', compact);
        trail.setAttribute('aria-valuemax', String(total));
        trail.setAttribute('aria-valuenow', String(current + 1));
        trail.setAttribute('aria-valuetext', `Question ${current + 1} of ${total}`);

        for (let i = 0; i < total; i++) {
            const step = document.createElement('span');
            if (i < current) {
                step.className = 'step done';
                step.textContent = compact ? '' : '⭐';
            } else if (i === current) {
                step.className = 'step now';
                step.textContent = String(i + 1);
            } else {
                step.className = 'step';
            }
            trail.appendChild(step);
        }

        const goal = document.createElement('span');
        goal.className = 'step goal';
        goal.textContent = '🏁';
        trail.appendChild(goal);
    }

    finish() {
        const { score, questions } = this.state;
        const total = questions.length;
        setMood(this.el.mascot, 'idle');

        // Every child "wins": in find-the-answer mode each question ends correct, and the score
        // stays out of the child's sight (adults see it in the dashboard).
        const title = pick(['All done!', 'You did it!', 'Super job!']);
        this.el.completeTitle.textContent = title;
        this.el.scoreDisplay.textContent = `${score} / ${total}`;
        this.el.scoreStars.innerHTML = '<span>⭐</span><span>⭐</span><span>⭐</span>';
        this.speaker.cheer(`${title} You finished the game!`);

        this.onComplete({ score, total });
    }
}
