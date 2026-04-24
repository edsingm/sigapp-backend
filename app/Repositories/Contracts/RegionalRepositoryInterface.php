<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\Regional;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface RegionalRepositoryInterface
{
    public function paginate(int $perPage, ?string $search = null): LengthAwarePaginator;

    public function findById(int $id): ?Regional;

    public function create(array $data): Regional;

    public function update(Regional $regional, array $data): Regional;

    public function delete(Regional $regional): void;

    /**
     * @return Collection<int, Regional>
     */
    public function forSelect(): Collection;
}
