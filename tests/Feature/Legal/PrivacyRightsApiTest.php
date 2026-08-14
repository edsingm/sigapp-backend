<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Enums\Common\RolesEnum;
use App\Enums\TenantExportStatus;
use App\Enums\TenantExportType;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnforceHostAccess;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\EnsureTenantBillingProfileCompleted;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Jobs\GenerateSubjectPortabilityJob;
use App\Jobs\GenerateTenantPortabilityJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\TenantExportGeneration;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Repositories\Tenant\TenantExportGenerationRepository;
use App\Services\Auth\TenantUserDirectoryService;
use App\Services\Privacy\PrivacySubjectService;
use App\Services\Tenant\StorageQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PrivacyRightsApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $member;

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
            EnforceHostAccess::class,
            EnsureCentralContext::class,
            EnsureTenantBillingProfileCompleted::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);
        Role::query()->firstOrCreate(['name' => RolesEnum::USER->value, 'guard_name' => 'web']);

        $this->tenant = Tenant::query()->create([
            'name' => 'Tenant Privacy',
            'slug' => 'tenant-privacy',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $this->tenant->domains()->create(['domain' => 'tenant-privacy']);

        $this->admin = User::query()->create([
            'name' => 'Privacy Admin',
            'email' => 'privacy-admin@test.com',
            'password' => 'Password123',
            'status' => 'Active',
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);

        $this->member = User::query()->create([
            'name' => 'Privacy Member',
            'email' => 'privacy-member@test.com',
            'password' => 'Password123',
            'status' => 'Active',
        ]);
        $this->member->assignRole(RolesEnum::USER);

        tenancy()->tenant = $this->tenant;
        tenancy()->initialized = true;

        $this->app->instance(
            TenantUserDirectoryService::class,
            Mockery::mock(TenantUserDirectoryService::class)->shouldIgnoreMissing(),
        );

        Storage::fake('s3');
    }

    protected function tearDown(): void
    {
        tenancy()->tenant = null;
        tenancy()->initialized = false;

        parent::tearDown();
    }

    public function test_privacy_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/privacy/me')->assertUnauthorized();
    }

    public function test_privacy_me_returns_inventory_for_the_authenticated_user(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/privacy/me')
            ->assertOk()
            ->assertJsonPath('data.profile.email', 'privacy-member@test.com')
            ->assertJsonPath('data.roles.0', RolesEnum::USER->value)
            ->assertJsonPath('data.counts.terrenos_created', 0)
            ->assertJsonPath('data.tenant.slug', 'tenant-privacy')
            ->assertJsonPath('data.legal.reacceptance_required', true);

        $this->assertNotEmpty($this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/privacy/me')
            ->json('data.subprocessors'));
    }

    public function test_privacy_export_is_queued_on_exports_and_rejects_unauthenticated(): void
    {
        $this->postJson('/api/v1/privacy/export')->assertUnauthorized();

        Queue::fake();

        $first = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/v1/privacy/export')
            ->assertStatus(202)
            ->assertJsonPath('data.type', TenantExportType::SUBJECT_PORTABILITY->value)
            ->assertJsonPath('data.status', TenantExportStatus::QUEUED->value);

        $second = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/v1/privacy/export')
            ->assertStatus(202);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        Queue::assertPushedOn('exports', GenerateSubjectPortabilityJob::class);
        Queue::assertPushed(GenerateSubjectPortabilityJob::class, 1);
    }

    public function test_subject_portability_job_writes_private_json_and_download_works(): void
    {
        Queue::fake();

        $exportId = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/v1/privacy/export')
            ->assertStatus(202)
            ->json('data.id');

        $this->assertIsInt($exportId);

        $quota = $this->createMock(StorageQuotaService::class);
        $quota->expects($this->once())
            ->method('commitFile')
            ->willReturnCallback(
                static fn (string $disk, string $path, \Closure $persist): mixed => $persist(64),
            );

        $job = new GenerateSubjectPortabilityJob($exportId);
        $job->handle(
            app(TenantExportGenerationRepository::class),
            app(PrivacySubjectService::class),
            $quota,
        );

        $generation = TenantExportGeneration::query()->findOrFail($exportId);
        $this->assertSame(TenantExportStatus::COMPLETED, $generation->status);
        $this->assertSame('application/json', $generation->mime_type);
        $this->assertNotNull($generation->storage_path);
        Storage::disk('s3')->assertExists((string) $generation->storage_path);

        $payload = json_decode((string) Storage::disk('s3')->get((string) $generation->storage_path), true);
        $this->assertIsArray($payload);
        $this->assertSame('privacy-member@test.com', $payload['profile']['email'] ?? null);
        $this->assertArrayNotHasKey('password', $payload['profile'] ?? []);

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/privacy/export/'.$exportId)
            ->assertOk()
            ->assertJsonPath('data.status', TenantExportStatus::COMPLETED->value)
            ->assertJsonPath('data.download_url', route('tenant.privacy.export.download', ['export' => $exportId]));

        $this->actingAs($this->member, 'sanctum')
            ->get('/api/v1/privacy/export/'.$exportId.'/download')
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_operational_exports_endpoint_rejects_subject_portability_type(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/exports', [
                'idempotency_key' => '11111111-1111-1111-1111-111111111111',
                'type' => TenantExportType::SUBJECT_PORTABILITY->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_erasure_requires_authentication_and_password(): void
    {
        $this->postJson('/api/v1/privacy/erasure')->assertUnauthorized();

        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/v1/privacy/erasure', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_erasure_rejects_invalid_password_and_last_admin(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/v1/privacy/erasure', ['password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PRIVACY_ERASURE_INVALID_PASSWORD');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/privacy/erasure', ['password' => 'Password123'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'LAST_TENANT_ADMIN');
    }

    public function test_erasure_anonymizes_account_revokes_tokens_and_keeps_created_by(): void
    {
        $token = $this->member->createToken('privacy-test')->plainTextToken;
        $this->assertSame(1, $this->member->tokens()->count());

        $this->withToken($token)
            ->postJson('/api/v1/privacy/erasure', ['password' => 'Password123'])
            ->assertOk()
            ->assertJsonPath('message', 'Sua conta foi anonimizada com sucesso');

        $this->member->refresh();

        $this->assertSame('Titular removido', $this->member->name);
        $this->assertSame('deleted-'.$this->member->id.'@privacy.invalid', $this->member->email);
        $this->assertSame('Inactive', $this->member->status);
        $this->assertSame(0, $this->member->tokens()->count());
        $this->assertFalse($this->member->hasRole(RolesEnum::USER->value));
        $this->assertDatabaseHas('users', ['id' => $this->member->id]);
    }

    public function test_workspace_export_is_restricted_to_admin_or_director(): void
    {
        $this->postJson('/api/v1/privacy/workspace-export')->assertUnauthorized();

        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/v1/privacy/workspace-export')
            ->assertForbidden();

        Queue::fake();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/privacy/workspace-export')
            ->assertStatus(202)
            ->assertJsonPath('data.type', TenantExportType::TENANT_PORTABILITY->value);

        Queue::assertPushedOn('exports', GenerateTenantPortabilityJob::class);
    }

    public function test_workspace_export_job_reads_terreno_cidade_code(): void
    {
        Terreno::query()->create([
            'nome' => 'Terreno Portabilidade',
            'cidade_code' => '3550308',
            'estado' => 'SP',
        ]);

        Queue::fake();

        $exportId = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/privacy/workspace-export')
            ->assertStatus(202)
            ->json('data.id');

        $this->assertIsInt($exportId);

        $quota = $this->createMock(StorageQuotaService::class);
        $quota->expects($this->once())
            ->method('commitFile')
            ->willReturnCallback(
                static fn (string $disk, string $path, \Closure $persist): mixed => $persist(64),
            );

        $job = new GenerateTenantPortabilityJob($exportId);
        $job->handle(
            app(TenantExportGenerationRepository::class),
            $quota,
        );

        $generation = TenantExportGeneration::query()->findOrFail($exportId);
        $this->assertSame(TenantExportStatus::COMPLETED, $generation->status);
        $this->assertNotNull($generation->storage_path);

        $payload = json_decode((string) Storage::disk('s3')->get((string) $generation->storage_path), true);
        $this->assertIsArray($payload);
        $this->assertSame('3550308', $payload['terrenos'][0]['cidade_code'] ?? null);
        $this->assertSame('SP', $payload['terrenos'][0]['estado'] ?? null);
        $this->assertArrayNotHasKey('cidade', $payload['terrenos'][0] ?? []);
    }

    public function test_ai_document_transfer_requires_admin_and_stamps_acceptance(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/v1/privacy/ai-document-transfer')
            ->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/privacy/ai-document-transfer')
            ->assertOk();

        $this->assertNotNull($this->tenant->fresh()?->getAttribute('ai_document_transfer_accepted_at'));
    }
}
