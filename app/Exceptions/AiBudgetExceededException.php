<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class AiBudgetExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O orçamento mensal de IA do tenant foi excedido.');
    }
}
