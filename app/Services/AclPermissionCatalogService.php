<?php

namespace App\Services;

use App\Enums\Common\ModulesEnum;

class AclPermissionCatalogService
{
    /**
     * Base actions generated for every module and sub-module.
     */
    private const BASE_ACTIONS = ['view_any', 'view', 'create', 'update', 'delete', 'restore'];

    /**
     * Builds all system permission definitions dynamically from ModulesEnum.
     *
     * Naming convention (underscores become spaces):
     *   Module-level:     "{action} {module}"            → "view any terrenos"
     *   Sub-module level: "{action} {submodule} {module}" → "view any predio terrenos"
     *   Extra actions:    "{action} {module}"            → "export terrenos"
     *
     * To add a new module, sub-module, or extra action, only ModulesEnum needs to change.
     *
     * @return array<int, array{name: string, module: string, action: string, submodule?: string}>
     */
    public function systemPermissionDefinitions(): array
    {
        $definitions = [];

        foreach (ModulesEnum::cases() as $module) {
            $moduleLabel = str_replace('_', ' ', $module->value);

            if ($module->hasSubModules()) {
                // Sub-module level base permissions
                foreach ($module->subModules() as $submodule) {
                    foreach (self::BASE_ACTIONS as $action) {
                        $actionLabel   = str_replace('_', ' ', $action);
                        $definitions[] = [
                            'name'      => "{$actionLabel} {$submodule} {$moduleLabel}",
                            'module'    => $module->value,
                            'submodule' => $submodule,
                            'action'    => $action,
                        ];
                    }
                }
            } else {
                // Module-level base permissions
                foreach (self::BASE_ACTIONS as $action) {
                    $actionLabel   = str_replace('_', ' ', $action);
                    $definitions[] = [
                        'name'   => "{$actionLabel} {$moduleLabel}",
                        'module' => $module->value,
                        'action' => $action,
                    ];
                }
            }

            // Module-level extra actions (export, approve, etc.) — always at module level
            foreach ($module->extraActions() as $action) {
                $actionLabel   = str_replace('_', ' ', $action);
                $definitions[] = [
                    'name'   => "{$actionLabel} {$moduleLabel}",
                    'module' => $module->value,
                    'action' => $action,
                ];
            }
        }

        return $definitions;
    }

    /**
     * @return array<int, string>
     */
    public function allSystemPermissions(): array
    {
        return array_values(array_map(
            static fn (array $permission) => (string) $permission['name'],
            $this->systemPermissionDefinitions()
        ));
    }

    /**
     * @return array<int, string>
     */
    public function deprecatedPermissions(): array
    {
        return array_values((array) config('acl_permissions.deprecated_permissions', []));
    }

    public function isSystemPermission(string $name): bool
    {
        return in_array($name, $this->allSystemPermissions(), true);
    }

    /**
     * @return array<string, array<int, array{name: string, module: string, action: string, submodule?: string}>>
     */
    public function groupedForUi(): array
    {
        $grouped = [];

        foreach ($this->systemPermissionDefinitions() as $permission) {
            $module = $permission['module'] ?? 'outros';
            $grouped[$module][] = $permission;
        }

        ksort($grouped);

        return $grouped;
    }
}
