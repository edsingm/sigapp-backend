<?php

declare(strict_types=1);

namespace App\Enums\Common;

enum AdminMfaChallengePurpose: string
{
    case SETUP = 'setup';
    case LOGIN = 'login';
    case ROTATE = 'rotate';
}
