<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class DocumentAnalysisUnsupportedException extends DomainException
{
    public function __construct(string $message = 'Apenas arquivos PDF podem ser analisados.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    public function toResponsePayload(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => 'DOCUMENT_ANALYSIS_UNSUPPORTED_TYPE',
                'message' => $this->getMessage(),
                'details' => null,
            ],
        ];
    }
}
