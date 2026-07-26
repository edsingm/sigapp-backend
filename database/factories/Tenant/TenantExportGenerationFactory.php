<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\TenantExportStatus;
use App\Enums\TenantExportType;
use App\Models\Tenant\TenantExportGeneration;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @phpstan-extends Factory<TenantExportGeneration> */
class TenantExportGenerationFactory extends Factory
{
    protected $model = TenantExportGeneration::class;

    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'type' => TenantExportType::TERRENOS_PDF,
            'subject_id' => null,
            'filters' => [],
            'payload' => [],
            'status' => TenantExportStatus::QUEUED,
            'progress' => 0,
            'requested_at' => now(),
            'expires_at' => now()->addHours(24),
        ];
    }
}
