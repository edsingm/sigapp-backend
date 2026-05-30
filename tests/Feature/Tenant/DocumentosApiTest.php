<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnforcePlanLimits;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Jobs\IndexDocumentEmbeddingJob;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
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
        Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Documento Admin',
            'email' => 'documento-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');

        $this->terreno = Terreno::create([
            'nome' => 'Terreno Documentos',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Storage::fake('local');
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
        Storage::assertExists($documento->file_path);

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
}
