<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FF6B6B">
    <title>🌟 Learning Fun!</title>
    @fonts
    @vite(['resources/css/quiz.css', 'resources/js/quiz/main.js'])
</head>
<body>

<!-- Loading -->
<div id="loader">
    <div class="loader-emoji">🌟</div>
    <div class="loader-text">Loading Fun!</div>
</div>

<!-- Animated background bubbles -->
<div class="bubbles" id="bubbles"></div>

<!-- ── SETUP SCREEN (for the adult) ─────────────────── -->
<div class="screen" id="setup">
    <div class="setup-card">
        <h1>🌈 Learning Fun!</h1>
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
    </div>
</div>

<!-- ── QUIZ SCREEN ─────────────────────────────────── -->
<div class="screen hidden" id="quiz">
    <div class="progress-wrap">
        <button type="button" class="exit-btn" id="exitBtn" title="End session (adult)" aria-label="End session">✕</button>
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill" style="width:0%"></div>
        </div>
        <div class="progress-label" id="progressLabel">1 / 10</div>
        <div class="stars-score" id="starsScore"></div>
        <span class="mode-pill" id="modePill"></span>
    </div>

    <div class="question-card">
        <button type="button" class="speak-btn" id="speakBtn" title="Hear the question again" aria-label="Hear the question again">🔊</button>
        <img id="questionImage" class="question-img" style="display:none" alt="">
        <div class="question-emoji-display" id="questionEmojiDisplay" style="display:none"></div>
        <div class="question-text" id="questionText">Loading…</div>
        <span class="category-badge" id="categoryBadge"></span>
    </div>

    <!-- Answers: choice cards -->
    <div class="options-grid" id="optionsGrid"></div>

    <!-- Answers: number keypad (counting questions) -->
    <div class="number-input-wrap" id="numberInputWrap" style="display:none">
        <div class="number-display" id="numberDisplay">?</div>
        <div class="number-keypad" id="numberKeypad"></div>
    </div>
</div>

<!-- ── COMPLETE SCREEN ─────────────────────────────── -->
<div class="screen hidden" id="complete">
    <div class="complete-card">
        <div class="complete-emoji" id="completeEmoji">🎉</div>
        <div class="complete-title" id="completeTitle">Amazing Job!</div>
        <div class="score-display" id="scoreDisplay"></div>
        <div class="score-stars" id="scoreStars">⭐⭐⭐</div>
        <div class="complete-btns">
            <button type="button" class="btn-retry" id="retryBtn">🔄 Play Again</button>
            <button type="button" class="btn-new" id="newChildBtn">👶 New Child</button>
        </div>
    </div>
</div>

<!-- Feedback overlay -->
<div class="feedback-msg" id="feedbackMsg" role="status" aria-live="polite"></div>

<script type="application/json" id="quiz-config">@json($config)</script>
</body>
</html>
