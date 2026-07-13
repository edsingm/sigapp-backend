<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\PremissasViabilidade;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Repositories\Contracts\PremissasViabilidadeRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PremissasViabilidadeApiTest extends TestCase
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
            'email' => 'premissas-admin@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->adminUser->assignRole(RolesEnum::ADMIN);
    }

    public function test_it_filters_premissas_by_ativo_query_param(): void
    {
        $ativa = PremissasViabilidade::factory()->ativa()->createOne([
            'nome' => 'Modelo ativo',
            'versao' => 1,
        ]);

        PremissasViabilidade::factory()->inativa()->createOne([
            'nome' => 'Modelo inativo',
            'versao' => 2,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/premissas-viabilidade?ativo=true&per_page=100');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ativa->id)
            ->assertJsonPath('data.0.ativo', true);
    }

    public function test_agendar_premissa_futura_nao_invalida_vigente_hoje(): void
    {
        $hoje = now()->toDateString();
        $futuro = now()->addMonth()->toDateString();
        $vespera = now()->addMonth()->subDay()->toDateString();

        $atual = PremissasViabilidade::factory()->ativa()->createOne([
            'nome' => 'Vigente hoje',
            'perfil_financiamento' => 'cef',
            'versao' => 1,
            'vigente_em' => now()->subMonth()->toDateString(),
            'encerrada_em' => null,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/premissas-viabilidade', [
                'nome' => 'Futura',
                'perfil_financiamento' => 'cef',
                'vigente_em' => $futuro,
                'pis_cofins' => 4.0,
            ])
            ->assertCreated();

        $atual->refresh();
        $this->assertSame($vespera, $atual->encerrada_em?->toDateString());
        // Ainda no catálogo se o fim é no futuro.
        $this->assertTrue((bool) $atual->ativo);

        $repo = app(PremissasViabilidadeRepositoryInterface::class);
        $aplicavelHoje = $repo->findActiveForPerfilAt('cef', $hoje);
        $this->assertNotNull($aplicavelHoje);
        $this->assertSame($atual->id, $aplicavelHoje->id);

        $aplicavelFuturo = $repo->findActiveForPerfilAt('cef', $futuro);
        $this->assertNotNull($aplicavelFuturo);
        $this->assertNotSame($atual->id, $aplicavelFuturo->id);
    }

    public function test_nao_permite_sobrepor_intervalos_ativos_do_mesmo_perfil(): void
    {
        // Premissa futura já agendada a partir de 2026-10.
        PremissasViabilidade::factory()->ativa()->createOne([
            'nome' => 'Futura',
            'perfil_financiamento' => 'cef',
            'versao' => 1,
            'vigente_em' => '2026-10-01',
            'encerrada_em' => '2026-12-31',
            'ativo' => true,
        ]);

        // Nova começa antes e termina depois do início da futura — não pode
        // "fechar" a futura (vigente_em dela não é < 2026-09-01) e sobrepõe.
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/premissas-viabilidade', [
                'nome' => 'Conflito',
                'perfil_financiamento' => 'cef',
                'vigente_em' => '2026-09-01',
                'encerrada_em' => '2026-11-01',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PREMISSAS_OVERLAP');
    }

    public function test_destroy_referenciada_apenas_inativa(): void
    {
        $premissa = PremissasViabilidade::factory()->ativa()->createOne([
            'versao' => 1,
            'perfil_financiamento' => 'cef',
            'vigente_em' => now()->subMonths(2)->toDateString(),
        ]);

        // Substituta vigente (evita bloqueio de "única ativa").
        PremissasViabilidade::factory()->ativa()->createOne([
            'versao' => 2,
            'perfil_financiamento' => 'cef',
            'vigente_em' => now()->subDay()->toDateString(),
        ]);

        $terrenoId = DB::table('terrenos')->insertGetId([
            'nome' => 'Terreno Premissas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Viabilidade::query()->create([
            'terreno_id' => $terrenoId,
            'version' => 1,
            'is_current' => true,
            'status' => 'rascunho',
            'approval_status' => 'pendente',
            'premissas_snapshot' => [
                'schema_version' => 2,
                'premissas' => ['id' => $premissa->id, 'version' => 1],
            ],
        ]);

        $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/premissas-viabilidade/{$premissa->id}")
            ->assertOk()
            ->assertJsonPath('data.ativo', false);

        $this->assertDatabaseHas('premissas_viabilidade', [
            'id' => $premissa->id,
            'ativo' => 0,
        ]);
    }

    public function test_selecao_deterministica_por_vigente_versao_e_id(): void
    {
        PremissasViabilidade::factory()->createOne([
            'perfil_financiamento' => 'cef',
            'versao' => 1,
            'vigente_em' => '2026-01-01',
            'encerrada_em' => null,
            'ativo' => true,
        ]);
        $preferida = PremissasViabilidade::factory()->createOne([
            'perfil_financiamento' => 'cef',
            'versao' => 2,
            'vigente_em' => '2026-01-01',
            'encerrada_em' => null,
            'ativo' => true,
        ]);

        $repo = app(PremissasViabilidadeRepositoryInterface::class);
        $ativa = $repo->findActiveForPerfilAt('cef', '2026-06-01');

        $this->assertNotNull($ativa);
        $this->assertSame($preferida->id, $ativa->id);
    }
}
