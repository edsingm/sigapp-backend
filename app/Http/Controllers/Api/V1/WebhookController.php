<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\CreateFullTenantJob;
use App\Models\Central\Tenant;
use App\Traits\LogsAudit;
use App\Models\Central\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends CashierController
{
    use LogsAudit;
    /**
     * Create a new WebhookController instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (config('cashier.webhook.secret') && !app()->isLocal()) {
            $this->middleware(VerifyWebhookSignature::class);
        }
    }
    /**
     * Handle incoming Stripe webhooks.
     */
    public function handleWebhook(Request $request)
    {
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

            if ($response instanceof Response && $response->isSuccessful()) {
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
        $session = (object) $payload['data']['object'];
        $tenantId = $session->metadata['tenant_id'] ?? null;

        if (!$tenantId) {
            Log::warning('Checkout completed sem tenant_id', ['session_id' => $session->id]);

            $this->audit('tenant.checkout_no_tenant_id', 'Checkout concluído sem tenant_id nos metadados da sessão.', [
                'session_id' => $session->id,
                'customer' => $session->customer ?? null,
                'reason' => 'missing_tenant_id',
            ]);

            return $this->successMethod();
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            Log::error('Tenant não encontrado para checkout', ['tenant_id' => $tenantId]);

            $this->audit('tenant.checkout_tenant_not_found', "Checkout concluído mas tenant ID '{$tenantId}' não encontrado no banco.", [
                'tenant_id' => $tenantId,
                'session_id' => $session->id,
                'customer' => $session->customer ?? null,
                'reason' => 'tenant_not_found',
            ]);

            return $this->successMethod();
        }

        $signupContractAcceptance = $this->getSignupContractAcceptance($tenant);
        $signupContractAccepted = (bool) data_get($signupContractAcceptance, 'accepted', false);
        if (!$signupContractAccepted) {
            Log::warning('Checkout completed para tenant sem aceite de contrato registrado no signup', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'session_id' => $session->id ?? null,
                'customer_id' => $session->customer ?? null,
            ]);

            $this->audit('tenant.checkout_missing_contract_acceptance', 'Checkout concluído sem aceite de contrato de utilização registrado no signup.', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->name,
                'session_id' => $session->id ?? null,
                'subscription_id' => $session->subscription ?? null,
                'customer_id' => $session->customer ?? null,
                'reason' => 'missing_signup_contract_acceptance',
            ]);
        }

        $tenant->update([
            'stripe_subscription_id' => $session->subscription,
            'stripe_id' => $session->customer,
        ]);

        Log::info('Checkout completed, disparando CreateFullTenantJob', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $session->subscription,
        ]);

        $this->dispatchTenantProvisioning($tenant);

        // Audit: checkout completed
        $this->audit('tenant.checkout_completed', "Checkout Stripe concluído para tenant '{$tenant->name}'. Job de criação disparado.", [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->name,
            'session_id' => $session->id,
            'subscription_id' => $session->subscription,
            'customer_id' => $session->customer,
        ]);

        // Manual Sync: Ensure subscription is recorded (fix for race condition)
        if ($session->subscription) {
            $this->syncSubscription($tenant, $session->subscription);
        }

        return $this->successMethod();
    }

    /**
     * Stancl Tenancy decodes the "data" JSON column into virtual attributes on retrieval.
     * This helper supports both shapes (virtual attribute and raw data column).
     */
    protected function getSignupContractAcceptance(Tenant $tenant): array
    {
        $virtualAcceptance = $tenant->getAttribute('signup_contract_acceptance');
        if (is_array($virtualAcceptance)) {
            return $virtualAcceptance;
        }

        $rawData = $tenant->getAttribute('data');
        if (is_array($rawData)) {
            return (array) ($rawData['signup_contract_acceptance'] ?? []);
        }

        if (is_string($rawData) && $rawData !== '') {
            $decoded = json_decode($rawData, true);
            if (is_array($decoded)) {
                return (array) ($decoded['signup_contract_acceptance'] ?? []);
            }
        }

        return [];
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
        // Let Cashier handle DB sync if any (usually not much for invoice.paid)

        $invoice = (object) $payload['data']['object'];
        $customerId = $invoice->customer;

        $tenant = Tenant::where('stripe_id', $customerId)->first();

        if ($tenant && $tenant->status === Tenant::STATUS_SUSPENDED) {
            $tenant->activate();
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

        $subscription = (object) $payload['data']['object'];
        $customerId = $subscription->customer;

        $tenant = Tenant::where('stripe_id', $customerId)->first();

        if ($tenant) {
            // Check if plan changed
            $priceId = $subscription->items['data'][0]['price']['id'] ?? null;

            if ($priceId) {
                $newPlan = \App\Models\Central\Plan::where('stripe_price_id', $priceId)->first();

                if ($newPlan && $newPlan->id !== $tenant->plan_id) {
                    $tenant->update(['plan_id' => $newPlan->id]);
                    Log::info('Tenant mudou de plano', ['tenant_id' => $tenant->id]);
                }
            }

            // Check subscription status
            if (($subscription->status ?? '') === 'active' && !$tenant->isActive()) {
                $tenant->activate();
            }
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

        $subscription = (object) $payload['data']['object'];
        $customerId = $subscription->customer;

        $tenant = Tenant::where('stripe_id', $customerId)->first();

        if ($tenant) {
            $tenant->cancel();
            Log::info('Tenant cancelou assinatura', ['tenant_id' => $tenant->id]);

            $this->audit('tenant.subscription_canceled', "Assinatura cancelada para tenant '{$tenant->name}'.", [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->name,
                'customer_id' => $customerId,
                'subscription_id' => $subscription->id ?? null,
            ]);
        }

        return $this->successMethod();
    }

    /**
     * Sync subscription manually from Stripe to DB.
     */
    protected function syncSubscription(Tenant $tenant, string $subscriptionId)
    {
        try {
            // Check if subscription already exists
            if ($tenant->subscriptions()->where('stripe_id', $subscriptionId)->exists()) {
                return;
            }

            $stripeSubscription = $tenant->stripe()->subscriptions->retrieve($subscriptionId);

            $subscription = $tenant->subscriptions()->create([
                'type' => 'default',
                'stripe_id' => $stripeSubscription->id,
                'stripe_status' => $stripeSubscription->status,
                'stripe_price' => $stripeSubscription->items->data[0]->price->id ?? null,
                'quantity' => $stripeSubscription->items->data[0]->quantity ?? 1,
                'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                'ends_at' => $stripeSubscription->cancel_at ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->cancel_at) : null,
            ]);

            foreach ($stripeSubscription->items->data as $item) {
                $subscription->items()->create([
                    'stripe_id' => $item->id,
                    'stripe_product' => $item->price->product,
                    'stripe_price' => $item->price->id,
                    'quantity' => $item->quantity ?? 1,
                ]);
            }

            Log::info('Assinatura sincronizada manualmente via checkout session', ['subscription_id' => $subscriptionId]);
        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar assinatura manualmente', ['error' => $e->getMessage()]);
        }
    }
}
