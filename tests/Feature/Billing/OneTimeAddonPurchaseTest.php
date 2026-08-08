<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Common\BillingAddonType;
use App\Enums\Common\TenantAddonPurchaseStatus;
use App\Models\Central\AiCreditTransaction;
use App\Models\Central\BillingAddon;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;
use App\Services\Billing\AiCreditService;
use App\Services\Billing\TenantAddonPurchaseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OneTimeAddonPurchaseTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tenantIds = [];

    /** @var list<int> */
    private array $addonIds = [];

    /** @var list<int> */
    private array $planIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::setDefaultDriver('array');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        AiCreditTransaction::query()->whereIn('tenant_id', $this->tenantIds)->delete();
        TenantAddonPurchase::query()->whereIn('tenant_id', $this->tenantIds)->delete();
        Tenant::query()->whereIn('id', $this->tenantIds)->delete();
        BillingAddon::query()->whereIn('id', $this->addonIds)->delete();
        Plan::query()->whereIn('id', $this->planIds)->delete();

        parent::tearDown();
    }

    public function test_paid_checkouts_accumulate_ai_credit_and_webhook_replay_is_idempotent(): void
    {
        $tenant = $this->tenant();
        $addon = $this->aiCreditAddon();
        $first = $this->pendingPurchase($tenant, $addon, 'cs_credit_first_'.uniqid());
        $second = $this->pendingPurchase($tenant, $addon, 'cs_credit_second_'.uniqid());
        $service = app(TenantAddonPurchaseService::class);

        $service->completeFromCheckoutSession($this->checkoutSession($tenant, $first));
        $service->completeFromCheckoutSession($this->checkoutSession($tenant, $second));
        $service->completeFromCheckoutSession($this->checkoutSession($tenant, $first));

        $this->assertSame(TenantAddonPurchaseStatus::PAID, $first->fresh()?->status);
        $this->assertSame(TenantAddonPurchaseStatus::PAID, $second->fresh()?->status);
        $this->assertSame(2, AiCreditTransaction::query()->where('tenant_id', $tenant->getKey())->count());
        $this->assertSame(
            10.0,
            round((float) AiCreditTransaction::query()
                ->where('tenant_id', $tenant->getKey())
                ->sum('amount_usd'), 6),
        );

        $credits = app(AiCreditService::class);
        $credits->syncMonthlyConsumption($tenant, 3.0);
        $this->assertSame(7.0, $credits->summary($tenant)['balance_usd']);

        Carbon::setTestNow(now()->addMonthNoOverflow());
        $nextMonth = $credits->summary($tenant);
        $this->assertSame(7.0, $nextMonth['balance_usd']);
        $this->assertSame(0.0, $nextMonth['consumed_this_month_usd']);
    }

    public function test_unpaid_async_checkout_does_not_grant_credit_before_confirmation(): void
    {
        $tenant = $this->tenant();
        $addon = $this->aiCreditAddon();
        $purchase = $this->pendingPurchase($tenant, $addon, 'cs_credit_pending_'.uniqid());
        $session = $this->checkoutSession($tenant, $purchase);
        $session['payment_status'] = 'unpaid';

        $result = app(TenantAddonPurchaseService::class)->completeFromCheckoutSession($session);

        $this->assertNull($result);
        $this->assertSame(TenantAddonPurchaseStatus::PENDING, $purchase->fresh()?->status);
        $this->assertSame(
            0,
            AiCreditTransaction::query()->where('tenant_id', $tenant->getKey())->count(),
        );
    }

    private function tenant(): Tenant
    {
        $plan = Plan::query()->create([
            'name' => 'Plan credit test',
            'slug' => 'plan-credit-'.strtolower(fake()->unique()->lexify('????????')),
            'description' => 'Plan fixture for persistent AI credit tests.',
            'price' => 24700,
            'trial_days' => 0,
            'is_active' => true,
        ]);
        $this->planIds[] = (int) $plan->getKey();

        $tenant = Tenant::query()->create([
            'name' => 'Tenant credit test',
            'slug' => 'tenant-credit-'.strtolower(fake()->unique()->lexify('????????')),
            'status' => Tenant::STATUS_ACTIVE,
            'stripe_id' => 'cus_credit_test',
            'plan_id' => $plan->getKey(),
            'database_created' => false,
            'trial_extended' => false,
            'admin_name' => 'Admin',
            'admin_email' => fake()->unique()->safeEmail(),
            'admin_password' => 'Password123!',
        ]);
        $this->tenantIds[] = (string) $tenant->getKey();

        return $tenant;
    }

    private function aiCreditAddon(): BillingAddon
    {
        $addon = BillingAddon::query()->create([
            'slug' => 'ai-credit-test-'.strtolower(fake()->unique()->lexify('????????')),
            'name' => 'AI credit test',
            'type' => BillingAddonType::LIMIT_PACK,
            'stripe_price_id' => 'price_'.fake()->unique()->regexify('[A-Za-z0-9]{16}'),
            'currency' => 'brl',
            'billing_interval' => 'one_time',
            'definition' => [
                'grants' => [
                    ['key' => 'ai_budget', 'type' => 'limit', 'unit_value' => 5.0],
                ],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->addonIds[] = (int) $addon->getKey();

        return $addon;
    }

    private function pendingPurchase(
        Tenant $tenant,
        BillingAddon $addon,
        string $sessionId,
    ): TenantAddonPurchase {
        return TenantAddonPurchase::query()->create([
            'tenant_id' => $tenant->getKey(),
            'billing_addon_id' => $addon->getKey(),
            'stripe_checkout_session_id' => $sessionId,
            'stripe_price_id' => $addon->stripe_price_id,
            'quantity' => 1,
            'unit_amount' => 3500,
            'currency' => 'brl',
            'status' => TenantAddonPurchaseStatus::PENDING,
            'grant_snapshot' => $addon->definition,
            'expires_at' => now()->addDay(),
        ]);
    }

    /** @return array<string, mixed> */
    private function checkoutSession(Tenant $tenant, TenantAddonPurchase $purchase): array
    {
        return [
            'id' => $purchase->stripe_checkout_session_id,
            'customer' => $tenant->stripe_id,
            'payment_intent' => 'pi_'.$purchase->getKey(),
            'payment_status' => 'paid',
            'amount_total' => 3500,
            'currency' => 'brl',
            'metadata' => [
                'purpose' => TenantAddonPurchaseService::CHECKOUT_PURPOSE,
                'purchase_id' => (string) $purchase->getKey(),
                'tenant_id' => (string) $tenant->getKey(),
                'addon_id' => (string) $purchase->billing_addon_id,
                'price_id' => $purchase->stripe_price_id,
            ],
        ];
    }
}
