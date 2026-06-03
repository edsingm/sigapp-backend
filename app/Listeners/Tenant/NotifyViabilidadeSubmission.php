<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\ViabilidadeSubmitted;
use App\Models\Tenant\Viabilidade;
use App\Services\Acl\PermissionNameResolver;
use App\Services\Tenant\MobilePushService;

class NotifyViabilidadeSubmission
{
    public function __construct(
        private readonly MobilePushService $mobilePushService,
        private readonly PermissionNameResolver $permissions,
    ) {}

    public function handle(ViabilidadeSubmitted $event): void
    {
        $this->mobilePushService->notifyUsersWithPermission(
            (string) $this->permissions->forModel(Viabilidade::class, 'approve'),
            [
                'title' => 'Viabilidade aguardando aprovação',
                'body' => "A viabilidade do terreno {$event->terreno->nome} aguarda decisão.",
                'type' => 'viabilidade.solicitar_aprovacao',
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
