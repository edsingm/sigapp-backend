<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\WipeTenantJob;
use App\Services\Privacy\TenantLifecycleService;
use Illuminate\Console\Command;

class PurgeCancelledTenantsCommand extends Command
{
    protected $signature = 'privacy:purge-cancelled-tenants {--force : Executa o wipe mesmo com PRIVACY_AUTO_WIPE_ENABLED=false}';

    protected $description = 'Envia avisos D60/D83 e remove schema/S3 de tenants cujo wipe_scheduled_at já venceu';

    public function handle(TenantLifecycleService $lifecycle): int
    {
        $notified = $lifecycle->sendDueWipeNotices();
        $this->info('Avisos de wipe enviados: '.$notified);

        if (! (bool) config('privacy.auto_wipe_enabled', false) && ! $this->option('force')) {
            $this->warn('PRIVACY_AUTO_WIPE_ENABLED=false — nenhum wipe automático.');

            return self::SUCCESS;
        }

        $wiped = 0;
        $lifecycle->eachDueForWipe(function ($tenant) use (&$wiped): void {
            WipeTenantJob::dispatch((string) $tenant->getKey(), force: true);
            $wiped++;
        });

        $this->info('Tenants removidos: '.$wiped);

        return self::SUCCESS;
    }
}
