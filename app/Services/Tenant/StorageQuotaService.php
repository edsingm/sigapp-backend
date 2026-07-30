<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Exceptions\StorageQuotaExceededException;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\UsageMetricsRepositoryInterface;
use App\Services\PlanMatrixService;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StorageQuotaService
{
    public function __construct(
        private readonly UsageMetricsRepositoryInterface $usage,
        private readonly PlanMatrixService $planMatrix,
    ) {}

    /**
     * Verifica a quota e registra o arquivo enquanto o lock do tenant permanece ativo.
     *
     * @template TResult
     *
     * @param  Closure(int): TResult  $persist
     * @return TResult
     */
    public function commitFile(string $diskName, string $path, Closure $persist): mixed
    {
        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            throw new RuntimeException('O arquivo gerado não foi encontrado no storage.');
        }

        $size = (int) $disk->size($path);
        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            try {
                return $persist($size);
            } catch (Throwable $exception) {
                $disk->delete($path);

                throw $exception;
            }
        }

        $lock = Cache::lock("plan-limit:{$tenant->getTenantKey()}:storage_gb", 30);
        if (! $lock->get()) {
            $disk->delete($path);
            throw new RuntimeException('Não foi possível adquirir o lock do limite de armazenamento.');
        }

        try {
            if (! $this->planMatrix->isUnlimitedLimitForTenant($tenant, 'storage_gb')) {
                $usage = $this->usage->storageUsageForObject($diskName, $path);
                $limit = $this->planMatrix->getLimitForTenant($tenant, 'storage_gb') * 1024 * 1024 * 1024;

                if (($usage['used'] - $usage['previous'] + $size) > $limit) {
                    $disk->delete($path);
                    throw new StorageQuotaExceededException;
                }
            }

            return $persist($size);
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        } finally {
            $lock->release();
        }
    }
}
