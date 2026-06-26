<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\WorkflowTransitioned;
use App\Models\Tenant\Terreno;
use App\Services\Acl\PermissionNameResolver;
use App\Services\Tenant\MobilePushService;

class NotifyWorkflowTransitioned
{
    public function __construct(
        private readonly MobilePushService $mobilePushService,
        private readonly PermissionNameResolver $permissions,
    ) {}

    public function handle(WorkflowTransitioned $event): void
    {
        $permission = $this->permissions->forModel(Terreno::class, 'update');

        if ($permission === null) {
            return;
        }

        $this->mobilePushService->notifyUsersWithPermission(
            $permission,
            [
                'title' => 'Mudança de status',
                'body' => "{$event->terreno->nome} mudou para {$event->newLabel}.",
                'type' => 'workflow.transicao',
                'category' => 'workflow.transicao',
                'entity_type' => 'terreno',
                'entity_id' => (string) $event->terreno->id,
                'target_route' => "/terrenos/{$event->terreno->id}",
                'payload' => [
                    'tenant_slug' => tenant('slug'),
                    'terreno_id' => $event->terreno->id,
                    'previous_stage' => $event->previousStage,
                    'new_stage' => $event->newStage,
                ],
            ],
            $event->user,
        );
    }
}
