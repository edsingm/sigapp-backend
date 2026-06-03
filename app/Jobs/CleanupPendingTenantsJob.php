<?php

namespace App\Jobs;

use App\Models\Central\Tenant;
use App\Notifications\AbandonedCheckoutNotification;
use App\Services\Billing\TenantBillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CleanupPendingTenantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

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

                        if ($sessionStatus === 'complete' || ! empty($session->subscription)) {
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

                // Captura dados necessários ANTES da deleção
                $adminEmail = $tenant->admin_email;
                $tenantName = $tenant->name;
                $planSlug = $tenant->plan?->slug ?? '';

                // Deleta os domínios primeiro, depois o tenant
                $tenant->domains()->delete();
                $tenant->delete();

                $count++;

                // Envia email de reengajamento após a deleção.
                // Usa Notification::route() porque o model já foi deletado.
                if ($adminEmail) {
                    try {
                        $signupUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/')
                            .'/cadastro'
                            .($planSlug ? '?plan='.$planSlug : '');

                        Notification::route('mail', $adminEmail)
                            ->notify(new AbandonedCheckoutNotification($tenantName, $planSlug, $signupUrl));
                    } catch (\Exception $e) {
                        Log::warning('Falha ao enviar email de reengajamento para tenant removido.', [
                            'admin_email' => $adminEmail,
                            'tenant_name' => $tenantName,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

            } catch (\Exception $e) {
                Log::error('Erro ao remover tenant pending', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('CleanupPendingTenantsJob concluído', ['removed_count' => $count]);
    }

    /**
     * Trata falha definitiva do job após esgotar tentativas.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('CleanupPendingTenantsJob falhou definitivamente', [
            'error' => $exception->getMessage(),
            'exception_class' => $exception::class,
        ]);
    }
}
