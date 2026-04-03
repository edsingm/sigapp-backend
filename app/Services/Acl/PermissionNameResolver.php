<?php

namespace App\Services\Acl;

use App\Enums\Common\ModulesEnum;
use App\Models\Tenant\User;

class PermissionNameResolver
{
    /**
     * Mapa de métodos HTTP para níveis de permissão.
     */
    private const METHOD_LEVEL_MAP = [
        'GET' => 'viewer',
        'POST' => 'editor',
        'PUT' => 'editor',
        'PATCH' => 'editor',
        'DELETE' => 'manager',
    ];

    /**
     * Hierarquia de níveis de permissão (cumulativa).
     */
    private const LEVEL_HIERARCHY = [
        'viewer' => ['viewer'],
        'editor' => ['viewer', 'editor'],
        'manager' => ['viewer', 'editor', 'manager'],
    ];

    /**
     * Resolve o nome da permissão baseada na requisição HTTP.
     */
    public function forRequest(string $module, ?string $resource, string $method): string
    {
        $level = self::METHOD_LEVEL_MAP[strtoupper($method)] ?? 'viewer';

        return $resource !== null
            ? "{$module}.{$resource}.{$level}"
            : "{$module}.{$level}";
    }

    /**
     * Resolve o nome da permissão para um modelo e habilidade específica.
     */
    public function forModel(string|object $modelOrClass, string $ability): ?string
    {
        $class = is_object($modelOrClass) ? get_class($modelOrClass) : $modelOrClass;
        $module = ModulesEnum::modelMap()[$class] ?? null;

        if (!$module) {
            return null;
        }

        return "{$module}.{$this->abilityLevel($ability)}";
    }

    /**
     * Expande um mapa de permissões de módulos em uma lista plana de permissões.
     *
     * @param  array<string, string|array<string, string>|null>  $modulePermissions
     * @return array<int, string>
     */
    public function expandModulePermissions(array $modulePermissions): array
    {
        $permissions = [];

        foreach ($modulePermissions as $moduleKey => $value) {
            $module = ModulesEnum::tryFrom($moduleKey);

            if (!$module || $value === null) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $resource => $level) {
                    if (!isset(self::LEVEL_HIERARCHY[$level])) {
                        continue;
                    }

                    foreach (self::LEVEL_HIERARCHY[$level] as $permissionLevel) {
                        $permissions[] = "{$moduleKey}.{$resource}.{$permissionLevel}";
                    }
                }

                continue;
            }

            if (!isset(self::LEVEL_HIERARCHY[$value])) {
                continue;
            }

            foreach (self::LEVEL_HIERARCHY[$value] as $permissionLevel) {
                $permissions[] = "{$moduleKey}.{$permissionLevel}";
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Verifica se o usuário possui a permissão informada.
     */
    public function userCan(User $user, string $permission): bool
    {
        return $user->isAdmin() || $user->can($permission);
    }

    /**
     * Determina o nível de permissão baseado na habilidade (ação).
     */
    private function abilityLevel(string $ability): string
    {
        return match (true) {
            in_array($ability, ['viewAny', 'view', 'compare'], true) => 'viewer',
            in_array($ability, ['create', 'update', 'ativar', 'requestApproval', 'duplicate', 'gerarDre', 'recalcular', 'reorder', 'syncGantt', 'recalcularProgresso'], true) => 'editor',
            default => 'manager',
        };
    }
}
