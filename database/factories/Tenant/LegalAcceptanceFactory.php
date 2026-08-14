<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\LegalAcceptance;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<LegalAcceptance>
 */
class LegalAcceptanceFactory extends Factory
{
    protected $model = LegalAcceptance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'document_key' => 'privacy_policy',
            'document_version' => 'v2026-08-14',
            'document_hash' => hash('sha256', fake()->uuid()),
            'accepted_at' => now(),
        ];
    }
}
