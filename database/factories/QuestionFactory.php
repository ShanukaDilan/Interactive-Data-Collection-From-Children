<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $animal = fake()->randomElement(['cat', 'dog', 'cow', 'duck', 'frog']);

        return [
            'type' => QuestionType::Choice,
            'category' => 'Animals',
            'speak_text' => "Touch the {$animal}!",
            'hint' => ucfirst($animal),
            'version' => 1,
        ];
    }

    /**
     * Add three options, the first one correct.
     */
    public function withOptions(int $count = 3): static
    {
        return $this->afterCreating(function (Question $question) use ($count): void {
            foreach (range(0, $count - 1) as $position) {
                QuestionOption::factory()->for($question)->create([
                    'position' => $position,
                    'is_correct' => $position === 0,
                ]);
            }
        });
    }

    public function numberInput(int $correctNumber = 3): static
    {
        return $this->state([
            'type' => QuestionType::NumberInput,
            'category' => 'Numbers - Counting',
            'speak_text' => 'Count the stars and press the number!',
            'question_emoji' => str_repeat('⭐', $correctNumber),
            'correct_number' => $correctNumber,
        ]);
    }
}
