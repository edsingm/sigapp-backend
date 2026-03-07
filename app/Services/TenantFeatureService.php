<?php

namespace App\Services;

use App\Models\Central\Plan;

class TenantFeatureService
{
    public function __construct(
        protected PlanEntitlementService $entitlementService
    ) {
    }

    protected ?Plan $plan = null;

    /**
     * Set the plan to check features against.
     */
    public function forPlan(Plan $plan): self
    {
        $this->plan = $plan;

        return $this;
    }

    /**
     * Get the current plan from tenant context.
     */
    protected function getPlan(): ?Plan
    {
        if ($this->plan) {
            return $this->plan;
        }

        $tenant = tenancy()->tenant;

        return $tenant?->plan;
    }

    /**
     * Check if a feature is enabled.
     */
    public function hasFeature(string $feature): bool
    {
        $plan = $this->getPlan();

        if (!$plan) {
            return false;
        }

        return $plan->hasFeature($feature);
    }

    /**
     * Check if viability module is enabled.
     */
    public function isViabilityEnabled(): bool
    {
        return (bool) $this->entitlementService->get('viabilidade.enabled', false, $this->getPlan());
    }

    /**
     * Check if full viability module is available.
     */
    public function hasFullViability(): bool
    {
        return $this->entitlementService->meetsMinimum('viabilidade.tier', 'full', $this->getPlan());
    }

    /**
     * Check if API access is enabled.
     */
    public function hasApiAccess(): bool
    {
        return $this->entitlementService->isEnabled('api_access.enabled', $this->getPlan());
    }

    /**
     * Check if SSO is enabled.
     */
    public function hasSsoEnabled(): bool
    {
        return $this->entitlementService->isEnabled('sso.enabled', $this->getPlan());
    }

    /**
     * Check if advanced reports are available.
     */
    public function hasAdvancedReports(): bool
    {
        return $this->entitlementService->meetsMinimum('reports.tier', 'advanced', $this->getPlan());
    }

    /**
     * Check if user can create more users.
     */
    public function canCreateUsers(int $currentCount): bool
    {
        $plan = $this->getPlan();

        if (!$plan) {
            return false;
        }

        if ($plan->hasUnlimitedUsers()) {
            return true;
        }

        return $currentCount < $plan->max_users;
    }

    /**
     * Check if user can create more terrenos.
     */
    public function canCreateTerrenos(int $currentCount): bool
    {
        $plan = $this->getPlan();

        if (!$plan) {
            return false;
        }

        if ($plan->hasUnlimitedTerrenos()) {
            return true;
        }

        return $currentCount < $plan->max_terrenos;
    }

    /**
     * Check if storage limit is exceeded.
     */
    public function isStorageExceeded(float $usedGb): bool
    {
        $plan = $this->getPlan();

        if (!$plan) {
            return true;
        }

        return $usedGb >= $plan->max_storage_gb;
    }

    /**
     * Get remaining users quota.
     */
    public function getRemainingUsers(int $currentCount): int|string
    {
        $plan = $this->getPlan();

        if (!$plan) {
            return 0;
        }

        if ($plan->hasUnlimitedUsers()) {
            return 'unlimited';
        }

        return max(0, $plan->max_users - $currentCount);
    }

    /**
     * Get remaining terrenos quota.
     */
    public function getRemainingTerrenos(int $currentCount): int|string
    {
        $plan = $this->getPlan();

        if (!$plan) {
            return 0;
        }

        if ($plan->hasUnlimitedTerrenos()) {
            return 'unlimited';
        }

        return max(0, $plan->max_terrenos - $currentCount);
    }

    /**
     * Get all feature flags.
     */
    public function getFeatureFlags(): array
    {
        $plan = $this->getPlan();

        if (!$plan) {
            return [];
        }

        return $plan->feature_flags;
    }

    /**
     * Get normalized entitlements map (raw JSON if present, otherwise legacy-derived via model accessor methods).
     */
    public function getEntitlements(): array
    {
        $plan = $this->getPlan();

        if (!$plan) {
            return [];
        }

        return is_array($plan->entitlements) && $plan->entitlements !== []
            ? $plan->entitlements
            : [
                'users' => ['max' => $plan->getEntitlement('users.max')],
                'terrenos' => ['max' => $plan->getEntitlement('terrenos.max')],
                'storage' => ['max_gb' => $plan->getEntitlement('storage.max_gb')],
                'viabilidade' => [
                    'enabled' => $plan->getEntitlement('viabilidade.enabled'),
                    'tier' => $plan->getEntitlement('viabilidade.tier'),
                ],
                'reports' => ['tier' => $plan->getEntitlement('reports.tier')],
                'api_access' => ['enabled' => $plan->getEntitlement('api_access.enabled')],
                'sso' => ['enabled' => $plan->getEntitlement('sso.enabled')],
                'integrations' => ['full' => $plan->getEntitlement('integrations.full')],
                'support' => ['priority' => $plan->getEntitlement('support.priority')],
            ];
    }
}
