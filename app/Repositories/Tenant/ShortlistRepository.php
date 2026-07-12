<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\Shortlist;
use App\Models\Tenant\ShortlistItem;
use App\Models\Tenant\Terreno;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ShortlistRepository
{
    /**
     * @return LengthAwarePaginator<int, Shortlist>
     */
    public function paginateForUser(int $userId, ?string $scope, int $page, int $perPage): LengthAwarePaginator
    {
        $query = Shortlist::query()
            ->with(['owner', 'items.terreno'])
            ->where(function ($builder) use ($userId): void {
                $builder->where('owner_id', $userId)
                    ->orWhere('scope', 'shared');
            })
            ->latest('updated_at');

        if ($scope !== null) {
            $query->where('scope', $scope);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findForUser(int $id, int $userId): Shortlist
    {
        return Shortlist::query()
            ->with(['owner', 'items.terreno'])
            ->whereKey($id)
            ->where(function ($builder) use ($userId): void {
                $builder->where('owner_id', $userId)
                    ->orWhere('scope', 'shared');
            })
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Shortlist
    {
        return Shortlist::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Shortlist $shortlist, array $data): Shortlist
    {
        $shortlist->update($data);

        return $shortlist->refresh()->load(['owner', 'items.terreno']);
    }

    public function delete(Shortlist $shortlist): void
    {
        $shortlist->delete();
    }

    public function addItem(Shortlist $shortlist, int $terrenoId): ShortlistItem
    {
        $position = (int) $shortlist->items()->max('position') + 1;

        return ShortlistItem::query()->firstOrCreate(
            ['shortlist_id' => $shortlist->getAttribute('id'), 'terreno_id' => $terrenoId],
            ['position' => $position],
        );
    }

    public function removeItem(Shortlist $shortlist, int $terrenoId): void
    {
        $shortlist->items()->where('terreno_id', $terrenoId)->delete();
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Terreno>
     */
    public function findTerrenosForComparison(array $ids): Collection
    {
        $terrenos = Terreno::query()->findMany($ids);
        $terrenos->load([
            'responsavel',
            'regional',
            'cidade',
            'terrenoProdutos.produto',
            'viabilidadeAtual',
        ]);

        return $terrenos;
    }
}
