<?php

declare(strict_types=1);

namespace App\Enums\Common;

enum TenantAddonPurchaseStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
}
