<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Http\Requests\Tenant\FilterTerrenosRequest;
use App\Repositories\Contracts\TerrenoFilterRepositoryInterface;

class TerrenoFilterService
{
    public function __construct(
        private readonly TerrenoFilterRepositoryInterface $repository,
    ) {}

    /**
     * Aplica filtros avançados na consulta de terrenos e retorna os resultados paginados.
     */
    public function filter(FilterTerrenosRequest $request)
    {
        return $this->repository->search($request->validated());
    }
}
