<?php

namespace App\Enums\Common;

enum RolesEnum: string
{
    case ADMIN      = 'ADMIN';
    case DIRECTOR   = 'DIRECTOR';
    case MANAGER    = 'MANAGER';
    case SUPERVISOR = 'SUPERVISOR';
    case ANALYST    = 'ANALYST';
    case USER       = 'USER';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN      => language()->t('ADMINISTRATOR'),
            self::DIRECTOR   => language()->t('DIRECTOR'),
            self::MANAGER    => language()->t('MANAGER'),
            self::SUPERVISOR => language()->t('SUPERVISOR'),
            self::ANALYST    => language()->t('ANALYST'),
            self::USER       => language()->t('USER')
        };
    }
}
