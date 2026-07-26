<?php

namespace App\Services\Tenant;

use App\Enums\Common\RolesEnum;
use App\Models\Tenant\User;
use App\Repositories\Tenant\UserRepository;
use App\Services\Acl\PermissionNameResolver;
use App\Services\Auth\TenantPasswordResetService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class TenantUserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TenantPasswordResetService $passwordResetService,
        private readonly TenantCacheService $cache,
    ) {}

    private const ADMIN_ROLE_NAMES = [
        RolesEnum::ADMIN->value,
        RolesEnum::DIRECTOR->value,
    ];

    /**
     * Lista usuários com busca opcional, filtros e ordenação.
     */
    public function list(
        ?string $search = null,
        ?string $role = null,
        string $sort = 'name',
        string $order = 'asc',
        int $perPage = 15,
        ?string $status = null,
        ?int $departmentId = null,
        bool $withoutDepartment = false,
        bool $incomplete = false,
    ): LengthAwarePaginator {
        $allowedSorts = ['id', 'name', 'email', 'created_at', 'updated_at', 'status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $order = strtolower($order) === 'desc' ? 'desc' : 'asc';
        $perPage = min($perPage, 100);

        $query = $this->userRepository->queryWithRelations();

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role !== null && $role !== '') {
            $query->whereHas('roles', function (Builder $builder) use ($role): void {
                $builder->where('name', $role);
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($withoutDepartment) {
            $query->whereNull('department_id');
        } elseif ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        // Sem departamento ou sem cargo (role) — vínculo incompleto.
        if ($incomplete) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('department_id')
                    ->orWhereDoesntHave('roles');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function findById(int|string $id): ?User
    {
        return $this->userRepository->find($id);
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function findWithRelations(int|string $id, array $relations = ['roles', 'department']): ?User
    {
        return $this->userRepository->findWithRelations($id, $relations);
    }

    /**
     * @return Collection<int, User>
     */
    public function listForSelect(): Collection
    {
        return $this->userRepository->listForSelect();
    }

    /**
     * Cria um novo usuário com a função especificada.
     * Com `invite=true` (ou sem senha), gera senha aleatória e envia link de definição.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        $invite = (bool) ($data['invite'] ?? false);
        $password = $data['password'] ?? null;

        if ($invite || $password === null || $password === '') {
            $invite = true;
            $password = Str::password(32);
        }

        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'locale' => $data['locale'] ?? 'pt-br',
            'department_id' => $data['department_id'] ?? null,
            'status' => $data['status'] ?? 'Active',
        ]);

        $role = $data['role'] ?? RolesEnum::USER->value;
        $user->syncRoles([$role]);
        $this->flushUserCaches();

        $user = $user->load(['roles', 'permissions', 'department']);

        if ($invite) {
            $this->sendInviteLink($user);
        }

        return $user;
    }

    /**
     * Envia/reenvia o e-mail de convite (template próprio, não o de reset).
     */
    public function sendInviteLink(User $user): string
    {
        try {
            return $this->passwordResetService->sendInviteLinkForCurrentTenant($user);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send user invite link', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return Password::INVALID_USER;
        }
    }

    /**
     * Atualiza os dados do usuário e, opcionalmente, altera sua função.
     * Retorna uma string com o código de erro se a atualização for rejeitada, ou null em caso de sucesso.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, ?User $requestingUser = null): ?string
    {
        $payload = collect($data)->except(['role'])->all();

        if (array_key_exists('password', $payload)) {
            $payload['password'] = Hash::make((string) $payload['password']);
        }

        $user->update($payload);

        if (array_key_exists('role', $data)) {
            $nextRole = (string) $data['role'];
            $isSelfUpdate = $requestingUser && (int) $requestingUser->id === (int) $user->id;
            $isAdminEligible = $user->hasAnyRole(self::ADMIN_ROLE_NAMES);
            $willRemainAdminEligible = in_array($nextRole, self::ADMIN_ROLE_NAMES, true);

            if ($isSelfUpdate && $isAdminEligible && ! $willRemainAdminEligible) {
                if ($this->adminEligibleCount() <= 1) {
                    return 'LAST_TENANT_ADMIN';
                }
            }

            $user->syncRoles([$nextRole]);
            $this->flushUserCaches();
        }

        return null;
    }

    /**
     * Exclui um usuário com proteção de último administrador.
     * Retorna uma string com o código de erro se a exclusão for rejeitada, ou null em caso de sucesso.
     */
    public function delete(User $user, ?User $requestingUser = null): ?string
    {
        if ($requestingUser && (int) $requestingUser->id === (int) $user->id) {
            return 'CANNOT_DELETE_SELF';
        }

        if ($user->hasAnyRole(self::ADMIN_ROLE_NAMES) && $this->adminEligibleCount() <= 1) {
            return 'LAST_TENANT_ADMIN';
        }

        $user->delete();

        return null;
    }

    /**
     * Atualiza as permissões diretas de nível de módulo para um usuário.
     *
     * @param  array<string, mixed>  $permissionsMap  e.g. ['terrenos' => ['view', 'create'], ...]
     */
    public function updateModulePermissions(User $user, array $permissionsMap, PermissionNameResolver $resolver): void
    {
        $flatPermissions = $resolver->expandModulePermissions($permissionsMap);

        $moduleKeys = array_keys($permissionsMap);
        $toRevoke = $user->getDirectPermissions()
            ->filter(fn (Permission $p) => collect($moduleKeys)
                ->contains(fn (string $m) => str_starts_with($p->name, $m.'.')))
            ->pluck('name')
            ->all();

        if (! empty($toRevoke)) {
            $user->revokePermissionTo($toRevoke);
        }

        if (! empty($flatPermissions)) {
            $user->givePermissionTo($flatPermissions);
        }

        $this->flushUserCaches();
    }

    /**
     * Conta usuários que possuem qualquer função elegível para administrador.
     */
    public function adminEligibleCount(): int
    {
        return $this->userRepository->adminEligibleCount(self::ADMIN_ROLE_NAMES);
    }

    private function flushUserCaches(): void
    {
        $this->cache->flushModules('dashboard', 'users', 'terrenos', 'legalizacoes');
    }
}
