<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Common\BillingAddonSubscriptionStatus;
use App\Models\Central\BillingAddon;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonSubscription;
use App\Repositories\Contracts\BillingAddonRepositoryInterface;
use App\Repositories\Contracts\TenantAddonPurchaseRepositoryInterface;
use App\Repositories\Contracts\TenantAddonSubscriptionRepositoryInterface;
use App\Services\Billing\AddonReconciliationService;
use App\Services\Billing\AiCreditService;
use App\Services\Billing\BillingAddonPricingService;
use App\Services\Billing\TenantAddonPurchaseService;
use App\Services\Billing\TenantAddonService;
use App\Services\Billing\TenantBillingService;
use App\Services\PlanMatrixService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Laravel\Cashier\Subscription;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TenantAddonServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::setDefaultDriver('array');
    }

    public function test_cancel_is_idempotent_when_already_canceled(): void
    {
        $tenant = $this->tenantMock('tenant-1');
        $addon = $this->addon(99, 'price_catalog');
        $record = $this->subscriptionRecord(
            id: 10,
            tenantId: 'tenant-1',
            addon: $addon,
            itemPriceId: 'price_item',
            status: BillingAddonSubscriptionStatus::CANCELED,
            quantity: 0,
        );

        $subscriptionRepository = $this->subscriptionRepositoryMock();
        $subscriptionRepository->shouldReceive('findForTenant')
            ->once()
            ->with($tenant, 10)
            ->andReturn($record);

        /** @var TenantBillingService&MockInterface $billingService */
        $billingService = Mockery::mock(TenantBillingService::class);
        $billingService->shouldNotReceive('syncSubscription');
        $billingService->shouldNotReceive('deleteSubscriptionItem');

        $service = $this->service(
            subscriptionRepository: $subscriptionRepository,
            billingService: $billingService,
        );

        $result = $service->cancel($tenant, 10);

        $this->assertSame($record, $result);
        $this->assertFalse($result->grantsAccess());
    }

    public function test_cancel_uses_item_price_id_not_catalog_price(): void
    {
        $tenant = $this->tenantMock('tenant-1');
        $addon = $this->addon(7, 'price_catalog_new');
        $record = $this->subscriptionRecord(
            id: 22,
            tenantId: 'tenant-1',
            addon: $addon,
            itemPriceId: 'price_item_original',
            status: BillingAddonSubscriptionStatus::ACTIVE,
            quantity: 2,
            itemId: 'si_addon_22',
        );
        $canceled = $this->subscriptionRecord(
            id: 22,
            tenantId: 'tenant-1',
            addon: $addon,
            itemPriceId: 'price_item_original',
            status: BillingAddonSubscriptionStatus::CANCELED,
            quantity: 0,
            itemId: 'si_addon_22',
        );

        /** @var Subscription&MockInterface $subscription */
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('getAttribute')->with('stripe_id')->andReturn('sub_1');
        $subscription->shouldReceive('valid')->andReturn(true);
        $subscription->shouldReceive('load')->with('items')->andReturnSelf();
        $subscription->shouldReceive('hasPrice')->with('price_item_original')->once()->andReturn(true);
        $subscription->shouldReceive('prorate')->once()->andReturnSelf();
        $subscription->shouldReceive('removePrice')->once()->with('price_item_original')->andReturnSelf();
        $subscription->shouldNotReceive('hasPrice')->with('price_catalog_new');

        $tenant->shouldReceive('subscription')->with('default')->andReturn($subscription);

        $subscriptionRepository = $this->subscriptionRepositoryMock();
        $subscriptionRepository->shouldReceive('findForTenant')
            ->with($tenant, 22)
            ->andReturn($record, $canceled);

        /** @var TenantBillingService&MockInterface $billingService */
        $billingService = Mockery::mock(TenantBillingService::class);
        $billingService->shouldReceive('syncSubscription')->once()->with($tenant, 'sub_1');
        $billingService->shouldReceive('retrieveSubscription')
            ->once()
            ->with('sub_1')
            ->andReturn((object) ['id' => 'sub_1', 'status' => 'active', 'items' => (object) ['data' => []]]);

        /** @var AddonReconciliationService&MockInterface $reconciliation */
        $reconciliation = Mockery::mock(AddonReconciliationService::class);
        $reconciliation->shouldReceive('reconcile')->once()->andReturn(['matched' => 0, 'canceled' => 1, 'ignored' => 0]);

        $service = $this->service(
            subscriptionRepository: $subscriptionRepository,
            billingService: $billingService,
            reconciliationService: $reconciliation,
        );

        $result = $service->cancel($tenant, 22);

        $this->assertSame(BillingAddonSubscriptionStatus::CANCELED, $result->status);
        $this->assertFalse($result->grantsAccess());
    }

    public function test_cancel_fails_when_access_remains_after_operation(): void
    {
        $tenant = $this->tenantMock('tenant-1');
        $addon = $this->addon(3, 'price_same');
        $record = $this->subscriptionRecord(
            id: 5,
            tenantId: 'tenant-1',
            addon: $addon,
            itemPriceId: 'price_same',
            status: BillingAddonSubscriptionStatus::ACTIVE,
            quantity: 1,
            itemId: 'si_5',
        );

        /** @var Subscription&MockInterface $subscription */
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('getAttribute')->with('stripe_id')->andReturn('sub_stuck');
        $subscription->shouldReceive('valid')->andReturn(true);
        $subscription->shouldReceive('load')->with('items')->andReturnSelf();
        $subscription->shouldReceive('hasPrice')->with('price_same')->andReturn(false);

        $tenant->shouldReceive('subscription')->with('default')->andReturn($subscription);

        $subscriptionRepository = $this->subscriptionRepositoryMock();
        $subscriptionRepository->shouldReceive('findForTenant')
            ->with($tenant, 5)
            ->andReturn($record, $record);

        /** @var TenantBillingService&MockInterface $billingService */
        $billingService = Mockery::mock(TenantBillingService::class);
        $billingService->shouldReceive('syncSubscription')->once();
        $billingService->shouldReceive('deleteSubscriptionItem')
            ->once()
            ->with('si_5', true)
            ->andReturn((object) ['id' => 'si_5', 'deleted' => true]);
        $billingService->shouldReceive('retrieveSubscription')
            ->once()
            ->andReturn((object) ['id' => 'sub_stuck', 'status' => 'active', 'items' => (object) ['data' => []]]);

        /** @var AddonReconciliationService&MockInterface $reconciliation */
        $reconciliation = Mockery::mock(AddonReconciliationService::class);
        $reconciliation->shouldReceive('reconcile')->once()->andReturn(['matched' => 0, 'canceled' => 0, 'ignored' => 0]);

        $service = $this->service(
            subscriptionRepository: $subscriptionRepository,
            billingService: $billingService,
            reconciliationService: $reconciliation,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Não foi possível cancelar o add-on no Stripe');

        $service->cancel($tenant, 5);
    }

    public function test_update_quantity_uses_item_price_and_rejects_canceled(): void
    {
        $tenant = $this->tenantMock('tenant-1');
        $addon = $this->addon(1, 'price_catalog');
        $canceled = $this->subscriptionRecord(
            id: 9,
            tenantId: 'tenant-1',
            addon: $addon,
            itemPriceId: 'price_item',
            status: BillingAddonSubscriptionStatus::CANCELED,
            quantity: 0,
        );

        $subscriptionRepository = $this->subscriptionRepositoryMock();
        $subscriptionRepository->shouldReceive('findForTenant')->once()->with($tenant, 9)->andReturn($canceled);

        $service = $this->service(subscriptionRepository: $subscriptionRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Não é possível alterar a quantidade de um add-on cancelado');

        $service->updateQuantity($tenant, 9, 2);
    }

    public function test_reconciliation_never_copies_plan_cancel_at_period_end(): void
    {
        /** @var BillingAddon $addon */
        $addon = BillingAddon::factory()->make([
            'id' => 11,
            'stripe_price_id' => 'price_addon',
        ]);
        /** @var Tenant&MockInterface $tenant */
        $tenant = Mockery::mock(Tenant::class);
        $tenant->shouldReceive('getKey')->andReturn('tenant-x');

        /** @var BillingAddonRepositoryInterface&MockInterface $addonRepository */
        $addonRepository = Mockery::mock(BillingAddonRepositoryInterface::class);
        $addonRepository->shouldReceive('findByStripePriceId')->with('price_addon')->andReturn($addon);

        /** @var TenantAddonSubscriptionRepositoryInterface&MockInterface $subscriptionRepository */
        $subscriptionRepository = Mockery::mock(TenantAddonSubscriptionRepositoryInterface::class);
        $subscriptionRepository->shouldReceive('upsertFromStripe')
            ->once()
            ->withArgs(static function (...$arguments): bool {
                // cancelAtPeriodEnd is the 8th argument (index 7)
                return $arguments[7] === false;
            })
            ->andReturn(new TenantAddonSubscription);
        $subscriptionRepository->shouldReceive('deactivateMissingItems')->once()->andReturn(0);

        $result = (new AddonReconciliationService($addonRepository, $subscriptionRepository))
            ->reconcile($tenant, (object) [
                'id' => 'sub_plan_canceling',
                'status' => 'active',
                'cancel_at_period_end' => true,
                'items' => (object) [
                    'data' => [
                        (object) [
                            'id' => 'si_addon',
                            'quantity' => 1,
                            'price' => (object) ['id' => 'price_addon'],
                        ],
                    ],
                ],
            ]);

        $this->assertSame(['matched' => 1, 'canceled' => 0, 'ignored' => 0], $result);
    }

    private function service(
        ?TenantAddonSubscriptionRepositoryInterface $subscriptionRepository = null,
        ?TenantBillingService $billingService = null,
        ?AddonReconciliationService $reconciliationService = null,
    ): TenantAddonService {
        /** @var BillingAddonRepositoryInterface&MockInterface $addonRepository */
        $addonRepository = Mockery::mock(BillingAddonRepositoryInterface::class);
        /** @var BillingAddonPricingService&MockInterface $pricingService */
        $pricingService = Mockery::mock(BillingAddonPricingService::class);
        /** @var TenantAddonPurchaseRepositoryInterface&MockInterface $purchaseRepository */
        $purchaseRepository = Mockery::mock(TenantAddonPurchaseRepositoryInterface::class);
        /** @var TenantAddonPurchaseService&MockInterface $purchaseService */
        $purchaseService = Mockery::mock(TenantAddonPurchaseService::class);
        /** @var AiCreditService&MockInterface $aiCredits */
        $aiCredits = Mockery::mock(AiCreditService::class);
        /** @var PlanMatrixService&MockInterface $planMatrix */
        $planMatrix = Mockery::mock(PlanMatrixService::class);
        /** @var TenantBillingService&MockInterface $resolvedBilling */
        $resolvedBilling = $billingService ?? Mockery::mock(TenantBillingService::class);
        /** @var AddonReconciliationService&MockInterface $resolvedReconciliation */
        $resolvedReconciliation = $reconciliationService ?? Mockery::mock(AddonReconciliationService::class);
        /** @var TenantAddonSubscriptionRepositoryInterface&MockInterface $resolvedSubscriptions */
        $resolvedSubscriptions = $subscriptionRepository ?? $this->subscriptionRepositoryMock();

        return new TenantAddonService(
            addonRepository: $addonRepository,
            subscriptionRepository: $resolvedSubscriptions,
            billingService: $resolvedBilling,
            reconciliationService: $resolvedReconciliation,
            pricingService: $pricingService,
            purchaseRepository: $purchaseRepository,
            purchaseService: $purchaseService,
            aiCredits: $aiCredits,
            planMatrix: $planMatrix,
        );
    }

    /** @return TenantAddonSubscriptionRepositoryInterface&MockInterface */
    private function subscriptionRepositoryMock(): TenantAddonSubscriptionRepositoryInterface&MockInterface
    {
        /** @var TenantAddonSubscriptionRepositoryInterface&MockInterface $mock */
        $mock = Mockery::mock(TenantAddonSubscriptionRepositoryInterface::class);
        $mock->shouldReceive('forTenant')->byDefault()->andReturn(new Collection);

        return $mock;
    }

    /** @return Tenant&MockInterface */
    private function tenantMock(string $id): Tenant&MockInterface
    {
        /** @var Tenant&MockInterface $tenant */
        $tenant = Mockery::mock(Tenant::class);
        $tenant->shouldReceive('getKey')->andReturn($id);

        return $tenant;
    }

    private function addon(int $id, string $catalogPriceId): BillingAddon
    {
        /** @var BillingAddon $addon */
        $addon = BillingAddon::factory()->make([
            'id' => $id,
            'stripe_price_id' => $catalogPriceId,
            'slug' => 'storage-10gb',
            'is_active' => true,
        ]);

        return $addon;
    }

    private function subscriptionRecord(
        int $id,
        string $tenantId,
        BillingAddon $addon,
        string $itemPriceId,
        BillingAddonSubscriptionStatus $status,
        int $quantity,
        string $itemId = 'si_test',
    ): TenantAddonSubscription {
        $record = new TenantAddonSubscription;
        $record->forceFill([
            'id' => $id,
            'tenant_id' => $tenantId,
            'billing_addon_id' => $addon->getKey(),
            'stripe_subscription_id' => 'sub_1',
            'stripe_subscription_item_id' => $itemId,
            'stripe_price_id' => $itemPriceId,
            'quantity' => $quantity,
            'status' => $status,
            'cancel_at_period_end' => false,
        ]);
        $record->setRelation('addon', $addon);
        $record->syncOriginal();

        return $record;
    }
}
