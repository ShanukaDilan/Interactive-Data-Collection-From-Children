<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;

/**
 * One answer with its session context and tap sequence, flattened for analysis.
 */
final class Answer
{
    /**
     * @param  list<string>|null  $optionOrder
     * @param  list<array{label: ?string, number: ?int, position: ?int, correct: bool, elapsed_ms: int, during_prompt: bool}>  $taps
     */
    public function __construct(
        public int $id,
        public int $sessionId,
        public int $studyId,
        public ?int $participantId,
        public ?int $childAge,
        public string $mode,
        public ?CarbonImmutable $sessionStartedAt,
        public ?int $questionId,
        public string $questionKey,
        public ?string $questionText,
        public ?string $category,
        public ?string $correctLabel,
        public ?string $selectedLabel,
        public ?int $selectedNumber,
        public bool $isCorrect,
        public int $attempts,
        public int $responseTimeMs,
        public ?int $firstTapMs,
        public ?int $promptEndedMs,
        public int $replayCount,
        public bool $answeredDuringPrompt,
        public ?array $optionOrder,
        public ?int $selectedPosition,
        public ?int $position,
        public array $taps,
        public bool $hasRecordedAudio = false,
        public bool $hasImageOptions = false,
        public bool $hasQuestionVisual = false,
        public bool $isNumberInput = false,
    ) {}

    /**
     * The headline accuracy measure: right on the first tap in "Find the Answer" mode,
     * or choosing the target in "Free Choice" mode.
     */
    public function firstTryCorrect(): bool
    {
        return $this->mode === 'free' ? $this->isCorrect : ($this->isCorrect && $this->attempts === 1);
    }

    /** Key that identifies the child: participant, or the session for anonymous play. */
    public function childKey(): string
    {
        return $this->participantId !== null ? 'p'.$this->participantId : 's'.$this->sessionId;
    }

    /** @return array{label: ?string, number: ?int, position: ?int, correct: bool, elapsed_ms: int, during_prompt: bool}|null */
    public function firstTap(): ?array
    {
        return $this->taps[0] ?? null;
    }

    /** Milliseconds from the question appearing to the first tap (falls back to the final answer time). */
    public function firstTapTime(): int
    {
        return $this->firstTapMs ?? $this->firstTap()['elapsed_ms'] ?? $this->responseTimeMs;
    }

    /**
     * Time to answer after the spoken prompt finished. Null when unknown, or when the child
     * tapped before the prompt ended (that is "answering early", not a fast reaction).
     */
    public function reactionTimeMs(): ?int
    {
        if ($this->promptEndedMs === null || $this->firstTapTime() < $this->promptEndedMs) {
            return null;
        }

        return $this->firstTapTime() - $this->promptEndedMs;
    }

    public function optionCount(): ?int
    {
        return $this->optionOrder !== null ? count($this->optionOrder) : null;
    }

    public function hasTaps(): bool
    {
        return $this->taps !== [];
    }
}
