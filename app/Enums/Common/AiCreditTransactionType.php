<?php

declare(strict_types=1);

namespace App\Enums\Common;

enum AiCreditTransactionType: string
{
    case CREDIT = 'credit';
    case CONSUMPTION = 'consumption';
}
