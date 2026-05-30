<?php

namespace Tests\Unit\TenantPlan;

use App\Enums\Common\EntitlementType;
use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\TenantPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TenantPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantPlanService $service;

    private Plan $planA;

    private Plan $planB;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TenantPlanService::class);

        $this->planA = Plan::create([
            'name' => 'Plan A',
            'slug' => 'plan-a',
            'price' => 10000,
            'sort_order' => 1,
            'is_active' => true,
            'trial_days' => 7,
        ]);

        $this->planB = Plan::create([
            'name' => 'Plan B',
            'slug' => 'plan-b',
            'price' => 20000,
            'sort_order' => 2,
            'is_active' => true,
            'trial_days' => 7,
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'Admin',
            'admin_email' => 'admin@test.com',
            'admin_password' => 'password',
        ]);
    }

    public function test_it_assigns_a_plan_to_tenant(): void
    {
        $result = $this->service->assignPlan($this->tenantId(), $this->planA->id);

        $this->assertSame($this->planA->id, $result->getAttribute('plan_id'));
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'plan_id' => $this->planA->id]);
    }

    public function test_assign_fails_for_inactive_plan(): void
    {
        $inactive = Plan::create([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'price' => 0,
            'sort_order' => 5,
            'is_active' => false,
            'trial_days' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->assignPlan($this->tenantId(), $inactive->id);
    }

    public function test_upgrade_succeeds_for_higher_sort_order_plan(): void
    {
        $this->tenant->update(['plan_id' => $this->planA->id]);

        $result = $this->service->upgradePlan($this->tenantId(), $this->planB->id);

        $this->assertSame($this->planB->id, $result->getAttribute('plan_id'));
    }

    public function test_upgrade_fails_when_new_plan_has_lower_or_equal_order(): void
    {
        $this->tenant->update(['plan_id' => $this->planB->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->upgradePlan($this->tenantId(), $this->planA->id);
    }

    public function test_downgrade_succeeds_for_lower_sort_order_plan(): void
    {
        $this->tenant->update(['plan_id' => $this->planB->id]);

        $result = $this->service->downgradePlan($this->tenantId(), $this->planA->id);

        $this->assertSame($this->planA->id, $result->getAttribute('plan_id'));
    }

    public function test_downgrade_fails_when_new_plan_has_higher_or_equal_order(): void
    {
        $this->tenant->update(['plan_id' => $this->planA->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->downgradePlan($this->tenantId(), $this->planB->id);
    }

    public function test_it_adds_extra_entitlement_to_tenant(): void
    {
        $ent = Entitlement::create([
            'key' => 'extra.feature',
            'type' => EntitlementType::FEATURE->value,
            'label' => 'Extra Feature',
            'default_value' => false,
        ]);

        $record = $this->service->addExtraEntitlement($this->tenantId(), $ent->id, true, 5000);

        $this->assertSame($this->tenant->id, $record->tenant_id);
        $this->assertSame($ent->id, $record->entitlement_id);
        $this->assertTrue($record->value);
        $this->assertSame(5000, $record->price);
    }

    public function test_it_add_fails_for_missing_entitlement(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->addExtraEntitlement($this->tenantId(), 99999, true, 1000);
    }

    public function test_it_lists_extra_entitlements_with_entitlement_loaded(): void
    {
        $ent = Entitlement::create([
            'key' => 'list.feat',
            'type' => EntitlementType::FEATURE->value,
            'label' => 'List Feat',
            'default_value' => false,
        ]);

        $this->service->addExtraEntitlement($this->tenantId(), $ent->id, true, 1000);

        $list = $this->service->listExtraEntitlements($this->tenantId());

        $this->assertCount(1, $list);
        $this->assertNotNull($list->first()->entitlement);
        $this->assertSame('list.feat', $list->first()->entitlement->key);
    }

    public function test_it_updates_extra_entitlement_price(): void
    {
        $ent = Entitlement::create([
            'key' => 'upd.feat',
            'type' => EntitlementType::FEATURE->value,
            'label' => 'Upd',
            'default_value' => false,
        ]);

        $this->service->addExtraEntitlement($this->tenantId(), $ent->id, true, 1000);

        $updated = $this->service->updateExtraEntitlement($this->tenantId(), $ent->id, ['price' => 9999]);

        $this->assertSame(9999, $updated->price);
    }

    public function test_it_removes_extra_entitlement(): void
    {
        $ent = Entitlement::create([
            'key' => 'rm.feat',
            'type' => EntitlementType::FEATURE->value,
            'label' => 'Rm',
            'default_value' => false,
        ]);

        $this->service->addExtraEntitlement($this->tenantId(), $ent->id, true, 1000);
        $this->service->removeExtraEntitlement($this->tenantId(), $ent->id);

        $this->assertDatabaseMissing('tenant_entitlements', [
            'tenant_id' => $this->tenant->id,
            'entitlement_id' => $ent->id,
        ]);
    }

    private function tenantId(): string
    {
        return (string) $this->tenant->id;
    }
}
