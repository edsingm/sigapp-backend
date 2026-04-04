<?php

namespace App\Services;

use App\Models\Central\Entitlement;
use App\Repositories\Contracts\EntitlementRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class EntitlementService
{
    public function __construct(
        private readonly EntitlementRepositoryInterface $entitlementRepository
    ) {}

    public function list(): Collection
    {
        return $this->entitlementRepository->all();
    }

    public function create(array $data): Entitlement
    {
        if ($this->entitlementRepository->findByKey($data['key']) !== null) {
            throw new InvalidArgumentException("Entitlement com key [{$data['key']}] já existe.");
        }

        return $this->entitlementRepository->create($data);
    }

    public function update(int $id, array $data): Entitlement
    {
        $entitlement = $this->entitlementRepository->findById($id);

        if ($entitlement === null) {
            throw new InvalidArgumentException("Entitlement #{$id} não encontrado.");
        }

        if (isset($data['key']) && $data['key'] !== $entitlement->key) {
            $existing = $this->entitlementRepository->findByKey($data['key']);

            if ($existing !== null) {
                throw new InvalidArgumentException("Entitlement com key [{$data['key']}] já existe.");
            }
        }

        return $this->entitlementRepository->update($entitlement, $data);
    }

    public function delete(int $id): void
    {
        $entitlement = $this->entitlementRepository->findById($id);

        if ($entitlement === null) {
            throw new InvalidArgumentException("Entitlement #{$id} não encontrado.");
        }

        $this->entitlementRepository->delete($entitlement);
    }

    public function findOrFail(int $id): Entitlement
    {
        $entitlement = $this->entitlementRepository->findById($id);

        if ($entitlement === null) {
            throw new InvalidArgumentException("Entitlement #{$id} não encontrado.");
        }

        return $entitlement;
    }
}
