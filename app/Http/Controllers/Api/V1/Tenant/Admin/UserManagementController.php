<?php

namespace App\Http\Controllers\Api\V1\Tenant\Admin;

use App\Enums\Common\RolesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateUserModulePermissionsRequest;
use App\Http\Resources\UserResource;
use App\Models\Tenant\User;
use App\Services\ApiResponseService;
use App\Services\LanguageService;
use App\Services\LimitEnforcementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;

class UserManagementController extends Controller
{
    private const ADMIN_ROLE_NAMES = [RolesEnum::ADMIN->value, RolesEnum::DIRECTOR->value];

    /**
     * List tenant users.
     */
    public function index(Request $request)
    {
        $query = User::query()->with('roles');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->role($request->string('role')->toString());
        }

        $sort = $request->string('sort', 'name')->toString();
        $order = strtolower($request->string('order', 'asc')->toString()) === 'desc' ? 'desc' : 'asc';
        if (!in_array($sort, ['id', 'name', 'email', 'created_at', 'updated_at'], true)) {
            $sort = 'name';
        }

        $users = $query
            ->orderBy($sort, $order)
            ->paginate(min((int) $request->integer('per_page', 15), 100));

        $users->through(function (User $user) use ($request) {
            return (new UserResource($user))->toArray($request);
        });

