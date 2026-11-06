<?php

namespace Database\Factories;

use App\Enums\QuizMode;
use App\Models\Study;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Study>
 */
class StudyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'owner_id' => User::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'locale' => 'en-US',
            'default_quiz_mode' => QuizMode::Correct,
            'age_min' => 4,
            'age_max' => 6,
            'is_active' => true,
        ];
    }

    public function freeChoice(): static
    {
        return $this->state(['default_quiz_mode' => QuizMode::Free]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
