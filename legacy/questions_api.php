<?php
/**
 * questions_api.php — CRUD API for quiz questions (admin only).
 * Requires active admin session set by admin.php.
 */

session_start();

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

define('QUESTIONS_FILE', __DIR__ . '/data/questions.json');
define('UPLOADS_DIR',    __DIR__ . '/uploads/');
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024); // 10 MB

header('Content-Type: application/json; charset=UTF-8');

// ── Helpers ──────────────────────────────────────────────────────────

function loadQuestions(): array {
    if (!file_exists(QUESTIONS_FILE)) return [];
    $data = json_decode(file_get_contents(QUESTIONS_FILE), true);
    return is_array($data) ? $data : [];
}

function saveQuestions(array $questions): bool {
    $dir = dirname(QUESTIONS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return file_put_contents(
        QUESTIONS_FILE,
        json_encode(array_values($questions), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

function handleUpload(string $fileKey, string $type): ?string {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$fileKey];

    if ($file['size'] > MAX_UPLOAD_BYTES) return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = $type === 'image'
        ? ['jpg', 'jpeg', 'png', 'gif', 'webp']
        : ['mp3', 'ogg', 'wav', 'm4a'];

    if (!in_array($ext, $allowed, true)) return null;

    // Verify it is actually an image (extra safety for image uploads)
    if ($type === 'image' && !@getimagesize($file['tmp_name'])) return null;

    if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0755, true);

    $filename = $type . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest     = UPLOADS_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    return 'uploads/' . $filename;
}

function deleteOldFile(?string $path): void {
    if (!$path) return;
    $abs = __DIR__ . '/' . ltrim($path, '/');
    if (file_exists($abs) && str_starts_with(realpath($abs), realpath(UPLOADS_DIR))) {
        @unlink($abs);
    }
}

function safeStr(string $key, int $maxLen = 200): string {
    return substr(trim(htmlspecialchars($_POST[$key] ?? '', ENT_QUOTES)), 0, $maxLen);
}

// ── Route ────────────────────────────────────────────────────────────

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ── LIST ──────────────────────────────────────────────────────────
    case 'list':
        echo json_encode(loadQuestions());
        break;

    // ── SAVE (create or update) ───────────────────────────────────────
    case 'save':
        $questions = loadQuestions();

        $qid = trim($_POST['id'] ?? '');
        if (empty($qid)) {
            $qid = 'q_' . bin2hex(random_bytes(6));
        }

        // Find existing question (to keep old file paths when not replaced)
        $existing = null;
        foreach ($questions as $q) {
            if ($q['id'] === $qid) { $existing = $q; break; }
        }

        // ── Question image ────────────────────────────────────────────
        $newQImg = handleUpload('question_image', 'image');
        if ($newQImg) {
            deleteOldFile($existing['image'] ?? null);
            $qImg = $newQImg;
        } else {
            $kept = trim($_POST['question_image_existing'] ?? '');
            $qImg = !empty($kept) ? $kept : null;
            // If existing had an image but admin cleared it, delete file
            if (empty($kept) && !empty($existing['image'])) {
                deleteOldFile($existing['image']);
            }
        }

        // ── Question audio ────────────────────────────────────────────
        $newAudio = handleUpload('question_audio', 'audio');
        if ($newAudio) {
            deleteOldFile($existing['audio'] ?? null);
            $qAudio = $newAudio;
        } else {
            $kept = trim($_POST['question_audio_existing'] ?? '');
            $qAudio = !empty($kept) ? $kept : null;
            if (empty($kept) && !empty($existing['audio'])) {
                deleteOldFile($existing['audio']);
            }
        }

        // ── Options ───────────────────────────────────────────────────
        $correctIdx = max(0, min(3, intval($_POST['correct_option'] ?? 0)));
        $options    = [];

        for ($i = 0; $i < 4; $i++) {
            $existingOpt = $existing['options'][$i] ?? null;

            $newOptImg = handleUpload("opt_image_{$i}", 'image');
            if ($newOptImg) {
                deleteOldFile($existingOpt['image'] ?? null);
                $optImg = $newOptImg;
            } else {
                $kept   = trim($_POST["opt_image_existing_{$i}"] ?? '');
                $optImg = !empty($kept) ? $kept : null;
                if (empty($kept) && !empty($existingOpt['image'])) {
                    deleteOldFile($existingOpt['image']);
                }
            }

            $options[] = [
                'label'   => safeStr("opt_label_{$i}", 80),
                'correct' => ($i === $correctIdx),
                'bg'      => preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST["opt_bg_{$i}"] ?? '')
                                ? $_POST["opt_bg_{$i}"] : '#F0F4FF',
                'emoji'   => mb_substr(trim($_POST["opt_emoji_{$i}"] ?? ''), 0, 8),
                'image'   => $optImg,
            ];
        }

        $question = [
            'id'       => $qid,
            'category' => safeStr('category', 60),
            'speak'    => safeStr('speak', 200),
            'hint'     => safeStr('hint', 80),
            'image'    => $qImg,
            'audio'    => $qAudio,
            'options'  => $options,
        ];

        // Replace or append
        $found = false;
        foreach ($questions as &$q) {
            if ($q['id'] === $qid) { $q = $question; $found = true; break; }
        }
        unset($q);
        if (!$found) $questions[] = $question;

        if (saveQuestions($questions)) {
            echo json_encode(['success' => true, 'id' => $qid]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Could not write questions file. Check directory permissions.']);
        }
        break;

    // ── DELETE ────────────────────────────────────────────────────────
    case 'delete':
        $qid = trim($_POST['id'] ?? '');
        if (empty($qid)) {
            echo json_encode(['error' => 'No ID provided']);
            break;
        }
        $questions = loadQuestions();
        foreach ($questions as $q) {
            if ($q['id'] === $qid) {
                deleteOldFile($q['image'] ?? null);
                deleteOldFile($q['audio'] ?? null);
                foreach ($q['options'] ?? [] as $opt) {
                    deleteOldFile($opt['image'] ?? null);
                }
                break;
            }
        }
        $questions = array_values(array_filter($questions, fn($q) => $q['id'] !== $qid));
        if (saveQuestions($questions)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Could not write questions file.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
}
