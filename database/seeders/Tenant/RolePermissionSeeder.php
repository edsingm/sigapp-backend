<?php

namespace Database\Seeders\Tenant;

use App\Enums\Common\ModulesEnum;
use App\Enums\Common\RolesEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Hierarquia cumulativa: manager inclui editor e viewer, editor inclui viewer.
     * Assim `can('prospection.terrains.editor')` retorna true para quem tem manager.
     */
    private const LEVEL_MAP = [
        'viewer'  => ['viewer'],
        'editor'  => ['viewer', 'editor'],
        'manager' => ['viewer', 'editor', 'manager'],
    ];

    public function run(): void
    {
        // 1. Sincroniza permissions
        $allPermissions = $this->generateAllPermissions();

        foreach ($allPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Permission::whereNotIn('name', $allPermissions)
            ->whereDoesntHave('roles')
            ->whereDoesntHave('users')
            ->delete();

        $this->command?->info('Permissions sincronizadas: ' . count($allPermissions));

        // 2. Sincroniza roles
        $enumRoles = collect(RolesEnum::cases())->map(fn ($r) => $r->value);

        foreach (RolesEnum::cases() as $roleEnum) {
            Role::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web']);
        }

        Role::whereNotIn('name', $enumRoles->all())
            ->whereDoesntHave('users')
            ->delete();

        // 3. Aplica template de permissões em cada role
        foreach (RolesEnum::cases() as $roleEnum) {
            $templatePath = database_path('rbacTemplates/' . strtolower($roleEnum->value) . '.json');

            if (!file_exists($templatePath)) {
                $this->command?->warn("Template não encontrado para role {$roleEnum->value}, pulando.");
                continue;
            }

            $template = json_decode(file_get_contents($templatePath), true);
            $role     = Role::where('name', $roleEnum->value)->where('guard_name', 'web')->first();

            $permissions = $this->resolvePermissions($template['permissions']);
            $role->syncPermissions($permissions);

            $this->command?->info("Role {$roleEnum->value}: " . count($permissions) . ' permissões atribuídas.');
        }
    }

    /**
     * Gera todos os nomes de permissão possíveis a partir do ModulesEnum.
     * Formato: module.resource.level (com resource) ou module.level (sem resource).
     */
    private function generateAllPermissions(): array
    {
        $levels      = array_keys(self::LEVEL_MAP);
        $permissions = [];

        foreach (ModulesEnum::cases() as $module) {
            if ($module->hasResources()) {
                foreach ($module->resources() as $resource) {
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
     * Converte o mapa do template em nomes de permissão cumulativos.
     * Ex: terrains => manager  →  [prospection.terrains.viewer, prospection.terrains.editor, prospection.terrains.manager]
     */
    private function resolvePermissions(array $modulePermissions): array
    {
        $permissions = [];

        foreach ($modulePermissions as $moduleKey => $value) {
            $module = ModulesEnum::tryFrom($moduleKey);

            if (!$module) {
                $this->command?->warn("Módulo '{$moduleKey}' não existe no ModulesEnum, ignorado.");
                continue;
            }

            if ($value === null) {
                // null = módulo não visível para este papel, nenhuma permissão atribuída
                continue;
            }

            if (is_array($value)) {
                // Módulo com resources: ex prospection => { terrains: manager, maps: editor }
                foreach ($value as $resource => $level) {
                    if ($level === null) {
                        continue;
                    }
                    foreach (self::LEVEL_MAP[$level] as $permLevel) {
                        $permissions[] = "{$moduleKey}.{$resource}.{$permLevel}";
                    }
                }
            } else {
                // Módulo sem resources: ex viability => manager
                foreach (self::LEVEL_MAP[$value] as $permLevel) {
                    $permissions[] = "{$moduleKey}.{$permLevel}";
                }
            }
        }

        return $permissions;
    }
}

