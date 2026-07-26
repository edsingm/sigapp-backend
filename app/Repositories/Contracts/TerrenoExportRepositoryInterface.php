<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\Terreno;
use Illuminate\Database\Eloquent\Collection;

interface TerrenoExportRepositoryInterface
{
    public function exists(int $id): bool;

    /**
     * @return Collection<int, Terreno>
     */
    public function queryForExport(array $filters): Collection;

    public function findForSingleExport(int $id): ?Terreno;

    public function findForChecklist(int $id): ?Terreno;
}
