<?php

namespace App\Services;

use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantEntitlement;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class TenantPlanService
{
    /**
     * Atribui um plano a um tenant, independentemente do plano atual.
     */
    public function assignPlan(string $tenantId, int $planId): Tenant
    {
        $tenant = Tenant::findOrFail($tenantId);
        $plan   = Plan::find($planId);

        if (!$plan || !$plan->is_active) {
            throw new InvalidArgumentException('Plano não encontrado ou inativo.');
        }

        $tenant->update(['plan_id' => $planId]);

        return $tenant->refresh();
    }

    /**
     * Realiza upgrade de plano (novo plano deve ter sort_order maior).
     */
    public function upgradePlan(string $tenantId, int $newPlanId): Tenant
    {
        $tenant      = Tenant::findOrFail($tenantId);
        $currentPlan = $tenant->plan;
        $newPlan     = Plan::find($newPlanId);

        if (!$newPlan || !$newPlan->is_active) {
            throw new InvalidArgumentException('Plano não encontrado ou inativo.');
        }

        if ($currentPlan && $newPlan->sort_order <= $currentPlan->sort_order) {
            throw new InvalidArgumentException(
                'O novo plano deve ter ordem superior ao plano atual para realizar upgrade.'
            );
        }

        $tenant->update(['plan_id' => $newPlanId]);

        return $tenant->refresh();
    }

    /**
     * Realiza downgrade de plano (novo plano deve ter sort_order menor).
     */
    public function downgradePlan(string $tenantId, int $newPlanId): Tenant
    {
        $tenant      = Tenant::findOrFail($tenantId);
        $currentPlan = $tenant->plan;
        $newPlan     = Plan::find($newPlanId);

        if (!$newPlan || !$newPlan->is_active) {
            throw new InvalidArgumentException('Plano não encontrado ou inativo.');
        }

        if ($currentPlan && $newPlan->sort_order >= $currentPlan->sort_order) {
            throw new InvalidArgumentException(
                'O novo plano deve ter ordem inferior ao plano atual para realizar downgrade.'
            );
        }

        $tenant->update(['plan_id' => $newPlanId]);

        return $tenant->refresh();
    }

    /**
     * Lista os entitlements extras de um tenant com o entitlement carregado.
     */
    public function listExtraEntitlements(string $tenantId): Collection
    {
        $tenant = Tenant::findOrFail($tenantId);

        return $tenant->extraEntitlements()->with('entitlement')->get();
    }

    /**
     * Adiciona um entitlement extra ao tenant.
     *
     * @param mixed $value Valor do entitlement (bool para features, int para limites)
     * @param int $price Custo mensal adicional em centavos
     */
    public function addExtraEntitlement(string $tenantId, int $entitlementId, mixed $value, int $price): TenantEntitlement
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (!Entitlement::find($entitlementId)) {
            throw new InvalidArgumentException('Entitlement não encontrado.');
        }

        return TenantEntitlement::create([
            'tenant_id'      => $tenant->id,
            'entitlement_id' => $entitlementId,
            'value'          => $value,
            'price'          => $price,
        ]);
    }

    /**
     * Atualiza o valor e/ou preço de um entitlement extra do tenant.
     *
     * @param array<string, mixed> $data Campos a atualizar (value, price)
     */
    public function updateExtraEntitlement(string $tenantId, int $entitlementId, array $data): TenantEntitlement
    {
        $record = TenantEntitlement::where('tenant_id', $tenantId)
            ->where('entitlement_id', $entitlementId)
            ->firstOrFail();

        $record->update($data);

        return $record->refresh();
    }

    /**
     * Remove um entitlement extra do tenant.
     */
    public function removeExtraEntitlement(string $tenantId, int $entitlementId): void
    {
        TenantEntitlement::where('tenant_id', $tenantId)
            ->where('entitlement_id', $entitlementId)
            ->firstOrFail()
            ->delete();
    }
}
