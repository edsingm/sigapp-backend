<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\CommitteePendingException;
use App\Exceptions\ContractValidationException;
use App\Exceptions\DomainException;
use App\Exceptions\EtapaBlockedException;
use App\Exceptions\ViabilidadeAlreadyDecidedException;
use App\Exceptions\WorkflowTransitionNotAllowedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class DomainExceptionsTest extends TestCase
{
    public function test_workflow_transition_not_allowed_retorna_422(): void
    {
        $e = new WorkflowTransitionNotAllowedException('Transição não permitida');

        $this->assertInstanceOf(DomainException::class, $e);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $e->statusCode());
        $this->assertSame(['message' => 'Transição não permitida'], $e->toResponsePayload());
    }

    public function test_viabilidade_already_decided_retorna_409(): void
    {
        $e = new ViabilidadeAlreadyDecidedException('Já decidida');

        $this->assertSame(Response::HTTP_CONFLICT, $e->statusCode());
    }

    public function test_committee_pending_retorna_409(): void
    {
        $e = new CommitteePendingException('Comitê pendente');

        $this->assertSame(Response::HTTP_CONFLICT, $e->statusCode());
    }

    public function test_etapa_blocked_retorna_409(): void
    {
        $e = new EtapaBlockedException('Etapa bloqueada');

        $this->assertSame(Response::HTTP_CONFLICT, $e->statusCode());
    }

    public function test_contract_validation_retorna_422_com_missing_fields(): void
    {
        $e = new ContractValidationException('Faltam campos', ['contract_type', 'signed_at']);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $e->statusCode());

        $payload = $e->toResponsePayload();
        $this->assertSame('Faltam campos', $payload['message']);
        $this->assertArrayHasKey('errors', $payload);
        $this->assertSame(['contract_type', 'signed_at'], $payload['errors']['missing_fields']);
    }
}
