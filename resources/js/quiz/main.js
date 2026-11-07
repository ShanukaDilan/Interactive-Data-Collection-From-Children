import { forgetDevice, getDevice, registerDevice, suggestDeviceName } from './device.js';
import { buildBubbles, shake } from './effects.js';
import { QuizEngine } from './engine.js';
import { holdToActivate } from './hold.js';
import { mountAllMascots } from './mascot.js';
import { cacheMedia, mediaUrls, registerServiceWorker } from './offline.js';
import { Outbox } from './outbox.js';
import { applyPrefs, getPrefs, setPref } from './prefs.js';
import { awardSticker, renderStickerBook } from './stickers.js';
import { createSpeaker } from './tts.js';
import { shuffle, storage, uuid } from './util.js';

const CONFIG_KEY = 'learningfun.config';

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
    deviceText: document.getElementById('deviceText'),
    deviceBtn: document.getElementById('deviceBtn'),
    exitBtn: document.getElementById('exitBtn'),
    lockBtn: document.getElementById('lockBtn'),
    sheet: document.getElementById('parentSheet'),
    sheetBackdrop: document.getElementById('sheetBackdrop'),
    sheetInfo: document.getElementById('sheetInfo'),
    resumeBtn: document.getElementById('resumeBtn'),
    calmToggle: document.getElementById('calmToggle'),
    volumeRange: document.getElementById('volumeRange'),
    stickerGrid: document.getElementById('stickerGrid'),
    stickerCount: document.getElementById('stickerCount'),
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
let offlineSummary = null;

// ── Device (offline sync token) ───────────────────────────────

/** The registered device, if it belongs to the account signed in on this page. */
function activeDevice() {
    const device = getDevice();
    return device && device.userId === config.userId ? device : null;
}

function deviceHeaders() {
    const device = activeDevice();
    return device ? { Authorization: `Bearer ${device.token}` } : {};
}

/** Device revoked by an admin: drop the token and fall back to the browser session. */
function handleDeviceRejected() {
    forgetDevice();
    renderDeviceStatus();
}

// Prefer a newer study list saved from the device API (e.g. this page was served offline).
const saved = storage.get(CONFIG_KEY);
if (saved?.userId === config.userId && saved.generatedAt > config.generatedAt) {
    config.studies = saved.studies;
}

