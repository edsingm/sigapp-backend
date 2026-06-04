<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\ViabilidadeSubmitted;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Notifications\Workflow\ViabilidadeSubmittedNotification;
use App\Repositories\Tenant\UserRepository;
use App\Services\Acl\PermissionNameResolver;
use Illuminate\Support\Facades\Notification;

class SendViabilidadeSubmissionEmail
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PermissionNameResolver $permissions,
    ) {}

    public function handle(ViabilidadeSubmitted $event): void
    {
        $permission = $this->permissions->forModel(Viabilidade::class, 'approve');

        if ($permission === null) {
            return;
        }

        $users = $this->userRepository->getAllWithRolesAndPermissions()
            ->filter(fn (User $user) => $this->permissions->userCan($user, $permission))
            ->reject(fn (User $user) => $event->actor?->is($user) ?? false);

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new ViabilidadeSubmittedNotification(
            terrenoNome: $event->terreno->nome,
            viabilidadeId: $event->viabilidade->id,
            terrenoId: $event->viabilidade->terreno_id,
        ));
    }
}
