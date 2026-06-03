<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Lançada quando uma etapa de legalização está bloqueada porque
 * há pendências críticas abertas em outras etapas ou porque o
 * processo não foi iniciado.
 */
class EtapaBlockedException extends DomainException
{
    public function statusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
