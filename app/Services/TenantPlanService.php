<?php

namespace App\Services;

use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use App\Models\Central\Tenant as TenantModel;
use App\Models\Central\TenantEntitlement;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class TenantPlanService
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}
    /**
     * Atribui um plano a um tenant, independentemente do plano atual.
     */
    public function assignPlan(string $tenantId, int $planId): TenantModel
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (! $tenant) {
            throw new InvalidArgumentException('Tenant não encontrado.');
        }

        $plan = Plan::find($planId);

        if (! $plan || ! $plan->is_active) {
            throw new InvalidArgumentException('Plano não encontrado ou inativo.');
        }

        return $this->tenantRepository->updatePlan($tenant, $planId);
    }

    /**
     * Realiza upgrade de plano (novo plano deve ter sort_order maior).
     */
    public function upgradePlan(string $tenantId, int $newPlanId): TenantModel
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (! $tenant) {
            throw new InvalidArgumentException('Tenant não encontrado.');
        }

        $currentPlan = $tenant->plan;
        $newPlan = Plan::find($newPlanId);

        if (! $newPlan || ! $newPlan->is_active) {
            throw new InvalidArgumentException('Plano não encontrado ou inativo.');
        }

        if ($currentPlan && $newPlan->sort_order <= $currentPlan->sort_order) {
            throw new InvalidArgumentException(
                'O novo plano deve ter ordem superior ao plano atual para realizar upgrade.'
            );
        }

        return $this->tenantRepository->updatePlan($tenant, $newPlanId);
    }

    /**
     * Realiza downgrade de plano (novo plano deve ter sort_order menor).
     */
    public function downgradePlan(string $tenantId, int $newPlanId): TenantModel
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (! $tenant) {
            throw new InvalidArgumentException('Tenant não encontrado.');
        }

        $currentPlan = $tenant->plan;
        $newPlan = Plan::find($newPlanId);

        if (! $newPlan || ! $newPlan->is_active) {
            throw new InvalidArgumentException('Plano não encontrado ou inativo.');
        }

        if ($currentPlan && $newPlan->sort_order >= $currentPlan->sort_order) {
            throw new InvalidArgumentException(
                'O novo plano deve ter ordem inferior ao plano atual para realizar downgrade.'
            );
        }

        return $this->tenantRepository->updatePlan($tenant, $newPlanId);
    }

    /**
     * Lista os entitlements extras de um tenant com o entitlement carregado.
     */
    public function listExtraEntitlements(string $tenantId): Collection
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (! $tenant) {
            throw new InvalidArgumentException('Tenant não encontrado.');
        }

        return $this->tenantRepository->listExtraEntitlements($tenant);
    }

    /**
     * Adiciona um entitlement extra ao tenant.
     *
     * @param  mixed  $value  Valor do entitlement (bool para features, int para limites)
     * @param  int  $price  Custo mensal adicional em centavos
     */
    public function addExtraEntitlement(string $tenantId, int $entitlementId, mixed $value, int $price): TenantEntitlement
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (! $tenant) {
            throw new InvalidArgumentException('Tenant não encontrado.');
        }

        if (! Entitlement::find($entitlementId)) {
            throw new InvalidArgumentException('Entitlement não encontrado.');
        }

        return $this->tenantRepository->addExtraEntitlement($tenant, $entitlementId, $value, $price);
    }

    /**
     * Atualiza o valor e/ou preço de um entitlement extra do tenant.
     *
     * @param  array<string, mixed>  $data  Campos a atualizar (value, price)
     */
    public function updateExtraEntitlement(string $tenantId, int $entitlementId, array $data): TenantEntitlement
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (! $tenant) {
            throw new InvalidArgumentException('Tenant não encontrado.');
        }

        return $this->tenantRepository->updateExtraEntitlement($tenant, $entitlementId, $data);
    }

    /**
     * Remove um entitlement extra do tenant.
     */
    public function removeExtraEntitlement(string $tenantId, int $entitlementId): void
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (! $tenant) {
            throw new InvalidArgumentException('Tenant não encontrado.');
        }

        $this->tenantRepository->removeExtraEntitlement($tenant, $entitlementId);
    }
}
