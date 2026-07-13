<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class ViabilidadeConflictException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message = 'Conflito de concorrência na viabilidade.',
        private readonly string $errorCode = 'VIABILIDADE_CONFLICT',
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return Response::HTTP_CONFLICT;
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
