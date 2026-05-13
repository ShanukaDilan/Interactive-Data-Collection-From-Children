<?php
/**
 * submit.php — Receives one answer record as JSON, appends to JSONL data file.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['question_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// ── Sanitise every field ─────────────────────────────────────────
function clean_str(string $v, int $max = 200): string {
    return htmlspecialchars(trim(substr($v, 0, $max)), ENT_QUOTES, 'UTF-8');
}

$record = [
    'session_id'       => preg_replace('/[^a-zA-Z0-9_\-]/', '', substr($data['session_id']    ?? '', 0, 64)),
    'child_age'        => max(0, min(10, intval($data['child_age']  ?? 0))),
    'child_id'         => clean_str($data['child_id']         ?? '', 60),
    'question_id'      => preg_replace('/[^a-zA-Z0-9_]/', '', substr($data['question_id'] ?? '', 0, 20)),
    'question_text'    => clean_str($data['question_text']    ?? ''),
    'category'         => clean_str($data['category']         ?? '', 60),
    'correct_label'    => clean_str($data['correct_label']    ?? '', 100),
    'selected_label'   => clean_str($data['selected_label']   ?? '', 100),
    'is_correct'       => isset($data['is_correct']) && $data['is_correct'] ? 1 : 0,
    'attempts'         => max(1, intval($data['attempts'] ?? 1)),
    'response_time_ms' => max(0, intval($data['response_time_ms'] ?? 0)),
    'timestamp'        => date('Y-m-d H:i:s'),
];

// ── Write to JSONL file ──────────────────────────────────────────
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

echo json_encode(['success' => true, 'saved' => $record['question_id']]);
