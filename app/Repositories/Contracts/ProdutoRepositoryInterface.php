<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\Produto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProdutoRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator;

    public function findById(int $id, bool $withTrashed = false): ?Produto;

    public function create(array $data): Produto;

    public function update(Produto $produto, array $data): Produto;

    public function delete(Produto $produto): void;

    public function restore(Produto $produto): void;

    /**
     * @return Collection<int, Produto>
     */
    public function searchForSelect(string $search): Collection;
}
