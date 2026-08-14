<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\TenantStatus;
use App\Models\Central\LegalAcceptance;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'tenant_id' => fn (): string => (string) Tenant::query()->create([
                'name' => fake()->company(),
                'slug' => 'legal-'.Str::lower(Str::random(8)),
                'status' => TenantStatus::PENDING->value,
            ])->getKey(),
            'actor_email' => fake()->unique()->safeEmail(),
            'document_key' => 'signup_usage_contract',
            'document_version' => 'v2026-02-25',
            'document_hash' => hash('sha256', fake()->uuid()),
            'accepted_at' => now(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
