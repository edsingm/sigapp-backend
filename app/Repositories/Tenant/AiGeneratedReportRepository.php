<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\AiGeneratedReport;

class AiGeneratedReportRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): AiGeneratedReport
    {
        return AiGeneratedReport::query()->create($data);
    }

    public function findById(int $id): ?AiGeneratedReport
    {
        return AiGeneratedReport::query()->find($id);
    }

    public function findLatestByTerrenoId(int $terrenoId): ?AiGeneratedReport
    {
        return AiGeneratedReport::query()
            ->where('terreno_id', $terrenoId)
            ->latest('id')
            ->first();
    }
}
