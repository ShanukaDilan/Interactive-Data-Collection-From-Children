<?php

namespace App\Services\Analytics;

use Illuminate\Support\Collection;

/**
 * Finds behavioural patterns in how a child (or a group) answers.
 *
 * Choice-pattern checks use the FIRST tap of each question: in "Find the Answer" mode the
 * final tap is always the correct card, so only the first tap reflects the child's own choice.
 * Every check requires a minimum amount of evidence before it says anything.
 */
final class PatternDetector
{
    /**
     * Patterns for one child (one participant, or one anonymous session).
     *
     * @param  Collection<int, Answer>  $answers
     * @return list<Insight>
     */
    public function forChild(Collection $answers): array
    {
        return array_values(array_filter([
            $this->positionBias($answers),
            $this->perseveration($answers),
            $this->repeatedWrongChoice($answers),
            $this->rapidGuessing($answers),
            $this->confusionPairs($answers),
            $this->countingErrors($answers),
            $this->replayEffect($answers),
            $this->answeringDuringPrompt($answers),
            $this->recordedAudioEffect($answers),
            $this->pictureCardEffect($answers),
            $this->fatigue($answers),
            $this->longPauses($answers),
            $this->freeChoicePreferences($answers),
        ]));
    }

    /**
     * Patterns across a group: per-child checks are counted ("3 of 12 children…"),
     * group-level effects (sound, pictures, fatigue, confusions) are computed on all answers.
     *
     * @param  Collection<int, Answer>  $answers
     * @return list<Insight>
     */
    public function forGroup(Collection $answers): array
    {
        $children = $answers->groupBy(fn (Answer $a) => $a->childKey());
        $childCount = $children->count();
        $insights = [];

        $perChild = [
            'position_bias' => ['🎯', 'Position bias', 'tend to tap the same spot on the screen regardless of the question'],
            'perseveration' => ['🔁', 'Repeating choices', 'repeat the same choice several questions in a row'],
            'rapid_guessing' => ['⚡', 'Fast tapping', 'often tap very quickly or before the question has finished'],
        ];

        foreach ($perChild as $type => [$icon, $title, $phrase]) {
            $flagged = $children->filter(fn (Collection $childAnswers) => match ($type) {
                'position_bias' => $this->positionBias($childAnswers) !== null,
                'perseveration' => $this->perseveration($childAnswers) !== null,
                'rapid_guessing' => $this->rapidGuessing($childAnswers) !== null,
            })->count();

            if ($flagged > 0) {
                $insights[] = new Insight(
                    type: $type,
                    title: $title,
                    message: "{$flagged} of {$childCount} ".str('child')->plural($childCount)." {$phrase}.",
                    severity: $flagged / max(1, $childCount) >= 0.25 ? Insight::NOTICE : Insight::INFO,
                    icon: $icon,
                    evidence: ['flagged' => $flagged, 'children' => $childCount],
                );
            }
        }

        return array_values(array_filter([
            ...$insights,
            $this->confusionPairs($answers, group: true),
            $this->countingErrors($answers),
            $this->replayEffect($answers),
            $this->answeringDuringPrompt($answers),
            $this->recordedAudioEffect($answers),
            $this->pictureCardEffect($answers),
            $this->fatigue($answers),
        ]));
    }

