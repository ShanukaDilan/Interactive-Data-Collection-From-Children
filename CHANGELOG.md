# Changelog

All notable changes to **Learning Fun!** are documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.0.0] — Unreleased

Complete rebuild on Laravel 13. The original PHP app is kept in `legacy/` for reference until parity is confirmed.

### Added
- **Accounts and roles**: hashed-password sign-in with lockout; admin, researcher and teacher roles; audit log of changes
- **Custom admin dashboard** (Blade + Livewire): studies, question bank, participants, users, devices
- **Studies**: own question set and order, speech language (English, Sinhala, Tamil), default mode, age range, questions per session, shuffling, data-retention period, team members
- **Question editor**: tap-the-answer and number-keypad questions, 2–6 answer cards, image/audio uploads, live child-view preview, versioning (answers keep the text they were given with), archive instead of delete once answered
- **Participants**: pseudonymous codes, optional encrypted names, parental consent tracking
- **Behaviour capture**: every tap with screen position and timing, question replays, time the spoken question ended, card order
- **Analytics**: first-try accuracy, median response/reaction times, breakdowns by category, age and question position; pattern detection (position bias, repeated choices, fast tapping, mix-ups, counting slips, replay effect, answering early, recorded vs computer voice, photos vs emoji, tiring/warming up, long pauses, free-choice favourites); question quality for researchers
- **Child report** (strengths vs own average; anonymous same-age average for researchers), session timeline, printable
- **Parent share links**: private, expiring, revocable; only a hash is stored
- **Offline classroom devices**: installable web app; registered tablets run quizzes with no connection and sync later with their own token
- **Data exports** (researchers and admins), using the analytics filters:
  - an Excel workbook (answers, taps, codebook, filter notes)
  - answers CSV (one row per answer), taps CSV (one row per tap) and a codebook CSV
  - the original app's 13-column CSV
  - names are never exported, spreadsheet formulas are neutralised, and every export is audited
- **Import** of questions and answers from the original app (`php artisan quiz:import-legacy`)
- **Railway deployment** (Dockerfile, FrankenPHP, health check)

### Changed
- Answers are stored relationally (sessions → responses → taps) instead of JSONL/flat tables
- Text is stored as entered and escaped on output (fixes double-encoded `&amp;` in the old CSV)
- Answer submission requires sign-in or a registered device (previously open to anyone)

---

## [0.1.0] — 2026-04-28

### Added
- Touch-screen quiz interface for children aged 4–6
- Text-to-speech question delivery via Web Speech API
- Optional uploaded audio per question (MP3 / WAV / OGG)
- Two quiz modes: **Find the Answer** (correctness-enforced) and **Free Choice** (open-ended recording)
- 10 built-in fallback questions across Animals, Colors, Shapes, Food, and Numbers
- Custom question editor with 24 built-in categories and free-text custom categories
- Image and audio upload support for question cards and answer options
- Per-response JSONL data store (`data/responses.jsonl`) with 13 fields
- CSV export via admin dashboard
- Admin dashboard with accuracy stats by category and age
- Paginated, filterable response table
- Session ID, child ID, child age, attempt count, and response time (ms) per response
- Fully responsive layout (phones, tablets, desktops, landscape mode)
- Safe-area inset support for notched/foldable screens
- Animated bubble background and confetti on correct answers
- MIT License
