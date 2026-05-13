<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>🌟 Learning Fun!</title>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --coral:   #FF6B6B;
      --sky:     #4ECDC4;
      --sun:     #FFE66D;
      --mint:    #A8E6CF;
      --lavender:#C9B1FF;
      --peach:   #FFB347;
      --bg:      #FFF5E4;
      --card-r:  28px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      width: 100%; height: 100%;
      overflow: hidden;
      font-family: 'Nunito', sans-serif;
      background: var(--bg);
      touch-action: manipulation;
    }

    /* ── BUBBLES BACKGROUND ─────────────────────── */
    .bubbles {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      overflow: hidden;
    }
    .bubble {
      position: absolute; border-radius: 50%; opacity: .12;
      animation: float linear infinite;
    }
    @keyframes float {
      0%   { transform: translateY(110vh) scale(1); }
      100% { transform: translateY(-20vh) scale(1.1); }
    }

    /* ── SCREENS ─────────────────────────────────── */
    .screen {
      position: fixed; inset: 0; z-index: 10;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 20px;
      transition: opacity .4s, transform .4s;
    }
    .screen.hidden { opacity: 0; pointer-events: none; transform: scale(.96); }

    /* ── SETUP SCREEN ───────────────────────────── */
    #setup {
      background: linear-gradient(135deg, #FF6B6B 0%, #FFB347 50%, #FFE66D 100%);
    }
    .setup-card {
      background: white;
      border-radius: 32px;
      padding: 44px 48px;
      max-width: 540px; width: 100%;
      box-shadow: 0 24px 60px rgba(0,0,0,.15);
      text-align: center;
    }
    .setup-card h1 {
      font-family: 'Fredoka One', cursive;
      font-size: clamp(2rem, 5vw, 3rem);
      color: var(--coral);
      margin-bottom: 6px;
    }
    .setup-card .subtitle {
      color: #888; font-size: 1rem; margin-bottom: 32px;
    }
    .field-label {
      text-align: left;
      font-weight: 800; color: #444;
      font-size: .95rem;
      margin-bottom: 8px;
    }
    .field-wrap { margin-bottom: 20px; }
    .age-buttons {
      display: flex; gap: 12px; justify-content: center;
      margin-bottom: 28px;
    }
    .age-btn {
      width: 72px; height: 72px;
      border-radius: 50%;
      border: 3px solid #eee;
      background: white;
      font-family: 'Fredoka One', cursive;
      font-size: 1.6rem;
      cursor: pointer;
      transition: all .2s;
      color: #666;
    }
    .age-btn.active {
      background: var(--coral);
      border-color: var(--coral);
      color: white;
      transform: scale(1.12);
      box-shadow: 0 8px 20px rgba(255,107,107,.4);
    }
    .text-input {
      width: 100%;
      padding: 14px 18px;
      border: 2.5px solid #eee;
      border-radius: 14px;
      font-family: 'Nunito', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      outline: none;
      transition: border-color .2s;
    }
    .text-input:focus { border-color: var(--sky); }
    .start-btn {
      background: linear-gradient(135deg, var(--sky), #2bbcb3);
      color: white;
      border: none;
      border-radius: 18px;
      padding: 18px 48px;
      font-family: 'Fredoka One', cursive;
      font-size: 1.4rem;
      cursor: pointer;
      box-shadow: 0 10px 28px rgba(78,205,196,.45);
      transition: transform .15s, box-shadow .15s;
      width: 100%;
      letter-spacing: .5px;
    }
    .start-btn:active { transform: scale(.96); }

    /* ── QUIZ SCREEN ─────────────────────────────── */
    #quiz {
      background: linear-gradient(160deg, #E0F7FF 0%, #FFF5E4 60%, #FFE8F5 100%);
      padding: 16px;
    }

    /* Progress bar */
    .progress-wrap {
      width: 100%; max-width: 700px;
      display: flex; align-items: center; gap: 12px;
      margin-bottom: 14px;
    }
    .progress-bar {
      flex: 1; height: 14px;
      background: rgba(0,0,0,.08);
      border-radius: 99px;
      overflow: hidden;
    }
    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--coral), var(--peach));
      border-radius: 99px;
      transition: width .5s cubic-bezier(.34,1.56,.64,1);
    }
    .progress-label {
      font-family: 'Fredoka One', cursive;
      font-size: 1.05rem;
      color: #888;
      white-space: nowrap;
    }
    .stars-score {
      display: flex; gap: 3px;
    }
    .star-icon {
      font-size: 1.3rem;
      filter: grayscale(1);
      transition: filter .3s;
    }
    .star-icon.lit { filter: none; }

    /* Question card */
    .question-card {
      background: white;
      border-radius: var(--card-r);
      padding: 20px 28px;
      max-width: 700px; width: 100%;
      display: flex; align-items: center;
      gap: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,.10);
      margin-bottom: 18px;
    }
    .speak-btn {
      width: 62px; height: 62px; flex-shrink: 0;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--coral), var(--peach));
      border: none;
      cursor: pointer;
      font-size: 1.8rem;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 6px 18px rgba(255,107,107,.35);
      transition: transform .15s;
    }
    .speak-btn:active { transform: scale(.92); }
    .speak-btn.speaking { animation: pulse-speak .6s ease infinite alternate; }
    @keyframes pulse-speak {
      from { transform: scale(1); }
      to   { transform: scale(1.12); }
    }
    .question-text {
      font-family: 'Fredoka One', cursive;
      font-size: clamp(1.4rem, 3.5vw, 2.1rem);
      color: #333;
      flex: 1;
    }
    .category-badge {
      background: var(--sun);
      color: #7a5a00;
      border-radius: 99px;
      padding: 4px 14px;
      font-weight: 800;
      font-size: .82rem;
      white-space: nowrap;
    }

    /* Options grid */
    .options-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      max-width: 700px; width: 100%;
    }
    .option-card {
      background: white;
      border-radius: var(--card-r);
      border: 4px solid transparent;
      padding: 20px 12px;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 8px;
      cursor: pointer;
      box-shadow: 0 6px 20px rgba(0,0,0,.08);
      transition: transform .15s, box-shadow .15s, border-color .2s;
      user-select: none;
      -webkit-tap-highlight-color: transparent;
      min-height: 140px;
      animation: card-in .45s cubic-bezier(.34,1.56,.64,1) both;
    }
    .option-card:nth-child(1) { animation-delay: .05s; }
    .option-card:nth-child(2) { animation-delay: .12s; }
    .option-card:nth-child(3) { animation-delay: .19s; }
    .option-card:nth-child(4) { animation-delay: .26s; }
    @keyframes card-in {
      from { opacity: 0; transform: translateY(30px) scale(.9); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .option-card:active { transform: scale(.95); }
    .option-emoji {
      font-size: clamp(3rem, 8vw, 5rem);
      line-height: 1;
      transition: transform .2s;
    }
    .option-label {
      font-family: 'Fredoka One', cursive;
      font-size: clamp(.9rem, 2.5vw, 1.3rem);
      color: #555;
    }

    /* States */
    .option-card.correct {
      border-color: #2ecc71;
      background: #f0fff6;
      animation: correct-bounce .5s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes correct-bounce {
      0%   { transform: scale(1); }
      40%  { transform: scale(1.15); }
      100% { transform: scale(1.05); }
    }
    .option-card.correct .option-emoji { transform: scale(1.2); }
    .option-card.wrong {
      border-color: #e74c3c;
      background: #fff5f5;
      animation: shake .4s ease;
    }
    @keyframes shake {
      0%,100% { transform: translateX(0); }
      20%     { transform: translateX(-8px); }
      40%     { transform: translateX(8px); }
      60%     { transform: translateX(-6px); }
      80%     { transform: translateX(6px); }
    }
    .option-card.disabled { pointer-events: none; opacity: .6; }

    /* Feedback message */
    .feedback-msg {
      position: fixed;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%) scale(0);
      background: white;
      border-radius: 24px;
      padding: 24px 40px;
      font-family: 'Fredoka One', cursive;
      font-size: 2rem;
      box-shadow: 0 20px 60px rgba(0,0,0,.2);
      z-index: 100;
      text-align: center;
      transition: transform .3s cubic-bezier(.34,1.56,.64,1);
      pointer-events: none;
    }
    .feedback-msg.show { transform: translate(-50%, -50%) scale(1); }
    .feedback-msg.correct-msg { color: #2ecc71; }
    .feedback-msg.wrong-msg   { color: #e74c3c; }

    /* ── CONFETTI ───────────────────────────────── */
    .confetti-piece {
      position: fixed;
      width: 12px; height: 12px;
      border-radius: 2px;
      pointer-events: none;
      z-index: 200;
      animation: confetti-fall 1s ease-in forwards;
    }
    @keyframes confetti-fall {
      0%   { opacity: 1; transform: translateY(0) rotate(0deg) scale(1); }
      100% { opacity: 0; transform: translateY(220px) rotate(720deg) scale(.4); }
    }

    /* ── COMPLETION SCREEN ──────────────────────── */
    #complete {
      background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%);
    }
    .complete-card {
      background: white;
      border-radius: 36px;
      padding: 48px 52px;
      max-width: 520px; width: 100%;
      box-shadow: 0 24px 60px rgba(0,0,0,.18);
      text-align: center;
    }
    .complete-emoji { font-size: 5rem; margin-bottom: 12px; animation: bounce-loop 1.2s ease infinite alternate; }
    @keyframes bounce-loop {
      from { transform: translateY(0); }
      to   { transform: translateY(-14px); }
    }
    .complete-title {
      font-family: 'Fredoka One', cursive;
      font-size: clamp(2rem, 5vw, 3rem);
      color: #a18cd1;
      margin-bottom: 8px;
    }
    .score-display {
      font-family: 'Fredoka One', cursive;
      font-size: 3.5rem;
      color: var(--coral);
      margin: 16px 0;
    }
    .score-stars { font-size: 2.2rem; margin-bottom: 20px; }
    .complete-btns { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; }
    .btn-retry, .btn-new {
      border: none; border-radius: 16px;
      padding: 16px 32px;
      font-family: 'Fredoka One', cursive;
      font-size: 1.2rem;
      cursor: pointer;
      transition: transform .15s, box-shadow .15s;
    }
    .btn-retry {
      background: linear-gradient(135deg, var(--coral), var(--peach));
      color: white;
      box-shadow: 0 8px 20px rgba(255,107,107,.35);
    }
    .btn-new {
      background: linear-gradient(135deg, var(--sky), #2bbcb3);
      color: white;
      box-shadow: 0 8px 20px rgba(78,205,196,.35);
    }
    .btn-retry:active, .btn-new:active { transform: scale(.96); }

    /* ── LOADING OVERLAY ───────────────────────── */
    #loader {
      position: fixed; inset: 0; z-index: 300;
      background: var(--bg);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 20px;
      transition: opacity .4s;
    }
    #loader.fade-out { opacity: 0; pointer-events: none; }
    .loader-emoji { font-size: 5rem; animation: bounce-loop 1s ease infinite alternate; }
    .loader-text {
      font-family: 'Fredoka One', cursive;
      font-size: 1.6rem; color: var(--coral);
    }
  </style>
</head>
<body>

<!-- Loading -->
<div id="loader">
  <div class="loader-emoji">🌟</div>
  <div class="loader-text">Loading Fun!</div>
</div>

<!-- Animated background bubbles -->
<div class="bubbles" id="bubbles"></div>

<!-- ── SETUP SCREEN ──────────────────────────────── -->
<div class="screen" id="setup">
  <div class="setup-card">
    <h1>🌈 Learning Fun!</h1>
    <p class="subtitle">For teachers & parents — set up before handing to child</p>

    <div class="field-wrap">
      <div class="field-label">👶 Child's Age</div>
      <div class="age-buttons" id="ageBtns">
        <button class="age-btn" data-age="4">4</button>
        <button class="age-btn" data-age="5">5</button>
        <button class="age-btn" data-age="6">6</button>
      </div>
    </div>

    <div class="field-wrap">
      <div class="field-label">🏷️ Child ID / Name (optional)</div>
      <input class="text-input" id="childId" type="text" placeholder="e.g. Child A, Student 3..." maxlength="40">
    </div>

    <button class="start-btn" id="startBtn" onclick="startQuiz()">
      ▶&nbsp; Let's Play!
    </button>
  </div>
</div>

<!-- ── QUIZ SCREEN ─────────────────────────────────── -->
<div class="screen hidden" id="quiz">
  <!-- Progress -->
  <div class="progress-wrap">
    <div class="progress-bar">
      <div class="progress-fill" id="progressFill" style="width:0%"></div>
    </div>
    <div class="progress-label" id="progressLabel">1 / 10</div>
    <div class="stars-score" id="starsScore"></div>
  </div>

  <!-- Question -->
  <div class="question-card">
    <button class="speak-btn" id="speakBtn" onclick="playQuestion()" title="Hear the question again">🔊</button>
    <div class="question-text" id="questionText">Loading…</div>
    <span class="category-badge" id="categoryBadge">Animals</span>
  </div>

  <!-- Answers -->
  <div class="options-grid" id="optionsGrid"></div>
</div>

<!-- ── COMPLETE SCREEN ─────────────────────────────── -->
<div class="screen hidden" id="complete">
  <div class="complete-card">
    <div class="complete-emoji" id="completeEmoji">🎉</div>
    <div class="complete-title" id="completeTitle">Amazing Job!</div>
    <div class="score-display" id="scoreDisplay">8 / 10</div>
    <div class="score-stars" id="scoreStars">⭐⭐⭐</div>
    <div class="complete-btns">
      <button class="btn-retry" onclick="retryQuiz()">🔄 Play Again</button>
      <button class="btn-new" onclick="newChild()">👶 New Child</button>
    </div>
  </div>
</div>

<!-- Feedback overlay -->
<div class="feedback-msg" id="feedbackMsg"></div>

<script>
// ═══════════════════════════════════════════════
//  QUESTION BANK
// ═══════════════════════════════════════════════
const QUESTIONS = [
  {
    id:'q01', category:'Animals', speak:'Touch the cat!', hint:'Cat',
    options:[
      {emoji:'🐱',label:'Cat',correct:true,  bg:'#FFF0F0'},
      {emoji:'🐶',label:'Dog',correct:false, bg:'#F0F4FF'},
      {emoji:'🐰',label:'Rabbit',correct:false,bg:'#F0FFF4'},
      {emoji:'🦆',label:'Duck',correct:false, bg:'#FFFBF0'},
    ]
  },
  {
    id:'q02', category:'Animals', speak:'Touch the dog!', hint:'Dog',
    options:[
      {emoji:'🐱',label:'Cat',correct:false,  bg:'#FFF0F0'},
      {emoji:'🐶',label:'Dog',correct:true,   bg:'#F0F4FF'},
      {emoji:'🐦',label:'Bird',correct:false, bg:'#F0FFF4'},
      {emoji:'🐠',label:'Fish',correct:false, bg:'#F0FFFF'},
    ]
  },
  {
    id:'q03', category:'Colors', speak:'Touch the red color!', hint:'Red',
    options:[
      {emoji:'🔴',label:'Red',   correct:true,  bg:'#FFF0F0'},
      {emoji:'🔵',label:'Blue',  correct:false, bg:'#F0F4FF'},
      {emoji:'🟡',label:'Yellow',correct:false, bg:'#FFFDF0'},
      {emoji:'🟢',label:'Green', correct:false, bg:'#F0FFF4'},
    ]
  },
  {
    id:'q04', category:'Shapes', speak:'Touch the circle!', hint:'Circle',
    options:[
      {emoji:'⭕',label:'Circle',   correct:true,  bg:'#FFF0F8'},
      {emoji:'⬛',label:'Square',   correct:false, bg:'#F0F0F0'},
      {emoji:'🔺',label:'Triangle', correct:false, bg:'#FFF8F0'},
      {emoji:'💠',label:'Diamond',  correct:false, bg:'#F0FCFF'},
    ]
  },
  {
    id:'q05', category:'Food', speak:'Touch the apple!', hint:'Apple',
    options:[
      {emoji:'🍎',label:'Apple',  correct:true,  bg:'#FFF0F0'},
      {emoji:'🍌',label:'Banana', correct:false, bg:'#FFFDF0'},
      {emoji:'🍇',label:'Grapes', correct:false, bg:'#F8F0FF'},
      {emoji:'🍊',label:'Orange', correct:false, bg:'#FFF8F0'},
    ]
  },
  {
    id:'q06', category:'Numbers', speak:'Touch the number two!', hint:'Two',
    options:[
      {emoji:'1️⃣',label:'One',  correct:false, bg:'#FFF0F0'},
      {emoji:'2️⃣',label:'Two',  correct:true,  bg:'#F0F4FF'},
      {emoji:'3️⃣',label:'Three',correct:false, bg:'#F0FFF4'},
      {emoji:'4️⃣',label:'Four', correct:false, bg:'#FFFBF0'},
    ]
  },
  {
    id:'q07', category:'Animals', speak:'Touch the elephant!', hint:'Elephant',
    options:[
      {emoji:'🐘',label:'Elephant',correct:true,  bg:'#F4F0FF'},
      {emoji:'🦁',label:'Lion',    correct:false, bg:'#FFF8E0'},
      {emoji:'🐻',label:'Bear',    correct:false, bg:'#FFF4EC'},
      {emoji:'🦊',label:'Fox',     correct:false, bg:'#FFF2EC'},
    ]
  },
  {
    id:'q08', category:'Colors', speak:'Touch the blue color!', hint:'Blue',
    options:[
      {emoji:'🔴',label:'Red',   correct:false, bg:'#FFF0F0'},
      {emoji:'🔵',label:'Blue',  correct:true,  bg:'#F0F4FF'},
      {emoji:'🟠',label:'Orange',correct:false, bg:'#FFF8F0'},
      {emoji:'🟣',label:'Purple',correct:false, bg:'#F8F0FF'},
    ]
  },
  {
    id:'q09', category:'Food', speak:'Touch the banana!', hint:'Banana',
    options:[
      {emoji:'🍓',label:'Strawberry',correct:false,bg:'#FFF0F0'},
      {emoji:'🍌',label:'Banana',    correct:true, bg:'#FFFDF0'},
      {emoji:'🍑',label:'Peach',     correct:false,bg:'#FFF8F0'},
      {emoji:'🍒',label:'Cherry',    correct:false,bg:'#FFF0F5'},
    ]
  },
  {
    id:'q10', category:'Shapes', speak:'Touch the star!', hint:'Star',
    options:[
      {emoji:'⭐',label:'Star',   correct:true,  bg:'#FFFDF0'},
      {emoji:'❤️',label:'Heart',  correct:false, bg:'#FFF0F0'},
      {emoji:'🌙',label:'Moon',   correct:false, bg:'#F0F0FF'},
      {emoji:'☁️',label:'Cloud',  correct:false, bg:'#F0F8FF'},
    ]
  }
];

// ═══════════════════════════════════════════════
//  STATE
// ═══════════════════════════════════════════════
let state = {
  sessionId: crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36),
  childAge: null,
  childId: '',
  questions: [],
  current: 0,
  score: 0,
  results: [],
  questionStartTime: 0,
  attempts: 0,
  answered: false,
};

// ═══════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════
window.addEventListener('load', () => {
  buildBubbles();
  buildAgeButtons();
  setTimeout(() => {
    document.getElementById('loader').classList.add('fade-out');
  }, 800);
});

function buildBubbles() {
  const container = document.getElementById('bubbles');
  const colors = ['#FF6B6B','#4ECDC4','#FFE66D','#A8E6CF','#C9B1FF','#FFB347'];
  for (let i = 0; i < 14; i++) {
    const b = document.createElement('div');
    b.className = 'bubble';
    const size = 40 + Math.random() * 120;
    b.style.cssText = `
      width:${size}px; height:${size}px;
      left:${Math.random()*100}%;
      bottom:${-20}%;
      background:${colors[i % colors.length]};
      animation-duration:${8 + Math.random()*12}s;
      animation-delay:${Math.random()*-15}s;
    `;
    container.appendChild(b);
  }
}

function buildAgeButtons() {
  document.querySelectorAll('.age-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.age-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.childAge = parseInt(btn.dataset.age);
    });
  });
}

