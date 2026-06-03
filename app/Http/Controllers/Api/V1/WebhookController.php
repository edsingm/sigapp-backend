<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantStatus;
use App\Jobs\CreateFullTenantJob;
use App\Models\Central\Tenant;
use App\Notifications\PaymentRetryNotification;
use App\Notifications\TrialEndingNotification;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Billing\CouponService;
use App\Services\Billing\TenantBillingService;
use App\Services\Billing\WebhookEventService;
use App\Traits\LogsAudit;
use Carbon\Carbon;
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
        protected TenantBillingService $billingService,
        protected TenantRepositoryInterface $tenantRepository,
        protected CouponService $couponService,
        protected WebhookEventService $webhookEventService,
    ) {
        if ($this->requiresSignedWebhook() && $this->hasWebhookSecret()) {
            $this->middleware(VerifyWebhookSignature::class);
        }
    }

    /**
     * Manipula webhooks recebidos do Stripe.
     */
    public function handleWebhook(Request $request)
    {
        if ($this->requiresSignedWebhook() && ! $this->hasWebhookSecret()) {
            Log::critical('Stripe webhook recusado: STRIPE_WEBHOOK_SECRET ausente fora de local/testing.');

            return response()->json([
                'message' => 'Stripe webhook is temporarily unavailable.',
            ], 503);
        }

        $payload = json_decode($request->getContent(), true);
        $eventId = $payload['id'] ?? null;

        if (! is_string($eventId) || $eventId === '') {
            return parent::handleWebhook($request);
        }

        return Cache::lock('stripe-webhook:'.$eventId, 30)->block(5, function () use ($eventId, $payload, $request) {
            $event = $this->webhookEventService->findOrCreate(
                $eventId,
                $payload['type'] ?? 'unknown',
                $payload,
            );

            if ($event->processed_at) {
                Log::info('Webhook do Stripe já processado', [
                    'event_id' => $eventId,
                    'type' => $event->type,
                ]);

                return $this->successMethod();
            }

            $this->webhookEventService->update(
                $event,
                $payload['type'] ?? 'unknown',
                $payload,
            );

            $response = parent::handleWebhook($request);

            if ($response instanceof Response
                && $response->isSuccessful()
                && $response->headers->get('X-Webhook-Processed', '1') !== '0') {
                $this->webhookEventService->markAsProcessed($event);
            }

            return $response;
        });
    }

    /**
     * Manipula o evento checkout.session.completed.
     */
    protected function handleCheckoutSessionCompleted(array $payload)
    {
        $session = (array) data_get($payload, 'data.object', []);
        $tenantId = data_get($session, 'metadata.tenant_id');

        if (! $tenantId) {
            $this->audit('tenant.checkout_validation_failed', 'Checkout concluído sem tenant_id válido.', [
                'session_id' => $session['id'] ?? null,
                'customer' => $session['customer'] ?? null,
                'reason' => 'missing_tenant_id',
            ]);

            return $this->unprocessedSuccessMethod();
        }

        $tenant = $this->tenantRepository->findById($tenantId);

        if (! $tenant) {
            $this->audit('tenant.checkout_validation_failed', "Checkout concluído mas tenant ID '{$tenantId}' não encontrado no banco.", [
                'tenant_id' => $tenantId,
                'session_id' => $session['id'] ?? null,
                'customer' => $session['customer'] ?? null,
                'reason' => 'tenant_not_found',
            ]);

            return $this->unprocessedSuccessMethod();
        }

        if ($validationFailure = $this->validateCheckoutSessionCompleted($tenant, $session)) {
            Log::warning('Checkout concluído rejeitado pela validação de vínculo', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $this->tenantSlug($tenant),
                'session_id' => $session['id'] ?? null,
                'stored_session_id' => $this->billingService->getSignupCheckoutSessionId($tenant),
                'client_reference_id' => $session['client_reference_id'] ?? null,
                'customer_id' => $session['customer'] ?? null,
                'stored_customer_id' => $this->tenantStripeId($tenant),
                'reason' => $validationFailure,
            ]);

            $this->audit('tenant.checkout_validation_failed', 'Checkout concluído rejeitado pela validação de vínculo.', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $this->tenantSlug($tenant),
                'session_id' => $session['id'] ?? null,
                'customer_id' => $session['customer'] ?? null,
                'reason' => $validationFailure,
            ]);

            return $this->unprocessedSuccessMethod();
        }

        $this->hydrateMissingSignupCheckoutReference($tenant, $session);

        $signupContractAcceptance = $this->billingService->getSignupContractAcceptance($tenant);
        $signupContractAccepted = (bool) data_get($signupContractAcceptance, 'accepted', false);
        if (! $signupContractAccepted) {
            Log::warning('Checkout completed para tenant sem aceite de contrato registrado no signup', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $this->tenantSlug($tenant),
                'session_id' => $session['id'] ?? null,
                'customer_id' => $session['customer'] ?? null,
            ]);

            $this->audit('tenant.checkout_missing_contract_acceptance', 'Checkout concluído sem aceite de contrato de utilização registrado no signup.', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $this->tenantSlug($tenant),
                'tenant_name' => $this->tenantName($tenant),
                'session_id' => $session['id'] ?? null,
                'subscription_id' => $session['subscription'] ?? null,
                'customer_id' => $session['customer'] ?? null,
                'reason' => 'missing_signup_contract_acceptance',
            ]);
        }

        // Guard para métodos de pagamento assíncronos (Boleto, etc.):
        // Boleto gera checkout.session.completed mas com payment_status='unpaid'.
        // O provisionamento só deve ocorrer após a confirmação do pagamento via invoice.paid.
        $paymentStatus = data_get($session, 'payment_status');
        if ($paymentStatus !== 'paid' && $paymentStatus !== 'no_payment_required') {
            Log::info('Checkout concluído mas pagamento pendente (método assíncrono)', [
                'tenant_id' => $tenant->id,
                'payment_status' => $paymentStatus,
                'session_id' => $session['id'] ?? null,
            ]);

            // Armazena os IDs para que o invoice.paid possa provisionar corretamente
            $tenant->update([
                'stripe_subscription_id' => $session['subscription'] ?? null,
                'stripe_id' => $session['customer'] ?? null,
            ]);

            return $this->successMethod();
        }

        Log::info('Checkout completed, disparando CreateFullTenantJob', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $session['subscription'] ?? null,
        ]);

        // Sincroniza o estado de billing (stripe_id, stripe_subscription_id, plano, subscription table)
        // ANTES de disparar o provisionamento para evitar race condition.
        if (! empty($session['subscription'])) {
            try {
                $this->reconcileTenantBillingState(
                    $tenant,
                    $session['subscription'],
                    'checkout.session.completed',
                    [
                        'session_id' => $session['id'] ?? null,
                        'customer_id' => $session['customer'] ?? null,
                    ]
                );
            } catch (\Exception $e) {
                Log::error('Erro ao sincronizar assinatura manualmente', ['error' => $e->getMessage()]);
            }
        }

        // Provisionamento é disparado DENTRO de reconcileTenantBillingState quando necessário.
        // Garantia extra: se não houve subscription, dispara diretamente.
        if (empty($session['subscription'])) {
            $this->dispatchTenantProvisioning($tenant);
        }

        // Audit: checkout completed
        $this->audit('tenant.checkout_completed', "Checkout Stripe concluído para tenant '{$this->tenantName($tenant)}'. Job de criação disparado.", [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $this->tenantSlug($tenant),
            'tenant_name' => $this->tenantName($tenant),
            'session_id' => $session['id'] ?? null,
            'subscription_id' => $session['subscription'] ?? null,
            'customer_id' => $session['customer'] ?? null,
        ]);

        return $this->successMethod();
    }

    /**
     * Queue tenant provisioning without blocking the Stripe webhook request.
     */
    protected function dispatchTenantProvisioning(Tenant $tenant): void
    {
        // Refresh do banco para evitar dispatch duplicado em caso de webhooks concorrentes
        $tenant->refresh();

        if ($this->tenantDatabaseCreated($tenant)) {
            Log::info('CreateFullTenantJob ignorado: tenant já provisionado', [
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        $defaultConnection = (string) config('queue.default', 'sync');
        if (! app()->isLocal() && $defaultConnection === 'sync') {
            throw new \RuntimeException('QUEUE_CONNECTION assíncrona é obrigatória fora do ambiente local.');
        }

        if (app()->isLocal() && $defaultConnection === 'sync') {
            CreateFullTenantJob::dispatchAfterResponse($tenant);

            return;
        }

        CreateFullTenantJob::dispatch($tenant)
            ->onConnection($defaultConnection)
            ->onQueue('tenant-provisioning');
    }

    /**
     * Handle invoice.paid event.
     */
    protected function handleInvoicePaid(array $payload)
    {
        $invoice = (array) data_get($payload, 'data.object', []);
        $customerId = $invoice['customer'] ?? null;

        $tenant = $this->validateTenantForWebhook(
            $this->tenantRepository->findByStripeId($customerId),
            'invoice.paid',
            $customerId
        );

        if ($tenant) {
            $this->reconcileTenantBillingState(
                $tenant,
                $invoice['subscription'] ?? null,
                'invoice.paid',
                [
                    'invoice_id' => $invoice['id'] ?? null,
                    'customer_id' => $customerId,
                ]
            );
        }

        return $this->successMethod();
    }

    /**
     * Handle invoice.payment_failed event.
     *
     * Notifica o usuário em toda falha. Suspende após 3 tentativas.
     */
    protected function handleInvoicePaymentFailed(array $payload)
    {
        $invoice = (array) data_get($payload, 'data.object', []);
        $customerId = data_get($invoice, 'customer');
        $attempts = (int) data_get($invoice, 'attempt_count', 0);
        $invoiceUrl = data_get($invoice, 'hosted_invoice_url');

        $tenant = $this->validateTenantForWebhook(
            $this->tenantRepository->findByStripeId($customerId),
            'invoice.payment_failed',
            $customerId
        );

        if (! $tenant) {
            return $this->successMethod();
        }

        // Notifica o usuário em toda falha de pagamento com deep link
        $tenant->notify(new PaymentRetryNotification($this->tenantName($tenant), $attempts, $invoiceUrl));

        $this->audit('tenant.payment_notification_sent', "Notificação de falha de pagamento enviada (tentativa {$attempts}).", [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $this->tenantSlug($tenant),
            'attempt_count' => $attempts,
            'invoice_id' => data_get($invoice, 'id'),
        ]);

        if ($attempts >= 3) {
            $tenant->suspend();

            Log::warning('Tenant suspenso por falta de pagamento', [
                'tenant_id' => $tenant->id,
                'attempts' => $attempts,
            ]);

            $this->audit('tenant.payment_failed', "Pagamento falhou {$attempts}x para tenant '{$this->tenantName($tenant)}'. Tenant suspenso.", [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $this->tenantSlug($tenant),
                'tenant_name' => $this->tenantName($tenant),
                'customer_id' => $customerId,
                'attempt_count' => $attempts,
                'invoice_id' => data_get($invoice, 'id'),
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
        parent::handleCustomerSubscriptionUpdated($payload);

        $subscription = (array) data_get($payload, 'data.object', []);
        $customerId = $subscription['customer'] ?? null;

        $tenant = $this->validateTenantForWebhook(
            $this->tenantRepository->findByStripeId($customerId),
            'customer.subscription.updated',
            $customerId
        );

        if ($tenant) {
            $this->reconcileTenantBillingState(
                $tenant,
                $subscription['id'] ?? null,
                'customer.subscription.updated',
                [
                    'customer_id' => $customerId,
                    'stripe_status' => $subscription['status'] ?? null,
                ]
            );
        }

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.created event.
     */
    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        parent::handleCustomerSubscriptionCreated($payload);

        $subscription = (array) data_get($payload, 'data.object', []);
        $customerId = $subscription['customer'] ?? null;

        $tenant = $this->validateTenantForWebhook(
            $this->tenantRepository->findByStripeId($customerId),
            'customer.subscription.created',
            $customerId
        );

        if ($tenant) {
            $this->reconcileTenantBillingState(
                $tenant,
                $subscription['id'] ?? null,
                'customer.subscription.created',
                [
                    'customer_id' => $customerId,
                    'stripe_status' => $subscription['status'] ?? null,
                ]
            );
        }

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.deleted event.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        // Let Cashier sync local DB
        parent::handleCustomerSubscriptionDeleted($payload);

        $subscription = (array) data_get($payload, 'data.object', []);
        $customerId = $subscription['customer'] ?? null;

        $tenant = $this->validateTenantForWebhook(
            $this->tenantRepository->findByStripeId($customerId),
            'customer.subscription.deleted',
            $customerId
        );

        if ($tenant) {
            $tenant->update([
                'stripe_subscription_id' => $subscription['id'] ?? $this->tenantStripeSubscriptionId($tenant),
            ]);

            $this->billingService->applyStripeSubscriptionStatus($tenant, 'canceled');
            Log::info('Tenant cancelou assinatura', ['tenant_id' => $tenant->id]);

            $this->audit('tenant.subscription_canceled', "Assinatura cancelada para tenant '{$this->tenantName($tenant)}'.", [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $this->tenantSlug($tenant),
                'tenant_name' => $this->tenantName($tenant),
                'customer_id' => $customerId,
                'subscription_id' => $subscription['id'] ?? null,
            ]);
        }

        return $this->successMethod();
    }

    /**
     * Handle customer.subscription.trial_will_end event.
     *
     * Disparado pelo Stripe 3 dias antes do fim do trial.
     * Requer que o evento esteja registrado no Dashboard do Stripe.
     */
    protected function handleCustomerSubscriptionTrialWillEnd(array $payload)
    {
        $subscription = (array) data_get($payload, 'data.object', []);
        $customerId = data_get($subscription, 'customer');
        $trialEnd = data_get($subscription, 'trial_end');

        $tenant = $this->validateTenantForWebhook(
            $this->tenantRepository->findByStripeId($customerId),
            'customer.subscription.trial_will_end',
            $customerId
        );

        if (! $tenant || ! $trialEnd) {
            return $this->successMethod();
        }

        $trialEndsAt = Carbon::createFromTimestamp($trialEnd);

        $tenant->notify(new TrialEndingNotification($this->tenantName($tenant), $trialEndsAt));

        $this->audit('tenant.trial_ending_notified', 'Notificação de fim do período de teste enviada.', [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $this->tenantSlug($tenant),
            'trial_ends_at' => $trialEndsAt->toIso8601String(),
        ]);

        return $this->successMethod();
    }

    /**
     * Handle charge.dispute.created event (chargeback).
     *
     * Requer que o evento esteja registrado no Dashboard do Stripe.
     */
    protected function handleChargeDisputeCreated(array $payload)
    {
        $dispute = (array) data_get($payload, 'data.object', []);
        $chargeId = data_get($dispute, 'charge');
        $amount = data_get($dispute, 'amount');
        $reason = data_get($dispute, 'reason');
        $disputeId = data_get($dispute, 'id');

        Log::critical('Chargeback criado no Stripe', [
            'dispute_id' => $disputeId,
            'charge_id' => $chargeId,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        $this->audit('stripe.dispute_created', 'Chargeback recebido do Stripe.', [
            'dispute_id' => $disputeId,
            'charge_id' => $chargeId,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        return $this->successMethod();
    }

    /**
     * Handle coupon.redeemed event.
     */
    protected function handleCouponRedeemed(array $payload): Response
    {
        $couponData = (array) data_get($payload, 'data.object', []);
        $stripeCouponId = data_get($couponData, 'id');

        if ($stripeCouponId) {
            $this->couponService->incrementRedemption($stripeCouponId);
        }

        $this->audit('coupon.redeemed_webhook', 'Coupon redemption registrado via webhook.', [
            'stripe_coupon_id' => $stripeCouponId,
            'customer' => data_get($payload, 'data.object.customer'),
        ]);

        return $this->successMethod();
    }

    protected function requiresSignedWebhook(): bool
    {
        return ! app()->environment(['local', 'testing']);
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

        if (($session['client_reference_id'] ?? null) && (string) $session['client_reference_id'] !== (string) $tenant->id) {
            return 'client_reference_mismatch';
        }

        $storedSessionId = $this->billingService->getSignupCheckoutSessionId($tenant);
        $receivedSessionId = $session['id'] ?? null;

        if ($storedSessionId && $storedSessionId !== $receivedSessionId) {
            return 'session_mismatch';
        }

        $tenantStripeId = $this->tenantStripeId($tenant);

        if ($tenantStripeId !== null && $tenantStripeId !== ($session['customer'] ?? null)) {
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

    protected function hydrateMissingSignupCheckoutReference(Tenant $tenant, array $session): void
    {
        $receivedSessionId = $session['id'] ?? null;
        if (! is_string($receivedSessionId) || $receivedSessionId === '') {
            return;
        }

        $storedSessionId = $this->billingService->getSignupCheckoutSessionId($tenant);
        if ($storedSessionId) {
            return;
        }

        $this->billingService->storeSignupCheckoutSessionId($tenant, $receivedSessionId);

        Log::warning('Referência local do checkout ausente; vínculo recuperado a partir do webhook assinado', [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $this->tenantSlug($tenant),
            'session_id' => $receivedSessionId,
        ]);
    }

    protected function reconcileTenantBillingState(
        Tenant $tenant,
        ?string $subscriptionId,
        string $source,
        array $context = []
    ): void {
        if (! $subscriptionId) {
            Log::warning('Evento Stripe sem subscription_id para reconciliação do tenant', array_merge([
                'tenant_id' => $tenant->id,
                'source' => $source,
            ], $context));

            return;
        }

        $stripeSubscription = $this->billingService->retrieveSubscription($subscriptionId);
        $stripeStatus = (string) ($stripeSubscription->status ?? '');

        $tenant->update([
            'stripe_id' => $stripeSubscription->customer ?? $this->tenantStripeId($tenant),
            'stripe_subscription_id' => $stripeSubscription->id ?? $subscriptionId,
        ]);

        $this->billingService->syncPlanFromPriceId($tenant, data_get($stripeSubscription, 'items.data.0.price.id'));
        $this->billingService->syncSubscription($tenant, $subscriptionId);

        $appliedStatus = $this->billingService->applyStripeSubscriptionStatus($tenant, $stripeStatus);

        Log::info('Tenant reconciliado a partir de evento Stripe', array_merge([
            'tenant_id' => $tenant->id,
            'source' => $source,
            'stripe_status' => $stripeStatus,
            'applied_status' => $appliedStatus,
            'database_created' => $this->tenantDatabaseCreated($tenant),
        ], $context));

        if (in_array($stripeStatus, ['active', 'trialing'], true)) {
            $this->dispatchTenantProvisioning($tenant);
        }
    }

    /**
     * Valida se o tenant encontrado pelo Stripe customer ID está em estado válido
     * para processar eventos de webhook. Retorna null se o tenant não for válido.
     */
    private function validateTenantForWebhook(?Tenant $tenant, string $source, ?string $customerId): ?Tenant
    {
        if (! $tenant) {
            Log::warning('Tenant não encontrado para customer_id no webhook', [
                'customer_id' => $customerId,
                'source' => $source,
            ]);

            return null;
        }

        // Tenants cancelled não devem receber eventos de billing
        if ($this->tenantStatus($tenant) === TenantStatus::CANCELLED->value) {
            Log::warning('Webhook ignorado: tenant cancelado', [
                'tenant_id' => $tenant->id,
                'tenant_status' => $this->tenantStatus($tenant),
                'source' => $source,
            ]);

            return null;
        }

        // Tenants com setup_failed não devem receber eventos de billing
        if ($this->tenantStatus($tenant) === TenantStatus::SETUP_FAILED->value) {
            Log::warning('Webhook ignorado: setup do tenant falhou', [
                'tenant_id' => $tenant->id,
                'tenant_status' => $this->tenantStatus($tenant),
                'source' => $source,
            ]);

            return null;
        }

        return $tenant;
    }

    private function tenantName(Tenant $tenant): string
    {
        return (string) $tenant->getAttribute('name');
    }

    private function tenantSlug(Tenant $tenant): string
    {
        return (string) $tenant->getAttribute('slug');
    }

    private function tenantStatus(Tenant $tenant): string
    {
        return (string) $tenant->getAttribute('status');
    }

    private function tenantStripeId(Tenant $tenant): ?string
    {
        $stripeId = $tenant->getAttribute('stripe_id');

        return is_string($stripeId) && $stripeId !== '' ? $stripeId : null;
    }

    private function tenantStripeSubscriptionId(Tenant $tenant): ?string
    {
        $subscriptionId = $tenant->getAttribute('stripe_subscription_id');

        return is_string($subscriptionId) && $subscriptionId !== '' ? $subscriptionId : null;
    }

    private function tenantDatabaseCreated(Tenant $tenant): bool
    {
        return (bool) $tenant->getAttribute('database_created');
    }
}
