<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\TerrenoPolygonImportStatus;
use App\Models\Tenant\TerrenoPolygonImport;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @phpstan-extends Factory<TerrenoPolygonImport> */
class TerrenoPolygonImportFactory extends Factory
{
    protected $model = TerrenoPolygonImport::class;

    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'status' => TerrenoPolygonImportStatus::QUEUED,
            'total_files' => 1,
            'requested_at' => now(),
        ];
    }
}
