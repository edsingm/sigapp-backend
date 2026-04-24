<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Regional;
use App\Repositories\Contracts\RegionalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class RegionalService
{
    public function __construct(
        private readonly RegionalRepositoryInterface $repository,
    ) {}

    public function list(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $search);
    }

    public function findById(int $id): ?Regional
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): Regional
    {
        return $this->repository->create($data);
    }

    public function update(Regional $regional, array $data): Regional
    {
        return $this->repository->update($regional, $data);
    }

    public function delete(Regional $regional): void
    {
        $this->repository->delete($regional);
    }

    public function forSelect(): Collection
    {
        return $this->repository->forSelect();
    }
}
