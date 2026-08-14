<?php

declare(strict_types=1);

namespace App\Enums;

enum PrivacyRequestStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case WAITING_IDENTITY = 'waiting_identity';
    case FULFILLED = 'fulfilled';
    case REFUSED = 'refused';
    case CANCELLED = 'cancelled';
}
