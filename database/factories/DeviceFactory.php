<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Classroom tablet '.fake()->numberBetween(1, 9),
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14; Tablet)',
        ];
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }
}
