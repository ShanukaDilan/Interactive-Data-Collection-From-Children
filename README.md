<img width="260" height="150" alt="learning_fun_cover_image" src="https://github.com/user-attachments/assets/936167e7-f3df-41b8-876f-c9baa4cbf6ad" />

# 🌟 Learning Fun! — Child Quiz Data Collection System

## What It Does
Interactive touch-screen quiz for children aged 4–6.
Questions are spoken aloud; children answer by tapping large emoji or image cards.
All responses are saved and downloadable as a CSV for analysis.
Fully responsive — works on phones, tablets, and desktops.

---

## Files Overview

| File | Purpose |
|---|---|
| `index.php` | **Child-facing quiz** — touch-friendly, audio questions, animated answers |
| `submit.php` | **API endpoint** — receives and stores each answer as JSON |
| `admin.php` | **Admin dashboard** — view stats, manage questions, download CSV |
| `questions_api.php` | **Questions CRUD API** — save / delete questions with media uploads |
| `data/responses.jsonl` | **Response store** — auto-created on first submission |
| `data/questions.json` | **Question store** — created when first custom question is saved |
| `uploads/` | **Media store** — question and option images / audio files |

---

## Requirements
- PHP 7.4+ (with `json`, `session`, `fileinfo` extensions — standard)
- A web server (Apache / Nginx / XAMPP / MAMP)
- Modern browser with Web Speech API (Chrome recommended for best TTS)
- Works on any screen size — phones, tablets, and desktops

---

## Setup (3 Steps)

### 1. Upload to your server
Place all files inside a folder on your web server. Example:
```
/var/www/html/child-quiz/
```

### 2. Set permissions
The `data/` and `uploads/` directories need write permissions:
```bash
mkdir -p data uploads
chmod 755 data uploads
```

### 3. Change the admin password
Open `admin.php` and change line 7:
```php
define('ADMIN_PASSWORD', 'admin123');   // ← change this!
```

---

## Usage

### Running a Session (Teacher/Parent)
1. Open `index.php` on any device (phone, tablet, desktop)
2. Select the child's age (4, 5, or 6)
3. Optionally enter a child ID or name
4. Choose a quiz mode:
   - **Find the Answer** — child must tap the correct option; wrong taps shake and retry
   - **Free Choice** — any tap is recorded without right/wrong feedback (for open-ended research)
5. Tap **"Let's Play!"** and hand the device to the child

### During the Quiz (Child)
- The question is **spoken aloud** automatically
- The child taps the **correct picture**
- Correct answer → 🎉 celebration + confetti
- Wrong answer → gentle shake, try again
- 10 questions total, randomly ordered

### Viewing Data (Admin)
1. Open `admin.php`
2. Login with the password you set
3. View:
   - Summary stats (sessions, accuracy, avg response time)
   - Breakdown by **category** and **age**
   - Full response table with filters (category, age)
4. Click **"Download All Data as CSV"** to export

### Managing Questions (Admin)
1. Open `admin.php` → **Questions** tab
2. Click **+ New** to create a question, or click any question to edit it
3. Fill in:
   - **Category** — choose from 24 built-in categories or type a custom one
   - **Audio / TTS text** — what gets spoken aloud (e.g. "Touch the cat!")
   - **Display hint** — text shown on the question card (e.g. "Cat")
   - **Optional media** — upload an image or audio file for the question
4. Set up 4 answer options — each with a label, emoji, background colour, and optional image
5. Mark the correct answer with the radio button
6. Click **Save Question** — custom questions replace the built-in set immediately

---

## Quiz Modes

| Mode | Child Sees | Recorded |
|---|---|---|
| **Find the Answer** | ✅/❌ feedback, must get it right to advance | First correct tap + attempt count |
| **Free Choice** | No feedback, advances on any tap | Whatever the child tapped |

---

## CSV Columns Explained

| Column | Description |
|---|---|
| Session ID | Unique ID per quiz run |
| Child ID | Optional name/ID entered at setup |
| Child Age | 4, 5, or 6 |
| Quiz Mode | `correct` (Find the Answer) or `free` (Free Choice) |
| Question ID | e.g. `q01`, `q05` |
| Question Text | The spoken prompt |
| Category | e.g. Animals, Colors, Shapes |
| Correct Answer | The right answer label |
| Selected Answer | What the child tapped |
| Is Correct | Yes / No |
| Attempts | Taps before the correct answer (Find the Answer mode) |
| Response Time (ms) | Milliseconds from question load to final tap |
| Timestamp | Server time of submission |

---

## Question Categories (24 Built-in)

| Group | Categories |
|---|---|
| **Living Things** | Animals, Birds, Sea Creatures, Insects |
| **Concepts** | Colors, Shapes, Numbers, Letters, Opposites |
| **Food & Drink** | Food, Fruits, Vegetables, Drinks |
| **World** | Vehicles, Nature, Weather |
| **People** | Body Parts, Clothes, Family, Feelings |
| **Activities** | Sports, Musical Instruments |
| **Environment** | Household, School |

> Custom categories can also be typed freely in the question editor.
> Questions are shuffled each session so answer positions vary.

---

## Responsive Design

| Device | Layout |
|---|---|
| Phone (portrait) | Compact quiz cards, safe-area insets for notched screens |
| Phone (landscape) | Options switch to row layout to fit the short viewport |
| Tablet | Standard layout with comfortable touch targets |
| Desktop | Full layout |

---

## Customisation Tips

### Adding Questions via the Admin Panel
Use the **Questions** tab in `admin.php` — no code editing needed.
Upload images or audio files, choose a category, and save.

### Adding Questions via Code
Add entries to the built-in fallback array in `index.php`:
```javascript
{
  id:'q11', category:'Animals', speak:'Touch the lion!', hint:'Lion',
  options:[
    {emoji:'🦁', label:'Lion',   correct:true,  bg:'#FFF8E0'},
    {emoji:'🐯', label:'Tiger',  correct:false, bg:'#FFF4E0'},
    {emoji:'🦊', label:'Fox',    correct:false, bg:'#FFF2EC'},
    {emoji:'🐻', label:'Bear',   correct:false, bg:'#FFF4EC'},
  ]
},
```
> Note: questions saved via the admin panel (in `data/questions.json`) take priority over the built-in fallback.

### Changing the Language
Find `utt.lang = 'en-US'` in `index.php` and update the language code (e.g. `si-LK` for Sinhala, `ta-IN` for Tamil).
Also update the `speak` text on each question to match.

### Protecting the Data Folder
Add this to an `.htaccess` file inside the `data/` and `uploads/` folders:
```
Deny from all
```

---

## Security Notes
- Change the admin password from the default `admin123`
- The `data/` and `uploads/` directories should not be publicly accessible (see above)
- No database required — data is stored in plain JSONL / JSON files
- Input is sanitised with `htmlspecialchars` before output
- The questions API (`questions_api.php`) requires an active admin session

---

## Browser Compatibility

| Browser | Audio | Recommended |
|---|---|---|
| Chrome / Edge | ✅ Full Web Speech API | ✅ Best |
| Firefox | ✅ Works | ✅ Good |
| Safari (iOS) | ✅ Works (tap needed first) | ✅ Good |
| Samsung Internet | ✅ Works | ✅ Good |

> **iOS note:** Audio requires at least one user interaction before it plays. The "Let's Play!" button satisfies this requirement.
