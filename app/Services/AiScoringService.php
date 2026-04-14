<?php

namespace App\Services;

use App\Models\Tenant\AiRecommendationScore;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de scoring heurístico de terrenos.
 *
 * Algoritmo ponderado 0-100, versionável para evolução futura com ML.
 * Version 1: heurística baseada em regras de negócio.
 */
class AiScoringService
{
    public const CURRENT_VERSION = 1;

    // Pesos por fator (total = 100)
    protected const WEIGHT_VIABILIDADE_APROVADA = 25;
    protected const WEIGHT_ESTAGIO_AVANCADO = 20;
    protected const WEIGHT_RECENTE = 15;
    protected const WEIGHT_VGV = 15;
    protected const WEIGHT_DOCUMENTACAO = 10;
    protected const WEIGHT_SEM_PENDENCIAS = 10;
    protected const WEIGHT_RESPONSAVEL = 5;

    // Posição dos estágios no pipeline (ordem crescente de avanço)
    protected const STAGE_ORDER = [
        'em_analise' => 1,
        'aguardando_viabilidade' => 2,
        'viabilidade_aprovada' => 3,
        'aguardando_comite' => 4,
        'negociacao_minuta' => 5,
        'contrato_assinado' => 6,
        'legalizando' => 7,
        'legalizado_finalizado' => 8,
    ];

    // Stages de encerramento — score zero
    protected const STAGE_ENCERRAMENTO = ['descartado', 'arquivado'];

    /**
     * Calcula score para um terreno individual.
     */
    public function score(Terreno $terreno): array
    {
        $factors = [];
        $totalScore = 0;

        // Verificar se é encerramento
        if (in_array($terreno->workflow_stage, self::STAGE_ENCERRAMENTO, true)) {
            return [
                'score' => 0,
                'tier' => 'sem_classificacao',
                'factors' => [
                    'encerrado' => [
                        'score' => 0,
                        'details' => 'Terreno encerrado (stage: '.$terreno->workflow_stage.')',
                    ],
                ],
                'version' => self::CURRENT_VERSION,
            ];
        }

        // 1. Viabilidade aprovada (0-25 pts)
        $viabScore = $this->scoreViabilidadeAprovada($terreno);
        $totalScore += $viabScore;
        $factors['viabilidade_aprovada'] = [
            'raw' => $viabScore,
            'weight' => self::WEIGHT_VIABILIDADE_APROVADA,
        ];

        // 2. Estágio avançado (0-20 pts)
        $stageScore = $this->scoreEstagio($terreno);
        $totalScore += $stageScore;
        $factors['estagio_workflow'] = [
            'raw' => $stageScore,
            'stage' => $terreno->workflow_stage,
            'weight' => self::WEIGHT_ESTAGIO_AVANCADO,
        ];

        // 3. Recência de dados (0-15 pts)
        $recentScore = $this->scoreRecencia($terreno);
        $totalScore += $recentScore;
        $factors['recencia'] = [
            'raw' => $recentScore,
            'days_since_update' => $terreno->updated_at?->diffInDays(now()) ?? 999,
            'weight' => self::WEIGHT_RECENTE,
        ];

        // 4. VGV/Valor (0-15 pts)
        $vgvScore = $this->scoreVgv($terreno);
        $totalScore += $vgvScore;
        $factors['vgv'] = [
            'raw' => $vgvScore,
            'weight' => self::WEIGHT_VGV,
        ];

        // 5. Completude documental (0-10 pts)
        $docScore = $this->scoreDocumentacao($terreno);
        $totalScore += $docScore;
        $factors['documentacao'] = [
            'raw' => $docScore,
            'weight' => self::WEIGHT_DOCUMENTACAO,
        ];

        // 6. Sem pendências (0-10 pts)
        $pendenciaScore = $this->scorePendencias($terreno);
        $totalScore += $pendenciaScore;
        $factors['pendencias'] = [
            'raw' => $pendenciaScore,
            'weight' => self::WEIGHT_SEM_PENDENCIAS,
        ];

        // 7. Responsável atribuído (0-5 pts)
        $respScore = $terreno->responsavel_id ? self::WEIGHT_RESPONSAVEL : 0;
        $totalScore += $respScore;
        $factors['responsavel'] = [
            'raw' => $respScore,
            'assigned' => $terreno->responsavel_id !== null,
            'weight' => self::WEIGHT_RESPONSAVEL,
        ];

        $score = round(min(100, max(0, $totalScore)), 2);
        $tier = $this->classifyTier($score);

        return [
            'score' => $score,
            'tier' => $tier,
            'factors' => $factors,
            'version' => self::CURRENT_VERSION,
        ];
    }

