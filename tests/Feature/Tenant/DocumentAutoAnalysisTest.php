<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Jobs\AnalyzeDocumentJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\DocumentAnalysis;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Services\PlanMatrixService;
use App\Services\Tenant\DocumentAnalysisEligibility;
use App\Services\Tenant\DocumentIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Tenancy;
use Tests\TestCase;

class DocumentAutoAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Terreno $terreno;

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
        ]);
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);
        $this->admin = User::create([
            'name' => 'Auto Analysis Admin',
            'email' => 'auto-analysis-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
        $this->terreno = Terreno::create(['nome' => 'Terreno Auto', 'created_by' => $this->admin->id]);
        Storage::fake('s3');
    }

    protected function tearDown(): void
    {
        app(Tenancy::class)->tenant = null;
        Mockery::close();
        parent::tearDown();
    }

    public function test_auto_dispatch_for_matricula_pdf_when_feature_enabled(): void
    {
        Queue::fake([AnalyzeDocumentJob::class]);

        $tenant = new Tenant;
        app(Tenancy::class)->tenant = $tenant;

        $planMatrix = Mockery::mock(PlanMatrixService::class);
        $planMatrix->shouldReceive('hasFeatureForTenant')
            ->once()
            ->with($tenant, 'documents.intelligence')
            ->andReturn(true);
        $this->app->instance(PlanMatrixService::class, $planMatrix);

        $documento = Documento::create([
            'terreno_id' => $this->terreno->id,
            'nome' => 'Matrícula.pdf',
            'tipo' => 'matricula',
            'file_path' => 'documentos/matricula.pdf',
            'tamanho' => 100,
            'status' => 'pendente',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $result = app(DocumentIntelligenceService::class)
            ->dispatchAutoAnalysisIfEligible($documento, $this->admin);

        $this->assertNotNull($result);
        $this->assertSame('queued', $result->status);
        Queue::assertPushed(AnalyzeDocumentJob::class, 1);
    }

    public function test_auto_dispatch_skips_when_feature_disabled(): void
    {
        Queue::fake([AnalyzeDocumentJob::class]);

        $tenant = new Tenant;
        app(Tenancy::class)->tenant = $tenant;

        $planMatrix = Mockery::mock(PlanMatrixService::class);
        $planMatrix->shouldReceive('hasFeatureForTenant')
            ->once()
            ->with($tenant, 'documents.intelligence')
            ->andReturn(false);
        $this->app->instance(PlanMatrixService::class, $planMatrix);

        $documento = Documento::create([
            'terreno_id' => $this->terreno->id,
            'nome' => 'Matrícula.pdf',
            'tipo' => 'matricula',
            'file_path' => 'documentos/matricula.pdf',
            'tamanho' => 100,
            'status' => 'pendente',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $result = app(DocumentIntelligenceService::class)
            ->dispatchAutoAnalysisIfEligible($documento, $this->admin);

        $this->assertNull($result);
        Queue::assertNothingPushed();
    }

    public function test_request_analysis_rejects_non_pdf(): void
    {
        $documento = Documento::create([
            'terreno_id' => $this->terreno->id,
            'nome' => 'foto.png',
            'tipo' => 'matricula',
            'file_path' => 'documentos/foto.png',
            'tamanho' => 10,
            'status' => 'pendente',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/documentos/{$documento->id}/analysis")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DOCUMENT_ANALYSIS_UNSUPPORTED_TYPE');
    }

    public function test_outros_pdf_is_on_demand_only(): void
    {
        Queue::fake([AnalyzeDocumentJob::class]);

        $documento = Documento::create([
            'terreno_id' => $this->terreno->id,
            'nome' => 'outros.pdf',
            'tipo' => 'outros',
            'file_path' => 'documentos/outros.pdf',
            'tamanho' => 10,
            'status' => 'pendente',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $eligibility = app(DocumentAnalysisEligibility::class);
        $this->assertFalse($eligibility->shouldAutoAnalyze($documento));
        $this->assertTrue($eligibility->canAnalyzeOnDemand($documento));

        $this->actingAs($this->admin)
            ->postJson("/api/v1/documentos/{$documento->id}/analysis")
            ->assertStatus(202);

        Queue::assertPushed(AnalyzeDocumentJob::class, 1);
    }

    public function test_force_reprocesses_after_failed_analysis(): void
    {
        Queue::fake([AnalyzeDocumentJob::class]);

        $documento = Documento::create([
            'terreno_id' => $this->terreno->id,
            'nome' => 'MATRICULA 48969.pdf',
            'tipo' => 'matricula',
            'file_path' => 'documentos/matricula-48969.pdf',
            'tamanho' => 100,
            'status' => 'pendente',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        DocumentAnalysis::query()->create([
            'documento_id' => $documento->id,
            'requested_by' => $this->admin->id,
            'status' => 'failed',
            'provider' => 'opencode_go',
            'model' => 'gpt-5.6-luna',
            'error_message' => 'Provedor de análise documental não configurado (OPENCODE_GO_API_KEY).',
            'limitations' => ['Falha definitiva após esgotar tentativas do job.'],
            'completed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/documentos/{$documento->id}/analysis", ['force' => true])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued');

        Queue::assertPushed(AnalyzeDocumentJob::class, 1);
        $this->assertSame(2, $documento->analyses()->count());
    }
}
