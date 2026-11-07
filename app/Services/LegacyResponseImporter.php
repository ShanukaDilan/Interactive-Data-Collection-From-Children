<?php

namespace App\Services;

use App\Enums\QuizMode;
use App\Models\Participant;
use App\Models\Question;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\Study;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Imports answers recorded by the original PHP app (responses.jsonl lines or rows of its
 * `responses` table) into quiz sessions and responses.
 *
 * - Rows are grouped into sessions by the legacy session_id.
 * - UUIDs are derived deterministically from the legacy data, so re-running never duplicates.
 * - Text the old app HTML-escaped before storing is decoded.
 * - Legacy child IDs (often names) are kept only as `legacy_child_id`, or — with
 *   $createParticipants — turned into pseudonymous participants whose name is encrypted.
 */
class LegacyResponseImporter
{
    private const UUID_NAMESPACE = 'b6c5b0a2-6a0e-4b8f-9d0b-2f3c1e6b7a10';

    /** @var array<string, int> */
    private array $stats = [];

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function import(Study $study, iterable $rows, bool $createParticipants = false): array
    {
        $this->stats = [
            'rows' => 0,
            'invalid_rows' => 0,
            'sessions_created' => 0,
            'sessions_existing' => 0,
            'responses_created' => 0,
            'responses_skipped' => 0,
            'participants_created' => 0,
        ];

        $normalized = LazyCollection::make($rows)
            ->map(function (array $row): ?array {
                $this->stats['rows']++;
                $clean = $this->normalize($row);
                if ($clean === null) {
                    $this->stats['invalid_rows']++;
                }

                return $clean;
            })
            ->filter()
            ->collect();

        $questionIds = Question::whereNotNull('legacy_id')->pluck('id', 'legacy_id');

        $normalized
            ->groupBy('session_id')
            ->each(fn (Collection $sessionRows, string $legacySessionId) => DB::transaction(
                fn () => $this->importSession($study, $legacySessionId, $sessionRows, $questionIds, $createParticipants)
            ));

        return $this->stats;
    }

    /**
     * Read a responses.jsonl file line by line.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public static function readJsonl(string $path): LazyCollection
    {
        return LazyCollection::make(function () use ($path) {
            $handle = fopen($path, 'r');
            try {
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $decoded = json_decode($line, true);
                    yield is_array($decoded) ? $decoded : ['__invalid' => true];
                }
            } finally {
                fclose($handle);
            }
        });
    }

    /**
     * Read the original app's `responses` table from a database connection.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public static function readDatabase(string $connection = 'legacy'): LazyCollection
    {
        return DB::connection($connection)
            ->table('responses')
            ->orderBy('id')
            ->lazy(1000)
            ->map(fn (object $row) => (array) $row);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<string, int>  $questionIds
     */
    private function importSession(Study $study, string $legacySessionId, Collection $rows, Collection $questionIds, bool $createParticipants): void
    {
        $rows = $rows->sortBy(fn (array $row) => $row['answered_at']->getTimestamp())->values();
        $first = $rows->first();

        $session = QuizSession::where('study_id', $study->id)->where('legacy_session_id', $legacySessionId)->first();

        if ($session === null) {
            $session = QuizSession::create([
                'uuid' => $this->uuid("session:{$study->id}:{$legacySessionId}"),
                'study_id' => $study->id,
                'participant_id' => $createParticipants ? $this->participantFor($study, $first['child_id'], $first['child_age']) : null,
                'legacy_session_id' => $legacySessionId,
                'legacy_child_id' => $first['child_id'],
                'child_age' => $first['child_age'],
                'quiz_mode' => $first['quiz_mode'],
                'started_at' => $first['answered_at'],
                // The old app did not record completion; the last answer is the best available end time.
                'completed_at' => $rows->last()['answered_at'],
            ]);
            $this->stats['sessions_created']++;
        } else {
            $this->stats['sessions_existing']++;
        }

        $now = now();
        $records = $rows->map(fn (array $row, int $index) => [
            'client_uuid' => $this->uuid("response:{$study->id}:{$legacySessionId}:{$index}:{$row['question_ref']}:{$row['answered_at']->getTimestamp()}"),
            'quiz_session_id' => $session->id,
            'question_id' => $questionIds->get($row['question_ref']),
            'question_ref' => $row['question_ref'],
            'question_text' => $row['question_text'],
            'category' => $row['category'],
            'correct_label' => $row['correct_label'],
            'selected_label' => $row['selected_label'],
            'selected_number' => is_numeric($row['selected_label']) && is_numeric($row['correct_label']) ? (int) $row['selected_label'] : null,
            'is_correct' => $row['is_correct'],
            'attempts' => $row['attempts'],
            'response_time_ms' => $row['response_time_ms'],
            'position' => $index,
            'answered_at' => $row['answered_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $created = $records->chunk(500)->sum(fn (Collection $chunk) => QuizResponse::insertOrIgnore($chunk->values()->all()));
        $this->stats['responses_created'] += $created;
        $this->stats['responses_skipped'] += $records->count() - $created;
    }

    /**
     * Pseudonymous participant per legacy child ID. The code is derived from a hash (stable
     * across re-runs, reveals nothing); the original ID is stored only as the encrypted name.
     */
    private function participantFor(Study $study, ?string $childId, ?int $age): ?int
    {
        if ($childId === null || $childId === '') {
            return null;
        }

        $code = 'L-'.strtoupper(substr(hash('sha256', $study->id.'|'.Str::lower($childId)), 0, 6));

        $participant = Participant::firstOrCreate(
            ['study_id' => $study->id, 'code' => $code],
            ['display_name' => $childId, 'age' => $age, 'notes' => 'Imported from the original app.'],
        );

        if ($participant->wasRecentlyCreated) {
            $this->stats['participants_created']++;
        }

        return $participant->id;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalize(array $row): ?array
    {
        if (isset($row['__invalid']) || blank($row['question_id'] ?? null)) {
            return null;
        }

        try {
            $answeredAt = CarbonImmutable::parse($row['timestamp'] ?? $row['created_at'] ?? 'now');
        } catch (Throwable) {
            return null;
        }

        $text = fn (string $key, int $max) => filled($row[$key] ?? null)
            ? Str::limit(html_entity_decode((string) $row[$key], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $max, '')
            : null;

        $sessionId = filled($row['session_id'] ?? null)
            ? Str::limit((string) $row['session_id'], 64, '')
            : 'unknown-'.$answeredAt->format('Ymd').'-'.Str::lower((string) ($row['child_id'] ?? 'anon'));

        $age = (int) ($row['child_age'] ?? 0);

        return [
            'session_id' => $sessionId,
            'child_id' => $text('child_id', 60),
            'child_age' => $age >= 2 && $age <= 12 ? $age : null,
            'quiz_mode' => ($row['quiz_mode'] ?? null) === 'free' ? QuizMode::Free->value : QuizMode::Correct->value,
            'question_ref' => Str::limit((string) $row['question_id'], 40, ''),
            'question_text' => $text('question_text', 255),
            'category' => $text('category', 80),
            'correct_label' => $text('correct_label', 100),
            'selected_label' => $text('selected_label', 100),
            'is_correct' => filter_var($row['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN) || ($row['is_correct'] ?? null) === 1,
            'attempts' => max(1, min(100, (int) ($row['attempts'] ?? 1))),
            'response_time_ms' => max(0, min(3600000, (int) ($row['response_time_ms'] ?? 0))),
            'answered_at' => $answeredAt,
        ];
    }

    private function uuid(string $name): string
    {
        return Uuid::uuid5(self::UUID_NAMESPACE, $name)->toString();
    }
}
