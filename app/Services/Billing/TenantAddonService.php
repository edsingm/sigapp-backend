<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Common\BillingAddonSubscriptionStatus;
use App\Models\Central\BillingAddon;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;
use App\Models\Central\TenantAddonSubscription;
use App\Repositories\Contracts\BillingAddonRepositoryInterface;
use App\Repositories\Contracts\TenantAddonPurchaseRepositoryInterface;
use App\Repositories\Contracts\TenantAddonSubscriptionRepositoryInterface;
use App\Services\PlanMatrixService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Cashier\Subscription;
use Stripe\Exception\ApiErrorException;
use Throwable;

class TenantAddonService
{
    public function __construct(
        private readonly BillingAddonRepositoryInterface $addonRepository,
        private readonly TenantAddonSubscriptionRepositoryInterface $subscriptionRepository,
        private readonly TenantBillingService $billingService,
        private readonly AddonReconciliationService $reconciliationService,
        private readonly BillingAddonPricingService $pricingService,
        private readonly TenantAddonPurchaseRepositoryInterface $purchaseRepository,
        private readonly TenantAddonPurchaseService $purchaseService,
        private readonly AiCreditService $aiCredits,
        private readonly PlanMatrixService $planMatrix,
    ) {}

    /** @return Collection<int, BillingAddon> */
    public function catalog(Tenant $tenant): Collection
    {
        $paidQuantities = $this->purchaseRepository->paidQuantitiesForTenant($tenant);
        $creditSummary = $this->aiCredits->summary($tenant);

        return $this->addonRepository->all(activeOnly: true)
            ->each(function (BillingAddon $addon) use ($paidQuantities, $creditSummary): void {
                $this->pricingService->hydrate($addon);
                $addon->setAttribute('purchased_quantity', $paidQuantities[(int) $addon->getKey()] ?? 0);

                if ($this->aiCredits->aiBudgetGrant($addon->definition) > 0) {
                    $addon->setAttribute('ai_credit_summary', $creditSummary);
                }
            });
    }

    /** @return Collection<int, TenantAddonSubscription> */
    public function mine(Tenant $tenant): Collection
    {
        return $this->subscriptionRepository->forTenant($tenant)
            ->each(function (TenantAddonSubscription $subscription): void {
                if ($subscription->addon instanceof BillingAddon) {
                    $this->pricingService->hydrate($subscription->addon);
                }
            });
    }

    public function purchase(
        Tenant $tenant,
        string $addonSlug,
        int $quantity,
    ): TenantAddonSubscription|TenantAddonPurchase {
        $addon = $this->findPurchasableAddon($addonSlug);

        if ($quantity < 1 || $quantity > 100) {
            throw new InvalidArgumentException('A quantidade deve estar entre 1 e 100.');
        }

        $this->activeSubscription($tenant);
        $price = $this->pricingService->details($addon);

        if ($price['price_type'] === 'one_time') {
            $this->ensureOneTimeEligibility($tenant, $addon);

            return $this->purchaseService->createCheckout($tenant, $addon, $quantity, $price);
        }

        return Cache::lock("tenant-addon:{$tenant->getKey()}:{$addon->getKey()}", 60)
            ->block(10, function () use ($tenant, $addon, $quantity): TenantAddonSubscription {
                $subscription = $this->activeSubscription($tenant);
                $priceId = (string) $addon->stripe_price_id;

                if ($subscription->hasPrice($priceId)) {
                    $subscription->findItemOrFail($priceId)
                        ->pendingIfPaymentFails()
                        ->alwaysInvoice()
                        ->updateQuantity($quantity);
                } else {
                    $subscription
                        ->pendingIfPaymentFails()
                        ->alwaysInvoice()
                        ->addPrice($priceId, $quantity);
                }

                $this->reconcileCurrentSubscription($tenant, $subscription);

                $record = $this->subscriptionRepository->forTenant($tenant, activeOnly: true)
                    ->firstWhere('billing_addon_id', $addon->getKey());

                if (! $record instanceof TenantAddonSubscription) {
                    throw new InvalidArgumentException('O add-on foi aceito pelo Stripe, mas ainda não foi reconciliado.');
                }

                return $record;
            });
    }

