<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class LastTenantAdminException extends DomainException
{
    public function __construct()
    {
        parent::__construct('USER_ADMIN_CANT_DELETE_LAST_ADMIN');
    }

    public function statusCode(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    public function toResponsePayload(): array
    {
        $message = language()->t('USER_ADMIN_CANT_DELETE_LAST_ADMIN');

        return [
            'success' => false,
            'error' => [
                'code' => 'LAST_TENANT_ADMIN',
                'message' => $message,
            ],
        ];
    }
}
