<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Models\Concerns\LogsChanges;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'legacy_id', 'type', 'category', 'speak_text', 'hint', 'question_emoji',
    'image_path', 'audio_path', 'correct_number', 'version', 'is_archived', 'created_by',
])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, LogsChanges;

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'correct_number' => 'integer',
            'version' => 'integer',
            'is_archived' => 'boolean',
        ];
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('position');
    }

    /** @return BelongsToMany<Study, $this> */
    public function studies(): BelongsToMany
    {
        return $this->belongsToMany(Study::class)->withPivot('position');
    }

    /** @return HasMany<QuizResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(QuizResponse::class);
    }

    /**
     * Stable reference used in exports: legacy id (e.g. "q01") when imported, otherwise "q{id}".
     */
    public function reference(): string
    {
        return $this->legacy_id ?? 'q'.$this->id;
    }

    /**
     * All media paths referenced by this question and its options.
     *
     * @return list<string>
     */
    public function mediaPaths(): array
    {
        return $this->options->pluck('image_path')
            ->push($this->image_path, $this->audio_path)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Of the given paths, those still referenced by any question other than this one
     * (duplicated questions share files, so these must not be deleted).
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public function mediaPathsUsedElsewhere(array $paths): array
    {
        $paths = array_values(array_filter($paths));

        if ($paths === []) {
            return [];
        }

        $questionPaths = static::query()
            ->whereKeyNot($this->id)
            ->where(fn ($query) => $query->whereIn('image_path', $paths)->orWhereIn('audio_path', $paths))
            ->get(['image_path', 'audio_path'])
            ->flatMap(fn (Question $other) => [$other->image_path, $other->audio_path]);

        $optionPaths = QuestionOption::query()
            ->where('question_id', '!=', $this->id)
            ->whereIn('image_path', $paths)
            ->pluck('image_path');

        return $questionPaths->merge($optionPaths)
            ->filter(fn ($path) => in_array($path, $paths, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Label of the correct answer (number for keypad questions).
     */
    public function correctLabel(): ?string
    {
        if ($this->type === QuestionType::NumberInput && $this->correct_number !== null) {
            return (string) $this->correct_number;
        }

        return $this->options->firstWhere('is_correct', true)?->label;
    }
}
