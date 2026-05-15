<?php

namespace App\Jobs;

use App\Services\TenantStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshTenantStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

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
