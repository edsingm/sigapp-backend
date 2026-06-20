<?php

namespace App\Services;

use App\Enums\Common\ModulesEnum;
use App\Enums\Common\RolesEnum;
use App\Repositories\Tenant\AclSyncRepository;

class TenantAclSyncService
{
    private const LEVEL_MAP = [
        'viewer' => ['viewer'],
        'editor' => ['viewer', 'editor'],
        'manager' => ['viewer', 'editor', 'manager'],
    ];

    public function __construct(
        private readonly AclSyncRepository $repository,
    ) {}

    /**
     * Sincroniza as permissões e funções do sistema para o contexto do tenant atual,
     * aplicando templates JSON de database/rbacTemplates/.
     *
     * @return array<string, int|string|null>
     */
    public function syncForCurrentTenant(): array
    {
        if (! tenancy()->initialized) {
            throw new \RuntimeException('Tenant ACL sync requer contexto de tenant inicializado.');
        }

        $tenant = tenancy()->tenant;

        if (! $tenant) {
            return ['tenant_id' => null, 'permissions_synced' => 0, 'roles_synced' => 0];
        }

        // 1. Sincroniza permissões
        $allPermissions = $this->generateAllPermissions();

        $synced = 0;
        foreach ($allPermissions as $name) {
            if ($this->repository->ensurePermission($name)) {
                $synced++;
            }
        }

        // 2. Sincroniza funções (roles) e aplica templates
        $rolesSynced = 0;

        foreach (RolesEnum::cases() as $roleEnum) {
            $this->repository->ensureRole($roleEnum->value);

            $templatePath = database_path('rbacTemplates/'.strtolower($roleEnum->value).'.json');

            if (! file_exists($templatePath)) {
                continue;
            }

            $template = json_decode(file_get_contents($templatePath), true);
            $role = $this->repository->findRole($roleEnum->value);

            if ($role === null) {
                continue;
            }

            $permissions = $this->resolvePermissions($template['permissions']);

            $this->repository->syncRolePermissions($role, $permissions);
            $rolesSynced++;
        }

        $this->repository->forgetCachedPermissions();

        return [
            'tenant_id' => (string) $tenant->id,
            'permissions_synced' => $synced,
            'roles_synced' => $rolesSynced,
        ];
    }

    /**
     * Gera todos os nomes de permissão do ModulesEnum no formato de notação de ponto.
     *
     * @return array<int, string>
     */
    private function generateAllPermissions(): array
    {
        $levels = array_keys(self::LEVEL_MAP);
        $permissions = [];

        foreach (ModulesEnum::cases() as $module) {
            if ($module->hasSubmodules()) {
                foreach ($module->submodules() as $resource) {
                    foreach ($levels as $level) {
                        $permissions[] = "{$module->value}.{$resource}.{$level}";
                    }
                }
            } else {
                foreach ($levels as $level) {
                    $permissions[] = "{$module->value}.{$level}";
                }
            }
        }

        return $permissions;
    }

    /**
     * Converte um mapa de permissões de template em nomes de permissão planos cumulativos.
     *
     * @param  array<string, string|array<string, string>|null>  $modulePermissions
     * @return array<int, string>
     */
    private function resolvePermissions(array $modulePermissions): array
    {
        $permissions = [];

        foreach ($modulePermissions as $moduleKey => $value) {
            $module = ModulesEnum::tryFrom($moduleKey);
            if (! $module || $value === null) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $resource => $level) {
                    if ($level === null) {
                        continue;
                    }
                    foreach (self::LEVEL_MAP[$level] as $permLevel) {
                        $permissions[] = "{$moduleKey}.{$resource}.{$permLevel}";
                    }
                }
            } else {
                foreach (self::LEVEL_MAP[$value] as $permLevel) {
                    $permissions[] = "{$moduleKey}.{$permLevel}";
                }
            }
        }

        return $permissions;
    }
}
