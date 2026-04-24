<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\Proprietario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProprietarioRepositoryInterface
{
    public function paginateForTenant(int $tenantId, int $perPage, ?int $terrenoId = null): LengthAwarePaginator;

    public function findById(int $id): ?Proprietario;

    public function create(array $data): Proprietario;

    public function update(Proprietario $proprietario, array $data): Proprietario;

    public function delete(Proprietario $proprietario): void;

    public function findWithRelations(int $id): ?Proprietario;
}
