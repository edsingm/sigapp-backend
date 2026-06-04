<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\LegalizacaoEtapaOverdue;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\User;
use App\Notifications\Workflow\LegalizacaoEtapaOverdueNotification;
use App\Repositories\Tenant\UserRepository;
use App\Services\Acl\PermissionNameResolver;
use Illuminate\Support\Facades\Notification;

class SendLegalizacaoEtapaOverdueEmail
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PermissionNameResolver $permissions,
    ) {}

    public function handle(LegalizacaoEtapaOverdue $event): void
    {
        $etapa = $event->etapa;

        if ($etapa->responsavel) {
            $etapa->responsavel->notify(new LegalizacaoEtapaOverdueNotification(
                etapaNome: $etapa->nome,
                terrenoNome: $etapa->legalizacao?->terreno?->nome,
                terrenoId: $etapa->legalizacao?->terreno_id,
            ));

            return;
        }

        $permission = $this->permissions->forModel(LegalizacaoEtapa::class, 'update');

        if ($permission === null) {
            return;
        }

        $users = $this->userRepository->getAllWithRolesAndPermissions()
            ->filter(fn (User $user) => $this->permissions->userCan($user, $permission));

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new LegalizacaoEtapaOverdueNotification(
            etapaNome: $etapa->nome,
            terrenoNome: $etapa->legalizacao?->terreno?->nome,
            terrenoId: $etapa->legalizacao?->terreno_id,
        ));
    }
}
