<?php

namespace App\Console\Commands;

use App\Enums\LegalizacaoEtapaStatus;
use App\Events\Tenant\LegalizacaoEtapaOverdue;
use App\Models\Central\Tenant;
use App\Repositories\Tenant\LegalizacaoEtapaRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyOverdueLegalizacaoEtapasCommand extends Command
{
    protected $signature = 'tenant:notify-overdue-legalizacao-etapas';

    protected $description = 'Notifica usuários do tenant sobre etapas de legalização atrasadas';

    public function handle(LegalizacaoEtapaRepository $repository): int
    {
        $total = 0;
        $today = now()->startOfDay();

        Tenant::query()
            ->where('status', Tenant::STATUS_ACTIVE)
            ->get()
            ->each(function (Tenant $tenant) use ($repository, $today, &$total) {
                try {
                    $tenant->run(function () use ($repository, $today, &$total) {
                        $overdue = $repository->findOverdue(
                            [
                                LegalizacaoEtapaStatus::CONCLUIDA->value,
                                LegalizacaoEtapaStatus::BLOQUEADA->value,
                            ],
                            $today,
                        );

                        $tenantSlug = (string) tenant('slug');

                        foreach ($overdue as $etapa) {
                            LegalizacaoEtapaOverdue::dispatch($etapa, $tenantSlug);
                            $total++;
                        }
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
