<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\LegalizacaoEtapaStatusUpdated;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\User;
use App\Notifications\Workflow\LegalizacaoEtapaStatusUpdatedNotification;
use App\Repositories\Tenant\UserRepository;
use App\Services\Acl\PermissionNameResolver;
use Illuminate\Support\Facades\Notification;

class SendLegalizacaoEtapaStatusUpdatedEmail
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PermissionNameResolver $permissions,
    ) {}

    public function handle(LegalizacaoEtapaStatusUpdated $event): void
    {
        $permission = $this->permissions->forModel(LegalizacaoEtapa::class, 'view');

        if ($permission === null) {
            return;
        }

        $users = $this->userRepository->getAllWithRolesAndPermissions()
            ->filter(fn (User $user) => $this->permissions->userCan($user, $permission))
            ->reject(fn (User $user) => $event->user?->is($user) ?? false);

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new LegalizacaoEtapaStatusUpdatedNotification(
            etapaTitulo: $event->etapa->titulo,
            status: $event->status,
            terrenoNome: $event->etapa->legalizacao?->terreno?->nome,
            terrenoId: $event->etapa->legalizacao?->terreno_id,
        ));
    }
}
