<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use App\Services\Tenant\ReportScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunDueReportSchedulesCommand extends Command
{
    protected $signature = 'reports:run-due-schedules {--tenant= : Limita a um tenant}';

    protected $description = 'Dispara gerações de relatórios com schedule vencido em cada tenant ativo';

    public function handle(ReportScheduleService $schedules): int
    {
        $tenantOption = $this->option('tenant');
        $tenants = Tenant::query()
            ->when(
                $tenantOption,
                fn ($query) => $query->whereKey($tenantOption),
                fn ($query) => $query->where('status', TenantStatus::ACTIVE->value),
            )
            ->cursor();

        $total = 0;
        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($schedules, &$total, $tenant): void {
                    $dispatched = $schedules->dispatchDue();
                    $total += $dispatched;
                    if ($dispatched > 0) {
                        $this->info("Tenant {$tenant->id}: {$dispatched} relatório(s) enfileirado(s).");
                    }
                });
            } catch (\Throwable $exception) {
                Log::warning('Falha ao processar schedules de relatório do tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $exception->getMessage(),
                ]);
                $this->warn("Tenant {$tenant->id}: ".$exception->getMessage());
            }
        }

        $this->info("Total de runs enfileirados: {$total}");

        return self::SUCCESS;
    }
}
