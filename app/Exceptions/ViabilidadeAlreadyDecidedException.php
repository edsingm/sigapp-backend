<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Lançada quando uma viabilidade já foi decidida (aprovada/rejeitada)
 * e o cliente tenta operar sobre ela de uma forma que requer estado
 * pendente.
 */
class ViabilidadeAlreadyDecidedException extends DomainException
{
    public function statusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