// ═══════════════════════════════════════════════
//  SETUP → QUIZ
// ═══════════════════════════════════════════════
function startQuiz() {
  if (!state.childAge) {
    document.getElementById('ageBtns').animate([
      {transform:'translateX(-6px)'},{transform:'translateX(6px)'},
      {transform:'translateX(-4px)'},{transform:'translateX(4px)'},
      {transform:'translateX(0)'}
    ], {duration:350, easing:'ease'});
    return;
  }
  state.childId  = document.getElementById('childId').value.trim();
  state.sessionId = crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36);
  state.questions = shuffle([...QUESTIONS]);
  state.current   = 0;
  state.score     = 0;
  state.results   = [];
  showScreen('quiz');
  loadQuestion();
}

// ═══════════════════════════════════════════════
//  QUESTION LOADING
// ═══════════════════════════════════════════════
function loadQuestion() {
  const q = state.questions[state.current];
  state.questionStartTime = Date.now();
  state.attempts = 0;
  state.answered = false;

  // Update progress
  const pct = (state.current / state.questions.length) * 100;
  document.getElementById('progressFill').style.width = pct + '%';
  document.getElementById('progressLabel').textContent =
    `${state.current + 1} / ${state.questions.length}`;
  updateStars();

  // Update question card
  document.getElementById('questionText').textContent = q.hint;
  document.getElementById('categoryBadge').textContent = q.category;

  // Build options (shuffled)
  const grid = document.getElementById('optionsGrid');
  grid.innerHTML = '';
  const opts = shuffle([...q.options]);
  opts.forEach((opt, i) => {
    const card = document.createElement('div');
    card.className = 'option-card';
    card.style.background = opt.bg;
    card.innerHTML = `
      <div class="option-emoji">${opt.emoji}</div>
      <div class="option-label">${opt.label}</div>
    `;
    card.addEventListener('pointerdown', () => handleAnswer(card, opt, q));
    grid.appendChild(card);
  });

  // Auto-play audio after a short delay
  setTimeout(() => playQuestion(), 600);
}

