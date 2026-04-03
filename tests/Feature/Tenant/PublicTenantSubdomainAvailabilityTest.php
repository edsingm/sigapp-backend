<?php

namespace Tests\Feature\Tenant;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTenantSubdomainAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_subdomain_availability_marks_existing_pending_tenant_as_unavailable(): void
    {
        $tenant = Tenant::create([
            'name' => 'Ed Broker',
            'slug' => 'ed-broker',
            'status' => Tenant::STATUS_PENDING,
            'admin_name' => 'Ed',
            'admin_email' => 'ed@example.com',
            'admin_password' => 'Password123',
        ]);

        $tenant->domains()->create([
            'domain' => 'ed-broker',
        ]);

        $response = $this->getJson('/api/v1/tenant/subdomain-availability/ed-broker');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.normalized_subdomain', 'ed-broker');
    }

    public function test_signup_returns_validation_error_when_requested_slug_already_exists(): void
    {
        Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 9700,
            'trial_days' => 7,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ]);

        $tenant = Tenant::create([
            'name' => 'Ed Broker',
            'slug' => 'ed-broker',
            'status' => Tenant::STATUS_PENDING,
            'admin_name' => 'Ed',
            'admin_email' => 'ed@example.com',
            'admin_password' => 'Password123',
        ]);

        $tenant->domains()->create([
            'domain' => 'ed-broker',
        ]);

        $response = $this
            ->withHeader('Host', 'localhost')
            ->postJson('/api/v1/signup', [
                'plan_slug' => 'pro',
                'organization_name' => 'Nova Broker',
                'slug' => 'ed-broker',
                'admin_name' => 'Nova Admin',
                'admin_email' => 'nova@example.com',
                'admin_password' => 'Password123',
                'accept_usage_contract' => true,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseMissing('tenants', [
            'slug' => 'ed-broker-98gm',
        ]);
    }
}
