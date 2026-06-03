<?php
/**
 * admin.php — Password-protected dashboard: view responses, download CSV,
 *             and manage quiz questions (create / edit / delete with media uploads).
 */

define('ADMIN_PASSWORD', 'admin123');   // ← CHANGE THIS
define('DATA_FILE',      __DIR__ . '/data/responses.jsonl');
define('QUESTIONS_FILE', __DIR__ . '/data/questions.json');

require_once __DIR__ . '/db.php';

session_start();

// ── Handle logout ────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ── Handle login ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
    } else {
        $loginError = 'Incorrect password.';
    }
}

$loggedIn = !empty($_SESSION['admin']);

// ── CSV download (must happen before HTML output) ────────────────
if ($loggedIn && isset($_GET['download']) && $_GET['download'] === 'csv') {
    $rows = loadData();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="responses_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, [
        'Session ID','Child ID','Child Age','Quiz Mode',
        'Question ID','Question Text','Category',
        'Correct Answer','Selected Answer',
        'Is Correct','Attempts','Response Time (ms)','Timestamp'
    ]);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['session_id']    ?? '',
            $r['child_id']      ?? '',
            $r['child_age']     ?? '',
            $r['quiz_mode']     ?? 'correct',
            $r['question_id']   ?? '',
            $r['question_text'] ?? '',
            $r['category']      ?? '',
            $r['correct_label'] ?? '',
            $r['selected_label']?? '',
            isset($r['is_correct']) ? ($r['is_correct'] ? 'Yes' : 'No') : '',
            $r['attempts']         ?? '',
            $r['response_time_ms'] ?? '',
            $r['timestamp']     ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ── Delete actions (before HTML output) ─────────────────────────
if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'] ?? '';

    if ($act === 'delete_record') {
        // AJAX: delete one row, return JSON
        header('Content-Type: application/json');
        $id  = (int)($_POST['record_id'] ?? 0);
        try {
            $pdo = getDb();
            if ($pdo && $id > 0) {
                $pdo->prepare("DELETE FROM responses WHERE id = ?")->execute([$id]);
            }
        } catch (Throwable $e) { error_log('delete_record: ' . $e->getMessage()); }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($act === 'reset_all') {
        // Form POST: delete everything, redirect
        try {
            $pdo = getDb();
            if ($pdo) $pdo->exec("DELETE FROM responses");
        } catch (Throwable $e) { error_log('reset_all: ' . $e->getMessage()); }
        if (file_exists(DATA_FILE)) file_put_contents(DATA_FILE, '');
        header('Location: admin.php?view=dashboard');
        exit;
    }
}

// ── Load response records (DB first, file fallback) ───────────────
function loadData(): array {
    $pdo = getDb();

    if ($pdo) {
        try {
            $stmt = $pdo->query(
                "SELECT * FROM responses ORDER BY created_at DESC"
            );
            $rows = [];
            foreach ($stmt->fetchAll() as $r) {
                // Normalise column names to match the rest of the code
                $r['timestamp']  = $r['created_at'] ?? '';
                $r['is_correct'] = (int)($r['is_correct'] ?? 0);
                $r['child_age']  = (int)($r['child_age']  ?? 0);
                $r['attempts']   = (int)($r['attempts']   ?? 1);
                $r['response_time_ms'] = (int)($r['response_time_ms'] ?? 0);
                $r['quiz_mode']  = $r['quiz_mode'] ?? 'correct';
                $rows[] = $r;
            }
            return $rows;
        } catch (Throwable $e) {
            error_log('DB loadData: ' . $e->getMessage());
            // fall through to file fallback
        }
    }

    // File fallback
    if (!file_exists(DATA_FILE)) return [];
    $lines = file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $rows  = [];
    foreach ($lines as $line) {
        $r = json_decode($line, true);
        if ($r) $rows[] = $r;
    }
    return array_reverse($rows); // newest first, consistent with DB order
}

// ── Load questions ────────────────────────────────────────────────
function loadQuestions(): array {
    if (!file_exists(QUESTIONS_FILE)) return [];
    $data = json_decode(file_get_contents(QUESTIONS_FILE), true);
    return is_array($data) ? $data : [];
}

// ── Compute stats ─────────────────────────────────────────────────
function computeStats(array $rows): array {
    if (!$rows) return [];

    $sessions = array_unique(array_column($rows, 'session_id'));
    $total    = count($rows);
    $correct  = array_sum(array_column($rows, 'is_correct'));
    $rtimes   = array_filter(array_column($rows, 'response_time_ms'));
    $avgTime  = $rtimes ? round(array_sum($rtimes) / count($rtimes)) : 0;

    $cats = [];
    foreach ($rows as $r) {
        $c = $r['category'] ?? 'Unknown';
        $cats[$c]['total']   = ($cats[$c]['total']   ?? 0) + 1;
        $cats[$c]['correct'] = ($cats[$c]['correct'] ?? 0) + $r['is_correct'];
    }

    $ages = [];
    foreach ($rows as $r) {
        $a = $r['child_age'] ?? 0;
        $ages[$a]['total']   = ($ages[$a]['total']   ?? 0) + 1;
        $ages[$a]['correct'] = ($ages[$a]['correct'] ?? 0) + $r['is_correct'];
    }
    ksort($ages);

    return compact('sessions','total','correct','avgTime','cats','ages');
}

$rows  = $loggedIn ? loadData() : [];
$stats = $loggedIn ? computeStats($rows) : [];

// ── Pagination ───────────────────────────────────────────────────
$perPage = 30;
$page    = max(1, intval($_GET['page'] ?? 1));
$total_r = count($rows);
$pages   = max(1, ceil($total_r / $perPage));
$paged   = array_slice(array_reverse($rows), ($page-1)*$perPage, $perPage);

// ── Filters ──────────────────────────────────────────────────────
$filterCat = $_GET['cat'] ?? '';
$filterAge = $_GET['age'] ?? '';
if ($filterCat || $filterAge) {
    $filtered = array_filter($rows, function($r) use ($filterCat, $filterAge) {
        $mc = !$filterCat || ($r['category'] ?? '') === $filterCat;
        $ma = !$filterAge || ($r['child_age'] ?? '') == $filterAge;
        return $mc && $ma;
    });
    $filtered  = array_values($filtered);
    $total_r   = count($filtered);
    $pages     = max(1, ceil($total_r / $perPage));
    $paged     = array_slice(array_reverse($filtered), ($page-1)*$perPage, $perPage);
}

