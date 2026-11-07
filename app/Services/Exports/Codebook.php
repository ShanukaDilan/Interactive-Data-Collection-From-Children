<?php

namespace App\Services\Exports;

/**
 * Column definitions for every export. This is the single source of truth: the exporter
 * writes columns in exactly this order, and the codebook file is generated from it.
 */
final class Codebook
{
    /** Header row of the original app's CSV, kept so existing spreadsheets and scripts still work. */
    public const LEGACY_HEADER = [
        'Session ID', 'Child ID', 'Child Age', 'Quiz Mode',
        'Question ID', 'Question Text', 'Category',
        'Correct Answer', 'Selected Answer',
        'Is Correct', 'Attempts', 'Response Time (ms)', 'Timestamp',
    ];

    /**
     * One row per answer (wide format).
     *
     * @return array<string, array{type: string, description: string, values?: string}>
     */
    public static function responses(): array
    {
        return [
            'response_id' => ['type' => 'integer', 'description' => 'Unique answer id. Joins to response_id in the taps file.'],
            'study' => ['type' => 'text', 'description' => 'Study short name (slug).'],
            'session_uuid' => ['type' => 'text', 'description' => 'Unique id of the quiz session (one sitting).'],
            'legacy_session_id' => ['type' => 'text', 'description' => 'Session id from the original app, for imported data. Blank otherwise.'],
            'child_id' => ['type' => 'text', 'description' => 'Pseudonymous child code (or the original app\'s child id for imported data). Blank = anonymous play. Names are never exported.'],
            'child_age' => ['type' => 'integer', 'description' => 'Child\'s age in years as entered when the session started.'],
            'quiz_mode' => ['type' => 'category', 'description' => 'Quiz mode of the session.', 'values' => 'correct = Find the Answer (wrong taps shake, child tries again); free = Free Choice (first tap recorded, no feedback)'],
            'session_started_at' => ['type' => 'datetime', 'description' => self::timeNote('When the session started.')],
            'session_completed_at' => ['type' => 'datetime', 'description' => self::timeNote('When the last question was answered. Blank = ended early or interrupted.')],
            'question_position' => ['type' => 'integer', 'description' => 'Order of the question within the session, starting at 1.'],
            'question_id' => ['type' => 'integer', 'description' => 'Question id in the question bank. Blank if the question was later deleted.'],
            'question_ref' => ['type' => 'text', 'description' => 'Question id from the original app, for imported data.'],
            'question_version' => ['type' => 'integer', 'description' => 'Version of the question at the time it was asked (edits create a new version).'],
            'question_text' => ['type' => 'text', 'description' => 'Question exactly as it was asked.'],
            'category' => ['type' => 'text', 'description' => 'Question category (e.g. Numbers, Colors).'],
            'correct_label' => ['type' => 'text', 'description' => 'The correct (target) answer.'],
            'selected_label' => ['type' => 'text', 'description' => 'The final answer chosen. For number-keypad questions, the number entered.'],
            'selected_number' => ['type' => 'integer', 'description' => 'Number entered on the keypad. Blank for picture/emoji card questions.'],
            'is_correct' => ['type' => 'binary', 'description' => 'Final answer matches the target.', 'values' => '1 = yes, 0 = no. In Find the Answer this is always 1, because the child keeps trying until correct — use first_try_correct instead.'],
            'first_try_correct' => ['type' => 'binary', 'description' => 'Main accuracy measure. Find the Answer: correct on the first tap. Free Choice: chose the target answer.', 'values' => '1 = yes, 0 = no'],
            'attempts' => ['type' => 'integer', 'description' => 'Number of taps needed to reach the final answer (1 = first try).'],
            'response_time_ms' => ['type' => 'integer', 'description' => 'Milliseconds from the question appearing to the final answer.'],
            'first_tap_ms' => ['type' => 'integer', 'description' => 'Milliseconds from the question appearing to the first tap. Blank for data from the original app.'],
            'prompt_ended_ms' => ['type' => 'integer', 'description' => 'Milliseconds from the question appearing to the end of the spoken question (recorded audio or computer voice). Blank if unknown.'],
            'reaction_ms' => ['type' => 'integer', 'description' => 'Milliseconds from the end of the spoken question to the first tap. Blank if the child tapped before the question finished, or if unknown.'],
            'answered_during_prompt' => ['type' => 'binary', 'description' => 'The child tapped before the spoken question finished.', 'values' => '1 = yes, 0 = no'],
            'replay_count' => ['type' => 'integer', 'description' => 'Times the child (or adult) pressed the speaker to hear the question again.'],
            'option_count' => ['type' => 'integer', 'description' => 'Number of answer cards shown. Blank for keypad questions and older data.'],
            'option_order' => ['type' => 'text', 'description' => 'Answer cards in on-screen order, separated by " | ".'],
            'selected_position' => ['type' => 'integer', 'description' => 'Screen slot of the final answer: 1 = first card in on-screen order (left to right, then top to bottom).'],
            'answered_at' => ['type' => 'datetime', 'description' => self::timeNote('When the final answer was given.')],
        ];
    }

