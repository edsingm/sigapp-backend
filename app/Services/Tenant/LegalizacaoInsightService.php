<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Repositories\Tenant\LegalizacaoInsightRepository;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LegalizacaoInsightService
{
    public function __construct(
        private readonly LegalizacaoInsightRepository $repository,
    ) {}

    /** @param array<string, mixed> $filters */
    public function controlCenter(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateControlCenter($filters);
    }

    /** @return array<string, mixed> */
    public function criticalPath(int $legalizacaoId): array
    {
        $legalizacao = $this->repository->forInsights($legalizacaoId);
        $stages = $legalizacao->etapas->keyBy('id');
        if ($stages->isEmpty()) {
            return [
                'cycle_detected' => false,
                'data_sufficient' => false,
                'total_days' => 0,
                'path' => [],
                'missing_stage_ids' => [],
                'message' => 'A legalização ainda não possui etapas para calcular o caminho crítico.',
            ];
        }
        $adjacency = [];
        $inDegree = array_fill_keys($stages->keys()->all(), 0);
        $predecessors = [];

        foreach ($legalizacao->dependencias as $dependency) {
            $origin = (int) $dependency->etapa_origem_id;
            $destination = (int) $dependency->etapa_destino_id;
            if (! isset($stages[$origin], $stages[$destination])) {
                continue;
            }
            $adjacency[$origin][] = $destination;
            $predecessors[$destination][] = $origin;
            $inDegree[$destination]++;
        }

        $queue = array_keys(array_filter($inDegree, fn (int $degree): bool => $degree === 0));
        $topological = [];
        while ($queue !== []) {
            $current = array_shift($queue);
            $topological[] = $current;
            foreach ($adjacency[$current] ?? [] as $next) {
                $inDegree[$next]--;
                if ($inDegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        if (count($topological) !== $stages->count()) {
            return [
                'cycle_detected' => true,
                'data_sufficient' => false,
                'total_days' => null,
                'path' => [],
                'missing_stage_ids' => [],
                'message' => 'As dependências possuem um ciclo; corrija o Gantt antes de calcular o caminho crítico.',
            ];
        }

        $distance = [];
        $parent = [];
        $missingStageIds = [];
        $stageDetails = [];
        foreach ($topological as $stageId) {
            $stage = $stages[$stageId];
            $duration = $this->durationInDays($stage->inicio_planejado, $stage->fim_planejado);
            if ($duration === null) {
                $missingStageIds[] = $stageId;
                $duration = 1;
            }

            $bestPredecessor = null;
            $bestDistance = 0;
            foreach ($predecessors[$stageId] ?? [] as $predecessorId) {
                if (($distance[$predecessorId] ?? 0) > $bestDistance) {
                    $bestDistance = $distance[$predecessorId];
                    $bestPredecessor = $predecessorId;
                }
            }

            $distance[$stageId] = $bestDistance + $duration;
            $parent[$stageId] = $bestPredecessor;
            $stageDetails[$stageId] = [
                'id' => $stageId,
                'titulo' => $stage->titulo,
                'duration_days' => $duration,
                'is_critical' => false,
            ];
        }

        $maxDistance = $distance === [] ? 0 : max($distance);
        $endStage = array_search($maxDistance, $distance, true);
        $criticalIds = [];
        while ($endStage !== false && $endStage !== null) {
            $criticalIds[] = $endStage;
            $endStage = $parent[$endStage] ?? null;
        }
        foreach ($criticalIds as $criticalId) {
            $stageDetails[$criticalId]['is_critical'] = true;
        }

        return [
            'cycle_detected' => false,
            'data_sufficient' => $missingStageIds === [],
            'total_days' => $maxDistance,
            'path' => array_reverse(array_values(array_filter($stageDetails, fn (array $stage): bool => $stage['is_critical']))),
            'missing_stage_ids' => $missingStageIds,
        ];
    }

    /** @return array<string, mixed> */
    public function costs(int $legalizacaoId): array
    {
        $legalizacao = $this->repository->forInsights($legalizacaoId);
        $byStage = [];
        $plannedTotal = 0.0;
        $realizedTotal = 0.0;

        foreach ($legalizacao->etapas as $stage) {
            $items = is_array($stage->custos) ? $stage->custos : [];
            if ($items === [] && $stage->valor_custo !== null) {
                $items = [['tipo_custo' => $stage->tipo_custo, 'valor_custo' => $stage->valor_custo, 'custo_pago' => $stage->custo_pago]];
            }

            $planned = array_sum(array_map(fn (array $item): float => (float) ($item['valor_custo'] ?? 0), $items));
            $realized = array_sum(array_map(
                fn (array $item): float => ! empty($item['custo_pago']) ? (float) ($item['valor_custo'] ?? 0) : 0.0,
                $items,
            ));
            $plannedTotal += $planned;
            $realizedTotal += $realized;
            $byStage[] = [
                'etapa_id' => $stage->id,
                'titulo' => $stage->titulo,
                'planned' => $planned,
                'committed' => null,
                'realized' => $realized,
                'committed_available' => false,
            ];
        }

        return [
            'planned' => $plannedTotal > 0 ? $plannedTotal : (float) ($legalizacao->custo_total_previsto ?? 0),
            'committed' => null,
            'realized' => $realizedTotal,
            'committed_available' => false,
            'by_stage' => $byStage,
        ];
    }

    private function durationInDays(mixed $start, mixed $end): ?int
    {
        if (! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface) {
            return null;
        }

        return max(1, (int) $start->diffInDays($end) + 1);
    }
}
