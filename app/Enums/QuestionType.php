<?php

namespace App\Enums;

enum QuestionType: string
{
    /** Child taps one of several emoji / image cards. */
    case Choice = 'choice';

    /** Child counts items and enters a number on the keypad. */
    case NumberInput = 'number_input';

    public function label(): string
    {
        return match ($this) {
            self::Choice => 'Tap the answer',
            self::NumberInput => 'Number keypad',
        };
    }
}
