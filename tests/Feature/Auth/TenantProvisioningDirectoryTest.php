<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\DTOs\RequestContext;
use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use App\Services\Auth\CentralLoginBrokerService;
use Database\Seeders\Tenant\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class TenantProvisioningDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_seed_registers_admin_for_central_login(): void
    {
        config([
            'database.connections.tenant_template' => config('database.connections.sqlite'),
            'app.domain' => 'example.test',
            'app.url' => 'https://api.example.test',
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Paulo Silva Corretor',
            'slug' => 'paulo-silva-corretor',
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'Paulo Silva',
            'admin_email' => 'paulosilva@exemplo.com',
            'admin_password' => 'Paulo1234',
            'database_created' => true,
        ]);
        $tenant->domains()->create(['domain' => 'paulo-silva-corretor']);
        $tenant->ensureEncryptionKey();
        $manager = $tenant->database()->manager();
        $databaseName = (string) $tenant->database()->getName();
        $manager->createDatabase($tenant);

        try {
            tenancy()->initialize($tenant);
            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => database_path('migrations/tenant'),
                '--realpath' => true,
                '--force' => true,
            ]));
            $this->assertSame(0, Artisan::call('db:seed', [
                '--class' => TenantSeeder::class,
                '--force' => true,
            ]));
            tenancy()->end();

            $this->assertDatabaseHas((new TenantUserDirectory)->getTable(), [
                'tenant_id' => (string) $tenant->getKey(),
                'tenant_user_id' => '1',
                'email_normalized' => 'paulosilva@exemplo.com',
                'active' => true,
            ]);

            $result = app(CentralLoginBrokerService::class)->attemptCentralLogin(
                'paulosilva@exemplo.com',
                'Paulo1234',
                'web',
                new RequestContext(ipAddress: '192.0.2.10'),
            );

            $this->assertSame('redirect', $result['next_action'] ?? null);
            $this->assertSame('paulo-silva-corretor', $result['tenant']['slug'] ?? null);
            $this->assertNotEmpty($result['transfer_ticket'] ?? null);
        } finally {
            tenancy()->end();

            if ($manager->databaseExists($databaseName)) {
                $manager->deleteDatabase($tenant);
            }
        }
    }
}
