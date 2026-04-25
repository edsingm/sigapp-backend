<?php

namespace App\Enums;

enum PerfilFinanciamento: string
{
    case CEF = 'cef';
    case PROPRIO = 'proprio';

    public function label(): string
    {
        return match ($this) {
            self::CEF => 'CEF (Caixa Econômica Federal)',
            self::PROPRIO => 'Financiamento Próprio',
        };
    }

    public function isCef(): bool
    {
        return $this === self::CEF;
    }

    public function isProprio(): bool
    {
        return $this === self::PROPRIO;
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
