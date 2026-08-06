<?php

declare(strict_types=1);

namespace App\Enums\Common;

enum AdminMfaChallengeStatus: string
{
    case PENDING = 'pending';
    case CONSUMED = 'consumed';
    case INVALIDATED = 'invalidated';
}
