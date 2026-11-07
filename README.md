<img width="1200" height="720" alt="learning_fun_cover_image" src="https://github.com/user-attachments/assets/936167e7-f3df-41b8-876f-c9baa4cbf6ad" />

# 🌟 Learning Fun! — Child Quiz Data Collection System

## What it does
An interactive touch-screen quiz for children aged 4–6, built for early-childhood research and classroom assessment.
Questions are spoken aloud; children answer by tapping large emoji or picture cards, or by counting on a number keypad.
Every tap is recorded, so researchers and teachers can see not only *whether* a child answered correctly, but *how*: speed, hesitation, replaying the question, and choice patterns.

Works on phones, tablets and desktops, and **offline on registered classroom tablets**.

> Version 1.0 is a rebuild on Laravel. The original PHP version is kept in [`legacy/`](legacy/) for reference.

---

## Who uses what

| Role | Can |
|---|---|
| **Admin** | Everything: users, all studies, all data |
| **Researcher** | Create and run studies, manage the question bank, see analytics including question quality and anonymous age-group comparisons |
| **Teacher** | Run quiz sessions and add children in the studies they are assigned to; see analytics and child reports (child compared with themselves) |
| **Parent** | Opens a private, expiring link a teacher shares — a simple report of their own child only (no account) |

---

## Running a session

1. Sign in and press **▶ Start a quiz session** (or open `/play`).
2. Pick the study, the child's age, the child (or *Anonymous*) and the mode:
   - **Find the Answer** — wrong taps shake and the child tries again; records how many taps it took.
   - **Free Choice** — any tap is recorded and the quiz moves on, with no right/wrong feedback.
3. Tap **Let's Play!** and hand the device to the child. The small ✕ lets an adult end the session early.

The status line under the setup card shows whether all answers are saved.

### Offline tablets
Open the quiz on the tablet once while online and tap **Set up for offline use**. The tablet downloads the quiz, all its studies' questions, pictures and sounds. From then on it works without internet; answers are kept on the tablet and sync automatically when it is back online — even if the teacher's sign-in has expired. Manage or revoke tablets under **Devices**.

> Offline mode needs HTTPS (it works on Railway, or on `localhost` during development).

---

## What the analytics show

- **Right first try** — in *Find the Answer* children always reach the right card eventually, so the meaningful score is being right on the *first* tap.
- **Time to first tap** and **reaction time after the question ends** — medians, because a few long pauses would distort an average.
- Breakdowns by **category**, **age** and **question position** (tiredness towards the end).
- **Behaviour patterns**, only reported with enough evidence: position bias, repeating choices, same wrong answer after feedback, fast tapping, common mix-ups (e.g. b/d), off-by-one counting slips, whether replaying the question helps, answering before the question ends, recorded voice vs computer voice, photos vs emoji, tiring or warming up, long pauses, favourite picks in Free Choice.
- **Child report** — strengths and areas to practise compared with the child's own average, progress over sessions, printable.
- **Session timeline** — every tap of a session in order with timing and screen position.
- **Question quality** (researchers) — difficulty, discrimination, and which wrong answers attract children.

All insights are presented as *indicators from a short game, not a diagnosis.*

---

## Local development

Requirements: PHP 8.3+ (extensions: pdo_sqlite or pdo_mysql, intl, mbstring, fileinfo, gd, zip), Composer 2, Node 22.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed      # roles, admin from ADMIN_* in .env, default study with 20 questions
php artisan storage:link
npm install && npm run build    # or `composer run dev` while developing
php artisan serve
```

Sign in at <http://localhost:8000/login> with `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` (default `admin@example.com` / `change-me-now` — change it).

Run the tests:

```bash
php artisan test
```

---

## Deploying to Railway

The repository includes a `Dockerfile` (PHP 8.3 + FrankenPHP) and `railway.json` (health check on `/up`).

1. Create a Railway project with this repository and add the **MySQL** plugin.
2. Add a **volume** to the app service mounted at `/app/storage/app/public` (uploaded pictures and audio).
3. Set the service variables:

   | Variable | Value |
   |---|---|
   | `APP_KEY` | output of `php artisan key:generate --show` |
   | `APP_URL` | `https://<your-app>.up.railway.app` |
   | `APP_NAME` | `Learning Fun!` |
   | `DB_CONNECTION` | `mysql` |
   | `DB_URL` | `${{MySQL.MYSQL_URL}}` |
   | `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME` | first admin account |
   | `SEED_ON_DEPLOY` | `true` for the first deploy (creates roles, admin and default study) |

4. Deploy. Migrations run automatically on every start (`RUN_MIGRATIONS=false` to disable).
5. Sign in, **change the admin password**, then set `SEED_ON_DEPLOY=false` (re-seeding is harmless — it never overwrites edited questions — but no longer needed).

---

## Importing data from the original app

```bash
# Questions + answers from the old files
php artisan quiz:import-legacy \
    --questions=legacy/data/questions.json \
    --jsonl=/path/to/old/data/responses.jsonl

# …or answers straight from the old app's Railway database
LEGACY_DB_URL="mysql://user:pass@host:3306/railway" php artisan quiz:import-legacy --from-db
```

Options: `--study=<slug>` (default `legacy-import`, created as an inactive study), `--participants` (turn old child IDs into pseudonymous participants; names are stored encrypted), `--dry-run` (show counts, save nothing).
Re-running is safe — existing sessions and answers are skipped. Text the old app HTML-escaped is decoded.

---

## Privacy & security
- Children are identified by pseudonymous codes; optional names are encrypted at rest and kept out of the audit log.
- Passwords are hashed; sign-in is rate-limited; every change to studies, questions and participants is audited.
- Quiz answers can only be submitted by signed-in staff or registered devices.
- Parent links store only a hash of the token, expire, and can be revoked.
- Set a data-retention period per study for ethics compliance.

---

## Citation
If you use this software in research, please cite it using [`CITATION.cff`](CITATION.cff).

## License
[MIT](LICENSE)
