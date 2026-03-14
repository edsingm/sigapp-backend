<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\CreateFullTenantJob;
use App\Models\Central\Tenant;
use App\Models\Central\WebhookEvent;
use App\Services\Billing\TenantBillingService;
use App\Traits\LogsAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends CashierController
{
    use LogsAudit;

    public function __construct(
        protected TenantBillingService $billingService
    ) {
        if ($this->requiresSignedWebhook() && $this->hasWebhookSecret()) {
            $this->middleware(VerifyWebhookSignature::class);
        }
    }

    /**
     * Handle incoming Stripe webhooks.
     */
    public function handleWebhook(Request $request)
    {
        if ($this->requiresSignedWebhook() && !$this->hasWebhookSecret()) {
            Log::critical('Stripe webhook recusado: STRIPE_WEBHOOK_SECRET ausente fora de local/testing.');

            return response()->json([
                'message' => 'Stripe webhook is temporarily unavailable.',
            ], 503);
        }

        $payload = json_decode($request->getContent(), true);
        $eventId = $payload['id'] ?? null;

        if (!is_string($eventId) || $eventId === '') {
            return parent::handleWebhook($request);
        }

        return Cache::lock('stripe-webhook:' . $eventId, 30)->block(5, function () use ($eventId, $payload, $request) {
            $event = WebhookEvent::query()->firstOrCreate(
                ['event_id' => $eventId],
                [
                    'type' => $payload['type'] ?? 'unknown',
                    'payload' => $payload,
                ]
            );

            if ($event->processed_at) {
                Log::info('Stripe webhook already processed', [
                    'event_id' => $eventId,
                    'type' => $event->type,
                ]);

                return $this->successMethod();
            }

            $event->forceFill([
                'type' => $payload['type'] ?? 'unknown',
                'payload' => $payload,
            ])->save();

            $response = parent::handleWebhook($request);

            if ($response instanceof Response
                && $response->isSuccessful()
                && $response->headers->get('X-Webhook-Processed', '1') !== '0') {
                $event->markAsProcessed();
            }

            return $response;
        });
    }

    /**
     * Handle checkout.session.completed event.
     */
    protected function handleCheckoutSessionCompleted(array $payload)
    {
        $session = (array) data_get($payload, 'data.object', []);
        $tenantId = data_get($session, 'metadata.tenant_id');

        if (!$tenantId) {
            $this->audit('tenant.checkout_validation_failed', 'Checkout concluído sem tenant_id válido.', [
                'session_id' => $session['id'] ?? null,
                'customer' => $session['customer'] ?? null,
                'reason' => 'missing_tenant_id',
            ]);

            return $this->unprocessedSuccessMethod();
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->audit('tenant.checkout_validation_failed', "Checkout concluído mas tenant ID '{$tenantId}' não encontrado no banco.", [
                'tenant_id' => $tenantId,
                'session_id' => $session['id'] ?? null,
                'customer' => $session['customer'] ?? null,
                'reason' => 'tenant_not_found',
            ]);

            return $this->unprocessedSuccessMethod();
        }

        if ($validationFailure = $this->validateCheckoutSessionCompleted($tenant, $session)) {
            $this->audit('tenant.checkout_validation_failed', 'Checkout concluído rejeitado pela validação de vínculo.', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'session_id' => $session['id'] ?? null,
                'customer_id' => $session['customer'] ?? null,
                'reason' => $validationFailure,
            ]);

            return $this->unprocessedSuccessMethod();
        }

        $signupContractAcceptance = $this->billingService->getSignupContractAcceptance($tenant);
        $signupContractAccepted = (bool) data_get($signupContractAcceptance, 'accepted', false);
        if (!$signupContractAccepted) {
            Log::warning('Checkout completed para tenant sem aceite de contrato registrado no signup', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'session_id' => $session['id'] ?? null,
                'customer_id' => $session['customer'] ?? null,
            ]);

            $this->audit('tenant.checkout_missing_contract_acceptance', 'Checkout concluído sem aceite de contrato de utilização registrado no signup.', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->name,
                'session_id' => $session['id'] ?? null,
                'subscription_id' => $session['subscription'] ?? null,
                'customer_id' => $session['customer'] ?? null,
                'reason' => 'missing_signup_contract_acceptance',
            ]);
        }

        $tenant->update([
            'stripe_subscription_id' => $session['subscription'] ?? null,
            'stripe_id' => $session['customer'] ?? null,
        ]);

        Log::info('Checkout completed, disparando CreateFullTenantJob', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $session['subscription'] ?? null,
        ]);

        $this->dispatchTenantProvisioning($tenant);

        // Audit: checkout completed
        $this->audit('tenant.checkout_completed', "Checkout Stripe concluído para tenant '{$tenant->name}'. Job de criação disparado.", [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->name,
            'session_id' => $session['id'] ?? null,
            'subscription_id' => $session['subscription'] ?? null,
            'customer_id' => $session['customer'] ?? null,
        ]);

        // Manual Sync: Ensure subscription is recorded (fix for race condition)
        if (!empty($session['subscription'])) {
            try {
                $this->billingService->syncSubscription($tenant, $session['subscription']);
            } catch (\Exception $e) {
                Log::error('Erro ao sincronizar assinatura manualmente', ['error' => $e->getMessage()]);
            }
        }

        return $this->successMethod();
    }

    /**
     * Queue tenant provisioning without blocking the Stripe webhook request.
     */
    protected function dispatchTenantProvisioning(Tenant $tenant): void
    {
        if ($tenant->database_created) {
            Log::info('CreateFullTenantJob ignorado: tenant já provisionado', [
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        $defaultConnection = (string) config('queue.default', 'sync');
        if (!app()->isLocal() && $defaultConnection === 'sync') {
            throw new \RuntimeException('QUEUE_CONNECTION assíncrona é obrigatória fora do ambiente local.');
        }

        $queueConnection = $defaultConnection === 'sync' ? 'background' : $defaultConnection;

        CreateFullTenantJob::dispatch($tenant)
            ->onConnection($queueConnection)
            ->onQueue('tenant-provisioning');
    }

    /**
     * Handle invoice.paid event.
     */
    protected function handleInvoicePaid(array $payload)
    {
        $invoice = (object) $payload['data']['object'];
        $customerId = $invoice->customer;

        $tenant = Tenant::where('stripe_id', $customerId)->first();

        if ($tenant && $tenant->status === Tenant::STATUS_SUSPENDED) {
            $this->billingService->applyStripeSubscriptionStatus($tenant, 'active');
            Log::info('Tenant reativado após pagamento', ['tenant_id' => $tenant->id]);
        }

        return $this->successMethod();
    }

    /**
     * Handle invoice.payment_failed event.
     */
    protected function handleInvoicePaymentFailed(array $payload)
    {
        $invoice = (object) $payload['data']['object'];
        $customerId = $invoice->customer;

        $tenant = Tenant::where('stripe_id', $customerId)->first();

        if ($tenant && ($invoice->attempt_count ?? 0) >= 3) {
            $tenant->suspend();
            Log::warning('Tenant suspenso por falta de pagamento', [
                'tenant_id' => $tenant->id,
                'attempts' => $invoice->attempt_count ?? 0,
            ]);

            $this->audit('tenant.payment_failed', "Pagamento falhou {$invoice->attempt_count}x para tenant '{$tenant->name}'. Tenant suspenso.", [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->name,
                'customer_id' => $customerId,
                'attempt_count' => $invoice->attempt_count ?? 0,
                'invoice_id' => $invoice->id ?? null,
                'reason' => 'payment_failed_suspended',
            ]);
        }

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.updated event.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        // Call Parent to let Cashier sync the subscriptions table
        if (method_exists(parent::class, 'handleCustomerSubscriptionUpdated')) {
            parent::handleCustomerSubscriptionUpdated($payload);
        }

        $subscription = (array) data_get($payload, 'data.object', []);
        $customerId = $subscription['customer'] ?? null;

        $tenant = Tenant::where('stripe_id', $customerId)->first();

        if ($tenant) {
            $tenant->update([
                'stripe_subscription_id' => $subscription['id'] ?? $tenant->stripe_subscription_id,
            ]);

            $this->billingService->syncPlanFromPriceId($tenant, data_get($subscription, 'items.data.0.price.id'));
            $this->billingService->applyStripeSubscriptionStatus($tenant, $subscription['status'] ?? null);
        }

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.deleted event.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        // Let Cashier sync local DB
        if (method_exists(parent::class, 'handleCustomerSubscriptionDeleted')) {
            parent::handleCustomerSubscriptionDeleted($payload);
        }

        $subscription = (array) data_get($payload, 'data.object', []);
        $customerId = $subscription['customer'] ?? null;

        $tenant = Tenant::where('stripe_id', $customerId)->first();

        if ($tenant) {
            $tenant->update([
                'stripe_subscription_id' => $subscription['id'] ?? $tenant->stripe_subscription_id,
            ]);

            $this->billingService->applyStripeSubscriptionStatus($tenant, 'canceled');
            Log::info('Tenant cancelou assinatura', ['tenant_id' => $tenant->id]);

            $this->audit('tenant.subscription_canceled', "Assinatura cancelada para tenant '{$tenant->name}'.", [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->name,
                'customer_id' => $customerId,
                'subscription_id' => $subscription['id'] ?? null,
            ]);
        }

        return $this->successMethod();
    }

    protected function requiresSignedWebhook(): bool
    {
        return !app()->environment(['local', 'testing']);
    }

    protected function hasWebhookSecret(): bool
    {
        return (string) config('cashier.webhook.secret', '') !== '';
    }

    protected function validateCheckoutSessionCompleted(Tenant $tenant, array $session): ?string
    {
        if (($session['mode'] ?? null) !== 'subscription') {
            return 'invalid_mode';
        }

        if (($session['status'] ?? null) !== 'complete') {
            return 'invalid_status';
        }

        if (!$this->billingService->matchesSignupCheckoutSession($tenant, $session['id'] ?? null)) {
            return 'session_mismatch';
        }

        if ($tenant->stripe_id && $tenant->stripe_id !== ($session['customer'] ?? null)) {
            return 'customer_mismatch';
        }

        return null;
    }

    protected function unprocessedSuccessMethod(): JsonResponse
    {
        return response()->json(['received' => true], 200, [
            'X-Webhook-Processed' => '0',
        ]);
    }
}
