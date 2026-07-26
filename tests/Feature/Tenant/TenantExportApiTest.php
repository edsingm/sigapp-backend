<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Enums\TenantExportStatus;
use App\Enums\TenantExportType;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Jobs\GenerateTenantExportJob;
use App\Models\Tenant\TenantExportGeneration;
use App\Models\Tenant\User;
use App\Repositories\Tenant\TenantExportGenerationRepository;
use App\Services\Tenant\TenantExportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class TenantExportApiTest extends TestCase
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
            EnsureTenantContext::class,
            EnsureTenantUser::class,
        ]);
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);
        $this->admin = User::query()->create([
            'name' => 'Export Admin',
            'email' => 'export-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
        Storage::fake('s3');
    }

    public function test_export_request_is_idempotent_and_dispatched_to_exports_queue(): void
    {
        Queue::fake();
        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'type' => TenantExportType::TERRENOS_PDF->value,
            'filters' => ['ufs' => ['SP']],
        ];

        $first = $this->actingAs($this->admin)
            ->postJson('/api/v1/exports', $payload)
            ->assertStatus(202);
        $second = $this->actingAs($this->admin)
            ->postJson('/api/v1/exports', $payload)
            ->assertStatus(202);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(TenantExportStatus::QUEUED->value, $first->json('data.status'));
        Queue::assertPushedOn('exports', GenerateTenantExportJob::class);
        Queue::assertPushed(GenerateTenantExportJob::class, 1);
    }

    public function test_export_request_validates_type_specific_subject_filters_and_payload(): void
    {
        $base = ['idempotency_key' => (string) Str::uuid()];

        $this->actingAs($this->admin)->postJson('/api/v1/exports', [
            ...$base,
            'type' => TenantExportType::TERRENO_DETAIL_PDF->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('subject_id');

        $this->actingAs($this->admin)->postJson('/api/v1/exports', [
            ...$base,
            'type' => TenantExportType::TERRENOS_EXCEL->value,
            'subject_id' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('subject_id');

        $this->actingAs($this->admin)->postJson('/api/v1/exports', [
            ...$base,
            'type' => TenantExportType::TERRENOS_PDF->value,
            'payload' => ['observacoes' => 'não permitido'],
        ])->assertUnprocessable()->assertJsonValidationErrors('payload');
    }

    public function test_export_subject_must_exist_in_the_current_tenant_schema(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)->postJson('/api/v1/exports', [
            'idempotency_key' => (string) Str::uuid(),
            'type' => TenantExportType::TERRENO_DETAIL_PDF->value,
            'subject_id' => 999999,
        ])->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_job_claims_generation_once_and_persists_safe_artifact_metadata(): void
    {
        $generation = TenantExportGeneration::factory()
            ->for($this->admin, 'requester')
            ->createOne();
        $generator = $this->createMock(TenantExportGenerator::class);
        $generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'storage_disk' => 's3',
                'storage_path' => 'exports/1/report.pdf',
                'file_name' => 'report.pdf',
                'mime_type' => 'application/pdf',
                'size' => 123,
            ]);
        $job = new GenerateTenantExportJob($generation->id);

        $job->handle(app(TenantExportGenerationRepository::class), $generator);
        $job->handle(app(TenantExportGenerationRepository::class), $generator);

        $this->assertDatabaseHas('tenant_export_generations', [
            'id' => $generation->id,
            'status' => TenantExportStatus::COMPLETED->value,
            'progress' => 100,
            'storage_path' => 'exports/1/report.pdf',
            'size' => 123,
        ]);
    }

    public function test_job_reclaims_processing_generation_after_worker_visibility_timeout(): void
    {
        $generation = TenantExportGeneration::factory()
            ->for($this->admin, 'requester')
            ->createOne([
                'status' => TenantExportStatus::PROCESSING,
                'progress' => 10,
                'started_at' => now()->subMinutes(11),
                'updated_at' => now()->subMinutes(11),
            ]);
        $generator = $this->createMock(TenantExportGenerator::class);
        $generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'storage_disk' => 's3',
                'storage_path' => 'exports/1/recovered.pdf',
                'file_name' => 'recovered.pdf',
                'mime_type' => 'application/pdf',
                'size' => 321,
            ]);

        (new GenerateTenantExportJob($generation->id))
            ->handle(app(TenantExportGenerationRepository::class), $generator);

        $this->assertDatabaseHas('tenant_export_generations', [
            'id' => $generation->id,
            'status' => TenantExportStatus::COMPLETED->value,
            'storage_path' => 'exports/1/recovered.pdf',
        ]);
    }

    public function test_failed_attempt_is_released_for_retry_and_failed_handler_is_safe(): void
    {
        $generation = TenantExportGeneration::factory()
            ->for($this->admin, 'requester')
            ->createOne();
        $generator = $this->createMock(TenantExportGenerator::class);
        $generator->method('generate')->willThrowException(new RuntimeException('internal detail'));
        $job = new GenerateTenantExportJob($generation->id);

        try {
            $job->handle(app(TenantExportGenerationRepository::class), $generator);
            $this->fail('A falha da geração deveria ser propagada para o worker.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('tenant_export_generations', [
                'id' => $generation->id,
                'status' => TenantExportStatus::QUEUED->value,
                'progress' => 0,
            ]);
        }

        $job->failed(new RuntimeException('internal detail'));

        $this->assertDatabaseHas('tenant_export_generations', [
            'id' => $generation->id,
            'status' => TenantExportStatus::FAILED->value,
            'error_message' => 'EXPORT_GENERATION_FAILED',
        ]);
    }

    public function test_only_requester_can_poll_and_download_completed_export(): void
    {
        $path = 'exports/10/listagem-terrenos.pdf';
        Storage::disk('s3')->put($path, 'pdf-content');
        $generation = TenantExportGeneration::factory()
            ->for($this->admin, 'requester')
            ->createOne([
                'status' => TenantExportStatus::COMPLETED,
                'progress' => 100,
                'storage_disk' => 's3',
                'storage_path' => $path,
                'file_name' => 'listagem-terrenos.pdf',
                'mime_type' => 'application/pdf',
                'size' => 11,
                'completed_at' => now(),
                'expires_at' => now()->addHour(),
            ]);
        $otherUser = User::query()->create([
            'name' => 'Other Admin',
            'email' => 'other-export-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $otherUser->assignRole(RolesEnum::ADMIN);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/exports/{$generation->id}")
            ->assertOk()
            ->assertJsonPath('data.download_url', route('tenant.exports.download', ['export' => $generation->id]));

        $this->actingAs($this->admin)
            ->get("/api/v1/exports/{$generation->id}/download")
            ->assertOk()
            ->assertDownload('listagem-terrenos.pdf');

        $this->actingAs($otherUser)
            ->getJson("/api/v1/exports/{$generation->id}")
            ->assertNotFound();
    }

    public function test_expired_export_is_not_downloadable(): void
    {
        $generation = TenantExportGeneration::factory()
            ->for($this->admin, 'requester')
            ->createOne([
                'status' => TenantExportStatus::COMPLETED,
                'storage_disk' => 's3',
                'storage_path' => 'exports/expired.pdf',
                'file_name' => 'expired.pdf',
                'mime_type' => 'application/pdf',
                'completed_at' => now()->subHours(2),
                'expires_at' => now()->subHour(),
            ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/exports/{$generation->id}")
            ->assertOk()
            ->assertJsonPath('data.download_url', null);

        $this->actingAs($this->admin)
            ->get("/api/v1/exports/{$generation->id}/download")
            ->assertNotFound();
    }
}
