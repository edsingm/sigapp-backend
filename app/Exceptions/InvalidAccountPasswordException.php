<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class InvalidAccountPasswordException extends DomainException
{
    public function __construct()
    {
        parent::__construct('PRIVACY_ERASURE_INVALID_PASSWORD');
    }

    public function statusCode(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    public function toResponsePayload(): array
    {
        $message = language()->t('PRIVACY_ERASURE_INVALID_PASSWORD');

        return [
            'success' => false,
            'error' => [
                'code' => 'PRIVACY_ERASURE_INVALID_PASSWORD',
                'message' => $message,
            ],
        ];
    }
}
