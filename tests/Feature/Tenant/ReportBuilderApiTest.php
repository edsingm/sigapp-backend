<?php

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Exports\Tenant\ReportRunWorkbookExport;
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
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
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

    public function test_catalog_exposes_modes_columns_and_system_templates(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/catalog')
            ->assertOk()
            ->assertJsonPath('success', true);

        $datasets = collect($response->json('data.datasets'))->pluck('key')->all();
        $formats = collect($response->json('data.formats'))->pluck('key')->all();
        $modes = collect($response->json('data.modes'))->pluck('key')->all();

        $this->assertContains('terrenos', $datasets);
        $this->assertContains('negociacoes', $datasets);
        $this->assertContains('comite_reunioes', $datasets);
        $this->assertContains('projetos', $datasets);
        $this->assertContains('deal_ofertas', $datasets);
        $this->assertContains('comite_dossies', $datasets);
        $this->assertSame(['csv', 'xlsx', 'pdf'], $formats);
        $this->assertEqualsCanonicalizing(['aggregate', 'detail'], $modes);
        $this->assertNotEmpty($response->json('data.predefined_exports'));
        $this->assertNotEmpty($response->json('data.recommendations'));
        $this->assertNotEmpty($response->json('data.system_templates'));
        $this->assertSame(['daily', 'weekly', 'monthly'], $response->json('data.schedule_frequencies'));

        $terrenos = collect($response->json('data.datasets'))->firstWhere('key', 'terrenos');
        $this->assertNotEmpty($terrenos['columns'] ?? []);
        $legal = collect($response->json('data.datasets'))->firstWhere('key', 'legalizacoes');
        $legalMetrics = collect($legal['metrics'] ?? [])->pluck('key')->all();
        $this->assertContains('sum_custo_realizado', $legalMetrics);
    }

    public function test_list_templates_seeds_system_templates(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/templates')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('[Sistema] Funil executivo', $names);
        $this->assertContains('[Sistema] Book de negociações', $names);

        $system = collect($response->json('data'))->firstWhere('name', '[Sistema] Funil executivo');
        $this->assertTrue((bool) ($system['is_system'] ?? false));
        $this->assertSame('aggregate', $system['definition']['mode'] ?? null);
        $this->assertContains('terrenos', $system['definition']['datasets'] ?? []);
    }

    public function test_report_template_validates_catalog_and_run_is_idempotent(): void
    {
        $template = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Pipeline por status',
            'definition' => [
                'mode' => 'aggregate',
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

        $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Métrica inválida no dataset',
            'definition' => [
                'datasets' => ['comites'],
                'dimensions' => ['status'],
                'metrics' => ['sum_valor'],
            ],
        ])->assertUnprocessable();

        Queue::fake();
        $key = (string) Str::uuid();
        $payload = [
            'template_id' => $template,
            'idempotency_key' => $key,
            'filters' => ['estado' => 'SP'],
            'format' => 'xlsx',
        ];
        $first = $this->actingAs($this->admin)->postJson('/api/v1/reports/runs', $payload)
            ->assertStatus(202);
        $second = $this->actingAs($this->admin)->postJson('/api/v1/reports/runs', $payload)
            ->assertStatus(202);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame('xlsx', $first->json('data.format'));
        Queue::assertPushed(GenerateReportRunJob::class, 1);
    }

    public function test_detail_mode_template_and_csv_snapshot(): void
    {
        $template = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Detalhe terrenos',
            'definition' => [
                'mode' => 'detail',
                'datasets' => ['terrenos'],
                'columns' => ['id', 'nome', 'estado', 'valor', 'created_at'],
                'charts' => ['table'],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame('detail', $template['definition']['mode']);
        $this->assertContains('nome', $template['definition']['columns']);

        $this->actingAs($this->admin)->postJson('/api/v1/terrenos', [
            'nome' => 'Lote Wave 2',
            'valor' => 250000,
            'estado' => 'SP',
        ]);

        Queue::fake();
        $runId = $this->actingAs($this->admin)->postJson('/api/v1/reports/runs', [
            'template_id' => $template['id'],
            'idempotency_key' => (string) Str::uuid(),
            'format' => 'csv',
        ])->json('data.id');

        app(ReportGenerationService::class)->generate(ReportRun::query()->findOrFail((int) $runId));

        $this->assertDatabaseHas('report_runs', ['id' => $runId, 'status' => 'completed']);
        $contents = (string) Storage::disk('s3')->get('reports/runs/'.$runId.'.csv');
        $this->assertStringContainsString('Lote Wave 2', $contents);
        $this->assertStringContainsString('detail', $contents);
        $this->assertStringContainsString('250000', $contents);
    }

    public function test_multi_dataset_aggregate_builds_sections(): void
    {
        $template = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Funil multi',
            'definition' => [
                'mode' => 'aggregate',
                'datasets' => ['terrenos', 'viabilidades'],
                'dimensions' => ['status', 'workflow_status_code'],
                'metrics' => ['count', 'sum_valor'],
            ],
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin)->postJson('/api/v1/terrenos', [
            'nome' => 'Multi A',
            'valor' => 1000,
            'estado' => 'RJ',
        ]);

        $run = ReportRun::query()->create([
            'report_template_id' => $template,
            'requested_by' => $this->admin->id,
            'idempotency_key' => (string) Str::uuid(),
            'definition_snapshot' => [
                'mode' => 'aggregate',
                'datasets' => ['terrenos', 'viabilidades'],
                'dimensions' => ['status', 'workflow_status_code'],
                'metrics' => ['count', 'sum_valor'],
            ],
            'filters' => [],
            'format' => 'csv',
            'status' => 'pending',
            'progress' => 0,
            'requested_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $sections = app(ReportGenerationService::class)->buildSections(
            $run->definition_snapshot,
            [],
        );

        $this->assertCount(2, $sections);
        $this->assertSame('terrenos', $sections[0]['dataset']);
        $this->assertSame('viabilidades', $sections[1]['dataset']);
        $this->assertSame('aggregate', $sections[0]['mode']);
        $this->assertNotEmpty($sections[0]['rows']);
    }

    public function test_report_job_generates_private_csv_snapshot(): void
    {
        $template = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Status',
            'definition' => [
                'datasets' => ['terrenos'],
                'dimensions' => ['workflow_status_code'],
                'metrics' => ['count', 'sum_valor'],
            ],
        ])->json('data.id');
        $this->actingAs($this->admin)->postJson('/api/v1/terrenos', [
            'nome' => 'Relatório Norte',
            'valor' => 150000,
            'estado' => 'SP',
        ]);

        Queue::fake();
        $runId = $this->actingAs($this->admin)->postJson('/api/v1/reports/runs', [
            'template_id' => $template,
            'idempotency_key' => (string) Str::uuid(),
            'format' => 'csv',
        ])->json('data.id');

        app(ReportGenerationService::class)->generate(ReportRun::query()->findOrFail((int) $runId));

        $this->assertDatabaseHas('report_runs', ['id' => $runId, 'status' => 'completed', 'mime_type' => 'text/csv']);
        Storage::disk('s3')->assertExists('reports/runs/'.$runId.'.csv');
        $contents = Storage::disk('s3')->get('reports/runs/'.$runId.'.csv');
        $this->assertStringContainsString('sum_valor', (string) $contents);
        $this->assertStringContainsString('150000', (string) $contents);
    }

    public function test_report_job_generates_excel_workbook(): void
    {
        $template = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Negociações',
            'definition' => [
                'datasets' => ['negociacoes'],
                'dimensions' => ['status'],
                'metrics' => ['count', 'sum_valor'],
            ],
        ])->json('data.id');

        Queue::fake();
        Excel::fake();
        $runId = (int) $this->actingAs($this->admin)->postJson('/api/v1/reports/runs', [
            'template_id' => $template,
            'idempotency_key' => (string) Str::uuid(),
            'format' => 'xlsx',
        ])->json('data.id');

        $path = 'reports/runs/'.$runId.'.xlsx';
        Storage::disk('s3')->put($path, 'xlsx-bytes');

        app(ReportGenerationService::class)->generate(ReportRun::query()->findOrFail($runId));

        Excel::assertStored(
            $path,
            's3',
            fn (ReportRunWorkbookExport $export): bool => count($export->sheets()) >= 1,
        );
        $this->assertDatabaseHas('report_runs', [
            'id' => $runId,
            'status' => 'completed',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function test_report_job_generates_pdf_snapshot(): void
    {
        $template = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Comitês PDF',
            'definition' => [
                'datasets' => ['comites'],
                'dimensions' => ['status'],
                'metrics' => ['count'],
                'charts' => ['table'],
            ],
        ])->json('data.id');

        Queue::fake();
        $pdf = Pdf::fake();
        $runId = (int) $this->actingAs($this->admin)->postJson('/api/v1/reports/runs', [
            'template_id' => $template,
            'idempotency_key' => (string) Str::uuid(),
            'format' => 'pdf',
        ])->json('data.id');

        $path = 'reports/runs/'.$runId.'.pdf';
        Storage::disk('s3')->put($path, 'pdf-bytes');

        app(ReportGenerationService::class)->generate(
            ReportRun::query()->with(['template', 'requester'])->findOrFail($runId),
        );

        $pdf->assertViewIs('exports.report-builder-pdf');
        $pdf->assertSaved($path);
        $this->assertDatabaseHas('report_runs', [
            'id' => $runId,
            'status' => 'completed',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_system_template_cannot_be_deleted(): void
    {
        $list = $this->actingAs($this->admin)->getJson('/api/v1/reports/templates')->json('data');
        $systemId = collect($list)->firstWhere('is_system', true)['id'] ?? null;
        $this->assertNotNull($systemId);

        $this->actingAs($this->admin)
            ->deleteJson('/api/v1/reports/templates/'.$systemId)
            ->assertStatus(422);
    }

    public function test_download_uses_format_extension(): void
    {
        $template = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Download',
            'definition' => [
                'datasets' => ['terrenos'],
                'dimensions' => ['estado'],
                'metrics' => ['count'],
            ],
        ])->json('data.id');

        Queue::fake();
        $runId = (int) $this->actingAs($this->admin)->postJson('/api/v1/reports/runs', [
            'template_id' => $template,
            'idempotency_key' => (string) Str::uuid(),
            'format' => 'csv',
        ])->json('data.id');

        app(ReportGenerationService::class)->generate(ReportRun::query()->findOrFail($runId));

        $this->actingAs($this->admin)
            ->get('/api/v1/reports/runs/'.$runId.'/download')
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=relatorio-'.$runId.'.csv');
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
