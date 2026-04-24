<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\Produto;
use App\Repositories\Contracts\ProdutoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProdutoRepository implements ProdutoRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Produto::query()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findById(int $id, bool $withTrashed = false): ?Produto
    {
        $query = Produto::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    public function create(array $data): Produto
    {
        return Produto::create($data);
    }

    public function update(Produto $produto, array $data): Produto
    {
        $produto->update($data);

        return $produto->refresh();
    }

    public function delete(Produto $produto): void
    {
        $produto->delete();
    }

    public function restore(Produto $produto): void
    {
        $produto->restore();
    }

    public function searchForSelect(string $search): Collection
    {
        return Produto::query()
            ->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%")
            ->orderBy('name')
            ->limit(20)
            ->get();
    }
}
