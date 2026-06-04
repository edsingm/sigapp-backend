<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\ViabilidadeDecided;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Notifications\Workflow\ViabilidadeDecidedNotification;
use App\Repositories\Tenant\UserRepository;
use App\Services\Acl\PermissionNameResolver;
use Illuminate\Support\Facades\Notification;

class SendViabilidadeDecisionEmail
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PermissionNameResolver $permissions,
    ) {}

    public function handle(ViabilidadeDecided $event): void
    {
        $permission = $this->permissions->forModel(Terreno::class, 'view');

        if ($permission === null) {
            return;
        }

        $users = $this->userRepository->getAllWithRolesAndPermissions()
            ->filter(fn (User $user) => $this->permissions->userCan($user, $permission))
            ->reject(fn (User $user) => $event->actor?->is($user) ?? false);

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new ViabilidadeDecidedNotification(
            terrenoNome: $event->terreno->nome,
            decision: $event->decision,
            terrenoId: $event->terreno->id,
        ));
    }
}
