<?php

namespace App\Repositories\Contracts;

use App\Models\Central\Plan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface PlanRepositoryInterface
{
    public function all(): Collection;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?Plan;

    public function findBySlug(string $slug): ?Plan;

    public function create(array $data): Plan;

    public function update(Plan $plan, array $data): Plan;

    public function delete(Plan $plan): void;

    /**
     * Retorna os entitlements do plano com o pivot value, indexados por entitlement_id.
     * Resultado cacheado por plan_id.
     *
     * @return array{features: array<string, mixed>, limits: array<string, int>}
     */
    public function getMatrix(int $planId): array;

    /**
     * Sincroniza os entitlements de um plano.
     * $entitlements = [entitlement_id => value, ...]
     *
     * @param array<int, mixed> $entitlements
     */
    public function syncEntitlements(Plan $plan, array $entitlements): void;

    public function invalidateMatrixCache(int $planId): void;
}
