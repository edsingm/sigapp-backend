<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\StatusHistory;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface AiPredictiveRepositoryInterface
{
    /**
     * @return Collection<int, Viabilidade>
     */
    public function getDecidedViabilities(): Collection;

    /**
     * @return Collection<int, ComiteRevisao>
     */
    public function getComiteReviewsWithParecer(): Collection;

    /**
     * @param  array<int, int>  $productIds
     * @return Collection<int, Viabilidade>
     */
    public function getSimilarViabilities(Terreno $terreno, array $productIds, ?int $excludeViabilidadeId): Collection;

    /**
     * @return Collection<int, Terreno>
     */
    public function getStalledTerrains(CarbonInterface $threshold): Collection;

    public function getActiveTerrainsCount(): int;

    /**
     * @return Collection<int, Terreno>
     */
    public function getActiveTerrainsForRiskAnalysis(int $limit): Collection;

    public function getLatestStageChange(int $terrenoId): ?StatusHistory;
}
