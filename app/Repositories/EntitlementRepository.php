<?php

namespace App\Repositories;

use App\Models\Central\Entitlement;
use App\Repositories\Contracts\EntitlementRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EntitlementRepository implements EntitlementRepositoryInterface
{
    public function all(): Collection
    {
        return Entitlement::orderBy('type')->orderBy('key')->get();
    }

    public function findById(int $id): ?Entitlement
    {
        return Entitlement::find($id);
    }

    public function findByKey(string $key): ?Entitlement
    {
        return Entitlement::where('key', $key)->first();
    }

    public function create(array $data): Entitlement
    {
        return Entitlement::create($data);
    }

    public function update(Entitlement $entitlement, array $data): Entitlement
    {
        $entitlement->update($data);

        return $entitlement->refresh();
    }

    public function delete(Entitlement $entitlement): void
    {
        $entitlement->delete();
    }
}