        return ApiResponseService::paginated($users, language()->t('USER_LIST_RETRIEVED'));
    }

    /**
     * Show a single user.
     */
    public function show(int $id)
    {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return ApiResponseService::notFound(language()->t('USER_NOT_FOUND'));
        }

        return ApiResponseService::success(
            new UserResource($user),
            language()->t('USER_RETRIEVED')
        );
    }

    /**
     * Create a tenant user.
     */
    public function store(Request $request)
    {
        $tenant = tenant();
        $limitService = new LimitEnforcementService($tenant);

        if (!$limitService->canCreateUser()) {
            return ApiResponseService::error(
                'LIMIT_EXCEEDED',
                language()->t('USER_LIMIT_REACHED'),
                null,
                403
            );
        }

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', Password::defaults()],
            'role'     => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
            'locale'   => ['sometimes', 'string', 'in:' . implode(',', LanguageService::SUPPORTED_LOCALES)],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'locale'   => $validated['locale'] ?? 'pt-br',
        ]);

        $user->syncRoles([$validated['role']]);

        return ApiResponseService::created(
            new UserResource($user->fresh(['roles', 'permissions'])),
            language()->t('USER_CREATED_SUCCESSFULLY')
        );
    }

    /**
     * Update a tenant user.
     */
    public function update(Request $request, int $id)
    {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return ApiResponseService::notFound(language()->t('USER_NOT_FOUND'));
        }

        $validated = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['sometimes', Password::defaults()],
            'role'     => [
                'sometimes',
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
            'locale'   => ['sometimes', 'string', 'in:' . implode(',', LanguageService::SUPPORTED_LOCALES)],
        ]);

        if (array_key_exists('password', $validated)) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update(collect($validated)->except(['role'])->all());

        if (array_key_exists('role', $validated)) {
            $nextRole = (string) $validated['role'];

            if ($user->hasRole('super_admin') && $nextRole !== 'super_admin') {
                $superAdminCount = User::role('super_admin')->count();

                if ($adminCount <= 1) {
                    return ApiResponseService::error(
                        'LAST_ADMIN',
                        language()->t('USER_ADMIN_CANT_CHANGE_ROLE'),
                        null,
                        400
                    );
                }
            }

            $requestUser = $request->user();
            $isSelfUpdate = $requestUser && (int) $requestUser->id === (int) $user->id;
            $isCurrentlyAdminEligible = $user->hasAnyRole(self::ADMIN_ROLE_NAMES);
            $willRemainAdminEligible = in_array($nextRole, self::ADMIN_ROLE_NAMES, true);

            if ($isSelfUpdate && $isCurrentlyAdminEligible && !$willRemainAdminEligible) {
                $adminEligibleCount = User::query()
                    ->whereHas('roles', function ($query) {
                        $query->whereIn('name', self::ADMIN_ROLE_NAMES)
                            ->where('guard_name', 'web');
                    })
                    ->count();

                if ($adminEligibleCount <= 1) {
                    return ApiResponseService::error(
                        'LAST_TENANT_ADMIN',
                        language()->t('USER_ADMIN_CANT_REMOVE_LAST_ADMIN_ROLE'),
                        null,
                        400
                    );
                }
            }

            $user->syncRoles([$validated['role']]);
        }

        return ApiResponseService::success(
            new UserResource($user->fresh('roles')), language()->t('USER_UPDATED_SUCCESSFULLY')
        );
    }

    /**
     * Delete a tenant user.
     */
    public function destroy(Request $request, int $id)
    {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return ApiResponseService::notFound(language()->t('USER_NOT_FOUND'));
        }

        if ((int) $request->user()?->id === (int) $user->id) {
            return ApiResponseService::error(
                'CANNOT_DELETE_SELF',
                language()->t('USER_CANNOT_DELETE_OWN_ACCOUNT'),
                null,
                400
            );
        }

        if ($user->hasRole('super_admin')) {
            $superAdminCount = User::role('super_admin')->count();
            if ($superAdminCount <= 1) {
                return ApiResponseService::error(
                    'LAST_ADMIN',
                    language()->t('USER_ADMIN_CANT_DELETE_LAST_ADMIN'),
                    null,
                    400
                );
            }
        }

        $user->delete();

        return ApiResponseService::noContent();
    }

    public function updateModulePermissions(
        UpdateUserModulePermissionsRequest $request,
        int $id
    ) {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return ApiResponseService::notFound(language()->t('USER_NOT_FOUND'));
        }

        $permissionsMap  = (array) $request->input('permissions', []);
        $flatPermissions = $this->resolvePermissionsFromMap($permissionsMap);

        // Revoke existing direct permissions for the requested modules only
        $moduleKeys = array_keys($permissionsMap);
        $toRevoke   = $user->getDirectPermissions()
            ->filter(fn (Permission $p) => collect($moduleKeys)
                ->contains(fn (string $m) => str_starts_with($p->name, $m . '.')))
            ->pluck('name')
            ->all();

        if (!empty($toRevoke)) {
            $user->revokePermissionTo($toRevoke);
        }

        if (!empty($flatPermissions)) {
            $user->givePermissionTo($flatPermissions);
        }

        return ApiResponseService::success(
            new UserResource($user->fresh(['roles', 'permissions'])),
                language()->t('USER_PERMISSIONS_UPDATED_SUCCESSFULLY')
        );
    }

    /**
     * Converts a module permissions map to flat dot-notation permission names.
     * Hierarchy is cumulative: manager includes editor and viewer.
     *
     * @param  array<string, string|array<string, string>|null> $modulePermissions
     * @return array<int, string>
     */
    private function resolvePermissionsFromMap(array $modulePermissions): array
    {
        $levelMap = [
            'viewer'  => ['viewer'],
            'editor'  => ['viewer', 'editor'],
            'manager' => ['viewer', 'editor', 'manager'],
        ];

        $permissions = [];

        foreach ($modulePermissions as $moduleKey => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $resource => $level) {
                    if ($level === null || !isset($levelMap[$level])) {
                        continue;
                    }
                    foreach ($levelMap[$level] as $permLevel) {
                        $permissions[] = "{$moduleKey}.{$resource}.{$permLevel}";
                    }
                }
            } else {
                if (!isset($levelMap[$value])) {
                    continue;
                }
                foreach ($levelMap[$value] as $permLevel) {
                    $permissions[] = "{$moduleKey}.{$permLevel}";
                }
            }
        }

        return $permissions;
    }
}
