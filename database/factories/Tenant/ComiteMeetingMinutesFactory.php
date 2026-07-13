<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\ComiteMeetingMinutes;
use App\Models\Tenant\ComiteMeetingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<ComiteMeetingMinutes> */
class ComiteMeetingMinutesFactory extends Factory
{
    protected $model = ComiteMeetingMinutes::class;

    public function definition(): array
    {
        return [
            'session_id' => ComiteMeetingSession::factory(),
            'summary' => fake()->paragraph(),
            'decisions' => [],
            'blockers' => [],
            'next_steps' => fake()->sentence(),
        ];
    }
}
