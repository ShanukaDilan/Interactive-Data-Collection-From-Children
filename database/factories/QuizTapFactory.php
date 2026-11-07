<?php

namespace Database\Factories;

use App\Models\QuizResponse;
use App\Models\QuizTap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizTap>
 */
class QuizTapFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_response_id' => QuizResponse::factory(),
            'sequence' => 1,
            'selected_label' => 'Cat',
            'screen_position' => 0,
            'is_correct' => true,
            'elapsed_ms' => 2000,
            'during_prompt' => false,
        ];
    }
}
