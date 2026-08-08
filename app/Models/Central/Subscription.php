<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription as CashierSubscription;
use Stripe\Subscription as StripeSubscription;

/**
 * Extensão do Subscription do Cashier para compatibilidade com pending updates.
 *
 * O Cashier sempre anexa `items[*].tax_rates` no payload de swap (mesmo quando null).
 * Com `payment_behavior=pending_if_incomplete` o Stripe rejeita esse atributo:
 *
 * @see https://docs.stripe.com/billing/subscriptions/pending-updates-reference#supported-attributes
 */
class Subscription extends CashierSubscription
{
    /**
     * @param  Collection<string|int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function getSwapOptions(Collection $items, array $options = []): array
    {
        $payload = parent::getSwapOptions($items, $options);

        if (($payload['payment_behavior'] ?? null) !== StripeSubscription::PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE) {
            return $payload;
        }

        if (! isset($payload['items']) || ! is_array($payload['items'])) {
            return $payload;
        }

        $payload['items'] = array_map(
            static function (mixed $item): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                unset($item['tax_rates']);

                return $item;
            },
            $payload['items'],
        );

        return $payload;
    }
}
