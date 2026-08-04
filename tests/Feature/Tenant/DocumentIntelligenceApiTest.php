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
use App\Jobs\AnalyzeDocumentJob;
use App\Models\Tenant\Documento;
use App\Models\Tenant\DocumentRequirement;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Services\Ai\Document\DocumentAnalysisResult;
use App\Services\Ai\Document\DocumentUnderstandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentIntelligenceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Documento $documento;

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
            'name' => 'Document Intelligence Admin',
            'email' => 'document-intelligence-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
        $terreno = Terreno::create(['nome' => 'Terreno Documental', 'created_by' => $this->admin->id]);
        Storage::fake('s3');
        $this->documento = Documento::create([
            'terreno_id' => $terreno->id,
            'nome' => 'Matrícula.pdf',
            'tipo' => 'matricula',
            'file_path' => 'documentos/matricula.pdf',
            'tamanho' => 100,
            'status' => 'pendente',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_versions_analysis_and_reviews_preserve_document_contract(): void
    {
        $this->actingAs($this->admin)->post("/api/v1/documentos/{$this->documento->id}/versions", [
            'arquivo' => UploadedFile::fake()->create('matricula-v2.pdf', 120, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.version', 1);

        Queue::fake();
        $analysis = $this->actingAs($this->admin)->postJson("/api/v1/documentos/{$this->documento->id}/analysis")
            ->assertStatus(202)
            ->json('data.id');
        Queue::assertPushed(AnalyzeDocumentJob::class, 1);

        /** @var DocumentUnderstandingService&MockInterface $understanding */
        $understanding = Mockery::mock(DocumentUnderstandingService::class);
        $understanding->shouldReceive('analyze')
            ->once()
            ->andReturn(new DocumentAnalysisResult(
                extractedFields: [
                    'summary' => 'Resumo de teste da matrícula.',
                    'key_fields' => DocumentAnalysisResult::emptyExtractedFields()['key_fields'],
                ],
                confidence: 0.8,
                limitations: [],
                provider: 'opencode_go',
                model: 'gpt-5.6-luna',
            ));

        $job = new AnalyzeDocumentJob((int) $analysis);
        $job->handle($understanding);
        $this->actingAs($this->admin)->getJson("/api/v1/documentos/{$this->documento->id}/analysis")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.extracted_fields.summary', 'Resumo de teste da matrícula.');

        $this->actingAs($this->admin)->postJson("/api/v1/documentos/{$this->documento->id}/reviews", [
            'status' => 'approved',
            'notes' => 'Conferido pelo jurídico.',
        ])->assertCreated()->assertJsonPath('data.status', 'approved');
    }

    public function test_requirements_are_scoped_by_entity_and_phase(): void
    {
        DocumentRequirement::create([
            'entity_type' => 'terreno',
            'phase' => 'prospeccao',
            'document_type' => 'matricula',
            'label' => 'Matrícula atualizada',
            'required' => true,
            'active' => true,
        ]);

        $this->actingAs($this->admin)->getJson('/api/v1/documentos/requirements?entity_type=terreno&phase=prospeccao')
            ->assertOk()->assertJsonPath('data.0.document_type', 'matricula');
    }
}
