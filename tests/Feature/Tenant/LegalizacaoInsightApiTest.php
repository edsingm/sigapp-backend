<?php

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\PermissionGate;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoDependencia;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LegalizacaoInsightApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Legalizacao $legalizacao;

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
            CheckFeature::class,
            PermissionGate::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Legalization Insight Admin',
            'email' => 'legalization-insight-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);

        $terreno = Terreno::create(['nome' => 'Terreno central de legalização', 'created_by' => $this->admin->id]);
        $this->legalizacao = Legalizacao::create([
            'terreno_id' => $terreno->id,
            'nome' => 'Legalização central',
            'status' => 'em_andamento',
            'custo_total_previsto' => 10000,
            'created_by' => $this->admin->id,
        ]);

        $first = LegalizacaoEtapa::create([
            'legalizacao_id' => $this->legalizacao->id,
            'titulo' => 'Protocolo',
            'ordem' => 1,
            'status' => 'concluida',
            'inicio_planejado' => '2026-07-01',
            'fim_planejado' => '2026-07-02',
            'percentual' => 100,
            'custos' => [['tipo_custo' => 'taxa', 'valor_custo' => 3000, 'custo_pago' => true]],
        ]);
        $second = LegalizacaoEtapa::create([
            'legalizacao_id' => $this->legalizacao->id,
            'titulo' => 'Aprovação',
            'ordem' => 2,
            'status' => 'em_andamento',
            'inicio_planejado' => '2026-07-03',
            'fim_planejado' => '2026-07-05',
            'percentual' => 50,
            'custos' => [['tipo_custo' => 'cartorio', 'valor_custo' => 7000, 'custo_pago' => false]],
        ]);
        LegalizacaoDependencia::create([
            'legalizacao_id' => $this->legalizacao->id,
            'etapa_origem_id' => $first->id,
            'etapa_destino_id' => $second->id,
            'tipo' => 'FS',
        ]);
    }

    public function test_admin_can_read_legalization_control_center_critical_path_and_costs(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/legalizacoes/control-center')
            ->assertOk()->assertJsonPath('data.0.critical_open_pendencies', 0);

        $this->actingAs($this->admin)->getJson("/api/v1/legalizacoes/{$this->legalizacao->id}/critical-path")
            ->assertOk()
            ->assertJsonPath('data.total_days', 5)
            ->assertJsonPath('data.data_sufficient', true);

        $this->actingAs($this->admin)->getJson("/api/v1/legalizacoes/{$this->legalizacao->id}/costs")
            ->assertOk()
            ->assertJsonPath('data.planned', 10000)
            ->assertJsonPath('data.realized', 3000)
            ->assertJsonPath('data.committed_available', false);
    }
}
