<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\AiGeneratedReport;

class AiGeneratedReportRepository
{
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
