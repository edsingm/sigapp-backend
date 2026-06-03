<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\StatusHistory;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use App\Repositories\Contracts\AiPredictiveRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class AiPredictiveRepository implements AiPredictiveRepositoryInterface
{
    private const TERMINAL_STATUSES = ['descartado', 'arquivado', 'legalizado_finalizado'];

    public function getDecidedViabilities(): Collection
    {
        /** @var Collection<int, Viabilidade> $viabilidades */
        $viabilidades = Viabilidade::withTrashed()
            ->whereNotNull('approval_status')
            ->where('approval_status', '!=', '')
            ->get();

        return $viabilidades;
    }

    public function getComiteReviewsWithParecer(): Collection
    {
        /** @var Collection<int, ComiteRevisao> $reviews */
        $reviews = ComiteRevisao::query()
            ->whereNotNull('parecer')
            ->where('parecer', '!=', '')
            ->get();

        return $reviews;
    }

    public function getSimilarViabilities(Terreno $terreno, array $productIds, ?int $excludeViabilidadeId): Collection
    {
        $query = Viabilidade::query()
            ->withTrashed()
            ->whereNotNull('approval_status')
            ->where('id', '!=', $excludeViabilidadeId);

        $query->whereHas('terreno', function ($q) use ($terreno, $productIds) {
            $q->where('id', '!=', $terreno->id)
                ->where(function ($subQ) use ($terreno, $productIds) {
                    if (! empty($productIds)) {
                        $subQ->whereHas('terrenoProdutos', function ($prodQ) use ($productIds) {
                            $prodQ->whereIn('produto_id', $productIds);
                        });
                    }

                    if ($terreno->cidade_code) {
                        $subQ->orWhere('cidade_code', $terreno->cidade_code);
                    }
                });
        });

        /** @var Collection<int, Viabilidade> $viabilidades */
        $viabilidades = $query->limit(50)->get();

        return $viabilidades;
    }

    public function getStalledTerrains(CarbonInterface $threshold): Collection
    {
        /** @var Collection<int, Terreno> $terrenos */
        $terrenos = Terreno::query()
            ->whereNotIn('workflow_status_code', self::TERMINAL_STATUSES)
            ->where('updated_at', '<', $threshold)
            ->get(['id', 'nome', 'workflow_stage', 'workflow_status_code', 'updated_at', 'created_at']);

        return $terrenos;
    }

    public function getActiveTerrainsCount(): int
    {
        return Terreno::query()
            ->whereNotIn('workflow_status_code', self::TERMINAL_STATUSES)
            ->count();
    }

    public function getActiveTerrainsForRiskAnalysis(int $limit): Collection
    {
        /** @var Collection<int, Terreno> $terrenos */
        $terrenos = Terreno::query()
            ->whereNotIn('workflow_status_code', self::TERMINAL_STATUSES)
            ->limit($limit)
            ->get();

        return $terrenos;
    }

    public function getLatestStageChange(int $terrenoId): ?StatusHistory
    {
        /** @var StatusHistory|null $history */
        $history = StatusHistory::query()
            ->where('terreno_id', $terrenoId)
            ->whereNotNull('created_at')
            ->latest('created_at')
            ->first();

        return $history;
    }
}