    public function updateQuantity(Tenant $tenant, int $id, int $quantity): TenantAddonSubscription
    {
        if ($quantity < 1 || $quantity > 100) {
            throw new InvalidArgumentException('A quantidade deve estar entre 1 e 100.');
        }

        $record = $this->subscriptionRepository->findForTenant($tenant, $id);
        if ($record === null || ! $record->addon instanceof BillingAddon) {
            throw new InvalidArgumentException('Assinatura de add-on não encontrada.');
        }

        if ($record->status === BillingAddonSubscriptionStatus::CANCELED || ! $record->grantsAccess()) {
            throw new InvalidArgumentException('Não é possível alterar a quantidade de um add-on cancelado ou sem acesso ativo.');
        }

        $this->ensurePurchasablePrice($record->addon);

        $subscription = $this->refreshLocalSubscriptionItems($tenant, $this->activeSubscription($tenant));
        $priceId = $this->resolvedItemPriceId($record);

        if (! $subscription->hasPrice($priceId)) {
            throw new InvalidArgumentException('O item do add-on não está presente na assinatura Stripe.');
        }

        Cache::lock("tenant-addon:{$tenant->getKey()}:{$record->billing_addon_id}", 60)
            ->block(10, function () use ($subscription, $priceId, $quantity): void {
                $subscription->findItemOrFail($priceId)
                    ->pendingIfPaymentFails()
                    ->alwaysInvoice()
                    ->updateQuantity($quantity);
            });

        $this->reconcileCurrentSubscription($tenant, $subscription);

        return $this->subscriptionRepository->findForTenant($tenant, $id)
            ?? throw new InvalidArgumentException('Assinatura de add-on não encontrada após reconciliação.');
    }

    public function cancel(Tenant $tenant, int $id): TenantAddonSubscription
    {
        $record = $this->subscriptionRepository->findForTenant($tenant, $id);
        if ($record === null || ! $record->addon instanceof BillingAddon) {
            throw new InvalidArgumentException('Assinatura de add-on não encontrada.');
        }

        // Idempotente: já cancelado e sem acesso.
        if ($record->status === BillingAddonSubscriptionStatus::CANCELED && ! $record->grantsAccess()) {
            return $record;
        }

        $subscription = $tenant->subscription('default');
        if ($subscription instanceof Subscription && $subscription->valid()) {
            $subscription = $this->refreshLocalSubscriptionItems($tenant, $subscription);
            $priceId = $this->resolvedItemPriceId($record);

            Cache::lock("tenant-addon:{$tenant->getKey()}:{$record->billing_addon_id}", 60)
                ->block(10, function () use ($subscription, $priceId, $record): void {
                    $this->removeAddonItemFromStripe($subscription, $priceId, $record);
                });

            $this->reconcileCurrentSubscription($tenant, $subscription);
        } else {
            $this->removeAddonItemWhenSubscriptionUnavailable($record);
            $this->subscriptionRepository->cancel($record);
            $this->reconcileByStoredSubscriptionId($tenant, $record);
        }

        $updated = $this->subscriptionRepository->findForTenant($tenant, $id)
            ?? throw new InvalidArgumentException('Assinatura de add-on não encontrada após cancelamento.');

        if ($updated->grantsAccess()) {
            throw new InvalidArgumentException(
                'Não foi possível cancelar o add-on no Stripe. O acesso permanece ativo; tente reconciliar a assinatura.'
            );
        }

        if ($updated->status !== BillingAddonSubscriptionStatus::CANCELED) {
            return $this->subscriptionRepository->cancel($updated);
        }

        return $updated;
    }

    private function findPurchasableAddon(string $slug): BillingAddon
    {
        $addon = $this->addonRepository->findBySlug($slug);

        if ($addon === null || ! $addon->isPurchasable()) {
            throw new InvalidArgumentException('Add-on não encontrado ou indisponível para compra.');
        }

        $this->ensurePurchasablePrice($addon);

        return $addon;
    }

    private function ensurePurchasablePrice(BillingAddon $addon): void
    {
        $price = $this->pricingService->details($addon);

        if (! $price['is_purchasable']) {
            throw new InvalidArgumentException('O preço do add-on está indisponível ou não possui cobrança compatível.');
        }
    }

    private function ensureOneTimeEligibility(Tenant $tenant, BillingAddon $addon): void
    {
        if ($this->aiCredits->aiBudgetGrant($addon->definition) <= 0) {
            return;
        }

        $planBudget = (float) $this->planMatrix->getLimitForTenant($tenant, 'ai_budget', 0);
        if ($planBudget <= 0) {
            throw new InvalidArgumentException(
                'Créditos avulsos de IA só podem complementar um plano que já possua orçamento mensal de IA.'
            );
        }
    }

    private function activeSubscription(Tenant $tenant): Subscription
    {
        $subscription = $tenant->subscription('default');

        if (! $subscription instanceof Subscription || ! $subscription->valid()) {
            throw new InvalidArgumentException('O tenant não possui uma assinatura ativa para contratar add-ons.');
        }

        if ($subscription->onTrial() || $subscription->getAttribute('stripe_status') === 'trialing') {
            throw new InvalidArgumentException(
                'Add-ons só podem ser contratados ou alterados após o fim do período de teste do plano.'
            );
        }

        return $subscription;
    }