    /**
     * One row per tap (long format) — every card tap or keypad entry, in order.
     *
     * @return array<string, array{type: string, description: string, values?: string}>
     */
    public static function taps(): array
    {
        return [
            'response_id' => ['type' => 'integer', 'description' => 'Answer this tap belongs to. Joins to response_id in the responses file.'],
            'study' => ['type' => 'text', 'description' => 'Study short name (slug).'],
            'session_uuid' => ['type' => 'text', 'description' => 'Unique id of the quiz session.'],
            'child_id' => ['type' => 'text', 'description' => 'Pseudonymous child code. Blank = anonymous play.'],
            'child_age' => ['type' => 'integer', 'description' => 'Child\'s age in years.'],
            'quiz_mode' => ['type' => 'category', 'description' => 'Quiz mode of the session.', 'values' => 'correct = Find the Answer; free = Free Choice'],
            'question_position' => ['type' => 'integer', 'description' => 'Order of the question within the session, starting at 1.'],
            'question_id' => ['type' => 'integer', 'description' => 'Question id in the question bank.'],
            'category' => ['type' => 'text', 'description' => 'Question category.'],
            'correct_label' => ['type' => 'text', 'description' => 'The correct (target) answer.'],
            'tap_sequence' => ['type' => 'integer', 'description' => 'Order of this tap within the question, starting at 1.'],
            'selected_label' => ['type' => 'text', 'description' => 'Card tapped, or number entered on the keypad.'],
            'selected_number' => ['type' => 'integer', 'description' => 'Number entered on the keypad. Blank for card taps.'],
            'screen_position' => ['type' => 'integer', 'description' => 'Screen slot tapped: 1 = first card in on-screen order. Blank for keypad entries.'],
            'is_correct' => ['type' => 'binary', 'description' => 'This tap was the correct answer.', 'values' => '1 = yes, 0 = no'],
            'elapsed_ms' => ['type' => 'integer', 'description' => 'Milliseconds from the question appearing to this tap.'],
            'during_prompt' => ['type' => 'binary', 'description' => 'Tapped before the spoken question finished.', 'values' => '1 = yes, 0 = no'],
        ];
    }

    /**
     * The codebook itself, as rows (one per column of each file).
     *
     * @return list<array{file: string, column: string, type: string, description: string, values: string}>
     */
    public static function rows(): array
    {
        $rows = [];

        foreach (['responses' => self::responses(), 'taps' => self::taps()] as $file => $columns) {
            foreach ($columns as $name => $definition) {
                $rows[] = [
                    'file' => $file,
                    'column' => $name,
                    'type' => $definition['type'],
                    'description' => $definition['description'],
                    'values' => $definition['values'] ?? '',
                ];
            }
        }

        return $rows;
    }

    private static function timeNote(string $text): string
    {
        return $text.' Format YYYY-MM-DD HH:MM:SS, time zone '.config('app.timezone').'.';
    }
}
