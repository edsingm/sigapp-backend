<?php

namespace Tests\Feature\Admin;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CentralTenantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_tenants_with_resources(): void
    {
        $this->actingAsCentralAdmin();
        $plan = $this->makePlan();

        Tenant::create([
            'name' => 'Tenant Alpha',
            'slug' => 'tenant-alpha',
            'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $plan->id,
            'admin_name' => 'Alpha Admin',
            'admin_email' => 'alpha@example.com',
            'admin_password' => 'password123',
        ]);

        Tenant::create([
            'name' => 'Tenant Beta',
            'slug' => 'tenant-beta',
            'status' => Tenant::STATUS_PENDING,
            'admin_name' => 'Beta Admin',
            'admin_email' => 'beta@example.com',
            'admin_password' => 'password123',
        ]);

        $response = $this->adminJson('get', '/api/v1/admin/tenants');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'success',
                'data' => [[
                    'id',
                    'name',
                    'slug',
                    'status',
                    'admin_name',
                    'admin_email',
                    'plan',
                    'on_trial',
                ]],
                'meta',
            ]);

        $this->assertArrayNotHasKey('admin_password', $response->json('data.0'));
        $this->assertArrayNotHasKey('encryption_key', $response->json('data.0'));
    }

    public function test_list_tenants_validates_filters_and_applies_search(): void
    {
        $this->actingAsCentralAdmin();

        Tenant::create([
            'name' => 'Encontrar Este',
            'slug' => 'tenant-find-me',
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'Alpha Admin',
            'admin_email' => 'alpha@example.com',
            'admin_password' => 'password123',
        ]);

        Tenant::create([
            'name' => 'Outro Tenant',
            'slug' => 'tenant-other',
            'status' => Tenant::STATUS_SUSPENDED,
            'admin_name' => 'Other Admin',
            'admin_email' => 'other@example.com',
            'admin_password' => 'password123',
        ]);

        $this->adminJson('get', '/api/v1/admin/tenants?status=broken')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $response = $this->adminJson('get', '/api/v1/admin/tenants?search=find-me&status=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'tenant-find-me');
    }

    public function test_admin_can_show_tenant_detail(): void
    {
        $this->actingAsCentralAdmin();
        $plan = $this->makePlan();
        $tenant = Tenant::create([
            'name' => 'Tenant Detail',
            'slug' => 'tenant-detail',
            'status' => Tenant::STATUS_PENDING,
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addDays(7),
            'admin_name' => 'Detail Admin',
            'admin_email' => 'detail@example.com',
            'admin_password' => 'password123',
        ]);

        $response = $this->adminJson('get', "/api/v1/admin/tenants/{$tenant->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.plan.slug', $plan->slug)
            ->assertJsonPath('data.stats.users_count', 0)
            ->assertJsonPath('data.finance.subscription_status', 'trialing');

        $this->assertArrayNotHasKey('admin_password', $response->json('data'));
    }

    public function test_admin_can_activate_trial_tenant(): void
    {
        $this->actingAsCentralAdmin();
        $tenant = Tenant::create([
            'name' => 'Trial Tenant',
            'slug' => 'trial-tenant',
            'status' => Tenant::STATUS_PENDING,
            'trial_ends_at' => now()->addDays(5),
            'admin_name' => 'Trial Admin',
            'admin_email' => 'trial@example.com',
            'admin_password' => 'password123',
        ]);

        $response = $this->adminJson('post', "/api/v1/admin/tenants/{$tenant->id}/activate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Tenant::STATUS_ACTIVE);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => Tenant::STATUS_ACTIVE,
        ]);
    }

    public function test_admin_can_suspend_tenant(): void
    {
        $this->actingAsCentralAdmin();
        $tenant = Tenant::create([
            'name' => 'Suspend Tenant',
            'slug' => 'suspend-tenant',
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'Suspend Admin',
            'admin_email' => 'suspend@example.com',
            'admin_password' => 'password123',
        ]);

        $response = $this->adminJson('post', "/api/v1/admin/tenants/{$tenant->id}/suspend");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Tenant::STATUS_SUSPENDED);
    }

    public function test_non_admin_cannot_access_tenant_admin_endpoints(): void
    {
        $user = $this->makeUser(['is_admin' => false]);
        Sanctum::actingAs($user, ['admin']);

        $tenant = Tenant::create([
            'name' => 'Blocked Tenant',
            'slug' => 'blocked-tenant',
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'Blocked Admin',
            'admin_email' => 'blocked@example.com',
            'admin_password' => 'password123',
        ]);

        $this->adminJson('get', '/api/v1/admin/tenants')->assertForbidden();
        $this->adminJson('get', "/api/v1/admin/tenants/{$tenant->id}")->assertForbidden();
    }

    private function actingAsCentralAdmin(): User
    {
        $user = $this->makeUser(['is_admin' => true]);

        Sanctum::actingAs($user, ['admin']);

        return $user;
    }

    private function makePlan(): Plan
    {
        return Plan::create([
            'name' => 'Plano Admin',
            'slug' => 'plano-admin',
            'price' => 10000,
            'trial_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(array $attributes = []): User
    {
        return User::create([
            'name' => $attributes['name'] ?? 'Admin Central',
            'email' => $attributes['email'] ?? ('user-'.uniqid().'@example.com'),
            'password' => $attributes['password'] ?? Hash::make('password123'),
            'is_admin' => $attributes['is_admin'] ?? true,
        ]);
    }

    private function adminJson(string $method, string $uri, array $payload = [])
    {
        return $this
            ->withHeader('Host', 'localhost')
            ->{$method.'Json'}($uri, $payload);
    }
}
