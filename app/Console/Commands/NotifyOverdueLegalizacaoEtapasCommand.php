<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Tenant\MobilePushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyOverdueLegalizacaoEtapasCommand extends Command
{
    protected $signature = 'tenant:notify-overdue-legalizacao-etapas';

    protected $description = 'Notifica usuários do tenant sobre etapas de legalização atrasadas';

    public function handle(MobilePushService $mobilePushService): int
    {
        $total = 0;

        Tenant::query()
            ->where('status', Tenant::STATUS_ACTIVE)
            ->get()
            ->each(function (Tenant $tenant) use ($mobilePushService, &$total) {
                try {
                    $tenant->run(function () use ($mobilePushService, &$total) {
                        $total += $mobilePushService->notifyOverdueLegalizacaoEtapasForCurrentTenant();
                    });
                } catch (\Throwable $exception) {
                    Log::warning('Erro ao notificar etapas atrasadas do tenant', [
                        'tenant_id' => $tenant->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });

        $this->info("Notificações processadas: {$total}");

        return self::SUCCESS;
    }
}
