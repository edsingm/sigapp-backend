<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\Produto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Repositories\Contracts\UsageMetricsRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class UsageMetricsRepository implements UsageMetricsRepositoryInterface
{
    public function userCount(): int
    {
        return User::query()->count();
    }

    public function terrenoCount(): int
    {
        return Terreno::query()->count();
    }

    public function produtoCount(): int
    {
        return Produto::query()->count();
    }

    public function storageUsedBytes(): int
    {
        return (int) DB::query()
            ->fromSub($this->deduplicatedStorageObjectsQuery(), 'storage_objects')
            ->sum('size');
    }

    public function storageUsageForObject(string $disk, string $path): array
    {
        $row = DB::query()
            ->fromSub($this->deduplicatedStorageObjectsQuery(), 'storage_objects')
            ->selectRaw(
                'COALESCE(SUM(size), 0) AS used_bytes, COALESCE(MAX(CASE WHEN disk = ? AND path = ? THEN size END), 0) AS previous_size',
                [$disk, $path],
            )
            ->first();

        return [
            'used' => (int) ($row->used_bytes ?? 0),
            'previous' => (int) ($row->previous_size ?? 0),
        ];
    }

    public function storageObjects(): array
    {
        $objects = [];

        foreach ($this->deduplicatedStorageObjectsQuery()->orderBy('disk')->orderBy('path')->cursor() as $row) {
            $disk = (string) $row->disk;
            $path = (string) $row->path;
            $objects[$disk."\0".$path] = [
                'disk' => $disk,
                'path' => $path,
                'size' => (int) $row->size,
            ];
        }

        return $objects;
    }

    private function deduplicatedStorageObjectsQuery(): Builder
    {
        return DB::query()
            ->fromSub($this->rawStorageObjectsQuery(), 'raw_storage_objects')
            ->selectRaw('disk, path, MAX(size) AS size')
            ->where('disk', '<>', '')
            ->where('path', '<>', '')
            ->groupBy('disk', 'path');
    }

    private function rawStorageObjectsQuery(): Builder
    {
        $queries = [
            DB::table('terreno_documentos')
                ->whereNotNull('file_path')
                ->selectRaw("'s3' AS disk, file_path AS path, COALESCE(tamanho, 0) AS size"),
            DB::table('document_versions')
                ->whereNotNull('file_path')
                ->selectRaw("COALESCE(disk, 's3') AS disk, file_path AS path, COALESCE(size, 0) AS size"),
            DB::table('ai_generated_reports')
                ->whereNotNull('file_path')
                ->selectRaw("'s3' AS disk, file_path AS path, COALESCE(tamanho, 0) AS size"),
            DB::table('mobile_capture_attachments')
                ->whereNotNull('file_path')
                ->selectRaw("COALESCE(disk, 's3') AS disk, file_path AS path, COALESCE(size, 0) AS size"),
            DB::table('report_runs')
                ->whereNotNull('storage_path')
                ->selectRaw("COALESCE(storage_disk, 's3') AS disk, storage_path AS path, COALESCE(size, 0) AS size"),
            DB::table('tenant_export_generations')
                ->whereNotNull('storage_path')
                ->selectRaw("COALESCE(storage_disk, 's3') AS disk, storage_path AS path, COALESCE(size, 0) AS size"),
            DB::table('terreno_imports')
                ->whereNotNull('storage_path')
                ->selectRaw("COALESCE(storage_disk, 's3') AS disk, storage_path AS path, COALESCE(size, 0) AS size"),
            DB::table('terreno_polygon_import_files')
                ->whereNotNull('storage_path')
                ->selectRaw("COALESCE(storage_disk, 's3') AS disk, storage_path AS path, COALESCE(size, 0) AS size"),
        ];

        $query = array_shift($queries);
        foreach ($queries as $union) {
            $query->unionAll($union);
        }

        return $query;
    }
}
