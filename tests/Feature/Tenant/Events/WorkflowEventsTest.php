<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Events;

use App\Enums\WorkflowStatus;
use App\Events\Tenant\ContratoSigned;
use App\Events\Tenant\LegalizacaoEtapaOverdue;
use App\Events\Tenant\LegalizacaoEtapaStatusUpdated;
use App\Events\Tenant\ProjetoFinalizado;
use App\Events\Tenant\ViabilidadeDecided;
use App\Events\Tenant\ViabilidadeSubmitted;
use App\Events\Tenant\WorkflowTransitioned;
use App\Listeners\Tenant\CreateCommitteeObservationTask;
use App\Listeners\Tenant\NotifyLegalizacaoEtapaUpdate;
use App\Listeners\Tenant\NotifyOverdueLegalizacaoEtapa;
use App\Listeners\Tenant\NotifyProjetoFinalizado;
use App\Listeners\Tenant\NotifyViabilidadeDecision;
use App\Listeners\Tenant\NotifyViabilidadeSubmission;
use App\Listeners\Tenant\RecordContractSignedActivity;
use App\Listeners\Tenant\RecordWorkflowActivity;
use App\Listeners\Tenant\RecordWorkflowStatusHistory;
use App\Listeners\Tenant\TransitionRelatedProjetos;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Providers\EventServiceProvider;
use App\Repositories\Contracts\LandWorkflowRepositoryInterface;
use App\Services\Acl\PermissionNameResolver;
use App\Services\Tenant\MobilePushService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WorkflowEventsTest extends TestCase
{
    public function test_event_service_provider_registers_all_workflow_events(): void
    {
        $provider = new EventServiceProvider(app());
        $reflection = new \ReflectionProperty($provider, 'listen');
        $listen = $reflection->getValue($provider);

        $this->assertArrayHasKey(WorkflowTransitioned::class, $listen);
        $this->assertContains(RecordWorkflowStatusHistory::class, $listen[WorkflowTransitioned::class]);
        $this->assertContains(RecordWorkflowActivity::class, $listen[WorkflowTransitioned::class]);
        $this->assertContains(CreateCommitteeObservationTask::class, $listen[WorkflowTransitioned::class]);
        $this->assertContains(TransitionRelatedProjetos::class, $listen[WorkflowTransitioned::class]);

        $this->assertArrayHasKey(ViabilidadeSubmitted::class, $listen);
        $this->assertContains(NotifyViabilidadeSubmission::class, $listen[ViabilidadeSubmitted::class]);

        $this->assertArrayHasKey(ViabilidadeDecided::class, $listen);
        $this->assertContains(NotifyViabilidadeDecision::class, $listen[ViabilidadeDecided::class]);

        $this->assertArrayHasKey(ContratoSigned::class, $listen);
        $this->assertContains(RecordContractSignedActivity::class, $listen[ContratoSigned::class]);

        $this->assertArrayHasKey(LegalizacaoEtapaStatusUpdated::class, $listen);
        $this->assertContains(NotifyLegalizacaoEtapaUpdate::class, $listen[LegalizacaoEtapaStatusUpdated::class]);

        $this->assertArrayHasKey(ProjetoFinalizado::class, $listen);
        $this->assertContains(NotifyProjetoFinalizado::class, $listen[ProjetoFinalizado::class]);

        $this->assertArrayHasKey(LegalizacaoEtapaOverdue::class, $listen);
        $this->assertContains(NotifyOverdueLegalizacaoEtapa::class, $listen[LegalizacaoEtapaOverdue::class]);
    }

    public function test_workflow_transitioned_event_carries_all_properties(): void
    {
        $terreno = new Terreno;
        $terreno->id = 1;
        $user = new User;
        $user->id = 42;

        $event = new WorkflowTransitioned(
            terreno: $terreno,
            previousStatus: WorkflowStatus::EM_ANALISE->value,
            previousStage: 'captacao',
            newStatus: WorkflowStatus::AGUARDANDO_VIABILIDADE->value,
            newStage: 'viabilidade',
            newLabel: 'Aguardando viabilidade',
            user: $user,
            reasonCode: 'test_reason',
            reasonNotes: 'Test notes',
            context: ['key' => 'value'],
        );

        $this->assertSame($terreno, $event->terreno);
        $this->assertSame(WorkflowStatus::EM_ANALISE->value, $event->previousStatus);
        $this->assertSame('captacao', $event->previousStage);
        $this->assertSame(WorkflowStatus::AGUARDANDO_VIABILIDADE->value, $event->newStatus);
        $this->assertSame('viabilidade', $event->newStage);
        $this->assertSame('Aguardando viabilidade', $event->newLabel);
        $this->assertSame($user, $event->user);
        $this->assertSame('test_reason', $event->reasonCode);
        $this->assertSame('Test notes', $event->reasonNotes);
        $this->assertSame(['key' => 'value'], $event->context);
    }

    public function test_record_workflow_status_history_listener_calls_repository(): void
    {
        Event::fake([WorkflowTransitioned::class]);

        $terreno = new Terreno;
        $terreno->id = 1;

        $event = new WorkflowTransitioned(
            terreno: $terreno,
            previousStatus: WorkflowStatus::EM_ANALISE->value,
            previousStage: 'captacao',
            newStatus: WorkflowStatus::AGUARDANDO_VIABILIDADE->value,
            newStage: 'viabilidade',
            newLabel: 'Aguardando viabilidade',
            user: null,
            reasonCode: null,
            reasonNotes: null,
        );

        $repository = $this->createMock(LandWorkflowRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('recordStatusHistory')
            ->with($this->callback(function (array $data) {
                return $data['terreno_id'] === 1
                    && $data['old_status_code'] === WorkflowStatus::EM_ANALISE->value
                    && $data['new_status_code'] === WorkflowStatus::AGUARDANDO_VIABILIDADE->value
                    && $data['new_stage'] === 'viabilidade';
            }));

        $listener = new RecordWorkflowStatusHistory($repository);
        $listener->handle($event);
    }

    public function test_record_workflow_activity_listener_calls_repository(): void
    {
        Event::fake([WorkflowTransitioned::class]);

        $terreno = new Terreno;
        $terreno->id = 1;

        $event = new WorkflowTransitioned(
            terreno: $terreno,
            previousStatus: WorkflowStatus::EM_ANALISE->value,
            previousStage: 'captacao',
            newStatus: WorkflowStatus::AGUARDANDO_VIABILIDADE->value,
            newStage: 'viabilidade',
            newLabel: 'Aguardando viabilidade',
            user: null,
            reasonCode: null,
            reasonNotes: null,
        );

        $repository = $this->createMock(LandWorkflowRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('recordActivity')
            ->with($this->callback(function (array $data) {
                return $data['action'] === 'workflow.transition'
                    && $data['terreno_id'] === 1
                    && $data['summary'] === 'Workflow alterado para Aguardando viabilidade';
            }));

        $listener = new RecordWorkflowActivity($repository);
        $listener->handle($event);
    }

    public function test_transition_related_projetos_skips_for_non_matching_status(): void
    {
        $terreno = new Terreno;
        $terreno->id = 1;

        $event = new WorkflowTransitioned(
            terreno: $terreno,
            previousStatus: WorkflowStatus::EM_ANALISE->value,
            previousStage: 'captacao',
            newStatus: WorkflowStatus::VIABILIDADE_APROVADA->value,
            newStage: 'viabilidade',
            newLabel: 'Viabilidade aprovada',
            user: null,
            reasonCode: null,
            reasonNotes: null,
        );

        $repository = $this->createMock(LandWorkflowRepositoryInterface::class);
        $repository->expects($this->never())->method('transitionProjetosToLegalizacao');
        $repository->expects($this->never())->method('transitionProjetosToFinalizado');
        $repository->expects($this->never())->method('transitionProjetosToCancelado');

        $listener = new TransitionRelatedProjetos($repository);
        $listener->handle($event);
    }

    public function test_transition_related_projetos_calls_legalizacao_for_legalizando(): void
    {
        $terreno = new Terreno;
        $terreno->id = 1;

        $event = new WorkflowTransitioned(
            terreno: $terreno,
            previousStatus: WorkflowStatus::CONTRATO_ASSINADO->value,
            previousStage: 'negociacao_contrato',
            newStatus: WorkflowStatus::LEGALIZANDO->value,
            newStage: 'legalizacao',
            newLabel: 'Legalizando',
            user: null,
            reasonCode: null,
            reasonNotes: null,
        );

        $repository = $this->createMock(LandWorkflowRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('transitionProjetosToLegalizacao')
            ->with(1, null);

        $listener = new TransitionRelatedProjetos($repository);
        $listener->handle($event);
    }

    public function test_viabilidade_submitted_event_carries_properties(): void
    {
        $viabilidade = new Viabilidade;
        $viabilidade->id = 10;
        $terreno = new Terreno;
        $terreno->id = 1;
        $user = new User;
        $user->id = 5;

        $event = new ViabilidadeSubmitted($viabilidade, $terreno, $user);

        $this->assertSame($viabilidade, $event->viabilidade);
        $this->assertSame($terreno, $event->terreno);
        $this->assertSame($user, $event->actor);
    }

    public function test_viabilidade_decided_event_carries_decision(): void
    {
        $viabilidade = new Viabilidade;
        $viabilidade->id = 10;
        $terreno = new Terreno;
        $terreno->id = 1;

        $event = new ViabilidadeDecided($viabilidade, $terreno, 'aprovada', null);

        $this->assertSame('aprovada', $event->decision);
    }

    public function test_contrato_signed_event_carries_properties(): void
    {
        $contract = new Contrato;
        $contract->id = 20;
        $terreno = new Terreno;
        $terreno->id = 1;
        $user = new User;
        $user->id = 5;

        $event = new ContratoSigned($contract, $terreno, $user);

        $this->assertSame($contract, $event->contract);
        $this->assertSame($terreno, $event->terreno);
        $this->assertSame($user, $event->user);
    }

    public function test_record_contract_signed_activity_listener_calls_repository(): void
    {
        $contract = new Contrato;
        $contract->id = 20;
        $contract->terreno_id = 1;
        $contract->contract_type = 'compra_venda';
        $contract->signed_at = now();

        $terreno = new Terreno;
        $terreno->id = 1;

        $event = new ContratoSigned($contract, $terreno, null);

        $repository = $this->createMock(LandWorkflowRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('recordActivity')
            ->with($this->callback(function (array $data) {
                return $data['action'] === 'contract.signed'
                    && $data['entity_id'] === 20
                    && $data['terreno_id'] === 1;
            }));

        $listener = new RecordContractSignedActivity($repository);
        $listener->handle($event);
    }

    public function test_legalizacao_etapa_status_updated_event_carries_properties(): void
    {
        $etapa = new LegalizacaoEtapa;
        $etapa->id = 30;
        $user = new User;
        $user->id = 5;

        $event = new LegalizacaoEtapaStatusUpdated($etapa, 'concluida', $user);

        $this->assertSame($etapa, $event->etapa);
        $this->assertSame('concluida', $event->status);
        $this->assertSame($user, $event->user);
    }

    public function test_projeto_finalizado_event_carries_properties(): void
    {
        $projeto = new Projeto;
        $projeto->id = 40;
        $user = new User;
        $user->id = 5;

        $event = new ProjetoFinalizado($projeto, $user);

        $this->assertSame($projeto, $event->projeto);
        $this->assertSame($user, $event->user);
    }

    public function test_notify_projeto_finalizado_listener_calls_push_service(): void
    {
        $projeto = new Projeto;
        $projeto->id = 40;
        $projeto->nome = 'Test Project';

        $user = new User;
        $user->id = 5;

        $event = new ProjetoFinalizado($projeto, $user);

        $pushService = $this->createMock(MobilePushService::class);
        $pushService->expects($this->once())
            ->method('notifyAllUsers')
            ->with(
                $this->callback(function (array $payload) {
                    return $payload['type'] === 'projeto.finalizado'
                        && $payload['title'] === 'Projeto finalizado'
                        && str_contains($payload['body'], 'Test Project');
                }),
                $user
            );

        $listener = new NotifyProjetoFinalizado($pushService);
        $listener->handle($event);
    }

    public function test_notify_legalizacao_etapa_update_listener_calls_push_service(): void
    {
        $legalizacao = new Legalizacao;
        $legalizacao->terreno_id = 1;

        $etapa = new LegalizacaoEtapa;
        $etapa->id = 30;
        $etapa->titulo = 'Test Etapa';
        $etapa->legalizacao_id = 1;
        $etapa->setRelation('legalizacao', $legalizacao);

        $user = new User;
        $user->id = 5;

        $event = new LegalizacaoEtapaStatusUpdated($etapa, 'concluida', $user);

        $pushService = $this->createMock(MobilePushService::class);
        $pushService->expects($this->once())
            ->method('notifyAllUsers')
            ->with(
                $this->callback(function (array $payload) {
                    return $payload['type'] === 'legalizacao.etapa.status_atualizado'
                        && str_contains($payload['body'], 'Test Etapa')
                        && str_contains($payload['body'], 'concluida');
                }),
                $user
            );

        $listener = new NotifyLegalizacaoEtapaUpdate($pushService);
        $listener->handle($event);
    }

    public function test_notify_viabilidade_decision_listener_sends_approved_notification(): void
    {
        $viabilidade = new Viabilidade;
        $viabilidade->id = 10;
        $viabilidade->terreno_id = 1;

        $terreno = new Terreno;
        $terreno->id = 1;
        $terreno->nome = 'Test Terreno';

        $event = new ViabilidadeDecided($viabilidade, $terreno, 'aprovada', null);

        $pushService = $this->createMock(MobilePushService::class);
        $pushService->expects($this->once())
            ->method('notifyAllUsers')
            ->with(
                $this->callback(function (array $payload) {
                    return $payload['type'] === 'viabilidade.aprovada'
                        && $payload['title'] === 'Viabilidade aprovada'
                        && str_contains($payload['body'], 'aprovada');
                }),
                null
            );

        $listener = new NotifyViabilidadeDecision($pushService);
        $listener->handle($event);
    }

    public function test_notify_viabilidade_submission_listener_calls_push_with_permission(): void
    {
        $viabilidade = new Viabilidade;
        $viabilidade->id = 10;
        $viabilidade->terreno_id = 1;

        $terreno = new Terreno;
        $terreno->id = 1;
        $terreno->nome = 'Test Terreno';

        $user = new User;
        $user->id = 5;

        $event = new ViabilidadeSubmitted($viabilidade, $terreno, $user);

        $pushService = $this->createMock(MobilePushService::class);
        $pushService->expects($this->once())
            ->method('notifyUsersWithPermission')
            ->with(
                $this->callback(fn ($v) => is_string($v)),
                $this->callback(function (array $payload) {
                    return $payload['type'] === 'viabilidade.solicitar_aprovacao'
                        && str_contains($payload['body'], 'aguarda decisão');
                }),
                $user
            );

        $permissions = $this->createMock(PermissionNameResolver::class);
        $permissions->method('forModel')->willReturn('viabilidade.approve');

        $listener = new NotifyViabilidadeSubmission($pushService, $permissions);
        $listener->handle($event);
    }
}
