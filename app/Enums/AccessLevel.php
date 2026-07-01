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
            self::VIEWER => language()->t('ACCESS_LEVEL_VIEWER'),
            self::EDITOR => language()->t('ACCESS_LEVEL_EDITOR'),
            self::MANAGER => language()->t('ACCESS_LEVEL_MANAGER'),
        };
    }
}
