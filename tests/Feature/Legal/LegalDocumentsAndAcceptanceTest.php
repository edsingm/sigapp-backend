<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnforceHostAccess;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Central\LegalAcceptance as CentralLegalAcceptance;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Tenant\LegalAcceptance as TenantLegalAcceptance;
use App\Models\Tenant\User;
use App\Services\Auth\TenantPasswordResetService;
use App\Services\Billing\TenantBillingService;
use App\Services\Privacy\LegalDocumentService;
use App\Services\Signup\TenantSignupService;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class LegalDocumentsAndAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_lists_canonical_legal_documents(): void
    {
        $response = $this
            ->withHeader('Host', 'localhost')
            ->getJson('/api/v1/legal/documents');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reacceptance_required', false);

        $keys = collect($response->json('data.documents'))->pluck('key')->all();

        $this->assertSame([
            'signup_usage_contract',
            'privacy_policy',
            'cookies_policy',
            'lgpd',
        ], $keys);

        $usage = collect($response->json('data.documents'))
            ->firstWhere('key', 'signup_usage_contract');

        $this->assertIsArray($usage);
        $this->assertStringEndsWith('/legal/termos-de-uso', (string) $usage['url']);
        $this->assertTrue($usage['requires_acceptance']);
        $this->assertFalse($usage['needs_reacceptance']);
    }

    public function test_signup_rejects_missing_privacy_acceptance(): void
    {
        Plan::query()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 9700,
            'trial_days' => 7,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ]);

        $response = $this
            ->withHeader('Host', 'localhost')
            ->postJson('/api/v1/signup', [
                'plan_slug' => 'pro',
                'organization_name' => 'Nova Broker',
                'slug' => 'nova-broker',
                'admin_name' => 'Nova Admin',
                'admin_email' => 'nova@example.com',
                'admin_password' => 'Password123',
                'accept_usage_contract' => true,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accept_privacy_policy']);

        $this->assertDatabaseCount('legal_acceptances', 0);
    }

    public function test_signup_persists_central_acceptances_and_keeps_legacy_json(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 9700,
            'trial_days' => 7,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ]);

        $result = app(TenantSignupService::class)->createPendingTenant([
            'plan_slug' => 'pro',
            'organization_name' => 'Empresa Legal',
            'slug' => 'empresa-legal',
            'admin_name' => 'Admin Legal',
            'admin_email' => 'admin.legal@example.com',
            'admin_password' => 'Password123',
            'accept_usage_contract' => true,
            'accept_privacy_policy' => true,
        ], $plan, '203.0.113.10', 'Signup Browser');

        $tenant = $result['tenant']->refresh();
        $legacy = app(TenantBillingService::class)->getSignupContractAcceptance($tenant);

        $this->assertSame('signup_usage_contract', $legacy['document_key'] ?? null);
        $this->assertTrue((bool) ($legacy['accepted'] ?? false));

        $this->assertDatabaseCount('legal_acceptances', 2);
        $this->assertDatabaseHas('legal_acceptances', [
            'tenant_id' => $tenant->getKey(),
            'actor_email' => 'admin.legal@example.com',
            'document_key' => 'signup_usage_contract',
            'document_hash' => config('legal.signup_usage_contract.hash'),
            'ip_hash' => hash('sha256', '203.0.113.10'),
        ]);
        $this->assertDatabaseHas('legal_acceptances', [
            'tenant_id' => $tenant->getKey(),
            'actor_email' => 'admin.legal@example.com',
            'document_key' => 'privacy_policy',
            'document_hash' => config('legal.privacy_policy.hash'),
        ]);
    }

    public function test_tenant_acceptance_requires_authentication(): void
    {
        $this->bootTenantUser();

        $this->postJson('/api/v1/legal/acceptances', [])
            ->assertUnauthorized();
    }

    public function test_authenticated_tenant_user_records_acceptances_and_clears_reacceptance_gate(): void
    {
        [$tenant, $user] = $this->bootTenantUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/legal/documents')
            ->assertOk()
            ->assertJsonPath('data.reacceptance_required', true);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/legal/acceptances', []);

        $response
            ->assertCreated()
            ->assertJsonPath('data.reacceptance_required', false)
            ->assertJsonPath('data.accepted', [
                'signup_usage_contract',
                'privacy_policy',
            ]);

        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'document_key' => 'signup_usage_contract',
            'document_hash' => config('legal.signup_usage_contract.hash'),
        ]);
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'document_key' => 'privacy_policy',
            'document_hash' => config('legal.privacy_policy.hash'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/legal/documents')
            ->assertOk()
            ->assertJsonPath('data.reacceptance_required', false);

        unset($tenant);
    }

    public function test_invite_reset_requires_legal_acceptance(): void
    {
        $this
            ->withHeader('Host', 'localhost')
            ->postJson('/api/v1/auth/password/reset', [
                'email' => 'invited@test.com',
                'token' => 'token-123',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'tenant_identifier' => 'acme',
                'intent' => 'invite',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accept_legal_documents']);
    }

    public function test_invite_reset_records_tenant_acceptances(): void
    {
        [, $user] = $this->bootTenantUser();

        tenancy()->initialized = false;

        $broker = Password::broker('tenant_users');
        $this->assertInstanceOf(PasswordBroker::class, $broker);
        $token = $broker->createToken($user);

        $status = app(TenantPasswordResetService::class)
            ->resetForCurrentTenant(
                (string) $user->email,
                $token,
                'NewPassword123',
                true,
            );

        $this->assertSame(Password::PASSWORD_RESET, $status);
        $this->assertSame(2, TenantLegalAcceptance::query()->where('user_id', $user->id)->count());
        $this->assertFalse(
            app(LegalDocumentService::class)->catalog((int) $user->id)['reacceptance_required']
        );
    }

    public function test_central_and_tenant_legal_acceptance_factories_persist(): void
    {
        $central = CentralLegalAcceptance::factory()->createOne();
        $this->assertTrue($central->exists);
        $this->assertDatabaseHas('legal_acceptances', ['id' => $central->id]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        $tenantAcceptance = TenantLegalAcceptance::factory()->createOne();
        $this->assertTrue($tenantAcceptance->exists);
        $this->assertDatabaseHas('legal_acceptances', [
            'id' => $tenantAcceptance->id,
            'user_id' => $tenantAcceptance->user_id,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function bootTenantUser(): array
    {
        $this->withoutMiddleware([
            InitializeTenancyFlexible::class,
            AddTenantContextToLogs::class,
            ApiRequestLogger::class,
            CheckSubscriptionStatus::class,
            EnsureTenantContext::class,
            EnsureTenantUser::class,
            EnforceHostAccess::class,
            EnsureCentralContext::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        $tenant = Tenant::query()->create([
            'name' => 'Tenant Legal',
            'slug' => 'tenant-legal',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $tenant->domains()->create(['domain' => 'tenant-legal']);

        $user = User::query()->create([
            'name' => 'Legal User',
            'email' => 'legal-user@test.com',
            'password' => Hash::make('password123'),
        ]);

        tenancy()->tenant = $tenant;
        tenancy()->initialized = true;

        return [$tenant, $user];
    }

    protected function tearDown(): void
    {
        tenancy()->tenant = null;
        tenancy()->initialized = false;

        parent::tearDown();
    }
}
