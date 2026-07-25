<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\DTOs\RequestContext;
use App\Models\Central\CentralLoginBrokerSession;
use App\Models\Central\LoginTransferTicket;
use App\Models\Central\Tenant;
use App\Services\Auth\CentralLoginBrokerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class CentralLoginBrokerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_url_expands_a_bare_domain_with_the_application_domain(): void
    {
        config([
            'app.domain' => 'sigapp.com.br',
            'app.url' => 'https://api.sigapp.com.br',
        ]);

        $tenant = Tenant::create([
            'name' => 'Construtora Halz4',
            'slug' => 'construtora-halz4',
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'Admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'Password123',
        ]);
        $tenant->domains()->create(['domain' => 'construtora-halz4']);

        $service = app(CentralLoginBrokerService::class);
        $method = new ReflectionMethod($service, 'resolveTenantUrl');

        $this->assertSame(
            'https://construtora-halz4.sigapp.com.br',
            $method->invoke($service, $tenant),
        );
    }

    public function test_select_tenant_rejects_a_broker_session_from_a_different_ip(): void
    {
        $session = CentralLoginBrokerSession::create([
            'id' => (string) Str::uuid(),
            'email' => 'broker@example.com',
            'ip_address' => '192.0.2.10',
            'tenant_options' => [
                ['tenant_id' => 'tenant-1'],
            ],
            'expires_at' => now()->addMinutes(5),
        ]);

        $result = app(CentralLoginBrokerService::class)->selectTenant(
            $session->id,
            'tenant-1',
            null,
            new RequestContext(ipAddress: '192.0.2.11'),
        );

        $this->assertNull($result);
        $this->assertNull($session->fresh()?->completed_at);
        $this->assertDatabaseCount((new LoginTransferTicket)->getTable(), 0);
    }

    public function test_select_tenant_accepts_a_broker_session_from_the_same_ip(): void
    {
        $tenant = Tenant::create([
            'name' => 'Construtora Halz4',
            'slug' => 'construtora-halz4',
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'Admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'Password123',
        ]);

        $session = CentralLoginBrokerSession::create([
            'id' => (string) Str::uuid(),
            'email' => 'broker@example.com',
            'ip_address' => '192.0.2.10',
            'tenant_options' => [
                [
                    'tenant_id' => (string) $tenant->id,
                    'tenant_name' => $tenant->name,
                    'tenant_slug' => $tenant->slug,
                    'tenant_url' => 'https://construtora-halz4.sigapp.com.br',
                    'tenant_user_id' => (string) Str::uuid(),
                    'email' => 'broker@example.com',
                ],
            ],
            'expires_at' => now()->addMinutes(5),
        ]);

        $result = app(CentralLoginBrokerService::class)->selectTenant(
            $session->id,
            (string) $tenant->id,
            null,
            new RequestContext(ipAddress: '192.0.2.10'),
        );

        $this->assertSame('redirect', $result['next_action'] ?? null);
        $this->assertNotNull($session->fresh()?->completed_at);
        $this->assertDatabaseCount((new LoginTransferTicket)->getTable(), 1);
    }

    public function test_select_tenant_issues_only_one_ticket_for_the_same_session(): void
    {
        $tenant = Tenant::create([
            'name' => 'Construtora Halz4',
            'slug' => 'construtora-halz4',
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'Admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'Password123',
        ]);
        $session = CentralLoginBrokerSession::create([
            'id' => (string) Str::uuid(),
            'email' => 'broker@example.com',
            'ip_address' => '192.0.2.10',
            'tenant_options' => [[
                'tenant_id' => (string) $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_slug' => $tenant->slug,
                'tenant_url' => 'https://construtora-halz4.sigapp.com.br',
                'tenant_user_id' => (string) Str::uuid(),
                'email' => 'broker@example.com',
            ]],
            'expires_at' => now()->addMinutes(5),
        ]);
        $context = new RequestContext(ipAddress: '192.0.2.10');
        $service = app(CentralLoginBrokerService::class);

        $first = $service->selectTenant($session->id, (string) $tenant->id, null, $context);
        $second = $service->selectTenant($session->id, (string) $tenant->id, null, $context);

        $this->assertSame('redirect', $first['next_action'] ?? null);
        $this->assertNull($second);
        $this->assertDatabaseCount((new LoginTransferTicket)->getTable(), 1);
    }
}
