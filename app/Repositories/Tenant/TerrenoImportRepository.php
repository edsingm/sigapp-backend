<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Enums\TerrenoImportRowStatus;
use App\Enums\TerrenoImportStatus;
use App\Models\Tenant\TerrenoImport;
use App\Models\Tenant\TerrenoImportRow;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TerrenoImportRepository
{
    public function findByIdempotency(User $user, string $idempotencyKey): ?TerrenoImport
    {
        return TerrenoImport::query()
            ->where('requested_by', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /** @param array<string, mixed> $data */
    public function createOrFind(User $user, string $idempotencyKey, array $data): TerrenoImport
    {
        return TerrenoImport::query()->firstOrCreate(
            ['requested_by' => $user->id, 'idempotency_key' => $idempotencyKey],
            $data,
        );
    }

    public function findById(int $id): ?TerrenoImport
    {
        return TerrenoImport::query()->with('requester')->find($id);
    }

    public function findForUser(User $user, int $id): TerrenoImport
    {
        return TerrenoImport::query()
            ->where('requested_by', $user->id)
            ->findOrFail($id);
    }

    public function claimQueued(int $id): ?TerrenoImport
    {
        $claimed = TerrenoImport::query()
            ->whereKey($id)
            ->where('status', TerrenoImportStatus::QUEUED->value)
            ->update([
                'status' => TerrenoImportStatus::VALIDATING->value,
                'progress' => 5,
                'started_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

        return $claimed === 1 ? TerrenoImport::query()->find($id) : null;
    }

    /** @param list<array<string, mixed>> $rows */
    public function replaceRows(TerrenoImport $import, array $rows): void
    {
        TerrenoImportRow::query()->where('terreno_import_id', $import->id)->delete();
        foreach (array_chunk($rows, 200) as $chunk) {
            TerrenoImportRow::query()->insert($chunk);
        }
    }

    public function markValidated(TerrenoImport $import, int $total, int $valid, int $invalid): void
    {
        $import->update([
            'status' => $invalid > 0 ? TerrenoImportStatus::INVALID : TerrenoImportStatus::READY,
            'progress' => 100,
            'total_rows' => $total,
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'validated_at' => now(),
        ]);
    }

    public function confirmForImport(User $user, int $id): ?TerrenoImport
    {
        $updated = TerrenoImport::query()
            ->whereKey($id)
            ->where('requested_by', $user->id)
            ->where('status', TerrenoImportStatus::READY->value)
            ->update([
                'status' => TerrenoImportStatus::IMPORTING->value,
                'progress' => 0,
                'confirmed_at' => now(),
            ]);

        return $updated === 1 ? TerrenoImport::query()->find($id) : null;
    }

    /** @return Collection<int, TerrenoImportRow> */
    public function validRows(int $importId): Collection
    {
        return TerrenoImportRow::where('terreno_import_id', $importId)
            ->where('status', TerrenoImportRowStatus::VALID->value)
            ->orderBy('row_number')
            ->get();
    }

    public function paginateRows(User $user, int $importId, ?TerrenoImportRowStatus $status, int $perPage): LengthAwarePaginator
    {
        $this->findForUser($user, $importId);

        $query = TerrenoImportRow::query()->where('terreno_import_id', $importId);
        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query->orderBy('row_number')->paginate(min($perPage, 100));
    }

    /** @return Collection<int, TerrenoImportRow> */
    public function invalidRowsForReport(User $user, int $importId): Collection
    {
        $this->findForUser($user, $importId);

        return TerrenoImportRow::where('terreno_import_id', $importId)
            ->where('status', TerrenoImportRowStatus::INVALID->value)
            ->orderBy('row_number')
            ->limit(1000)
            ->get();
    }

    /** @param array<int, int> $terrainIdsByRowId */
    public function markCompleted(TerrenoImport $import, array $terrainIdsByRowId): void
    {
        foreach ($terrainIdsByRowId as $rowId => $terrainId) {
            TerrenoImportRow::query()->whereKey($rowId)->update([
                'status' => TerrenoImportRowStatus::IMPORTED->value,
                'terreno_id' => $terrainId,
            ]);
        }

        $import->update([
            'status' => TerrenoImportStatus::COMPLETED,
            'progress' => 100,
            'imported_rows' => count($terrainIdsByRowId),
            'completed_at' => now(),
        ]);
    }

    public function markFailed(int $id, string $code, ?string $message = null): void
    {
        TerrenoImport::query()->whereKey($id)->update([
            'status' => TerrenoImportStatus::FAILED->value,
            'error_code' => $code,
            'error_message' => $message ?? $code,
            'completed_at' => now(),
        ]);
    }

    public function clearStorage(int $id): void
    {
        TerrenoImport::query()->whereKey($id)->update([
            'storage_disk' => null,
            'storage_path' => null,
            'size' => null,
        ]);
    }

    public function releaseForRetry(int $id): void
    {
        TerrenoImport::query()
            ->whereKey($id)
            ->where('status', TerrenoImportStatus::VALIDATING->value)
            ->update(['status' => TerrenoImportStatus::QUEUED->value]);
    }

    public function updateProgress(int $id, int $progress): void
    {
        TerrenoImport::query()->whereKey($id)->update(['progress' => min(100, max(0, $progress))]);
    }
}