    // ── Choice patterns ───────────────────────────────────────────

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function positionBias(Collection $answers): ?Insight
    {
        $minChoices = (int) config('quiz.analysis.position_bias_min_choices');
        $threshold = (float) config('quiz.analysis.position_bias_share');

        $choices = $answers
            ->filter(fn (Answer $a) => $a->optionCount() >= 2 && ($a->firstTap()['position'] ?? null) !== null)
            ->groupBy(fn (Answer $a) => $a->optionCount());

        foreach ($choices as $optionCount => $group) {
            if ($group->count() < $minChoices) {
                continue;
            }

            $counts = $group->countBy(fn (Answer $a) => $a->firstTap()['position']);
            $position = $counts->sortDesc()->keys()->first();
            $share = (float) ($counts[$position] / $group->count());
            $expected = 1 / $optionCount;

            if ($share >= max($threshold, $expected + 0.3)) {
                $place = self::positionName((int) $position, (int) $optionCount);

                return new Insight(
                    type: 'position_bias',
                    title: 'Position bias',
                    message: sprintf(
                        'First tapped the %s card in %d%% of %d questions (about %d%% would be expected by chance). The choice may be driven by where cards are rather than what they show.',
                        $place, round($share * 100), $group->count(), round($expected * 100),
                    ),
                    severity: Insight::NOTICE,
                    icon: '🎯',
                    evidence: ['position' => $place, 'share' => $share, 'expected' => $expected, 'choices' => $group->count()],
                );
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function perseveration(Collection $answers): ?Insight
    {
        $streakNeeded = (int) config('quiz.analysis.perseveration_streak');
        $longest = 0;
        $longestKind = null;

        foreach ($answers->groupBy('sessionId') as $session) {
            $ordered = $session->sortBy('position')->values();
            foreach (['position', 'label'] as $kind) {
                $streak = 1;
                for ($i = 1; $i < $ordered->count(); $i++) {
                    $previous = $ordered[$i - 1]->firstTap()[$kind] ?? null;
                    $current = $ordered[$i]->firstTap()[$kind] ?? null;
                    $streak = ($previous !== null && $previous === $current) ? $streak + 1 : 1;
                    if ($streak > $longest) {
                        $longest = $streak;
                        $longestKind = $kind;
                    }
                }
            }
        }

        if ($longest < $streakNeeded) {
            return null;
        }

        $what = $longestKind === 'position' ? 'the card in the same place' : 'the same answer';

        return new Insight(
            type: 'perseveration',
            title: 'Repeating choices',
            message: "First chose {$what} {$longest} questions in a row. This can mean the child is repeating a habit rather than listening to each new question.",
            severity: Insight::NOTICE,
            icon: '🔁',
            evidence: ['streak' => $longest, 'kind' => $longestKind],
        );
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function repeatedWrongChoice(Collection $answers): ?Insight
    {
        $repeats = $answers->filter(function (Answer $a): bool {
            $wrong = collect($a->taps)->reject(fn (array $tap) => $tap['correct'])->pluck('label');

            return $wrong->count() !== $wrong->unique()->count();
        });

        if ($repeats->count() < 2) {
            return null;
        }

        return new Insight(
            type: 'repeated_wrong',
            title: 'Same wrong answer again',
            message: "On {$repeats->count()} questions the child tapped the same wrong card again after “Try again”. They may not be using the feedback yet.",
            severity: Insight::INFO,
            icon: '↩️',
            evidence: ['questions' => $repeats->count()],
        );
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function rapidGuessing(Collection $answers): ?Insight
    {
        $minAnswers = (int) config('quiz.analysis.position_bias_min_choices');
        $rapidMs = (int) config('quiz.analysis.rapid_tap_ms');
        $withTaps = $answers->filter(fn (Answer $a) => $a->hasTaps());

        if ($withTaps->count() < $minAnswers) {
            return null;
        }

        [$rapid, $considered] = $withTaps->partition(
            fn (Answer $a) => $a->firstTapTime() < $rapidMs || ($a->firstTap()['during_prompt'] ?? false)
        );
        $share = (float) ($rapid->count() / $withTaps->count());

        if ($share < (float) config('quiz.analysis.rapid_share')) {
            return null;
        }

        $message = sprintf('%d%% of first taps came very fast (under %.1f s) or before the question had finished playing.', round($share * 100), $rapidMs / 1000);
        $evidence = ['share' => $share, 'answers' => $withTaps->count()];

        if ($rapid->count() >= $this->minGroup() && $considered->count() >= $this->minGroup()) {
            $rapidRate = $this->firstTryRate($rapid);
            $consideredRate = $this->firstTryRate($considered);
            $message .= sprintf(' Fast taps were right %s of the time vs %s when the child waited.', Metrics::percent($rapidRate), Metrics::percent($consideredRate));
            $evidence += ['rapid_rate' => $rapidRate, 'considered_rate' => $consideredRate];

            if ($consideredRate - $rapidRate >= 0.2) {
                $message .= ' Encouraging the child to listen to the whole question first may help.';
            }
        }

        return new Insight('rapid_guessing', 'Fast tapping', $message, Insight::NOTICE, '⚡', $evidence);
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function confusionPairs(Collection $answers, bool $group = false): ?Insight
    {
        $pairs = $answers
            ->filter(fn (Answer $a) => ! $a->isNumberInput && $a->correctLabel !== null)
            ->flatMap(fn (Answer $a) => collect($a->taps)
                ->reject(fn (array $tap) => $tap['correct'] || $tap['label'] === null)
                ->map(fn (array $tap) => "{$a->correctLabel}→{$tap['label']}"))
            ->countBy()
            ->filter(fn (int $count) => $count >= ($group ? 3 : 2))
            ->sortDesc()
            ->take(3);

        if ($pairs->isEmpty()) {
            return null;
        }

        $list = $pairs->map(function (int $count, string $pair): string {
            [$correct, $chosen] = explode('→', $pair, 2);

            return "“{$chosen}” instead of “{$correct}” ({$count}×)";
        })->implode(', ');

        return new Insight(
            type: 'confusion_pairs',
            title: 'Common mix-ups',
            message: "Most frequent wrong picks: {$list}. Similar-looking or similar-sounding pairs may need extra practice.",
            severity: Insight::INFO,
            icon: '🔀',
            evidence: ['pairs' => $pairs->all()],
        );
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function countingErrors(Collection $answers): ?Insight
    {
        $errors = $answers
            ->filter(fn (Answer $a) => $a->isNumberInput && is_numeric($a->correctLabel))
            ->flatMap(fn (Answer $a) => collect($a->taps)
                ->reject(fn (array $tap) => $tap['correct'] || $tap['number'] === null)
                ->map(fn (array $tap) => abs($tap['number'] - (int) $a->correctLabel)));

        if ($errors->count() < $this->minGroup()) {
            return null;
        }

        $offByOne = $errors->filter(fn (int $diff) => $diff === 1)->count();
        $share = (float) ($offByOne / $errors->count());

        $message = $share >= 0.5
            ? sprintf('%d of %d counting mistakes were off by just one. The child likely knows how to count but may skip or double-count an item; pointing at each item while counting can help.', $offByOne, $errors->count())
            : sprintf('Only %d of %d counting mistakes were off by one; most were further away, which suggests guessing rather than a small counting slip.', $offByOne, $errors->count());

        return new Insight('counting_errors', 'Counting mistakes', $message, Insight::INFO, '🔢', ['off_by_one' => $offByOne, 'errors' => $errors->count()]);
    }

    // ── Sound & object interaction ────────────────────────────────

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function replayEffect(Collection $answers): ?Insight
    {
        $withTaps = $answers->filter(fn (Answer $a) => $a->hasTaps());
        [$replayed, $notReplayed] = $withTaps->partition(fn (Answer $a) => $a->replayCount > 0);

        if ($replayed->count() < $this->minGroup()) {
            return null;
        }

        $share = (float) ($replayed->count() / max(1, $withTaps->count()));
        $message = sprintf('Pressed 🔊 to hear the question again on %d%% of questions.', round($share * 100));
        $evidence = ['share' => $share];

        if ($notReplayed->count() >= $this->minGroup()) {
            $replayRate = $this->firstTryRate($replayed);
            $otherRate = $this->firstTryRate($notReplayed);
            $evidence += ['replayed_rate' => $replayRate, 'other_rate' => $otherRate];
            $message .= sprintf(' Right first time on %s of replayed questions vs %s otherwise', Metrics::percent($replayRate), Metrics::percent($otherRate));
            $message .= $replayRate < $otherRate - 0.15
                ? ' — replays mostly happen on questions the child finds hard to understand.'
                : ($replayRate > $otherRate + 0.15 ? ' — listening again seems to help.' : '.');
        }

        return new Insight('replay_effect', 'Listening again', $message, Insight::INFO, '🔊', $evidence);
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function answeringDuringPrompt(Collection $answers): ?Insight
    {
        $withTaps = $answers->filter(fn (Answer $a) => $a->hasTaps());
        if ($withTaps->count() < (int) config('quiz.analysis.position_bias_min_choices')) {
            return null;
        }

        $early = $withTaps->filter(fn (Answer $a) => $a->firstTap()['during_prompt']);
        $share = (float) ($early->count() / $withTaps->count());

        if ($share < 0.3) {
            return null;
        }

        return new Insight(
            type: 'answered_during_prompt',
            title: 'Answering before the question ends',
            message: sprintf('Tapped before the spoken question had finished on %d%% of questions. The child may be answering from the picture or the first word only.', round($share * 100)),
            severity: Insight::INFO,
            icon: '🗣️',
            evidence: ['share' => $share],
        );
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function recordedAudioEffect(Collection $answers): ?Insight
    {
        return $this->compareGroups(
            $answers,
            fn (Answer $a) => $a->hasRecordedAudio,
            type: 'recorded_audio',
            title: 'Recorded voice vs computer voice',
            icon: '🎙️',
            describe: fn (string $a, string $b) => "Right first time on {$a} of questions with a recorded voice vs {$b} with the computer voice.",
        );
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function pictureCardEffect(Collection $answers): ?Insight
    {
        return $this->compareGroups(
            $answers->filter(fn (Answer $a) => ! $a->isNumberInput),
            fn (Answer $a) => $a->hasImageOptions,
            type: 'picture_cards',
            title: 'Photos vs emoji',
            icon: '🖼️',
            describe: fn (string $a, string $b) => "Right first time on {$a} of questions with photo cards vs {$b} with emoji cards.",
        );
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function fatigue(Collection $answers): ?Insight
    {
        $early = collect();
        $late = collect();

        foreach ($answers->groupBy('sessionId') as $session) {
            $ordered = $session->sortBy('position')->values();
            $half = intdiv($ordered->count(), 2);
            if ($half < 2) {
                continue;
            }
            $early = $early->merge($ordered->take($half));
            $late = $late->merge($ordered->slice($ordered->count() - $half));
        }

        $minimum = 2 * $this->minGroup();
        if ($early->count() < $minimum || $late->count() < $minimum) {
            return null;
        }

        $earlyRate = $this->firstTryRate($early);
        $lateRate = $this->firstTryRate($late);
        $earlyRt = Metrics::quantile($early->map(fn (Answer $a) => $a->firstTapTime()), 0.5);
        $lateRt = Metrics::quantile($late->map(fn (Answer $a) => $a->firstTapTime()), 0.5);
        $drop = (float) config('quiz.analysis.fatigue_drop');
        $evidence = compact('earlyRate', 'lateRate', 'earlyRt', 'lateRt');

        if ($earlyRate - $lateRate >= $drop) {
            return new Insight(
                'fatigue', 'Tiring towards the end',
                sprintf('Right first time on %s of questions in the first half of a session but %s in the second half. Shorter sessions or a break may help.', Metrics::percent($earlyRate), Metrics::percent($lateRate)),
                Insight::NOTICE, '😴', $evidence,
            );
        }

        if ($lateRate - $earlyRate >= $drop) {
            return new Insight(
                'warm_up', 'Warming up',
                sprintf('Accuracy rose from %s in the first half of a session to %s in the second half — the child gets more confident as they go.', Metrics::percent($earlyRate), Metrics::percent($lateRate)),
                Insight::POSITIVE, '🌱', $evidence,
            );
        }

        return null;
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function longPauses(Collection $answers): ?Insight
    {
        $idleMs = (int) config('quiz.analysis.idle_ms');
        $paused = $answers->filter(fn (Answer $a) => $a->firstTapTime() >= $idleMs);

        if ($paused->count() < 2) {
            return null;
        }

        return new Insight(
            type: 'long_pauses',
            title: 'Long pauses',
            message: sprintf('Waited more than %d seconds before the first tap on %d questions. The child may have been unsure, distracted, or needed help.', $idleMs / 1000, $paused->count()),
            severity: Insight::INFO,
            icon: '⏳',
            evidence: ['questions' => $paused->count()],
        );
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    public function freeChoicePreferences(Collection $answers): ?Insight
    {
        $free = $answers->filter(fn (Answer $a) => $a->mode === 'free' && $a->selectedLabel !== null);

        if ($free->count() < 5) {
            return null;
        }

        $favourites = $free->countBy('selectedLabel')->filter(fn (int $n) => $n >= 2)->sortDesc()->take(3);
        $matched = $this->firstTryRate($free);
        $message = sprintf('In Free Choice the child picked the target answer %s of the time.', Metrics::percent($matched));

        if ($favourites->isNotEmpty()) {
            $message .= ' Most chosen: '.$favourites->map(fn (int $n, string $label) => "{$label} ({$n})")->implode(', ').'.';
        }

        return new Insight('free_choice_preferences', 'Free Choice picks', $message, Insight::INFO, '📝', ['favourites' => $favourites->all(), 'target_rate' => $matched]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * @param  Collection<int, Answer>  $answers
     * @param  callable(Answer): bool  $inGroup
     * @param  callable(string, string): string  $describe
     */
    private function compareGroups(Collection $answers, callable $inGroup, string $type, string $title, string $icon, callable $describe): ?Insight
    {
        [$with, $without] = $answers->partition($inGroup);

        if ($with->count() < $this->minGroup() || $without->count() < $this->minGroup()) {
            return null;
        }

        $withRate = $this->firstTryRate($with);
        $withoutRate = $this->firstTryRate($without);

        if (abs($withRate - $withoutRate) < 0.15) {
            return null;
        }

        return new Insight($type, $title, $describe(Metrics::percent($withRate), Metrics::percent($withoutRate)), Insight::INFO, $icon, [
            'with_rate' => $withRate, 'without_rate' => $withoutRate, 'with' => $with->count(), 'without' => $without->count(),
        ]);
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    private function firstTryRate(Collection $answers): float
    {
        return $answers->isEmpty() ? 0.0 : $answers->filter(fn (Answer $a) => $a->firstTryCorrect())->count() / $answers->count();
    }

    private function minGroup(): int
    {
        return (int) config('quiz.analysis.min_group_size');
    }

    public static function positionName(int $position, int $optionCount): string
    {
        return match ($optionCount) {
            2 => ['left', 'right'][$position] ?? 'card '.($position + 1),
            3 => ['left', 'middle', 'right'][$position] ?? 'card '.($position + 1),
            4 => ['top-left', 'top-right', 'bottom-left', 'bottom-right'][$position] ?? 'card '.($position + 1),
            default => 'card '.($position + 1),
        };
    }
}
