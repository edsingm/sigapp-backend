<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\InitializeTenancyFlexible;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class InitializeTenancyFlexibleTest extends TestCase
{
    public function test_exact_central_host_is_not_interpreted_as_a_tenant_slug(): void
    {
        config()->set('tenancy.identification.central_domains', [
            'sigapp.com.br',
            'app.sigapp.com.br',
            'admin.sigapp.com.br',
            'www.sigapp.com.br',
        ]);

        $request = Request::create('https://app.sigapp.com.br/api/v1/auth/login');

        $this->assertNull($this->resolveTenantSlug($request));
    }

    public function test_registered_domain_namespace_still_resolves_a_tenant_slug(): void
    {
        config()->set('tenancy.identification.central_domains', ['sigapp.com.br']);

        $request = Request::create('https://tenant-cadastrado.sigapp.com.br/api/v1/auth/login');

        $this->assertSame('tenant-cadastrado', $this->resolveTenantSlug($request));
    }

    public function test_central_api_host_resolves_mobile_tenant_header(): void
    {
        config()->set('tenancy.identification.central_domains', [
            'sigapp.com.br',
            'api.sigapp.com.br',
        ]);

        $request = Request::create('https://api.sigapp.com.br/api/v1/auth/exchange-ticket');
        $request->headers->set('X-Tenant', 'Construtora-Halz4');

        $this->assertSame('construtora-halz4', $this->resolveTenantSlug($request));
    }

    public function test_tenant_host_takes_precedence_over_mobile_header(): void
    {
        config()->set('tenancy.identification.central_domains', ['sigapp.com.br']);

        $request = Request::create('https://tenant-cadastrado.sigapp.com.br/api/v1/auth/start');
        $request->headers->set('X-Tenant', 'outro-tenant');

        $this->assertSame('tenant-cadastrado', $this->resolveTenantSlug($request));
    }

    public function test_central_api_host_rejects_invalid_mobile_tenant_header(): void
    {
        config()->set('tenancy.identification.central_domains', ['api.sigapp.com.br']);

        $request = Request::create('https://api.sigapp.com.br/api/v1/auth/start');
        $request->headers->set('X-Tenant', '../outro-tenant');

        $this->assertNull($this->resolveTenantSlug($request));
    }

    private function resolveTenantSlug(Request $request): ?string
    {
        $middleware = app(InitializeTenancyFlexible::class);
        $method = new ReflectionMethod($middleware, 'resolveTenantSlug');

        /** @var string|null $tenantSlug */
        $tenantSlug = $method->invoke($middleware, $request);

        return $tenantSlug;
    }
}
