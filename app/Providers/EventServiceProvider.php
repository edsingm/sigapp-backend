<?php

declare(strict_types=1);

namespace App\Providers;

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
use App\Listeners\Tenant\SendContratoSignedEmail;
use App\Listeners\Tenant\SendLegalizacaoEtapaOverdueEmail;
use App\Listeners\Tenant\SendLegalizacaoEtapaStatusUpdatedEmail;
use App\Listeners\Tenant\SendProjetoFinalizadoEmail;
use App\Listeners\Tenant\SendViabilidadeDecisionEmail;
use App\Listeners\Tenant\SendViabilidadeSubmissionEmail;
use App\Listeners\Tenant\SendWorkflowTransitionedEmail;
use App\Listeners\Tenant\TransitionRelatedProjetos;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        WorkflowTransitioned::class => [
            RecordWorkflowStatusHistory::class,
            RecordWorkflowActivity::class,
            CreateCommitteeObservationTask::class,
            TransitionRelatedProjetos::class,
            SendWorkflowTransitionedEmail::class,
        ],
        ViabilidadeSubmitted::class => [
            NotifyViabilidadeSubmission::class,
            SendViabilidadeSubmissionEmail::class,
        ],
        ViabilidadeDecided::class => [
            NotifyViabilidadeDecision::class,
            SendViabilidadeDecisionEmail::class,
        ],
        ContratoSigned::class => [
            RecordContractSignedActivity::class,
            SendContratoSignedEmail::class,
        ],
        LegalizacaoEtapaStatusUpdated::class => [
            NotifyLegalizacaoEtapaUpdate::class,
            SendLegalizacaoEtapaStatusUpdatedEmail::class,
        ],
        ProjetoFinalizado::class => [
            NotifyProjetoFinalizado::class,
            SendProjetoFinalizadoEmail::class,
        ],
        LegalizacaoEtapaOverdue::class => [
            NotifyOverdueLegalizacaoEtapa::class,
            SendLegalizacaoEtapaOverdueEmail::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
