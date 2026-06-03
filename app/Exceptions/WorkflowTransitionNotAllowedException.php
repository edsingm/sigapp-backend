<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Lançada quando uma transição de workflow não é permitida pelo estado atual.
 *
 * Usada em `LandWorkflowService::transition()` quando o status atual
 * não permite ir para o status solicitado.
 */
class WorkflowTransitionNotAllowedException extends DomainException
{
    public function statusCode(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
