<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Enums\AiReportGenerationStatus;
use App\Models\Tenant\AiReportGeneration;

class AiReportGenerationRepository
{
    private const STALE_AFTER_MINUTES = 10;

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

    public function claimQueued(int $id): ?AiReportGeneration
    {
        $claimed = AiReportGeneration::query()
            ->whereKey($id)
            ->where(function ($query): void {
                $query->where('status', AiReportGenerationStatus::QUEUED)
                    ->orWhere(function ($query): void {
                        $query->where('status', AiReportGenerationStatus::PROCESSING)
                            ->where('updated_at', '<=', now()->subMinutes(self::STALE_AFTER_MINUTES));
                    });
            })
            ->update([
                'status' => AiReportGenerationStatus::PROCESSING,
                'progress' => 10,
                'started_at' => now(),
                'error_message' => null,
                'updated_at' => now(),
            ]);

        return $claimed === 1
            ? $this->findById($id)
            : null;
    }

    public function releaseForRetry(int $id): void
    {
        AiReportGeneration::query()
            ->whereKey($id)
            ->where('status', AiReportGenerationStatus::PROCESSING)
            ->update([
                'status' => AiReportGenerationStatus::QUEUED,
                'progress' => 0,
                'started_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function markFailed(int $id): void
    {
        AiReportGeneration::query()
            ->whereKey($id)
            ->whereIn('status', [
                AiReportGenerationStatus::QUEUED,
                AiReportGenerationStatus::PROCESSING,
            ])
            ->update([
                'status' => AiReportGenerationStatus::FAILED,
                'progress' => 0,
                'error_message' => 'Não foi possível gerar o relatório. Tente novamente.',
                'updated_at' => now(),
            ]);
    }

    /** @param array<string, mixed> $data */
    public function update(AiReportGeneration $generation, array $data): AiReportGeneration
    {
        $generation->update($data);

        return $generation->fresh('report') ?? $generation;
    }
}
