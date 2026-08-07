<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Notifications\StorageLimitApproachingNotification;
use App\Services\PlanMatrixService;
use App\Services\UsageMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckTenantStorageUsageCommand extends Command
{
    protected $signature = 'tenant:check-storage-usage';

    protected $description = 'Verifica o uso de armazenamento de cada tenant e notifica quando o limite do plano estiver próximo';

    public function handle(PlanMatrixService $planMatrix, UsageMetricsService $usageService): int
    {
        $total = 0;

        Tenant::query()
            ->where('status', Tenant::STATUS_ACTIVE)
            ->whereNotNull('plan_id')
            ->get()
            ->each(function (Tenant $tenant) use ($planMatrix, $usageService, &$total) {
                try {
                    if ($planMatrix->isUnlimitedLimitForTenant($tenant, 'storage_gb')) {
                        if ($tenant->storage_alert_threshold !== 0) {
                            $tenant->update(['storage_alert_threshold' => 0]);
                        }

                        return;
                    }

                    $limitGb = (int) $planMatrix->getLimitForTenant($tenant, 'storage_gb');
                    if ($limitGb <= 0) {
                        return;
                    }

                    $usedBytes = 0;
                    $tenant->run(function () use ($usageService, &$usedBytes) {
                        $usedBytes = $usageService->getStorageUsedBytes();
                    });

                    $usedGb = $usedBytes / (1024 * 1024 * 1024);
                    $percentage = ($usedGb / $limitGb) * 100;

                    $newThreshold = match (true) {
                        $percentage >= 90 => 90,
                        $percentage >= 80 => 80,
                        default => 0,
                    };

                    if ($newThreshold === $tenant->storage_alert_threshold) {
                        return;
                    }

                    if ($newThreshold > $tenant->storage_alert_threshold) {
                        $tenant->notify(new StorageLimitApproachingNotification(
                            $tenant->name,
                            $usedGb,
                            $limitGb,
                            $newThreshold,
                        ));
                        $total++;
                    }

                    $tenant->update(['storage_alert_threshold' => $newThreshold]);
                } catch (\Throwable $exception) {
                    Log::warning('Erro ao verificar uso de armazenamento do tenant', [
                        'tenant_id' => $tenant->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });

        $this->info("Notificações de armazenamento enviadas: {$total}");

        return self::SUCCESS;
    }
}