// ═══════════════════════════════════════════════
//  SPEECH
// ═══════════════════════════════════════════════
function playQuestion() {
  const q = state.questions[state.current];
  if (!window.speechSynthesis) return;
  speechSynthesis.cancel();
  const btn = document.getElementById('speakBtn');
  btn.classList.add('speaking');
  const utt = new SpeechSynthesisUtterance(q.speak);
  utt.rate  = 0.85;
  utt.pitch = 1.2;
  utt.lang  = 'en-US';
  utt.onend = () => btn.classList.remove('speaking');
  speechSynthesis.speak(utt);
}

// ═══════════════════════════════════════════════
//  ANSWER HANDLING
// ═══════════════════════════════════════════════
function handleAnswer(card, opt, q) {
  if (state.answered) return;
  state.attempts++;

  const allCards = document.querySelectorAll('.option-card');

  if (opt.correct) {
    state.answered = true;
    card.classList.add('correct');
    allCards.forEach(c => { if (c !== card) c.classList.add('disabled'); });
    state.score++;

    const responseTime = Date.now() - state.questionStartTime;
    const correctOpt   = q.options.find(o => o.correct);
    const result = {
      session_id:       state.sessionId,
      child_age:        state.childAge,
      child_id:         state.childId,
      question_id:      q.id,
      question_text:    q.speak,
      category:         q.category,
      correct_label:    correctOpt.label,
      selected_label:   opt.label,
      is_correct:       true,
      attempts:         state.attempts,
      response_time_ms: responseTime,
    };
    state.results.push(result);
    submitResult(result);

    showFeedback('✅ Great job!', 'correct-msg');
    launchConfetti(card);

    // Speak praise
    speakText(['Wonderful!','Amazing!','Super!','You got it!'][Math.floor(Math.random()*4)]);

    setTimeout(() => {
      hideFeedback();
      state.current++;
      if (state.current < state.questions.length) {
        loadQuestion();
      } else {
        showComplete();
      }
    }, 1400);

  } else {
    card.classList.add('wrong');
    setTimeout(() => card.classList.remove('wrong'), 500);

    // Record wrong attempt but don't lock
    speakText('Try again!');
    showFeedback('🤔 Try again!', 'wrong-msg');
    setTimeout(hideFeedback, 900);
  }
}

