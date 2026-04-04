<?php

namespace App\Repositories\Contracts;

use App\Models\Central\Entitlement;
use Illuminate\Database\Eloquent\Collection;

interface EntitlementRepositoryInterface
{
    public function all(): Collection;

    public function findById(int $id): ?Entitlement;

    public function findByKey(string $key): ?Entitlement;

    public function create(array $data): Entitlement;

    public function update(Entitlement $entitlement, array $data): Entitlement;

    public function delete(Entitlement $entitlement): void;
}
