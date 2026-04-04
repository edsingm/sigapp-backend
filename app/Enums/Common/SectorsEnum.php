<?php

namespace App\Enums\Common;

enum SectorsEnum: string
{
    case PRINCIPAL = 'principal';
    case OPERATION = 'operation';
    case CONFIGURATION = 'configuration';
    case INTELLIGENCE = 'intelligence';
    case ADMINISTRATION = 'administration';

    public function label(): string
    {
        return match ($this) {
            self::PRINCIPAL => language()->t('PRINCIPAL'),
            self::OPERATION => language()->t('OPERATION'),
            self::CONFIGURATION => language()->t('CONFIGURATIONS'),
            self::INTELLIGENCE => language()->t('INTELLIGENCE'),
            self::ADMINISTRATION => language()->t('ADMINISTRATION')
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::PRINCIPAL => 1,
            self::OPERATION => 2,
            self::CONFIGURATION => 3,
            self::INTELLIGENCE => 4,
            self::ADMINISTRATION => 5
        };
    }
}
