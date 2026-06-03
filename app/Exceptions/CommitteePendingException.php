<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Lançada quando uma operação requer um comitê aprovado, mas o comitê
 * ainda está pendente.
 */
class CommitteePendingException extends DomainException
{
    public function statusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
