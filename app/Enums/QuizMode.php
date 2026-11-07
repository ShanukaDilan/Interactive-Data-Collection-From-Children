<?php

namespace App\Enums;

enum QuizMode: string
{
    /** "Find the Answer" — child must tap the correct option; wrong taps shake and retry. */
    case Correct = 'correct';

    /** "Free Choice" — any tap is recorded without right/wrong feedback. */
    case Free = 'free';

    public function label(): string
    {
        return match ($this) {
            self::Correct => 'Find the Answer',
            self::Free => 'Free Choice',
        };
    }
}
