<?php

namespace App\Services;

use App\Models\Central\Plan;

class PlanEntitlementService
{
    /**
     * Read an entitlement from the current tenant plan (or a provided plan).
     */
    public function get(string $key, mixed $default = null, ?Plan $plan = null): mixed
    {
        $plan ??= tenancy()->tenant?->plan;

        if (!$plan) {
            return $default;
        }

        return $plan->getEntitlement($key, $default);
    }

    /**
     * Check a boolean entitlement.
     */
    public function isEnabled(string $key, ?Plan $plan = null): bool
    {
        return (bool) $this->get($key, false, $plan);
    }

    /**
     * Exact equality check (with normalization for booleans).
     */
    public function matches(string $key, mixed $expected, ?Plan $plan = null): bool
    {
        $actual = $this->get($key, null, $plan);

        if (is_bool($expected)) {
            return (bool) $actual === $expected;
        }

        return (string) $actual === (string) $expected;
    }

    /**
     * Minimum comparison for tiered entitlements (e.g. none < simple < full).
     */
    public function meetsMinimum(string $key, mixed $minimum, ?Plan $plan = null): bool
    {
        $actual = $this->get($key, null, $plan);

        if ($actual === null) {
            return false;
        }

        if (is_numeric($actual) && is_numeric($minimum)) {
            return (float) $actual >= (float) $minimum;
        }

        $actualRank = $this->rankTier((string) $actual);
        $minimumRank = $this->rankTier((string) $minimum);

        if ($actualRank !== null && $minimumRank !== null) {
            return $actualRank >= $minimumRank;
        }

        return (string) $actual === (string) $minimum;
    }

    protected function rankTier(string $value): ?int
    {
        $normalized = strtolower(trim($value));

        $map = [
            'false' => 0,
            'none' => 0,
            'off' => 0,
            'disabled' => 0,
            'basic' => 1,
            'simple' => 1,
            'lite' => 1,
            'advanced' => 2,
            'full' => 2,
            'pro' => 2,
            'enterprise' => 3,
            'true' => 1,
        ];

        return $map[$normalized] ?? null;
    }
}
