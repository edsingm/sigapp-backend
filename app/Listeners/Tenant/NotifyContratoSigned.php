<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\ContratoSigned;
use App\Models\Tenant\Contrato;
use App\Services\Acl\PermissionNameResolver;
use App\Services\Tenant\MobilePushService;

class NotifyContratoSigned
{
    public function __construct(
        private readonly MobilePushService $mobilePushService,
        private readonly PermissionNameResolver $permissions,
    ) {}

    public function handle(ContratoSigned $event): void
    {
        $permission = $this->permissions->forModel(Contrato::class, 'view');

        if ($permission === null) {
            return;
        }

        $this->mobilePushService->notifyUsersWithPermission(
            $permission,
            [
                'title' => 'Contrato assinado',
                'body' => "O contrato do terreno {$event->terreno->nome} foi assinado.",
                'type' => 'contrato.assinado',
                'category' => 'contrato.assinado',
                'entity_type' => 'contrato',
                'entity_id' => (string) $event->contract->id,
                'target_route' => "/terrenos/{$event->terreno->id}",
                'payload' => [
                    'tenant_slug' => tenant('slug'),
                    'contrato_id' => $event->contract->id,
                    'terreno_id' => $event->terreno->id,
                ],
            ],
            $event->user,
        );
    }
}
