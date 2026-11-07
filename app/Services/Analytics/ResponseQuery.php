<?php

namespace App\Services\Analytics;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one place that turns analytics filters into a query over answers, so the dashboard
 * and the exports always select exactly the same rows.
 *
 * Aliases: `r` = quiz_responses, `s` = quiz_sessions.
 */
final class ResponseQuery
{
    /**
     * @param  Collection<int, int>|list<int>  $studyIds  studies the caller has already checked access to
     */
    public static function make(Collection|array $studyIds, AnalyticsFilters $filters): Builder
    {
        return DB::table('quiz_responses as r')
            ->join('quiz_sessions as s', 's.id', '=', 'r.quiz_session_id')
            ->whereIn('s.study_id', $studyIds)
            ->when($filters->studyId, fn ($q, $id) => $q->where('s.study_id', $id))
            ->when($filters->participantId, fn ($q, $id) => $q->where('s.participant_id', $id))
            ->when($filters->sessionUuid, fn ($q, $uuid) => $q->where('s.uuid', $uuid))
            ->when($filters->age, fn ($q, $age) => $q->where('s.child_age', $age))
            ->when($filters->mode, fn ($q, $mode) => $q->where('s.quiz_mode', $mode))
            ->when($filters->category, fn ($q, $category) => $q->where('r.category', $category))
            ->when($filters->from, fn ($q, $from) => $q->where('r.answered_at', '>=', $from))
            ->when($filters->to, fn ($q, $to) => $q->where('r.answered_at', '<=', $to));
    }

    /** Chronological order: session start, then question position within the session. */
    public static function ordered(Builder $query): Builder
    {
        return $query
            ->orderBy('s.started_at')
            ->orderBy('s.id')
            ->orderBy('r.position')
            ->orderBy('r.id');
    }
}
