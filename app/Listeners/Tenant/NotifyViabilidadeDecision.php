<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\ViabilidadeDecided;
use App\Services\Tenant\MobilePushService;

class NotifyViabilidadeDecision
{
    public function __construct(
        private readonly MobilePushService $mobilePushService,
    ) {}

    public function handle(ViabilidadeDecided $event): void
    {
        $approved = $event->decision === 'aprovada';

        $this->mobilePushService->notifyAllUsers(
            [
                'title' => $approved ? 'Viabilidade aprovada' : 'Viabilidade reprovada',
                'body' => $approved
                    ? "A viabilidade do terreno {$event->terreno->nome} foi aprovada."
                    : "A viabilidade do terreno {$event->terreno->nome} foi reprovada.",
                'type' => $approved ? 'viabilidade.aprovada' : 'viabilidade.reprovada',
                'entity_type' => 'viabilidade',
                'entity_id' => (string) $event->viabilidade->id,
                'target_route' => "/terrenos/{$event->viabilidade->terreno_id}",
                'payload' => [
                    'tenant_slug' => tenant('slug'),
                    'viabilidade_id' => $event->viabilidade->id,
                    'terreno_id' => $event->viabilidade->terreno_id,
                ],
            ],
            $event->actor
        );
    }
}
