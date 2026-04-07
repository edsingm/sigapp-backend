<?php

namespace App\Services;

use App\Models\Tenant\Cidade;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Serviço de geração de insights e análise avançada do portfólio.
 *
 * Fornece:
 * - Tendências e padrões por região, cidade e responsável
 * - Insights automáticos acionáveis
 * - Comparação de performance entre áreas
 *
 * Version 1: análise estatística e heurística.
 */
class AiInsightGeneratorService
{
    public const VERSION = '1.0.0';

    /**
     * Gera insights automáticos sobre o portfólio.
     *
     * @return array{total_insights: int, insights: array, generated_at: string}
     */
    public function generateInsights(int $limit = 20): array
    {
        $insights = collect();

        // Insight: taxa de conversão por etapa
        $insights = $insights->merge($this->conversionRateInsights());

        // Insight: top cidades por VGV
        $insights = $insights->merge($this->topCitiesInsights());

        // Insight: top responsáveis por volume
        $insights = $insights->merge($this->topResponsaveisInsights());

        // Insight: gargalos de workflow
        $insights = $insights->merge($this->bottleneckInsights());

        // Insight: evolução temporal
        $insights = $insights->merge($this->temporalEvolutionInsights());

        // Insight: concentração de risco
        $insights = $insights->merge($this->riskConcentrationInsights());

        // Ordena por importância
        $sorted = $insights->sortByDesc('importance')->values()->take($limit);

        return [
            'total_insights' => $sorted->count(),
            'insights' => $sorted,
            'version' => self::VERSION,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Analisa tendências por região, cidade e responsável.
     *
     * @return array{by_city: array, by_state: array, by_responsavel: array, monthly_trend: array}
     */
    public function getTrends(?string $dimension = null): array
    {
        $result = [
            'version' => self::VERSION,
        ];

        if ($dimension === null || $dimension === 'city') {
            $result['by_city'] = $this->getTrendsByCity();
        }

        if ($dimension === null || $dimension === 'responsavel') {
            $result['by_responsavel'] = $this->getTrendsByResponsavel();
        }

        if ($dimension === null || $dimension === 'monthly') {
            $result['monthly_trend'] = $this->getMonthlyTrends();
        }

        return $result;
    }

    /**
     * Compara performance entre áreas/responsáveis.
     *
     * @return array{comparison: array, ranking: array, summary: array}
     */
    public function compareAreas(?string $dimension = 'responsavel', int $limit = 20): array
    {
        if ($dimension === 'cidade') {
            $comparison = $this->compareByCity($limit);
        } elseif ($dimension === 'responsavel') {
            $comparison = $this->compareByResponsavel($limit);
        } else {
            $comparison = $this->compareByResponsavel($limit);
        }

        $ranking = collect($comparison['items'])
            ->sortByDesc('score')
            ->values()
            ->take(10)
            ->mapWithKeys(function (array $item, int $index) {
                return [
                    $index + 1 .'_'.$item['name'] => [
                        'score' => $item['score'],
                        'details' => $item,
                    ],
                ];
            });

        return [
            'dimension' => $dimension,
            'comparison' => $comparison,
            'ranking' => $ranking,
            'summary' => [
                'total_items' => count($comparison['items'] ?? []),
                'best_performer' => $ranking->first()['details'] ?? null,
                'worst_performer' => $ranking->reverse()->first()['details'] ?? null,
            ],
        ];
    }

    // ── Insights ─────────────────────────────────────────────────────

    /**
     * @return Collection<int, array>
     */
    protected function conversionRateInsights(): Collection
    {
        $total = Terreno::whereNotIn('workflow_status_code', ['descartado', 'arquivado'])->count();
        $finalizados = Terreno::where('workflow_status_code', 'legalizado_finalizado')->count();
        $descartados = Terreno::where('workflow_status_code', 'descartado')->count();
        $ativos = $total;

        $conversionRate = ($total + $finalizados + $descartados) > 0
            ? round(($finalizados / ($total + $finalizados + $descartados)) * 100, 1)
            : 0;

        $insights = collect();

        if ($conversionRate > 0) {
            $insights->push([
                'type' => 'conversion_rate',
                'importance' => 90,
                'title' => 'Taxa de conversão do pipeline',
                'message' => "{$conversionRate}% dos terrenos concluem o fluxo (legalizado).",
                'suggestion' => $conversionRate < 20
                    ? 'Taxa baixa — revisar critérios de captação.'
                    : 'Taxa saudável — manter processo atual.',
            ]);
        }

        if ($descartados > 0 && $total > 0) {
            $discardRate = round(($descartados / ($total + $descartados)) * 100, 1);
            if ($discardRate > 30) {
                $insights->push([
                    'type' => 'high_discard_rate',
                    'importance' => 80,
                    'title' => 'Alta taxa de descarte',
                    'message' => "{$discardRate}% dos terrenos foram descartados.",
                    'suggestion' => 'Revisar critérios iniciais de captação para reduzir desperdício.',
                ]);
            }
        }

        return $insights;
    }

    /**
     * @return Collection<int, array>
     */
    protected function topCitiesInsights(): Collection
    {
        $insights = collect();

        $citiesData = Terreno::query()
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado'])
            ->whereNotNull('cidade_code')
            ->selectRaw('cidade_code, COUNT(*) as total')
            ->groupBy('cidade_code')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        if ($citiesData->isNotEmpty()) {
            $topCity = $citiesData->first();
            $insights->push([
                'type' => 'top_city',
                'importance' => 70,
                'title' => 'Cidade com mais terrenos',
                'message' => "Cidade {$topCity->cidade_code} lidera com {$topCity->total} terrenos ativos.",
                'suggestion' => 'Priorizar alocação de recursos para atender alta demanda.',
            ]);
        }

        $topVgvCities = Viabilidade::query()
            ->withTrashed()
            ->join('terrenos', 'terrenos.id', '=', 'viabilidades.terreno_id')
            ->whereNotNull('terrenos.cidade_code')
            ->whereNotNull('resultados_dre')
            ->selectRaw('terrenos.cidade_code as cidade, COUNT(*) as total_viabs')
            ->groupBy('terrenos.cidade_code')
            ->orderByDesc('total_viabs')
            ->limit(3)
            ->get();

        if ($topVgvCities->isNotEmpty()) {
            $insights->push([
                'type' => 'top_vgv_city',
                'importance' => 65,
                'title' => 'Cidade com mais viabilidades',
                'message' => "Cidade {$topVgvCities->first()->cidade} tem {$topVgvCities->first()->total_viabs} viabilidades registradas.",
                'suggestion' => 'Cidade estratégica — manter atenção e investimento.',
            ]);
        }

        return $insights;
    }

    /**
     * @return Collection<int, array>
     */
    protected function topResponsaveisInsights(): Collection
    {
        $insights = collect();

        $responsaveis = $this->getResponsavelStats();

        if ($responsaveis->isNotEmpty()) {
            $topResp = $responsaveis->first();
            $insights->push([
                'type' => 'top_performer',
                'importance' => 60,
                'title' => 'Responsável com mais terrenos',
                'message' => "{$topResp->name} lidera com {$topResp->total} terrenos.",
                'suggestion' => $topResp->total > 20
                    ? 'Possível sobrecarga — redistribuir terrenos.'
                    : 'Alta produtividade — considerar expandir escopo.',
            ]);
        }

        return $insights;
    }

    /**
     * @return Collection<int, array>
     */
    protected function bottleneckInsights(): Collection
    {
        $insights = collect();

        $stageCounts = Terreno::query()
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->selectRaw('workflow_stage, COUNT(*) as count')
            ->groupBy('workflow_stage')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->workflow_stage => $row->count]);

        if ($stageCounts->isNotEmpty()) {
            $biggestBottleneck = $stageCounts->sortDesc()->first(fn ($count) => $count > 0);

            if ($biggestBottleneck > 0) {
                $stageName = $stageCounts->sortDesc()->keys()->first();
                $insights->push([
                    'type' => 'bottleneck',
                    'importance' => 85,
                    'title' => 'Gargalo no workflow',
                    'message' => "{$stageName} é o maior gargalo com {$biggestBottleneck} terrenos parados.",
                    'suggestion' => match ($stageName) {
                        'captacao' => 'Aumentar velocidade de captação.',
                        'viabilidade' => 'Acelerar criação de viabilidades.',
                        'comite' => 'Agendar comitês com mais frequência.',
                        'negociacao_contrato' => 'Priorizar negociações paradas.',
                        'legalizacao' => 'Contratar mais equipe de legalização.',
                        default => 'Analisar causas do gargalo.',
                    },
                ]);
            }
        }

        return $insights;
    }

