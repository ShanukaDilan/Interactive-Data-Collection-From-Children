<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'position' => 0,
            'label' => fake()->word(),
            'emoji' => fake()->randomElement(['🐱', '🐶', '🐮', '🦆', '🐸']),
            'bg_color' => '#F0F4FF',
            'is_correct' => false,
        ];
    }

    public function correct(): static
    {
        return $this->state(['is_correct' => true]);
    }
}
