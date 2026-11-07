<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Study;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Imports questions from the legacy `data/questions.json` format into the database.
 *
 * Legacy text was HTML-escaped before storage, so values are decoded here.
 * Re-running is safe: questions are matched by `legacy_id` and updated in place.
 */
class LegacyQuestionImporter
{
    /**
     * @return int Number of questions imported or updated.
     */
    public function importFile(string $path, ?Study $study = null): int
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Questions file not found: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("Questions file is not valid JSON: {$path}");
        }

        return $this->import($decoded, $study);
    }

    /**
     * @param  list<array<string, mixed>>  $legacyQuestions
     * @return int Number of questions imported or updated.
     */
    public function import(array $legacyQuestions, ?Study $study = null): int
    {
        return DB::transaction(function () use ($legacyQuestions, $study): int {
            $position = $study?->questions()->max('question_study.position') ?? 0;
            $count = 0;

            foreach ($legacyQuestions as $legacy) {
                if (! isset($legacy['id'], $legacy['speak'])) {
                    continue;
                }

                $question = $this->upsertQuestion($legacy);
                $count++;

                if ($study !== null && ! $study->questions()->whereKey($question->id)->exists()) {
                    $study->questions()->attach($question->id, ['position' => ++$position]);
                }
            }

            return $count;
        });
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function upsertQuestion(array $legacy): Question
    {
        $isNumberInput = ($legacy['type'] ?? null) === 'number-input';

        $question = Question::updateOrCreate(
            ['legacy_id' => (string) $legacy['id']],
            [
                'type' => $isNumberInput ? QuestionType::NumberInput : QuestionType::Choice,
                'category' => $this->text($legacy['category'] ?? 'General', 80),
                'speak_text' => $this->text($legacy['speak'], 255),
                'hint' => $this->nullableText($legacy['hint'] ?? null, 120),
                'question_emoji' => $this->nullableText($legacy['question_emoji'] ?? null, 64),
                'image_path' => $legacy['image'] ?? null,
                'audio_path' => $legacy['audio'] ?? null,
                'correct_number' => $isNumberInput ? (int) ($legacy['correct_number'] ?? 0) : null,
            ],
        );

        $question->options()->delete();

        foreach (array_values($legacy['options'] ?? []) as $index => $option) {
            $question->options()->create([
                'position' => $index,
                'label' => $this->text($option['label'] ?? '', 100),
                'emoji' => $this->nullableText($option['emoji'] ?? null, 32),
                'bg_color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($option['bg'] ?? ''))
                    ? strtoupper($option['bg'])
                    : '#F0F4FF',
                'image_path' => $option['image'] ?? null,
                'is_correct' => (bool) ($option['correct'] ?? false),
            ]);
        }

        return $question;
    }

    private function text(mixed $value, int $max): string
    {
        $decoded = html_entity_decode(trim((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr($decoded, 0, $max);
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->text($value, $max);
    }
}
