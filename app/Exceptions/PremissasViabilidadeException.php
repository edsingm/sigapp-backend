<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class PremissasViabilidadeException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        private readonly string $errorCode = 'PREMISSAS_VIABILIDADE_ERROR',
        private readonly array $details = [],
        private readonly int $httpStatus = Response::HTTP_UNPROCESSABLE_ENTITY,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array{success: false, error: array{code: string, message: string, details: array<string, mixed>}}
     */
    public function toResponsePayload(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => $this->details,
            ],
        ];
    }
}