    /**
     * @return Collection<int, array>
     */
    protected function temporalEvolutionInsights(): Collection
    {
        $insights = collect();

        // Cadastros últimos 3 meses vs anteriores
        $recent3Months = Terreno::where('created_at', '>=', now()->subMonths(3))->count();
        $previous3Months = Terreno::whereBetween('created_at', [now()->subMonths(6), now()->subMonths(3)])->count();

        if ($previous3Months > 0 && $recent3Months > 0) {
            $growthRate = round((($recent3Months - $previous3Months) / $previous3Months) * 100, 1);
            $direction = $growthRate >= 0 ? 'crescimento' : 'retração';
            $insights->push([
                'type' => 'growth_rate',
                'importance' => 75,
                'title' => "Taxa de {$direction} de cadastros",
                'message' => "{$direction} de {$growthRate}% nos cadastros (últimos 3 meses vs anteriores).",
                'suggestion' => $growthRate >= 0
                    ? 'Manter ritmo de captação.'
                    : 'Investigar causas da retração.',
            ]);
        }

        return $insights;
    }

    /**
     * @return Collection<int, array>
     */
    protected function riskConcentrationInsights(): Collection
    {
        $insights = collect();

        $responsaveis = $this->getResponsavelStats();
        $totalActive = Terreno::whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])->count();

        if ($totalActive > 0 && $responsaveis->isNotEmpty()) {
            $topResp = $responsaveis->first();
            $concentration = round(($topResp->total / $totalActive) * 100, 1);

            if ($concentration > 40) {
                $insights->push([
                    'type' => 'risk_concentration',
                    'importance' => 90,
                    'title' => 'Concentração de risco por responsável',
                    'message' => "{$topResp->name} concentra {$concentration}% dos terrenos ativos.",
                    'suggestion' => 'Redistribuir terrenos para reduzir dependência de uma pessoa.',
                ]);
            }
        }

        return $insights;
    }

    // ── Trends ───────────────────────────────────────────────────────

    /**
     * @return array<int, array>
     */
    protected function getTrendsByCity(): array
    {
        return Terreno::query()
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado'])
            ->whereNotNull('cidade_code')
            ->selectRaw('
                cidade_code,
                COUNT(*) as total_terrenos,
                COUNT(CASE WHEN workflow_status_code = "legalizado_finalizado" THEN 1 END) as finalizados,
                AVG(COALESCE(valor, 0)) as avg_valor,
                MAX(created_at) as last_cadastro
            ')
            ->groupBy('cidade_code')
            ->orderByDesc('total_terrenos')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'cidade' => $row->cidade_code,
                'total_terrenos' => $row->total_terrenos,
                'finalizados' => $row->finalizados,
                'avg_valor' => round($row->avg_valor, 2),
                'last_cadastro' => $row->last_cadastro?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array>
     */
    protected function getTrendsByResponsavel(): array
    {
        return $this->getResponsavelStats()
            ->map(fn ($row) => [
                'responsavel_id' => $row->responsavel_id,
                'name' => $row->name,
                'total_terrenos' => $row->total,
                'aprovados' => $row->aprovados,
                'em_analise' => $row->em_analise,
                'descartados' => $row->descartados,
                'aprovacao_rate' => $row->total > 0 ? round(($row->aprovados / $row->total) * 100, 1) : 0,
            ])
            ->all();
    }

    /**
     * @return array<int, array>
     */
    protected function getMonthlyTrends(): array
    {
        $months = Terreno::query()
            ->selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                COUNT(*) as cadastros,
                COUNT(CASE WHEN workflow_stage = "captacao" THEN 1 END) as captacoes,
                COUNT(CASE WHEN workflow_status_code IN ("descartado", "arquivado") THEN 1 END) as descarte
            ')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'cadastros' => $row->cadastros,
                'captacoes' => $row->captacoes,
                'descartes' => $row->descarte,
            ])
            ->all();

        return $months;
    }

    // ── Comparisons ──────────────────────────────────────────────────

    /**
     * @return array{items: array, metrics: array}
     */
    protected function compareByResponsavel(int $limit): array
    {
        $stats = $this->getResponsavelStats();

        $items = $stats->map(function ($row) {
            $approvalRate = $row->total > 0 ? ($row->aprovados / $row->total) * 100 : 0;
            $discardRate = $row->total > 0 ? ($row->descartados / $row->total) * 100 : 0;

            return [
                'responsavel_id' => $row->responsavel_id,
                'name' => $row->name,
                'total' => $row->total,
                'aprovados' => $row->aprovados,
                'em_analise' => $row->em_analise,
                'descartados' => $row->descartados,
                'approval_rate' => round($approvalRate, 1),
                'discard_rate' => round($discardRate, 1),
                'score' => round(($approvalRate * 0.6) + min(40, $row->total * 2), 1),
            ];
        })->sortByDesc('score')->values()->all();

        return [
            'items' => $items,
            'metrics' => [
                'total_responsaveis' => count($items),
                'avg_approval_rate' => count($items) > 0
                    ? round(collect($items)->average('approval_rate'), 1)
                    : 0,
            ],
        ];
    }

    /**
     * @return array{items: array, metrics: array}
     */
    protected function compareByCity(int $limit): array
    {
        $data = Terreno::query()
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado'])
            ->whereNotNull('cidade_code')
            ->selectRaw('
                cidade_code,
                COUNT(*) as total,
                COUNT(CASE WHEN workflow_status_code = "legalizado_finalizado" THEN 1 END) as finalizados,
                COUNT(CASE WHEN workflow_status_code = "descartado" THEN 1 END) as descartados
            ')
            ->groupBy('cidade_code')
            ->get()
            ->map(function ($row) {
                $completionRate = $row->total > 0 ? ($row->finalizados / $row->total) * 100 : 0;

                return [
                    'cidade' => $row->cidade_code,
                    'total' => $row->total,
                    'finalizados' => $row->finalizados,
                    'descartados' => $row->descartados,
                    'completion_rate' => round($completionRate, 1),
                    'score' => round(($completionRate * 0.7) + min(30, $row->total * 1.5), 1),
                ];
            })->sortByDesc('score')->values()->all();

        return [
            'items' => $data,
            'metrics' => [
                'total_cities' => count($data),
                'avg_completion_rate' => count($data) > 0
                    ? round(collect($items)->average('completion_rate') ?? 0, 1)
                    : 0,
            ],
        ];
    }

    /**
     * @return Collection<int, object>
     */
    protected function getResponsavelStats(): Collection
    {
        return DB::table('terrenos')
            ->leftJoin('users', 'users.id', '=', 'terrenos.responsavel_id')
            ->selectRaw('
                terrenos.responsavel_id,
                COALESCE(users.name, "Sem responsável") as name,
                COUNT(*) as total,
                COUNT(CASE WHEN terrenos.workflow_status_code IN ("viabilidade_aprovada", "aguardando_comite", "negociacao_minuta", "contrato_assinado", "legalizando", "legalizado_finalizado") THEN 1 END) as aprovados,
                COUNT(CASE WHEN terrenos.workflow_status_code = "em_analise" THEN 1 END) as em_analise,
                COUNT(CASE WHEN terrenos.workflow_status_code IN ("descartado", "arquivado") THEN 1 END) as descartados
            ')
            ->whereNotIn('terrenos.workflow_status_code', ['descartado', 'arquivado'])
            ->groupBy('terrenos.responsavel_id', 'users.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get();
    }
}
