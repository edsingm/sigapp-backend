<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class SignupSlugReservedException extends RuntimeException
{
    public function __construct(
        public readonly string $messageKey = 'SUBDOMAIN_RESERVED',
    ) {
        parent::__construct($messageKey);
    }
}
