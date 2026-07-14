<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\AiReportGeneration;

class AiReportGenerationRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): AiReportGeneration
    {
        return AiReportGeneration::query()->create($data);
    }

    public function findById(int $id): ?AiReportGeneration
    {
        return AiReportGeneration::query()->with('report')->find($id);
    }

    public function findForTerreno(int $id, int $terrenoId): ?AiReportGeneration
    {
        return AiReportGeneration::query()
            ->whereKey($id)
            ->where('terreno_id', $terrenoId)
            ->with('report')
            ->first();
    }

    /** @param array<string, mixed> $data */
    public function update(AiReportGeneration $generation, array $data): AiReportGeneration
    {
        $generation->update($data);

        return $generation->fresh('report') ?? $generation;
    }
}
