<?php

namespace App\Console\Commands;

use App\Jobs\CleanupPendingTenantsJob;
use Illuminate\Console\Command;

class CleanupPendingTenants extends Command
{
    /**
     * O nome e a assinatura do comando de console.
     */
    protected $signature = 'tenants:cleanup-pending';

    /**
     * A descrição do comando de console.
     */
    protected $description = 'Remove tenants com status pending há mais de 24 horas após limpeza segura no Stripe';

    /**
     * Executa o comando de console.
     */
    public function handle(): int
    {
        $this->info('Iniciando limpeza de tenants pending...');

        CleanupPendingTenantsJob::dispatchSync();

        $this->info('Limpeza concluída!');

        return Command::SUCCESS;
    }
}
