<?php
/**
 * admin.php — Password-protected dashboard: view all responses + download CSV.
 * Default password: admin123   (change ADMIN_PASSWORD below!)
 */

define('ADMIN_PASSWORD', 'admin123');   // ← CHANGE THIS
define('DATA_FILE',  __DIR__ . '/data/responses.jsonl');

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
    // UTF-8 BOM for Excel
    fputs($out, "\xEF\xBB\xBF");
    // Header row
    fputcsv($out, [
        'Session ID','Child ID','Child Age',
        'Question ID','Question Text','Category',
        'Correct Answer','Selected Answer',
        'Is Correct','Attempts','Response Time (ms)','Timestamp'
    ]);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['session_id']    ?? '',
            $r['child_id']      ?? '',
            $r['child_age']     ?? '',
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

// ── Load all records ─────────────────────────────────────────────
function loadData(): array {
    if (!file_exists(DATA_FILE)) return [];
    $lines = file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $rows  = [];
    foreach ($lines as $line) {
        $r = json_decode($line, true);
        if ($r) $rows[] = $r;
    }
    return $rows;
}

// ── Compute stats ─────────────────────────────────────────────────
function computeStats(array $rows): array {
    if (!$rows) return [];

    $sessions = array_unique(array_column($rows, 'session_id'));
    $total    = count($rows);
    $correct  = array_sum(array_column($rows, 'is_correct'));
    $rtimes   = array_filter(array_column($rows, 'response_time_ms'));
    $avgTime  = $rtimes ? round(array_sum($rtimes) / count($rtimes)) : 0;

    // By category
    $cats = [];
    foreach ($rows as $r) {
        $c = $r['category'] ?? 'Unknown';
        $cats[$c]['total']   = ($cats[$c]['total']   ?? 0) + 1;
        $cats[$c]['correct'] = ($cats[$c]['correct'] ?? 0) + $r['is_correct'];
    }

    // By age
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

    /* NAV */
    nav {
      background: white;
      border-bottom: 2px solid var(--border);
      padding: 0 32px;
      display: flex; align-items: center; gap: 20px; height: 64px;
      position: sticky; top:0; z-index:50;
      box-shadow: 0 2px 12px rgba(0,0,0,.06);
    }
    .nav-logo { font-family:'Fredoka One',cursive; font-size:1.5rem; color:var(--coral); }
    .nav-spacer { flex:1; }
    .nav-btn {
      background: var(--sky); color:white;
      border:none; border-radius:10px;
      padding:8px 20px;
      font-family:'Nunito',sans-serif; font-weight:800; font-size:.9rem;
      cursor:pointer; text-decoration:none;
      transition:opacity .15s;
    }
    .nav-btn:hover { opacity:.85; }
    .nav-btn.danger { background:var(--coral); }

    /* MAIN */
    main { max-width: 1300px; margin: 0 auto; padding: 32px 24px; }

    /* LOGIN */
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

    /* STAT CARDS */
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

    /* SECTION TITLES */
    .section-title { font-family:'Fredoka One',cursive; font-size:1.4rem; color:var(--text); margin-bottom:16px; }

    /* CATEGORY TABLE */
    .table-wrap { background:white; border-radius:20px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.06); margin-bottom:32px; }
    table { width:100%; border-collapse:collapse; }
    thead tr { background:var(--bg); }
    th { padding:13px 18px; text-align:left; font-size:.82rem; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; }
    td { padding:12px 18px; border-top:1px solid var(--border); font-size:.93rem; }
    tr:hover td { background:#fafafa; }
    .badge {
      display:inline-block; padding:3px 10px; border-radius:99px;
      font-weight:800; font-size:.78rem;
    }
    .badge-yes  { background:#d4edda; color:#155724; }
    .badge-no   { background:#f8d7da; color:#721c24; }
    .badge-cat  { background:var(--sun); color:#7a5a00; }
    .bar-wrap { background:#eee; border-radius:99px; height:8px; width:100px; display:inline-block; vertical-align:middle; overflow:hidden; }
    .bar-fill { height:100%; border-radius:99px; background:linear-gradient(90deg,var(--sky),var(--mint)); }

    /* FILTERS */
    .filter-bar {
      display:flex; gap:12px; align-items:center;
      flex-wrap:wrap;
      margin-bottom:18px;
    }
    .filter-bar select, .filter-bar input {
      padding:8px 14px; border:2px solid var(--border); border-radius:10px;
      font-family:'Nunito',sans-serif; font-weight:700; font-size:.9rem;
      outline:none; cursor:pointer;
    }
    .filter-bar select:focus { border-color:var(--sky); }
    .filter-apply {
      padding:8px 20px; background:var(--sky); color:white;
      border:none; border-radius:10px;
      font-family:'Nunito',sans-serif; font-weight:800; font-size:.9rem;
      cursor:pointer;
    }
    .filter-clear { text-decoration:none; color:var(--muted); font-weight:700; font-size:.9rem; }

    /* PAGINATION */
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

    /* Download btn */
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

    .empty-state { text-align:center; padding:60px 20px; color:var(--muted); }
    .empty-state .empty-icon { font-size:3.5rem; margin-bottom:12px; }
    .empty-state p { font-weight:700; font-size:1.1rem; }
  </style>
</head>
<body>

<nav>
  <span class="nav-logo">🌟 Learning Fun — Admin</span>
  <span class="nav-spacer"></span>
  <?php if ($loggedIn): ?>
    <a href="index.php" target="_blank" class="nav-btn">▶ Open Quiz</a>
    <a href="?logout" class="nav-btn danger">Logout</a>
  <?php endif; ?>
</nav>

<main>

<?php if (!$loggedIn): ?>
<!-- ── LOGIN ──────────────────────────────── -->
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

<?php else: ?>
<!-- ── DASHBOARD ──────────────────────────── -->

<?php if ($rows): ?>
  <a href="?download=csv" class="download-btn">📥 Download All Data as CSV</a>
<?php endif; ?>

<!-- Stats -->
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

<!-- By Category -->
<div class="section-title">📊 Results by Category</div>
<div class="table-wrap" style="margin-bottom:32px">
  <table>
    <thead><tr>
      <th>Category</th><th>Responses</th><th>Correct</th><th>Accuracy</th>
    </tr></thead>
    <tbody>
    <?php foreach ($stats['cats'] as $cat => $d):
      $acc = round($d['correct'] / $d['total'] * 100);
    ?>
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

<!-- By Age -->
<div class="section-title">👶 Results by Age</div>
<div class="table-wrap" style="margin-bottom:32px">
  <table>
    <thead><tr><th>Age</th><th>Responses</th><th>Correct</th><th>Accuracy</th></tr></thead>
    <tbody>
    <?php foreach ($stats['ages'] as $age => $d):
      $acc = round($d['correct'] / $d['total'] * 100);
    ?>
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

<!-- All Responses -->
<div class="section-title">🗂 All Responses
  <span style="font-family:Nunito;font-size:.9rem;color:var(--muted);font-weight:700;margin-left:8px">
    (<?= $total_r ?> records)
  </span>
</div>

<!-- Filters -->
<form class="filter-bar" method="GET">
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
      <th>Time</th>
      <th>Child ID</th>
      <th>Age</th>
      <th>Category</th>
      <th>Question</th>
      <th>Correct Ans</th>
      <th>Selected</th>
      <th>Result</th>
      <th>Attempts</th>
      <th>Time (ms)</th>
    </tr></thead>
    <tbody>
    <?php foreach ($paged as $r): ?>
      <tr>
        <td style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars(substr($r['timestamp']??'',0,16)) ?></td>
        <td><?= htmlspecialchars($r['child_id'] ?? '—') ?></td>
        <td style="font-weight:800"><?= intval($r['child_age'] ?? 0) ?></td>
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
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
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

<?php endif; ?>
</main>
</body>
</html>
