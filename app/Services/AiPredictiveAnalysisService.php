<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\Terreno;
use App\Repositories\Contracts\AiPredictiveRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Serviço de análise preditiva para terrenos.
 *
 * Usa dados históricos de viabilidades e comitês para gerar
 * previsões de probabilidade de aprovação e análise comparativa.
 *
 * Version 1: análise estatística baseada em frequência e médias.
 * Version futura: modelo de regressão com dados acumulados.
 */
class AiPredictiveAnalysisService
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly AiPredictiveRepositoryInterface $repository
    ) {}

    /**
     * Calcula probabilidade de aprovação para um terreno novo.
     *
     * Baseia-se em:
     * - Taxa histórica de aprovação do tenant
     * - Viabilidades similares (mesmo tipo de produto, faixa de VGV)
     * - Tempo médio de aprovação/reprovação
     * - Motivos mais comuns de reprovação
     *
     * @return array{approval_probability: float, confidence: float, reason: string, benchmarks: array, risk_factors: array}
     */
    public function predictApprovalProbability(Terreno $terreno): array
    {
        $stats = $this->getTenantApprovalStats();
        $similarViabilities = $this->getSimilarViabilities($terreno);

        $probability = $this->calculateProbability($terreno, $stats, $similarViabilities);
        $confidence = $this->calculateConfidence($stats);
        $riskFactors = $this->identifyRiskFactors($terreno, $stats);

        return [
            'approval_probability' => round($probability, 2),
            'confidence' => round($confidence, 2),
            'version' => self::VERSION,
            'reason' => $this->generateExplanation($probability, $confidence, $stats),
            'benchmarks' => [
                'total_viabilidades' => $stats['total'],
                'aprovadas' => $stats['aprovadas'],
                'reprovadas' => $stats['reprovadas'],
                'taxa_aprovacao' => round($stats['approval_rate'], 2),
                'tempo_medio_aprovacao_dias' => $stats['avg_days_to_approval'],
                'tempo_medio_reprovacao_dias' => $stats['avg_days_to_rejection'],
            ],
            'risk_factors' => $riskFactors,
            'similar_terrenos' => [
                'total' => $similarViabilities->count(),
                'avg_vgv' => $this->formatCurrency((float) ($similarViabilities->avg('vgv') ?: 0)),
                'approval_rate' => round($this->getApprovalRate($similarViabilities), 2),
            ],
        ];
    }

    /**
     * Estatísticas de aprovação do tenant.
     *
     * @return array{total: int, aprovadas: int, reprovadas: int, pendentes: int, approval_rate: float, avg_days_to_approval: float, avg_days_to_rejection: float, top_rejection_reasons: array}
     */
    public function getTenantApprovalStats(): array
    {
        $viabilidades = $this->repository->getDecidedViabilities();

        $aprovadas = $viabilidades->filter(fn ($v) => $v->approval_status === 'aprovada');
        $reprovadas = $viabilidades->filter(fn ($v) => in_array($v->approval_status, ['reprovada', 'reprovada_comite']));
        $pendentes = $viabilidades->filter(fn ($v) => $v->approval_status === 'pendente');
        $total = $viabilidades->count();

        // Tempo médio de aprovação
        $approvalDurations = $aprovadas->filter(
            fn ($v) => $v->approval_requested_at && $v->approval_decided_at
        )->map(fn ($v) => $v->approval_requested_at->diffInDays($v->approval_decided_at));

        $avgDaysApproval = $approvalDurations->isNotEmpty()
            ? round((float) $approvalDurations->avg(), 1)
            : 0;

        // Tempo médio de reprovação
        $rejectionDurations = $reprovadas->filter(
            fn ($v) => $v->approval_requested_at && $v->approval_decided_at
        )->map(fn ($v) => $v->approval_requested_at->diffInDays($v->approval_decided_at));

        $avgDaysRejection = $rejectionDurations->isNotEmpty()
            ? round((float) $rejectionDurations->avg(), 1)
            : 0;

        // Motivos comuns de reprovação
        $rejectionReasons = collect();

        foreach ($this->repository->getComiteReviewsWithParecer() as $review) {
            $parecer = strtolower($review->parecer);
            if (str_contains($parecer, 'zona') || str_contains($parecer, 'zoneamento')) {
                $rejectionReasons->push('zoneamento');
            }
            if (str_contains($parecer, 'viabilidade') || str_contains($parecer, 'dre') || str_contains($parecer, 'financeiro')) {
                $rejectionReasons->push('viabilidade_financeira');
            }
            if (str_contains($parecer, 'document') || str_contains($parecer, 'matricula') || str_contains($parecer, 'escritura')) {
                $rejectionReasons->push('documentacao');
            }
            if (str_contains($parecer, 'ambi') || str_contains($parecer, 'licenca') || str_contains($parecer, 'meio ambiente')) {
                $rejectionReasons->push('licenca_ambiental');
            }
        }

        $topRejectionReasons = $rejectionReasons->countBy()->sortDesc()->take(5)
            ->mapWithKeys(fn ($count, $reason) => [$reason => $count])->all();

        return [
            'total' => $total,
            'aprovadas' => $aprovadas->count(),
            'reprovadas' => $reprovadas->count(),
            'pendentes' => $pendentes->count(),
            'approval_rate' => $total > 0 ? ($aprovadas->count() / max(1, $aprovadas->count() + $reprovadas->count())) * 100 : 0,
            'avg_days_to_approval' => $avgDaysApproval,
            'avg_days_to_rejection' => $avgDaysRejection,
            'top_rejection_reasons' => $topRejectionReasons,
        ];
    }

    /**
     * Encontra terrenos similares por produto, cidade ou faixa de área.
     *
     * @return Collection<int, array{terreno_id: int, approval_status: string, vgv: float|null, created_at: mixed}>
     */
    public function getSimilarViabilities(Terreno $terreno): Collection
    {
        $products = $terreno->terrenoProdutos()->pluck('produto_id')->all();

        $excludeId = $terreno->viabilidadeAtual?->id;

        $viabilidades = $this->repository->getSimilarViabilities($terreno, $products, $excludeId);

        $result = collect();
        foreach ($viabilidades as $v) {
            $dre = $v->resultados_dre;
            $vgv = $dre['indicadores']['vgv'] ?? $dre['totais']['vgv_geral'] ?? null;
            if ($vgv === null) {
                continue;
            }
            $result->push([
                'terreno_id' => $v->terreno_id,
                'approval_status' => $v->approval_status,
                'vgv' => (float) $vgv,
                'created_at' => $v->created_at,
            ]);
        }

        return $result;
    }

    /**
     * Identifica fatores de risco para um terreno específico.
     *
     * @return array<int, array{factor: string, impact: string, description: string}>
     */
    public function identifyRiskFactors(Terreno $terreno, array $stats): array
    {
        $risks = [];

        // Taxa de aprovação baixa do tenant
        if ($stats['approval_rate'] < 50 && $stats['total'] > 0) {
            $risks[] = [
                'factor' => 'low_tenant_approval_rate',
                'impact' => 'high',
                'description' => "Taxa de aprovação histórica do tenant é baixa ({$stats['approval_rate']}%).",
            ];
        }

        // Falta de dados comparativos
        if ($stats['total'] < 5) {
            $risks[] = [
                'factor' => 'insufficient_data',
                'impact' => 'medium',
                'description' => "Poucas viabilidades históricas ({$stats['total']}) para análise comparativa.",
            ];
        }

        // Terreno sem produtos cadastrados
        if (! $terreno->terrenoProdutos()->exists()) {
            $risks[] = [
                'factor' => 'no_products',
                'impact' => 'high',
                'description' => 'Sem produtos cadastrados — impossibilita comparação com viabilidades similares.',
            ];
        }

        // Ausência de corretor
        if (blank($terreno->corretor_id)) {
            $risks[] = [
                'factor' => 'no_broker',
                'impact' => 'medium',
                'description' => 'Sem corretor vinculado — pode indicar captação incompleta.',
            ];
        }

        return $risks;
    }

    /**
     * Calcula probabilidade final (0-100%).
     *
     * @param  Collection<int, array{terreno_id: int, approval_status: string, vgv: float|null, created_at: mixed}>  $similarViabilities
     */
    protected function calculateProbability(Terreno $terreno, array $stats, Collection $similarViabilities): float
    {
        // Base: taxa de aprovação do tenant
        $baseProbability = $stats['approval_rate'] ?: 50;

        // Ajuste por similaridade
        if ($similarViabilities->isNotEmpty()) {
            $similarApprovalRate = $this->getApprovalRate($similarViabilities);
            $baseProbability = ($baseProbability + $similarApprovalRate) / 2;
        }

        // Ajuste por readiness do terreno
        $criteria = [
            $terreno->proprietarios()->exists(),
            filled($terreno->corretor_id),
            $terreno->terrenoProdutos()->exists(),
            filled($terreno->valor),
            filled($terreno->area_terreno),
        ];
        $readinessScore = (collect($criteria)->filter()->count() / count($criteria)) * 100;

        // Weight: 60% historical rate + 30% similar + 10% readiness
        $final = ($baseProbability * 0.6) + ($readinessScore * 0.4);

        return max(0, min(100, round($final, 1)));
    }

    /**
     * Calcula confiança da previsão (0-100%).
     */
    protected function calculateConfidence(array $stats): float
    {
        // Confiança baseada em volume de dados
        $dataPoints = $stats['total'];

        if ($dataPoints >= 50) {
            return 85;
        } elseif ($dataPoints >= 20) {
            return 70;
        } elseif ($dataPoints >= 10) {
            return 55;
        } elseif ($dataPoints >= 5) {
            return 40;
        }

        return max(10, $dataPoints * 8);
    }

    /**
     * Gera explicação textual da previsão.
     */
    protected function generateExplanation(float $probability, float $confidence, array $stats): string
    {
        $level = $probability >= 70 ? 'alta' : ($probability >= 50 ? 'moderada' : 'baixa');
        $confLevel = $confidence >= 70 ? 'confiável' : ($confidence >= 40 ? 'moderada' : 'baixa');

        $text = "Probabilidade de aprovação {$level} ({$probability}%) com confiabilidade {$confLevel} ({$confidence}%).";

        if ($stats['total'] > 0) {
            $text .= " Baseado em {$stats['total']} viabilidades analisadas, com taxa de aprovação de {$stats['approval_rate']}%.";
        } else {
            $text .= ' Sem dados históricos suficientes para análise comparativa.';
        }

        return $text;
    }

    /**
     * Benchmark de VGV para terrenos similares.
     *
     * @return array{min: float, max: float, avg: float, median: float, p25: float, p75: float, count: int, currency_formatted: array}
     */
    public function getVgvBenchmark(Terreno $terreno): array
    {
        $similarViabilities = $this->getSimilarViabilities($terreno);

        if ($similarViabilities->isEmpty()) {
            return [
                'available' => false,
                'message' => 'Sem dados de terrenos similares para benchmark.',
                'suggestion' => 'Cadastre mais viabilidades para melhorar a análise.',
            ];
        }

        $values = $similarViabilities->pluck('vgv')->sort()->values();
        $count = $values->count();
        $sum = $values->sum();
        $avg = $sum / max(1, $count);

        return [
            'available' => true,
            'min' => round((float) $values->first(), 2),
            'max' => round((float) $values->last(), 2),
            'avg' => round($avg, 2),
            'median' => round((float) $values->get((int) ($count / 2)) ?: 0, 2),
            'p25' => round((float) $values->get((int) ($count * 0.25)) ?: 0, 2),
            'p75' => round((float) $values->get((int) ($count * 0.75)) ?: 0, 2),
            'count' => $count,
            'std_dev' => round($this->standardDeviation($values), 2),
            'currency_formatted' => [
                'min' => $this->formatCurrency((float) $values->first()),
                'max' => $this->formatCurrency((float) $values->last()),
                'avg' => $this->formatCurrency($avg),
            ],
        ];
    }

    /**
     * Estatísticas de stalling do tenant.
     *
     * @return array{total_stalled: int, avg_stall_days: float, most_common_stalling_stage: int|string|null, stalling_rate: float}
     */
    public function getStallingForecast(): array
    {
        $threshold = now()->subDays(60);

        $stalled = $this->repository->getStalledTerrains($threshold);
        $totalActive = $this->repository->getActiveTerrainsCount();

        $stageCounts = $stalled->countBy('workflow_stage')->sortDesc();
        $mostCommonStage = $stageCounts->isNotEmpty() ? $stageCounts->keys()->first() : null;

        $avgStallDays = $stalled->isNotEmpty()
            ? round((float) $stalled->map(fn ($t) => $t->updated_at->diffInDays(now()))->avg(), 1)
            : 0;

        // Identificar terrenos em risco
        $atRisk = $this->identifyAtRiskTerrains();

        return [
            'total_stalled' => $stalled->count(),
            'total_active' => $totalActive,
            'stalling_rate' => $totalActive > 0 ? round(($stalled->count() / $totalActive) * 100, 1) : 0,
            'avg_stall_days' => $avgStallDays,
            'most_common_stalling_stage' => $mostCommonStage,
            'stage_distribution' => $stageCounts->all(),
            'at_risk_terrenos' => $atRisk,
        ];
    }

    /**
     * Identifica terrenos com alto risco de pararem.
     *
     * @return array<int, array{terreno_id: int, nome: string, stage: string, days_in_stage: int, risk_score: int, reason: string}>
     */
    public function identifyAtRiskTerrains(int $limit = 20): array
    {
        $atRiskTerrains = collect();

        $this->repository->getActiveTerrainsForRiskAnalysis(200)->each(
            function (Terreno $t) use ($atRiskTerrains): void {
                $riskScore = 0;
                $reasons = [];

                // Tempo no stage atual
                $stageChange = $this->repository->getLatestStageChange($t->id);

                $daysInStage = $t->workflow_status_changed_at
                    ? $t->workflow_status_changed_at->diffInDays(now())
                    : ($stageChange ? $stageChange->created_at->diffInDays(now()) : 0);

                if ($daysInStage > 90) {
                    $riskScore += 40;
                    $reasons[] = "Parado há {$daysInStage} dias";
                } elseif ($daysInStage > 60) {
                    $riskScore += 25;
                    $reasons[] = "Sem avançar há {$daysInStage} dias";
                } elseif ($daysInStage > 30) {
                    $riskScore += 10;
                }

                // Falta de readiness
                if (! $t->proprietarios()->exists()) {
                    $riskScore += 20;
                    $reasons[] = 'Sem proprietário';
                }
                if (blank($t->corretor_id)) {
                    $riskScore += 15;
                    $reasons[] = 'Sem corretor';
                }

                // Viabilidade reprovada
                $viab = $t->viabilidades()->withTrashed()->latest()->first();
                if ($viab && in_array($viab->approval_status, ['reprovada', 'reprovada_comite'], true)) {
                    $riskScore += 30;
                    $reasons[] = 'Viabilidade reprovada';
                }

                // Tarefas abertas sem conclusão
                $openTasks = $t->tasks()->whereNotIn('status', ['concluded', 'cancelled'])->count();
                if ($openTasks > 3) {
                    $riskScore += 10;
                    $reasons[] = "{$openTasks} tarefas abertas";
                }

                if ($riskScore >= 20) {
                    $atRiskTerrains->push([
                        'terreno_id' => $t->id,
                        'nome' => $t->nome,
                        'stage' => $t->workflow_stage,
                        'days_in_stage' => $daysInStage,
                        'risk_score' => min(100, $riskScore),
                        'reason' => implode('; ', $reasons),
                    ]);
                }
            }
        );

        return $atRiskTerrains
            ->sortByDesc('risk_score')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Calcula o desvio padrão de uma coleção.
     */
    protected function standardDeviation(Collection $values): float
    {
        $count = $values->count();
        if ($count <= 1) {
            return 0;
        }

        $mean = (float) $values->sum() / $count;
        $sumSquares = (float) $values->sum(fn ($v) => pow($v - $mean, 2));

        return sqrt($sumSquares / ($count - 1));
    }

    /**
     * Calcula taxa de aprovação de uma coleção de viabilidades.
     *
     * @param  Collection<int, array{terreno_id: int, approval_status: string, vgv: float|null, created_at: mixed}>  $viabilities
     */
    protected function getApprovalRate(Collection $viabilities): float
    {
        if ($viabilities->isEmpty()) {
            return 50;
        }

        $approved = $viabilities->filter(fn ($v) => $v['approval_status'] === 'aprovada')->count();
        $total = $viabilities->count();

        return ($approved / $total) * 100;
    }

    /**
     * Formata um valor como moeda brasileira.
     */
    protected function formatCurrency(float $value): string
    {
        return 'R$ '.number_format($value, 0, ',', '.');
    }
}
