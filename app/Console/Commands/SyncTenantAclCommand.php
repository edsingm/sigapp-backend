<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\TenantAclSyncService;
use Illuminate\Console\Command;

class SyncTenantAclCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenants:acl-sync
        {--tenant= : ID ou slug do tenant}
        {--all : Sincroniza todos os tenants}';

    /**
     * The console command description.
     */
    protected $description = 'Sincroniza permissões e roles padrão dos tenants com a matriz de plano';

    public function handle(TenantAclSyncService $syncService): int
    {
        $syncAll = (bool) $this->option('all');
        $tenantIdentifier = $this->option('tenant');

        if (!$syncAll && !$tenantIdentifier) {
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
            $this->warn('Nenhum tenant encontrado para sincronização.');
            return self::SUCCESS;
        }

        $this->info("Iniciando sync de ACL para {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            $this->line("-> {$tenant->id} ({$tenant->slug})");

            try {
                $result = $tenant->run(fn () => $syncService->syncForCurrentTenant());

                $this->line(sprintf(
                    '   roles_synced=%d templates=%d permissions_created=%d',
                    (int) ($result['roles_synced'] ?? 0),
                    (int) ($result['templates_applied'] ?? 0),
                    (int) ($result['permissions_created'] ?? 0)
                ));
            } catch (\Throwable $e) {
                $this->error("   Falha: {$e->getMessage()}");
            }
        }

        $this->info('Sync de ACL concluído.');

        return self::SUCCESS;
    }
}
