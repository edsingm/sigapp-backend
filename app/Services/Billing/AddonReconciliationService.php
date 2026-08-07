<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Common\BillingAddonSubscriptionStatus;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\BillingAddonRepositoryInterface;
use App\Repositories\Contracts\TenantAddonSubscriptionRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AddonReconciliationService
{
    public function __construct(
        private readonly BillingAddonRepositoryInterface $addonRepository,
        private readonly TenantAddonSubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    /**
     * Reconcilia os add-ons locais com os itens atuais de uma assinatura Stripe.
     *
     * @return array{matched: int, canceled: int, ignored: int}
     */
    public function reconcile(Tenant $tenant, object $stripeSubscription): array
    {
        $subscriptionId = $this->stringValue($stripeSubscription, 'id');
        if ($subscriptionId === null) {
            Log::warning('Assinatura Stripe sem id para reconciliação de add-ons.', [
                'tenant_id' => $tenant->getKey(),
            ]);

            return ['matched' => 0, 'canceled' => 0, 'ignored' => 0];
        }

        $status = BillingAddonSubscriptionStatus::tryFrom(
            $this->stringValue($stripeSubscription, 'status') ?? ''
        ) ?? BillingAddonSubscriptionStatus::INCOMPLETE;
        $cancelAtPeriodEnd = (bool) data_get($stripeSubscription, 'cancel_at_period_end', false);
        $activeItemIds = [];
        $matched = 0;
        $ignored = 0;

        foreach ($this->items($stripeSubscription) as $item) {
            $itemId = $this->stringValue($item, 'id');
            $priceId = $this->stringValue($item, 'price.id');

            if ($itemId === null || $priceId === null) {
                $ignored++;

                continue;
            }

            $addon = $this->addonRepository->findByStripePriceId($priceId);
            if ($addon === null) {
                $ignored++;

                continue;
            }

            $activeItemIds[] = $itemId;
            $quantity = max(0, (int) data_get($item, 'quantity', 1));
            $effectiveQuantity = $status->grantsAccess() ? $quantity : 0;

            $this->subscriptionRepository->upsertFromStripe(
                tenant: $tenant,
                addon: $addon,
                stripeSubscriptionId: $subscriptionId,
                stripeSubscriptionItemId: $itemId,
                stripePriceId: $priceId,
                quantity: $effectiveQuantity,
                status: $status,
                cancelAtPeriodEnd: $cancelAtPeriodEnd,
                currentPeriodStart: $this->timestamp(
                    data_get($item, 'current_period_start')
                        ?? data_get($stripeSubscription, 'current_period_start')
                ),
                currentPeriodEnd: $this->timestamp(
                    data_get($item, 'current_period_end')
                        ?? data_get($stripeSubscription, 'current_period_end')
                ),
            );
            $matched++;
        }

        $canceled = $this->subscriptionRepository->deactivateMissingItems(
            tenant: $tenant,
            stripeSubscriptionId: $subscriptionId,
            activeItemIds: $activeItemIds,
        );

        Log::info('Add-ons Stripe reconciliados.', [
            'tenant_id' => $tenant->getKey(),
            'stripe_subscription_id' => $subscriptionId,
            'matched' => $matched,
            'canceled' => $canceled,
            'ignored' => $ignored,
            'status' => $status->value,
        ]);

        return ['matched' => $matched, 'canceled' => $canceled, 'ignored' => $ignored];
    }

    public function cancelSubscription(Tenant $tenant, string $stripeSubscriptionId): int
    {
        return $this->subscriptionRepository->markCanceled($tenant, $stripeSubscriptionId);
    }

    /** @return list<object> */
    private function items(object $stripeSubscription): array
    {
        $items = data_get($stripeSubscription, 'items.data', []);

        if (! is_iterable($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (is_object($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    private function stringValue(object $source, string $path): ?string
    {
        $value = data_get($source, $path);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $value);
    }
}
