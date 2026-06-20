<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Repositories\Contracts\TerrenoFilterRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TerrenoFilterService
{
    public function __construct(
        private readonly TerrenoFilterRepositoryInterface $repository,
    ) {}

    /**
     * Aplica filtros avançados na consulta de terrenos e retorna os resultados paginados.
     *
     * @param  array<string, mixed>  $filters  Filtros já validados (FilterTerrenosRequest::validated()).
     */
    public function filter(array $filters): LengthAwarePaginator
    {
        return $this->repository->search($filters);
    }
}
