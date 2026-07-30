<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Enums\Common\ModulesEnum;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Services\PlanMatrixService;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

class ModuleAccessService
{
    /** @var array<string, list<string>> */
    private const FEATURE_MAP = [
        'prospection' => ['prospection'],
        'brokers' => ['prospection'],
        'data' => ['product_settings', 'regionals', 'territorial_base'],
        'dashboard' => ['dashboard.enabled'],
        'committee' => ['committee'],
        'legal' => ['legalizations'],
        'negotiation' => ['negotiation'],
        'projects' => ['projects.enabled'],
        'reports' => ['reports.builder'],
        'viability' => ['viabilities.enabled'],
        'ai' => ['ai'],
    ];

    public function __construct(
        private readonly PlanMatrixService $planMatrix,
        private readonly PermissionRepositoryInterface $permissions,
    ) {}

    /**
     * @param  array<string, mixed>  $groupedModules
     * @return array{features: array<string, mixed>, limits: array<string, int|float>, modules: array<string, array{plan_enabled: bool, rbac_allowed: bool, available: bool, reasons: list<string>}>}
     */
    public function resolve(Tenant $tenant, Authenticatable $user, array $groupedModules): array
    {
        if (! $user instanceof User) {
            throw new InvalidArgumentException('Usuário tenant inválido para resolver os módulos.');
        }

        $matrix = $this->planMatrix->resolveForTenant($tenant);
        $active = [];

        foreach ($groupedModules as $moduleCollection) {
            foreach ($moduleCollection as $module) {
                $active[(string) $module->slug] = (bool) $module->active;
            }
        }

        $modules = [];
        foreach (ModulesEnum::cases() as $module) {
            $moduleActive = $active[$module->value] ?? false;
            $planEnabled = $this->planAllows($matrix['features'], $module);
            $rbacAllowed = $this->permissions->userCanViewModule($user, $module->value);
            $reasons = [];

            if (! $moduleActive) {
                $reasons[] = 'module';
            }
            if (! $planEnabled) {
                $reasons[] = 'plan';
            }
            if (! $rbacAllowed) {
                $reasons[] = 'rbac';
            }

            $modules[$module->value] = [
                'plan_enabled' => $planEnabled,
                'rbac_allowed' => $rbacAllowed,
                'available' => $moduleActive && $planEnabled && $rbacAllowed,
                'reasons' => $reasons,
            ];
        }

        return [
            'features' => $matrix['features'],
            'limits' => $matrix['limits'],
            'modules' => $modules,
        ];
    }

    /** @param array<string, mixed> $features */
    private function planAllows(array $features, ModulesEnum $module): bool
    {
        if (in_array($module, [ModulesEnum::CONFIGURATIONS, ModulesEnum::ADMIN], true)) {
            return true;
        }

        foreach (self::FEATURE_MAP[$module->value] as $feature) {
            $value = data_get($features, $feature);
            if (is_array($value)) {
                $value = $value['enabled'] ?? false;
            }

            if ($value === true) {
                return true;
            }
        }

        return false;
    }
}
