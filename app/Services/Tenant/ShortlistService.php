<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Shortlist;
use App\Models\Tenant\ShortlistItem;
use App\Models\Tenant\Terreno;
use App\Repositories\Tenant\ShortlistRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ShortlistService
{
    public function __construct(
        private readonly ShortlistRepository $repository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Shortlist>
     */
    public function paginate(int $userId, ?string $scope, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateForUser($userId, $scope, $page, $perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): Shortlist
    {
        return $this->repository->create([
            'owner_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'scope' => $data['scope'] ?? 'private',
            'is_default' => $data['is_default'] ?? false,
        ]);
    }

    public function find(int $id, int $userId): Shortlist
    {
        return $this->repository->findForUser($id, $userId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Shortlist $shortlist, array $data, int $userId): Shortlist
    {
        if ((int) $shortlist->getAttribute('owner_id') !== $userId) {
            throw new InvalidArgumentException('Somente o proprietário pode editar a shortlist.');
        }

        return $this->repository->update($shortlist, $data);
    }

    public function delete(Shortlist $shortlist, int $userId): void
    {
        if ((int) $shortlist->getAttribute('owner_id') !== $userId) {
            throw new InvalidArgumentException('Somente o proprietário pode excluir a shortlist.');
        }

        $this->repository->delete($shortlist);
    }

    public function addItem(Shortlist $shortlist, int $terrenoId): ShortlistItem
    {
        return $this->repository->addItem($shortlist, $terrenoId);
    }

    public function removeItem(Shortlist $shortlist, int $terrenoId): void
    {
        $this->repository->removeItem($shortlist, $terrenoId);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, Terreno>
     */
    public function compare(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) < 2 || count($ids) > 4) {
            throw new InvalidArgumentException('A comparação exige entre 2 e 4 terrenos.');
        }

        $terrenos = $this->repository->findTerrenosForComparison($ids);
        if ($terrenos->count() !== count($ids)) {
            throw new InvalidArgumentException('Um ou mais terrenos não foram encontrados.');
        }

        return $terrenos->all();
    }
}
