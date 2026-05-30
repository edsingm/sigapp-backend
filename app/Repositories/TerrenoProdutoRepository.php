<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\TerrenoProduto;
use App\Repositories\Contracts\TerrenoProdutoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TerrenoProdutoRepository implements TerrenoProdutoRepositoryInterface
{
    public function paginate(int $perPage, ?int $terrenoId = null): LengthAwarePaginator
    {
        $query = TerrenoProduto::query()
            ->with([
                'terreno',
                'produto' => fn ($q) => $q->withTrashed(),
                'createdBy',
                'updatedBy',
            ])
            ->orderBy('created_at', 'desc');

        if ($terrenoId !== null) {
            $query->where('terreno_id', $terrenoId);
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?TerrenoProduto
    {
        return TerrenoProduto::query()
            ->with([
                'terreno',
                'produto' => fn ($q) => $q->withTrashed(),
                'createdBy',
                'updatedBy',
            ])
            ->find($id);
    }

    public function create(array $data): TerrenoProduto
    {
        return TerrenoProduto::create($data);
    }

    public function update(TerrenoProduto $terrenoProduto, array $data): TerrenoProduto
    {
        $terrenoProduto->update($data);

        return $terrenoProduto->refresh();
    }

    public function delete(TerrenoProduto $terrenoProduto): void
    {
        $terrenoProduto->delete();
    }

    /**
     * @return Collection<int, TerrenoProduto>
     */
    public function byTerreno(int $terrenoId): Collection
    {
        /** @var Collection<int, TerrenoProduto> $items */
        $items = TerrenoProduto::query()
            ->where('terreno_id', $terrenoId)
            ->with([
                'terreno',
                'produto' => fn ($q) => $q->withTrashed(),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return $items;
    }
}
