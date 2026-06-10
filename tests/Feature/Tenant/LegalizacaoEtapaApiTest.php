<?php

namespace Tests\Feature\Tenant;

use App\Enums\WorkflowStatus;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\ContratoParte;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LegalizacaoEtapaApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Terreno $terreno;

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
            EnsureTenantAdmin::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Tenant Etapa Admin',
            'email' => 'tenant-etapa-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');

        $this->terreno = $this->createSignedContractFixture();
        $this->legalizacao = $this->createLegalizacao($this->terreno);
    }

    public function test_admin_can_list_etapas(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas");

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_create_etapa(): void
    {
        $payload = [
            'titulo' => 'Protocolo Prefeitura',
            'status' => 'pendente',
            'inicio_planejado' => now()->toDateString(),
            'fim_planejado' => now()->addDays(10)->toDateString(),
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.titulo', 'Protocolo Prefeitura')
            ->assertJsonPath('data.status', 'pendente');

        $this->assertDatabaseHas('legalizacao_etapas', [
            'legalizacao_id' => $this->legalizacao->id,
            'titulo' => 'Protocolo Prefeitura',
        ]);
    }

    public function test_admin_can_show_etapa(): void
    {
        $etapa = $this->createEtapa($this->legalizacao);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas/{$etapa->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $etapa->id)
            ->assertJsonPath('data.titulo', $etapa->titulo);
    }

    public function test_admin_can_update_etapa(): void
    {
        $etapa = $this->createEtapa($this->legalizacao);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas/{$etapa->id}", [
                'titulo' => 'Protocolo Atualizado',
                'percentual' => 50,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.titulo', 'Protocolo Atualizado')
            ->assertJsonPath('data.percentual', 50);
    }

    public function test_admin_can_delete_etapa(): void
    {
        $etapa = $this->createEtapa($this->legalizacao);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas/{$etapa->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('legalizacao_etapas', ['id' => $etapa->id]);
    }

    public function test_admin_can_reorder_etapas(): void
    {
        $etapa1 = $this->createEtapa($this->legalizacao, ['ordem' => 1, 'titulo' => 'Etapa 1']);
        $etapa2 = $this->createEtapa($this->legalizacao, ['ordem' => 2, 'titulo' => 'Etapa 2']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas/reorder", [
                'etapas' => [
                    ['id' => $etapa1->id, 'ordem' => 2],
                    ['id' => $etapa2->id, 'ordem' => 1],
                ],
            ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_update_etapa_status(): void
    {
        $etapa = $this->createEtapa($this->legalizacao);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas/{$etapa->id}/status", [
                'status' => 'concluida',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'concluida');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas")
            ->assertUnauthorized();

        $this->postJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas", ['nome' => 'Test'])
            ->assertUnauthorized();

        $this->deleteJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas/999")
            ->assertUnauthorized();
    }

    public function test_create_etapa_with_invalid_payload_returns_422(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['titulo']);
    }

    public function test_update_status_with_invalid_status_returns_422(): void
    {
        $etapa = $this->createEtapa($this->legalizacao);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas/{$etapa->id}/status", [
                'status' => 'invalid_status',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_reorder_etapas_with_invalid_payload_returns_422(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas/reorder", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['etapas']);
    }

    public function test_show_nonexistent_etapa_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/legalizacoes/{$this->legalizacao->id}/etapas/99999");

        $response->assertNotFound();
    }

    private function createSignedContractFixture(): Terreno
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno Etapa Test',
            'workflow_stage' => WorkflowStatus::CONTRATO_ASSINADO->stage(),
            'workflow_status_code' => WorkflowStatus::CONTRATO_ASSINADO->value,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $contract = Contrato::create([
            'terreno_id' => $terreno->id,
            'contract_type' => 'compra',
            'contract_number' => 'ETAPA-001',
            'signed_at' => now(),
            'status' => WorkflowStatus::CONTRATO_ASSINADO->value,
            'file_path' => '/contracts/etapa-001.pdf',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        ContratoParte::create([
            'contrato_id' => $contract->id,
            'name' => 'Proprietário Etapa',
            'document' => '98765432100',
            'party_type' => 'seller',
        ]);

        return $terreno;
    }

    private function createLegalizacao(Terreno $terreno): Legalizacao
    {
        return Legalizacao::create([
            'terreno_id' => $terreno->id,
            'nome' => 'Legalização Etapa Test',
            'status' => 'planejado',
            'percentual_concluido' => 0,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    private function createEtapa(Legalizacao $legalizacao, array $overrides = []): LegalizacaoEtapa
    {
        $count = LegalizacaoEtapa::where('legalizacao_id', $legalizacao->id)->count();

        return LegalizacaoEtapa::create(array_merge([
            'legalizacao_id' => $legalizacao->id,
            'titulo' => 'Etapa Teste',
            'status' => 'pendente',
            'ordem' => $count + 1,
            'percentual' => 0,
            'inicio_planejado' => now()->toDateString(),
            'fim_planejado' => now()->addDays(5)->toDateString(),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ], $overrides));
    }
}
