<?php

namespace App\Console\Commands;

use App\Models\Tenant\Terreno;
use App\Services\AiScoringService;
use Illuminate\Console\Command;

class RecalculateAiScoresCommand extends Command
{
    protected $signature = 'ai:recalculate-scores
                            {--terreno-id= : Calcula score apenas para um terreno específico}';

    protected $description = 'Recalcula scores de priorização de terrenos (Fase 3)';

    public function handle(AiScoringService $scoringService): int
    {
        $terrenoId = $this->option('terreno-id');

        if ($terrenoId) {
            $terreno = Terreno::find($terrenoId);
            if (! $terreno) {
                $this->error("Terreno {$terrenoId} não encontrado.");

                return Command::FAILURE;
            }

            $result = $scoringService->score($terreno);
            $this->info("Score do terreno {$terreno->nome}: {$result['score']} ({$result['tier']})");

            return Command::SUCCESS;
        }

        $this->info('Recalculando scores para todos os terrenos...');

        $results = $scoringService->scoreAll();

        $this->table(
            ['Terreno', 'Score', 'Tier'],
            array_map(
                fn ($r): array => [$r['nome'], number_format($r['score'], 2), $r['tier']],
                $results
            )
        );

        $this->info("{$results['total']} terrenos classificados.");

        return Command::SUCCESS;
    }
}
