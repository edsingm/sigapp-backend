<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Central\BillingAddon;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonSubscription;
use App\Repositories\Contracts\BillingAddonRepositoryInterface;
use App\Repositories\Contracts\TenantAddonSubscriptionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Laravel\Cashier\Subscription;

class TenantAddonService
{
    public function __construct(
        private readonly BillingAddonRepositoryInterface $addonRepository,
        private readonly TenantAddonSubscriptionRepositoryInterface $subscriptionRepository,
        private readonly TenantBillingService $billingService,
        private readonly AddonReconciliationService $reconciliationService,
    ) {}

    /** @return Collection<int, BillingAddon> */
    public function catalog(): Collection
    {
        return $this->addonRepository->all(activeOnly: true)
            ->filter(static fn (BillingAddon $addon): bool => $addon->isPurchasable())
            ->values();
    }

    /** @return Collection<int, TenantAddonSubscription> */
    public function mine(Tenant $tenant): Collection
    {
        return $this->subscriptionRepository->forTenant($tenant);
    }

    public function purchase(Tenant $tenant, string $addonSlug, int $quantity): TenantAddonSubscription
    {
        $addon = $this->findPurchasableAddon($addonSlug);

        if ($quantity < 1 || $quantity > 100) {
            throw new InvalidArgumentException('A quantidade deve estar entre 1 e 100.');
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

        $subscription = $this->activeSubscription($tenant);
        $priceId = (string) $record->addon->stripe_price_id;

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

        $subscription = $tenant->subscription('default');
        if ($subscription instanceof Subscription && $subscription->valid()) {
            $priceId = (string) $record->addon->stripe_price_id;

            Cache::lock("tenant-addon:{$tenant->getKey()}:{$record->billing_addon_id}", 60)
                ->block(10, function () use ($subscription, $priceId): void {
                    if ($subscription->hasPrice($priceId)) {
                        $subscription->noProrate()->removePrice($priceId);
                    }
                });

            $this->reconcileCurrentSubscription($tenant, $subscription);
        } else {
            $this->subscriptionRepository->cancel($record);
        }

        return $this->subscriptionRepository->findForTenant($tenant, $id)
            ?? throw new InvalidArgumentException('Assinatura de add-on não encontrada após cancelamento.');
    }

    private function findPurchasableAddon(string $slug): BillingAddon
    {
        $addon = $this->addonRepository->findBySlug($slug);

        if ($addon === null || ! $addon->isPurchasable()) {
            throw new InvalidArgumentException('Add-on não encontrado ou indisponível para compra.');
        }

        return $addon;
    }

    private function activeSubscription(Tenant $tenant): Subscription
    {
        $subscription = $tenant->subscription('default');

        if (! $subscription instanceof Subscription || ! $subscription->valid()) {
            throw new InvalidArgumentException('O tenant não possui uma assinatura ativa para contratar add-ons.');
        }

        return $subscription;
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
