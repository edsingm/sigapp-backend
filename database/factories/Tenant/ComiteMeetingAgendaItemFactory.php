<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\ComiteMeetingAgendaItem;
use App\Models\Tenant\ComiteMeetingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<ComiteMeetingAgendaItem> */
class ComiteMeetingAgendaItemFactory extends Factory
{
    protected $model = ComiteMeetingAgendaItem::class;

    public function definition(): array
    {
        return [
            'session_id' => ComiteMeetingSession::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'position' => 0,
            'duration_minutes' => 15,
            'decision_required' => false,
            'status' => 'pending',
        ];
    }
}