// ═══════════════════════════════════════════════
//  FEEDBACK + CONFETTI
// ═══════════════════════════════════════════════
function showFeedback(msg, cls) {
  const el = document.getElementById('feedbackMsg');
  el.textContent = msg;
  el.className   = 'feedback-msg ' + cls + ' show';
}
function hideFeedback() {
  document.getElementById('feedbackMsg').classList.remove('show');
}

function launchConfetti(fromCard) {
  const rect   = fromCard.getBoundingClientRect();
  const cx     = rect.left + rect.width / 2;
  const cy     = rect.top  + rect.height / 2;
  const colors = ['#FF6B6B','#4ECDC4','#FFE66D','#A8E6CF','#C9B1FF','#FFB347'];
  for (let i = 0; i < 24; i++) {
    const p = document.createElement('div');
    p.className = 'confetti-piece';
    const angle  = Math.random() * 360;
    const dist   = 60 + Math.random() * 120;
    const dx     = Math.cos(angle * Math.PI/180) * dist;
    const dy     = Math.sin(angle * Math.PI/180) * dist - 80;
    p.style.cssText = `
      left:${cx}px; top:${cy}px;
      background:${colors[i % colors.length]};
      transform:translate(${dx}px,${dy}px) rotate(${Math.random()*360}deg);
      animation-duration:${0.8 + Math.random()*0.5}s;
    `;
    document.body.appendChild(p);
    setTimeout(() => p.remove(), 1400);
  }
}

