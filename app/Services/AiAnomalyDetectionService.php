<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\Terreno;
use App\Repositories\Contracts\AiAnomalyRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Serviço de detecção de anomalias para terrenos e viabilidades.
 *
 * Identifica:
 * - Inconsistências de workflow (stage vs status)
 * - VGV desproporcional ao valor do terreno
 * - Terrenos duplicados ou muito similares
 *
 * Version 1: regras heurísticas baseadas em thresholds.
 */
class AiAnomalyDetectionService
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly AiAnomalyRepositoryInterface $repository
    ) {}

    /**
     * Escaneia o portfólio e retorna todas as anomalias detectadas.
     *
     * @return array{total_anomalies: int, categories: array, anomalies: array}
     */
    public function scanPortfolio(?string $category = null, int $limit = 100): array
    {
        $anomalies = collect();

        if ($category === null || $category === 'workflow_inconsistencies') {
            $anomalies = $anomalies->merge($this->detectWorkflowInconsistencies($limit));
        }

        if ($category === null || $category === 'financial_anomalies') {
            $anomalies = $anomalies->merge($this->detectFinancialAnomalies($limit));
        }

        if ($category === null || $category === 'duplicate_terrains') {
            $anomalies = $anomalies->merge($this->detectDuplicateTerrains($limit));
        }

        if ($category === null || $category === 'data_quality') {
            $anomalies = $anomalies->merge($this->detectDataQualityIssues($limit));
        }

        // Ordena por severidade
        $sorted = $anomalies->sortByDesc('severity_score')->values()->take($limit);

        $byCategory = $sorted->groupBy('category')->map(
            fn ($items) => [
                'count' => $items->count(),
                'max_severity' => $items->max('severity_score'),
            ]
        )->all();

        return [
            'total_anomalies' => $sorted->count(),
            'categories' => $byCategory,
            'anomalies' => $sorted,
            'version' => self::VERSION,
            'scan_timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Detecta inconsistências entre workflow_stage e dados reais.
     *
     * @return Collection<int, array>
     */
    public function detectWorkflowInconsistencies(int $limit): Collection
    {
        $anomalies = collect();

        $this->repository->getActiveForWorkflowCheck($limit)->each(
            function (Terreno $t) use ($anomalies): void {
                // Stage viabilidade mas sem viabilidade aprovada
                if ($t->workflow_stage === 'viabilidade' &&
                    $t->viabilidadeAtual &&
                    ! in_array($t->viabilidadeAtual->approval_status, ['aprovada', 'pendente'], true)) {
                    $anomalies->push([
                        'category' => 'workflow_inconsistencies',
                        'type' => 'stage_without_viability',
                        'severity' => 'high',
                        'severity_score' => 85,
                        'terrain_id' => $t->id,
                        'terrain_name' => $t->nome,
                        'message' => "Estão no stage 'viabilidade' mas a viabilidade atual não está aprovada (status: {$t->viabilidadeAtual->approval_status}).",
                        'recommended_action' => 'Verificar se a viabilidade deve ser refeita ou se o stage está errado.',
                    ]);
                }

                // Stage comitê mas sem comitê criado
                if ($t->workflow_stage === 'comite' && ! $t->comiteAtual) {
                    $anomalies->push([
                        'category' => 'workflow_inconsistencies',
                        'type' => 'stage_without_committee',
                        'severity' => 'high',
                        'severity_score' => 80,
                        'terrain_id' => $t->id,
                        'terrain_name' => $t->nome,
                        'message' => "Estão no stage 'comite' mas não há comitê criado.",
                        'recommended_action' => 'Criar comitê ou retornar stage anterior.',
                    ]);
                }

                // Stage negociaçao/comitê com viabilidade reprovada
                if (in_array($t->workflow_stage, ['comite', 'negociacao_contrato'], true) &&
                    $t->viabilidadeAtual &&
                    in_array($t->viabilidadeAtual->approval_status, ['reprovada', 'reprovada_comite'], true)) {
                    $anomalies->push([
                        'category' => 'workflow_inconsistencies',
                        'type' => 'advanced_stage_with_rejected_viability',
                        'severity' => 'critical',
                        'severity_score' => 95,
                        'terrain_id' => $t->id,
                        'terrain_name' => $t->nome,
                        'message' => "Estão no stage '{$t->workflow_stage}' mas a viabilidade foi reprovada.",
                        'recommended_action' => 'Revisar imediatamente — terreno em stage avançado com viabilidade reprovada.',
                    ]);
                }

                // Stage legalizando mas contrato não assinado
                if ($t->workflow_stage === 'legalizacao' &&
                    (! $t->contratoAtual || ! $t->contratoAtual->signed_at)) {
                    $anomalies->push([
                        'category' => 'workflow_inconsistencies',
                        'type' => 'legalizacao_without_signed_contract',
                        'severity' => 'high',
                        'severity_score' => 90,
                        'terrain_id' => $t->id,
                        'terrain_name' => $t->nome,
                        'message' => "Estão no stage 'legalizacao' mas não há contrato assinado.",
                        'recommended_action' => 'Assinar contrato antes de iniciar legalização.',
                    ]);
                }

                // Stage legalizado mas legalização não concluída
                if ($t->workflow_status_code === 'legalizado_finalizado' &&
                    $t->legalizacao &&
                    $t->legalizacao->status !== 'concluido') {
                    $anomalies->push([
                        'category' => 'workflow_inconsistencies',
                        'type' => 'finished_without_legalization',
                        'severity' => 'critical',
                        'severity_score' => 90,
                        'terrain_id' => $t->id,
                        'terrain_name' => $t->nome,
                        'message' => 'Finalizado mas legalização não está concluída.',
                        'recommended_action' => 'Concluir legalização ou revisar status do terreno.',
                    ]);
                }
            }
        );

        return $anomalies;
    }

    /**
     * Detecta anomalias financeiras — VGV desproporcional ao valor do terreno.
     *
     * @return Collection<int, array>
     */
    public function detectFinancialAnomalies(int $limit): Collection
    {
        $anomalies = collect();

        $this->repository->getWithViabilidadesForFinancialCheck($limit)->each(
            function (Terreno $t) use ($anomalies): void {
                $terrenoValue = (float) $t->valor;
                $ratios = [];

                foreach ($t->viabilidades as $v) {
                    $dre = $v->resultados_dre;
                    $vgv = $dre['indicadores']['vgv'] ?? $dre['totais']['vgv_geral'] ?? null;

                    if ($vgv && (float) $vgv > 0) {
                        $ratios[] = (float) $vgv / $terrenoValue;
                    }
                }

                if (empty($ratios)) {
                    return;
                }

                $avgRatio = array_sum($ratios) / count($ratios);
                $maxRatio = max($ratios);
                $minRatio = min($ratios);
                $minAcceptable = 1.5;

                // Se em alguma viabilidade o VGV é < 1.5x o valor do terreno, é anomalia
                if ($minRatio < $minAcceptable) {
                    $anomalies->push([
                        'category' => 'financial_anomalies',
                        'type' => 'low_vgv_to_land_ratio',
                        'severity' => 'high',
                        'severity_score' => 75,
                        'terrain_id' => $t->id,
                        'terrain_name' => $t->nome,
                        'message' => 'VGV muito próximo do valor do terreno. Menor ratio: '.round($minRatio, 2)."x (mínimo aceitável: {$minAcceptable}x).",
                        'recommended_action' => 'Revisar viabilidade — margem de lucro pode ser insuficiente.',
                        'details' => [
                            'valor_terreno' => 'R$ '.number_format($terrenoValue, 0, ',', '.'),
                            'menor_vgv' => 'R$ '.number_format($minRatio * $terrenoValue, 0, ',', '.'),
                            'ratio_medio' => round($avgRatio, 2).'x',
                        ],
                    ]);
                }

                // VGV extremamente desproporcional (> 50x valor do terreno)
                if ($maxRatio > 50) {
                    $anomalies->push([
                        'category' => 'financial_anomalies',
                        'type' => 'excessive_vgv_to_land_ratio',
                        'severity' => 'medium',
                        'severity_score' => 60,
                        'terrain_id' => $t->id,
                        'terrain_name' => $t->nome,
                        'message' => "VGV muito acima do esperado ({$maxRatio}x valor do terreno).",
                        'recommended_action' => 'Validar dados de viabilidade — pode haver erro de digitação.',
                    ]);
                }
            }
        );

        return $anomalies;
    }

    /**
     * Detecta terrenos duplicados ou muito similares.
     *
     * @return Collection<int, array>
     */
    public function detectDuplicateTerrains(int $limit): Collection
    {
        $anomalies = collect();
        $checked = [];

        $this->repository->getAllActiveForDuplicateCheck(200)->each(
            function (Terreno $t) use ($anomalies, &$checked): void {
                if (in_array($t->id, $checked, true)) {
                    return;
                }

                $this->repository->getActiveAfterId($t->id, 100)->each(
                    function (Terreno $other) use ($t, $anomalies, &$checked): void {
                        $matchType = $this->matchType($t, $other);

                        if ($matchType === null) {
                            return;
                        }

                        $checked[] = $other->id;

                        $anomalies->push([
                            'category' => 'duplicate_terrains',
                            'type' => 'possible_duplicate',
                            'severity' => $matchType['severity'],
                            'severity_score' => $matchType['score'],
                            'terrain_id' => $t->id,
                            'terrain_name' => $t->nome,
                            'message' => "Possível duplicata: terreno ID {$t->id} ('{$t->nome}') e ID {$other->id} ('{$other->nome}') compartilham {$matchType['description']}.",
                            'recommended_action' => 'Verificar se são o mesmo terreno e consolidar.',
                            'related_terrain_id' => $other->id,
                            'related_terrain_name' => $other->nome,
                            'match_details' => $matchType,
                        ]);
                    }
                );
            }
        );

        return $anomalies->take((int) ($limit / 2));
    }

    /**
     * Detecta problemas de qualidade de dados.
     *
     * @return Collection<int, array>
     */
    public function detectDataQualityIssues(int $limit): Collection
    {
        $anomalies = collect();

        // Terrenos com valor zerado
        $this->repository->getZeroValue($limit)->each(
            function ($t) use ($anomalies): void {
                $anomalies->push([
                    'category' => 'data_quality',
                    'type' => 'zero_value',
                    'severity' => 'low',
                    'severity_score' => 30,
                    'terrain_id' => $t->id,
                    'terrain_name' => $t->nome,
                    'message' => 'Terreno com valor zerado.',
                    'recommended_action' => 'Atualizar valor do terreno.',
                ]);
            }
        );

        // Terrenos com área zerada
        $this->repository->getMissingArea($limit)->each(
            function ($t) use ($anomalies): void {
                $anomalies->push([
                    'category' => 'data_quality',
                    'type' => 'missing_area',
                    'severity' => 'medium',
                    'severity_score' => 40,
                    'terrain_id' => $t->id,
                    'terrain_name' => $t->nome,
                    'message' => 'Terreno sem área registrada.',
                    'recommended_action' => 'Cadastrar área do terreno.',
                ]);
            }
        );

        return $anomalies;
    }

    /**
     * Avalia se dois terrenos são possivelmente duplicados.
     *
     * @return array{type: string, description: string, severity: string, score: int}|null
     */
    protected function matchType(Terreno $a, Terreno $b): ?array
    {
        $matchPoints = 0;

        // Mesmo endereço exato
        if (filled($a->endereco) && filled($b->endereco) &&
            strtolower(trim($a->endereco)) === strtolower(trim($b->endereco))) {
            $matchPoints += 4;
        } elseif (filled($a->endereco) && filled($b->endereco) &&
            similar_text(strtolower($a->endereco), strtolower($b->endereco), $pct) &&
            $pct > 80) {
            $matchPoints += 3;
        }

        // Mesmo nome com alta similaridade
        if (filled($a->nome) && filled($b->nome) &&
            similar_text(strtolower($a->nome), strtolower($b->nome), $pct) &&
            $pct > 85) {
            $matchPoints += 3;
        }

        // Mesma cidade + área muito próxima (< 5% diferença)
        if (filled($a->cidade_code) && $a->cidade_code === $b->cidade_code) {
            $matchPoints += 1;

            if ($a->area_terreno > 0 && $b->area_terreno > 0) {
                $areaDiff = abs($a->area_terreno - $b->area_terreno) / max($a->area_terreno, $b->area_terreno);
                if ($areaDiff < 0.05) {
                    $matchPoints += 2;
                }
            }
        }

        if ($matchPoints >= 5) {
            return [
                'type' => $matchPoints >= 6 ? 'exact_duplicate' : 'high_similarity',
                'description' => match (true) {
                    $matchPoints >= 7 => 'endereço e nome muito similares',
                    $matchPoints >= 5 => 'dados similares na mesma cidade',
                    default => 'alta similaridade',
                },
                'severity' => $matchPoints >= 6 ? 'high' : 'medium',
                'score' => min(95, $matchPoints * 15),
            ];
        }

        return null;
    }
}
