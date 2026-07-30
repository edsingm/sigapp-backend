<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class StorageQuotaExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O arquivo excede o limite de armazenamento do plano.');
    }
}
