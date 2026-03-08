<?php

namespace App\Console\Commands;

use App\Enums\Common\ModulesEnum;
use App\Enums\Common\RolesEnum;
use App\Models\Central\Tenant;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * Sincroniza as permissões das roles com base nos templates RBAC.
 *
 * Funciona exatamente como o RolePermissionSeeder: lê cada template em
 * database/rbacTemplates/{role}.json e aplica syncPermissions() na role correspondente.
 *
 * Usage:
 *   php artisan rbac:apply-templates --tenant=<id|slug>
 *   php artisan rbac:apply-templates --all
 */
class ApplyRbacTemplatesCommand extends Command
{
    protected $signature = 'rbac:apply-templates
        {--tenant= : ID ou slug do tenant}
        {--all : Aplica em todos os tenants}';

    protected $description = 'Sincroniza as permissões das roles com base nos templates RBAC (database/rbacTemplates)';

    /**
     * Hierarquia cumulativa: igual ao RolePermissionSeeder.
     */
    private const LEVEL_MAP = [
        'viewer'  => ['viewer'],
        'editor'  => ['viewer', 'editor'],
        'manager' => ['viewer', 'editor', 'manager'],
    ];

    public function handle(): int
    {
        $applyAll         = (bool) $this->option('all');
        $tenantIdentifier = $this->option('tenant');

        if (!$applyAll && !$tenantIdentifier) {
            $this->error('Informe --tenant=<id|slug> ou use --all.');
            return self::FAILURE;
        }

        $query = Tenant::query();

        if ($tenantIdentifier) {
            $query->where('id', (string) $tenantIdentifier)
                ->orWhere('slug', (string) $tenantIdentifier);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');
            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->line("\n→ Tenant: {$tenant->id} ({$tenant->slug})");

            $tenant->run(function () {
                foreach (RolesEnum::cases() as $roleEnum) {
                    $templatePath = database_path('rbacTemplates/' . strtolower($roleEnum->value) . '.json');

                    if (!file_exists($templatePath)) {
                        $this->warn("  [SKIP] Template não encontrado para role {$roleEnum->value}");
                        continue;
                    }

                    $template = json_decode(file_get_contents($templatePath), true);
                    $role     = Role::where('name', $roleEnum->value)->where('guard_name', 'web')->first();

                    if (!$role) {
                        $this->warn("  [SKIP] Role {$roleEnum->value} não encontrada no tenant.");
                        continue;
                    }

                    $permissions = $this->resolvePermissions($template['permissions']);
                    $role->syncPermissions($permissions);

                    $this->line("  [OK]   {$roleEnum->value}: " . count($permissions) . ' permissões atribuídas.');
                }
            });
        }

        $this->newLine();
        $this->info('Concluído.');

        return self::SUCCESS;
    }

    /**
     * Converte o mapa do template em nomes de permissão cumulativos.
     * Lógica idêntica ao RolePermissionSeeder::resolvePermissions().
     */
    private function resolvePermissions(array $modulePermissions): array
    {
        $permissions = [];

        foreach ($modulePermissions as $moduleKey => $value) {
            $module = ModulesEnum::tryFrom($moduleKey);

            if (!$module) {
                $this->warn("  [WARN] Módulo '{$moduleKey}' não existe no ModulesEnum, ignorado.");
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
