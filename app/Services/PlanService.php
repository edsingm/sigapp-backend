<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use App\Repositories\Contracts\EntitlementRepositoryInterface;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlanService
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository,
        private readonly EntitlementRepositoryInterface $entitlementRepository,
        private readonly EntitlementValueService $valueService,
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
     * @param  array<int, array{entitlement_id: int, value?: mixed}>  $entitlements
     */
    public function syncEntitlements(int $planId, array $entitlements): Plan
    {
        $plan = $this->findOrFail($planId);

        $pivotData = [];

        foreach ($entitlements as $item) {
            $entitlementId = (int) $item['entitlement_id'];
            if (array_key_exists($entitlementId, $pivotData)) {
                throw new InvalidArgumentException("Entitlement #{$entitlementId} foi informado mais de uma vez.");
            }

            $entitlement = $this->entitlementRepository->findById($entitlementId);
            if (! $entitlement instanceof Entitlement) {
                throw new InvalidArgumentException("Entitlement #{$entitlementId} não encontrado.");
            }

            $value = array_key_exists('value', $item)
                ? $item['value']
                : $entitlement->default_value;
            $pivotData[$entitlementId] = $this->valueService->normalize(
                $entitlement->type,
                $entitlement->key,
                $value,
            );
        }

        return DB::transaction(function () use ($plan, $pivotData): Plan {
            $this->planRepository->syncEntitlements($plan, $pivotData);

            return $plan->load('entitlements');
        });
    }
}
