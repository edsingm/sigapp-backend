<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class StorageQuotaExceededException extends DomainException
{
    public function __construct()
    {
        parent::__construct('O arquivo excede o limite de armazenamento do plano.');
    }

    public function statusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function toResponsePayload(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => 'PLAN_LIMIT_EXCEEDED',
                'message' => $this->getMessage(),
                'details' => [
                    'resource' => 'storage_gb',
                ],
            ],
        ];
    }
}
