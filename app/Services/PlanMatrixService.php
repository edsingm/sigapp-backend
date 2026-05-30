<?php

namespace App\Services;

use App\Enums\Common\EntitlementType;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\PlanRepositoryInterface;
use InvalidArgumentException;

class PlanMatrixService
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository
    ) {}

    /**
     * Resolve a matriz de features e limites de um plano pelo modelo ou slug.
     *
     * @return array{features: array<string, mixed>, limits: array<string, int>}
     */
    public function resolve(Plan|string|null $plan): array
    {
        $planModel = $this->planFrom($plan);

        if ($planModel === null) {
            throw new InvalidArgumentException('Plano não informado ou não encontrado para resolução da matriz.');
        }

        return $this->planRepository->getMatrix($planModel->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function features(Plan|string|null $plan): array
    {
        return $this->resolve($plan)['features'];
    }

    /**
     * @return array<string, int>
     */
    public function limits(Plan|string|null $plan): array
    {
        return $this->resolve($plan)['limits'];
    }

    public function hasFeature(Plan|string|null $plan, string $path): bool
    {
        $value = data_get($this->features($plan), $path);

        return $value === true;
    }

    public function featureValue(Plan|string|null $plan, string $path, mixed $default = null): mixed
    {
        return data_get($this->features($plan), $path, $default);
    }

    public function getLimit(Plan|string|null $plan, string $key, int $default = 0): int
    {
        $value = data_get($this->limits($plan), $key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function isUnlimitedLimit(Plan|string|null $plan, string $key): bool
    {
        return $this->getLimit($plan, $key) === -1;
    }

    /**
     * Resolve a matriz efetiva de features e limites para um tenant específico,
     * mesclando os entitlements extras do tenant sobre a matriz base do plano.
     *
     * @return array{features: array<string, mixed>, limits: array<string, int>}
     */
    public function resolveForTenant(Tenant $tenant): array
    {
        $planId = $tenant->getAttribute('plan_id');
        if (! is_int($planId)) {
            throw new InvalidArgumentException('Tenant não possui plano atribuído.');
        }

        $base = $this->planRepository->getMatrix($planId);
        $extras = $tenant->extraEntitlements()->with('entitlement')->get();

        if ($extras->isEmpty()) {
            return $base;
        }

        $features = $base['features'];
        $limits = $base['limits'];

        foreach ($extras as $extra) {
            $ent = $extra->entitlement;
            $value = $extra->value;

            if ($ent->type === EntitlementType::FEATURE) {
                data_set($features, $ent->key, (bool) $value);
            } else {
                $limits[$ent->key] = (int) $value;
            }
        }

        return ['features' => $features, 'limits' => $limits];
    }

    /**
     * Verifica se o tenant possui uma feature, considerando entitlements extras.
     */
    public function hasFeatureForTenant(Tenant $tenant, string $path): bool
    {
        $value = data_get($this->resolveForTenant($tenant)['features'], $path);

        return $value === true;
    }

    /**
     * Obtém o limite para um tenant específico, considerando entitlements extras.
     */
    public function getLimitForTenant(Tenant $tenant, string $key, int $default = 0): int
    {
        $value = data_get($this->resolveForTenant($tenant)['limits'], $key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Verifica se o limite de uma chave específica é ilimitado para este tenant.
     */
    public function isUnlimitedLimitForTenant(Tenant $tenant, string $key): bool
    {
        return $this->getLimitForTenant($tenant, $key) === -1;
    }

    protected function planFrom(Plan|string|null $plan): ?Plan
    {
        if ($plan instanceof Plan) {
            return $plan;
        }

        if (is_string($plan) && $plan !== '') {
            return $this->planRepository->findBySlug($plan);
        }

        return null;
    }
}
