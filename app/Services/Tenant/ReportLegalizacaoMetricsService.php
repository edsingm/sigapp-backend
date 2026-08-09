<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Métricas ricas de legalização para o report builder (custos pagos e caminho crítico).
 * Usa Query Builder (sem SQL livre do cliente) e reutiliza a lógica de critical path.
 */
class ReportLegalizacaoMetricsService
{
    public function __construct(
        private readonly LegalizacaoInsightService $insights,
    ) {}

    /**
     * @param  list<int>  $legalizacaoIds
     * @return array<int, array{custo_planejado: float, custo_realizado: float, critical_path_days: int|null, cycle_detected: bool}>
     */
    public function metricsByLegalizacao(array $legalizacaoIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $legalizacaoIds,
            static fn (int $id): bool => $id > 0,
        )));
        if ($ids === []) {
            return [];
        }

        $etapas = DB::table('legalizacao_etapas')
            ->whereIn('legalizacao_id', $ids)
            ->whereNull('deleted_at')
            ->get(['legalizacao_id', 'custos', 'valor_custo', 'custo_pago', 'tipo_custo']);

        /** @var array<int, array{custo_planejado: float, custo_realizado: float}> $costs */
        $costs = [];
        foreach ($ids as $id) {
            $costs[$id] = ['custo_planejado' => 0.0, 'custo_realizado' => 0.0];
        }

        foreach ($etapas as $etapa) {
            $legalizacaoId = (int) $etapa->legalizacao_id;
            $items = $this->normalizeCustos($etapa);
            $planned = 0.0;
            $realized = 0.0;
            foreach ($items as $item) {
                $value = (float) ($item['valor_custo'] ?? 0);
                $planned += $value;
                if (! empty($item['custo_pago'])) {
                    $realized += $value;
                }
            }
            $costs[$legalizacaoId]['custo_planejado'] = ($costs[$legalizacaoId]['custo_planejado'] ?? 0.0) + $planned;
            $costs[$legalizacaoId]['custo_realizado'] = ($costs[$legalizacaoId]['custo_realizado'] ?? 0.0) + $realized;
        }

        $result = [];
        foreach ($ids as $id) {
            $critical = $this->safeCriticalPath($id);
            $result[$id] = [
                'custo_planejado' => round($costs[$id]['custo_planejado'], 2),
                'custo_realizado' => round($costs[$id]['custo_realizado'], 2),
                'critical_path_days' => $critical['total_days'],
                'cycle_detected' => $critical['cycle_detected'],
            ];
        }

        return $result;
    }

    /**
     * Agrega métricas por rótulo de dimensão (ex.: status).
     *
     * @param  Collection<int, object>  $legalizacoes  rows with id + dimension column
     * @return array<string, array{count: int, sum_custo_planejado: float, sum_custo_realizado: float, sum_critical_days: float|null, avg_critical_days: float|null}>
     */
    public function aggregateByDimension(Collection $legalizacoes, string $dimensionColumn): array
    {
        $ids = array_values($legalizacoes->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());
        $metrics = $this->metricsByLegalizacao($ids);

        $groups = [];
        foreach ($legalizacoes as $row) {
            $label = data_get($row, $dimensionColumn);
            $label = $label === null || $label === '' ? 'Não informado' : (string) $label;
            $id = (int) data_get($row, 'id');
            $m = $metrics[$id] ?? [
                'custo_planejado' => 0.0,
                'custo_realizado' => 0.0,
                'critical_path_days' => null,
                'cycle_detected' => false,
            ];

            if (! isset($groups[$label])) {
                $groups[$label] = [
                    'count' => 0,
                    'sum_custo_planejado' => 0.0,
                    'sum_custo_realizado' => 0.0,
                    'sum_critical_days' => 0.0,
                    'critical_samples' => 0,
                ];
            }
            $groups[$label]['count']++;
            $groups[$label]['sum_custo_planejado'] += $m['custo_planejado'];
            $groups[$label]['sum_custo_realizado'] += $m['custo_realizado'];
            if ($m['critical_path_days'] !== null && ! $m['cycle_detected']) {
                $groups[$label]['sum_critical_days'] += $m['critical_path_days'];
                $groups[$label]['critical_samples']++;
            }
        }

        $out = [];
        foreach ($groups as $label => $group) {
            $samples = (int) $group['critical_samples'];
            $out[$label] = [
                'count' => (int) $group['count'],
                'sum_custo_planejado' => round((float) $group['sum_custo_planejado'], 2),
                'sum_custo_realizado' => round((float) $group['sum_custo_realizado'], 2),
                'sum_critical_days' => $samples > 0 ? round((float) $group['sum_critical_days'], 2) : null,
                'avg_critical_days' => $samples > 0
                    ? round((float) $group['sum_critical_days'] / $samples, 2)
                    : null,
            ];
        }

        return $out;
    }

    /**
     * @return array{total_days: int|null, cycle_detected: bool}
     */
    private function safeCriticalPath(int $legalizacaoId): array
    {
        try {
            $path = $this->insights->criticalPath($legalizacaoId);
            $total = $path['total_days'] ?? null;

            return [
                'total_days' => is_numeric($total) ? (int) $total : null,
                'cycle_detected' => (bool) ($path['cycle_detected'] ?? false),
            ];
        } catch (\Throwable) {
            return ['total_days' => null, 'cycle_detected' => false];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeCustos(object $etapa): array
    {
        $raw = data_get($etapa, 'custos');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (is_array($raw) && $raw !== []) {
            /** @var list<array<string, mixed>> $items */
            $items = array_values(array_filter($raw, 'is_array'));

            return $items;
        }

        $valorCusto = data_get($etapa, 'valor_custo');
        if ($valorCusto !== null) {
            return [[
                'tipo_custo' => data_get($etapa, 'tipo_custo'),
                'valor_custo' => $valorCusto,
                'custo_pago' => (bool) (data_get($etapa, 'custo_pago') ?? false),
            ]];
        }

        return [];
    }
}
