<?php

namespace App\Enums\Common;

enum EntitlementType: string
{
    case FEATURE = 'feature';
    case LIMIT   = 'limit';

    public function label(): string
    {
        return match ($this) {
            self::FEATURE => 'Funcionalidade',
            self::LIMIT   => 'Limite',
        };
    }
}
