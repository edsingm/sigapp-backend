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
use App\Jobs\GenerateReportRunJob;
use App\Models\Tenant\ComiteAiDossier;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\ReportRun;
use App\Models\Tenant\ReportSchedule;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Notifications\ReportScheduleReadyNotification;
use App\Services\Tenant\CommitteeAiDossierPdfService;
use App\Services\Tenant\ReportGenerationService;
use App\Services\Tenant\ReportScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportBuilderWave3Test extends TestCase
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
            'name' => 'Wave3 Admin',
            'email' => 'wave3-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
        Storage::fake('s3');
    }

    public function test_schedule_crud_and_dispatch_due(): void
    {
        $templateId = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Agendado',
            'definition' => [
                'datasets' => ['terrenos'],
                'dimensions' => ['workflow_status_code'],
                'metrics' => ['count'],
            ],
        ])->assertCreated()->json('data.id');

        Queue::fake();
        $scheduleId = $this->actingAs($this->admin)->postJson('/api/v1/reports/schedules', [
            'name' => 'Pipeline diário',
            'template_id' => $templateId,
            'frequency' => 'daily',
            'format' => 'csv',
            'notify_email' => true,
        ])->assertCreated()->json('data.id');

        ReportSchedule::query()->whereKey($scheduleId)->update(['next_run_at' => now()->subMinute()]);

        $dispatched = app(ReportScheduleService::class)->dispatchDue();
        $this->assertSame(1, $dispatched);
        Queue::assertPushed(GenerateReportRunJob::class, 1);

        $this->assertDatabaseHas('report_schedules', [
            'id' => $scheduleId,
            'is_active' => 1,
        ]);
        $this->assertNotNull(ReportSchedule::query()->find($scheduleId)?->last_run_id);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/schedules')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Pipeline diário');

        $this->actingAs($this->admin)
            ->deleteJson('/api/v1/reports/schedules/'.$scheduleId)
            ->assertNoContent();
    }

    public function test_scheduled_run_notifies_owner_on_completion(): void
    {
        Notification::fake();
        $templateId = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Notify me',
            'definition' => [
                'datasets' => ['terrenos'],
                'dimensions' => ['estado'],
                'metrics' => ['count'],
            ],
        ])->json('data.id');

        $schedule = ReportSchedule::query()->create([
            'report_template_id' => $templateId,
            'owner_id' => $this->admin->id,
            'name' => 'Notify schedule',
            'frequency' => 'daily',
            'format' => 'csv',
            'filters' => [],
            'notify_email' => true,
            'is_active' => true,
            'next_run_at' => now()->addDay(),
        ]);

        $run = ReportRun::query()->create([
            'report_template_id' => $templateId,
            'report_schedule_id' => $schedule->id,
            'requested_by' => $this->admin->id,
            'idempotency_key' => (string) Str::uuid(),
            'definition_snapshot' => [
                'mode' => 'aggregate',
                'datasets' => ['terrenos'],
                'dimensions' => ['estado'],
                'metrics' => ['count'],
            ],
            'filters' => [],
            'format' => 'csv',
            'status' => 'pending',
            'progress' => 0,
            'requested_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        app(ReportGenerationService::class)->generate(
            ReportRun::query()->with(['requester', 'schedule', 'template'])->findOrFail($run->id),
        );

        Notification::assertSentTo($this->admin, ReportScheduleReadyNotification::class);
        $this->assertDatabaseHas('report_runs', ['id' => $run->id, 'status' => 'completed']);
    }

    public function test_legalizacao_rich_metrics_and_deal_dataset(): void
    {
        $terreno = Terreno::query()->create([
            'nome' => 'Terreno Legal',
            'estado' => 'SP',
            'created_by' => $this->admin->id,
        ]);

        $legalizacaoId = DB::table('legalizacoes')->insertGetId([
            'terreno_id' => $terreno->id,
            'nome' => 'Legalização Norte',
            'status' => 'em_andamento',
            'percentual_concluido' => 40,
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('legalizacao_etapas')->insert([
            'legalizacao_id' => $legalizacaoId,
            'titulo' => 'Etapa A',
            'ordem' => 1,
            'status' => 'em_andamento',
            'inicio_planejado' => now()->toDateString(),
            'fim_planejado' => now()->addDays(5)->toDateString(),
            'percentual' => 50,
            'valor_custo' => 1000,
            'custo_pago' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('legalizacao_etapas')->insert([
            'legalizacao_id' => $legalizacaoId,
            'titulo' => 'Etapa B',
            'ordem' => 2,
            'status' => 'pendente',
            'inicio_planejado' => now()->addDays(6)->toDateString(),
            'fim_planejado' => now()->addDays(10)->toDateString(),
            'percentual' => 0,
            'valor_custo' => 500,
            'custo_pago' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = app(ReportGenerationService::class)->buildAggregateRows(
            'legalizacoes',
            'status',
            ['count', 'sum_custo_planejado', 'sum_custo_realizado', 'avg_critical_days'],
            [],
        );

        $this->assertNotEmpty($rows);
        $group = collect($rows)->firstWhere('label', 'em_andamento');
        $this->assertNotNull($group);
        $this->assertSame(1, $group['count']);
        $this->assertEquals(1500.0, $group['sum_custo_planejado']);
        $this->assertEquals(1000.0, $group['sum_custo_realizado']);
        $this->assertNotNull($group['avg_critical_days']);

        $template = $this->actingAs($this->admin)->postJson('/api/v1/reports/templates', [
            'name' => 'Deal ofertas',
            'definition' => [
                'mode' => 'detail',
                'datasets' => ['deal_ofertas'],
                'columns' => ['id', 'negociacao_id', 'amount', 'status'],
            ],
        ])->assertCreated();

        $this->assertSame('detail', $template->json('data.definition.mode'));
        $this->assertContains('deal_ofertas', $template->json('data.definition.datasets'));
    }

    public function test_committee_dossier_pdf_export(): void
    {
        $terreno = Terreno::query()->create([
            'nome' => 'Terreno Comitê',
            'estado' => 'RJ',
            'created_by' => $this->admin->id,
        ]);
        $viabilidade = Viabilidade::query()->create([
            'terreno_id' => $terreno->id,
            'status' => 'aprovada',
            'created_by' => $this->admin->id,
        ]);
        $review = ComiteRevisao::query()->create([
            'terreno_id' => $terreno->id,
            'viabilidade_id' => $viabilidade->id,
            'status' => 'aguardando_comite',
        ]);
        ComiteAiDossier::query()->create([
            'comite_revisao_id' => $review->id,
            'terreno_id' => $terreno->id,
            'viabilidade_id' => $viabilidade->id,
            'status' => 'ready',
            'prompt_version' => 1,
            'input_hash' => str_repeat('a', 64),
            'sections' => [
                'resumo' => 'Resumo executivo do dossiê.',
                'riscos' => 'Riscos jurídicos moderados.',
            ],
            'generated_at' => now(),
        ]);

        $service = app(CommitteeAiDossierPdfService::class);
        $payload = $service->previewPayload($review->id);
        $this->assertSame('ready', $payload['status']);
        $this->assertCount(2, $payload['sections']);
        $this->assertSame('RESUMO', $payload['sections'][0]['title']);

        // Pdf::fake só rastreia save(); download() devolve builder fake sem persistência.
        Pdf::fake();
        $response = $service->download($review->id);
        $this->assertNotNull($response);
    }

    public function test_chart_bars_are_built_for_aggregate_sections(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/terrenos', [
            'nome' => 'Chart A',
            'estado' => 'SP',
            'valor' => 10,
        ]);
        $this->actingAs($this->admin)->postJson('/api/v1/terrenos', [
            'nome' => 'Chart B',
            'estado' => 'RJ',
            'valor' => 20,
        ]);

        $sections = app(ReportGenerationService::class)->buildSections([
            'mode' => 'aggregate',
            'datasets' => ['terrenos'],
            'dimensions' => ['estado'],
            'metrics' => ['count'],
            'charts' => ['bar', 'table'],
        ], []);

        $this->assertNotEmpty($sections[0]['chart_bars'] ?? []);
        $this->assertArrayHasKey('percent', $sections[0]['chart_bars'][0]);
    }
}
