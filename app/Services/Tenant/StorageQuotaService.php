<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Exceptions\StorageQuotaExceededException;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\UsageMetricsRepositoryInterface;
use App\Services\PlanMatrixService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StorageQuotaService
{
    public function __construct(
        private readonly UsageMetricsRepositoryInterface $usage,
        private readonly PlanMatrixService $planMatrix,
    ) {}

    public function assertGeneratedFileFits(string $diskName, string $path): int
    {
        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            throw new RuntimeException('O arquivo gerado não foi encontrado no storage.');
        }

        $size = (int) $disk->size($path);
        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return $size;
        }

        $lock = Cache::lock("plan-limit:{$tenant->getTenantKey()}:storage_gb", 30);
        if (! $lock->get()) {
            $disk->delete($path);
            throw new RuntimeException('Não foi possível adquirir o lock do limite de armazenamento.');
        }

        try {
            if ($this->planMatrix->isUnlimitedLimitForTenant($tenant, 'storage_gb')) {
                return $size;
            }

            $objects = $this->usage->storageObjects();
            $key = $diskName."\0".$path;
            $previousSize = $objects[$key]['size'] ?? 0;
            $used = array_sum(array_column($objects, 'size'));
            $limit = $this->planMatrix->getLimitForTenant($tenant, 'storage_gb') * 1024 * 1024 * 1024;

            if (($used - $previousSize + $size) > $limit) {
                $disk->delete($path);
                throw new StorageQuotaExceededException;
            }

            return $size;
        } finally {
            $lock->release();
        }
    }
}