    /**
     * Calcula score para todos os terrenos de um tenant.
     */
    public function scoreAll(): array
    {
        $terrenos = Terreno::with(['viabilidadeAtual', 'comiteAtual'])->get();
        $results = [];

        foreach ($terrenos as $terreno) {
            $result = $this->score($terreno);

            // Upsert score
            AiRecommendationScore::updateOrCreate(
                ['terreno_id' => $terreno->id],
                [
                    'score' => $result['score'],
                    'tier' => $result['tier'],
                    'factors' => $result['factors'],
                    'version' => $result['version'],
                ]
            );

            $results[] = [
                'terreno_id' => $terreno->id,
                'nome' => $terreno->nome,
                'score' => $result['score'],
                'tier' => $result['tier'],
            ];
        }

        // Ordenar por score
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        Log::info("AI Scoring: {$terrenos->count()} terrenos classificados.");

        return $results;
    }

    /**
     * Retorna ranking de terrenos ordenado por score.
     */
    public function getRanking(?int $limit = null): array
    {
        $limit ??= 50;

        return AiRecommendationScore::with('terreno:id,nome,cidade_code,estado')
            ->orderByDesc('score')
            ->limit($limit)
            ->get()
            ->map(fn (AiRecommendationScore $s): array => [
                'terreno_id' => $s->terreno_id,
                'terreno' => $s->terreno ? [
                    'nome' => $s->terreno->nome,
                    'cidade_code' => $s->terreno->cidade_code,
                    'estado' => $s->terreno->estado,
                ] : null,
                'score' => (float) $s->score,
                'tier' => $s->tier,
                'updated_at' => optional($s->updated_at)?->toAtomString(),
            ])
            ->toArray();
    }

    /**
     * Retorna score individual salvo ou recalcula.
     */
    public function getScore(Terreno $terreno, bool $forceRecalculate = false): array
    {
        if (!$forceRecalculate) {
            $saved = AiRecommendationScore::byTerreno($terreno->id)
                ->where('version', self::CURRENT_VERSION)
                ->latest()
                ->first();

            if ($saved) {
                return [
                    'score' => (float) $saved->score,
                    'tier' => $saved->tier,
                    'factors' => $saved->factors ?? [],
                    'version' => $saved->version,
                ];
            }
        }

        $result = $this->score($terreno);

        AiRecommendationScore::updateOrCreate(
            ['terreno_id' => $terreno->id],
            [
                'score' => $result['score'],
                'tier' => $result['tier'],
                'factors' => $result['factors'],
                'version' => $result['version'],
            ]
        );

        return $result;
    }

    /**
     * 1. Viabilidade aprovada (0-25 pts)
     */
    protected function scoreViabilidadeAprovada(Terreno $terreno): float
    {
        $viab = $terreno->viabilidadeAtual;

        if (!$viab) {
            return 0;
        }

        // Aprovada + vigente = máximo
        if ($viab->approval_status === 'aprovada' && $viab->is_current) {
            return self::WEIGHT_VIABILIDADE_APROVADA;
        }

        // Pendente = parcial (ainda em processo)
        if ($viab->approval_status === 'pendente' && $viab->approval_requested_at) {
            return self::WEIGHT_VIABILIDADE_APROVADA * 0.4;
        }

        // Reprovada = penalização
        if ($viab->approval_status === 'reprovada') {
            return 0;
        }

        // Em análise = parcial baixo
        if ($viab->status === 'em_analise') {
            return self::WEIGHT_VIABILIDADE_APROVADA * 0.2;
        }

        // Tem viabilidade mas status desconhecido
        return self::WEIGHT_VIABILIDADE_APROVADA * 0.1;
    }

