<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\UserOnboardingState;
use App\Repositories\Tenant\UserOnboardingRepository;

class UserOnboardingService
{
    /** @var array<string, array<int, array{id: string, label: string, href: string}>> */
    private const CATALOG = [
        'analyst' => [
            ['id' => 'profile', 'label' => 'Revisar perfil', 'href' => '/sig/conta'],
            ['id' => 'dashboard', 'label' => 'Ler o dashboard', 'href' => '/sig/dashboard'],
            ['id' => 'terrain', 'label' => 'Cadastrar o primeiro terreno', 'href' => '/sig/terrenos/novo'],
            ['id' => 'task', 'label' => 'Criar uma tarefa', 'href' => '/sig/tarefas'],
        ],
        'broker' => [
            ['id' => 'profile', 'label' => 'Revisar perfil', 'href' => '/sig/conta'],
            ['id' => 'terrain', 'label' => 'Cadastrar uma oportunidade', 'href' => '/sig/terrenos/novo'],
            ['id' => 'capture', 'label' => 'Testar captura em campo', 'href' => '/sig/terrenos/captura'],
        ],
        'manager' => [
            ['id' => 'profile', 'label' => 'Revisar perfil', 'href' => '/sig/conta'],
            ['id' => 'dashboard', 'label' => 'Ler o dashboard executivo', 'href' => '/sig/dashboard'],
            ['id' => 'committee', 'label' => 'Abrir o comitê', 'href' => '/sig/comite'],
            ['id' => 'task', 'label' => 'Criar uma tarefa', 'href' => '/sig/tarefas'],
        ],
        'executive' => [
            ['id' => 'profile', 'label' => 'Revisar perfil', 'href' => '/sig/conta'],
            ['id' => 'dashboard', 'label' => 'Ler o dashboard executivo', 'href' => '/sig/dashboard'],
            ['id' => 'reports', 'label' => 'Abrir relatórios', 'href' => '/sig/relatorios'],
        ],
    ];

    public function __construct(private readonly UserOnboardingRepository $repository) {}

    /** @return array<string, mixed> */
    public function get(User $user): array
    {
        $state = $this->stateFor($user);
        $steps = self::CATALOG[$state->profile] ?? self::CATALOG['analyst'];
        $completed = array_values(array_intersect($this->completed($state), array_column($steps, 'id')));

        return [
            'catalog_version' => $state->catalog_version,
            'profile' => $state->profile,
            'steps' => $steps,
            'completed_steps' => $completed,
            'completed_count' => count($completed),
            'total_count' => count($steps),
            'progress' => round(count($completed) / count($steps), 4),
            'dismissed' => $state->dismissed_at !== null,
            'dismissed_at' => $state->dismissed_at?->toIso8601String(),
            'resumed_at' => $state->resumed_at?->toIso8601String(),
            'last_event_at' => $state->last_event_at?->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $data */
    /** @return array<string, mixed> */
    public function event(User $user, array $data): array
    {
        $state = $this->stateFor($user);
        $this->repository->recordEvent($user, $data);
        $step = $this->stepForEvent($data);
        $completed = $this->completed($state);
        if ($step !== null && ! in_array($step, $completed, true)) {
            $completed[] = $step;
        }
        $state->update(['completed_steps' => $completed, 'last_event_at' => now()]);

        return $this->get($user);
    }

    /** @return array<string, mixed> */
    public function dismiss(User $user): array
    {
        $state = $this->stateFor($user);
        $state->update(['dismissed_at' => now()]);

        return $this->get($user);
    }

    /** @return array<string, mixed> */
    public function resume(User $user): array
    {
        $state = $this->stateFor($user);
        $state->update(['dismissed_at' => null, 'resumed_at' => now()]);

        return $this->get($user);
    }

    private function stateFor(User $user): UserOnboardingState
    {
        $state = $this->repository->state($user);
        $profile = $this->profileFor($user);
        if ($state->profile !== $profile) {
            $state->update(['profile' => $profile]);
        }

        return $state->fresh() ?? $state;
    }

    private function profileFor(User $user): string
    {
        $role = strtolower((string) $user->getRoleNames()->first());

        return match (true) {
            str_contains($role, 'admin'), str_contains($role, 'executive'), str_contains($role, 'diretor') => 'executive',
            str_contains($role, 'manager'), str_contains($role, 'gestor'), str_contains($role, 'gerente') => 'manager',
            str_contains($role, 'broker'), str_contains($role, 'corretor') => 'broker',
            default => 'analyst',
        };
    }

    /** @return list<string> */
    private function completed(UserOnboardingState $state): array
    {
        $completed = $state->completed_steps;

        return is_array($completed) ? array_values(array_filter($completed, 'is_string')) : [];
    }

    private function stepForEvent(array $data): ?string
    {
        if ($data['event'] === 'onboarding_step_completed') {
            return isset($data['step_id']) && is_string($data['step_id']) ? $data['step_id'] : null;
        }

        return match ($data['event']) {
            'profile_viewed' => 'profile',
            'dashboard_viewed' => 'dashboard',
            'terrain_started', 'terrain_created' => 'terrain',
            'first_task_created' => 'task',
            default => null,
        };
    }
}
