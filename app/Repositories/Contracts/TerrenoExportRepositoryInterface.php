<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface TerrenoExportRepositoryInterface
{
    /**
     * @return Collection<int, \App\Models\Tenant\Terreno>
     */
    public function queryForExport(array $filters): Collection;

    public function findForSingleExport(int $id): ?\App\Models\Tenant\Terreno;

    public function findForChecklist(int $id): ?\App\Models\Tenant\Terreno;
}
