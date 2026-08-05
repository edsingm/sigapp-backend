<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\TenantBillingProfileType;

class BrazilianTaxIdValidator
{
    public function isValid(string $taxId, TenantBillingProfileType $type): bool
    {
        return match ($type) {
            TenantBillingProfileType::PF => $this->isValidCpf(self::digits($taxId)),
            TenantBillingProfileType::PJ => $this->isValidCnpj($taxId),
        };
    }

    public static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * Normaliza CPF/CNPJ preservando as letras permitidas no CNPJ alfanumérico.
     * Máscaras e separadores são removidos; letras são armazenadas em maiúsculo.
     */
    public static function normalizeTaxId(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? '';
    }

    public function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $cpf[$index] * (($position + 1) - $index);
            }

            $digit = (10 * $sum) % 11;
            if ($digit === 10) {
                $digit = 0;
            }

            if ($digit !== (int) $cpf[$position]) {
                return false;
            }
        }

        return true;
    }

    public function isValidCnpj(string $cnpj): bool
    {
        $cnpj = self::normalizeTaxId($cnpj);

        if (
            strlen($cnpj) !== 14
            || preg_match('/^[A-Z0-9]{12}\d{2}$/', $cnpj) !== 1
            || preg_match('/^([A-Z0-9])\1{13}$/', $cnpj) === 1
        ) {
            return false;
        }

        $firstWeights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $secondWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $firstDigit = $this->cnpjDigit(substr($cnpj, 0, 12), $firstWeights);
        if ($firstDigit !== (int) $cnpj[12]) {
            return false;
        }

        $secondDigit = $this->cnpjDigit(substr($cnpj, 0, 13), $secondWeights);

        return $secondDigit === (int) $cnpj[13];
    }

    /** @param list<int> $weights */
    private function cnpjDigit(string $value, array $weights): int
    {
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += $this->cnpjCharacterValue($value[$index]) * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }

    private function cnpjCharacterValue(string $character): int
    {
        return ord($character) - 48;
    }
}
