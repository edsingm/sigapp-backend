<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Common\EntitlementType;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\TenantAddonSubscriptionRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Support\EntitlementCatalog;
use InvalidArgumentException;

class PlanMatrixService
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TenantAddonSubscriptionRepositoryInterface $tenantAddonSubscriptionRepository,
    ) {}

    /**
     * Resolve a matriz de features e limites de um plano pelo modelo ou slug.
     *
     * @return array{features: array<string, mixed>, limits: array<string, int|float>}
     */
    public function resolve(Plan|string|null $plan): array
    {
        $planModel = $this->planFrom($plan);

        if ($planModel === null) {
            throw new InvalidArgumentException('Plano não informado ou não encontrado para resolução da matriz.');
        }

        return $this->withLegacyAliases($this->planRepository->getMatrix($planModel->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function features(Plan|string|null $plan): array
    {
        return $this->resolve($plan)['features'];
    }

    /**
     * @return array<string, int|float>
     */
    public function limits(Plan|string|null $plan): array
    {
        return $this->resolve($plan)['limits'];
    }

    public function hasFeature(Plan|string|null $plan, string $path): bool
    {
        $value = $this->featureValue($plan, $path);

        return $value === true;
    }

    public function featureValue(Plan|string|null $plan, string $path, mixed $default = null): mixed
    {
        return $this->resolveFeatureValue(
            $this->features($plan),
            EntitlementCatalog::canonicalKey($path),
            $default,
        );
    }

    public function getLimit(Plan|string|null $plan, string $key, int|float $default = 0): int|float
    {
        $value = data_get($this->limits($plan), $key, $default);

        if (! is_numeric($value)) {
            return $default;
        }

        return $key === 'ai_budget' ? (float) $value : (int) $value;
    }

    public function isUnlimitedLimit(Plan|string|null $plan, string $key): bool
    {
        return $this->getLimit($plan, $key) === -1;
    }

    /**
     * Resolve a matriz efetiva de features e limites para um tenant específico,
     * mesclando os entitlements extras do tenant sobre a matriz base do plano.
     *
     * @return array{features: array<string, mixed>, limits: array<string, int|float>}
     */
    public function resolveForTenant(Tenant $tenant): array
    {
        $planId = $tenant->getAttribute('plan_id');
        if (! is_int($planId)) {
            throw new InvalidArgumentException('Tenant não possui plano atribuído.');
        }

        $base = $this->planRepository->getMatrix($planId);
        $stripeAddons = $this->tenantAddonSubscriptionRepository->forTenant($tenant, activeOnly: true);
        $extras = $this->tenantRepository->listExtraEntitlements($tenant);

        if ($stripeAddons->isEmpty() && $extras->isEmpty()) {
            return $this->withLegacyAliases($base);
        }

        $features = $base['features'];
        $limits = $base['limits'];

        foreach ($stripeAddons as $stripeAddon) {
            if (! $stripeAddon->grantsAccess()) {
                continue;
            }

            foreach ($stripeAddon->addon->definition['grants'] ?? [] as $grant) {
                $key = EntitlementCatalog::canonicalKey((string) $grant['key']);
                $grantType = (string) $grant['type'];
                $unitValue = $grant['unit_value'];

                if ($grantType === EntitlementType::FEATURE->value) {
                    if ($unitValue === true) {
                        $this->setFeatureValue($features, $key, true);
                    }

                    continue;
                }

                $baseValue = $limits[$key] ?? 0;
                if ($baseValue === -1) {
                    continue;
                }

                $limits[$key] = $key === 'ai_budget'
                    ? (float) $baseValue + ((float) $unitValue * $stripeAddon->quantity)
                    : (int) $baseValue + ((int) $unitValue * $stripeAddon->quantity);
            }
        }

        // Extras manuais continuam sendo o override final administrado pelo CS.
        foreach ($extras as $extra) {
            $ent = $extra->entitlement;
            $value = $extra->value;

            if ($ent->type === EntitlementType::FEATURE) {
                $this->setFeatureValue($features, (string) $ent->key, (bool) $value);
            } else {
                $limits[$ent->key] = $ent->key === 'ai_budget'
                    ? (float) $value
                    : (int) $value;
            }
        }

        return $this->withLegacyAliases(['features' => $features, 'limits' => $limits]);
    }

    /**
     * Verifica se o tenant possui uma feature, considerando entitlements extras.
     */
    public function hasFeatureForTenant(Tenant $tenant, string $path): bool
    {
        $features = $this->resolveForTenant($tenant)['features'];
        $value = $this->resolveFeatureValue($features, EntitlementCatalog::canonicalKey($path));

        return $value === true;
    }

    /**
     * Obtém o limite para um tenant específico, considerando entitlements extras.
     */
    public function getLimitForTenant(Tenant $tenant, string $key, int|float $default = 0): int|float
    {
        $value = data_get($this->resolveForTenant($tenant)['limits'], $key, $default);

        if (! is_numeric($value)) {
            return $default;
        }

        return $key === 'ai_budget' ? (float) $value : (int) $value;
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

    /**
     * Resolve uma feature, tratando o campo enabled de features pai.
     *
     * @param  array<string, mixed>  $features
     */
    private function resolveFeatureValue(array $features, string $path, mixed $default = null): mixed
    {
        $value = data_get($features, $path, $default);

        if (is_array($value) && array_key_exists('enabled', $value)) {
            return $value['enabled'];
        }

        return $value;
    }

    /**
     * Monta features aninhadas sem perder uma feature pai com a mesma chave.
     * Ex.: "ai" e "ai.contextual" coexistem como enabled + contextual.
     *
     * @param  array<string, mixed>  $features
     */
    private function setFeatureValue(array &$features, string $key, bool $value): void
    {
        $segments = explode('.', $key);
        $last = (string) array_pop($segments);
        $cursor = &$features;

        foreach ($segments as $segment) {
            if (! array_key_exists($segment, $cursor)) {
                $cursor[$segment] = [];
            } elseif (! is_array($cursor[$segment])) {
                $cursor[$segment] = ['enabled' => $cursor[$segment]];
            }

            $cursor = &$cursor[$segment];
        }

        if (is_array($cursor[$last] ?? null)) {
            $cursor[$last]['enabled'] = $value;
        } else {
            $cursor[$last] = $value;
        }

        unset($cursor);
    }

    /**
     * @param  array{features: array<string, mixed>, limits: array<string, int|float>}  $matrix
     * @return array{features: array<string, mixed>, limits: array<string, int|float>}
     */
    private function withLegacyAliases(array $matrix): array
    {
        foreach (EntitlementCatalog::LEGACY_ALIASES as $legacy => $canonical) {
            $missing = new \stdClass;
            $value = $this->resolveFeatureValue($matrix['features'], $canonical, $missing);
            if ($value === $missing) {
                continue;
            }
            $this->setFeatureValue($matrix['features'], $legacy, $value === true);
        }

        return $matrix;
    }
}
