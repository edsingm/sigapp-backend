<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\TerrenoImportStatus;
use App\Models\Tenant\TerrenoImport;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @phpstan-extends Factory<TerrenoImport> */
class TerrenoImportFactory extends Factory
{
    protected $model = TerrenoImport::class;

    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'status' => TerrenoImportStatus::QUEUED,
            'progress' => 0,
            'file_name' => 'terrenos.xlsx',
            'requested_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }
}
