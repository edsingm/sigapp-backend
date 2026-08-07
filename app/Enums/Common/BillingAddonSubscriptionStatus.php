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
        return in_array($this, [self::ACTIVE, self::TRIALING, self::PAST_DUE], true);
    }
}
