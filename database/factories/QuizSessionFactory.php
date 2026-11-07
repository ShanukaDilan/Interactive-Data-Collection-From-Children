<?php

namespace Database\Factories;

use App\Enums\QuizMode;
use App\Models\QuizSession;
use App\Models\Study;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizSession>
 */
class QuizSessionFactory extends Factory
{
    /**
     * Define the model's default state. The uuid is generated on create.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_id' => Study::factory(),
            'child_age' => fake()->numberBetween(4, 6),
            'quiz_mode' => QuizMode::Correct,
            'started_at' => now()->subMinutes(10),
        ];
    }

    public function completed(): static
    {
        return $this->state(['completed_at' => now()]);
    }
}
