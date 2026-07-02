<?php

namespace Tests\Unit\Support;

use App\Models\Central\Tenant;
use App\Support\TenantAppUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAppUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_url_uses_existing_login_route_and_local_subdomain(): void
    {
        config(['app.frontend_url' => 'http://localhost:8080']);

        $tenant = $this->tenantWithDomain('construtora-halz', 'construtora-halz');

        $url = app(TenantAppUrl::class)->resetPasswordUrl($tenant, [
            'token' => 'token-123',
            'email' => 'edsingm@hotmail.com',
            'tenant' => 'construtora-halz',
        ]);

        $this->assertSame(
            'http://construtora-halz.localhost:8080/login/reset-password?token=token-123&email=edsingm%40hotmail.com&tenant=construtora-halz',
            $url
        );
    }

    public function test_reset_password_url_uses_root_domain_when_frontend_url_has_app_subdomain(): void
    {
        config([
            'app.frontend_url' => 'https://app.sigapp.com.br',
            'tenancy.identification.central_domains' => ['sigapp.com.br', 'localhost', '127.0.0.1'],
        ]);

        $tenant = $this->tenantWithDomain('construtora-halz', 'construtora-halz');

        $url = app(TenantAppUrl::class)->resetPasswordUrl($tenant, [
            'token' => 'token-123',
            'email' => 'edsingm@hotmail.com',
            'tenant' => 'construtora-halz',
        ]);

        $this->assertSame(
            'https://construtora-halz.sigapp.com.br/login/reset-password?token=token-123&email=edsingm%40hotmail.com&tenant=construtora-halz',
            $url
        );
    }

    public function test_reset_password_url_keeps_custom_domain(): void
    {
        config(['app.frontend_url' => 'https://app.sigapp.com.br']);

        $tenant = $this->tenantWithDomain('construtora-halz', 'portal.construtora.com.br');

        $url = app(TenantAppUrl::class)->resetPasswordUrl($tenant, [
            'token' => 'token-123',
            'email' => 'edsingm@hotmail.com',
            'tenant' => 'construtora-halz',
        ]);

        $this->assertSame(
            'https://portal.construtora.com.br/login/reset-password?token=token-123&email=edsingm%40hotmail.com&tenant=construtora-halz',
            $url
        );
    }

    private function tenantWithDomain(string $slug, string $domain): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Construtora Halz',
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'Admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'Password123',
        ]);

        $tenant->domains()->create(['domain' => $domain]);

        return $tenant;
    }
}
