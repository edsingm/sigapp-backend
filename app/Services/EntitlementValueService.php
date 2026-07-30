<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Common\EntitlementScope;
use App\Enums\Common\EntitlementType;
use InvalidArgumentException;

class EntitlementValueService
{
    public function normalize(
        EntitlementType|string $type,
        string $key,
        mixed $value,
    ): bool|int|float {
        $resolvedType = $type instanceof EntitlementType
            ? $type
            : EntitlementType::from($type);

        if ($resolvedType === EntitlementType::FEATURE) {
            if (! is_bool($value)) {
                throw new InvalidArgumentException("O valor de [{$key}] deve ser booleano.");
            }

            return $value;
        }

        if ($key === 'ai_budget') {
            if (! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException('O orçamento de IA deve ser numérico.');
            }

            if ($value < 0) {
                throw new InvalidArgumentException('O orçamento de IA não pode ser negativo.');
            }

            return $value;
        }

        if (! is_int($value) || $value < -1) {
            throw new InvalidArgumentException(
                "O limite [{$key}] deve ser um inteiro maior ou igual a zero, ou -1 para ilimitado."
            );
        }

        return $value;
    }

    public function validateScope(
        EntitlementType|string $type,
        EntitlementScope|string|null $scope,
    ): EntitlementScope {
        $resolvedType = $type instanceof EntitlementType
            ? $type
            : EntitlementType::from($type);
        $resolvedScope = $scope instanceof EntitlementScope
            ? $scope
            : ($scope !== null ? EntitlementScope::from($scope) : null);

        if ($resolvedType === EntitlementType::LIMIT) {
            if ($resolvedScope !== null && $resolvedScope !== EntitlementScope::INTERNAL) {
                throw new InvalidArgumentException('Entitlements de limite devem usar o escopo internal.');
            }

            return EntitlementScope::INTERNAL;
        }

        if ($resolvedScope === null || $resolvedScope === EntitlementScope::INTERNAL) {
            throw new InvalidArgumentException('Features exigem um escopo api, ui ou composite.');
        }

        return $resolvedScope;
    }
}
