<?php

namespace Tests\Feature\Tenant;

use App\Enums\WorkflowStatus;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\Produto;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommitteeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            InitializeTenancyFlexible::class,
            AddTenantContextToLogs::class,
            ApiRequestLogger::class,
            CheckSubscriptionStatus::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-committee-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_create_review_register_department_reviews_and_finalize_decision(): void
    {
        $terreno = $this->createCommitteeFixture();

        $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/comite', [
            'terreno_id' => $terreno->id,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.terreno_id', $terreno->id)
            ->assertJsonPath('data.status', WorkflowStatus::AGUARDANDO_COMITE->value);

        $reviewId = $createResponse->json('data.id');

        foreach (['comercial', 'engenharia', 'juridico'] as $department) {
            $this->actingAs($this->admin)->postJson("/api/v1/comite/{$reviewId}/department-reviews", [
                'department_code' => $department,
                'decision' => 'aprovado',
                'comments' => "Parecer {$department}",
                'checklist_completed' => true,
            ])->assertOk();
        }

        $this->actingAs($this->admin)->postJson("/api/v1/comite/{$reviewId}/decision", [
            'final_decision' => 'aprovado_comite',
            'final_comments' => 'Aprovado para avançar à negociação.',
        ])->assertOk()
            ->assertJsonPath('data.final_decision', 'aprovado_comite')
            ->assertJsonPath('data.status', 'aprovado_comite');

        $this->actingAs($this->admin)->getJson('/api/v1/comite')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);

        $this->actingAs($this->admin)->getJson("/api/v1/comite/{$reviewId}")
            ->assertOk()
            ->assertJsonPath('data.id', $reviewId);
    }

    public function test_committee_endpoints_require_auth_and_validate_business_rules(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno sem viabilidade aprovada',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->postJson('/api/v1/comite', ['terreno_id' => $terreno->id])
            ->assertUnauthorized();

        $this->actingAs($this->admin)->postJson('/api/v1/comite', ['terreno_id' => $terreno->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['terreno_id']);
    }

    private function createCommitteeFixture(): Terreno
    {
        $corretor = CorretorExterno::create([
            'nome' => 'Corretor Comitê',
            'email' => 'corretor-comite@test.com',
            'telefone' => '11988887777',
            'creci' => '54321',
        ]);

        $terreno = Terreno::create([
            'nome' => 'Terreno Comitê',
            'corretor_id' => $corretor->id,
            'workflow_stage' => WorkflowStatus::VIABILIDADE_APROVADA->stage(),
            'workflow_status_code' => WorkflowStatus::VIABILIDADE_APROVADA->value,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Proprietario::create([
            'terreno_id' => $terreno->id,
            'nome' => 'Proprietário Comitê',
            'tipo_pessoa' => 'fisica',
            'porcentagem_terreno' => 100,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $produto = Produto::create([
            'name' => 'Produto Comitê',
            'status' => 'ativo',
            'private_area' => 60,
            'm2_cost' => 1800,
            'infra_cost' => 300,
            'curva_vendas' => [20, 20, 20, 20, 20],
        ]);

        TerrenoProduto::create([
            'terreno_id' => $terreno->id,
            'produto_id' => $produto->id,
            'unidades' => 10,
            'valor' => 200000,
            'permuta' => 0,
            'pgto_por_lote' => 0,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Viabilidade::create([
            'terreno_id' => $terreno->id,
            'version' => 1,
            'is_current' => true,
            'status' => 'ativo',
            'approval_status' => 'aprovada',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        return $terreno;
    }
}
