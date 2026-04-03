<?php

namespace App\Enums\Common;

enum SubmodulesEnum: string
{
    case TERRAINS = 'terrains';
    case MAPS     = 'maps';

    public function label(): string
    {
        return match ($this) {
            self::TERRAINS => language()->t('TERRAINS'),
            self::MAPS     => language()->t('MAPS'),
        };
    }

    public function module(): ModulesEnum
    {
        return match ($this) {
            self::TERRAINS,
            self::MAPS => ModulesEnum::PROSPECTION,
        };
    }
}
