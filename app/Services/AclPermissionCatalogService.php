<?php

namespace App\Services;

class AclPermissionCatalogService
{
    /**
     * @return array<int, array{name: string, module: string, action: string}>
     */
    public function systemPermissionDefinitions(): array
    {
        return array_values((array) config('acl_permissions.system_permissions', []));
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
     * @return array<string, array<int, array{name: string, module: string, action: string}>>
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
