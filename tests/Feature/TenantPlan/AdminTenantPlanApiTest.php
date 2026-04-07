<?php

namespace Tests\Feature\TenantPlan;

use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTenantPlanApiTest extends TestCase
{
    use RefreshDatabase;

    private Plan $planLow;

    private Plan $planHigh;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planLow = Plan::create([
            'name' => 'Low Plan',
            'slug' => 'low-plan',
            'price' => 10000,
            'sort_order' => 1,
            'is_active' => true,
            'trial_days' => 7,
        ]);

        $this->planHigh = Plan::create([
            'name' => 'High Plan',
            'slug' => 'high-plan',
            'price' => 50000,
            'sort_order' => 10,
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

    private function actingAsAdmin(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@sigapp.test',
            'password' => 'password',
            'is_admin' => true,
        ]);

        Sanctum::actingAs($user, ['admin']);
    }

    private function adminJson(string $method, string $uri, array $data = []): TestResponse
    {
        return $this
            ->withHeader('Host', 'localhost')
            ->{$method.'Json'}($uri, $data);
    }

    // ─── Auth Guard ───────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $tenantId = $this->tenant->id;
        $response = $this->withHeader('Host', 'localhost')
            ->postJson("/api/v1/admin/tenants/{$tenantId}/plan", ['plan_id' => $this->planLow->id]);

        $response->assertUnauthorized();
    }

    // ─── Assign Plan ──────────────────────────────────────────────────────────

    public function test_it_assigns_a_plan_to_tenant(): void
    {
        $this->actingAsAdmin();

        $response = $this->adminJson(
            'post',
            "/api/v1/admin/tenants/{$this->tenant->id}/plan",
            ['plan_id' => $this->planLow->id]
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'plan_id' => $this->planLow->id]);
    }

    public function test_assign_plan_fails_for_non_existent_plan(): void
    {
        $this->actingAsAdmin();

        $response = $this->adminJson(
            'post',
            "/api/v1/admin/tenants/{$this->tenant->id}/plan",
            ['plan_id' => 99999]
        );

        $response->assertUnprocessable(); // validation: exists:plans,id
    }

    // ─── Upgrade Plan ─────────────────────────────────────────────────────────

    public function test_it_upgrades_tenant_plan(): void
    {
        $this->actingAsAdmin();
        $this->tenant->update(['plan_id' => $this->planLow->id]);

        $response = $this->adminJson(
            'put',
            "/api/v1/admin/tenants/{$this->tenant->id}/plan/upgrade",
            ['plan_id' => $this->planHigh->id]
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'plan_id' => $this->planHigh->id]);
    }

    public function test_upgrade_fails_when_new_plan_has_lower_order(): void
    {
        $this->actingAsAdmin();
        $this->tenant->update(['plan_id' => $this->planHigh->id]);

        $response = $this->adminJson(
            'put',
            "/api/v1/admin/tenants/{$this->tenant->id}/plan/upgrade",
            ['plan_id' => $this->planLow->id]
        );

        $response->assertStatus(422);
    }

    // ─── Downgrade Plan ───────────────────────────────────────────────────────

    public function test_it_downgrades_tenant_plan(): void
    {
        $this->actingAsAdmin();
        $this->tenant->update(['plan_id' => $this->planHigh->id]);

        $response = $this->adminJson(
            'put',
            "/api/v1/admin/tenants/{$this->tenant->id}/plan/downgrade",
            ['plan_id' => $this->planLow->id]
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'plan_id' => $this->planLow->id]);
    }

    public function test_downgrade_fails_when_new_plan_has_higher_order(): void
    {
        $this->actingAsAdmin();
        $this->tenant->update(['plan_id' => $this->planLow->id]);

        $response = $this->adminJson(
            'put',
            "/api/v1/admin/tenants/{$this->tenant->id}/plan/downgrade",
            ['plan_id' => $this->planHigh->id]
        );

        $response->assertStatus(422);
    }

    // ─── Extra Entitlements ───────────────────────────────────────────────────

    public function test_it_lists_empty_extra_entitlements(): void
    {
        $this->actingAsAdmin();

        $response = $this->adminJson('get', "/api/v1/admin/tenants/{$this->tenant->id}/entitlements");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame([], $response->json('data'));
    }

    public function test_it_adds_extra_entitlement_to_tenant(): void
    {
        $this->actingAsAdmin();

        $ent = Entitlement::create([
            'key' => 'extra.users',
            'type' => 'limit',
            'label' => 'Extra Users',
            'default_value' => 0,
        ]);

        $response = $this->adminJson(
            'post',
            "/api/v1/admin/tenants/{$this->tenant->id}/entitlements",
            [
                'entitlement_id' => $ent->id,
                'value' => 100,
                'price' => 9900,
            ]
        );

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.value', 100)
            ->assertJsonPath('data.price', 9900);

        $this->assertDatabaseHas('tenant_entitlements', [
            'tenant_id' => $this->tenant->id,
            'entitlement_id' => $ent->id,
        ]);
    }

    public function test_it_updates_extra_entitlement_price(): void
    {
        $this->actingAsAdmin();

        $ent = Entitlement::create(['key' => 'upd.feat', 'type' => 'feature', 'label' => 'Upd', 'default_value' => false]);

        $this->adminJson(
            'post',
            "/api/v1/admin/tenants/{$this->tenant->id}/entitlements",
            ['entitlement_id' => $ent->id, 'value' => true, 'price' => 1000]
        );

        $response = $this->adminJson(
            'put',
            "/api/v1/admin/tenants/{$this->tenant->id}/entitlements/{$ent->id}",
            ['value' => true, 'price' => 5500]
        );

        $response->assertOk()->assertJsonPath('data.price', 5500);
    }

    public function test_it_removes_extra_entitlement(): void
    {
        $this->actingAsAdmin();

        $ent = Entitlement::create(['key' => 'rm.feat', 'type' => 'feature', 'label' => 'Rm', 'default_value' => false]);

        $this->adminJson(
            'post',
            "/api/v1/admin/tenants/{$this->tenant->id}/entitlements",
            ['entitlement_id' => $ent->id, 'value' => true, 'price' => 1000]
        );

        $response = $this->adminJson(
            'delete',
            "/api/v1/admin/tenants/{$this->tenant->id}/entitlements/{$ent->id}"
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('tenant_entitlements', [
            'tenant_id' => $this->tenant->id,
            'entitlement_id' => $ent->id,
        ]);
    }

    // ─── Response does not expose sensitive fields ────────────────────────────

    public function test_extra_entitlement_response_does_not_expose_tenant_id(): void
    {
        $this->actingAsAdmin();

        $ent = Entitlement::create(['key' => 'safe.feat', 'type' => 'feature', 'label' => 'Safe', 'default_value' => false]);

        $response = $this->adminJson(
            'post',
            "/api/v1/admin/tenants/{$this->tenant->id}/entitlements",
            ['entitlement_id' => $ent->id, 'value' => true, 'price' => 0]
        );

        $response->assertCreated();
        $data = $response->json('data');
        $this->assertArrayNotHasKey('tenant_id', $data);
        $this->assertArrayHasKey('price_formatted', $data);
    }
}
