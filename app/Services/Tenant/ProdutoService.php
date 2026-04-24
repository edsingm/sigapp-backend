<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Produto;
use App\Repositories\Contracts\ProdutoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProdutoService
{
    public function __construct(
        private readonly ProdutoRepositoryInterface $repository,
    ) {}

    public function list(int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function findById(int $id, bool $withTrashed = false): ?Produto
    {
        return $this->repository->findById($id, $withTrashed);
    }

    public function create(array $data): Produto
    {
        return $this->repository->create($data);
    }

    public function update(Produto $produto, array $data): Produto
    {
        return $this->repository->update($produto, $data);
    }

    public function delete(Produto $produto): void
    {
        $this->repository->delete($produto);
    }

    public function restore(Produto $produto): void
    {
        $this->repository->restore($produto);
    }

    public function searchForSelect(string $search): Collection
    {
        return $this->repository->searchForSelect($search);
    }
}
