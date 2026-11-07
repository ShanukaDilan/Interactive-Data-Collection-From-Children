<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FFB347">
    <link rel="manifest" href="{{ route('web-manifest') }}">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/icon.svg">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>🌟 Learning Fun!</title>
    @fonts
    @vite(['resources/css/quiz.css', 'resources/js/quiz/main.js'])
</head>
<body>

<!-- Loading -->
<div id="loader">
    <div class="loader-blob loader-blob-1"></div>
    <div class="loader-blob loader-blob-2"></div>
    <div class="loader-sun"><div data-mascot="wave"></div></div>
    <div class="loader-text">Learning Fun!</div>
    <div class="loader-dots" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="loader-sub">Getting the games ready…</div>
</div>

<!-- Animated background bubbles -->
<div class="bubbles" id="bubbles"></div>

<!-- ── SETUP SCREEN (for the adult) ─────────────────── -->
<div class="screen" id="setup">
    <div class="setup-card">
        <div class="setup-mascot" data-mascot="wave"></div>
        <h1>Learning Fun!</h1>
        <p class="subtitle">For teachers &amp; parents — set up before handing to child</p>

        <div class="setup-warning" id="setupWarning" hidden></div>

        <div class="field-wrap" id="studyField">
            <div class="field-label">🧪 Study</div>
            <select class="text-input select-input" id="studySelect" aria-label="Study"></select>
        </div>

        <div class="field-wrap">
            <div class="field-label">👶 Child's Age</div>
            <div class="age-buttons" id="ageBtns"></div>
        </div>

        <div class="field-wrap">
            <div class="field-label">🏷️ Child (optional)</div>
            <select class="text-input select-input" id="participantSelect" aria-label="Child"></select>
            <div class="setup-hint">Add children under Participants in the dashboard. Leave as “Anonymous” to skip.</div>
        </div>

        <div class="field-wrap">
            <div class="field-label">🎮 Quiz Mode</div>
            <div class="mode-buttons">
                <button type="button" class="mode-btn active" data-mode="correct">
                    🎯 Find the Answer
                    <small>Must pick the correct one</small>
                </button>
                <button type="button" class="mode-btn" data-mode="free">
                    📝 Free Choice
                    <small>Records any selection</small>
                </button>
            </div>
        </div>

        <button type="button" class="start-btn" id="startBtn">▶&nbsp; Let's Play!</button>

        <div class="setup-footer">
            <span class="sync-status" id="syncStatus"><span class="sync-dot"></span><span id="syncText">All answers saved</span></span>
            <a href="{{ route('admin.dashboard') }}">Dashboard →</a>
        </div>
        <div class="device-status">
            <span id="deviceText"></span>
            <button type="button" class="device-btn" id="deviceBtn" hidden>Set up for offline use</button>
        </div>
    </div>
</div>

<!-- ── QUIZ SCREEN (for the child) ─────────────────── -->
<div class="screen hidden" id="quiz">
    <div class="quiz-top">
        <button type="button" class="lock-btn" id="lockBtn" aria-label="Grown-ups: press and hold to open the menu">
            <svg class="lock-ring" viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="23"/></svg>
            <span class="lock-icon" aria-hidden="true">🔒</span>
        </button>
        <div class="trail" id="trail" role="progressbar" aria-label="Progress" aria-valuemin="0"></div>
    </div>

    <div class="prompt-row">
        <div class="prompt-mascot" id="quizMascot" data-mascot="idle"></div>
        <div class="prompt-bubble">
            <button type="button" class="speak-btn" id="speakBtn" aria-label="Hear the question again">
                <span aria-hidden="true">🔊</span>
            </button>
            <div class="prompt-body">
                <div class="sound-wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
                <div class="question-text" id="questionText">Loading…</div>
            </div>
        </div>
    </div>

    <div class="question-card" id="questionCard">
        <img id="questionImage" class="question-img" style="display:none" alt="">
        <div class="question-emoji-display" id="questionEmojiDisplay" style="display:none"></div>
    </div>

    <div class="tap-hint" id="tapHint" aria-hidden="true"><span class="tap-hand">👆</span><span id="tapHintText">Tap your answer</span></div>

    <!-- Answers: choice cards -->
    <div class="options-grid" id="optionsGrid"></div>

    <!-- Answers: numbers (counting questions) -->
    <div class="number-input-wrap" id="numberInputWrap" style="display:none">
        <div class="number-display" id="numberDisplay">?</div>
        <div class="number-keypad" id="numberKeypad"></div>
    </div>
</div>

<!-- ── CELEBRATION OVERLAY ───────────────────────── -->
<div class="feedback-msg" id="feedbackMsg" role="status" aria-live="polite">
    <div class="feedback-mascot" data-mascot="cheer"></div>
    <div class="feedback-text" id="feedbackText"></div>
    <div class="feedback-star" aria-hidden="true">⭐</div>
</div>

<!-- ── COMPLETE SCREEN ─────────────────────────────── -->
<div class="screen hidden" id="complete">
    <div class="complete-mascot" id="completeMascot" data-mascot="cheer"></div>
    <div class="complete-title" id="completeTitle">All done!</div>
    <div class="score-stars" id="scoreStars" aria-hidden="true"></div>
    <div class="score-display" id="scoreDisplay" hidden></div>
    <span class="complete-emoji" id="completeEmoji" hidden></span>

    <div class="sticker-book">
        <div class="sticker-head">
            <span class="sticker-title">My stickers</span>
            <span class="sticker-count" id="stickerCount"></span>
        </div>
        <div class="sticker-grid" id="stickerGrid"></div>
    </div>

    <button type="button" class="big-btn btn-retry" id="retryBtn"><span aria-hidden="true">🔄</span> Play again</button>
    <button type="button" class="hold-btn" id="newChildBtn" aria-label="Grown-ups: press and hold for a new child">
        <span class="hold-icon">
            <svg class="lock-ring" viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="23"/></svg>
            <span aria-hidden="true">🔒</span>
        </span>
        Grown-ups: hold for New Child
    </button>
</div>

<!-- ── PARENT SHEET (hold 🔒 on the quiz screen) ────── -->
<div class="sheet-backdrop" id="sheetBackdrop" hidden></div>
<div class="parent-sheet" id="parentSheet" role="dialog" aria-modal="true" aria-labelledby="sheetTitle" hidden>
    <div class="sheet-grip"></div>
    <div class="sheet-title" id="sheetTitle">Grown-ups only</div>
    <div class="sheet-info" id="sheetInfo"></div>

    <label class="sheet-row">
        <span class="sheet-row-icon" aria-hidden="true">🌙</span>
        <span class="sheet-row-text"><strong>Calm mode</strong><small>Less motion, softer sounds, no confetti</small></span>
        <input type="checkbox" class="switch" id="calmToggle">
    </label>
    <label class="sheet-row">
        <span class="sheet-row-icon" aria-hidden="true">🔈</span>
        <span class="sheet-row-text"><strong>Voice volume</strong>
            <input type="range" class="volume" id="volumeRange" min="0" max="100" step="5" aria-label="Voice volume">
        </span>
    </label>

    <div class="sheet-btns">
        <button type="button" class="sheet-resume" id="resumeBtn">▶ Resume</button>
        <button type="button" class="sheet-end" id="exitBtn">End session</button>
    </div>
</div>

<script type="application/json" id="quiz-config">@json($config)</script>
</body>
</html>
