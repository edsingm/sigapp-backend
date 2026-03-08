<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Services\RbacTemplateService;
use Illuminate\Console\Command;

/**
 * Applies RBAC permission templates to tenant users.
 *
 * Usage:
 *   php artisan rbac:apply-templates --tenant=<id|slug>
 *   php artisan rbac:apply-templates --all
 *   php artisan rbac:apply-templates --all --force   ← overwrites users with custom permissions
 */
class ApplyRbacTemplatesCommand extends Command
{
    protected $signature = 'rbac:apply-templates
        {--tenant= : ID ou slug do tenant}
        {--all : Aplica em todos os tenants}
        {--force : Sobrescreve usuários que já possuem permissões diretas customizadas}';

    protected $description = 'Aplica os templates de permissão RBAC (database/rbacTemplates) aos usuários dos tenants';

    public function handle(RbacTemplateService $rbacTemplates): int
    {
        $applyAll         = (bool) $this->option('all');
        $tenantIdentifier = $this->option('tenant');
        $force            = (bool) $this->option('force');

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

        $availableTemplates = $rbacTemplates->allTemplates();

        if (empty($availableTemplates)) {
            $this->error('Nenhum template encontrado em database/rbacTemplates/.');
            return self::FAILURE;
        }

        $this->info('Templates disponíveis: ' . implode(', ', array_keys($availableTemplates)));
        if ($force) {
            $this->warn('Modo --force ativado: permissões customizadas serão sobrescritas.');
        }

        $totalApplied = 0;
        $totalSkipped = 0;

        foreach ($tenants as $tenant) {
            $this->line("\n→ Tenant: {$tenant->id} ({$tenant->slug})");

            $tenant->run(function () use ($rbacTemplates, $force, &$totalApplied, &$totalSkipped) {
                $users = User::with('roles')->get();

                foreach ($users as $user) {
                    $role = $user->roles->first()?->name;

                    if (!$role) {
                        $this->line("  [SKIP] {$user->email} — sem role atribuído");
                        $totalSkipped++;
                        continue;
                    }

                    $applied = $rbacTemplates->applyTemplateToUser($user, $role, $force);

                    if ($applied) {
                        $this->line("  [OK]   {$user->email} ({$role})");
                        $totalApplied++;
                    } else {
                        $this->line("  [SKIP] {$user->email} ({$role}) — sem template ou permissões customizadas");
                        $totalSkipped++;
                    }
                }
            });
        }

        $this->newLine();
        $this->info("Concluído. Aplicados: {$totalApplied} | Ignorados: {$totalSkipped}");

        return self::SUCCESS;
    }
}
