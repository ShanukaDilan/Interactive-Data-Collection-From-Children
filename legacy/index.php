<?php
// Load questions from data/questions.json; fall back to built-in set if missing/empty.
$_qFile = __DIR__ . '/data/questions.json';
$_qData = [];
if (file_exists($_qFile)) {
    $_loaded = json_decode(file_get_contents($_qFile), true);
    if (is_array($_loaded) && count($_loaded) > 0) {
        $_qData = $_loaded;
    }
}
if (empty($_qData)) {
    // Built-in fallback — mirrors the original 10 questions
    $_qData = [
      ['id'=>'q01','category'=>'Animals','speak'=>'Touch the cat!','hint'=>'Cat','image'=>null,'audio'=>null,
       'options'=>[['label'=>'Cat','correct'=>true,'bg'=>'#FFF0F0','emoji'=>'🐱','image'=>null],['label'=>'Dog','correct'=>false,'bg'=>'#F0F4FF','emoji'=>'🐶','image'=>null],['label'=>'Rabbit','correct'=>false,'bg'=>'#F0FFF4','emoji'=>'🐰','image'=>null],['label'=>'Duck','correct'=>false,'bg'=>'#FFFBF0','emoji'=>'🦆','image'=>null]]],
      ['id'=>'q02','category'=>'Animals','speak'=>'Touch the dog!','hint'=>'Dog','image'=>null,'audio'=>null,
       'options'=>[['label'=>'Cat','correct'=>false,'bg'=>'#FFF0F0','emoji'=>'🐱','image'=>null],['label'=>'Dog','correct'=>true,'bg'=>'#F0F4FF','emoji'=>'🐶','image'=>null],['label'=>'Bird','correct'=>false,'bg'=>'#F0FFF4','emoji'=>'🐦','image'=>null],['label'=>'Fish','correct'=>false,'bg'=>'#F0FFFF','emoji'=>'🐠','image'=>null]]],
      ['id'=>'q03','category'=>'Colors','speak'=>'Touch the red color!','hint'=>'Red','image'=>null,'audio'=>null,
       'options'=>[['label'=>'Red','correct'=>true,'bg'=>'#FFF0F0','emoji'=>'🔴','image'=>null],['label'=>'Blue','correct'=>false,'bg'=>'#F0F4FF','emoji'=>'🔵','image'=>null],['label'=>'Yellow','correct'=>false,'bg'=>'#FFFDF0','emoji'=>'🟡','image'=>null],['label'=>'Green','correct'=>false,'bg'=>'#F0FFF4','emoji'=>'🟢','image'=>null]]],
      ['id'=>'q04','category'=>'Shapes','speak'=>'Touch the circle!','hint'=>'Circle','image'=>null,'audio'=>null,
       'options'=>[['label'=>'Circle','correct'=>true,'bg'=>'#FFF0F8','emoji'=>'⭕','image'=>null],['label'=>'Square','correct'=>false,'bg'=>'#F0F0F0','emoji'=>'⬛','image'=>null],['label'=>'Triangle','correct'=>false,'bg'=>'#FFF8F0','emoji'=>'🔺','image'=>null],['label'=>'Diamond','correct'=>false,'bg'=>'#F0FCFF','emoji'=>'💠','image'=>null]]],
      ['id'=>'q05','category'=>'Food','speak'=>'Touch the apple!','hint'=>'Apple','image'=>null,'audio'=>null,
       'options'=>[['label'=>'Apple','correct'=>true,'bg'=>'#FFF0F0','emoji'=>'🍎','image'=>null],['label'=>'Banana','correct'=>false,'bg'=>'#FFFDF0','emoji'=>'🍌','image'=>null],['label'=>'Grapes','correct'=>false,'bg'=>'#F8F0FF','emoji'=>'🍇','image'=>null],['label'=>'Orange','correct'=>false,'bg'=>'#FFF8F0','emoji'=>'🍊','image'=>null]]],
      ['id'=>'q06','category'=>'Numbers','speak'=>'Touch the number two!','hint'=>'Two','image'=>null,'audio'=>null,
       'options'=>[['label'=>'One','correct'=>false,'bg'=>'#FFF0F0','emoji'=>'1️⃣','image'=>null],['label'=>'Two','correct'=>true,'bg'=>'#F0F4FF','emoji'=>'2️⃣','image'=>null],['label'=>'Three','correct'=>false,'bg'=>'#F0FFF4','emoji'=>'3️⃣','image'=>null],['label'=>'Four','correct'=>false,'bg'=>'#FFFBF0','emoji'=>'4️⃣','image'=>null]]],
      ['id'=>'q07','category'=>'Animals','speak'=>'Touch the elephant!','hint'=>'Elephant','image'=>null,'audio'=>null,
       'options'=>[['label'=>'Elephant','correct'=>true,'bg'=>'#F4F0FF','emoji'=>'🐘','image'=>null],['label'=>'Lion','correct'=>false,'bg'=>'#FFF8E0','emoji'=>'🦁','image'=>null],['label'=>'Bear','correct'=>false,'bg'=>'#FFF4EC','emoji'=>'🐻','image'=>null],['label'=>'Fox','correct'=>false,'bg'=>'#FFF2EC','emoji'=>'🦊','image'=>null]]],
      ['id'=>'q08','category'=>'Colors','speak'=>'Touch the blue color!','hint'=>'Blue','image'=>null,'audio'=>null,
       'options'=>[['label'=>'Red','correct'=>false,'bg'=>'#FFF0F0','emoji'=>'🔴','image'=>null],['label'=>'Blue','correct'=>true,'bg'=>'#F0F4FF','emoji'=>'🔵','image'=>null],['label'=>'Orange','correct'=>false,'bg'=>'#FFF8F0','emoji'=>'🟠','image'=>null],['label'=>'Purple','correct'=>false,'bg'=>'#F8F0FF','emoji'=>'🟣','image'=>null]]],
      ['id'=>'q09','category'=>'Food','speak'=>'Touch the banana!','hint'=>'Banana','image'=>null,'audio'=>null,
       'options'=>[['label'=>'Strawberry','correct'=>false,'bg'=>'#FFF0F0','emoji'=>'🍓','image'=>null],['label'=>'Banana','correct'=>true,'bg'=>'#FFFDF0','emoji'=>'🍌','image'=>null],['label'=>'Peach','correct'=>false,'bg'=>'#FFF8F0','emoji'=>'🍑','image'=>null],['label'=>'Cherry','correct'=>false,'bg'=>'#FFF0F5','emoji'=>'🍒','image'=>null]]],
      ['id'=>'q10','category'=>'Shapes','speak'=>'Touch the star!','hint'=>'Star','image'=>null,'audio'=>null,
       'options'=>[['label'=>'Star','correct'=>true,'bg'=>'#FFFDF0','emoji'=>'⭐','image'=>null],['label'=>'Heart','correct'=>false,'bg'=>'#FFF0F0','emoji'=>'❤️','image'=>null],['label'=>'Moon','correct'=>false,'bg'=>'#F0F0FF','emoji'=>'🌙','image'=>null],['label'=>'Cloud','correct'=>false,'bg'=>'#F0F8FF','emoji'=>'☁️','image'=>null]]],
    ];
}
$_questionsJson = json_encode($_qData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>
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

    /* ── MODE SELECTOR ──────────────────────────── */
    .mode-buttons { display:flex; gap:10px; }
    .mode-btn {
      flex:1; padding:14px 10px;
      border-radius:16px; border:3px solid #eee;
      background:white; cursor:pointer;
      transition:all .2s;
      display:flex; flex-direction:column; align-items:center; gap:5px;
      font-family:'Fredoka One',cursive; font-size:.95rem; color:#aaa;
      line-height:1.2;
    }
    .mode-btn small {
      font-family:'Nunito',sans-serif; font-size:.72rem;
      color:#bbb; font-weight:700; display:block;
    }
    .mode-btn.active { border-color:var(--sky); background:#f0fffe; color:#2bbcb3; }
    .mode-btn.active small { color:#4ECDC4; }
    .mode-pill {
      border-radius:99px; padding:4px 14px;
      font-family:'Nunito',sans-serif; font-weight:800; font-size:.75rem;
      white-space:nowrap;
    }
    .mode-pill-correct { background:rgba(255,107,107,.15); color:var(--coral); }
    .mode-pill-free    { background:rgba(78,205,196,.15);  color:#2bbcb3; }

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
    .question-img {
      max-width: 100px; max-height: 72px;
      object-fit: contain;
      border-radius: 10px;
      flex-shrink: 0;
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

    /* Question emoji / letter display */
    .question-emoji-display {
      flex-shrink: 0;
      line-height: 1.25;
      font-family: 'Fredoka One', cursive;
      letter-spacing: 0.05em;
    }
    .question-emoji-display.qemoji-letter {
      font-size: clamp(3.5rem, 10vw, 6rem);
    }
    .question-emoji-display.qemoji-count {
      font-size: clamp(1.8rem, 5vw, 3rem);
      max-width: 200px;
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
    .option-image {
      width: clamp(70px, 18vw, 120px);
      height: clamp(70px, 18vw, 120px);
      object-fit: contain;
      border-radius: 14px;
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
    .option-card.correct .option-emoji,
    .option-card.correct .option-image { transform: scale(1.2); }
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

    /* ══ RESPONSIVE — TOUCH / MOBILE / TABLET ══════════
       Setup: allow vertical scroll when card is taller
       than the viewport (common on small phones).
    ═══════════════════════════════════════════════════ */
    #setup { overflow-y: auto; justify-content: flex-start; overscroll-behavior-y: contain; }
    .setup-card { margin: auto; }

    /* ── ≤ 480px  (phones) ─────────────────────────── */
    @media (max-width: 480px) {
      .question-emoji-display.qemoji-letter { font-size: 2.6rem; }
      .question-emoji-display.qemoji-count  { font-size: 1.5rem; max-width: 160px; }
      .setup-card { padding: 24px 20px; border-radius: 24px; }
      .setup-card h1 { font-size: 1.8rem; margin-bottom: 4px; }
      .setup-card .subtitle { font-size: .88rem; margin-bottom: 18px; }
      .field-wrap { margin-bottom: 14px; }
      .age-buttons { gap: 8px; margin-bottom: 18px; }
      .age-btn { width: 64px; height: 64px; font-size: 1.3rem; }
      .mode-buttons { gap: 8px; }
      .mode-btn { padding: 11px 7px; font-size: .85rem; border-radius: 14px; }
      .mode-btn small { font-size: .66rem; }
      .start-btn { padding: 15px 32px; font-size: 1.2rem; border-radius: 14px; }

      #quiz { padding: 10px; }
      .progress-wrap { gap: 8px; margin-bottom: 10px; }
      .progress-bar { height: 10px; }
      .progress-label { font-size: .85rem; }
      .stars-score { display: none; }
      .mode-pill { font-size: .68rem; padding: 3px 10px; }
      .question-card { padding: 12px 14px; gap: 10px; margin-bottom: 12px; border-radius: 20px; }
      .speak-btn { width: 50px; height: 50px; font-size: 1.4rem; }
      .question-img { max-width: 56px; max-height: 48px; }
      .options-grid { gap: 10px; }
      .option-card { padding: 12px 8px; min-height: 118px; border-radius: 20px; border-width: 3px; gap: 6px; }

      .complete-card { padding: 28px 18px; border-radius: 28px; }
      .complete-emoji { font-size: 3.5rem; }
      .complete-title { font-size: 1.8rem; }
      .score-display { font-size: 2.4rem; margin: 12px 0; }
      .score-stars { font-size: 1.8rem; margin-bottom: 16px; }
      .complete-btns { gap: 10px; }
      .btn-retry, .btn-new { padding: 14px 22px; font-size: 1rem; border-radius: 14px; }
      .feedback-msg { padding: 16px 24px; font-size: 1.4rem; border-radius: 18px; width: 88%; }
    }

    /* ── ≤ 360px  (very small phones) ─────────────── */
    @media (max-width: 360px) {
      .setup-card { padding: 20px 16px; }
      .age-btn { width: 58px; height: 58px; font-size: 1.2rem; }
      .option-card { min-height: 108px; padding: 10px 6px; }
      .complete-card { padding: 22px 14px; }
      .complete-btns { flex-direction: column; }
      .btn-retry, .btn-new { width: 100%; }
    }

    /* ── 481–768px  (large phones / small tablets) ── */
    @media (min-width: 481px) and (max-width: 768px) {
      .setup-card { padding: 32px 28px; }
      .complete-card { padding: 36px 32px; }
      #quiz { padding: 14px; }
      .option-card { min-height: 130px; }
    }

    /* ── Landscape phone: height ≤ 520px ───────────── */
    @media (max-height: 520px) and (orientation: landscape) {
      .setup-card { padding: 14px 28px; max-width: 560px; }
      .setup-card h1 { font-size: 1.4rem; margin-bottom: 2px; }
      .setup-card .subtitle { font-size: .8rem; margin-bottom: 12px; }
      .age-btn { width: 52px; height: 52px; font-size: 1.1rem; }
      .age-buttons { margin-bottom: 12px; }
      .field-wrap { margin-bottom: 10px; }
      .mode-btn { padding: 8px 6px; font-size: .82rem; }
      .mode-btn small { display: none; }
      .start-btn { padding: 13px 32px; }

      #quiz { padding: 8px 14px; }
      .progress-wrap { margin-bottom: 7px; }
      .question-card { padding: 8px 14px; margin-bottom: 8px; }
      .speak-btn { width: 44px; height: 44px; font-size: 1.2rem; }
      .options-grid { gap: 8px; }
      .option-card {
        flex-direction: row; justify-content: flex-start;
        min-height: 66px; padding: 8px 14px; gap: 12px;
      }
      .option-emoji { font-size: 1.8rem !important; }
      .option-image { width: 44px !important; height: 44px !important; }
      .option-label { font-size: .82rem !important; }

      .complete-card { padding: 18px 32px; }
      .complete-emoji { font-size: 2.4rem; margin-bottom: 6px; }
      .score-display { font-size: 2rem; margin: 8px 0; }
      .score-stars { font-size: 1.5rem; margin-bottom: 12px; }
    }

    /* ── NUMBER INPUT KEYPAD ─────────────────────────── */
    .number-input-wrap {
      max-width: 700px; width: 100%;
      display: flex; flex-direction: column;
      align-items: center; gap: 16px;
    }
    .number-display {
      font-family: 'Fredoka One', cursive;
      font-size: 5rem;
      color: #aaa;
      background: white;
      border-radius: 24px;
      min-width: 140px; height: 120px;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 8px 24px rgba(0,0,0,.10);
      border: 4px dashed #ddd;
      transition: all .25s;
      padding: 0 24px;
    }
    .number-display.has-value   { border-color: var(--sky); border-style: solid; color: var(--sky); }
    .number-display.correct-val { border-color: #2ecc71; color: #2ecc71; background: #f0fff6; border-style: solid; }
    .number-display.wrong-val   { border-color: #e74c3c; color: #e74c3c; background: #fff5f5; border-style: solid; animation: shake .4s ease; }
    .number-keypad {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      max-width: 340px; width: 100%;
    }
    .num-btn {
      aspect-ratio: 1;
      border-radius: 20px;
      border: 3px solid #eee;
      background: white;
      font-family: 'Fredoka One', cursive;
      font-size: clamp(1.5rem, 5.5vw, 2.6rem);
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(0,0,0,.09);
      transition: transform .12s, box-shadow .12s, background .12s;
      color: #444;
      display: flex; align-items: center; justify-content: center;
      -webkit-tap-highlight-color: transparent;
      user-select: none;
    }
    .num-btn:hover  { background: #f0fffe; border-color: var(--sky); color: #2bbcb3; }
    .num-btn:active { transform: scale(.86); box-shadow: 0 2px 6px rgba(0,0,0,.10); }
    .num-btn.num-del {
      background: #fff5f5; border-color: #ffd0d0;
      font-size: clamp(1.1rem, 3.5vw, 1.7rem); color: #e74c3c;
    }
    .num-btn.num-ok {
      background: linear-gradient(135deg, var(--sky), #2bbcb3);
      border-color: transparent; color: white;
      box-shadow: 0 6px 16px rgba(78,205,196,.4);
    }
    .num-btn:disabled { opacity: .45; pointer-events: none; }

    @media (max-width: 480px) {
      .number-display { font-size: 3.5rem; height: 90px; min-width: 110px; }
      .number-keypad  { gap: 8px; max-width: 272px; }
      .num-btn        { border-radius: 14px; border-width: 2px; }
    }
    @media (max-height: 520px) and (orientation: landscape) {
      .number-display { font-size: 2.4rem; height: 66px; min-width: 88px; }
      .number-keypad  { gap: 6px; max-width: 220px; }
      .num-btn        { border-radius: 12px; }
    }

    /* ── Safe-area insets (notch / home indicator) ── */
    @supports (padding: max(0px)) {
      #setup {
        padding-top:    max(20px, env(safe-area-inset-top));
        padding-left:   max(20px, env(safe-area-inset-left));
        padding-right:  max(20px, env(safe-area-inset-right));
        padding-bottom: max(20px, env(safe-area-inset-bottom));
      }
      #quiz {
        padding-top:    max(12px, env(safe-area-inset-top));
        padding-left:   max(16px, env(safe-area-inset-left));
        padding-right:  max(16px, env(safe-area-inset-right));
        padding-bottom: max(16px, env(safe-area-inset-bottom));
      }
      #complete {
        padding-left:   max(20px, env(safe-area-inset-left));
        padding-right:  max(20px, env(safe-area-inset-right));
        padding-bottom: max(20px, env(safe-area-inset-bottom));
      }
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
    <p class="subtitle">For teachers &amp; parents — set up before handing to child</p>

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

    <div class="field-wrap">
      <div class="field-label">🎮 Quiz Mode</div>
      <div class="mode-buttons">
        <button type="button" class="mode-btn active" data-mode="correct" onclick="selectMode('correct',this)">
          🎯 Find the Answer
          <small>Must pick the correct one</small>
        </button>
        <button type="button" class="mode-btn" data-mode="free" onclick="selectMode('free',this)">
          📝 Free Choice
          <small>Records any selection</small>
        </button>
      </div>
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
    <span class="mode-pill" id="modePill"></span>
  </div>

  <!-- Question -->
  <div class="question-card">
    <button class="speak-btn" id="speakBtn" onclick="playQuestion()" title="Hear the question again">🔊</button>
    <img id="questionImage" class="question-img" style="display:none" alt="">
    <div class="question-emoji-display" id="questionEmojiDisplay" style="display:none"></div>
    <div class="question-text" id="questionText">Loading…</div>
    <span class="category-badge" id="categoryBadge">Animals</span>
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
//  QUESTION BANK  (loaded from PHP / questions.json)
// ═══════════════════════════════════════════════
const QUESTIONS = <?= $_questionsJson ?>;

// ═══════════════════════════════════════════════
//  STATE
// ═══════════════════════════════════════════════
let state = {
  sessionId: crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36),
  childAge: null,
  childId: '',
  mode: 'correct',   // 'correct' | 'free'
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

function selectMode(mode, btn) {
  state.mode = mode;
  document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
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
  state.childId   = document.getElementById('childId').value.trim();
  state.sessionId = crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36);
  state.questions = shuffle([...QUESTIONS]);
  state.current   = 0;
  state.score     = 0;
  state.results   = [];

  // Mode pill indicator on quiz screen
  const pill = document.getElementById('modePill');
  if (state.mode === 'free') {
    pill.textContent = '📝 Free Choice';
    pill.className   = 'mode-pill mode-pill-free';
  } else {
    pill.textContent = '🎯 Find the Answer';
    pill.className   = 'mode-pill mode-pill-correct';
  }

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

  // Progress
  const pct = (state.current / state.questions.length) * 100;
  document.getElementById('progressFill').style.width = pct + '%';
  document.getElementById('progressLabel').textContent =
    `${state.current + 1} / ${state.questions.length}`;
  updateStars();

  // Question card
  document.getElementById('questionText').textContent = q.hint;
  document.getElementById('categoryBadge').textContent = q.category;

  // Question image (if uploaded)
  const qImg = document.getElementById('questionImage');
  if (q.image) {
    qImg.src   = q.image;
    qImg.style.display = '';
  } else {
    qImg.style.display = 'none';
    qImg.src = '';
  }

  // Question emoji / letter display
  const qEmojiEl = document.getElementById('questionEmojiDisplay');
  if (q.question_emoji) {
    qEmojiEl.textContent = q.question_emoji;
    qEmojiEl.style.display = '';
    const codepoints = [...q.question_emoji].length;
    qEmojiEl.className = 'question-emoji-display ' + (codepoints <= 1 ? 'qemoji-letter' : 'qemoji-count');
  } else {
    qEmojiEl.style.display = 'none';
    qEmojiEl.textContent = '';
  }

  // Show number keypad or choice cards depending on question type
  const isNumberInput = q.type === 'number-input';
  document.getElementById('optionsGrid').style.display    = isNumberInput ? 'none' : '';
  document.getElementById('numberInputWrap').style.display = isNumberInput ? '' : 'none';

  if (isNumberInput) {
    buildNumberKeypad(q);
  } else {
    // Build option cards (shuffled)
    const grid = document.getElementById('optionsGrid');
    grid.innerHTML = '';
    grid.style.gridTemplateColumns = q.options.length <= 3 ? 'repeat(3, 1fr)' : '1fr 1fr';
    const opts = shuffle([...q.options]);
    opts.forEach(opt => {
      const card = document.createElement('div');
      card.className    = 'option-card';
      card.style.background = opt.bg || '#F0F4FF';

      if (opt.image) {
        const img = document.createElement('img');
        img.className = 'option-image';
        img.src       = opt.image;
        img.alt       = opt.label;
        card.appendChild(img);
      } else {
        const emojiEl = document.createElement('div');
        emojiEl.className   = 'option-emoji';
        emojiEl.textContent = opt.emoji || '❓';
        card.appendChild(emojiEl);
      }

      const labelEl = document.createElement('div');
      labelEl.className   = 'option-label';
      labelEl.textContent = opt.label;
      card.appendChild(labelEl);

      card.addEventListener('pointerdown', () => handleAnswer(card, opt, q));
      grid.appendChild(card);
    });
  }

  setTimeout(() => playQuestion(), 600);
}

// ═══════════════════════════════════════════════
//  SPEECH / AUDIO
// ═══════════════════════════════════════════════
function playQuestion() {
  const q   = state.questions[state.current];
  const btn = document.getElementById('speakBtn');
  btn.classList.add('speaking');

  if (q.audio) {
    const audio = new Audio(q.audio);
    audio.onended = () => btn.classList.remove('speaking');
    audio.onerror = () => { btn.classList.remove('speaking'); useTTS(q.speak, btn); };
    audio.play().catch(() => { btn.classList.remove('speaking'); useTTS(q.speak, btn); });
    return;
  }
  useTTS(q.speak, btn);
}

function useTTS(text, btn) {
  if (!window.speechSynthesis) { btn && btn.classList.remove('speaking'); return; }
  speechSynthesis.cancel();
  const utt   = new SpeechSynthesisUtterance(text);
  utt.rate    = 0.85;
  utt.pitch   = 1.2;
  utt.lang    = 'en-US';
  utt.onend   = () => btn && btn.classList.remove('speaking');
  speechSynthesis.speak(utt);
}

function speakText(text) {
  useTTS(text, null);
}

// ═══════════════════════════════════════════════
//  ANSWER HANDLING
// ═══════════════════════════════════════════════
function handleAnswer(card, opt, q) {
  if (state.answered) return;
  state.attempts++;

  const allCards    = document.querySelectorAll('.option-card');
  const correctOpt  = q.options.find(o => o.correct);
  const responseTime = Date.now() - state.questionStartTime;

  // ── FREE CHOICE MODE ─────────────────────────────────────────
  // Record whatever the child picks and advance — no right/wrong feedback.
  if (state.mode === 'free') {
    state.answered = true;
    allCards.forEach(c => c.classList.add('disabled'));
    card.style.border = '4px solid #4ECDC4';
    card.style.background = '#f0fffe';

    if (opt.correct) state.score++;

    const result = {
      session_id:       state.sessionId,
      child_age:        state.childAge,
      child_id:         state.childId,
      quiz_mode:        'free',
      question_id:      q.id,
      question_text:    q.speak,
      category:         q.category,
      correct_label:    correctOpt.label,
      selected_label:   opt.label,
      is_correct:       opt.correct,
      attempts:         1,
      response_time_ms: responseTime,
    };
    state.results.push(result);
    submitResult(result);

    setTimeout(() => {
      state.current++;
      if (state.current < state.questions.length) {
        loadQuestion();
      } else {
        showComplete();
      }
    }, 650);
    return;
  }

  // ── FIND CORRECT MODE (default) ──────────────────────────────
  if (opt.correct) {
    state.answered = true;
    card.classList.add('correct');
    allCards.forEach(c => { if (c !== card) c.classList.add('disabled'); });
    state.score++;

    const result = {
      session_id:       state.sessionId,
      child_age:        state.childAge,
      child_id:         state.childId,
      quiz_mode:        'correct',
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

// ═══════════════════════════════════════════════
//  STARS
// ═══════════════════════════════════════════════
function updateStars() {
  const container = document.getElementById('starsScore');
  container.innerHTML = '';
  // In free choice mode stars are hidden — no right/wrong shown to the child
  if (state.mode === 'free') return;
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

  if (state.mode === 'free') {
    // Free choice: always celebrate — don't reveal right/wrong to the child
    document.getElementById('completeEmoji').textContent = '🌟';
    document.getElementById('completeTitle').textContent = 'All Done!';
    document.getElementById('scoreDisplay').textContent  = `${total} / ${total}`;
    document.getElementById('scoreStars').textContent    = '⭐⭐⭐';
    showScreen('complete');
    speakText('Well done! All finished!');
  } else {
    const pct   = state.score / total;
    const emoji = pct >= .8 ? '🏆' : pct >= .6 ? '🎉' : '💪';
    const title = pct >= .8 ? 'Amazing!'  : pct >= .6 ? 'Great Job!' : 'Keep Trying!';
    const stars = pct >= .8 ? '⭐⭐⭐' : pct >= .6 ? '⭐⭐' : '⭐';
    document.getElementById('completeEmoji').textContent = emoji;
    document.getElementById('completeTitle').textContent = title;
    document.getElementById('scoreDisplay').textContent  = `${state.score} / ${total}`;
    document.getElementById('scoreStars').textContent    = stars;
    showScreen('complete');
    speakText(`You got ${state.score} out of ${total}! ${title}`);
  }
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

// ═══════════════════════════════════════════════
//  NUMBER KEYPAD (counting questions)
// ═══════════════════════════════════════════════
function buildNumberKeypad(q) {
  const display = document.getElementById('numberDisplay');
  display.textContent = '?';
  display.className   = 'number-display';

  const correctAnswer = String(q.correct_number ?? q.options.find(o => o.correct)?.label ?? '');
  let currentInput = '';

  const keypad = document.getElementById('numberKeypad');
  keypad.innerHTML = '';

  // Phone-style layout: 1-9, then ⌫, 0, ✓
  const keys = ['1','2','3','4','5','6','7','8','9','⌫','0','✓'];
  keys.forEach(k => {
    const btn = document.createElement('button');
    btn.className   = 'num-btn';
    btn.textContent = k;
    if (k === '⌫') btn.classList.add('num-del');
    if (k === '✓') btn.classList.add('num-ok');

    btn.addEventListener('pointerdown', e => {
      e.preventDefault();
      if (state.answered) return;

      if (k === '⌫') {
        currentInput = currentInput.slice(0, -1);
        display.textContent = currentInput || '?';
        display.className   = 'number-display' + (currentInput ? ' has-value' : '');
        return;
      }

      if (k === '✓') {
        if (currentInput) submitNumberAnswer(currentInput, correctAnswer, q);
        return;
      }

      // Digit pressed
      if (currentInput.length >= 2) return; // cap at 2 digits
      currentInput += k;
      display.textContent = currentInput;
      display.className   = 'number-display has-value';

      // Auto-submit once the digit count matches the correct answer length
      if (currentInput.length >= correctAnswer.length) {
        submitNumberAnswer(currentInput, correctAnswer, q);
      }
    });

    keypad.appendChild(btn);
  });
}

function submitNumberAnswer(input, correct, q) {
  if (state.answered) return;
  state.answered = true;
  state.attempts++;

  const display    = document.getElementById('numberDisplay');
  const keypadBtns = document.querySelectorAll('.num-btn');
  keypadBtns.forEach(b => b.disabled = true);

  const isCorrect    = input === correct;
  const responseTime = Date.now() - state.questionStartTime;
  const correctOpt   = q.options.find(o => o.correct);

  const result = {
    session_id:       state.sessionId,
    child_age:        state.childAge,
    child_id:         state.childId,
    quiz_mode:        state.mode,
    question_id:      q.id,
    question_text:    q.speak,
    category:         q.category,
    correct_label:    correct,
    selected_label:   input,
    is_correct:       isCorrect,
    attempts:         state.attempts,
    response_time_ms: responseTime,
  };

  // Free choice — record and advance regardless
  if (state.mode === 'free') {
    display.className = 'number-display correct-val';
    state.results.push(result);
    submitResult(result);
    setTimeout(() => {
      state.current++;
      if (state.current < state.questions.length) loadQuestion();
      else showComplete();
    }, 650);
    return;
  }

  // Correct mode
  if (isCorrect) {
    display.className = 'number-display correct-val';
    state.score++;
    state.results.push(result);
    submitResult(result);
    showFeedback('✅ Great job!', 'correct-msg');
    launchConfetti(display);
    speakText(['Wonderful!','Amazing!','Super!','You got it!'][Math.floor(Math.random()*4)]);
    setTimeout(() => {
      hideFeedback();
      state.current++;
      if (state.current < state.questions.length) loadQuestion();
      else showComplete();
    }, 1400);
  } else {
    display.className = 'number-display wrong-val';
    speakText('Try again!');
    showFeedback('🤔 Try again!', 'wrong-msg');
    setTimeout(() => {
      hideFeedback();
      // Reset for retry
      state.answered = false;
      display.textContent = '?';
      display.className   = 'number-display';
      keypadBtns.forEach(b => b.disabled = false);
    }, 900);
  }
}
</script>
</body>
</html>
