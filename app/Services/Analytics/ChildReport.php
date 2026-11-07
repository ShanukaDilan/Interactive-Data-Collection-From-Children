<?php

namespace App\Services\Analytics;

use App\Models\Participant;
use App\Models\QuizSession;

/**
 * Everything shown on a child's report — used by the staff report page and the parent share link.
 */
final class ChildReport
{
    /**
     * @param  bool  $withPeers  Include anonymous same-age averages (researcher view only).
     * @return array<string, mixed>
     */
    public static function build(Participant $participant, bool $withPeers = false): array
    {
        $studyIds = [$participant->study_id];
        $answers = AnswerDataset::forStudies($studyIds, new AnalyticsFilters(participantId: $participant->id))->answers;

        $peerAnswers = null;
        $peerAge = $participant->age ?? $answers->pluck('childAge')->filter()->mode()[0] ?? null;

        if ($withPeers && $peerAge !== null) {
            $peerAnswers = AnswerDataset::forStudies($studyIds, new AnalyticsFilters(age: $peerAge))
                ->answers
                ->reject(fn (Answer $a) => $a->participantId === $participant->id)
                ->values();
        }

        $profile = ChildProfile::build($answers, $peerAnswers);
        $categories = collect($profile['categories'])->reject(fn (array $row) => $row['label'] === ChildProfile::NOT_ENOUGH);

        $radar = [
            'labels' => $categories->pluck('category')->all(),
            'child' => $categories->map(fn (array $row) => round(($row['first_try_rate'] ?? 0) * 100))->values()->all(),
            'peers' => $profile['peers_available']
                ? $categories->map(fn (array $row) => $row['peer_rate'] === null ? null : round($row['peer_rate'] * 100))->values()->all()
                : null,
        ];

        $sessionUuids = QuizSession::whereIn('id', collect($profile['sessions'])->pluck('session_id'))->pluck('uuid', 'id');

        return [
            'participant' => $participant,
            'profile' => $profile,
            'insights' => (new PatternDetector)->forChild($answers),
            'radar' => $radar,
            'trend' => [
                'labels' => collect($profile['sessions'])->map(fn (array $s) => $s['started_at']?->format('j M') ?? '—')->all(),
                'values' => collect($profile['sessions'])->map(fn (array $s) => $s['first_try_rate'] === null ? null : round($s['first_try_rate'] * 100))->all(),
            ],
            'session_uuids' => $sessionUuids,
            'peer_age' => $peerAge,
            'strengths' => $categories->where('label', ChildProfile::STRENGTH)->pluck('category')->values()->all(),
            'practice' => $categories->where('label', ChildProfile::PRACTICE)->pluck('category')->values()->all(),
        ];
    }
}
