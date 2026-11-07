<?php

namespace Database\Factories;

use App\Models\QuizResponse;
use App\Models\QuizSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuizResponse>
 */
class QuizResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isCorrect = fake()->boolean(70);

        return [
            'client_uuid' => (string) Str::uuid(),
            'quiz_session_id' => QuizSession::factory(),
            'question_ref' => 'q'.fake()->numberBetween(1, 20),
            'question_text' => 'Touch the cat!',
            'category' => 'Animals',
            'correct_label' => 'Cat',
            'selected_label' => $isCorrect ? 'Cat' : 'Dog',
            'is_correct' => $isCorrect,
            'attempts' => $isCorrect ? 1 : fake()->numberBetween(2, 3),
            'response_time_ms' => fake()->numberBetween(800, 9000),
            'answered_at' => now(),
        ];
    }
}
