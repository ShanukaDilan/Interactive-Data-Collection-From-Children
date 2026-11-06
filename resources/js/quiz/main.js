import { buildBubbles, shake } from './effects.js';
import { QuizEngine } from './engine.js';
import { Outbox } from './outbox.js';
import { createSpeaker } from './tts.js';
import { shuffle, storage, uuid } from './util.js';

const config = JSON.parse(document.getElementById('quiz-config').textContent);
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const el = {
    loader: document.getElementById('loader'),
    warning: document.getElementById('setupWarning'),
    studyField: document.getElementById('studyField'),
    studySelect: document.getElementById('studySelect'),
    ageBtns: document.getElementById('ageBtns'),
    participantSelect: document.getElementById('participantSelect'),
    modeBtns: document.querySelectorAll('.mode-btn'),
    startBtn: document.getElementById('startBtn'),
    syncStatus: document.getElementById('syncStatus'),
    syncText: document.getElementById('syncText'),
    exitBtn: document.getElementById('exitBtn'),
    retryBtn: document.getElementById('retryBtn'),
    newChildBtn: document.getElementById('newChildBtn'),
};

const setup = {
    study: null,
    bundle: null,
    age: null,
    participantId: null,
    mode: 'correct',
};

let currentSessionUuid = null;
let speaker = createSpeaker('en-US');

// ── Sync status (for the adult) ───────────────────────────────
const outbox = new Outbox({
    syncUrl: config.syncUrl,
    csrfToken,
    onStatus: ({ state, pending }) => {
        el.syncStatus.className = `sync-status ${state === 'synced' ? '' : state === 'pending' ? 'pending' : 'offline'}`;
        el.syncText.textContent = {
            synced: 'All answers saved',
            pending: `${pending} answer${pending === 1 ? '' : 's'} waiting to save…`,
            offline: `Offline — ${pending} answer${pending === 1 ? '' : 's'} kept on this device`,
            auth: 'Signed out — reload the page and sign in to save answers',
        }[state];
    },
});

const engine = new QuizEngine({
    speaker: {
        // Delegate so the speaker can be swapped when the study (and language) changes.
        say: (...args) => speaker.say(...args),
        cheer: (...args) => speaker.cheer(...args),
        playQuestion: (...args) => speaker.playQuestion(...args),
        stop: () => speaker.stop(),
    },
    onAnswer: (response) => {
        outbox.addResponse(currentSessionUuid, response);
        outbox.flush();
    },
    onComplete: () => {
        outbox.completeSession(currentSessionUuid);
        outbox.flush();
        showScreen('complete');
    },
});

// ── Screens ───────────────────────────────────────────────────
function showScreen(id) {
    document.querySelectorAll('.screen').forEach((s) => s.classList.toggle('hidden', s.id !== id));
}

function warn(message) {
    el.warning.hidden = !message;
    el.warning.textContent = message ?? '';
}

// ── Setup screen ──────────────────────────────────────────────
function renderStudies() {
    el.studySelect.innerHTML = '';
    config.studies.forEach((study) => {
        const option = document.createElement('option');
        option.value = study.id;
        option.textContent = study.name;
        el.studySelect.appendChild(option);
    });
    el.studyField.hidden = config.studies.length <= 1;

    const remembered = storage.get('learningfun.lastStudy');
    const initial = config.studies.find((s) => s.id === remembered) ?? config.studies[0];
    el.studySelect.value = initial.id;
    selectStudy(initial);
}

async function selectStudy(study) {
    setup.study = study;
    setup.bundle = null;
    setup.age = null;
    setup.participantId = null;
    storage.set('learningfun.lastStudy', study.id);

    renderAges(study);
    renderParticipants(study);
    selectMode(study.default_quiz_mode);
    el.startBtn.disabled = true;
    warn(null);

    try {
        setup.bundle = await loadBundle(study);
    } catch {
        warn('Could not load the questions for this study. Check the connection and reload.');
        return;
    }

    if (setup.study !== study) {
        return; // the adult switched study while loading
    }

    speaker = createSpeaker(setup.bundle.study.locale);

    if (setup.bundle.questions.length === 0) {
        warn('This study has no questions yet. Add some from the dashboard.');
        return;
    }

    el.startBtn.disabled = false;
}

