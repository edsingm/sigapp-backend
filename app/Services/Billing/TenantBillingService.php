<?php

namespace App\Services\Billing;

use App\Enums\TenantStatus;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonSubscription;
use App\Notifications\PaymentRetryNotification;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\TenantAddonSubscriptionRepositoryInterface;
use Carbon\Carbon;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
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

    public function retrievePrice(string $priceId): object
    {
        return $this->stripe()->prices->retrieve($priceId, []);
    }

    public function retrieveCharge(string $chargeId): object
    {
        return $this->stripe()->charges->retrieve($chargeId, []);
    }

    public function cancelSubscription(string $subscriptionId): object
    {
        return $this->stripe()->subscriptions->cancel($subscriptionId, []);
    }

    /**
     * Remove um subscription item avulso (add-on) da assinatura no Stripe.
     *
     * @param  bool  $prorate  true = create_prorations (crédito proporcional); false = none
     */
    public function deleteSubscriptionItem(string $subscriptionItemId, bool $prorate = true): object
    {
        return $this->stripe()->subscriptionItems->delete($subscriptionItemId, [
            'proration_behavior' => $prorate ? 'create_prorations' : 'none',
        ]);
    }

    public function createBillingPortalUrl(Tenant $tenant, ?string $returnUrl = null): string
    {
        // Quando há uma portal configuration dedicada (troca de plano desabilitada),
        // enviamos explicitamente para não depender da configuração default do Dashboard,
        // que permitiria trocar de plano fora do PlanSwapController. Vazio => default.
        $options = [];
        $configurationId = config('cashier.portal_configuration_id');
        if (is_string($configurationId) && $configurationId !== '') {
            $options['configuration'] = $configurationId;
        }

        return $tenant->billingPortalUrl($returnUrl, $options);
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
            'payment_method_type' => null,
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
                $payment = $this->resolveAdminPaymentMethod($tenant, $tenantStripeId);
                $finance = array_merge($finance, $payment);

                $subscription = $tenant->subscription('default');

                if ($subscription !== null) {
                    $finance['subscription_status'] = $subscription->stripe_status;
                    $stripeSubscriptionData = $subscription->asStripeSubscription();
                    $currentPeriodEnd = $this->resolveStripePeriodTimestamp(
                        $stripeSubscriptionData,
                        'current_period_end'
                    );
                    $finance['renews_at'] = $subscription->ends_at
                        ? null
                        : $currentPeriodEnd;
                    $endsAt = $subscription->ends_at;
                    $finance['canceled_at'] = $endsAt instanceof \DateTimeInterface
                        ? $endsAt->format(\DateTimeInterface::ATOM)
                        : $endsAt;
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
                $finance['renews_at'] = $trialEndsAt instanceof \DateTimeInterface
                    ? $trialEndsAt->getTimestamp()
                    : ($trialEndsAt?->timestamp ?? null);
            }
        } catch (\Throwable $exception) {
            $finance['error'] = 'Erro ao carregar dados do Stripe: '.$exception->getMessage();
        }

        return $finance;
    }

    /**
     * Resolve cartão/método de pagamento para o admin.
     * Cashier só enxerga o default do customer; em vários tenants o PM está
     * na subscription ou só listado em paymentMethods — cobrimos esses casos.
     *
     * @return array{
     *   has_payment_method: bool,
     *   payment_method_type: ?string,
     *   card_brand: ?string,
     *   card_last4: ?string,
     *   card_exp_month: ?int,
     *   card_exp_year: ?int
     * }
     */
    private function resolveAdminPaymentMethod(Tenant $tenant, string $stripeCustomerId): array
    {
        $empty = [
            'has_payment_method' => false,
            'payment_method_type' => null,
            'card_brand' => null,
            'card_last4' => null,
            'card_exp_month' => null,
            'card_exp_year' => null,
        ];

        // 1) Cashier default no customer
        try {
            $cashierPm = $tenant->defaultPaymentMethod();
            if ($cashierPm !== null) {
                $mapped = $this->mapStripePaymentMethodPayload($cashierPm);
                if ($mapped['has_payment_method']) {
                    return $mapped;
                }
            }
        } catch (\Throwable) {
            // segue para API Stripe
        }

        try {
            $stripe = $tenant->stripe();
            $customer = $stripe->customers->retrieve($stripeCustomerId, []);

            $pmId = null;

            // 2) Default da subscription Stripe
            $subscriptionStripeId = $tenant->getAttribute('stripe_subscription_id');
            if (! is_string($subscriptionStripeId) || $subscriptionStripeId === '') {
                $localSub = $tenant->subscription('default');
                $subscriptionStripeId = $localSub?->getAttribute('stripe_id');
            }

            if (is_string($subscriptionStripeId) && $subscriptionStripeId !== '') {
                try {
                    $stripeSub = $stripe->subscriptions->retrieve($subscriptionStripeId, []);
                    $pmId = $this->stripeIdOrNull($stripeSub->default_payment_method ?? null);
                } catch (\Throwable) {
                    // ignore
                }
            }

            // 3) Default do customer (invoice_settings)
            if ($pmId === null) {
                $pmId = $this->stripeIdOrNull(
                    $customer->invoice_settings->default_payment_method ?? null
                );
            }

            if (is_string($pmId) && str_starts_with($pmId, 'pm_')) {
                $pm = $stripe->paymentMethods->retrieve($pmId, []);

                return $this->mapStripePaymentMethodPayload($pm);
            }

            // 4) Fallback: primeiro cartão anexado ao customer
            $listed = $stripe->paymentMethods->all([
                'customer' => $stripeCustomerId,
                'type' => 'card',
                'limit' => 1,
            ]);
            $first = $listed->data[0] ?? null;
            if ($first !== null) {
                return $this->mapStripePaymentMethodPayload($first);
            }
        } catch (\Throwable) {
            return $empty;
        }

        return $empty;
    }

    private function stripeIdOrNull(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }
        if (is_object($value) && isset($value->id) && is_string($value->id)) {
            return $value->id;
        }

        return null;
    }

    /**
     * @return array{
     *   has_payment_method: bool,
     *   payment_method_type: ?string,
     *   card_brand: ?string,
     *   card_last4: ?string,
     *   card_exp_month: ?int,
     *   card_exp_year: ?int
     * }
     */
    private function mapStripePaymentMethodPayload(mixed $paymentMethod): array
    {
        $empty = [
            'has_payment_method' => false,
            'payment_method_type' => null,
            'card_brand' => null,
            'card_last4' => null,
            'card_exp_month' => null,
            'card_exp_year' => null,
        ];

        if ($paymentMethod === null) {
            return $empty;
        }

        // Laravel Cashier PaymentMethod → Stripe object
        if (is_object($paymentMethod) && method_exists($paymentMethod, 'asStripePaymentMethod')) {
            try {
                $paymentMethod = $paymentMethod->asStripePaymentMethod();
            } catch (\Throwable) {
                // usa o wrapper Cashier como está
            }
        }

        $type = null;
        $card = null;

        if (is_object($paymentMethod)) {
            $type = isset($paymentMethod->type) && is_string($paymentMethod->type)
                ? $paymentMethod->type
                : null;
            $card = $paymentMethod->card ?? null;
        } elseif (is_array($paymentMethod)) {
            $type = isset($paymentMethod['type']) && is_string($paymentMethod['type'])
                ? $paymentMethod['type']
                : null;
            $card = $paymentMethod['card'] ?? null;
        } else {
            return $empty;
        }

        $brand = null;
        $last4 = null;
        $expMonth = null;
        $expYear = null;

        if (is_object($card)) {
            $brand = isset($card->brand) && is_string($card->brand) ? $card->brand : null;
            $last4 = isset($card->last4) && is_string($card->last4) ? $card->last4 : null;
            $expMonth = isset($card->exp_month) && is_numeric($card->exp_month)
                ? (int) $card->exp_month
                : null;
            $expYear = isset($card->exp_year) && is_numeric($card->exp_year)
                ? (int) $card->exp_year
                : null;
        } elseif (is_array($card)) {
            $brand = isset($card['brand']) && is_string($card['brand']) ? $card['brand'] : null;
            $last4 = isset($card['last4']) && is_string($card['last4']) ? $card['last4'] : null;
            $expMonth = isset($card['exp_month']) && is_numeric($card['exp_month'])
                ? (int) $card['exp_month']
                : null;
            $expYear = isset($card['exp_year']) && is_numeric($card['exp_year'])
                ? (int) $card['exp_year']
                : null;
        }

        $hasCard = $brand !== null || $last4 !== null;
        $hasAny = $hasCard || $type !== null;

        return [
            'has_payment_method' => $hasAny,
            'payment_method_type' => $type,
            'card_brand' => $brand,
            'card_last4' => $last4,
            'card_exp_month' => $expMonth,
            'card_exp_year' => $expYear,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscriptionSnapshot(Tenant $tenant): array
    {
        $tenant->load(['plan', 'scheduledPlan']);
        $addonSubscriptions = app(TenantAddonSubscriptionRepositoryInterface::class)->forTenant($tenant);
        $localSubscription = $tenant->subscription('default');
        $scheduledPlan = $tenant->getRelationValue('scheduledPlan');
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
                        // Stripe API recente moveu current_period_* para SubscriptionItem.
                        'current_period_start' => $this->formatStripePeriodIso(
                            $stripeSubscription,
                            'current_period_start'
                        ),
                        'current_period_end' => $this->formatStripePeriodIso(
                            $stripeSubscription,
                            'current_period_end'
                        ),
                        'cancel_at' => $stripeSubscription->cancel_at
                            ? Carbon::createFromTimestamp($stripeSubscription->cancel_at)->toIso8601String()
                            : null,
                        'cancel_at_period_end' => (bool) ($stripeSubscription->cancel_at_period_end ?? false),
                        'billing_cycle_anchor' => $stripeSubscription->billing_cycle_anchor
                            ? Carbon::createFromTimestamp($stripeSubscription->billing_cycle_anchor)->toIso8601String()
                            : null,
                        'price_id' => data_get(
                            $this->resolvePrimarySubscriptionItem($tenant, $stripeSubscription),
                            'price.id',
                        ),
                        'latest_invoice' => $stripeSubscription->latest_invoice ?? null,
                    ] : null,
                ];

                $stripeInvoices = $stripe->invoices->all([
                    'customer' => $tenantStripeId,
                    'limit' => 8,
                ]);

                foreach ($stripeInvoices->data ?? [] as $invoice) {
                    [$periodStartIso, $periodEndIso] = $this->resolveInvoicePeriodIso($invoice);

                    $invoices[] = [
                        'id' => $invoice->id ?? null,
                        'number' => $invoice->number ?? null,
                        'status' => $invoice->status ?? null,
                        // total = valor da fatura (após descontos/impostos). amount_due
                        // zera após o pagamento e não deve ser o valor de exibição.
                        'total' => $invoice->total ?? null,
                        'subtotal' => $invoice->subtotal ?? null,
                        'amount_due' => $invoice->amount_due ?? null,
                        'amount_paid' => $invoice->amount_paid ?? null,
                        'amount_remaining' => $invoice->amount_remaining ?? null,
                        'currency' => $invoice->currency ?? null,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                        'invoice_pdf' => $invoice->invoice_pdf ?? null,
                        'created_at' => $invoice->created
                            ? Carbon::createFromTimestampUTC((int) $invoice->created)->toIso8601String()
                            : null,
                        'period_start' => $periodStartIso,
                        'period_end' => $periodEndIso,
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
            // Downgrade agendado: plano que entra em vigor na próxima renovação
            // (definido pelo PlanSwapController). null quando não há downgrade pendente.
            'scheduled_plan' => $scheduledPlan instanceof Plan ? [
                'id' => $scheduledPlan->getKey(),
                'name' => $scheduledPlan->getAttribute('name'),
                'slug' => $scheduledPlan->getAttribute('slug'),
                'formatted_price' => $scheduledPlan->getAttribute('formatted_price'),
            ] : null,
            'local_subscription' => $localSubscription ? [
                'stripe_status' => $localSubscription->stripe_status,
                'trial_ends_at' => $localSubscription->trial_ends_at?->toIso8601String(),
                'ends_at' => $localSubscription->ends_at?->toIso8601String(),
            ] : null,
            'addons' => $addonSubscriptions->map(
                static fn (TenantAddonSubscription $subscription): array => [
                    'id' => $subscription->getKey(),
                    'slug' => $subscription->addon?->slug,
                    'name' => $subscription->addon?->name,
                    'quantity' => $subscription->quantity,
                    'status' => $subscription->status->value,
                    'grants_access' => $subscription->grantsAccess(),
                    'cancel_at_period_end' => $subscription->cancel_at_period_end,
                    'current_period_start' => $subscription->current_period_start?->toIso8601String(),
                    'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                    'canceled_at' => $subscription->canceled_at?->toIso8601String(),
                ],
            )->values()->all(),
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

        $newPlan = app(PlanRepositoryInterface::class)->findByStripePriceId($priceId);

        if ($newPlan && $newPlan->id !== $tenant->getAttribute('plan_id')) {
            $tenant->update(['plan_id' => $newPlan->id]);
        }
    }

    public function syncPlanFromSubscription(Tenant $tenant, object $stripeSubscription): void
    {
        $primaryItem = $this->resolvePrimarySubscriptionItem($tenant, $stripeSubscription);
        $priceId = data_get($primaryItem, 'price.id');

        $this->syncPlanFromPriceId($tenant, is_string($priceId) ? $priceId : null);
    }

    /**
     * Preserva os itens de add-on ativos ao trocar o preço-base da assinatura.
     * Sem esse payload explícito, o swap do Cashier remove os demais preços.
     *
     * @return string|array<string, array{quantity: int}>
     */
    public function buildPlanSwapPrices(Tenant $tenant, string $newPlanPriceId): string|array
    {
        $prices = [$newPlanPriceId => ['quantity' => 1]];
        $addonSubscriptions = app(TenantAddonSubscriptionRepositoryInterface::class)
            ->forTenant($tenant, activeOnly: true);

        foreach ($addonSubscriptions as $addonSubscription) {
            $priceId = $addonSubscription->stripe_price_id;
            if ($priceId === '' || $priceId === $newPlanPriceId || $addonSubscription->quantity < 1) {
                continue;
            }

            $prices[$priceId] = ['quantity' => $addonSubscription->quantity];
        }

        return count($prices) === 1 ? $newPlanPriceId : $prices;
    }

    public function subscriptionHasPlanPrice(
        Tenant $tenant,
        Subscription $subscription,
        string $priceId,
    ): bool {
        $subscriptionPrice = $subscription->getAttribute('stripe_price');
        if (is_string($subscriptionPrice) && $subscriptionPrice === $priceId) {
            return true;
        }

        $plan = $tenant->getRelationValue('plan');
        if (! $plan instanceof Plan) {
            $plan = $tenant->plan;
        }

        return $plan instanceof Plan && $plan->stripe_price_id === $priceId;
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
        $primaryItem = $this->resolvePrimarySubscriptionItem($tenant, $stripeSubscription);

        $subscription = $tenant->subscriptions()->firstOrNew([
            'stripe_id' => $stripeSubscription->id,
        ]);

        if (! $subscription instanceof Subscription) {
            throw new \UnexpectedValueException('Registro local de assinatura inválido.');
        }

        $trialEndsAt = $stripeSubscription->trial_end
            ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
            : null;

        $endsAt = $stripeSubscription->cancel_at
            ? Carbon::createFromTimestamp($stripeSubscription->cancel_at)
            : null;

        $subscription->fill([
            'type' => 'default',
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => data_get($primaryItem, 'price.id'),
            'quantity' => (int) data_get($primaryItem, 'quantity', 1),
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => $endsAt,
        ]);
        $subscription->save();

        foreach ($subscriptionItems as $item) {
            $subscription->items()->updateOrCreate([
                'stripe_id' => $item->id,
            ], [
                'stripe_product' => data_get($item, 'price.product'),
                'stripe_price' => data_get($item, 'price.id'),
                'quantity' => (int) data_get($item, 'quantity', 1),
            ]);
        }

        $stripeItemIds = array_values(array_filter(array_map(
            static fn (mixed $item): mixed => is_object($item) ? data_get($item, 'id') : null,
            $subscriptionItems,
        ), static fn (mixed $id): bool => is_string($id) && $id !== ''));

        if ($stripeItemIds === []) {
            $subscription->items()->delete();
        } else {
            $subscription->items()->whereNotIn('stripe_id', $stripeItemIds)->delete();
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

        $this->syncPlanFromSubscription($tenant, $subscription);
        $this->syncSubscription($tenant, $subscription->id);

        $this->applyStripeSubscriptionStatus($tenant, $stripeStatus);

        return [
            'eligible' => in_array($stripeStatus, ['active', 'trialing'], true),
            'source' => 'stripe',
            'stripe_status' => $stripeStatus,
        ];
    }

    private function resolvePrimarySubscriptionItem(Tenant $tenant, object $stripeSubscription): ?object
    {
        $items = data_get($stripeSubscription, 'items.data', []);
        if (! is_iterable($items)) {
            return null;
        }

        $tenant->loadMissing(['plan', 'scheduledPlan']);
        $scheduledPriceId = $tenant->scheduledPlan?->stripe_price_id;
        $currentPriceId = $tenant->plan?->stripe_price_id;
        $candidates = array_values(array_filter([$scheduledPriceId, $currentPriceId]));

        foreach ($candidates as $candidate) {
            foreach ($items as $item) {
                if (is_object($item) && data_get($item, 'price.id') === $candidate) {
                    return $item;
                }
            }
        }

        $planRepository = app(PlanRepositoryInterface::class);
        foreach ($items as $item) {
            $priceId = is_object($item) ? data_get($item, 'price.id') : null;
            if (is_string($priceId) && $planRepository->findByStripePriceId($priceId) instanceof Plan) {
                return $item;
            }
        }

        foreach ($items as $item) {
            if (is_object($item)) {
                return $item;
            }
        }

        return null;
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
     * Retorna a URL hospedada da invoice em aberto para o cliente concluir o pagamento.
     *
     * O Stripe Billing já re-tenta automaticamente invoices charge_automatically (dunning/
     * smart retries). Para o "pagar agora" manual, direcionamos o cliente à página hospedada
     * do Stripe — que cobra o cartão atual, permite trocar o cartão e trata SCA/3DS
     * nativamente — em vez de cobrar via API server-side (invoices->pay) e ter que orquestrar
     * requires_action por conta própria. Retorna null quando não há invoice em aberto.
     */
    public function getOpenInvoicePaymentUrl(Tenant $tenant): ?string
    {
        $tenantStripeId = $tenant->getAttribute('stripe_id');
        if (! is_string($tenantStripeId) || $tenantStripeId === '') {
            return null;
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

                if (($invoice->amount_remaining ?? 0) > 0) {
                    $hostedUrl = $invoice->hosted_invoice_url ?? null;

                    return is_string($hostedUrl) && $hostedUrl !== '' ? $hostedUrl : null;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
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

    /**
     * Resolve timestamp de período da assinatura Stripe.
     *
     * Em versões recentes da API, `current_period_start` / `current_period_end`
     * saíram do objeto Subscription e passaram a viver em cada SubscriptionItem.
     * Mantemos fallback no topo do objeto para contas em API legada.
     *
     * @param  object|array<string, mixed>  $stripeSubscription
     */
    protected function resolveStripePeriodTimestamp(object|array $stripeSubscription, string $field): ?int
    {
        $topLevel = data_get($stripeSubscription, $field);
        if (is_numeric($topLevel)) {
            return (int) $topLevel;
        }

        $itemLevel = data_get($stripeSubscription, "items.data.0.{$field}");
        if (is_numeric($itemLevel)) {
            return (int) $itemLevel;
        }

        return null;
    }

    /**
     * @param  object|array<string, mixed>  $stripeSubscription
     */
    protected function formatStripePeriodIso(object|array $stripeSubscription, string $field): ?string
    {
        $timestamp = $this->resolveStripePeriodTimestamp($stripeSubscription, $field);

        return $timestamp !== null
            ? Carbon::createFromTimestampUTC($timestamp)->toIso8601String()
            : null;
    }

    /**
     * Período de serviço da fatura.
     *
     * Stripe recomenda usar o period dos line items (não o period_start/end da
     * invoice, que reflete a janela de “usage/collection” e costuma deslocar 1 ciclo).
     * Timestamps são normalizados em UTC para evitar virada de dia em America/Sao_Paulo.
     *
     * @param  object|array<string, mixed>  $invoice
     * @return array{0: ?string, 1: ?string}
     */
    protected function resolveInvoicePeriodIso(object|array $invoice): array
    {
        $periodStart = null;
        $periodEnd = null;

        foreach (data_get($invoice, 'lines.data', []) as $line) {
            $start = data_get($line, 'period.start');
            $end = data_get($line, 'period.end');

            if (is_numeric($start)) {
                $start = (int) $start;
                if ($periodStart === null || $start < $periodStart) {
                    $periodStart = $start;
                }
            }

            if (is_numeric($end)) {
                $end = (int) $end;
                if ($periodEnd === null || $end > $periodEnd) {
                    $periodEnd = $end;
                }
            }
        }

        if ($periodStart === null && is_numeric(data_get($invoice, 'period_start'))) {
            $periodStart = (int) data_get($invoice, 'period_start');
        }

        if ($periodEnd === null && is_numeric(data_get($invoice, 'period_end'))) {
            $periodEnd = (int) data_get($invoice, 'period_end');
        }

        return [
            $periodStart !== null
                ? Carbon::createFromTimestampUTC($periodStart)->toIso8601String()
                : null,
            $periodEnd !== null
                ? Carbon::createFromTimestampUTC($periodEnd)->toIso8601String()
                : null,
        ];
    }
}
