<?php

namespace Tests\Unit\Services\Billing;

use App\Enums\Common\BillingAddonSubscriptionStatus;
use App\Models\Central\BillingAddon;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonSubscription;
use App\Repositories\Contracts\BillingAddonRepositoryInterface;
use App\Repositories\Contracts\TenantAddonSubscriptionRepositoryInterface;
use App\Services\Billing\AddonReconciliationService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AddonReconciliationServiceTest extends TestCase
{
    public function test_it_reconciles_known_items_and_ignores_unknown_prices(): void
    {
        $addon = BillingAddon::factory()->make([
            'id' => 7,
            'stripe_price_id' => 'price_known',
        ]);
        $tenant = Mockery::mock(Tenant::class);
        $tenant->shouldReceive('getKey')->andReturn('tenant-1');

        /** @var BillingAddonRepositoryInterface&MockInterface $addonRepository */
        $addonRepository = Mockery::mock(BillingAddonRepositoryInterface::class);
        $addonRepository->shouldReceive('findByStripePriceId')
            ->with('price_known')
            ->once()
            ->andReturn($addon);
        $addonRepository->shouldReceive('findByStripePriceId')
            ->with('price_unknown')
            ->once()
            ->andReturnNull();

        /** @var TenantAddonSubscriptionRepositoryInterface&MockInterface $subscriptionRepository */
        $subscriptionRepository = Mockery::mock(TenantAddonSubscriptionRepositoryInterface::class);
        $subscriptionRepository->shouldReceive('upsertFromStripe')
            ->once()
            ->withArgs(static function (...$arguments) use ($addon): bool {
                return $arguments[0]->getKey() === 'tenant-1'
                    && $arguments[1] === $addon
                    && $arguments[2] === 'sub_1'
                    && $arguments[3] === 'si_known'
                    && $arguments[4] === 'price_known'
                    && $arguments[5] === 2;
            })
            ->andReturn(new TenantAddonSubscription);
        $subscriptionRepository->shouldReceive('deactivateMissingItems')
            ->once()
            ->withArgs(static fn (Tenant $actualTenant, string $subscriptionId, array $itemIds): bool => $actualTenant->getKey() === 'tenant-1'
                && $subscriptionId === 'sub_1'
                && $itemIds === ['si_known']
            )
            ->andReturn(1);

        $stripeSubscription = (object) [
            'id' => 'sub_1',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'items' => (object) [
                'data' => [
                    (object) [
                        'id' => 'si_known',
                        'quantity' => 2,
                        'price' => (object) ['id' => 'price_known'],
                    ],
                    (object) [
                        'id' => 'si_unknown',
                        'quantity' => 1,
                        'price' => (object) ['id' => 'price_unknown'],
                    ],
                ],
            ],
        ];

        $result = (new AddonReconciliationService($addonRepository, $subscriptionRepository))
            ->reconcile($tenant, $stripeSubscription);

        $this->assertSame(['matched' => 1, 'canceled' => 1, 'ignored' => 1], $result);
    }

    public function test_it_does_not_grant_access_to_addons_on_a_plan_trial(): void
    {
        $addon = BillingAddon::factory()->make([
            'id' => 8,
            'stripe_price_id' => 'price_trial_addon',
        ]);
        $tenant = Mockery::mock(Tenant::class);
        $tenant->shouldReceive('getKey')->andReturn('tenant-1');

        /** @var BillingAddonRepositoryInterface&MockInterface $addonRepository */
        $addonRepository = Mockery::mock(BillingAddonRepositoryInterface::class);
        $addonRepository->shouldReceive('findByStripePriceId')
            ->with('price_trial_addon')
            ->once()
            ->andReturn($addon);

        /** @var TenantAddonSubscriptionRepositoryInterface&MockInterface $subscriptionRepository */
        $subscriptionRepository = Mockery::mock(TenantAddonSubscriptionRepositoryInterface::class);
        $subscriptionRepository->shouldReceive('upsertFromStripe')
            ->once()
            ->withArgs(static function (...$arguments): bool {
                return $arguments[5] === 0
                    && $arguments[6] === BillingAddonSubscriptionStatus::INCOMPLETE;
            })
            ->andReturn(new TenantAddonSubscription);
        $subscriptionRepository->shouldReceive('deactivateMissingItems')
            ->once()
            ->andReturn(0);

        $result = (new AddonReconciliationService($addonRepository, $subscriptionRepository))
            ->reconcile($tenant, (object) [
                'id' => 'sub_trial',
                'status' => 'trialing',
                'cancel_at_period_end' => false,
                'items' => (object) [
                    'data' => [
                        (object) [
                            'id' => 'si_trial_addon',
                            'quantity' => 1,
                            'price' => (object) ['id' => 'price_trial_addon'],
                        ],
                    ],
                ],
            ]);

        $this->assertSame(['matched' => 1, 'canceled' => 0, 'ignored' => 0], $result);
    }
}
