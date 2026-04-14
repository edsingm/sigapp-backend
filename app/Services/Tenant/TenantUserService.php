<?php

namespace App\Services\Tenant;

use App\Enums\Common\RolesEnum;
use App\Models\Tenant\User;
use App\Services\Acl\PermissionNameResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class TenantUserService
{
    private const ADMIN_ROLE_NAMES = [RolesEnum::ADMIN->value, RolesEnum::DIRECTOR->value];

    /**
     * Lista usuários com busca opcional, filtro de função e ordenação.
     */
    public function list(
        ?string $search,
        ?string $role,
        string $sort = 'name',
        string $order = 'asc',
        int $perPage = 15,
    ): LengthAwarePaginator {
        $allowedSorts = ['id', 'name', 'email', 'created_at', 'updated_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $order = strtolower($order) === 'desc' ? 'desc' : 'asc';
        $perPage = min($perPage, 100);

        $query = User::query()->with(['roles', 'department', 'position']);

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role !== null && $role !== '') {
            $query->role($role);
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    /**
     * Cria um novo usuário com a função especificada.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'locale' => $data['locale'] ?? 'pt-br',
            'department_id' => $data['department_id'] ?? null,
            'position_id' => $data['position_id'] ?? null,
        ]);

        $role = $data['role'] ?? RolesEnum::USER->value;
        $user->syncRoles([$role]);

        return $user->fresh(['roles', 'permissions', 'department', 'position']);
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
    }

    /**
     * Conta usuários que possuem qualquer função elegível para administrador.
     */
    public function adminEligibleCount(): int
    {
        return User::query()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', self::ADMIN_ROLE_NAMES)
                    ->where('guard_name', 'web');
            })
            ->count();
    }
}
