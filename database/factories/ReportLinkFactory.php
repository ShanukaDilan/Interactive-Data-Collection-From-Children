<?php

namespace Database\Factories;

use App\Models\Participant;
use App\Models\ReportLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReportLink>
 */
class ReportLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'participant_id' => Participant::factory(),
            'token_hash' => ReportLink::hash(Str::random(ReportLink::TOKEN_LENGTH)),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }
}
