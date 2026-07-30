<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Enums\TerrenoPolygonImportStatus;
use App\Enums\TerrenoPolygonStatus;
use App\Models\Tenant\TerrenoPendingPolygon;
use App\Models\Tenant\TerrenoPolygonImport;
use App\Models\Tenant\TerrenoPolygonImportFile;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TerrenoPolygonImportRepository
{
    public function findByIdempotency(User $user, string $idempotencyKey): ?TerrenoPolygonImport
    {
        return TerrenoPolygonImport::query()
            ->where('requested_by', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /** @param array<string, mixed> $data */
    public function createOrFind(User $user, string $idempotencyKey, array $data): TerrenoPolygonImport
    {
        return TerrenoPolygonImport::query()->firstOrCreate(
            ['requested_by' => $user->id, 'idempotency_key' => $idempotencyKey],
            $data,
        );
    }

    /** @param array<string, mixed> $data */
    public function createFile(TerrenoPolygonImport $import, array $data): TerrenoPolygonImportFile
    {
        return TerrenoPolygonImportFile::query()->create([
            ...$data,
            'terreno_polygon_import_id' => $import->id,
        ]);
    }

    public function findForUser(User $user, int $id): TerrenoPolygonImport
    {
        return TerrenoPolygonImport::query()
            ->with('files')
            ->where('requested_by', $user->id)
            ->findOrFail($id);
    }

    public function findWithFiles(int $id): ?TerrenoPolygonImport
    {
        return TerrenoPolygonImport::query()->with('files')->find($id);
    }

    /** @return Collection<int, TerrenoPolygonImportFile> */
    public function filesForImport(TerrenoPolygonImport $import): Collection
    {
        return TerrenoPolygonImportFile::where('terreno_polygon_import_id', $import->id)
            ->orderBy('id')
            ->get();
    }

    public function claimQueued(int $id): ?TerrenoPolygonImport
    {
        $claimed = TerrenoPolygonImport::query()
            ->whereKey($id)
            ->where('status', TerrenoPolygonImportStatus::QUEUED->value)
            ->update([
                'status' => TerrenoPolygonImportStatus::PROCESSING->value,
                'progress' => 5,
                'started_at' => now(),
            ]);

        return $claimed === 1
            ? TerrenoPolygonImport::query()->with('files')->find($id)
            : null;
    }

    /** @param array<string, mixed> $data */
    public function createPolygon(array $data): ?TerrenoPendingPolygon
    {
        $polygon = TerrenoPendingPolygon::query()->firstOrCreate(
            ['geometry_hash' => $data['geometry_hash']],
            $data,
        );

        return $polygon->wasRecentlyCreated ? $polygon : null;
    }

    public function markFileProcessed(TerrenoPolygonImportFile $file, int $polygonCount): void
    {
        $file->update([
            'status' => 'completed',
            'storage_disk' => null,
            'storage_path' => null,
            'size' => null,
            'error_message' => null,
        ]);
        $file->import()->increment('processed_files');
        $file->import()->increment('polygon_count', $polygonCount);
    }

    public function markFileFailed(TerrenoPolygonImportFile $file, string $message): void
    {
        $file->update([
            'status' => 'failed',
            'storage_disk' => null,
            'storage_path' => null,
            'size' => null,
            'error_message' => $message,
        ]);
        $file->import()->increment('processed_files');
        $file->import()->increment('failed_files');
    }

    public function clearFileStorage(TerrenoPolygonImportFile $file): void
    {
        $file->update([
            'storage_disk' => null,
            'storage_path' => null,
            'size' => null,
        ]);
    }

    public function complete(TerrenoPolygonImport $import): void
    {
        $import->refresh();
        $import->update([
            'status' => $import->failed_files > 0
                ? TerrenoPolygonImportStatus::COMPLETED_WITH_ERRORS
                : TerrenoPolygonImportStatus::COMPLETED,
            'progress' => 100,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(int $id, string $code, ?string $message = null): void
    {
        TerrenoPolygonImport::query()->whereKey($id)->update([
            'status' => TerrenoPolygonImportStatus::FAILED->value,
            'error_code' => $code,
            'error_message' => $message ?? $code,
            'completed_at' => now(),
        ]);
    }

    public function updateProgress(int $id, int $processed, int $total): void
    {
        TerrenoPolygonImport::query()->whereKey($id)->update([
            'progress' => $total < 1 ? 100 : min(99, (int) floor(($processed / $total) * 100)),
        ]);
    }

    public function releaseForRetry(int $id): void
    {
        TerrenoPolygonImport::query()
            ->whereKey($id)
            ->where('status', TerrenoPolygonImportStatus::PROCESSING->value)
            ->update(['status' => TerrenoPolygonImportStatus::QUEUED->value]);
    }

    /** @return Collection<int, TerrenoPendingPolygon> */
    public function inBoundingBox(float $minLng, float $minLat, float $maxLng, float $maxLat, int $limit): Collection
    {
        $ids = DB::table('terreno_pending_polygons')
            ->where('status', TerrenoPolygonStatus::PENDING->value)
            ->where('max_lng', '>=', $minLng)
            ->where('min_lng', '<=', $maxLng)
            ->where('max_lat', '>=', $minLat)
            ->where('min_lat', '<=', $maxLat)
            ->orderBy('id')
            ->limit(min($limit, 500))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return TerrenoPendingPolygon::query()
            ->with('file:id,file_name')
            ->findMany($ids);
    }

    public function findPolygonForUpdate(int $id): TerrenoPendingPolygon
    {
        return TerrenoPendingPolygon::query()
            ->with('file:id,file_name')
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function markLinked(TerrenoPendingPolygon $polygon, int $terrainId, int $userId): TerrenoPendingPolygon
    {
        $polygon->update([
            'status' => TerrenoPolygonStatus::LINKED,
            'terreno_id' => $terrainId,
            'linked_by' => $userId,
            'linked_at' => now(),
        ]);

        return $polygon->refresh()->load('file:id,file_name');
    }

    public function deletePending(int $id): bool
    {
        return TerrenoPendingPolygon::query()
            ->whereKey($id)
            ->where('status', TerrenoPolygonStatus::PENDING->value)
            ->delete() === 1;
    }
}
