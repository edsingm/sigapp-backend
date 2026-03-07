<?php

namespace App\Console\Commands;

use App\Jobs\CleanupPendingTenantsJob;
use Illuminate\Console\Command;

class CleanupPendingTenants extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenants:cleanup-pending';

    /**
     * The console command description.
     */
    protected $description = 'Remove tenants com status pending há mais de 2 horas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando limpeza de tenants pending...');

        CleanupPendingTenantsJob::dispatchSync();

        $this->info('Limpeza concluída!');

        return Command::SUCCESS;
    }
}
