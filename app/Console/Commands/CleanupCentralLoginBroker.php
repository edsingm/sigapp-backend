<?php

namespace App\Console\Commands;

use App\Services\Auth\CentralLoginBrokerService;
use Illuminate\Console\Command;

class CleanupCentralLoginBroker extends Command
{
    /**
     * O nome e a assinatura do comando de console.
     */
    protected $signature = 'auth:cleanup-central-login-broker';

    /**
     * A descrição do comando de console.
     */
    protected $description = 'Remove tickets e sessões expiradas/usadas do broker de login central';

    /**
     * Executa o comando de console.
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
