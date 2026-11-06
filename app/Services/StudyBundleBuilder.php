<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Study;
use App\Support\Media;

/**
 * Builds the self-contained JSON a device needs to run a study's quiz (also offline):
 * settings, questions with options and media URLs, and the snapshot data stored with each answer.
 */
class StudyBundleBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Study $study): array
    {
        $questions = $study->questions()
            ->where('is_archived', false)
            ->with('options')
            ->get();

        return [
            'study' => [
                'id' => $study->id,
                'name' => $study->name,
                'locale' => $study->locale,
                'default_quiz_mode' => $study->default_quiz_mode->value,
                'age_min' => $study->age_min,
                'age_max' => $study->age_max,
                'questions_per_session' => $study->questions_per_session,
                'shuffle_questions' => $study->shuffle_questions,
                'shuffle_options' => $study->shuffle_options,
            ],
            'questions' => $questions->map(fn (Question $question) => $this->question($question))->values()->all(),
            'version' => $this->fingerprint($study, $questions->max('updated_at')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function question(Question $question): array
    {
        return [
            'id' => $question->id,
            'ref' => $question->reference(),
            'version' => $question->version,
            'type' => $question->type === QuestionType::NumberInput ? 'number-input' : 'choice',
            'category' => $question->category,
            'speak' => $question->speak_text,
            'hint' => $question->hint ?? $question->speak_text,
            'question_emoji' => $question->question_emoji,
            'image' => Media::url($question->image_path),
            'audio' => Media::url($question->audio_path),
            'correct_number' => $question->correct_number,
            'correct_label' => $question->correctLabel(),
            'options' => $question->options->map(fn (QuestionOption $option) => [
                'label' => $option->label,
                'emoji' => $option->emoji,
                'bg' => $option->bg_color,
                'image' => Media::url($option->image_path),
                'correct' => $option->is_correct,
            ])->values()->all(),
        ];
    }

    /**
     * Changes whenever settings or any question in the set change, so devices know to refresh.
     */
    private function fingerprint(Study $study, mixed $latestQuestionUpdate): string
    {
        return sha1(implode('|', [
            $study->id,
            $study->updated_at?->getTimestamp(),
            $latestQuestionUpdate?->getTimestamp(),
            $study->questions()->pluck('questions.id')->implode(','),
        ]));
    }
}
