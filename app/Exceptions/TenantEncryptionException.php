<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TenantEncryptionException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'TENANT_ENCRYPTION_UNAVAILABLE',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function statusCode(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }

    /** @return array<string, mixed> */
    public function toResponsePayload(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => __($this->errorCode),
            ],
        ];
    }
}