/** Fetch the study's questions; fall back to the last copy saved on this device. */
async function loadBundle(study) {
    const cacheKey = `learningfun.bundle.${study.id}`;

    try {
        const response = await fetch(study.bundle_url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const bundle = await response.json();
        storage.set(cacheKey, bundle);
        return bundle;
    } catch (error) {
        const cached = storage.get(cacheKey);
        if (cached) {
            return cached;
        }
        throw error;
    }
}

function renderAges(study) {
    el.ageBtns.innerHTML = '';
    for (let age = study.age_min; age <= Math.min(study.age_max, study.age_min + 8); age++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'age-btn';
        btn.textContent = age;
        btn.dataset.age = age;
        btn.addEventListener('click', () => selectAge(age));
        el.ageBtns.appendChild(btn);
    }
}

function selectAge(age) {
    setup.age = age;
    el.ageBtns.querySelectorAll('.age-btn').forEach((b) => b.classList.toggle('active', Number(b.dataset.age) === age));
}

function renderParticipants(study) {
    el.participantSelect.innerHTML = '';
    const anonymous = document.createElement('option');
    anonymous.value = '';
    anonymous.textContent = 'Anonymous';
    el.participantSelect.appendChild(anonymous);

    study.participants.forEach((p) => {
        const option = document.createElement('option');
        option.value = p.id;
        option.textContent = [p.code, p.name, p.age ? `age ${p.age}` : null].filter(Boolean).join(' · ');
        el.participantSelect.appendChild(option);
    });
    el.participantSelect.value = '';
}

function selectMode(mode) {
    setup.mode = mode;
    el.modeBtns.forEach((b) => b.classList.toggle('active', b.dataset.mode === mode));
}

el.studySelect.addEventListener('change', () => {
    const study = config.studies.find((s) => String(s.id) === el.studySelect.value);
    if (study) {
        selectStudy(study);
    }
});

el.participantSelect.addEventListener('change', () => {
    const id = el.participantSelect.value ? Number(el.participantSelect.value) : null;
    setup.participantId = id;
    const participant = setup.study.participants.find((p) => p.id === id);
    if (participant?.age && participant.age >= setup.study.age_min && participant.age <= setup.study.age_max) {
        selectAge(participant.age);
    }
});

el.modeBtns.forEach((btn) => btn.addEventListener('click', () => selectMode(btn.dataset.mode)));

// ── Session lifecycle ─────────────────────────────────────────
function buildQuestionList() {
    const { study: settings, questions } = setup.bundle;
    let list = settings.shuffle_questions ? shuffle([...questions]) : [...questions];
    if (settings.questions_per_session) {
        list = list.slice(0, settings.questions_per_session);
    }
    return list;
}

function startSession() {
    currentSessionUuid = uuid();
    outbox.startSession({
        uuid: currentSessionUuid,
        study_id: setup.study.id,
        participant_id: setup.participantId,
        child_age: setup.age,
        quiz_mode: setup.mode,
        started_at: new Date().toISOString(),
    });
    outbox.flush();

    showScreen('quiz');
    engine.start({
        questions: buildQuestionList(),
        mode: setup.mode,
        shuffleOptions: setup.bundle.study.shuffle_options,
    });
}

el.startBtn.addEventListener('click', () => {
    if (!setup.bundle) {
        return;
    }
    if (!setup.age) {
        shake(el.ageBtns);
        return;
    }
    startSession();
});

el.exitBtn.addEventListener('click', () => {
    if (!window.confirm('End this session and go back to setup? Answers so far are kept.')) {
        return;
    }
    engine.stop();
    outbox.abandonSession(currentSessionUuid);
    outbox.flush();
    showScreen('setup');
});

el.retryBtn.addEventListener('click', () => startSession());

el.newChildBtn.addEventListener('click', () => {
    engine.stop();
    setup.participantId = null;
    el.participantSelect.value = '';
    setup.age = null;
    selectAge(null);
    showScreen('setup');
});

// ── Init ──────────────────────────────────────────────────────
buildBubbles(document.getElementById('bubbles'));

if (config.studies.length === 0) {
    el.studyField.hidden = true;
    el.startBtn.disabled = true;
    warn('No active studies are assigned to you yet. Ask a researcher to add you to a study.');
} else {
    renderStudies();
}

outbox.flush();

window.addEventListener('load', () => {
    setTimeout(() => el.loader.classList.add('fade-out'), 600);
});
