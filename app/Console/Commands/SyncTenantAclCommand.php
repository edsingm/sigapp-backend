<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\TenantAclSyncService;
use Illuminate\Console\Command;

class SyncTenantAclCommand extends Command
{
    /**
     * O nome e a assinatura do comando.
     */
    protected $signature = 'tenants:acl-sync
        {--tenant= : ID ou slug do tenant}
        {--all : Sincroniza todos os tenants}';

    /**
     * A descrição do comando.
     */
    protected $description = 'Sincroniza permissões e roles padrão dos tenants com a matriz de plano';

    public function handle(TenantAclSyncService $syncService): int
    {
        $syncAll = (bool) $this->option('all');
        $tenantIdentifier = $this->option('tenant');

        if (! $syncAll && ! $tenantIdentifier) {
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
            $tenantSlug = (string) $tenant->getAttribute('slug');

            $this->line("-> {$tenant->id} ({$tenantSlug})");

            try {
                $result = $tenant->run(fn () => $syncService->syncForCurrentTenant());

                $this->line(sprintf(
                    '   roles_synced=%d permissions_synced=%d',
                    (int) ($result['roles_synced'] ?? 0),
                    (int) ($result['permissions_synced'] ?? 0)
                ));
            } catch (\Throwable $e) {
                $this->error("   Falha: {$e->getMessage()}");
            }
        }

        $this->info('Sync de ACL concluído.');

        return self::SUCCESS;
    }
}
