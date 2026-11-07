<?php

namespace Database\Factories;

use App\Models\Participant;
use App\Models\Study;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    /**
     * Define the model's default state. The code is generated on create.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_id' => Study::factory(),
            'age' => fake()->numberBetween(4, 6),
            'display_name' => fake()->firstName(),
        ];
    }

    public function consented(): static
    {
        return $this->state(['consent_recorded_at' => now()]);
    }
}
