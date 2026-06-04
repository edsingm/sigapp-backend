<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\ProjetoFinalizado;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\User;
use App\Notifications\Workflow\ProjetoFinalizadoNotification;
use App\Repositories\Tenant\UserRepository;
use App\Services\Acl\PermissionNameResolver;
use Illuminate\Support\Facades\Notification;

class SendProjetoFinalizadoEmail
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PermissionNameResolver $permissions,
    ) {}

    public function handle(ProjetoFinalizado $event): void
    {
        $permission = $this->permissions->forModel(Projeto::class, 'view');

        if ($permission === null) {
            return;
        }

        $users = $this->userRepository->getAllWithRolesAndPermissions()
            ->filter(fn (User $user) => $this->permissions->userCan($user, $permission))
            ->reject(fn (User $user) => $event->user?->is($user) ?? false);

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new ProjetoFinalizadoNotification(
            projetoNome: $event->projeto->nome,
            projetoId: $event->projeto->id,
        ));
    }
}
