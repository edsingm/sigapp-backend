<?php

namespace App\Services;

use App\Models\Central\Plan;
use InvalidArgumentException;

class PlanMatrixService
{
    /**
     * Resolve a configured plan matrix entry by plan model or slug.
     *
     * @return array{features: array<string, mixed>, limits: array<string, int>}
     */
    public function resolve(Plan|string|null $plan): array
    {
        $slug = $this->slugFrom($plan);

        if ($slug === null) {
            throw new InvalidArgumentException('Plano não informado para resolução da matriz.');
        }

        $resolved = config("plans.plans.{$slug}");

        if (!is_array($resolved)) {
            throw new InvalidArgumentException("Plano [{$slug}] não está configurado em config/plans.php.");
        }

        return [
            'features' => is_array($resolved['features'] ?? null) ? $resolved['features'] : [],
            'limits' => is_array($resolved['limits'] ?? null) ? $resolved['limits'] : [],
        ];
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
     * @param  iterable<string>  $slugs
     */
    public function assertConfiguredSlugs(iterable $slugs): void
    {
        $configured = array_keys((array) config('plans.plans', []));

        foreach ($slugs as $slug) {
            if (!in_array($slug, $configured, true)) {
                throw new InvalidArgumentException("Plano ativo [{$slug}] não está configurado em config/plans.php.");
            }
        }
    }

    protected function slugFrom(Plan|string|null $plan): ?string
    {
        if ($plan instanceof Plan) {
            return $plan->slug;
        }

        if (is_string($plan) && $plan !== '') {
            return $plan;
        }

        return null;
    }
}
