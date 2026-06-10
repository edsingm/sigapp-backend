<?php

namespace App\Console\Commands;

use App\Models\ConsentLog;
use Illuminate\Console\Command;

class CleanupConsentLogsCommand extends Command
{
    protected $signature = 'privacy:cleanup-consent-logs';

    protected $description = 'Remove registros de consentimento expirados conforme a retenção configurada';

    public function handle(): int
    {
        $retentionDays = max(1, (int) config('privacy.consent_log_retention_days', 180));
        $cutoff = now()->subDays($retentionDays);

        $deletedCount = ConsentLog::query()
            ->where('consented_at', '<', $cutoff)
            ->delete();

        $this->info('Cleanup de consent_logs concluído.');
        $this->line('Retenção (dias): '.$retentionDays);
        $this->line('Registros removidos: '.$deletedCount);

        return Command::SUCCESS;
    }
}
