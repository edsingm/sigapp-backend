<?php

declare(strict_types=1);

namespace App\Enums\Common;

enum BillingAddonType: string
{
    case LIMIT_PACK = 'limit_pack';
    case FEATURE_UNLOCK = 'feature_unlock';
    case BUNDLE = 'bundle';

    public function label(): string
    {
        return match ($this) {
            self::LIMIT_PACK => 'Pacote de limite',
            self::FEATURE_UNLOCK => 'Desbloqueio de feature',
            self::BUNDLE => 'Bundle',
        };
    }
}
