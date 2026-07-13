<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class MobileCaptureConflictException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(public readonly array $details)
    {
        parent::__construct('O rascunho foi alterado em outro dispositivo.');
    }
}
