<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HostAccessPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.frontend_url', 'https://app.sigapp.com.br');
        config()->set('tenancy.identification.central_domains', [
            'sigapp.com.br',
            'app.sigapp.com.br',
            'admin.sigapp.com.br',
            'www.sigapp.com.br',
        ]);

        Route::get('/__host-policy-probe', static fn () => response()->noContent());
    }

    public function test_unknown_sigapp_subdomain_redirects_safe_navigation_to_the_app_host(): void
    {
        $response = $this
            ->get('https://nao-cadastrado.sigapp.com.br/__host-policy-probe?source=legacy');

        $response->assertRedirect('https://app.sigapp.com.br/__host-policy-probe?source=legacy');
    }

    public function test_unknown_sigapp_subdomain_does_not_redirect_api_mutations(): void
    {
        $response = $this
            ->postJson('https://nao-cadastrado.sigapp.com.br/api/v1/auth/login', []);

        $response
            ->assertNotFound()
            ->assertHeaderMissing('Location')
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TENANT_NOT_FOUND');
    }

    #[DataProvider('centralHttpHostProvider')]
    public function test_configured_central_http_hosts_are_allowed(string $host): void
    {
        $this
            ->get("https://{$host}/__host-policy-probe")
            ->assertNoContent();
    }

    public function test_registered_tenant_subdomain_is_allowed(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Cadastrado',
            'slug' => 'tenant-cadastrado',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $tenant->domains()->create(['domain' => 'tenant-cadastrado']);

        $this
            ->get('https://tenant-cadastrado.sigapp.com.br/__host-policy-probe')
            ->assertNoContent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function centralHttpHostProvider(): array
    {
        return [
            'app' => ['app.sigapp.com.br'],
            'admin' => ['admin.sigapp.com.br'],
            'www' => ['www.sigapp.com.br'],
        ];
    }
}
