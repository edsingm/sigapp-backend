<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Enums\TenantExportStatus;
use App\Models\Tenant\TenantExportGeneration;
use App\Models\Tenant\User;

class TenantExportGenerationRepository
{
    private const STALE_AFTER_MINUTES = 10;

    /** @param array<string, mixed> $data */
    public function createOrFind(User $user, string $idempotencyKey, array $data): TenantExportGeneration
    {
        return TenantExportGeneration::query()->firstOrCreate(
            [
                'requested_by' => $user->id,
                'idempotency_key' => $idempotencyKey,
            ],
            $data,
        );
    }

    public function findForUser(User $user, int $id): TenantExportGeneration
    {
        return TenantExportGeneration::query()
            ->whereKey($id)
            ->where('requested_by', $user->id)
            ->firstOrFail();
    }

    public function claimQueued(int $id): ?TenantExportGeneration
    {
        $claimed = TenantExportGeneration::query()
            ->whereKey($id)
            ->where(function ($query): void {
                $query->where('status', TenantExportStatus::QUEUED)
                    ->orWhere(function ($query): void {
                        $query->where('status', TenantExportStatus::PROCESSING)
                            ->where('updated_at', '<=', now()->subMinutes(self::STALE_AFTER_MINUTES));
                    });
            })
            ->update([
                'status' => TenantExportStatus::PROCESSING,
                'progress' => 10,
                'started_at' => now(),
                'error_message' => null,
                'updated_at' => now(),
            ]);

        return $claimed === 1
            ? TenantExportGeneration::query()->with('requester')->find($id)
            : null;
    }

    public function releaseForRetry(int $id): void
    {
        TenantExportGeneration::query()
            ->whereKey($id)
            ->where('status', TenantExportStatus::PROCESSING)
            ->update([
                'status' => TenantExportStatus::QUEUED,
                'progress' => 0,
                'started_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array{storage_disk: string, storage_path: string, file_name: string, mime_type: string, size: int}  $artifact
     */
    public function markCompleted(TenantExportGeneration $generation, array $artifact): void
    {
        $generation->update([
            ...$artifact,
            'status' => TenantExportStatus::COMPLETED,
            'progress' => 100,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(int $id): void
    {
        TenantExportGeneration::query()
            ->whereKey($id)
            ->whereIn('status', [
                TenantExportStatus::QUEUED,
                TenantExportStatus::PROCESSING,
            ])
            ->update([
                'status' => TenantExportStatus::FAILED,
                'progress' => 0,
                'error_message' => 'EXPORT_GENERATION_FAILED',
                'updated_at' => now(),
            ]);
    }
}
