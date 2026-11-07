<?php

namespace App\Services\Analytics;

use Illuminate\Support\Collection;

/**
 * Summary statistics over a set of answers. Response times use medians: children's
 * times are heavily skewed by the occasional long pause, so means mislead.
 */
final class Metrics
{
    /**
     * @param  Collection<int, Answer>  $answers
     * @return array{answers: int, children: int, sessions: int, first_try_rate: ?float, mean_attempts: ?float, median_rt_ms: ?int, rt_q1_ms: ?int, rt_q3_ms: ?int, median_reaction_ms: ?int, replay_rate: ?float}
     */
    public static function summarize(Collection $answers): array
    {
        $count = $answers->count();
        $times = $answers->map(fn (Answer $a) => $a->firstTapTime());
        $reactions = $answers->map(fn (Answer $a) => $a->reactionTimeMs())->filter(fn ($v) => $v !== null);
        $withTapData = $answers->filter(fn (Answer $a) => $a->hasTaps());

        return [
            'answers' => $count,
            'children' => $answers->map(fn (Answer $a) => $a->childKey())->unique()->count(),
            'sessions' => $answers->pluck('sessionId')->unique()->count(),
            'first_try_rate' => $count > 0 ? (float) ($answers->filter(fn (Answer $a) => $a->firstTryCorrect())->count() / $count) : null,
            'mean_attempts' => $count > 0 ? round($answers->avg('attempts'), 2) : null,
            'median_rt_ms' => self::quantile($times, 0.5),
            'rt_q1_ms' => self::quantile($times, 0.25),
            'rt_q3_ms' => self::quantile($times, 0.75),
            'median_reaction_ms' => self::quantile($reactions, 0.5),
            'replay_rate' => $withTapData->isNotEmpty()
                ? (float) ($withTapData->filter(fn (Answer $a) => $a->replayCount > 0)->count() / $withTapData->count())
                : null,
        ];
    }

    /**
     * @param  Collection<int, Answer>  $answers
     * @param  callable(Answer): (string|int|null)  $key
     * @return Collection<string, array<string, mixed>>
     */
    public static function breakdown(Collection $answers, callable $key): Collection
    {
        return $answers
            ->groupBy(fn (Answer $a) => (string) ($key($a) ?? '—'))
            ->map(fn (Collection $group) => self::summarize($group))
            ->sortKeys();
    }

    /**
     * Linear-interpolated quantile (same method as Excel PERCENTILE.INC).
     *
     * @param  Collection<int, int|float>  $values
     */
    public static function quantile(Collection $values, float $q): ?int
    {
        $sorted = $values->filter(fn ($v) => $v !== null)->sort()->values();
        $n = $sorted->count();

        if ($n === 0) {
            return null;
        }

        $index = ($n - 1) * $q;
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        $value = $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($index - $lower);

        return (int) round($value);
    }

    public static function percent(?float $rate): string
    {
        return $rate === null ? '—' : round($rate * 100).'%';
    }

    public static function seconds(?int $ms): string
    {
        return $ms === null ? '—' : number_format($ms / 1000, 1).' s';
    }
}
