<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Central;

use App\Models\Central\Subscription;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Stripe\Subscription as StripeSubscription;
use Tests\TestCase;

class SubscriptionPendingUpdatePayloadTest extends TestCase
{
    public function test_pending_if_incomplete_strips_tax_rates_from_swap_items(): void
    {
        $subscription = new Subscription;
        $subscription->pendingIfPaymentFails();

        $items = Collection::make([
            [
                'id' => 'si_plan',
                'price' => 'price_pro',
                'quantity' => 1,
                'tax_rates' => null,
            ],
            [
                'id' => 'si_addon',
                'price' => 'price_addon',
                'quantity' => 2,
                'tax_rates' => ['txr_123'],
            ],
        ]);

        $payload = $this->invokeGetSwapOptions($subscription, $items);

        $this->assertSame(
            StripeSubscription::PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE,
            $payload['payment_behavior'],
        );

        foreach ($payload['items'] as $item) {
            $this->assertIsArray($item);
            $this->assertArrayNotHasKey('tax_rates', $item);
        }

        $this->assertSame('price_pro', $payload['items'][0]['price']);
        $this->assertSame('price_addon', $payload['items'][1]['price']);
    }

    public function test_default_swap_keeps_tax_rates(): void
    {
        $subscription = new Subscription;

        $items = Collection::make([
            [
                'id' => 'si_plan',
                'price' => 'price_pro',
                'quantity' => 1,
                'tax_rates' => null,
            ],
        ]);

        $payload = $this->invokeGetSwapOptions($subscription, $items);

        $this->assertArrayHasKey('tax_rates', $payload['items'][0]);
        $this->assertNull($payload['items'][0]['tax_rates']);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function invokeGetSwapOptions(Subscription $subscription, Collection $items): array
    {
        $method = new ReflectionMethod(Subscription::class, 'getSwapOptions');
        $method->setAccessible(true);

        /** @var array<string, mixed> $payload */
        $payload = $method->invoke($subscription, $items, []);

        return $payload;
    }
}
