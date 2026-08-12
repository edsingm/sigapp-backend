<?php

declare(strict_types=1);

namespace App\Repositories\Central;

use App\Enums\HiperdadosImportStatus;
use App\Models\Central\HiperdadosImport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HiperdadosImportRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): HiperdadosImport
    {
        return HiperdadosImport::query()->create($data);
    }

    public function findByUuid(string $uuid): ?HiperdadosImport
    {
        return HiperdadosImport::query()->where('uuid', $uuid)->first();
    }

    public function findById(int $id): ?HiperdadosImport
    {
        return HiperdadosImport::query()->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(HiperdadosImport $import, array $attributes): HiperdadosImport
    {
        $import->fill($attributes);
        $import->save();

        return $import->refresh();
    }

    public function paginateForAdmin(int $perPage = 20): LengthAwarePaginator
    {
        return HiperdadosImport::query()
            ->with(['creator:id,name,email', 'tenant:id,name,slug'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function markFetching(HiperdadosImport $import): HiperdadosImport
    {
        return $this->update($import, [
            'status' => HiperdadosImportStatus::Fetching,
            'started_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function markReady(
        HiperdadosImport $import,
        int $total,
        int $processed,
        int $failed,
        string $disk,
        string $path,
        array $summary,
    ): HiperdadosImport {
        return $this->update($import, [
            'status' => HiperdadosImportStatus::Ready,
            'total_count' => $total,
            'processed_count' => $processed,
            'failed_count' => $failed,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'credentials_encrypted' => null,
            'summary' => $summary,
            'finished_at' => null,
            'error_message' => null,
        ]);
    }

    public function markFailed(HiperdadosImport $import, string $message): HiperdadosImport
    {
        return $this->update($import, [
            'status' => HiperdadosImportStatus::Failed,
            'error_message' => $message,
            'credentials_encrypted' => null,
            'finished_at' => now(),
        ]);
    }

    public function markCommitting(HiperdadosImport $import, string $tenantId): HiperdadosImport
    {
        return $this->update($import, [
            'status' => HiperdadosImportStatus::Committing,
            'tenant_id' => $tenantId,
            'error_message' => null,
            'started_at' => $import->started_at ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function markCompleted(HiperdadosImport $import, int $imported, array $summary): HiperdadosImport
    {
        return $this->update($import, [
            'status' => HiperdadosImportStatus::Completed,
            'imported_count' => $imported,
            'summary' => array_merge($import->summary ?? [], $summary),
            'finished_at' => now(),
            'error_message' => null,
        ]);
    }

    public function updateProgress(HiperdadosImport $import, int $processed, int $failed, int $total): void
    {
        HiperdadosImport::query()
            ->whereKey($import->id)
            ->update([
                'processed_count' => $processed,
                'failed_count' => $failed,
                'total_count' => $total,
                'updated_at' => now(),
            ]);
    }
}
