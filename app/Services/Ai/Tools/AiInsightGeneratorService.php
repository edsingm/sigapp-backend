<?php

namespace App\Services\Ai\Tools;

use App\Repositories\Tenant\AiInsightRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Throwable;

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

    public function __construct(
        private readonly AiInsightRepository $repository,
    ) {}

    /**
     * Gera insights automáticos sobre o portfólio.
     *
     * @return array<string, mixed>
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
            'insights' => $sorted->all(),
            'version' => self::VERSION,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Analisa tendências por região, cidade e responsável.
     *
     * @return array<string, mixed>
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
     * @return array<string, mixed>
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
                $label = $item['name'] ?? $item['cidade'] ?? $item['label'] ?? $item['responsavel_id'] ?? 'item';

                return [
                    $index + 1 .'_'.$label => [
                        'score' => $item['score'],
                        'details' => $item,
                    ],
                ];
            });

        return [
            'dimension' => $dimension,
            'comparison' => $comparison,
            'ranking' => $ranking->all(),
            'summary' => [
                'total_items' => count($comparison['items']),
                'best_performer' => $ranking->first()['details'] ?? null,
                'worst_performer' => $ranking->reverse()->first()['details'] ?? null,
            ],
        ];
    }

    // ── Insights ─────────────────────────────────────────────────────

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function conversionRateInsights(): Collection
    {
        $total = $this->repository->countActive();
        $finalizados = $this->repository->countByStatus('legalizado_finalizado');
        $descartados = $this->repository->countByStatus('descartado');
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
     * @return Collection<int, array<string, mixed>>
     */
    protected function topCitiesInsights(): Collection
    {
        $insights = collect();

        $citiesData = $this->repository->topCitiesByCount(5);

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

        $topVgvCities = $this->repository->topCitiesByViabilidade(3);

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
     * @return Collection<int, array<string, mixed>>
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
     * @return Collection<int, array<string, mixed>>
     */
    protected function bottleneckInsights(): Collection
    {
        $insights = collect();

        $stageCounts = $this->repository->stageCounts();

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
     * @return Collection<int, array<string, mixed>>
     */
    protected function temporalEvolutionInsights(): Collection
    {
        $insights = collect();

        // Cadastros últimos 3 meses vs anteriores
        $recent3Months = $this->repository->countCreatedSince(now()->subMonths(3));
        $previous3Months = $this->repository->countCreatedBetween(now()->subMonths(6), now()->subMonths(3));

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
     * @return Collection<int, array<string, mixed>>
     */
    protected function riskConcentrationInsights(): Collection
    {
        $insights = collect();

        $responsaveis = $this->getResponsavelStats();
        $totalActive = $this->repository->countFullyActive();

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
     * @return array<int, array<string, mixed>>
     */
    protected function getTrendsByCity(): array
    {
        return $this->repository->trendsByCity(20)
            ->map(fn ($row) => [
                'cidade' => $row->cidade_code,
                'total_terrenos' => $row->total_terrenos,
                'finalizados' => $row->finalizados,
                'avg_valor' => round($row->avg_valor, 2),
                'last_cadastro' => $this->toIsoDateTime($row->last_cadastro),
            ])
            ->all();
    }

    protected function toIsoDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        try {
            return CarbonImmutable::parse((string) $value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getTrendsByResponsavel(): array
    {
        return $this->getResponsavelStats()
            ->map(fn (object $row) => [
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
     * @return array<int, array<string, mixed>>
     */
    protected function getMonthlyTrends(): array
    {
        $months = $this->repository->monthlyTrends(now()->subMonths(12))
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
     * @return array{items: array<int, array<string, mixed>>, metrics: array<string, mixed>}
     */
    protected function compareByResponsavel(int $limit): array
    {
        $stats = $this->getResponsavelStats();

        $items = $stats->map(function (object $row): array {
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
        })->values()->all();

        usort(
            $items,
            fn ($a, $b): int => (float) $b['score'] <=> (float) $a['score']
        );

        $avgApprovalRate = collect($items)
            ->map(fn (array $item): float => (float) $item['approval_rate'])
            ->average() ?? 0;

        return [
            'items' => $items,
            'metrics' => [
                'total_responsaveis' => count($items),
                'avg_approval_rate' => count($items) > 0
                    ? round((float) $avgApprovalRate, 1)
                    : 0,
            ],
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, metrics: array<string, mixed>}
     */
    protected function compareByCity(int $limit): array
    {
        /** @var array<int, array<string, mixed>> $data */
        $data = $this->repository->comparisonByCity()
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
            })->values()->all();

        usort(
            $data,
            fn ($a, $b): int => (float) $b['score'] <=> (float) $a['score']
        );

        $avgCompletionRate = collect($data)
            ->map(fn (array $item): float => (float) $item['completion_rate'])
            ->average() ?? 0;

        return [
            'items' => $data,
            'metrics' => [
                'total_cities' => count($data),
                'avg_completion_rate' => count($data) > 0
                    ? round((float) $avgCompletionRate, 1)
                    : 0,
            ],
        ];
    }

    /**
     * @return Collection<int, object{responsavel_id: int|null, name: string, total: int, aprovados: int, em_analise: int, descartados: int}>
     */
    protected function getResponsavelStats(): Collection
    {
        return $this->repository->responsavelStats(20);
    }
}
