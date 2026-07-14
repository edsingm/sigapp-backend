<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\AiReportGenerationStatus;
use App\Models\Tenant\AiReportGeneration;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<AiReportGeneration> */
class AiReportGenerationFactory extends Factory
{
    protected $model = AiReportGeneration::class;

    public function definition(): array
    {
        return [
            'terreno_id' => Terreno::factory(),
            'requested_by' => User::factory(),
            'status' => AiReportGenerationStatus::QUEUED,
            'progress' => 0,
            'report_id' => null,
            'error_message' => null,
            'requested_at' => now(),
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
