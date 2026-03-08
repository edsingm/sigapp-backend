<?php

namespace App\Services;

use App\Enums\AccessLevel;
use App\Enums\Common\ModulesEnum;
use Illuminate\Support\Facades\Log;

/**
 * Reads RBAC permission templates from database/rbacTemplates/{role}.json
 * and applies them to a tenant user via Spatie permissions.
 *
 * Templates define the default module-permission map per role.
 * They are the single source of truth for "what a fresh user of role X can do".
 */
class RbacTemplateService
{
    private const TEMPLATES_PATH = 'database/rbacTemplates';

    public function __construct(
        protected ModuleAccessService $moduleAccess
    ) {
    }

    /**
     * Returns the module-permission map from the JSON template for the given role.
     * Returns null when no template file exists for that role.
     *
     * @return array<string, string|array<string, string>>|null
     */
    public function loadTemplate(string $role): ?array
    {
        $path = base_path(self::TEMPLATES_PATH . "/{$role}.json");

        if (!file_exists($path)) {
            return null;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (!is_array($data) || !isset($data['permissions'])) {
            Log::warning("RbacTemplateService: template inválido ou sem chave 'permissions'.", ['path' => $path]);
            return null;
        }

        return $data['permissions'];
    }

    /**
     * Returns all available templates indexed by role slug.
     *
     * @return array<string, array<string, string|array<string, string>>>
     */
    public function allTemplates(): array
    {
        $templates = [];
        $files     = glob(base_path(self::TEMPLATES_PATH . '/*.json')) ?: [];

        foreach ($files as $file) {
            $json = file_get_contents($file);
            $data = json_decode($json, true);

            if (!is_array($data) || !isset($data['role'], $data['permissions'])) {
                continue;
            }

            $templates[(string) $data['role']] = $data['permissions'];
        }

        return $templates;
    }

    /**
     * Converts a module-permission map (from a template) to flat Spatie permission names.
     *
     * @param  array<string, string|array<string, string>> $permissionsMap
     * @return array<int, string>
     */
    public function flatPermissionsFromTemplate(array $permissionsMap): array
    {
        // Convert string values to AccessLevel enums before delegating
        $enumMap = $this->normalizeToEnumMap($permissionsMap);

        return $this->moduleAccess->flatPermissionsFromMap($enumMap);
    }

    /**
     * Applies the template for the given role to a user model.
     * Only applies if the user currently has no direct permissions (not yet customized).
     *
     * @param  \App\Models\Tenant\User $user
     */
    public function applyTemplateToUser(mixed $user, string $role, bool $force = false): bool
    {
        // If not forced, skip users who already have direct permissions
        if (!$force && $user->getDirectPermissions()->isNotEmpty()) {
            return false;
        }

        $template = $this->loadTemplate($role);

        if ($template === null) {
            return false;
        }

        $flatPermissions = $this->flatPermissionsFromTemplate($template);

        if (empty($flatPermissions)) {
            return false;
        }

        $user->syncPermissions($flatPermissions);

        return true;
    }

    /**
     * Normalizes the raw JSON map (string levels) to an AccessLevel enum map
     * suitable for ModuleAccessService::flatPermissionsFromMap().
     *
     * @param  array<string, string|array<string, string>> $map
     * @return array<string, AccessLevel|array<string, AccessLevel>>
     */
    private function normalizeToEnumMap(array $map): array
    {
        $normalized = [];

        foreach ($map as $module => $value) {
            if (is_array($value)) {
                $subMap = [];
                foreach ($value as $submodule => $level) {
                    if ($level !== null) {
                        $subMap[$submodule] = AccessLevel::from($level);
                    }
                }
                if (!empty($subMap)) {
                    $normalized[$module] = $subMap;
                }
            } elseif ($value !== null) {
                $normalized[$module] = AccessLevel::from($value);
            }
        }

        return $normalized;
    }
}
