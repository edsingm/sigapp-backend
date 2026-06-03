<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Lançada quando o contrato não satisfaz os requisitos de validação
 * (tipo, data, partes, documento anexado, etc).
 *
 * Usada em `LandWorkflowService::transition()` ao tentar assinar
 * contrato com dados incompletos.
 */
class ContractValidationException extends DomainException
{
    /**
     * @param  array<int, string>  $missingFields
     */
    public function __construct(
        string $message,
        private readonly array $missingFields = [],
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    /**
     * @return array{message: string, errors: array{missing_fields: array<int, string>}}
     */
    public function toResponsePayload(): array
    {
        return [
            'message' => $this->getMessage(),
            'errors' => [
                'missing_fields' => $this->missingFields,
            ],
        ];
    }
}
