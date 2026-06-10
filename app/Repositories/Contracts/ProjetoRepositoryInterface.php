<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProjetoRepositoryInterface
{
    public function findById(int $id): ?Projeto;

    public function findWithRelations(int $id): ?Projeto;

    public function paginate(int $perPage): LengthAwarePaginator;

    public function listWithFilters(array $filters): LengthAwarePaginator;

    public function listTerrenosElegiveis(array $filters): LengthAwarePaginator;

    public function create(array $data): Projeto;

    public function findTerrenoElegivel(int $terrenoId): Terreno;

    public function existsActiveProjetoForTerreno(int $terrenoId): bool;

    public function findWithFullRelations(int $id): ?Projeto;
}
