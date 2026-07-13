<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Terreno;
use App\Services\Ai\Tools\AiScoringService;
use Illuminate\Console\Command;

class RecalculateAiScoresCommand extends Command
{
    protected $signature = 'ai:recalculate-scores
                            {--terreno-id= : Calcula score apenas para um terreno específico}
                            {--tenant= : Limita o processamento a um tenant específico}';

    protected $description = 'Recalcula scores de priorização de terrenos (Fase 3)';

    public function handle(AiScoringService $scoringService): int
    {
        $terrenoId = $this->option('terreno-id');
        $tenantId = $this->option('tenant');
        $tenants = Tenant::query()
            ->when($tenantId, fn ($query) => $query->whereKey($tenantId), fn ($query) => $query->where('status', TenantStatus::ACTIVE->value))
            ->cursor();

        $processed = 0;
        $rows = [];

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            try {
                if ($terrenoId) {
                    $terreno = Terreno::find($terrenoId);
                    if (! $terreno) {
                        $this->warn("Terreno {$terrenoId} não encontrado no tenant {$tenant->id}.");

                        continue;
                    }

                    $result = $scoringService->score($terreno);
                    $rows[] = [$tenant->name, $terreno->nome, number_format($result['score'], 2), $result['tier']];
                    $processed++;

                    continue;
                }

                foreach ($scoringService->scoreAll() as $result) {
                    $rows[] = [$tenant->name, $result['nome'], number_format($result['score'], 2), $result['tier']];
                    $processed++;
                }
            } finally {
                tenancy()->end();
            }
        }

        $this->table(['Tenant', 'Terreno', 'Score', 'Tier'], $rows);
        $this->info($processed.' terrenos classificados.');

        return Command::SUCCESS;
    }
}
