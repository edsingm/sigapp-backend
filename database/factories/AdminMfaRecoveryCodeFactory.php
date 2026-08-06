<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Central\AdminMfaRecoveryCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @phpstan-extends Factory<AdminMfaRecoveryCode> */
class AdminMfaRecoveryCodeFactory extends Factory
{
    protected $model = AdminMfaRecoveryCode::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'code_hash' => Hash::make(strtoupper(bin2hex(random_bytes(8)))),
            'used_at' => null,
        ];
    }
}