// ── Sync status (for the adult) ───────────────────────────────
const outbox = new Outbox({
    endpoint: () => (activeDevice()
        ? { url: config.apiSyncUrl, headers: deviceHeaders() }
        : { url: config.syncUrl, headers: { 'X-CSRF-TOKEN': csrfToken() ?? '' } }),
    onStatus: ({ state, pending }) => {
        const plural = `${pending} answer${pending === 1 ? '' : 's'}`;
        el.syncStatus.className = `sync-status ${state === 'synced' ? '' : state === 'pending' ? 'pending' : 'offline'}`;
        el.syncText.textContent = {
            synced: 'All answers saved',
            pending: `${plural} waiting to save…`,
            offline: `Offline — ${plural} kept on this device`,
            auth: activeDevice()
                ? 'This device was revoked — sign in again to save answers'
                : `Signed out — ${plural} kept here. Sign in again to save them`,
            storage_full: `Device storage full — ${plural} not yet saved. Keep this page open until online`,
        }[state];

        if (state === 'auth' && activeDevice()) {
            handleDeviceRejected();
        }
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
        renderStickerBook(el.stickerGrid, el.stickerCount, awardSticker(setup.participantId));
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

    const remembered = setup.study?.id ?? storage.get('learningfun.lastStudy');
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
        warn(navigator.onLine
            ? 'Could not load the questions for this study. Check the connection and reload.'
            : 'You are offline and this study has not been downloaded to this device yet.');
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
    const device = activeDevice();

    try {
        const response = await fetch(device ? study.api_bundle_url : study.bundle_url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...deviceHeaders() },
        });
        if (response.status === 401 && device) {
            handleDeviceRejected();
        }
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

// ── Offline preparation & device status ───────────────────────

function renderDeviceStatus() {
    const device = activeDevice();
    const offline = !navigator.onLine;
    const ready = offlineSummary
        ? ` · ready offline (${offlineSummary.studies} ${offlineSummary.studies === 1 ? 'study' : 'studies'}${offlineSummary.media ? `, ${offlineSummary.media} pictures & sounds` : ''})`
        : '';

    if (device) {
        el.deviceText.textContent = `📱 ${device.name}${ready}${offline ? ' · offline now' : ''}`;
        el.deviceBtn.hidden = true;
    } else {
        // Questions and pictures are still cached, but answers need a signed-in session to sync.
        el.deviceText.textContent = offline ? '📴 Offline now' : '📱 Not set up for offline use';
        el.deviceBtn.hidden = offline || !config.deviceRegisterUrl;
    }
}

/** Refresh the study list from the device API (works after the browser session expires). */
async function refreshConfigFromDevice() {
    if (!activeDevice() || !navigator.onLine) {
        return;
    }

    try {
        const response = await fetch(config.apiConfigUrl, { headers: { Accept: 'application/json', ...deviceHeaders() } });
        if (response.status === 401) {
            handleDeviceRejected();
            return;
        }
        if (!response.ok) {
            return;
        }
        const fresh = await response.json();
        storage.set(CONFIG_KEY, { userId: config.userId, generatedAt: fresh.generatedAt, studies: fresh.studies });

        const changed = JSON.stringify(fresh.studies) !== JSON.stringify(config.studies);
        config.studies = fresh.studies;
        if (changed && fresh.studies.length > 0 && !document.getElementById('setup').classList.contains('hidden')) {
            renderStudies();
        }
    } catch {
        // offline — keep what we have
    }
}

/** Download every study's questions and media so quizzes work without a connection. */
async function prepareOffline() {
    if (!navigator.onLine) {
        renderDeviceStatus();
        return;
    }

    let studies = 0;
    let media = 0;

    for (const study of config.studies) {
        try {
            const bundle = await loadBundle(study);
            studies++;
            media += (await cacheMedia(mediaUrls(bundle))).cached;
        } catch {
            // skip studies that fail; the setup screen shows a warning when one is selected
        }
    }

    offlineSummary = { studies, media };
    renderDeviceStatus();
}

el.deviceBtn.addEventListener('click', async () => {
    const name = window.prompt('Name this device so you can recognise it later (e.g. "Blue tablet, Class 2"):', suggestDeviceName());
    if (!name?.trim()) {
        return;
    }

    el.deviceBtn.disabled = true;
    try {
        await registerDevice({ url: config.deviceRegisterUrl, name: name.trim(), csrfToken: csrfToken(), userId: config.userId });
        renderDeviceStatus();
        outbox.flush();
        prepareOffline();
    } catch (error) {
        window.alert(error.message === 'signed_out'
            ? 'Your sign-in has expired. Reload the page, sign in, and try again.'
            : 'Could not set up this device. Check the connection and try again.');
    } finally {
        el.deviceBtn.disabled = false;
    }
});

window.addEventListener('online', () => {
    renderDeviceStatus();
    refreshConfigFromDevice().then(prepareOffline);
    outbox.flush();
});
window.addEventListener('offline', renderDeviceStatus);

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

// ── Parent sheet (press and hold 🔒 so children can't open it by accident) ──
function openSheet() {
    engine.pause();
    const child = setup.study?.participants.find((p) => p.id === setup.participantId);
    el.sheetInfo.textContent = [child ? (child.name || child.code) : 'Anonymous child', `Age ${setup.age}`, engine.describe()]
        .filter(Boolean).join(' · ');
    el.calmToggle.checked = !!getPrefs().calm;
    el.volumeRange.value = String(Math.round(getPrefs().volume * 100));
    el.sheet.hidden = false;
    el.sheetBackdrop.hidden = false;
    requestAnimationFrame(() => el.sheet.classList.add('open'));
    el.resumeBtn.focus();
}

function closeSheet() {
    el.sheet.classList.remove('open');
    el.sheet.hidden = true;
    el.sheetBackdrop.hidden = true;
}

holdToActivate(el.lockBtn, openSheet);
el.resumeBtn.addEventListener('click', closeSheet);
el.sheetBackdrop.addEventListener('click', closeSheet);
el.calmToggle.addEventListener('change', () => setPref('calm', el.calmToggle.checked));
el.volumeRange.addEventListener('input', () => setPref('volume', Number(el.volumeRange.value) / 100));

el.exitBtn.addEventListener('click', () => {
    closeSheet();
    engine.stop();
    outbox.abandonSession(currentSessionUuid);
    outbox.flush();
    showScreen('setup');
});

el.retryBtn.addEventListener('click', () => startSession());

holdToActivate(el.newChildBtn, () => {
    engine.stop();
    setup.participantId = null;
    el.participantSelect.value = '';
    setup.age = null;
    selectAge(null);
    showScreen('setup');
});

// ── Init ──────────────────────────────────────────────────────
applyPrefs();
mountAllMascots();
buildBubbles(document.getElementById('bubbles'));
renderDeviceStatus();

if (config.studies.length === 0) {
    el.studyField.hidden = true;
    el.startBtn.disabled = true;
    warn('No active studies are assigned to you yet. Ask a researcher to add you to a study.');
} else {
    renderStudies();
}

outbox.flush();
registerServiceWorker();
refreshConfigFromDevice().then(prepareOffline);

window.addEventListener('load', () => {
    setTimeout(() => el.loader.classList.add('fade-out'), 900);
});
