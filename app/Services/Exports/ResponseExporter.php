<?php

namespace App\Services\Exports;

use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\Answer;
use App\Services\Analytics\ResponseQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Streams answers and taps for export, row by row, with the same filters as the analytics
 * dashboard. Children appear only by pseudonymous code — names are never selected.
 */
final class ResponseExporter
{
    private const CHUNK = 1000;

    /**
     * @param  Collection<int, int>|list<int>  $studyIds  studies the caller has already checked access to
     */
    public function __construct(
        private readonly Collection|array $studyIds,
        private readonly AnalyticsFilters $filters,
    ) {}

    /** @return LazyCollection<int, array<string, mixed>> keyed exactly as {@see Codebook::responses()} */
    public function responses(): LazyCollection
    {
        return $this->responseQuery()->lazy(self::CHUNK)->map(function (object $row): array {
            $options = $row->option_order !== null ? json_decode($row->option_order, true) : null;
            $firstTap = self::int($row->first_tap_ms) ?? (int) $row->response_time_ms;
            $promptEnded = self::int($row->prompt_ended_ms);
            $reaction = $promptEnded !== null && $firstTap >= $promptEnded
                ? $firstTap - $promptEnded
                : null;

            return self::clean([
                'response_id' => (int) $row->id,
                'study' => $row->study_slug,
                'session_uuid' => $row->session_uuid,
                'legacy_session_id' => $row->legacy_session_id,
                'child_id' => $row->participant_code ?? $row->legacy_child_id,
                'child_age' => self::int($row->child_age),
                'quiz_mode' => $row->quiz_mode,
                'session_started_at' => $row->session_started_at,
                'session_completed_at' => $row->session_completed_at,
                'question_position' => self::position($row->position),
                'question_id' => self::int($row->question_id),
                'question_ref' => $row->question_ref,
                'question_version' => self::int($row->question_version),
                'question_text' => $row->question_text,
                'category' => $row->category,
                'correct_label' => $row->correct_label,
                'selected_label' => $row->selected_label ?? $row->selected_number,
                'selected_number' => self::int($row->selected_number),
                'is_correct' => (int) (bool) $row->is_correct,
                'first_try_correct' => (int) self::firstTryCorrect($row),
                'attempts' => (int) $row->attempts,
                'response_time_ms' => (int) $row->response_time_ms,
                'first_tap_ms' => self::int($row->first_tap_ms),
                'prompt_ended_ms' => $promptEnded,
                'reaction_ms' => $reaction,
                'answered_during_prompt' => (int) (bool) $row->answered_during_prompt,
                'replay_count' => (int) $row->replay_count,
                'option_count' => is_array($options) ? count($options) : null,
                'option_order' => is_array($options) ? implode(' | ', $options) : null,
                'selected_position' => self::position($row->selected_position),
                'answered_at' => $row->answered_at,
            ]);
        });
    }

    /** @return LazyCollection<int, array<string, mixed>> keyed exactly as {@see Codebook::taps()} */
    public function taps(): LazyCollection
    {
        $query = $this->withStudyAndChild(ResponseQuery::make($this->studyIds, $this->filters))
            ->join('quiz_taps as t', 't.quiz_response_id', '=', 'r.id')
            ->select([
                'r.id', 'r.position', 'r.question_id', 'r.category', 'r.correct_label',
                'st.slug as study_slug', 's.uuid as session_uuid', 's.child_age', 's.quiz_mode',
                'p.code as participant_code', 's.legacy_child_id',
                't.sequence', 't.selected_label as tap_label', 't.selected_number as tap_number',
                't.screen_position', 't.is_correct as tap_correct', 't.elapsed_ms', 't.during_prompt',
            ]);

        return ResponseQuery::ordered($query)->orderBy('t.sequence')->orderBy('t.id')
            ->lazy(self::CHUNK)
            ->map(fn (object $row): array => self::clean([
                'response_id' => (int) $row->id,
                'study' => $row->study_slug,
                'session_uuid' => $row->session_uuid,
                'child_id' => $row->participant_code ?? $row->legacy_child_id,
                'child_age' => self::int($row->child_age),
                'quiz_mode' => $row->quiz_mode,
                'question_position' => self::position($row->position),
                'question_id' => self::int($row->question_id),
                'category' => $row->category,
                'correct_label' => $row->correct_label,
                'tap_sequence' => (int) $row->sequence,
                'selected_label' => $row->tap_label ?? $row->tap_number,
                'selected_number' => self::int($row->tap_number),
                'screen_position' => self::position($row->screen_position),
                'is_correct' => (int) (bool) $row->tap_correct,
                'elapsed_ms' => (int) $row->elapsed_ms,
                'during_prompt' => (int) (bool) $row->during_prompt,
            ]));
    }

    /**
     * Rows in the original app's CSV layout ({@see Codebook::LEGACY_HEADER}).
     *
     * @return LazyCollection<int, list<mixed>>
     */
    public function legacy(): LazyCollection
    {
        return $this->responseQuery()->lazy(self::CHUNK)->map(fn (object $row): array => array_values(self::clean([
            $row->legacy_session_id ?? $row->session_uuid,
            $row->participant_code ?? $row->legacy_child_id ?? '',
            $row->child_age ?? '',
            $row->quiz_mode,
            $row->question_ref ?? $row->question_id ?? '',
            $row->question_text ?? '',
            $row->category ?? '',
            $row->correct_label ?? '',
            $row->selected_label ?? $row->selected_number ?? '',
            $row->is_correct ? 'Yes' : 'No',
            (int) $row->attempts,
            (int) $row->response_time_ms,
            $row->answered_at ?? '',
        ])));
    }

    private function responseQuery(): Builder
    {
        $query = $this->withStudyAndChild(ResponseQuery::make($this->studyIds, $this->filters))
            ->select([
                'r.*',
                'st.slug as study_slug', 's.uuid as session_uuid', 's.legacy_session_id', 's.legacy_child_id',
                's.child_age', 's.quiz_mode', 's.started_at as session_started_at', 's.completed_at as session_completed_at',
                'p.code as participant_code',
            ]);

        return ResponseQuery::ordered($query);
    }

    private function withStudyAndChild(Builder $query): Builder
    {
        return $query
            ->join('studies as st', 'st.id', '=', 's.study_id')
            ->leftJoin('participants as p', 'p.id', '=', 's.participant_id');
    }

    private static function int(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /** Stored 0-based; exported 1-based so "1" means the first card or question. */
    private static function position(mixed $value): ?int
    {
        $value = self::int($value);

        return $value === null ? null : $value + 1;
    }

    /** Same rule as the dashboard ({@see Answer::firstTryCorrect()}). */
    private static function firstTryCorrect(object $row): bool
    {
        return $row->quiz_mode === 'free'
            ? (bool) $row->is_correct
            : ((bool) $row->is_correct && (int) $row->attempts === 1);
    }

    /**
     * Neutralise spreadsheet formulas: text typed into question/answer fields that starts with
     * = + - @ would otherwise run as a formula when the file is opened in Excel/Sheets.
     *
     * @param  array<array-key, mixed>  $row
     * @return array<array-key, mixed>
     */
    public static function clean(array $row): array
    {
        return array_map(function (mixed $value): mixed {
            if (is_string($value) && $value !== '' && ! is_numeric($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
                return "'".$value;
            }

            return $value;
        }, $row);
    }
}
