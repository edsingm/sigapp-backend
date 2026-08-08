<?php

declare(strict_types=1);

namespace App\Enums\Common;

enum BillingAddonSubscriptionStatus: string
{
    case ACTIVE = 'active';
    case TRIALING = 'trialing';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case UNPAID = 'unpaid';
    case INCOMPLETE = 'incomplete';
    case INCOMPLETE_EXPIRED = 'incomplete_expired';

    public function grantsAccess(): bool
    {
        // Add-ons não possuem período de teste. O trial é uma característica
        // exclusiva do plano e não pode liberar um item complementar.
        return in_array($this, [self::ACTIVE, self::PAST_DUE], true);
    }
}
