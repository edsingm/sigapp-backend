<?php

namespace App\Services;

use App\Enums\AccessLevel;
use App\Enums\Common\ModulesEnum;

/**
 * Maps modules/sub-modules + access levels to concrete Spatie permission names,
 * and resolves the effective access level of a user per module/sub-module.
 *
 * Access levels:
 *   viewer  → view_any, view
 *   editor  → viewer  + create, update
 *   manager → editor  + delete, restore + module-specific extra actions
 *
 * Modules that declare sub-modules (via ModulesEnum::subModules()) resolve
 * permissions at the sub-module level. Others resolve at the module level.
 */
class ModuleAccessService
{
    /**
     * Base actions granted per access level (common to all modules/sub-modules).
     *
     * @return array<int, string>
     */
    private function baseActionsForLevel(AccessLevel $level): array
    {
        return match ($level) {
            AccessLevel::VIEWER  => ['view_any', 'view'],
            AccessLevel::EDITOR  => ['view_any', 'view', 'create', 'update'],
            AccessLevel::MANAGER => ['view_any', 'view', 'create', 'update', 'delete', 'restore'],
        };
    }

    /**
     * Extra actions that only MANAGER level grants for specific modules.
     * These are always at the module level (never at sub-module level).
     *
     * @return array<int, string>
     */
    private function extraActionsForModule(ModulesEnum $module): array
    {
        return $module->extraActions();
    }

    /**
     * Returns all Spatie permission definitions filtered by module and optional sub-module.
     *
     * Passing $submodule = null returns only module-level entries (no submodule key).
     * Passing a string returns only entries for that specific sub-module.
     *
     * @return \Illuminate\Support\Collection<int, array{name:string,module:string,action:string,submodule?:string}>
     */
    private function defsFor(ModulesEnum $module, ?string $submodule = null): \Illuminate\Support\Collection
    {
        /** @var array<int, array{name:string,module:string,action:string,submodule?:string}> $allDefs */
        $allDefs = app(AclPermissionCatalogService::class)->systemPermissionDefinitions();

        return collect($allDefs)->filter(function (array $def) use ($module, $submodule): bool {
            if ($def['module'] !== $module->value) {
                return false;
            }

            $defSubmodule = $def['submodule'] ?? null;

            return $submodule === null
                ? $defSubmodule === null
                : $defSubmodule === $submodule;
        });
    }

    /**
     * Returns the Spatie permission names for a module (and optional sub-module) + access level.
     *
     * @return array<int, string>
     */
    public function permissionsFor(ModulesEnum $module, AccessLevel $level, ?string $submodule = null): array
    {
        $actions = $this->baseActionsForLevel($level);

        // Extra actions (export, approve, etc.) only apply at module level
        if ($level === AccessLevel::MANAGER && $submodule === null) {
            $actions = array_merge($actions, $this->extraActionsForModule($module));
        }

        return $this->defsFor($module, $submodule)
            ->filter(fn (array $def) => in_array($def['action'], $actions, true))
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * Resolves the highest AccessLevel a user holds for a module (or sub-module).
     *
     * Returns null when the user has no permissions for that scope.
     */
    public function resolveLevel(
        ModulesEnum $module,
        array $userPermissionNames,
        ?string $submodule = null
    ): ?AccessLevel {
        $defs            = $this->defsFor($module, $submodule);
        $managerDistinct = $submodule === null
            ? array_merge(['delete', 'restore'], $this->extraActionsForModule($module))
            : ['delete', 'restore'];

        $hasManager = $defs
            ->filter(fn (array $def) => in_array($def['action'], $managerDistinct, true))
            ->pluck('name')
            ->intersect($userPermissionNames)
            ->isNotEmpty();

        if ($hasManager) {
            return AccessLevel::MANAGER;
        }

        $hasEditor = $defs
            ->filter(fn (array $def) => in_array($def['action'], ['create', 'update'], true))
            ->pluck('name')
            ->intersect($userPermissionNames)
            ->isNotEmpty();

        if ($hasEditor) {
            return AccessLevel::EDITOR;
        }

        $hasViewer = $defs
            ->filter(fn (array $def) => in_array($def['action'], ['view_any', 'view'], true))
            ->pluck('name')
            ->intersect($userPermissionNames)
            ->isNotEmpty();

        if ($hasViewer) {
            return AccessLevel::VIEWER;
        }

        return null;
    }

    /**
     * Resolves the effective access structure for all modules given a flat permission list.
     *
     * Modules with sub-modules return a nested array:
     *   'terrenos' => ['predio' => 'viewer', 'casa' => 'manager']
     *
     * Modules without sub-modules return the level directly:
     *   'viabilidades' => 'manager'
     *
     * Modules/sub-modules with no access are omitted.
     *
     * @param  array<int, string>  $permissionNames
     * @return array<string, string|array<string, string>>
     */
    public function resolveModuleAccess(array $permissionNames): array
    {
        $result = [];

        foreach (ModulesEnum::cases() as $module) {
            if ($module->hasSubModules()) {
                $subResult = [];

                foreach ($module->subModules() as $submodule) {
                    $level = $this->resolveLevel($module, $permissionNames, $submodule);

                    if ($level !== null) {
                        $subResult[$submodule] = $level->value;
                    }
                }

                if (!empty($subResult)) {
                    $result[$module->value] = $subResult;
                }
            } else {
                $level = $this->resolveLevel($module, $permissionNames);

                if ($level !== null) {
                    $result[$module->value] = $level->value;
                }
            }
        }

        return $result;
    }

    /**
     * Converts a module access map into a flat list of Spatie permission names.
     *
     * Accepts both flat and nested map formats:
     *   Flat:   ['viabilidades' => AccessLevel::MANAGER]
     *   Nested: ['terrenos' => ['predio' => AccessLevel::VIEWER, 'casa' => AccessLevel::MANAGER]]
     *
     * @param  array<string, AccessLevel|array<string, AccessLevel>>  $map
     * @return array<int, string>
     */
    public function flatPermissionsFromMap(array $map): array
    {
        $permissions = [];

        foreach ($map as $moduleValue => $levelOrSubMap) {
            $module = ModulesEnum::from($moduleValue);

            if (is_array($levelOrSubMap)) {
                foreach ($levelOrSubMap as $submodule => $level) {
                    $permissions = array_merge($permissions, $this->permissionsFor($module, $level, $submodule));
                }
            } else {
                $permissions = array_merge($permissions, $this->permissionsFor($module, $levelOrSubMap));
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Returns a full access map with every module (and sub-module) set to MANAGER.
     * Used for SIGAPP admin users who have unrestricted access.
     *
     * @return array<string, string|array<string, string>>
     */
    public function allModulesAsManager(): array
    {
        $result = [];

        foreach (ModulesEnum::cases() as $module) {
            if ($module->hasSubModules()) {
                $result[$module->value] = array_fill_keys(
                    $module->subModules(),
                    AccessLevel::MANAGER->value
                );
            } else {
                $result[$module->value] = AccessLevel::MANAGER->value;
            }
        }

        return $result;
    }
}
