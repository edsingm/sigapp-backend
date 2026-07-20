<?php

namespace Tests\Feature\Tenant;

use App\Exceptions\SignupSlugReservedException;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Signup\TenantSignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicTenantSubdomainAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('reservedSubdomainProvider')]
    public function test_structural_subdomains_are_not_available_for_tenant_signup(string $subdomain): void
    {
        config()->set('tenancy.identification.central_domains', [
            'sigapp.com.br',
            'app.sigapp.com.br',
            'admin.sigapp.com.br',
            'www.sigapp.com.br',
            'localhost',
        ]);
        config()->set('tenancy.identification.reserved_subdomains', ['smtp']);

        $this->getJson("/api/v1/tenant/subdomain-availability/{$subdomain}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('message', language()->t('SUBDOMAIN_UNAVAILABLE'));
    }

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

    #[DataProvider('serverSideReservedSubdomainProvider')]
    public function test_signup_service_rejects_structural_subdomains_when_the_frontend_is_bypassed(string $subdomain): void
    {
        config()->set('tenancy.identification.central_domains', [
            'sigapp.com.br',
            'app.sigapp.com.br',
            'admin.sigapp.com.br',
            'www.sigapp.com.br',
            'localhost',
        ]);
        config()->set('tenancy.identification.reserved_subdomains', ['smtp']);

        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 9700,
            'trial_days' => 7,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ]);

        try {
            app(TenantSignupService::class)->createPendingTenant([
                'plan_slug' => 'pro',
                'organization_name' => 'Empresa Estrutural',
                'slug' => $subdomain,
                'admin_name' => 'Admin Estrutural',
                'admin_email' => "admin-{$subdomain}@example.com",
                'admin_password' => 'Password123',
                'accept_usage_contract' => true,
            ], $plan);

            $this->fail('Expected the reserved structural subdomain to be rejected.');
        } catch (SignupSlugReservedException $exception) {
            $this->assertSame('SUBDOMAIN_UNAVAILABLE', $exception->messageKey);
        }
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
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CONFLICT');

        $this->assertDatabaseMissing('tenants', [
            'slug' => 'ed-broker-98gm',
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reservedSubdomainProvider(): array
    {
        return [
            'app' => ['app'],
            'admin' => ['admin'],
            'www' => ['www'],
            'smtp' => ['smtp'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function serverSideReservedSubdomainProvider(): array
    {
        return [
            'central HTTP host' => ['app'],
            'infrastructure host' => ['smtp'],
        ];
    }
}
