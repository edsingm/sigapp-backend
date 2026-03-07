<?php

namespace App\Jobs;

use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupPendingTenantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('CleanupPendingTenantsJob iniciado');

        $expiredTenants = Tenant::expiredPending()->get();

        $count = 0;

        foreach ($expiredTenants as $tenant) {
            try {
                Log::info('Removendo tenant pending expirado', [
                    'tenant_id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'created_at' => $tenant->created_at,
                ]);

                // Delete domains first
                $tenant->domains()->delete();

                // Delete tenant
                $tenant->delete();

                $count++;
            } catch (\Exception $e) {
                Log::error('Erro ao remover tenant pending', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('CleanupPendingTenantsJob concluído', ['removed_count' => $count]);
    }
}
