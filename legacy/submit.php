<?php
/**
 * submit.php — Receives one answer record as JSON.
 * Writes to the database if configured; falls back to responses.jsonl.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['question_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// ── Sanitise ─────────────────────────────────────────────────────
function clean(string $v, int $max = 200): string {
    return htmlspecialchars(trim(substr($v, 0, $max)), ENT_QUOTES, 'UTF-8');
}

$record = [
    'session_id'       => preg_replace('/[^a-zA-Z0-9_\-]/', '', substr($data['session_id']    ?? '', 0, 64)),
    'child_age'        => max(0, min(10, intval($data['child_age']  ?? 0))),
    'child_id'         => clean($data['child_id']         ?? '', 60),
    'quiz_mode'        => in_array($data['quiz_mode'] ?? '', ['correct','free']) ? $data['quiz_mode'] : 'correct',
    'question_id'      => preg_replace('/[^a-zA-Z0-9_]/', '', substr($data['question_id'] ?? '', 0, 20)),
    'question_text'    => clean($data['question_text']    ?? ''),
    'category'         => clean($data['category']         ?? '', 60),
    'correct_label'    => clean($data['correct_label']    ?? '', 100),
    'selected_label'   => clean($data['selected_label']   ?? '', 100),
    'is_correct'       => isset($data['is_correct']) && $data['is_correct'] ? 1 : 0,
    'attempts'         => max(1, intval($data['attempts'] ?? 1)),
    'response_time_ms' => max(0, intval($data['response_time_ms'] ?? 0)),
    'timestamp'        => date('Y-m-d H:i:s'),
];

// ── Try database first ───────────────────────────────────────────
require_once __DIR__ . '/db.php';
try {
    $pdo = getDb();
} catch (Throwable $e) {
    error_log('submit getDb: ' . $e->getMessage());
    $pdo = null;
}

if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO responses
                (session_id, child_age, child_id, quiz_mode, question_id,
                 question_text, category, correct_label, selected_label,
                 is_correct, attempts, response_time_ms)
            VALUES
                (:session_id, :child_age, :child_id, :quiz_mode, :question_id,
                 :question_text, :category, :correct_label, :selected_label,
                 :is_correct, :attempts, :response_time_ms)
        ");
        $stmt->execute([
            ':session_id'       => $record['session_id'],
            ':child_age'        => $record['child_age'],
            ':child_id'         => $record['child_id'],
            ':quiz_mode'        => $record['quiz_mode'],
            ':question_id'      => $record['question_id'],
            ':question_text'    => $record['question_text'],
            ':category'         => $record['category'],
            ':correct_label'    => $record['correct_label'],
            ':selected_label'   => $record['selected_label'],
            ':is_correct'       => $record['is_correct'],
            ':attempts'         => $record['attempts'],
            ':response_time_ms' => $record['response_time_ms'],
        ]);
        echo json_encode(['success' => true, 'storage' => 'db', 'saved' => $record['question_id']]);
        exit;
    } catch (Throwable $e) {
        error_log('DB insert error: ' . $e->getMessage());
        // fall through to file fallback
    }
}

// ── File fallback ────────────────────────────────────────────────
$dataDir  = __DIR__ . '/data';
$dataFile = $dataDir . '/responses.jsonl';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$line = json_encode($record, JSON_UNESCAPED_UNICODE) . "\n";
$ok   = file_put_contents($dataFile, $line, FILE_APPEND | LOCK_EX);

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not write data']);
    exit;
}

echo json_encode(['success' => true, 'storage' => 'file', 'saved' => $record['question_id']]);
