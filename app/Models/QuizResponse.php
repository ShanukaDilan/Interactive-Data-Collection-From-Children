<?php

namespace App\Models;

use Database\Factories\QuizResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One answer given by a child to one question within a quiz session.
 */
#[Fillable([
    'client_uuid', 'quiz_session_id', 'question_id', 'question_ref', 'question_version',
    'question_text', 'category', 'correct_label', 'selected_label', 'selected_number',
    'is_correct', 'attempts', 'response_time_ms', 'position', 'answered_at',
    'first_tap_ms', 'prompt_ended_ms', 'replay_count', 'answered_during_prompt',
    'option_order', 'selected_position',
])]
class QuizResponse extends Model
{
    /** @use HasFactory<QuizResponseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'attempts' => 'integer',
            'response_time_ms' => 'integer',
            'selected_number' => 'integer',
            'position' => 'integer',
            'answered_at' => 'datetime',
            'first_tap_ms' => 'integer',
            'prompt_ended_ms' => 'integer',
            'replay_count' => 'integer',
            'answered_during_prompt' => 'boolean',
            'option_order' => 'array',
            'selected_position' => 'integer',
        ];
    }

    /** @return HasMany<QuizTap, $this> */
    public function taps(): HasMany
    {
        return $this->hasMany(QuizTap::class)->orderBy('sequence');
    }

    /**
     * Correct on the first tap: the meaningful accuracy in "Find the Answer" mode,
     * where every recorded answer is eventually correct.
     */
    public function isFirstTryCorrect(): bool
    {
        return $this->is_correct && $this->attempts === 1;
    }

    /** @return BelongsTo<QuizSession, $this> */
    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
