<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\Terreno;
use Illuminate\Database\Eloquent\Collection;

interface AiAnomalyRepositoryInterface
{
    /**
     * @return Collection<int, Terreno>
     */
    public function getActiveForWorkflowCheck(int $limit): Collection;

    /**
     * @return Collection<int, Terreno>
     */
    public function getWithViabilidadesForFinancialCheck(int $limit): Collection;

    /**
     * @return Collection<int, Terreno>
     */
    public function getAllActiveForDuplicateCheck(int $initialLimit): Collection;

    /**
     * @return Collection<int, Terreno>
     */
    public function getActiveAfterId(int $terrenoId, int $limit): Collection;

    /**
     * @return Collection<int, Terreno>
     */
    public function getZeroValue(int $limit): Collection;

    /**
     * @return Collection<int, Terreno>
     */
    public function getMissingArea(int $limit): Collection;
}
