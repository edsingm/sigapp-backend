<?php

namespace App\Enums;

enum AccessLevel: string
{
    case VIEWER = 'viewer';
    case EDITOR = 'editor';
    case MANAGER = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::VIEWER => language()->t('VIEWER'),
            self::EDITOR => language()->t('EDITOR'),
            self::MANAGER => language()->t('MANAGER'),
        };
    }
}
