<?php

namespace App\Services;

use App\Models\Central\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class PlanService
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository
    ) {}

    public function list(): Collection
    {
        return $this->planRepository->all();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->planRepository->paginate($perPage);
    }

    public function findOrFail(int $id): Plan
    {
        $plan = $this->planRepository->findById($id);

        if ($plan === null) {
            throw new InvalidArgumentException("Plano #{$id} não encontrado.");
        }

        return $plan;
    }

    public function create(array $data): Plan
    {
        return $this->planRepository->create($data);
    }

    public function update(int $id, array $data): Plan
    {
        $plan = $this->findOrFail($id);

        return $this->planRepository->update($plan, $data);
    }

    public function delete(int $id): void
    {
        $plan = $this->findOrFail($id);

        if ($plan->tenants()->exists()) {
            throw new InvalidArgumentException('Não é possível excluir um plano com tenants vinculados.');
        }

        $this->planRepository->delete($plan);
    }

    /**
     * Sincroniza os entitlements de um plano.
     *
     * @param  array<int, array{entitlement_id: int, value: mixed}>  $entitlements
     */
    public function syncEntitlements(int $planId, array $entitlements): Plan
    {
        $plan = $this->findOrFail($planId);

        $pivotData = [];

        foreach ($entitlements as $item) {
            $pivotData[(int) $item['entitlement_id']] = $item['value'];
        }

        $this->planRepository->syncEntitlements($plan, $pivotData);

        return $plan->load('entitlements');
    }
}