// ── Current view ─────────────────────────────────────────────────
$view   = $_GET['view'] ?? 'dashboard';
$qsData = $loggedIn ? loadQuestions() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin — Learning Fun</title>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --coral:#FF6B6B; --sky:#4ECDC4; --sun:#FFE66D;
      --mint:#A8E6CF; --lav:#C9B1FF; --peach:#FFB347;
      --bg:#F4F7FA; --card:#fff; --border:#E8ECF0;
      --text:#2D3748; --muted:#718096;
    }
    *, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
    body { font-family:'Nunito',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }

    /* ── NAV ─────────────────────────────────────── */
    nav {
      background: white;
      border-bottom: 2px solid var(--border);
      padding: 0 24px;
      display: flex; align-items: center; gap: 8px; height: 64px;
      position: sticky; top:0; z-index:50;
      box-shadow: 0 2px 12px rgba(0,0,0,.06);
    }
    .nav-logo { font-family:'Fredoka One',cursive; font-size:1.4rem; color:var(--coral); margin-right:8px; }
    .nav-tab {
      padding: 8px 18px; border-radius: 10px;
      font-family:'Nunito',sans-serif; font-weight:800; font-size:.9rem;
      text-decoration: none; color: var(--muted);
      border: 2px solid transparent;
      transition: all .15s;
    }
    .nav-tab:hover { color: var(--text); background: var(--bg); }
    .nav-tab.active { color: var(--coral); border-color: var(--coral); background: #fff5f5; }
    .nav-spacer { flex:1; }
    .nav-btn {
      background: var(--sky); color:white;
      border:none; border-radius:10px;
      padding:8px 18px;
      font-family:'Nunito',sans-serif; font-weight:800; font-size:.9rem;
      cursor:pointer; text-decoration:none;
      transition:opacity .15s;
    }
    .nav-btn:hover { opacity:.85; }
    .nav-btn.danger { background:var(--coral); }

    /* ── MAIN ────────────────────────────────────── */
    main { max-width: 1400px; margin: 0 auto; padding: 32px 24px; }

    /* ── LOGIN ───────────────────────────────────── */
    .login-wrap {
      max-width: 400px; margin: 100px auto;
      background:white; border-radius:24px;
      padding:44px; box-shadow:0 12px 40px rgba(0,0,0,.1);
      text-align:center;
    }
    .login-wrap h1 { font-family:'Fredoka One',cursive; color:var(--coral); font-size:2rem; margin-bottom:6px; }
    .login-wrap p  { color:var(--muted); margin-bottom:28px; }
    .form-group { margin-bottom:16px; text-align:left; }
    .form-group label { font-weight:800; font-size:.88rem; color:var(--muted); display:block; margin-bottom:6px; }
    .form-group input {
      width:100%; padding:12px 16px;
      border:2px solid var(--border); border-radius:12px;
      font-family:'Nunito',sans-serif; font-size:1rem;
      outline:none; transition:border-color .2s;
    }
    .form-group input:focus { border-color:var(--sky); }
    .form-submit {
      width:100%; padding:14px; background:var(--coral); color:white;
      border:none; border-radius:12px;
      font-family:'Fredoka One',cursive; font-size:1.2rem;
      cursor:pointer; transition:opacity .15s;
    }
    .form-submit:hover { opacity:.88; }
    .error-msg { background:#fff5f5; color:var(--coral); border:1.5px solid #fdd; border-radius:10px; padding:10px; margin-bottom:14px; font-weight:700; }

    /* ── STAT CARDS ──────────────────────────────── */
    .stat-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:20px; margin-bottom:32px; }
    .stat-card {
      background:white; border-radius:20px; padding:24px 20px;
      box-shadow:0 4px 16px rgba(0,0,0,.06);
      border-left:5px solid var(--coral);
    }
    .stat-card:nth-child(2) { border-color:var(--sky); }
    .stat-card:nth-child(3) { border-color:var(--sun); }
    .stat-card:nth-child(4) { border-color:var(--mint); }
    .stat-value { font-family:'Fredoka One',cursive; font-size:2.2rem; color:var(--text); }
    .stat-label { color:var(--muted); font-size:.85rem; font-weight:700; margin-top:4px; }

    /* ── SECTION TITLES ──────────────────────────── */
    .section-title { font-family:'Fredoka One',cursive; font-size:1.4rem; color:var(--text); margin-bottom:16px; }

    /* ── TABLES ──────────────────────────────────── */
    .table-wrap { background:white; border-radius:20px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.06); margin-bottom:32px; }
    table { width:100%; border-collapse:collapse; }
    thead tr { background:var(--bg); }
    th { padding:13px 18px; text-align:left; font-size:.82rem; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; }
    td { padding:12px 18px; border-top:1px solid var(--border); font-size:.93rem; }
    tr:hover td { background:#fafafa; }
    .badge { display:inline-block; padding:3px 10px; border-radius:99px; font-weight:800; font-size:.78rem; }
    .badge-yes  { background:#d4edda; color:#155724; }
    .badge-no   { background:#f8d7da; color:#721c24; }
    .badge-cat  { background:var(--sun); color:#7a5a00; }
    .bar-wrap { background:#eee; border-radius:99px; height:8px; width:100px; display:inline-block; vertical-align:middle; overflow:hidden; }
    .bar-fill { height:100%; border-radius:99px; background:linear-gradient(90deg,var(--sky),var(--mint)); }

    /* ── FILTERS ─────────────────────────────────── */
    .filter-bar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:18px; }
    .filter-bar select, .filter-bar input {
      padding:8px 14px; border:2px solid var(--border); border-radius:10px;
      font-family:'Nunito',sans-serif; font-weight:700; font-size:.9rem;
      outline:none; cursor:pointer;
    }
    .filter-bar select:focus { border-color:var(--sky); }
    .filter-apply { padding:8px 20px; background:var(--sky); color:white; border:none; border-radius:10px; font-family:'Nunito',sans-serif; font-weight:800; font-size:.9rem; cursor:pointer; }
    .filter-clear { text-decoration:none; color:var(--muted); font-weight:700; font-size:.9rem; }

    /* ── PAGINATION ──────────────────────────────── */
    .pagination { display:flex; gap:8px; justify-content:center; margin-top:20px; flex-wrap:wrap; }
    .page-btn {
      padding:7px 14px; border-radius:10px;
      border:2px solid var(--border); background:white;
      font-family:'Nunito',sans-serif; font-weight:800; font-size:.88rem;
      cursor:pointer; text-decoration:none; color:var(--text);
      transition:all .15s;
    }
    .page-btn.active { background:var(--coral); color:white; border-color:var(--coral); }
    .page-btn:hover:not(.active) { border-color:var(--sky); color:var(--sky); }

    /* ── DOWNLOAD BTN ────────────────────────────── */
    .download-btn {
      display:inline-flex; align-items:center; gap:8px;
      background:linear-gradient(135deg,#27ae60,#2ecc71);
      color:white; text-decoration:none;
      padding:11px 24px; border-radius:12px;
      font-family:'Fredoka One',cursive; font-size:1.05rem;
      box-shadow:0 6px 20px rgba(46,204,113,.3);
      transition:transform .15s, box-shadow .15s;
      margin-bottom:24px;
    }
    .download-btn:hover { transform:translateY(-2px); box-shadow:0 8px 26px rgba(46,204,113,.4); }

    /* ── RESET / ROW-DELETE ─────────────────────────────────────── */
    .reset-btn {
      display:inline-flex; align-items:center; gap:8px;
      background:linear-gradient(135deg,#e74c3c,#c0392b);
      color:white; text-decoration:none;
      padding:11px 24px; border-radius:12px;
      font-family:'Fredoka One',cursive; font-size:1.05rem;
      box-shadow:0 6px 20px rgba(231,76,60,.3);
      transition:transform .15s,box-shadow .15s;
      margin-bottom:24px; margin-left:10px;
      border:none; cursor:pointer;
    }
    .reset-btn:hover { transform:translateY(-2px); box-shadow:0 8px 26px rgba(231,76,60,.4); }
    .btn-row-del {
      background:#fff5f5; color:var(--coral);
      border:1.5px solid #fdd; border-radius:7px;
      padding:4px 10px; font-size:.78rem; font-weight:800;
      cursor:pointer; transition:opacity .12s; white-space:nowrap;
    }
    .btn-row-del:hover { opacity:.75; }
    .btn-row-del:disabled { opacity:.4; cursor:not-allowed; }

    .empty-state { text-align:center; padding:60px 20px; color:var(--muted); }
    .empty-state .empty-icon { font-size:3.5rem; margin-bottom:12px; }
    .empty-state p { font-weight:700; font-size:1.1rem; }

    /* ════════════════════════════════════════════
       QUESTION MANAGER
    ═════════════════════════════════════════════ */
    .q-manager {
      display: grid;
      grid-template-columns: 340px 1fr;
      gap: 24px;
      align-items: start;
    }
    @media (max-width: 900px) {
      .q-manager { grid-template-columns: 1fr; }
    }

    /* Question list panel */
    .q-list-panel {
      background: white;
      border-radius: 20px;
      box-shadow: 0 4px 16px rgba(0,0,0,.06);
      overflow: hidden;
    }
    .q-list-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 18px 20px;
      border-bottom: 2px solid var(--border);
      background: var(--bg);
    }
    .q-list-header h2 { font-family:'Fredoka One',cursive; font-size:1.2rem; color:var(--text); }
    .q-add-btn {
      background: var(--coral); color: white;
      border: none; border-radius: 10px;
      padding: 8px 16px;
      font-family:'Nunito',sans-serif; font-weight:800; font-size:.88rem;
      cursor: pointer; transition: opacity .15s;
    }
    .q-add-btn:hover { opacity:.85; }

    .q-list-body { max-height: 70vh; overflow-y: auto; }
    .q-item {
      display: flex; align-items: center; gap: 12px;
      padding: 14px 16px;
      border-bottom: 1px solid var(--border);
      cursor: pointer;
      transition: background .12s;
    }
    .q-item:last-child { border-bottom: none; }
    .q-item:hover { background: #fafafa; }
    .q-item.editing { background: #fff5f5; border-left: 4px solid var(--coral); }
    .q-item-thumb {
      width: 44px; height: 44px; flex-shrink: 0;
      border-radius: 10px; overflow: hidden;
      background: var(--bg);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem;
    }
    .q-item-thumb img { width:100%; height:100%; object-fit:cover; }
    .q-item-info { flex: 1; min-width: 0; }
    .q-item-hint { font-weight:800; font-size:.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .q-item-meta { font-size:.78rem; color:var(--muted); margin-top:2px; }
    .q-item-actions { display:flex; gap:6px; flex-shrink:0; }
    .btn-edit, .btn-del {
      border: none; border-radius: 8px;
      padding: 5px 10px; font-size:.78rem; font-weight:800;
      cursor: pointer; transition: opacity .12s;
    }
    .btn-edit { background:#EBF8FF; color:#2b6cb0; }
    .btn-edit:hover { opacity:.8; }
    .btn-del  { background:#fff5f5; color:var(--coral); }
    .btn-del:hover  { opacity:.8; }

    /* Question form panel */
    .q-form-panel {
      background: white;
      border-radius: 20px;
      box-shadow: 0 4px 16px rgba(0,0,0,.06);
      padding: 28px;
    }
    .q-form-title {
      font-family:'Fredoka One',cursive; font-size:1.4rem; color:var(--text);
      margin-bottom: 24px;
      display: flex; align-items: center; gap: 10px;
    }
    .q-form-title span { flex:1; }

    .form-section {
      background: var(--bg);
      border-radius: 14px;
      padding: 20px;
      margin-bottom: 20px;
    }
    .form-section h3 {
      font-family:'Fredoka One',cursive; font-size:1.05rem; color:var(--muted);
      margin-bottom: 16px;
    }
    .form-row {
      display: flex; flex-direction: column; gap: 6px;
      margin-bottom: 14px;
    }
    .form-row:last-child { margin-bottom: 0; }
    .form-row label { font-weight:800; font-size:.84rem; color:var(--muted); }
    .form-row input[type=text],
    .form-row input[type=color],
    .form-row select {
      padding: 10px 14px;
      border: 2px solid var(--border); border-radius: 10px;
      font-family:'Nunito',sans-serif; font-weight:700; font-size:.93rem;
      outline: none; transition: border-color .2s; background:white;
    }
    .form-row input[type=text]:focus,
    .form-row select:focus { border-color:var(--sky); }
    .form-row input[type=color] { width:50px; height:36px; padding:2px 4px; cursor:pointer; }

    /* Upload area */
    .upload-group { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .upload-group input[type=file] {
      font-family:'Nunito',sans-serif; font-size:.88rem;
      max-width: 220px;
    }
    .preview-img {
      width: 60px; height: 60px;
      object-fit: contain; border-radius: 8px;
      border: 2px solid var(--border);
      background: white;
    }
    .preview-audio { display:flex; align-items:center; gap:8px; }
    .btn-remove-media {
      background: #fff5f5; color:var(--coral); border:none;
      border-radius:6px; padding:4px 10px;
      font-size:.78rem; font-weight:800; cursor:pointer;
    }
    .btn-remove-media:hover { opacity:.8; }

    /* Options rows */
    .opts-grid { display:flex; flex-direction:column; gap:10px; }
    .opt-row {
      display: grid;
      grid-template-columns: 36px 1fr 60px 60px 40px 80px;
      align-items: center;
      gap: 8px;
      background: white;
      border-radius: 12px;
      padding: 10px 14px;
      border: 2px solid var(--border);
      transition: border-color .15s;
    }
    .opt-row:has(input[type=radio]:checked) { border-color: #2ecc71; background:#f0fff6; }
    .opt-radio { display:flex; align-items:center; justify-content:center; }
    .opt-radio input { width:18px; height:18px; cursor:pointer; accent-color:#2ecc71; }
    .opt-label-input { font-family:'Nunito',sans-serif; padding:7px 10px; border:2px solid var(--border); border-radius:8px; font-weight:700; font-size:.9rem; outline:none; }
    .opt-label-input:focus { border-color:var(--sky); }
    .opt-emoji-input { font-family:'Nunito',sans-serif; padding:7px 8px; border:2px solid var(--border); border-radius:8px; font-size:1rem; outline:none; text-align:center; }
    .opt-emoji-input:focus { border-color:var(--sky); }
    .opt-img-wrap { position:relative; display:flex; align-items:center; gap:6px; }
    .opt-img-wrap label {
      display:flex; align-items:center; justify-content:center;
      width:36px; height:36px; background:var(--bg); border:2px dashed var(--border);
      border-radius:8px; cursor:pointer; font-size:1.2rem;
      transition: border-color .15s;
    }
    .opt-img-wrap label:hover { border-color:var(--sky); }
    .opt-img-wrap input[type=file] { display:none; }
    .opt-img-preview {
      width:36px; height:36px; object-fit:contain;
      border-radius:6px; border:1.5px solid var(--border);
    }
    .opt-correct-label {
      font-size:.72rem; font-weight:800; color:#2ecc71;
      text-align:center; line-height:1.2;
    }

    /* Save / cancel */
    .form-actions { display:flex; gap:12px; margin-top:8px; }
    .btn-save {
      flex:1; padding:14px; background:linear-gradient(135deg,var(--coral),var(--peach));
      color:white; border:none; border-radius:12px;
      font-family:'Fredoka One',cursive; font-size:1.1rem;
      cursor:pointer; transition:opacity .15s;
    }
    .btn-save:hover { opacity:.88; }
    .btn-save:disabled { opacity:.5; cursor:not-allowed; }
    .btn-cancel-form {
      padding:14px 24px; background:var(--bg); color:var(--muted);
      border:2px solid var(--border); border-radius:12px;
      font-family:'Nunito',sans-serif; font-weight:800; font-size:.95rem;
      cursor:pointer;
    }

    /* Toast */
    .toast {
      position:fixed; bottom:28px; left:50%; transform:translateX(-50%) translateY(100px);
      background:#2D3748; color:white;
      border-radius:12px; padding:12px 24px;
      font-weight:700; font-size:.95rem;
      transition:transform .35s cubic-bezier(.34,1.56,.64,1);
      z-index:999; pointer-events:none;
    }
    .toast.show { transform:translateX(-50%) translateY(0); }
    .toast.success { background:#27ae60; }
    .toast.error   { background:var(--coral); }

    /* ══ RESPONSIVE — TOUCH / MOBILE / TABLET ══════════ */

    /* Tables scroll horizontally on narrow screens */
    @media (max-width: 900px) {
      .table-wrap { overflow-x: auto; }
      th, td { white-space: nowrap; }
    }

    /* ── ≤ 640px  (phones) ─────────────────────────── */
    @media (max-width: 640px) {
      /* Nav: 2-row layout — logo+buttons row, then tabs row */
      nav {
        height: auto; flex-wrap: wrap;
        padding: 8px 14px 6px; gap: 6px;
      }
      .nav-logo   { flex: 1; font-size: 1.1rem; order: 1; }
      .nav-spacer { display: none; }
      .nav-btn    { order: 2; padding: 5px 10px; font-size: .8rem; }
      .nav-tab    { order: 3; flex: 1; text-align: center; padding: 7px 8px; font-size: .82rem; }

      main { padding: 16px 12px; }

      .login-wrap { margin: 32px auto; padding: 28px 20px; }

      .stat-grid { grid-template-columns: repeat(2,1fr); gap: 12px; margin-bottom: 20px; }
      .stat-card { padding: 18px 14px; }
      .stat-value { font-size: 1.8rem; }

      .section-title { font-size: 1.2rem; }
      .download-btn  { font-size: .9rem; padding: 10px 18px; }

      /* Prevent iOS auto-zoom on focus (inputs need font-size ≥ 16px) */
      .form-group input,
      .form-row input[type=text],
      .form-row select,
      .opt-label-input,
      .opt-emoji-input,
      .filter-bar select,
      .filter-bar input { font-size: 1rem; }

      .filter-bar { gap: 8px; }
      .filter-bar select { flex: 1; }

      /* Larger touch targets */
      .btn-edit, .btn-del { padding: 7px 12px; font-size: .82rem; }
      .q-add-btn { padding: 9px 18px; }
      .page-btn  { padding: 9px 16px; }

      /* Question form padding */
      .q-form-panel { padding: 18px 16px; }
      .form-section  { padding: 16px 14px; }

      /* Option rows: collapse 6-column grid into 2 rows
         Row 1: [radio] [label] [correct-badge]
         Row 2: [emoji] [color] [img-upload]       */
      .opt-row {
        grid-template-columns: 32px 1fr auto;
        grid-template-rows: auto auto;
        row-gap: 8px; column-gap: 6px;
      }
      .opt-radio         { grid-column: 1; grid-row: 1; }
      .opt-label-input   { grid-column: 2; grid-row: 1; }
      .opt-correct-label { grid-column: 3; grid-row: 1; font-size: .65rem; }
      .opt-emoji-input   { grid-column: 1; grid-row: 2; width: 42px; padding: 6px 4px; }
      .opt-row > input[type="color"] { grid-column: 2; grid-row: 2; justify-self: start; }
      .opt-img-wrap      { grid-column: 3; grid-row: 2; }

      /* Toast: full-width on mobile */
      .toast { width: 92%; left: 4%; transform: translateY(100px); }
      .toast.show { transform: translateY(0); }
    }

    /* ── ≤ 480px  (small phones) ───────────────────── */
    @media (max-width: 480px) {
      .login-wrap { margin: 20px auto; padding: 24px 16px; }

      .stat-grid { gap: 10px; }
      .stat-card { padding: 14px 12px; border-radius: 14px; }
      .stat-value { font-size: 1.5rem; }
      .stat-label { font-size: .78rem; }

      th { padding: 10px 10px; font-size: .76rem; }
      td { padding: 9px 10px;  font-size: .84rem; }

      .q-list-body { max-height: 45vh; }
      .q-form-panel { padding: 14px 12px; }
      .form-section  { padding: 14px 12px; }
    }

    /* ── 641–1024px  (tablets) ─────────────────────── */
    @media (min-width: 641px) and (max-width: 1024px) {
      main { padding: 24px 20px; }
    }

    /* Snappier tap response on all interactive elements */
    button, a, .q-item, .page-btn, .nav-tab, .age-btn, .option-card {
      touch-action: manipulation;
    }
  </style>
</head>
<body>

<nav>
  <span class="nav-logo">🌟 Learning Fun</span>
  <?php if ($loggedIn): ?>
    <a href="admin.php?view=dashboard"
       class="nav-tab <?= ($view==='dashboard')?'active':'' ?>">📊 Dashboard</a>
    <a href="admin.php?view=questions"
       class="nav-tab <?= ($view==='questions')?'active':'' ?>">📝 Questions</a>
  <?php endif; ?>
  <span class="nav-spacer"></span>
  <?php if ($loggedIn):
    $dbOk = (getDb() !== null); ?>
    <span style="font-size:.78rem;font-weight:800;padding:6px 12px;border-radius:8px;
                 background:<?= $dbOk?'#d4edda':'#fff3cd' ?>;
                 color:<?= $dbOk?'#155724':'#856404' ?>;">
      <?= $dbOk ? '🟢 DB Connected' : '🟡 File Storage' ?>
    </span>
    <a href="index.php" target="_blank" class="nav-btn">▶ Open Quiz</a>
    <a href="?logout" class="nav-btn danger">Logout</a>
  <?php endif; ?>
</nav>

<main>

<?php if (!$loggedIn): ?>
<!-- ── LOGIN ──────────────────────────────────── -->
<div class="login-wrap">
  <h1>🔐 Admin Login</h1>
  <p>Enter the admin password to access the dashboard.</p>
  <?php if (!empty($loginError)): ?>
    <div class="error-msg">❌ <?= htmlspecialchars($loginError) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="Enter password…" autofocus>
    </div>
    <button type="submit" class="form-submit">Login →</button>
  </form>
</div>

<?php elseif ($view === 'questions'): ?>
<!-- ═══════════════════════════════════════════════════════════
     QUESTIONS MANAGEMENT VIEW
════════════════════════════════════════════════════════════ -->

<div class="section-title" style="margin-bottom:24px">
  📝 Manage Questions
  <span style="font-family:Nunito;font-size:.9rem;color:var(--muted);font-weight:700;margin-left:8px">
    (<?= count($qsData) ?> questions)
  </span>
</div>

<div class="q-manager">

  <!-- ── Question List ── -->
  <div class="q-list-panel">
    <div class="q-list-header">
      <h2>All Questions</h2>
      <button class="q-add-btn" onclick="newQuestion()">+ New</button>
    </div>
    <div class="q-list-body" id="qListBody">
      <?php if (empty($qsData)): ?>
        <div class="empty-state" style="padding:32px 20px">
          <div class="empty-icon">📭</div>
          <p>No questions yet.<br>Click "+ New" to add one.</p>
        </div>
      <?php else: ?>
        <?php foreach ($qsData as $idx => $q): ?>
          <?php
            $thumb     = $q['image'] ?? null;
            $firstOptImg = null;
            $firstEmoji  = null;
            foreach ($q['options'] ?? [] as $opt) {
                if (!$firstOptImg && !empty($opt['image'])) $firstOptImg = $opt['image'];
                if (!$firstEmoji  && !empty($opt['emoji']))  $firstEmoji  = $opt['emoji'];
            }
            $correctLabel = '';
            foreach ($q['options'] ?? [] as $opt) {
                if (!empty($opt['correct'])) { $correctLabel = $opt['label']; break; }
            }
          ?>
          <div class="q-item" id="qitem-<?= $idx ?>" onclick="editQuestion(<?= $idx ?>)">
            <div class="q-item-thumb">
              <?php if ($thumb): ?>
                <img src="<?= htmlspecialchars($thumb) ?>" alt="">
              <?php elseif ($firstOptImg): ?>
                <img src="<?= htmlspecialchars($firstOptImg) ?>" alt="">
              <?php else: ?>
                <?= htmlspecialchars($firstEmoji ?? '❓') ?>
              <?php endif; ?>
            </div>
            <div class="q-item-info">
              <div class="q-item-hint"><?= htmlspecialchars($q['hint'] ?? '') ?></div>
              <div class="q-item-meta">
                <?= htmlspecialchars($q['category'] ?? '') ?>
                <?php if ($correctLabel): ?> · ✅ <?= htmlspecialchars($correctLabel) ?><?php endif; ?>
                <?php if (!empty($q['audio'])): ?> · 🔊<?php endif; ?>
              </div>
            </div>
            <div class="q-item-actions" onclick="event.stopPropagation()">
              <button class="btn-edit" onclick="editQuestion(<?= $idx ?>)">Edit</button>
              <button class="btn-del"  onclick="deleteQuestion('<?= htmlspecialchars($q['id'], ENT_QUOTES) ?>')">Del</button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Question Form ── -->
  <div class="q-form-panel" id="qFormSection">
    <div class="q-form-title">
      <span id="formTitle">New Question</span>
    </div>

    <form id="qForm" onsubmit="saveQuestion(event)">
      <input type="hidden" name="id" id="qId">

      <!-- Info -->
      <div class="form-section">
        <h3>Question Info</h3>
        <div class="form-row">
          <label>Category</label>
          <input list="catList" name="category" id="qCategory" placeholder="Animals, Colors, Shapes…" required>
          <datalist id="catList">
            <option value="Animals">
            <option value="Birds">
            <option value="Sea Creatures">
            <option value="Insects">
            <option value="Colors">
            <option value="Shapes">
            <option value="Numbers">
            <option value="Letters">
            <option value="Food">
            <option value="Fruits">
            <option value="Vegetables">
            <option value="Drinks">
            <option value="Vehicles">
            <option value="Body Parts">
            <option value="Clothes">
            <option value="Family">
            <option value="Feelings">
            <option value="Weather">
            <option value="Nature">
            <option value="Household">
            <option value="School">
            <option value="Sports">
            <option value="Musical Instruments">
            <option value="Opposites">
          </datalist>
        </div>
        <div class="form-row">
          <label>Audio label / TTS text</label>
          <input type="text" name="speak" id="qSpeak" placeholder="Touch the cat!" required>
        </div>
        <div class="form-row">
          <label>Display hint (shown on card)</label>
          <input type="text" name="hint" id="qHint" placeholder="Cat" required>
        </div>
      </div>

      <!-- Media -->
      <div class="form-section">
        <h3>Question Media (optional)</h3>
        <div class="form-row">
          <label>Question image or GIF</label>
          <div class="upload-group">
            <input type="file" name="question_image" accept="image/*"
                   onchange="previewFile(this,'qImagePreview','qImageRemove')">
            <input type="hidden" name="question_image_existing" id="qImageExisting">
            <img id="qImagePreview" class="preview-img" style="display:none" alt="preview">
            <button type="button" id="qImageRemove" class="btn-remove-media"
                    style="display:none" onclick="clearMedia('qImagePreview','qImageExisting','qImageRemove')">✕ Remove</button>
          </div>
        </div>
        <div class="form-row">
          <label>Question audio (MP3 / WAV / OGG)</label>
          <div class="upload-group">
            <input type="file" name="question_audio" accept="audio/*"
                   onchange="previewAudio(this)">
            <input type="hidden" name="question_audio_existing" id="qAudioExisting">
            <div id="qAudioPreview" class="preview-audio" style="display:none">
              <audio id="qAudioPlayer" controls style="height:34px; max-width:200px"></audio>
              <button type="button" class="btn-remove-media"
                      onclick="clearAudio()">✕ Remove</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Options -->
      <div class="form-section">
        <h3>Answer Options &nbsp;<small style="font-weight:600;color:var(--muted)">(mark the correct one)</small></h3>
        <div class="opts-grid">
          <?php for ($i = 0; $i < 4; $i++): ?>
          <div class="opt-row" id="optRow<?= $i ?>">
            <!-- Correct radio -->
            <div class="opt-radio" title="Mark as correct answer">
              <input type="radio" name="correct_option" value="<?= $i ?>" <?= $i===0?'checked':'' ?>>
            </div>
            <!-- Label -->
            <input type="text" class="opt-label-input" name="opt_label_<?= $i ?>"
                   id="optLabel<?= $i ?>" placeholder="Option <?= $i+1 ?> label" required>
            <!-- Emoji -->
            <input type="text" class="opt-emoji-input" name="opt_emoji_<?= $i ?>"
                   id="optEmoji<?= $i ?>" placeholder="😀" title="Emoji (optional)">
            <!-- BG colour -->
            <input type="color" name="opt_bg_<?= $i ?>" id="optBg<?= $i ?>"
                   value="#F0F4FF" title="Card background colour">
            <!-- Image upload -->
            <div class="opt-img-wrap" title="Upload image/GIF">
              <label for="optImgInput<?= $i ?>">🖼</label>
              <input type="file" id="optImgInput<?= $i ?>" name="opt_image_<?= $i ?>"
                     accept="image/*"
                     onchange="previewOptImage(this,<?= $i ?>)">
              <img id="optImagePreview<?= $i ?>" class="opt-img-preview" style="display:none" alt="">
            </div>
            <input type="hidden" name="opt_image_existing_<?= $i ?>" id="optImageExisting<?= $i ?>">
            <!-- Correct badge -->
            <div class="opt-correct-label" id="optCorrectLabel<?= $i ?>"
                 style="<?= $i===0?'':'display:none' ?>">✅<br>Correct</div>
          </div>
          <?php endfor; ?>
        </div>
        <p style="font-size:.8rem;color:var(--muted);margin-top:12px">
          Each option needs a label. Emoji and image are optional (image takes priority when both are set).
        </p>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-save" id="saveBtn">💾 Save Question</button>
        <button type="button" class="btn-cancel-form" onclick="newQuestion()">✕ Clear</button>
      </div>
    </form>
  </div><!-- /.q-form-panel -->

</div><!-- /.q-manager -->

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════
     DASHBOARD VIEW
════════════════════════════════════════════════════════════ -->

<?php if ($rows): ?>
  <div style="display:flex;flex-wrap:wrap;gap:0;align-items:center;margin-bottom:0">
    <a href="?download=csv" class="download-btn" style="margin-bottom:24px">📥 Download All Data as CSV</a>
    <form method="POST" onsubmit="return confirm('Delete ALL records? This cannot be undone.')">
      <input type="hidden" name="action" value="reset_all">
      <button type="submit" class="reset-btn">🗑 Reset All Data</button>
    </form>
  </div>
<?php endif; ?>

<?php if ($stats): ?>
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-value"><?= count($stats['sessions']) ?></div>
    <div class="stat-label">👶 Unique Sessions</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $stats['total'] ?></div>
    <div class="stat-label">📋 Total Responses</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $stats['total'] ? round($stats['correct']/$stats['total']*100) : 0 ?>%</div>
    <div class="stat-label">✅ Overall Accuracy</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $stats['avgTime'] > 0 ? round($stats['avgTime']/1000, 1).'s' : '—' ?></div>
    <div class="stat-label">⏱ Avg Response Time</div>
  </div>
</div>

<div class="section-title">📊 Results by Category</div>
<div class="table-wrap" style="margin-bottom:32px">
  <table>
    <thead><tr><th>Category</th><th>Responses</th><th>Correct</th><th>Accuracy</th></tr></thead>
    <tbody>
    <?php foreach ($stats['cats'] as $cat => $d):
      $acc = round($d['correct'] / $d['total'] * 100); ?>
      <tr>
        <td><span class="badge badge-cat"><?= htmlspecialchars($cat) ?></span></td>
        <td><?= $d['total'] ?></td>
        <td><?= $d['correct'] ?></td>
        <td>
          <div class="bar-wrap"><div class="bar-fill" style="width:<?= $acc ?>%"></div></div>
          <span style="margin-left:8px;font-weight:800"><?= $acc ?>%</span>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="section-title">👶 Results by Age</div>
<div class="table-wrap" style="margin-bottom:32px">
  <table>
    <thead><tr><th>Age</th><th>Responses</th><th>Correct</th><th>Accuracy</th></tr></thead>
    <tbody>
    <?php foreach ($stats['ages'] as $age => $d):
      $acc = round($d['correct'] / $d['total'] * 100); ?>
      <tr>
        <td><b><?= $age ?> years</b></td>
        <td><?= $d['total'] ?></td>
        <td><?= $d['correct'] ?></td>
        <td>
          <div class="bar-wrap"><div class="bar-fill" style="width:<?= $acc ?>%"></div></div>
          <span style="margin-left:8px;font-weight:800"><?= $acc ?>%</span>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="section-title">🗂 All Responses
  <span style="font-family:Nunito;font-size:.9rem;color:var(--muted);font-weight:700;margin-left:8px">
    (<?= $total_r ?> records)
  </span>
</div>

<form class="filter-bar" method="GET">
  <input type="hidden" name="view" value="dashboard">
  <select name="cat">
    <option value="">All Categories</option>
    <?php
    $allCats = array_unique(array_filter(array_column($rows, 'category')));
    sort($allCats);
    foreach ($allCats as $c): ?>
      <option value="<?= htmlspecialchars($c) ?>" <?= ($filterCat===$c)?'selected':'' ?>>
        <?= htmlspecialchars($c) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="age">
    <option value="">All Ages</option>
    <?php foreach ([4,5,6] as $a): ?>
      <option value="<?= $a ?>" <?= ($filterAge==$a)?'selected':'' ?>><?= $a ?> years</option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="filter-apply">Filter</button>
  <?php if ($filterCat || $filterAge): ?>
    <a href="admin.php" class="filter-clear">✕ Clear</a>
  <?php endif; ?>
</form>

<?php if ($paged): ?>
<div class="table-wrap">
  <table>
    <thead><tr>
      <th>Time</th><th>Child ID</th><th>Age</th><th>Mode</th><th>Category</th>
      <th>Question</th><th>Correct Ans</th><th>Selected</th>
      <th>Result</th><th>Attempts</th><th>Time (ms)</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($paged as $r):
      $rid = $r['id'] ?? null; ?>
      <tr id="resp-row-<?= htmlspecialchars((string)($rid ?? '')) ?>">
        <td style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars(substr($r['timestamp']??'',0,16)) ?></td>
        <td><?= htmlspecialchars($r['child_id'] ?? '—') ?></td>
        <td style="font-weight:800"><?= intval($r['child_age'] ?? 0) ?></td>
        <td>
          <?php $m = $r['quiz_mode'] ?? 'correct'; ?>
          <span class="badge" style="<?= $m==='free' ? 'background:#e0faf7;color:#2bbcb3' : 'background:#fff0f0;color:var(--coral)' ?>">
            <?= $m === 'free' ? '📝 Free' : '🎯 Find' ?>
          </span>
        </td>
        <td><span class="badge badge-cat"><?= htmlspecialchars($r['category'] ?? '') ?></span></td>
        <td><?= htmlspecialchars($r['question_text'] ?? '') ?></td>
        <td style="font-weight:700"><?= htmlspecialchars($r['correct_label'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['selected_label'] ?? '') ?></td>
        <td>
          <?php if (isset($r['is_correct'])): ?>
            <span class="badge <?= $r['is_correct'] ? 'badge-yes' : 'badge-no' ?>">
              <?= $r['is_correct'] ? '✅ Correct' : '❌ Wrong' ?>
            </span>
          <?php endif; ?>
        </td>
        <td><?= intval($r['attempts'] ?? 1) ?></td>
        <td><?= number_format(intval($r['response_time_ms'] ?? 0)) ?></td>
        <td>
          <?php if ($rid): ?>
            <button class="btn-row-del" onclick="delRecord(<?= (int)$rid ?>, this)">🗑 Del</button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php for ($p = 1; $p <= $pages; $p++):
    $q = http_build_query(array_merge($_GET, ['page' => $p]));
  ?>
    <a href="?<?= $q ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
  <div class="empty-state">
    <div class="empty-icon">📭</div>
    <p>No responses recorded yet.<br>Open the quiz and complete some questions first.</p>
  </div>
<?php endif; ?>

<?php endif; // end $loggedIn check ?>
</main>

<!-- Toast notification -->
<div class="toast" id="toast"></div>

<?php if ($loggedIn && $view === 'dashboard'): ?>
<script>
async function delRecord(id, btn) {
  if (!confirm('Delete this response record?')) return;
  btn.disabled = true;
  btn.textContent = '…';
  try {
    const fd = new FormData();
    fd.append('action', 'delete_record');
    fd.append('record_id', id);
    const res  = await fetch('admin.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      const row = document.getElementById('resp-row-' + id);
      if (row) {
        row.style.transition = 'opacity .3s';
        row.style.opacity = '0';
        setTimeout(() => row.remove(), 320);
      }
    } else {
      btn.disabled = false;
      btn.textContent = '🗑 Del';
    }
  } catch(e) {
    btn.disabled = false;
    btn.textContent = '🗑 Del';
  }
}
</script>
<?php endif; ?>

<?php if ($loggedIn && $view === 'questions'): ?>
<script>
// Questions data embedded from PHP
window.QS = <?= json_encode($qsData, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;

let currentEditId = null;

// ── Highlight editing item in list ──────────────────────────────
function setEditingItem(idx) {
  document.querySelectorAll('.q-item').forEach((el, i) => {
    el.classList.toggle('editing', i === idx);
  });
}

// ── New (clear form) ────────────────────────────────────────────
function newQuestion() {
  currentEditId = null;
  document.getElementById('formTitle').textContent = 'New Question';
  document.getElementById('qId').value    = '';
  document.getElementById('qCategory').value = '';
  document.getElementById('qSpeak').value = '';
  document.getElementById('qHint').value  = '';
  clearMedia('qImagePreview','qImageExisting','qImageRemove');
  clearAudio();
  for (let i = 0; i < 4; i++) {
    document.getElementById(`optLabel${i}`).value = '';
    document.getElementById(`optEmoji${i}`).value = '';
    document.getElementById(`optBg${i}`).value    = '#F0F4FF';
    document.getElementById(`optImagePreview${i}`).style.display = 'none';
    document.getElementById(`optImageExisting${i}`).value = '';
    // reset file input
    const fi = document.getElementById(`optImgInput${i}`);
    if (fi) fi.value = '';
  }
  document.querySelector('input[name="correct_option"][value="0"]').checked = true;
  updateCorrectLabels();
  document.querySelectorAll('.q-item').forEach(el => el.classList.remove('editing'));
  document.getElementById('qFormSection').scrollIntoView({behavior:'smooth', block:'start'});
}

// ── Populate form for editing ────────────────────────────────────
function editQuestion(idx) {
  const q = window.QS[idx];
  if (!q) return;
  currentEditId = q.id;

  document.getElementById('formTitle').textContent = 'Edit Question';
  document.getElementById('qId').value       = q.id;
  document.getElementById('qCategory').value = q.category  || '';
  document.getElementById('qSpeak').value    = q.speak     || '';
  document.getElementById('qHint').value     = q.hint      || '';

  // Question image
  if (q.image) {
    const img = document.getElementById('qImagePreview');
    img.src = q.image; img.style.display = '';
    document.getElementById('qImageRemove').style.display = '';
  } else {
    document.getElementById('qImagePreview').style.display = 'none';
    document.getElementById('qImageRemove').style.display  = 'none';
  }
  document.getElementById('qImageExisting').value = q.image || '';

  // Question audio
  if (q.audio) {
    document.getElementById('qAudioPlayer').src = q.audio;
    document.getElementById('qAudioPreview').style.display = '';
  } else {
    document.getElementById('qAudioPreview').style.display = 'none';
    document.getElementById('qAudioPlayer').src = '';
  }
  document.getElementById('qAudioExisting').value = q.audio || '';

  // Options
  let correctIdx = 0;
  (q.options || []).forEach((opt, i) => {
    if (i >= 4) return;
    document.getElementById(`optLabel${i}`).value = opt.label || '';
    document.getElementById(`optEmoji${i}`).value = opt.emoji || '';
    document.getElementById(`optBg${i}`).value    = opt.bg    || '#F0F4FF';
    document.getElementById(`optImageExisting${i}`).value = opt.image || '';
    const fi = document.getElementById(`optImgInput${i}`);
    if (fi) fi.value = '';
    const prev = document.getElementById(`optImagePreview${i}`);
    if (opt.image) { prev.src = opt.image; prev.style.display = ''; }
    else           { prev.style.display = 'none'; }
    if (opt.correct) correctIdx = i;
  });
  document.querySelector(`input[name="correct_option"][value="${correctIdx}"]`).checked = true;
  updateCorrectLabels();

  setEditingItem(idx);
  document.getElementById('qFormSection').scrollIntoView({behavior:'smooth', block:'start'});
}

// ── Delete ───────────────────────────────────────────────────────
async function deleteQuestion(qId) {
  if (!confirm('Delete this question? Uploaded files will also be removed.')) return;
  const fd = new FormData();
  fd.append('action', 'delete');
  fd.append('id', qId);
  try {
    const res  = await fetch('questions_api.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.success) {
      showToast('Question deleted.', 'success');
      setTimeout(() => window.location.reload(), 800);
    } else {
      showToast('Error: ' + (data.error || 'Could not delete.'), 'error');
    }
  } catch(e) {
    showToast('Network error.', 'error');
  }
}

// ── Save ─────────────────────────────────────────────────────────
async function saveQuestion(e) {
  e.preventDefault();
  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
  btn.textContent = 'Saving…';

  const fd = new FormData(document.getElementById('qForm'));
  fd.append('action', 'save');

  try {
    const res  = await fetch('questions_api.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.success) {
      showToast('Question saved!', 'success');
      setTimeout(() => window.location.reload(), 900);
    } else {
      showToast('Error: ' + (data.error || 'Could not save.'), 'error');
      btn.disabled = false;
      btn.textContent = '💾 Save Question';
    }
  } catch(e) {
    showToast('Network error.', 'error');
    btn.disabled = false;
    btn.textContent = '💾 Save Question';
  }
}

// ── File preview helpers ─────────────────────────────────────────
function previewFile(input, previewId, removeBtnId) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = ev => {
    const img = document.getElementById(previewId);
    img.src = ev.target.result;
    img.style.display = '';
    if (removeBtnId) document.getElementById(removeBtnId).style.display = '';
  };
  reader.readAsDataURL(input.files[0]);
}

function previewAudio(input) {
  if (!input.files || !input.files[0]) return;
  const url = URL.createObjectURL(input.files[0]);
  document.getElementById('qAudioPlayer').src = url;
  document.getElementById('qAudioPreview').style.display = '';
  document.getElementById('qAudioExisting').value = '';
}

function previewOptImage(input, idx) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = ev => {
    const img = document.getElementById(`optImagePreview${idx}`);
    img.src = ev.target.result;
    img.style.display = '';
    document.getElementById(`optImageExisting${idx}`).value = '';
  };
  reader.readAsDataURL(input.files[0]);
}

function clearMedia(previewId, existingId, removeBtnId) {
  const img = document.getElementById(previewId);
  if (img) { img.src = ''; img.style.display = 'none'; }
  const ex = document.getElementById(existingId);
  if (ex) ex.value = '';
  if (removeBtnId) {
    const btn = document.getElementById(removeBtnId);
    if (btn) btn.style.display = 'none';
  }
}

function clearAudio() {
  document.getElementById('qAudioPlayer').src = '';
  document.getElementById('qAudioPreview').style.display = 'none';
  document.getElementById('qAudioExisting').value = '';
}

// ── Correct label indicators ─────────────────────────────────────
function updateCorrectLabels() {
  const checked = document.querySelector('input[name="correct_option"]:checked');
  for (let i = 0; i < 4; i++) {
    const lbl = document.getElementById(`optCorrectLabel${i}`);
    if (lbl) lbl.style.display = (checked && parseInt(checked.value) === i) ? '' : 'none';
  }
}
document.querySelectorAll('input[name="correct_option"]').forEach(r => {
  r.addEventListener('change', updateCorrectLabels);
});

// ── Toast ────────────────────────────────────────────────────────
function showToast(msg, type='') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = 'toast ' + type + ' show';
  setTimeout(() => t.classList.remove('show'), 2800);
}
</script>
<?php endif; ?>
</body>
</html>
