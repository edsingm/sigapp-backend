<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TenantStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

#[Tries(3)]
#[Backoff(60)]
#[Timeout(120)]
class RefreshTenantStatsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return 'aggregated-tenant-stats';
    }

    /**
     * Executa o job.
     */
    public function handle(TenantStatusService $service): void
    {
        $lock = Cache::lock('job:refresh-tenant-stats', 180);
        if (! $lock->get()) {
            return;
        }

        Log::info('RefreshTenantStatsJob iniciado');

        try {
            $stats = $service->refreshStats();

            Log::info('RefreshTenantStatsJob concluído', $stats);
        } finally {
            $lock->release();
        }
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
