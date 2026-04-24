<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\TerrenoProduto;
use App\Repositories\Contracts\TerrenoProdutoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TerrenoProdutoService
{
    public function __construct(
        private readonly TerrenoProdutoRepositoryInterface $repository,
    ) {}

    public function list(int $perPage = 10, ?int $terrenoId = null): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $terrenoId);
    }

    public function findById(int $id): ?TerrenoProduto
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): TerrenoProduto
    {
        return $this->repository->create($data);
    }

    public function update(TerrenoProduto $terrenoProduto, array $data): TerrenoProduto
    {
        return $this->repository->update($terrenoProduto, $data);
    }

    public function delete(TerrenoProduto $terrenoProduto): void
    {
        $this->repository->delete($terrenoProduto);
    }

    public function byTerreno(int $terrenoId): Collection
    {
        return $this->repository->byTerreno($terrenoId);
    }
}
