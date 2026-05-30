<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\Proprietario;
use App\Repositories\Contracts\ProprietarioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProprietarioRepository implements ProprietarioRepositoryInterface
{
    public function paginateForTenant(int $tenantId, int $perPage, ?int $terrenoId = null): LengthAwarePaginator
    {
        $query = Proprietario::query()
            ->with(['terreno', 'createdBy', 'updatedBy'])
            ->orderBy('created_at', 'desc');

        if ($terrenoId) {
            $query->where('terreno_id', $terrenoId);
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?Proprietario
    {
        return Proprietario::query()->find($id);
    }

    public function findWithRelations(int $id): ?Proprietario
    {
        return Proprietario::query()
            ->with(['terreno', 'createdBy', 'updatedBy'])
            ->find($id);
    }

    public function create(array $data): Proprietario
    {
        return Proprietario::query()->create($data);
    }

    public function update(Proprietario $proprietario, array $data): Proprietario
    {
        $proprietario->update($data);

        return $proprietario->refresh();
    }

    public function delete(Proprietario $proprietario): void
    {
        $proprietario->delete();
    }
}
