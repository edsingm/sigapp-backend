<?php

namespace App\Services\Billing;

use App\Enums\TenantStatus;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Notifications\PaymentRetryNotification;
use Carbon\Carbon;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

class TenantBillingService
{
    public const STATUS_NOOP = 'noop';

    public const STATUS_ACTIVE = Tenant::STATUS_ACTIVE;

    public const STATUS_SUSPENDED = Tenant::STATUS_SUSPENDED;

    public const STATUS_CANCELLED = Tenant::STATUS_CANCELLED;

    protected function stripe(): StripeClient
    {
        return Cashier::stripe();
    }

    public function getSignupContractAcceptance(Tenant $tenant): array
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

    public function getSignupCheckoutSessionId(Tenant $tenant): ?string
    {
        $tenantData = $tenant->getAttribute('data');

        // Campo novo: top-level no JSON data
        if (is_array($tenantData)) {
            $sessionId = $tenantData['stripe_checkout_session_id'] ?? null;
            if (is_string($sessionId) && $sessionId !== '') {
                return $sessionId;
            }
        }

        // Fallback: campo antigo dentro de signup_contract_acceptance
        $sessionId = data_get($this->getSignupContractAcceptance($tenant), 'stripe_checkout_session_id');

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    public function storeSignupCheckoutSessionId(Tenant $tenant, string $sessionId): void
    {
        if ($sessionId === '') {
            return;
        }

        $tenantData = $tenant->getAttribute('data');
        if (! is_array($tenantData)) {
            $tenantData = [];
        }

        // Campo novo: top-level no JSON data
        $tenantData['stripe_checkout_session_id'] = $sessionId;

        // Limpa do campo antigo se existir
        data_set($tenantData, 'signup_contract_acceptance.stripe_checkout_session_id', null);

        $tenant->update(['data' => $tenantData]);
        $tenant->setAttribute('data', $tenantData);
    }

    public function matchesSignupCheckoutSession(Tenant $tenant, ?string $sessionId): bool
    {
        if (! is_string($sessionId) || $sessionId === '') {
            return false;
        }

        return $this->getSignupCheckoutSessionId($tenant) === $sessionId;
    }

    public function findTenantBySignupCheckoutSessionId(string $sessionId): ?Tenant
    {
        // Busca no campo novo primeiro, depois no antigo
        return Tenant::query()
            ->where('data->stripe_checkout_session_id', $sessionId)
            ->first()
            ?? Tenant::query()
                ->where('data->signup_contract_acceptance->stripe_checkout_session_id', $sessionId)
                ->first();
    }

    public function retrieveCheckoutSession(string $sessionId): object
    {
        return $this->stripe()->checkout->sessions->retrieve($sessionId, []);
    }

    public function expireCheckoutSession(string $sessionId): void
    {
        $this->stripe()->checkout->sessions->expire($sessionId, []);
    }

    public function deleteCustomer(string $customerId): void
    {
        $this->stripe()->customers->delete($customerId, []);
    }

    public function retrieveSubscription(string $subscriptionId): object
    {
        return $this->stripe()->subscriptions->retrieve($subscriptionId, []);
    }

    public function cancelSubscription(string $subscriptionId): object
    {
        return $this->stripe()->subscriptions->cancel($subscriptionId, []);
    }

    public function createBillingPortalUrl(Tenant $tenant, ?string $returnUrl = null): string
    {
        return $tenant->billingPortalUrl($returnUrl);
    }

    public function createSetupIntentSecret(Tenant $tenant): string
    {
        return $tenant->createSetupIntent()->client_secret;
    }

    public function updateDefaultPaymentMethod(Tenant $tenant, string $paymentMethodId): void
    {
        $tenant->updateDefaultPaymentMethod($paymentMethodId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdminFinanceOverview(Tenant $tenant): array
    {
        $tenantStatus = (string) $tenant->getAttribute('status');
        $tenantStripeId = $tenant->getAttribute('stripe_id');
        $trialEndsAt = $tenant->getAttribute('trial_ends_at');

        $finance = [
            'has_payment_method' => false,
            'card_brand' => null,
            'card_last4' => null,
            'card_exp_month' => null,
            'card_exp_year' => null,
            'invoices' => [],
            'subscription_status' => $tenantStatus,
            'renews_at' => null,
            'canceled_at' => null,
            'error' => null,
        ];

        try {
            if (is_string($tenantStripeId) && $tenantStripeId !== '') {
                $finance['has_payment_method'] = $tenant->hasDefaultPaymentMethod();

                if ($finance['has_payment_method']) {
                    $paymentMethod = $tenant->defaultPaymentMethod();

                    if ($paymentMethod !== null) {
                        $finance['card_brand'] = $paymentMethod->card->brand;
                        $finance['card_last4'] = $paymentMethod->card->last4;
                        $finance['card_exp_month'] = $paymentMethod->card->exp_month;
                        $finance['card_exp_year'] = $paymentMethod->card->exp_year;
                    }
                }

                $subscription = $tenant->subscription('default');

                if ($subscription !== null) {
                    $finance['subscription_status'] = $subscription->stripe_status;
                    $stripeSubscriptionData = $subscription->asStripeSubscription();
                    $currentPeriodEnd = data_get($stripeSubscriptionData, 'current_period_end');
                    $finance['renews_at'] = $subscription->ends_at
                        ? null
                        : (is_int($currentPeriodEnd) ? $currentPeriodEnd : null);
                    $finance['canceled_at'] = $subscription->ends_at;
                }

                foreach ($tenant->invoicesIncludingPending(['limit' => 5]) as $invoice) {
                    $finance['invoices'][] = [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'total' => $invoice->total(),
                        'status' => $invoice->status,
                        'created_at' => $invoice->created,
                        'pdf' => $invoice->hosted_invoice_url,
                        'download' => $invoice->invoice_pdf,
                    ];
                }
            } elseif ($tenant->onTrial()) {
                $finance['subscription_status'] = 'trialing';
                $finance['renews_at'] = $trialEndsAt?->timestamp;
            }
        } catch (\Throwable $exception) {
            $finance['error'] = 'Erro ao carregar dados do Stripe: '.$exception->getMessage();
        }

        return $finance;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscriptionSnapshot(Tenant $tenant): array
    {
        $tenant->load('plan');
        $localSubscription = $tenant->subscription('default');
        $tenantStripeId = $tenant->getAttribute('stripe_id');
        $tenantStripeSubscriptionId = $tenant->getAttribute('stripe_subscription_id');
        $tenantTrialEndsAt = $tenant->getAttribute('trial_ends_at');

        $stripeData = null;
        $invoices = [];
        $stripeError = null;

        if (is_string($tenantStripeId) && $tenantStripeId !== '') {
            try {
                $stripe = $tenant->stripe();
                $customer = $stripe->customers->retrieve($tenantStripeId, []);

                $stripeSubscription = null;
                if (is_string($tenantStripeSubscriptionId) && $tenantStripeSubscriptionId !== '') {
                    $stripeSubscription = $stripe->subscriptions->retrieve($tenantStripeSubscriptionId, []);
                }

                $defaultPaymentMethod = null;
                $defaultPaymentMethodId =
                    $stripeSubscription->default_payment_method
                    ?? ($customer->invoice_settings->default_payment_method ?? null);

                if ($defaultPaymentMethodId) {
                    $defaultPaymentMethod = $stripe->paymentMethods->retrieve($defaultPaymentMethodId, []);
                }

                $stripeData = [
                    'customer' => [
                        'id' => $customer->id ?? null,
                        'email' => $customer->email ?? null,
                        'name' => $customer->name ?? null,
                        'invoice_prefix' => $customer->invoice_prefix ?? null,
                        'default_payment_method' => $defaultPaymentMethod ? [
                            'id' => $defaultPaymentMethod->id ?? null,
                            'brand' => $defaultPaymentMethod->card->brand ?? null,
                            'last4' => $defaultPaymentMethod->card->last4 ?? null,
                            'exp_month' => $defaultPaymentMethod->card->exp_month ?? null,
                            'exp_year' => $defaultPaymentMethod->card->exp_year ?? null,
                        ] : null,
                    ],
                    'subscription' => $stripeSubscription ? [
                        'id' => $stripeSubscription->id ?? null,
                        'status' => $stripeSubscription->status ?? null,
                        'collection_method' => $stripeSubscription->collection_method ?? null,
                        'current_period_start' => is_int(data_get($stripeSubscription, 'current_period_start'))
                            ? Carbon::createFromTimestamp((int) data_get($stripeSubscription, 'current_period_start'))->toIso8601String()
                            : null,
                        'current_period_end' => is_int(data_get($stripeSubscription, 'current_period_end'))
                            ? Carbon::createFromTimestamp((int) data_get($stripeSubscription, 'current_period_end'))->toIso8601String()
                            : null,
                        'cancel_at' => $stripeSubscription->cancel_at
                            ? Carbon::createFromTimestamp($stripeSubscription->cancel_at)->toIso8601String()
                            : null,
                        'cancel_at_period_end' => (bool) ($stripeSubscription->cancel_at_period_end ?? false),
                        'billing_cycle_anchor' => $stripeSubscription->billing_cycle_anchor
                            ? Carbon::createFromTimestamp($stripeSubscription->billing_cycle_anchor)->toIso8601String()
                            : null,
                        'price_id' => $stripeSubscription->items->data[0]->price->id ?? null,
                        'latest_invoice' => $stripeSubscription->latest_invoice ?? null,
                    ] : null,
                ];

                $stripeInvoices = $stripe->invoices->all([
                    'customer' => $tenantStripeId,
                    'limit' => 8,
                ]);

                foreach ($stripeInvoices->data ?? [] as $invoice) {
                    $invoices[] = [
                        'id' => $invoice->id ?? null,
                        'number' => $invoice->number ?? null,
                        'status' => $invoice->status ?? null,
                        'amount_due' => $invoice->amount_due ?? null,
                        'amount_paid' => $invoice->amount_paid ?? null,
                        'amount_remaining' => $invoice->amount_remaining ?? null,
                        'currency' => $invoice->currency ?? null,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                        'invoice_pdf' => $invoice->invoice_pdf ?? null,
                        'created_at' => $invoice->created
                            ? Carbon::createFromTimestamp($invoice->created)->toIso8601String()
                            : null,
                        'period_start' => $invoice->period_start
                            ? Carbon::createFromTimestamp($invoice->period_start)->toIso8601String()
                            : null,
                        'period_end' => $invoice->period_end
                            ? Carbon::createFromTimestamp($invoice->period_end)->toIso8601String()
                            : null,
                    ];
                }
            } catch (\Exception $e) {
                $stripeError = $e->getMessage();
            }
        }

        return [
            'on_trial' => $tenant->onTrial(),
            'trial_ends_at' => $tenantTrialEndsAt?->toIso8601String(),
            'trial_ended' => $tenant->trialEnded(),
            'stripe_customer_id' => $tenantStripeId,
            'stripe_subscription_id' => $tenantStripeSubscriptionId,
            'local_subscription' => $localSubscription ? [
                'stripe_status' => $localSubscription->stripe_status,
                'trial_ends_at' => $localSubscription->trial_ends_at?->toIso8601String(),
                'ends_at' => $localSubscription->ends_at?->toIso8601String(),
            ] : null,
            'stripe' => $stripeData,
            'invoices' => $invoices,
            'stripe_error' => app()->environment('local') ? $stripeError : null,
        ];
    }

    public function syncPlanFromPriceId(Tenant $tenant, ?string $priceId): void
    {
        if (! $priceId) {
            return;
        }

        $newPlan = Plan::where('stripe_price_id', $priceId)->first();

        if ($newPlan && $newPlan->id !== $tenant->getAttribute('plan_id')) {
            $tenant->update(['plan_id' => $newPlan->id]);
        }
    }

    /**
     * Aplica o status da assinatura do Stripe ao tenant.
     *
     * - active/trialing  → ativa o tenant
     * - past_due         → notifica o usuário (Stripe ainda está em retry, não suspende)
     * - unpaid/incomplete_expired → suspende
     * - canceled         → cancela
     * - outros           → noop
     */
    public function applyStripeSubscriptionStatus(Tenant $tenant, ?string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active', 'trialing' => tap(self::STATUS_ACTIVE, fn () => $tenant->activate()),
            'past_due' => tap(self::STATUS_NOOP, fn () => $tenant->notify(
                new PaymentRetryNotification((string) $tenant->getAttribute('name'), 0, null)
            )),
            'unpaid', 'incomplete_expired' => tap(self::STATUS_SUSPENDED, fn () => $tenant->suspend()),
            'canceled' => tap(self::STATUS_CANCELLED, fn () => $tenant->cancel()),
            default => self::STATUS_NOOP,
        };
    }

    public function syncSubscription(Tenant $tenant, string $subscriptionId): void
    {
        $stripeSubscription = $this->retrieveSubscription($subscriptionId);
        $tenantTrialEndsAt = $tenant->getAttribute('trial_ends_at');
        $subscriptionItems = $stripeSubscription->items->data ?? [];
        $primaryItem = $subscriptionItems[0] ?? null;

        $subscription = $tenant->subscriptions()->firstOrNew([
            'stripe_id' => $stripeSubscription->id,
        ]);

        $trialEndsAt = $stripeSubscription->trial_end
            ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
            : null;

        $endsAt = $stripeSubscription->cancel_at
            ? Carbon::createFromTimestamp($stripeSubscription->cancel_at)
            : null;

        $subscription->fill([
            'type' => 'default',
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $primaryItem->price->id ?? null,
            'quantity' => $primaryItem->quantity ?? 1,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => $endsAt,
        ]);
        $subscription->save();

        foreach ($subscriptionItems as $item) {
            $subscription->items()->updateOrCreate([
                'stripe_id' => $item->id,
            ], [
                'stripe_product' => $item->price->product,
                'stripe_price' => $item->price->id,
                'quantity' => $item->quantity ?? 1,
            ]);
        }

        // Sincroniza trial_ends_at do Stripe de volta para a coluna do tenant,
        // corrigindo possível dessincronização entre o valor local (calculado no signup)
        // e o valor real registrado no Stripe (contado a partir do checkout completion).
        if ($stripeSubscription->trial_end) {
            if (! $tenantTrialEndsAt || ! $tenantTrialEndsAt->eq($trialEndsAt)) {
                $tenant->update(['trial_ends_at' => $trialEndsAt]);
            }
        } elseif ($tenantTrialEndsAt && $stripeSubscription->status !== 'trialing') {
            // Trial encerrado no Stripe mas ainda definido localmente — limpa
            $tenant->update(['trial_ends_at' => null]);
        }
    }

    public function reconcileTenantActivation(Tenant $tenant): array
    {
        $tenantTrialEndsAt = $tenant->getAttribute('trial_ends_at');
        $tenantStripeSubscriptionId = $tenant->getAttribute('stripe_subscription_id');

        if ($tenant->onTrial() && (! is_string($tenantStripeSubscriptionId) || $tenantStripeSubscriptionId === '')) {
            $tenant->activate();

            return [
                'eligible' => true,
                'source' => 'local_trial',
                'stripe_status' => null,
            ];
        }

        if (! is_string($tenantStripeSubscriptionId) || $tenantStripeSubscriptionId === '') {
            return [
                'eligible' => false,
                'source' => 'missing_subscription_reference',
                'stripe_status' => null,
            ];
        }

        $subscription = $this->retrieveSubscription($tenantStripeSubscriptionId);
        $stripeStatus = (string) ($subscription->status ?? '');

        $tenant->update([
            'stripe_id' => $subscription->customer ?? $tenant->getAttribute('stripe_id'),
            'stripe_subscription_id' => $subscription->id ?? $tenantStripeSubscriptionId,
        ]);

        $this->syncPlanFromPriceId($tenant, $subscription->items->data[0]->price->id ?? null);
        $this->syncSubscription($tenant, $subscription->id);

        $this->applyStripeSubscriptionStatus($tenant, $stripeStatus);

        return [
            'eligible' => in_array($stripeStatus, ['active', 'trialing'], true),
            'source' => 'stripe',
            'stripe_status' => $stripeStatus,
        ];
    }

    /**
     * Retorna o status de pagamento do tenant para dunning self-service.
     *
     * @return array<string, mixed>
     */
    public function getPaymentRetryStatus(Tenant $tenant): array
    {
        $tenantStatus = (string) $tenant->getAttribute('status');
        $tenantStripeId = $tenant->getAttribute('stripe_id');
        $result = [
            'is_past_due' => false,
            'attempt_count' => 0,
            'next_retry_at' => null,
            'amount_due' => null,
            'currency' => null,
            'invoice_url' => null,
            'invoice_id' => null,
            'can_retry' => false,
            'subscription_status' => $tenantStatus,
        ];

        if (! is_string($tenantStripeId) || $tenantStripeId === '') {
            return $result;
        }

        try {
            $stripe = $this->stripe();

            $invoices = $stripe->invoices->all([
                'customer' => $tenantStripeId,
                'status' => 'open',
                'limit' => 1,
            ]);

            if (isset($invoices->data[0])) {
                $invoice = $invoices->data[0];
                $amountRemaining = $invoice->amount_remaining ?? 0;

                if ($amountRemaining > 0) {
                    $result['is_past_due'] = true;
                    $result['attempt_count'] = (int) ($invoice->attempt_count ?? 0);
                    $result['amount_due'] = $amountRemaining;
                    $result['currency'] = $invoice->currency ?? 'brl';
                    $result['invoice_url'] = $invoice->hosted_invoice_url ?? null;
                    $result['invoice_id'] = $invoice->id ?? null;
                    $result['can_retry'] = $tenantStatus !== TenantStatus::CANCELLED->value;

                    if ($invoice->next_payment_attempt) {
                        $result['next_retry_at'] = Carbon::createFromTimestamp(
                            $invoice->next_payment_attempt
                        )->toIso8601String();
                    }
                }
            }
        } catch (\Throwable) {
            // Em caso de erro, retorna o estado básico
        }

        return $result;
    }

    /**
     * Dispara o reprocessamento de uma invoice pendente.
     */
    public function triggerPaymentRetry(Tenant $tenant): bool
    {
        $tenantStripeId = $tenant->getAttribute('stripe_id');
        if (! is_string($tenantStripeId) || $tenantStripeId === '') {
            return false;
        }

        try {
            $stripe = $this->stripe();

            $invoices = $stripe->invoices->all([
                'customer' => $tenantStripeId,
                'status' => 'open',
                'limit' => 1,
            ]);

            if (isset($invoices->data[0])) {
                $invoice = $invoices->data[0];
                $invoiceId = $invoice->id ?? '';

                if (($invoice->amount_remaining ?? 0) > 0 && $invoiceId !== '') {
                    $stripe->invoices->sendInvoice($invoiceId, []);

                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Notifica o tenant sobre falha de pagamento com deep link.
     */
    public function notifyPaymentRetry(Tenant $tenant, int $attemptCount, ?string $invoiceUrl = null): void
    {
        $tenant->notify(new PaymentRetryNotification(
            tenantName: (string) $tenant->getAttribute('name'),
            attemptCount: $attemptCount,
            invoiceUrl: $invoiceUrl,
        ));
    }
}
