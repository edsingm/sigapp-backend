<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\ProjetoFinalizado;
use App\Services\Tenant\MobilePushService;

class NotifyProjetoFinalizado
{
    public function __construct(
        private readonly MobilePushService $mobilePushService,
    ) {}

    public function handle(ProjetoFinalizado $event): void
    {
        $this->mobilePushService->notifyAllUsers([
            'title' => 'Projeto finalizado',
            'body' => "O projeto {$event->projeto->nome} foi finalizado após a legalização.",
            'type' => 'projeto.finalizado',
            'entity_type' => 'projeto',
            'entity_id' => (string) $event->projeto->id,
            'target_route' => "/projetos/{$event->projeto->id}",
            'payload' => [
                'tenant_slug' => tenant('slug'),
                'project_id' => $event->projeto->id,
            ],
        ], $event->user);
    }
}
