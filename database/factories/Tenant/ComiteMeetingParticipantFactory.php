<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\ComiteMeetingParticipant;
use App\Models\Tenant\ComiteMeetingSession;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<ComiteMeetingParticipant> */
class ComiteMeetingParticipantFactory extends Factory
{
    protected $model = ComiteMeetingParticipant::class;

    public function definition(): array
    {
        return [
            'session_id' => ComiteMeetingSession::factory(),
            'user_id' => User::factory(),
            'role' => 'participant',
            'attendance_status' => 'invited',
        ];
    }
}
