<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Proprietario;
use App\Repositories\Contracts\ProprietarioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProprietarioService
{
    public function __construct(
        private readonly ProprietarioRepositoryInterface $repository,
    ) {}

    public function list(int $tenantId, int $perPage, ?int $terrenoId = null): LengthAwarePaginator
    {
        return $this->repository->paginateForTenant($tenantId, $perPage, $terrenoId);
    }

    public function findById(int $id): ?Proprietario
    {
        return $this->repository->findById($id);
    }

    public function findWithRelations(int $id): ?Proprietario
    {
        return $this->repository->findWithRelations($id);
    }

    public function create(array $data): Proprietario
    {
        return $this->repository->create($data);
    }

    public function update(Proprietario $proprietario, array $data): Proprietario
    {
        return $this->repository->update($proprietario, $data);
    }

    public function delete(Proprietario $proprietario): void
    {
        $this->repository->delete($proprietario);
    }
}
