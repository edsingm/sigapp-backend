<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\AiRecommendationScore;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\Terreno;

class AiScoringRepository
{
    /**
     * Itera todos os terrenos em chunks, carregando relações necessárias para scoring.
     */
    public function chunkAllTerrenos(int $chunkSize, callable $callback): void
    {
        Terreno::with(['viabilidadeAtual', 'comiteAtual'])->chunkById($chunkSize, $callback);
    }

    /**
     * Cria ou atualiza o score de um terreno.
     *
     * @param  array<string, mixed>  $data
     */
    public function upsertScore(int $terrenoId, array $data): AiRecommendationScore
    {
        return AiRecommendationScore::updateOrCreate(
            ['terreno_id' => $terrenoId],
            $data,
        );
    }

    /**
     * Retorna o ranking de terrenos por score, já mapeado para array de resposta.
     *
     * @return array<int, array{terreno_id: int, terreno: array<string, mixed>|null, score: float, tier: string, updated_at: string|null}>
     */
    public function getRanking(int $limit): array
    {
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

    public function getLatestScore(int $terrenoId, int $version): ?AiRecommendationScore
    {
        return AiRecommendationScore::byTerreno($terrenoId)
            ->where('version', $version)
            ->latest()
            ->first();
    }

    public function findActiveCommitteeByTerreno(int $terrenoId): ?ComiteRevisao
    {
        return ComiteRevisao::where('terreno_id', $terrenoId)
            ->where('status', 'em_andamento')
            ->first();
    }

    public function findLegalizacaoByTerreno(int $terrenoId): ?Legalizacao
    {
        return Legalizacao::where('terreno_id', $terrenoId)->first();
    }

    public function countUnresolvedCommitteePendencias(ComiteRevisao $comite): int
    {
        return $comite->pendencias()
            ->where('status', '!=', 'resolvida')
            ->count();
    }

    public function countOverdueLegalizacaoEtapas(Legalizacao $legalizacao): int
    {
        return $legalizacao->etapas()
            ->where('prazo_fim', '<', now())
            ->whereNotIn('status', ['concluida', 'cancelada'])
            ->count();
    }
}
