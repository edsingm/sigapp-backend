<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\TerrenoProduto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TerrenoProdutoRepositoryInterface
{
    public function paginate(int $perPage, ?int $terrenoId = null): LengthAwarePaginator;

    public function findById(int $id): ?TerrenoProduto;

    public function create(array $data): TerrenoProduto;

    public function update(TerrenoProduto $terrenoProduto, array $data): TerrenoProduto;

    public function delete(TerrenoProduto $terrenoProduto): void;

    /**
     * @return Collection<int, TerrenoProduto>
     */
    public function byTerreno(int $terrenoId): Collection;
}
