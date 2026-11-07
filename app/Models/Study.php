<?php

namespace App\Models;

use App\Enums\QuizMode;
use App\Models\Concerns\LogsChanges;
use Database\Factories\StudyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id', 'name', 'slug', 'description', 'locale', 'default_quiz_mode',
    'age_min', 'age_max', 'questions_per_session', 'shuffle_questions',
    'shuffle_options', 'retention_days', 'is_active',
])]
class Study extends Model
{
    /** @use HasFactory<StudyFactory> */
    use HasFactory, LogsChanges;

    /**
     * Mirror the column defaults so new instances are complete before being reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'en-US',
        'default_quiz_mode' => 'correct',
        'age_min' => 4,
        'age_max' => 6,
        'shuffle_questions' => true,
        'shuffle_options' => true,
        'is_active' => true,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'default_quiz_mode' => QuizMode::class,
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Researchers / teachers assigned to this study.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class)
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /** @return HasMany<Participant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /** @return HasMany<QuizSession, $this> */
    public function quizSessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }
}
