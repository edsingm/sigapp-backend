<?php

declare(strict_types=1);

namespace App\Enums;

enum PrivacyRequestKind: string
{
    case ACCESS = 'access';
    case PORTABILITY = 'portability';
    case ERASURE = 'erasure';
    case CORRECTION = 'correction';
    case INFO = 'info';
}
