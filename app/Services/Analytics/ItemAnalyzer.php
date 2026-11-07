<?php

namespace App\Services\Analytics;

use Illuminate\Support\Collection;

/**
 * Question quality for researchers (classical item analysis):
 * difficulty, discrimination, and which wrong answers attract children.
 */
final class ItemAnalyzer
{
    /**
     * @param  Collection<int, Answer>  $answers
     * @return list<array<string, mixed>>
     */
    public function analyze(Collection $answers): array
    {
        [$strongChildren, $weakChildren] = $this->splitChildrenByScore($answers);

        return $answers
            ->groupBy('questionKey')
            ->map(function (Collection $items) use ($strongChildren, $weakChildren): array {
                $first = $items->first();
                $stats = Metrics::summarize($items);

                $strong = $items->filter(fn (Answer $a) => $strongChildren->has($a->childKey()));
                $weak = $items->filter(fn (Answer $a) => $weakChildren->has($a->childKey()));
                $discrimination = ($strong->count() >= 2 && $weak->count() >= 2)
                    ? $this->rate($strong) - $this->rate($weak)
                    : null;

                $distractors = $items
                    ->flatMap(fn (Answer $a) => collect($a->taps)->reject(fn (array $tap) => $tap['correct'])->pluck('label'))
                    ->filter()
                    ->countBy()
                    ->sortDesc();

                $optionLabels = $items->map(fn (Answer $a) => $a->optionOrder ?? [])->flatten()->unique()->reject(fn ($label) => $label === $first->correctLabel);
                $neverChosen = $first->isNumberInput ? collect() : $optionLabels->diff($distractors->keys())->values();

                return [
                    'key' => $first->questionKey,
                    'question_id' => $first->questionId,
                    'text' => $first->questionText,
                    'category' => $first->category,
                    'correct_label' => $first->correctLabel,
                    'difficulty' => $stats['first_try_rate'],
                    'discrimination' => $discrimination,
                    'distractors' => $distractors->take(4)->all(),
                    'never_chosen' => $items->count() >= 10 ? $neverChosen->all() : [],
                    'flags' => $this->flags($stats, $discrimination, $items->count()),
                    ...$stats,
                ];
            })
            ->sortBy('difficulty')
            ->values()
            ->all();
    }

    /**
     * Top and bottom third of children by overall first-try accuracy.
     *
     * @param  Collection<int, Answer>  $answers
     * @return array{0: Collection<string, true>, 1: Collection<string, true>}
     */
    private function splitChildrenByScore(Collection $answers): array
    {
        $scores = $answers->groupBy(fn (Answer $a) => $a->childKey())
            ->filter(fn (Collection $childAnswers) => $childAnswers->count() >= 3)
            ->map(fn (Collection $childAnswers) => $this->rate($childAnswers))
            ->sort();

        $third = intdiv($scores->count(), 3);

        if ($third < 2) {
            return [collect(), collect()];
        }

        return [
            $scores->slice(-$third)->map(fn () => true),
            $scores->take($third)->map(fn () => true),
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return list<string>
     */
    private function flags(array $stats, ?float $discrimination, int $count): array
    {
        if ($count < 5) {
            return ['few answers'];
        }

        $flags = [];
        if ($stats['first_try_rate'] >= (float) config('quiz.analysis.item_too_easy')) {
            $flags[] = 'very easy';
        }
        if ($stats['first_try_rate'] <= (float) config('quiz.analysis.item_too_hard')) {
            $flags[] = 'very hard';
        }
        if ($discrimination !== null && $discrimination < 0.1) {
            $flags[] = 'low discrimination';
        }

        return $flags;
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    private function rate(Collection $answers): float
    {
        return $answers->isEmpty() ? 0.0 : $answers->filter(fn (Answer $a) => $a->firstTryCorrect())->count() / $answers->count();
    }
}
