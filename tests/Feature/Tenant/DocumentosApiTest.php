<?php

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Exceptions\StorageQuotaExceededException;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnforcePlanLimits;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Jobs\IndexDocumentEmbeddingJob;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Services\Tenant\StorageQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentosApiTest extends TestCase
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
            EnsureTenantAdmin::class,
            EnforcePlanLimits::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Documento Admin',
            'email' => 'documento-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);

        $this->terreno = Terreno::create([
            'nome' => 'Terreno Documentos',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Storage::fake('s3');
        Queue::fake([IndexDocumentEmbeddingJob::class]);
    }

    public function test_admin_can_upload_list_show_and_update_documento(): void
    {
        $file = UploadedFile::fake()->create('matricula.pdf', 100, 'application/pdf');

        $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/documentos', [
            'terreno_id' => $this->terreno->id,
            'arquivo' => $file,
            'nome' => 'Matrícula atualizada',
            'tipo' => 'matricula',
            'categoria' => 'juridico',
            'descricao' => 'Documento de matrícula',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.nome', 'Matrícula atualizada')
            ->assertJsonPath('data.tipo', 'matricula');

        $documentoId = $createResponse->json('data.id');
        $documento = Documento::findOrFail($documentoId);
        Storage::disk('s3')->assertExists($documento->file_path);
        Queue::assertPushed(IndexDocumentEmbeddingJob::class);

        $this->actingAs($this->admin)->getJson('/api/v1/documentos')
            ->assertOk()
            ->assertJsonFragment(['id' => $documentoId]);

        $this->actingAs($this->admin)->getJson("/api/v1/documentos/{$documentoId}")
            ->assertOk()
            ->assertJsonPath('data.id', $documentoId);

        $this->actingAs($this->admin)->putJson("/api/v1/documentos/{$documentoId}", [
            'status' => 'aprovado',
            'descricao' => 'Aprovado pelo jurídico',
        ])->assertOk()
            ->assertJsonPath('data.status', 'aprovado')
            ->assertJsonPath('data.descricao', 'Aprovado pelo jurídico');
    }

    public function test_documento_upload_and_update_validate_payload(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/documentos', [
            'terreno_id' => $this->terreno->id,
            'tipo' => 'tipo_invalido',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['arquivo', 'tipo']);

        $documento = Documento::create([
            'terreno_id' => $this->terreno->id,
            'nome' => 'Contrato',
            'tipo' => 'contrato',
            'categoria' => 'juridico',
            'file_path' => 'documentos/contrato.pdf',
            'tamanho' => 100,
            'status' => 'pendente',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->putJson("/api/v1/documentos/{$documento->id}", [
            'status' => 'status_invalido',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_documento_upload_accepts_zip_based_formats_with_client_extension(): void
    {
        $kmzPath = tempnam(sys_get_temp_dir(), 'kmz');
        self::assertIsString($kmzPath);
        $zip = new \ZipArchive;
        self::assertTrue($zip->open($kmzPath, \ZipArchive::OVERWRITE));
        $zip->addFromString('doc.kml', '<?xml version="1.0"?><kml/>');
        $zip->close();

        $kmz = new UploadedFile($kmzPath, 'poligono.kmz', 'application/vnd.google-earth.kmz', null, true);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/documentos', [
            'terreno_id' => $this->terreno->id,
            'arquivo' => $kmz,
            'tipo' => 'planta',
            'categoria' => 'tecnico',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.tipo', 'planta');

        $documento = Documento::findOrFail($response->json('data.id'));
        $this->assertStringEndsWith('.kmz', (string) $documento->file_path);
        Storage::disk('s3')->assertExists($documento->file_path);
    }

    public function test_documento_upload_rejects_disallowed_type_with_clear_message(): void
    {
        $file = UploadedFile::fake()->create('malware.exe', 50, 'application/octet-stream');

        $this->actingAs($this->admin)->postJson('/api/v1/documentos', [
            'terreno_id' => $this->terreno->id,
            'arquivo' => $file,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['arquivo'])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_documento_upload_rejects_oversized_file_with_portuguese_message(): void
    {
        $file = UploadedFile::fake()->create('grande.pdf', 11 * 1024, 'application/pdf');

        $this->actingAs($this->admin)->postJson('/api/v1/documentos', [
            'terreno_id' => $this->terreno->id,
            'arquivo' => $file,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['arquivo'])
            ->assertJsonFragment(['O arquivo não pode ser maior que 10 MB.']);
    }

    public function test_documento_upload_returns_plan_limit_when_storage_quota_is_exceeded(): void
    {
        $this->mock(StorageQuotaService::class, function ($mock): void {
            $mock->shouldReceive('commitFile')
                ->once()
                ->andThrow(new StorageQuotaExceededException);
        });

        $file = UploadedFile::fake()->create('matricula.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin)->postJson('/api/v1/documentos', [
            'terreno_id' => $this->terreno->id,
            'arquivo' => $file,
            'tipo' => 'matricula',
        ])->assertForbidden()
            ->assertJsonPath('error.code', 'PLAN_LIMIT_EXCEEDED')
            ->assertJsonPath('error.details.resource', 'storage_gb');
    }
}
