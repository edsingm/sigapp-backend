<?php

declare(strict_types=1);

namespace App\Exceptions;

class AdminMfaException extends DomainException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        private readonly int $httpStatus = 422,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }

    public function toResponsePayload(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => language()->t($this->getMessage()),
                'details' => $this->details,
            ],
        ];
    }
}
