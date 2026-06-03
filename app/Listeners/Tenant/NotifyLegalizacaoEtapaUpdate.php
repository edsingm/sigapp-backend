<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\LegalizacaoEtapaStatusUpdated;
use App\Services\Tenant\MobilePushService;

class NotifyLegalizacaoEtapaUpdate
{
    public function __construct(
        private readonly MobilePushService $mobilePushService,
    ) {}

    public function handle(LegalizacaoEtapaStatusUpdated $event): void
    {
        $this->mobilePushService->notifyAllUsers(
            [
                'title' => 'Etapa de legalização atualizada',
                'body' => "A etapa {$event->etapa->titulo} foi atualizada para {$event->status}.",
                'type' => 'legalizacao.etapa.status_atualizado',
                'entity_type' => 'legalizacao_etapa',
                'entity_id' => (string) $event->etapa->id,
                'target_route' => $event->etapa->legalizacao?->terreno_id
                    ? "/terrenos/{$event->etapa->legalizacao->terreno_id}"
                    : '/notifications',
                'payload' => [
                    'tenant_slug' => tenant('slug'),
                    'legalizacao_id' => $event->etapa->legalizacao_id,
                    'etapa_id' => $event->etapa->id,
                ],
            ],
            $event->user
        );
    }
}
