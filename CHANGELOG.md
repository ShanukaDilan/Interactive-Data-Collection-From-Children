# Changelog

All notable changes to **Learning Fun!** are documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Versioning follows [Semantic Versioning](https://semver.org/).

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
