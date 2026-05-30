<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\Regional;
use App\Repositories\Contracts\RegionalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class RegionalRepository implements RegionalRepositoryInterface
{
    public function paginate(int $perPage, ?string $search = null): LengthAwarePaginator
    {
        $query = Regional::query()
            ->with(['responsavel', 'createdBy', 'updatedBy'])
            ->orderBy('nome', 'asc');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('cidade', 'like', "%{$search}%")
                    ->orWhere('estado', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?Regional
    {
        return Regional::query()
            ->with(['responsavel', 'createdBy', 'updatedBy'])
            ->find($id);
    }

    public function create(array $data): Regional
    {
        return Regional::query()->create($data);
    }

    public function update(Regional $regional, array $data): Regional
    {
        $regional->update($data);

        return $regional->refresh();
    }

    public function delete(Regional $regional): void
    {
        $regional->delete();
    }

    public function forSelect(): Collection
    {
        /** @var Collection<int, Regional> $regionais */
        $regionais = Regional::query()
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return $regionais;
    }
}
