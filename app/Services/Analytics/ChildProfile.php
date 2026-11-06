<?php

namespace App\Services\Analytics;

use Illuminate\Support\Collection;

/**
 * A child's relative strengths: each category compared with the child's OWN average
 * (and, for researchers, with anonymous same-age peers in the same study).
 */
final class ChildProfile
{
    public const STRENGTH = 'strength';

    public const DEVELOPING = 'developing';

    public const PRACTICE = 'practice';

    public const NOT_ENOUGH = 'not_enough';

    /**
     * @param  Collection<int, Answer>  $childAnswers
     * @param  Collection<int, Answer>|null  $peerAnswers  Same study + age, other children (researcher view only).
     * @return array{overall: array<string, mixed>, categories: list<array<string, mixed>>, sessions: list<array<string, mixed>>, peers_available: bool, peer_children: int}
     */
    public static function build(Collection $childAnswers, ?Collection $peerAnswers = null): array
    {
        $minAnswers = (int) config('quiz.analysis.min_answers_per_category');
        $delta = (float) config('quiz.analysis.strength_delta');
        $overall = Metrics::summarize($childAnswers);
        $ownRate = $overall['first_try_rate'];

        $peerChildren = $peerAnswers?->map(fn (Answer $a) => $a->childKey())->unique()->count() ?? 0;
        $peersAvailable = $peerAnswers !== null && $peerChildren >= (int) config('quiz.analysis.min_peer_children');
        $peerByCategory = $peersAvailable ? Metrics::breakdown($peerAnswers, fn (Answer $a) => $a->category) : collect();

        $categories = Metrics::breakdown($childAnswers, fn (Answer $a) => $a->category)
            ->map(function (array $stats, string $category) use ($ownRate, $minAnswers, $delta, $peerByCategory): array {
                $rate = $stats['first_try_rate'];
                $difference = ($rate !== null && $ownRate !== null) ? $rate - $ownRate : null;

                // A "strength" must beat the child's own average AND be genuinely good, so a child
                // averaging 15% is not told 33% is a strength.
                $label = match (true) {
                    $stats['answers'] < $minAnswers => self::NOT_ENOUGH,
                    $difference >= $delta && $rate >= (float) config('quiz.analysis.strength_min_rate') => self::STRENGTH,
                    $difference <= -$delta => self::PRACTICE,
                    default => self::DEVELOPING,
                };

                return [
                    'category' => $category,
                    'label' => $label,
                    'difference' => $difference,
                    'peer_rate' => $peerByCategory->get($category)['first_try_rate'] ?? null,
                    'peer_median_rt_ms' => $peerByCategory->get($category)['median_rt_ms'] ?? null,
                    ...$stats,
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['label'] === self::NOT_ENOUGH ? 0 : 1, $row['first_try_rate'] ?? -1])
            ->values()
            ->all();

        $sessions = $childAnswers
            ->groupBy('sessionId')
            ->map(fn (Collection $answers) => [
                'session_id' => $answers->first()->sessionId,
                'started_at' => $answers->first()->sessionStartedAt,
                'mode' => $answers->first()->mode,
                ...Metrics::summarize($answers),
            ])
            ->sortBy(fn (array $session) => $session['started_at']?->getTimestamp() ?? 0)
            ->values()
            ->all();

        return [
            'overall' => $overall,
            'categories' => $categories,
            'sessions' => $sessions,
            'peers_available' => $peersAvailable,
            'peer_children' => $peerChildren,
            'peer_overall' => $peersAvailable ? Metrics::summarize($peerAnswers) : null,
        ];
    }

    public static function labelText(string $label): string
    {
        return match ($label) {
            self::STRENGTH => 'Strength',
            self::DEVELOPING => 'Developing',
            self::PRACTICE => 'Needs practice',
            default => 'Not enough answers yet',
        };
    }
}
