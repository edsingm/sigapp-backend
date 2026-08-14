<?php

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Http\Controllers\Api\V1\Tenant\ProprietariosController;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProprietariosApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            InitializeTenancyFlexible::class,
            AddTenantContextToLogs::class,
            ApiRequestLogger::class,
            CheckSubscriptionStatus::class,
            EnsureTenantContext::class,
            EnsureTenantUser::class,
            EnsureTenantAdmin::class,
            CheckFeature::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->adminUser->assignRole(RolesEnum::ADMIN);
    }

    public function test_controller_existe(): void
    {
        $reflection = new \ReflectionClass(ProprietariosController::class);

        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->hasMethod('store'));
        $this->assertTrue($reflection->hasMethod('show'));
        $this->assertTrue($reflection->hasMethod('update'));
        $this->assertTrue($reflection->hasMethod('destroy'));
        $this->assertTrue($reflection->hasMethod('proprietariosForSelect'));
    }

    public function test_controller_tem_dependencias_injetadas(): void
    {
        $reflection = new \ReflectionClass(ProprietariosController::class);

        $this->assertTrue($reflection->hasMethod('__construct'));

        $constructor = $reflection->getMethod('__construct');
        $params = $constructor->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params));
    }

    public function test_it_lists_proprietarios_for_select(): void
    {
        $terreno = Terreno::create(['nome' => 'Terreno A', 'endereco' => 'Rua A']);
        Proprietario::create([
            'terreno_id' => $terreno->id,
            'nome' => 'Proprietário Select',
            'tipo_pessoa' => 'fisica',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/proprietarios/select');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.nome', 'Proprietário Select')
            ->assertJsonStructure(['success', 'data' => [['id', 'nome']], 'message']);
    }

    public function test_anonymize_requires_authentication(): void
    {
        $this->postJson('/api/v1/proprietarios/1/anonymize')->assertUnauthorized();
    }

    public function test_anonymize_rejects_non_admin_and_clears_pii_for_admin(): void
    {
        Role::query()->firstOrCreate(['name' => RolesEnum::USER->value, 'guard_name' => 'web']);
        $member = User::query()->create([
            'name' => 'Member',
            'email' => 'member@test.com',
            'password' => 'password',
        ]);
        $member->assignRole(RolesEnum::USER);

        $terreno = Terreno::query()->create(['nome' => 'Terreno PII', 'endereco' => 'Rua B']);
        $proprietario = Proprietario::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'João da Silva',
            'tipo_pessoa' => 'fisica',
            'cpf_cnpj' => '52998224725',
            'email' => 'joao@example.com',
            'telefone' => '14999999999',
            'rg' => '1234567',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        tenancy()->initialized = true;

        $this->actingAs($member)
            ->postJson('/api/v1/proprietarios/'.$proprietario->id.'/anonymize')
            ->assertForbidden();

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/proprietarios/'.$proprietario->id.'/anonymize')
            ->assertOk()
            ->assertJsonPath('data.nome', 'Titular anonimizado')
            ->assertJsonPath('data.cpf_cnpj', null)
            ->assertJsonPath('data.email', null);

        $this->assertDatabaseHas('terreno_proprietarios', [
            'id' => $proprietario->id,
            'nome' => 'Titular anonimizado',
            'cpf_cnpj' => null,
            'email' => null,
            'telefone' => null,
            'rg' => null,
        ]);

        tenancy()->initialized = false;
    }
}
