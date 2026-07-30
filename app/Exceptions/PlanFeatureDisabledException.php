<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class PlanFeatureDisabledException extends DomainException
{
    public function __construct(public readonly string $feature)
    {
        parent::__construct('Seu plano atual não permite esta seção.');
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
                'code' => 'PLAN_FEATURE_DISABLED',
                'message' => $this->getMessage(),
                'details' => ['feature' => $this->feature],
            ],
        ];
    }
}
