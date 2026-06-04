<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\WorkflowTransitioned;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Notifications\Workflow\WorkflowTransitionedNotification;
use App\Repositories\Tenant\UserRepository;
use App\Services\Acl\PermissionNameResolver;
use Illuminate\Support\Facades\Notification;

class SendWorkflowTransitionedEmail
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PermissionNameResolver $permissions,
    ) {}

    public function handle(WorkflowTransitioned $event): void
    {
        $permission = $this->permissions->forModel(Terreno::class, 'update');

        if ($permission === null) {
            return;
        }

        $users = $this->userRepository->getAllWithRolesAndPermissions()
            ->filter(fn (User $user) => $this->permissions->userCan($user, $permission));

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new WorkflowTransitionedNotification(
            terrenoNome: $event->terreno->nome,
            previousStage: $event->previousStage,
            newStage: $event->newStage,
            newLabel: $event->newLabel,
            reasonNotes: $event->reasonNotes,
        ));
    }
}
