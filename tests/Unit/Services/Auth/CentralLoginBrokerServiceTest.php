<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\Central\Tenant;
use App\Services\Auth\CentralLoginBrokerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
