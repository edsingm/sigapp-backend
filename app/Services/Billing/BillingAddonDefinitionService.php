<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Common\BillingAddonType;
use App\Enums\Common\EntitlementType;
use App\Repositories\Contracts\EntitlementRepositoryInterface;
use App\Support\EntitlementCatalog;
use InvalidArgumentException;

class BillingAddonDefinitionService
{
    public function __construct(
        private readonly EntitlementRepositoryInterface $entitlementRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     * @return array{grants: list<array{key: string, type: string, unit_value: bool|int|float}>}
     */
    public function normalize(BillingAddonType|string $type, array $definition): array
    {
        $resolvedType = $type instanceof BillingAddonType
            ? $type
            : BillingAddonType::from($type);
        $rawGrants = $definition['grants'] ?? null;

        if (! is_array($rawGrants) || $rawGrants === []) {
            throw new InvalidArgumentException('O add-on deve possuir ao menos um grant.');
        }

        $grants = [];
        foreach ($rawGrants as $grant) {
            if (! is_array($grant)) {
                throw new InvalidArgumentException('Cada grant do add-on deve ser um objeto.');
            }

            $key = EntitlementCatalog::canonicalKey((string) ($grant['key'] ?? ''));
            $entitlement = $this->entitlementRepository->findByKey($key);
            if ($entitlement === null) {
                throw new InvalidArgumentException("Entitlement [{$key}] não encontrado.");
            }

            $expectedType = $entitlement->type;
            $grantType = EntitlementType::tryFrom((string) ($grant['type'] ?? ''));
            if ($grantType !== $expectedType) {
                throw new InvalidArgumentException(
                    "O grant [{$key}] deve usar o tipo [{$expectedType->value}]."
                );
            }

            $unitValue = $this->normalizeUnitValue($expectedType, $key, $grant['unit_value'] ?? null);
            $grants[] = [
                'key' => $key,
                'type' => $expectedType->value,
                'unit_value' => $unitValue,
            ];
        }

        if ($resolvedType === BillingAddonType::LIMIT_PACK
            && count(array_filter($grants, static fn (array $grant): bool => $grant['type'] !== EntitlementType::LIMIT->value)) > 0
        ) {
            throw new InvalidArgumentException('Pacotes de limite só podem conter grants de limite.');
        }

        if ($resolvedType === BillingAddonType::FEATURE_UNLOCK
            && count(array_filter($grants, static fn (array $grant): bool => $grant['type'] !== EntitlementType::FEATURE->value)) > 0
        ) {
            throw new InvalidArgumentException('Desbloqueios só podem conter grants de feature.');
        }

        return ['grants' => $grants];
    }

    private function normalizeUnitValue(EntitlementType $type, string $key, mixed $value): bool|int|float
    {
        if ($type === EntitlementType::FEATURE) {
            if ($value !== true) {
                throw new InvalidArgumentException("O grant de feature [{$key}] deve ter unit_value=true.");
            }

            return true;
        }

        if ($key === 'ai_budget') {
            if (! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException('O unit_value de ai_budget deve ser numérico.');
            }

            if ($value <= 0) {
                throw new InvalidArgumentException('O unit_value de ai_budget deve ser maior que zero.');
            }

            return (float) $value;
        }

        if (! is_int($value) || $value <= 0) {
            throw new InvalidArgumentException(
                "O unit_value do limite [{$key}] deve ser um inteiro maior que zero."
            );
        }

        return $value;
    }
}
