<?php

namespace App\Jobs;

use App\Services\TenantStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

#[Tries(3)]
#[Timeout(120)]
class RefreshTenantStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Executa o job.
     */
    public function handle(TenantStatusService $service): void
    {
        Log::info('RefreshTenantStatsJob iniciado');

        $stats = $service->refreshStats();

        Log::info('RefreshTenantStatsJob concluído', $stats);
    }

    /**
     * Trata falha definitiva do job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('RefreshTenantStatsJob falhou definitivamente', [
            'error' => $exception->getMessage(),
        ]);
    }
}
