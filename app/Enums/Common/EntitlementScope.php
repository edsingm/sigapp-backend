<?php

declare(strict_types=1);

namespace App\Enums\Common;

enum EntitlementScope: string
{
    case API = 'api';
    case UI = 'ui';
    case COMPOSITE = 'composite';
    case INTERNAL = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::API => 'API',
            self::UI => 'Interface',
            self::COMPOSITE => 'Composta',
            self::INTERNAL => 'Interna',
        };
    }
}