function speakText(text) {
  if (!window.speechSynthesis) return;
  speechSynthesis.cancel();
  const utt = new SpeechSynthesisUtterance(text);
  utt.rate = 0.9; utt.pitch = 1.3; utt.lang = 'en-US';
  speechSynthesis.speak(utt);
}

// ═══════════════════════════════════════════════
//  STARS
// ═══════════════════════════════════════════════
function updateStars() {
  const container = document.getElementById('starsScore');
  container.innerHTML = '';
  for (let i = 0; i < state.questions.length; i++) {
    const s = document.createElement('span');
    s.className = 'star-icon' + (i < state.score ? ' lit' : '');
    s.textContent = '⭐';
    container.appendChild(s);
  }
}

// ═══════════════════════════════════════════════
//  COMPLETION
// ═══════════════════════════════════════════════
function showComplete() {
  const total = state.questions.length;
  const pct   = state.score / total;
  const emoji   = pct >= .8 ? '🏆' : pct >= .6 ? '🎉' : '💪';
  const title   = pct >= .8 ? 'Amazing!'  : pct >= .6 ? 'Great Job!' : 'Keep Trying!';
  const stars   = pct >= .8 ? '⭐⭐⭐' : pct >= .6 ? '⭐⭐' : '⭐';

  document.getElementById('completeEmoji').textContent  = emoji;
  document.getElementById('completeTitle').textContent  = title;
  document.getElementById('scoreDisplay').textContent   = `${state.score} / ${total}`;
  document.getElementById('scoreStars').textContent     = stars;

  showScreen('complete');
  speakText(`You got ${state.score} out of ${total}! ${title}`);
}

function retryQuiz() {
  state.questions = shuffle([...QUESTIONS]);
  state.current   = 0;
  state.score     = 0;
  state.results   = [];
  state.sessionId = crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36);
  showScreen('quiz');
  loadQuestion();
}

function newChild() {
  showScreen('setup');
}

// ═══════════════════════════════════════════════
//  DATA SUBMISSION
// ═══════════════════════════════════════════════
async function submitResult(result) {
  try {
    await fetch('submit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(result),
    });
  } catch (e) {
    console.warn('Submit error:', e);
  }
}

// ═══════════════════════════════════════════════
//  UTILITIES
// ═══════════════════════════════════════════════
function shuffle(arr) {
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

function showScreen(id) {
  document.querySelectorAll('.screen').forEach(s => {
    s.classList.toggle('hidden', s.id !== id);
  });
}
</script>
</body>
</html>
