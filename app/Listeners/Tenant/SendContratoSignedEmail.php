<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\ContratoSigned;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\User;
use App\Notifications\Workflow\ContratoSignedNotification;
use App\Repositories\Tenant\UserRepository;
use App\Services\Acl\PermissionNameResolver;
use Illuminate\Support\Facades\Notification;

class SendContratoSignedEmail
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PermissionNameResolver $permissions,
    ) {}

    public function handle(ContratoSigned $event): void
    {
        $permission = $this->permissions->forModel(Contrato::class, 'view');

        if ($permission === null) {
            return;
        }

        $users = $this->userRepository->getAllWithRolesAndPermissions()
            ->filter(fn (User $user) => $this->permissions->userCan($user, $permission))
            ->reject(fn (User $user) => $event->user?->is($user) ?? false);

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new ContratoSignedNotification(
            terrenoNome: $event->terreno->nome,
            contratoId: (string) $event->contract->id,
            terrenoId: $event->terreno->id,
        ));
    }
}
