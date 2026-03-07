<?php

namespace App\Console\Commands;

use App\Services\Auth\CentralLoginBrokerService;
use Illuminate\Console\Command;

class CleanupCentralLoginBroker extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'auth:cleanup-central-login-broker';

    /**
     * The console command description.
     */
    protected $description = 'Remove tickets e sessões expiradas/usadas do broker de login central';

    /**
     * Execute the console command.
     */
    public function handle(CentralLoginBrokerService $service): int
    {
        $result = $service->cleanup();

        $this->info('Cleanup do broker de login concluído.');
        $this->line('Sessões removidas: ' . $result['broker_sessions']);
        $this->line('Tickets removidos: ' . $result['transfer_tickets']);

        return Command::SUCCESS;
    }
}
