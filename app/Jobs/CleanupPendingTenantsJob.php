<?php

namespace App\Jobs;

use App\Models\Central\Tenant;
use App\Services\Billing\TenantBillingService;
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
     * Executa o job.
     */
    public function handle(TenantBillingService $billingService): void
    {
        Log::info('CleanupPendingTenantsJob iniciado');

        $expiredTenants = Tenant::expiredPending()->get();

        $count = 0;

        foreach ($expiredTenants as $tenant) {
            try {
                $checkoutSessionId = $billingService->getSignupCheckoutSessionId($tenant);

                if ($tenant->stripe_subscription_id) {
                    Log::warning('Cleanup ignorado: tenant pending possui assinatura Stripe associada.', [
                        'tenant_id' => $tenant->id,
                        'stripe_subscription_id' => $tenant->stripe_subscription_id,
                    ]);

                    continue;
                }

                if ($checkoutSessionId) {
                    try {
                        $session = $billingService->retrieveCheckoutSession($checkoutSessionId);
                        $sessionStatus = (string) ($session->status ?? '');

                        if ($sessionStatus === 'open') {
                            $billingService->expireCheckoutSession($checkoutSessionId);
                        }

                        if ($sessionStatus === 'complete' || !empty($session->subscription)) {
                            Log::warning('Cleanup ignorado: checkout já concluído ou com assinatura associada.', [
                                'tenant_id' => $tenant->id,
                                'session_id' => $checkoutSessionId,
                                'session_status' => $sessionStatus,
                                'subscription_id' => $session->subscription ?? null,
                            ]);

                            continue;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Cleanup não conseguiu consultar/expirar checkout Stripe.', [
                            'tenant_id' => $tenant->id,
                            'session_id' => $checkoutSessionId,
                            'error' => $e->getMessage(),
                        ]);

                        continue;
                    }
                }

                if ($tenant->stripe_id) {
                    try {
                        $billingService->deleteCustomer($tenant->stripe_id);
                    } catch (\Exception $e) {
                        Log::warning('Cleanup abortado: falha ao remover customer Stripe.', [
                            'tenant_id' => $tenant->id,
                            'customer_id' => $tenant->stripe_id,
                            'error' => $e->getMessage(),
                        ]);

                        continue;
                    }
                }

                Log::info('Removendo tenant pending expirado', [
                    'tenant_id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'created_at' => $tenant->created_at,
                ]);

                // Deleta os domínios primeiro
                $tenant->domains()->delete();

                // Deleta o tenant
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
