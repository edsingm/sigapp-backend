<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base para exceções de domínio.
 *
 * Encapsula um HTTP status code e expõe `render()` para integração
 * com o handler de exceções do Laravel 11+ (`bootstrap/app.php`).
 */
abstract class DomainException extends RuntimeException
{
    abstract public function statusCode(): int;

    /** @return array<string, mixed> */
    public function toResponsePayload(): array
    {
        return ['message' => $this->getMessage()];
    }
}
