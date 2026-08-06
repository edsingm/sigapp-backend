<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Common\AdminMfaChallengePurpose;
use App\Enums\Common\AdminMfaChallengeStatus;
use App\Models\Central\AdminMfaChallenge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<AdminMfaChallenge> */
class AdminMfaChallengeFactory extends Factory
{
    protected $model = AdminMfaChallenge::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'purpose' => AdminMfaChallengePurpose::LOGIN,
            'status' => AdminMfaChallengeStatus::PENDING,
            'factor_version' => 1,
            'pending_secret' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'device_name' => 'test-device',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => null,
            'invalidated_at' => null,
        ];
    }
}
