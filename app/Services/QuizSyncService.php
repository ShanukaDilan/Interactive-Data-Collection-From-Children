<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Participant;
use App\Models\Question;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\QuizTap;
use App\Models\Study;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores quiz sessions and answers sent from the quiz page or an offline device.
 *
 * Idempotent: sessions are matched by uuid and answers by client_uuid, so a device
 * may safely re-send everything it has not yet seen acknowledged.
 */
class QuizSyncService
{
    /**
     * @param  list<array<string, mixed>>  $sessions
     * @return array{accepted: list<string>, rejected: list<array{uuid: string, reason: string}>, responses_created: int}
     */
    public function sync(User $user, array $sessions, ?Device $device = null): array
    {
        $accepted = [];
        $rejected = [];
        $created = 0;

        $studies = Study::findMany(collect($sessions)->pluck('study_id')->unique())->keyBy('id');
        $accessibleIds = $user->accessibleStudies()->whereKey($studies->keys())->pluck('id')->flip();

        foreach ($sessions as $payload) {
            $uuid = (string) $payload['uuid'];
            $study = $studies->get($payload['study_id']);

            if ($study === null || ! $accessibleIds->has($study->id)) {
                $rejected[] = ['uuid' => $uuid, 'reason' => 'study_not_accessible'];

                continue;
            }

            $result = DB::transaction(fn () => $this->syncSession($user, $study, $payload, $device));

            if ($result === null) {
                $rejected[] = ['uuid' => $uuid, 'reason' => 'session_conflict'];

                continue;
            }

            $accepted[] = $uuid;
            $created += $result;
        }

        $device?->forceFill(['last_synced_at' => now()])->save();

        return ['accepted' => $accepted, 'rejected' => $rejected, 'responses_created' => $created];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return int|null Number of new answers stored, or null when the uuid belongs to another study.
     */
    private function syncSession(User $user, Study $study, array $payload, ?Device $device): ?int
    {
        $session = QuizSession::where('uuid', $payload['uuid'])->lockForUpdate()->first();

        if ($session !== null && $session->study_id !== $study->id) {
            return null;
        }

        $session ??= QuizSession::create([
            'uuid' => $payload['uuid'],
            'study_id' => $study->id,
            'participant_id' => $this->participantId($study, $payload['participant_id'] ?? null),
            'child_age' => $payload['child_age'] ?? null,
            'quiz_mode' => $payload['quiz_mode'],
            'conducted_by' => $user->id,
            'device_id' => $device?->id,
            'started_at' => $this->timestamp($payload['started_at'] ?? null) ?? now(),
        ]);

        if ($session->completed_at === null && ! empty($payload['completed_at'])) {
            $session->update(['completed_at' => $this->timestamp($payload['completed_at'])]);
        }

        return $this->storeResponses($session, $payload['responses'] ?? []);
    }

    /**
     * @param  list<array<string, mixed>>  $responses
     */
    private function storeResponses(QuizSession $session, array $responses): int
    {
        if ($responses === []) {
            return 0;
        }

        $knownQuestionIds = Question::whereKey(collect($responses)->pluck('question_id')->filter()->unique())
            ->pluck('id')
            ->flip();

        $now = now();

        $rows = collect($responses)->map(fn (array $response) => [
            'client_uuid' => $response['client_uuid'],
            'quiz_session_id' => $session->id,
            'question_id' => $knownQuestionIds->has($response['question_id'] ?? 0) ? $response['question_id'] : null,
            'question_ref' => $response['question_ref'] ?? null,
            'question_version' => $response['question_version'] ?? null,
            'question_text' => $this->clip($response['question_text'] ?? null, 255),
            'category' => $this->clip($response['category'] ?? null, 80),
            'correct_label' => $this->clip($response['correct_label'] ?? null, 100),
            'selected_label' => $this->clip($response['selected_label'] ?? null, 100),
            'selected_number' => $response['selected_number'] ?? null,
            'is_correct' => (bool) $response['is_correct'],
            'attempts' => (int) $response['attempts'],
            'response_time_ms' => (int) $response['response_time_ms'],
            'position' => $response['position'] ?? null,
            'answered_at' => $this->timestamp($response['answered_at'] ?? null) ?? $now,
            'first_tap_ms' => $response['first_tap_ms'] ?? null,
            'prompt_ended_ms' => $response['prompt_ended_ms'] ?? null,
            'replay_count' => (int) ($response['replay_count'] ?? 0),
            'answered_during_prompt' => (bool) ($response['answered_during_prompt'] ?? false),
            'option_order' => isset($response['option_order']) ? json_encode(array_values($response['option_order']), JSON_UNESCAPED_UNICODE) : null,
            'selected_position' => $response['selected_position'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        $alreadyStored = QuizResponse::whereIn('client_uuid', array_column($rows, 'client_uuid'))->pluck('client_uuid')->flip();
        $created = QuizResponse::insertOrIgnore($rows);

        $this->storeTaps(collect($responses)->reject(fn (array $response) => $alreadyStored->has($response['client_uuid'])));

        return $created;
    }

    /**
     * Store the tap sequence for newly created answers only, so re-sends never duplicate taps.
     *
     * @param  Collection<int, array<string, mixed>>  $newResponses
     */
    private function storeTaps(Collection $newResponses): void
    {
        $withTaps = $newResponses->filter(fn (array $response) => ! empty($response['taps']));

        if ($withTaps->isEmpty()) {
            return;
        }

        $ids = QuizResponse::whereIn('client_uuid', $withTaps->pluck('client_uuid'))->pluck('id', 'client_uuid');

        $rows = $withTaps->flatMap(fn (array $response) => collect($response['taps'])->values()->map(fn (array $tap, int $index) => [
            'quiz_response_id' => $ids[$response['client_uuid']] ?? null,
            'sequence' => $index + 1,
            'selected_label' => $this->clip($tap['selected_label'] ?? null, 100),
            'selected_number' => $tap['selected_number'] ?? null,
            'screen_position' => $tap['screen_position'] ?? null,
            'is_correct' => (bool) $tap['is_correct'],
            'elapsed_ms' => (int) $tap['elapsed_ms'],
            'during_prompt' => (bool) ($tap['during_prompt'] ?? false),
        ]))->filter(fn (array $row) => $row['quiz_response_id'] !== null)->values();

        $rows->chunk(500)->each(fn (Collection $chunk) => QuizTap::insert($chunk->all()));
    }

    private function participantId(Study $study, mixed $participantId): ?int
    {
        if ($participantId === null) {
            return null;
        }

        return Participant::where('study_id', $study->id)->whereKey($participantId)->value('id');
    }

    /**
     * Parse a client timestamp; clocks on classroom tablets can be wrong, so future times are clamped to now.
     */
    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        $time = CarbonImmutable::parse($value)->utc();

        return $time->isFuture() ? CarbonImmutable::now() : $time;
    }

    private function clip(?string $value, int $max): ?string
    {
        return $value === null ? null : Str::limit($value, $max, '');
    }
}