    /**
     * 2. Estágio avançado (0-20 pts)
     * Posição no pipeline: em_analise(1) → legalizado_finalizado(8)
     */
    protected function scoreEstagio(Terreno $terreno): float
    {
        $stagePosition = self::STAGE_ORDER[$terreno->workflow_stage] ?? 0;
        $maxPosition = count(self::STAGE_ORDER);

        if ($stagePosition === 0) {
            return 0;
        }

        // Score proporcional à posição no pipeline
        return round(($stagePosition / $maxPosition) * self::WEIGHT_ESTAGIO_AVANCADO, 2);
    }

    /**
     * 3. Recência de dados (0-15 pts)
     * Atualizado nos últimos 7 dias = máximo, 30+ dias = mínimo.
     */
    protected function scoreRecencia(Terreno $terreno): float
    {
        if (!$terreno->updated_at) {
            return 0;
        }

        $daysAgo = $terreno->updated_at->diffInDays(now());

        if ($daysAgo <= 3) {
            return self::WEIGHT_RECENTE;
        }

        if ($daysAgo <= 7) {
            return self::WEIGHT_RECENTE * 0.9;
        }

        if ($daysAgo <= 14) {
            return self::WEIGHT_RECENTE * 0.7;
        }

        if ($daysAgo <= 30) {
            return self::WEIGHT_RECENTE * 0.4;
        }

        if ($daysAgo <= 60) {
            return self::WEIGHT_RECENTE * 0.2;
        }

        return self::WEIGHT_RECENTE * 0.1;
    }

    /**
     * 4. VGV/Valor (0-15 pts)
     * Terrenos com VGV alto contribuem mais.
     */
    protected function scoreVgv(Terreno $terreno): float
    {
        $viab = $terreno->viabilidadeAtual;
        if (!$viab || empty($viab->resultados_dre)) {
            // Se não tem viabilidade, usa valor do terreno como proxy
            if ($terreno->valor && $terreno->valor > 0) {
                $valor = (float) $terreno->valor;

                return round(min(self::WEIGHT_VGV, ($valor / 5_000_000) * self::WEIGHT_VGV), 2);
            }

            return self::WEIGHT_VGV * 0.1;
        }

        $vgv = $viab->resultados_dre['indicadores']['vgv_total'] ?? 0;
        if ($vgv <= 0) {
            return self::WEIGHT_VGV * 0.1;
        }

        // Normalização: R$10M+ = máximo
        return round(min(self::WEIGHT_VGV, ($vgv / 10_000_000) * self::WEIGHT_VGV), 2);
    }

    /**
     * 5. Completude documental (0-10 pts)
     * Ratio de documentos existentes vs tipos esperados.
     */
    protected function scoreDocumentacao(Terreno $terreno): float
    {
        $docCount = $terreno->documentos()->count();
        if ($docCount === 0) {
            return 0;
        }

        // 5+ docs = máximo (cobertura razoável)
        return round(min(self::WEIGHT_DOCUMENTACAO, ($docCount / 5) * self::WEIGHT_DOCUMENTACAO), 2);
    }

    /**
     * 6. Sem pendências (0-10 pts)
     * Penaliza comitê com pendências e legalização com etapas atrasadas.
     */
    protected function scorePendencias(Terreno $terreno): float
    {
        $score = self::WEIGHT_SEM_PENDENCIAS;

        // Comitê com pendências
        $pendenciasComite = ComiteRevisao::where('terreno_id', $terreno->id)
            ->where('status', 'em_andamento')
            ->first();

        if ($pendenciasComite) {
            $pendenciaCount = $pendenciasComite->pendencias()
                ->where('status', '!=', 'resolvida')
                ->count();
            $score -= min($score * 0.5, $pendenciaCount * 2);
        }

        // Legalização com etapas atrasadas
        $legalizacao = Legalizacao::where('terreno_id', $terreno->id)->first();
        if ($legalizacao) {
            $atrasadas = $legalizacao->etapas()
                ->where('prazo_fim', '<', now())
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->count();
            $score -= min($score * 0.5, $atrasadas * 2);
        }

        return round(max(0, $score), 2);
    }

    /**
     * Classifica o tier baseado no score.
     */
    protected function classifyTier(float $score): string
    {
        if ($score >= 80) {
            return 'alta_prioridade';
        }

        if ($score >= 60) {
            return 'media';
        }

        if ($score >= 40) {
            return 'atencao';
        }

        if ($score >= 20) {
            return 'baixa';
        }

        return 'sem_classificacao';
    }
}
