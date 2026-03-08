<?php

namespace App\Enums;

enum AccessLevel: string
{
    case VIEWER  = 'viewer';
    case EDITOR  = 'editor';
    case MANAGER = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::VIEWER  => 'Visualizador',
            self::EDITOR  => 'Editor',
            self::MANAGER => 'Gerente',
        };
    }
}
