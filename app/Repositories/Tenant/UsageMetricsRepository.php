<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\AiGeneratedReport;
use App\Models\Tenant\Documento;
use App\Models\Tenant\DocumentVersion;
use App\Models\Tenant\MobileCaptureAttachment;
use App\Models\Tenant\Produto;
use App\Models\Tenant\ReportRun;
use App\Models\Tenant\TenantExportGeneration;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Repositories\Contracts\UsageMetricsRepositoryInterface;

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
        return array_sum(array_column($this->storageObjects(), 'size'));
    }

    public function storageObjects(): array
    {
        $objects = [];

        $this->append(
            $objects,
            Documento::query()
                ->whereNotNull('file_path')
                ->get(['file_path', 'tamanho'])
                ->map(fn (Documento $item): array => [
                    'disk' => 's3',
                    'path' => (string) $item->file_path,
                    'size' => (int) $item->getAttribute('tamanho'),
                ])->all(),
        );

        $this->append(
            $objects,
            DocumentVersion::query()
                ->whereNotNull('file_path')
                ->get(['file_path', 'disk', 'size'])
                ->map(fn (DocumentVersion $item): array => [
                    'disk' => (string) $item->disk,
                    'path' => (string) $item->file_path,
                    'size' => (int) $item->size,
                ])->all(),
        );

        $this->append(
            $objects,
            AiGeneratedReport::query()
                ->whereNotNull('file_path')
                ->get(['file_path', 'tamanho'])
                ->map(fn (AiGeneratedReport $item): array => [
                    'disk' => 's3',
                    'path' => (string) $item->getAttribute('file_path'),
                    'size' => (int) $item->getAttribute('tamanho'),
                ])->all(),
        );

        $this->append(
            $objects,
            MobileCaptureAttachment::query()
                ->whereNotNull('file_path')
                ->get(['file_path', 'disk', 'size'])
                ->map(fn (MobileCaptureAttachment $item): array => [
                    'disk' => (string) $item->disk,
                    'path' => (string) $item->file_path,
                    'size' => (int) $item->size,
                ])->all(),
        );

        $this->append(
            $objects,
            ReportRun::query()
                ->whereNotNull('storage_path')
                ->get(['storage_path', 'storage_disk', 'size'])
                ->map(fn (ReportRun $item): array => [
                    'disk' => (string) $item->storage_disk,
                    'path' => (string) $item->storage_path,
                    'size' => (int) $item->size,
                ])->all(),
        );

        $this->append(
            $objects,
            TenantExportGeneration::query()
                ->whereNotNull('storage_path')
                ->get(['storage_path', 'storage_disk', 'size'])
                ->map(fn (TenantExportGeneration $item): array => [
                    'disk' => (string) $item->storage_disk,
                    'path' => (string) $item->storage_path,
                    'size' => (int) $item->size,
                ])->all(),
        );

        return $objects;
    }

    /**
     * @param  array<string, array{disk: string, path: string, size: int}>  $objects
     * @param  array<int, array{disk: string, path: string, size: int}>  $rows
     */
    private function append(array &$objects, array $rows): void
    {
        foreach ($rows as $row) {
            if ($row['disk'] === '' || $row['path'] === '') {
                continue;
            }

            $key = $row['disk']."\0".$row['path'];
            $objects[$key] = [
                'disk' => $row['disk'],
                'path' => $row['path'],
                'size' => max($objects[$key]['size'] ?? 0, $row['size']),
            ];
        }
    }
}
