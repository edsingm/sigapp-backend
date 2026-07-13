<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\ComiteMeetingSession;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<ComiteMeetingSession> */
class ComiteMeetingSessionFactory extends Factory
{
    protected $model = ComiteMeetingSession::class;

    public function definition(): array
    {
        return [
            'comite_revisao_id' => ComiteRevisao::factory(),
            'title' => fake()->sentence(4),
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'meeting_mode' => 'online',
            'chair_user_id' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}
