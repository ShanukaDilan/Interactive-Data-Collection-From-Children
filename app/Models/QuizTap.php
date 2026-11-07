<?php

namespace App\Models;

use Database\Factories\QuizTapFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tap (or keypad submission) by a child on a question.
 */
#[Fillable([
    'quiz_response_id', 'sequence', 'selected_label', 'selected_number',
    'screen_position', 'is_correct', 'elapsed_ms', 'during_prompt',
])]
class QuizTap extends Model
{
    /** @use HasFactory<QuizTapFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'selected_number' => 'integer',
            'screen_position' => 'integer',
            'is_correct' => 'boolean',
            'elapsed_ms' => 'integer',
            'during_prompt' => 'boolean',
        ];
    }

    /** @return BelongsTo<QuizResponse, $this> */
    public function response(): BelongsTo
    {
        return $this->belongsTo(QuizResponse::class, 'quiz_response_id');
    }
}
