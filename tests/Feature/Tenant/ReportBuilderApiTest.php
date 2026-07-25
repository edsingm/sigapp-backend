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
use App\Jobs\GenerateReportRunJob;
use App\Models\Tenant\ReportRun;
use App\Models\Tenant\User;
use App\Repositories\Tenant\ReportRunRepository;
use App\Services\Tenant\ReportGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportBuilderApiTest extends TestCase
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
            CheckFeature::class,
        ]);
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);
        $this->admin = User::create([
            'name' => 'Report Admin',
            'email' => 'report-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
        Storage::fake('s3');
    }

    public function test_report_template_validates_catalog_and_run_is_idempotent(): void
    {
        $template = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Pipeline por status',
            'definition' => [
                'datasets' => ['terrenos'],
                'dimensions' => ['workflow_status_code'],
                'metrics' => ['count'],
                'charts' => ['bar'],
            ],
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'SQL livre',
            'definition' => [
                'datasets' => ['users'],
                'dimensions' => ['email'],
                'metrics' => ['count'],
            ],
        ])->assertUnprocessable();

        Queue::fake();
        $key = (string) Str::uuid();
        $payload = [
            'template_id' => $template,
            'idempotency_key' => $key,
            'filters' => ['estado' => 'SP'],
        ];
        $first = $this->actingAs($this->admin)->postJson('/api/v1/reports/runs', $payload)
            ->assertStatus(202);
        $second = $this->actingAs($this->admin)->postJson('/api/v1/reports/runs', $payload)
            ->assertStatus(202);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        Queue::assertPushed(GenerateReportRunJob::class, 1);
    }

    public function test_report_job_generates_private_csv_snapshot(): void
    {
        $template = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Status',
            'definition' => [
                'datasets' => ['terrenos'],
                'dimensions' => ['workflow_status_code'],
                'metrics' => ['count'],
            ],
        ])->json('data.id');
        $this->actingAs($this->admin)->postJson('/api/v1/terrenos', ['nome' => 'Relatório Norte']);

        Queue::fake();
        $runId = $this->actingAs($this->admin)->postJson('/api/v1/reports/runs', [
            'template_id' => $template,
            'idempotency_key' => (string) Str::uuid(),
        ])->json('data.id');

        app(ReportGenerationService::class)->generate(ReportRun::query()->findOrFail((int) $runId));

        $this->assertDatabaseHas('report_runs', ['id' => $runId, 'status' => 'completed']);
        Storage::disk('s3')->assertExists('reports/runs/'.$runId.'.csv');
    }

    public function test_report_job_claims_the_same_run_only_once(): void
    {
        $template = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Status',
            'definition' => [
                'datasets' => ['terrenos'],
                'dimensions' => ['workflow_status_code'],
                'metrics' => ['count'],
            ],
        ])->json('data.id');

        Queue::fake();
        $runId = (int) $this->actingAs($this->admin)->postJson('/api/v1/reports/runs', [
            'template_id' => $template,
            'idempotency_key' => (string) Str::uuid(),
        ])->json('data.id');
        $service = $this->createMock(ReportGenerationService::class);
        $service->expects($this->once())
            ->method('generate')
            ->willReturnCallback(static function (ReportRun $run): void {
                $run->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'completed_at' => now(),
                ]);
            });
        $job = new GenerateReportRunJob($runId);

        $job->handle(app(ReportRunRepository::class), $service);
        $job->handle(app(ReportRunRepository::class), $service);

        $this->assertDatabaseHas('report_runs', [
            'id' => $runId,
            'status' => 'completed',
            'progress' => 100,
        ]);
    }
}
