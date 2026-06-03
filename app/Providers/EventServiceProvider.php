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
        ],
        ViabilidadeSubmitted::class => [
            NotifyViabilidadeSubmission::class,
        ],
        ViabilidadeDecided::class => [
            NotifyViabilidadeDecision::class,
        ],
        ContratoSigned::class => [
            RecordContractSignedActivity::class,
        ],
        LegalizacaoEtapaStatusUpdated::class => [
            NotifyLegalizacaoEtapaUpdate::class,
        ],
        ProjetoFinalizado::class => [
            NotifyProjetoFinalizado::class,
        ],
        LegalizacaoEtapaOverdue::class => [
            NotifyOverdueLegalizacaoEtapa::class,
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