    /**
     * Price do item contratado (espelho local), com fallback seguro ao catálogo.
     */
    private function resolvedItemPriceId(TenantAddonSubscription $record): string
    {
        $itemPriceId = (string) $record->stripe_price_id;
        if ($itemPriceId !== '') {
            return $itemPriceId;
        }

        $catalogPriceId = (string) ($record->addon?->stripe_price_id ?? '');
        if ($catalogPriceId !== '') {
            return $catalogPriceId;
        }

        throw new InvalidArgumentException('O add-on não possui um price Stripe associado.');
    }

    /**
     * Sincroniza subscription_items do Cashier com o Stripe antes de hasPrice/removePrice.
     */
    private function refreshLocalSubscriptionItems(Tenant $tenant, Subscription $subscription): Subscription
    {
        $stripeSubscriptionId = (string) $subscription->getAttribute('stripe_id');
        if ($stripeSubscriptionId === '') {
            return $subscription;
        }

        $this->billingService->syncSubscription($tenant, $stripeSubscriptionId);

        $refreshed = $tenant->subscription('default');
        if (! $refreshed instanceof Subscription) {
            throw new InvalidArgumentException('Assinatura local não encontrada após sincronização com o Stripe.');
        }

        return $refreshed->load('items');
    }

    /**
     * Remove o item do add-on com crédito proporcional (proration).
     */
    private function removeAddonItemFromStripe(
        Subscription $subscription,
        string $priceId,
        TenantAddonSubscription $record,
    ): void {
        if ($subscription->hasPrice($priceId)) {
            $subscription->prorate()->removePrice($priceId);

            return;
        }

        $itemId = (string) $record->stripe_subscription_item_id;
        if ($itemId === '') {
            throw new InvalidArgumentException('O item do add-on não está presente na assinatura Stripe.');
        }

        try {
            $this->billingService->deleteSubscriptionItem($itemId, prorate: true);
        } catch (ApiErrorException $exception) {
            if ($this->isAlreadyDeletedStripeItem($exception)) {
                return;
            }

            throw new InvalidArgumentException(
                'Não foi possível remover o item do add-on no Stripe: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private function removeAddonItemWhenSubscriptionUnavailable(TenantAddonSubscription $record): void
    {
        $itemId = (string) $record->stripe_subscription_item_id;
        if ($itemId === '') {
            return;
        }

        try {
            $this->billingService->deleteSubscriptionItem($itemId, prorate: true);
        } catch (ApiErrorException $exception) {
            if ($this->isAlreadyDeletedStripeItem($exception)) {
                return;
            }

            Log::warning('Falha ao remover item de add-on no Stripe com assinatura local inválida.', [
                'tenant_id' => $record->tenant_id,
                'addon_subscription_id' => $record->getKey(),
                'stripe_subscription_item_id' => $itemId,
                'error' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Erro inesperado ao remover item de add-on no Stripe.', [
                'tenant_id' => $record->tenant_id,
                'addon_subscription_id' => $record->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function reconcileByStoredSubscriptionId(Tenant $tenant, TenantAddonSubscription $record): void
    {
        $stripeSubscriptionId = (string) $record->stripe_subscription_id;
        if ($stripeSubscriptionId === '') {
            return;
        }

        try {
            $stripeSubscription = $this->billingService->retrieveSubscription($stripeSubscriptionId);
            $this->reconciliationService->reconcile($tenant, $stripeSubscription);
        } catch (Throwable $exception) {
            Log::info('Reconciliação pós-cancelamento de add-on ignorada (assinatura Stripe indisponível).', [
                'tenant_id' => $tenant->getKey(),
                'stripe_subscription_id' => $stripeSubscriptionId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function isAlreadyDeletedStripeItem(ApiErrorException $exception): bool
    {
        $code = $exception->getStripeCode();
        $message = strtolower($exception->getMessage());

        return $code === 'resource_missing'
            || str_contains($message, 'no such subscription item')
            || str_contains($message, 'resource_missing');
    }

    private function reconcileCurrentSubscription(Tenant $tenant, Subscription $subscription): void
    {
        $stripeSubscriptionId = (string) $subscription->getAttribute('stripe_id');
        if ($stripeSubscriptionId === '') {
            throw new InvalidArgumentException('A assinatura Stripe não possui identificador válido.');
        }

        $stripeSubscription = $this->billingService->retrieveSubscription($stripeSubscriptionId);
        $this->reconciliationService->reconcile($tenant, $stripeSubscription);
    }
}
