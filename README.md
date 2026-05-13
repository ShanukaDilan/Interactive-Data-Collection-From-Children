# 🌟 Learning Fun! — Child Quiz Data Collection System

## What It Does
Interactive touch-screen quiz for children aged 4–6.
Questions are spoken aloud; children answer by tapping large emoji cards.
All responses are saved and downloadable as a CSV for analysis.

---

## Files Overview

| File | Purpose |
|---|---|
| `index.php` | **Child-facing quiz** — touch-friendly, audio questions, animated answers |
| `submit.php` | **API endpoint** — receives and stores each answer as JSON |
| `admin.php` | **Admin dashboard** — view stats, filter, download CSV |
| `data/responses.jsonl` | **Data store** — auto-created on first submission |

---

## Requirements
- PHP 7.4+ (with `json`, `session` extensions — standard)
- A web server (Apache / Nginx / XAMPP / MAMP)
- Modern browser with Web Speech API (Chrome recommended for best TTS)
- Tablet in landscape mode for best experience

---

## Setup (3 Steps)

### 1. Upload to your server
Place all files inside a folder on your web server. Example:
```
/var/www/html/child-quiz/
```

### 2. Set permissions
The `data/` directory needs write permissions:
```bash
mkdir data
chmod 755 data
```

### 3. Change the admin password
Open `admin.php` and change line 8:
```php
define('ADMIN_PASSWORD', 'admin123');   // ← change this!
```

---

## Usage

### Running a Session (Teacher/Parent)
1. Open `index.php` on the tablet
2. Select the child's age (4, 5, or 6)
3. Optionally enter a child ID or name
4. Tap **"Let's Play!"** and hand the tablet to the child

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
   - Summary stats (sessions, accuracy, response times)
   - Breakdown by **category** and **age**
   - Full response table with filters
4. Click **"Download All Data as CSV"** to export

---

## CSV Columns Explained

| Column | Description |
|---|---|
| Session ID | Unique ID per quiz run |
| Child ID | Optional name/ID entered at setup |
| Child Age | 4, 5, or 6 |
| Question ID | e.g. `q01`, `q05` |
| Question Text | The spoken prompt |
| Category | Animals / Colors / Shapes / Food / Numbers |
| Correct Answer | The right answer label |
| Selected Answer | What the child tapped |
| Is Correct | Yes / No |
| Attempts | How many taps before getting it right |
| Response Time (ms) | Milliseconds from question load to correct answer |
| Timestamp | Server time of submission |

---

## Question Categories (10 Questions)
- 🐾 **Animals** — Cat, Dog, Elephant, Duck
- 🎨 **Colors** — Red, Blue
- 🔷 **Shapes** — Circle, Star
- 🍎 **Food** — Apple, Banana
- 🔢 **Numbers** — Two

> Questions are shuffled each session so answer positions vary.

---

## Customisation Tips

### Adding More Questions
Open `index.php` and add entries to the `QUESTIONS` array in the `<script>` section:
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

### Changing the Language
Find all `utt.lang = 'en-US'` lines in `index.php` and update the language code.
You can also change the `speak` text to any language.

### Protecting the Data Folder
Add this to your Apache `.htaccess` or Nginx config:
```
# .htaccess inside /data/
Deny from all
```

---

## Security Notes
- The `data/` folder should not be publicly accessible (see above)
- Change the admin password from the default `admin123`
- No database required — data is stored in a plain JSONL file
- Input is sanitised before writing

---

## Browser Compatibility

| Browser | Audio | Recommended |
|---|---|---|
| Chrome / Edge | ✅ Full Web Speech API | ✅ Best |
| Firefox | ✅ Works | ✅ Good |
| Safari (iOS) | ✅ Works (tap needed first) | ✅ Good |
| Samsung Internet | ✅ Works | ✅ Good |

> **Tip:** On iOS Safari, audio requires at least one user interaction before it plays automatically. The "Let's Play!" button counts as this interaction.
