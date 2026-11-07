<?php

namespace App\Services\Analytics;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuizTap;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Loads answers (joined with their session and question media flags, plus taps) into
 * memory for analysis. Always restricted to studies the user may access.
 */
final class AnswerDataset
{
    /** Safety cap so a single page never loads an unbounded dataset. */
    public const MAX_ANSWERS = 50000;

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function __construct(public readonly Collection $answers, public readonly bool $truncated = false) {}

    public static function load(User $user, AnalyticsFilters $filters): self
    {
        return self::forStudies($user->accessibleStudies()->pluck('id'), $filters);
    }

    /**
     * Load answers restricted to the given studies (callers must have checked access).
     *
     * @param  Collection<int, int>|list<int>  $studyIds
     */
    public static function forStudies(Collection|array $studyIds, AnalyticsFilters $filters): self
    {
        $rows = ResponseQuery::ordered(ResponseQuery::make($studyIds, $filters))
            ->limit(self::MAX_ANSWERS + 1)
            ->get([
                'r.*',
                's.study_id', 's.participant_id', 's.child_age', 's.quiz_mode', 's.started_at as session_started_at',
            ]);

        $truncated = $rows->count() > self::MAX_ANSWERS;
        $rows = $rows->take(self::MAX_ANSWERS);

        $taps = self::loadTaps($rows->pluck('id'));
        $media = self::loadQuestionMedia($rows->pluck('question_id')->filter()->unique());

        $answers = $rows->map(function (object $row) use ($taps, $media): Answer {
            $questionMedia = $media->get($row->question_id, []);

            return new Answer(
                id: (int) $row->id,
                sessionId: (int) $row->quiz_session_id,
                studyId: (int) $row->study_id,
                participantId: $row->participant_id !== null ? (int) $row->participant_id : null,
                childAge: $row->child_age !== null ? (int) $row->child_age : null,
                mode: (string) $row->quiz_mode,
                sessionStartedAt: $row->session_started_at ? CarbonImmutable::parse($row->session_started_at) : null,
                questionId: $row->question_id !== null ? (int) $row->question_id : null,
                questionKey: $row->question_id !== null ? 'id:'.$row->question_id : 'ref:'.($row->question_ref ?? $row->question_text),
                questionText: $row->question_text,
                category: $row->category,
                correctLabel: $row->correct_label,
                selectedLabel: $row->selected_label,
                selectedNumber: $row->selected_number !== null ? (int) $row->selected_number : null,
                isCorrect: (bool) $row->is_correct,
                attempts: (int) $row->attempts,
                responseTimeMs: (int) $row->response_time_ms,
                firstTapMs: $row->first_tap_ms !== null ? (int) $row->first_tap_ms : null,
                promptEndedMs: $row->prompt_ended_ms !== null ? (int) $row->prompt_ended_ms : null,
                replayCount: (int) $row->replay_count,
                answeredDuringPrompt: (bool) $row->answered_during_prompt,
                optionOrder: $row->option_order !== null ? json_decode($row->option_order, true) : null,
                selectedPosition: $row->selected_position !== null ? (int) $row->selected_position : null,
                position: $row->position !== null ? (int) $row->position : null,
                taps: $taps->get($row->id, []),
                hasRecordedAudio: $questionMedia['audio'] ?? false,
                hasImageOptions: $questionMedia['image_options'] ?? false,
                hasQuestionVisual: $questionMedia['visual'] ?? false,
                isNumberInput: $questionMedia['number_input'] ?? ($row->selected_number !== null),
            );
        })->values();

        return new self($answers, $truncated);
    }

    /**
     * @param  Collection<int, mixed>  $responseIds
     * @return Collection<int, list<array<string, mixed>>>
     */
    private static function loadTaps(Collection $responseIds): Collection
    {
        $grouped = collect();

        $responseIds->chunk(1000)->each(function (Collection $chunk) use ($grouped): void {
            QuizTap::query()
                ->whereIn('quiz_response_id', $chunk)
                ->orderBy('quiz_response_id')
                ->orderBy('sequence')
                ->get()
                ->each(function (QuizTap $tap) use ($grouped): void {
                    $list = $grouped->get($tap->quiz_response_id, []);
                    $list[] = [
                        'label' => $tap->selected_label,
                        'number' => $tap->selected_number,
                        'position' => $tap->screen_position,
                        'correct' => $tap->is_correct,
                        'elapsed_ms' => $tap->elapsed_ms,
                        'during_prompt' => $tap->during_prompt,
                    ];
                    $grouped->put($tap->quiz_response_id, $list);
                });
        });

        return $grouped;
    }

    /**
     * @param  Collection<int, mixed>  $questionIds
     * @return Collection<int, array{audio: bool, image_options: bool, visual: bool, number_input: bool}>
     */
    private static function loadQuestionMedia(Collection $questionIds): Collection
    {
        if ($questionIds->isEmpty()) {
            return collect();
        }

        return Question::with('options:id,question_id,image_path')
            ->findMany($questionIds, ['id', 'type', 'audio_path', 'image_path', 'question_emoji'])
            ->mapWithKeys(fn (Question $question) => [$question->id => [
                'audio' => filled($question->audio_path),
                'image_options' => $question->options->contains(fn ($option) => filled($option->image_path)),
                'visual' => filled($question->image_path) || filled($question->question_emoji),
                'number_input' => $question->type === QuestionType::NumberInput,
            ]]);
    }

    public function isEmpty(): bool
    {
        return $this->answers->isEmpty();
    }

    /**
     * @param  callable(Answer): bool  $callback
     */
    public function filter(callable $callback): self
    {
        return new self($this->answers->filter($callback)->values(), $this->truncated);
    }

    /** @return Collection<string, Collection<int, Answer>> */
    public function byChild(): Collection
    {
        return $this->answers->groupBy(fn (Answer $answer) => $answer->childKey());
    }
}
