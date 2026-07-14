<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Repositories\Tenant\DashboardRepository;
use App\Services\Dashboard\DashboardQueryService;
use Database\Factories\Tenant\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
    }

    public function test_cards_preserve_counts_and_signed_vgv_payload(): void
    {
        $signed = Terreno::create([
            'nome' => 'Terreno contratado',
            'workflow_status_code' => WorkflowStatus::CONTRATO_ASSINADO->value,
        ]);
        Terreno::create([
            'nome' => 'Terreno legalizando',
            'workflow_status_code' => WorkflowStatus::LEGALIZANDO->value,
        ]);
        TerrenoProduto::create([
            'terreno_id' => $signed->id,
            'unidades' => 12,
            'valor' => 150000,
        ]);

        $cards = app(DashboardQueryService::class)->cards();

        $this->assertSame(2, $cards['total_terrenos']);
        $this->assertSame(1, $cards['total_contrato_assinado']);
        $this->assertSame(1, $cards['total_legalizando']);
        $this->assertSame(1800000.0, $cards['vgv_contrato_assinado']);
    }

    public function test_status_chart_preserves_status_metadata_and_available_years(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno em análise',
            'workflow_status_code' => WorkflowStatus::EM_ANALISE->value,
        ]);
        $terreno->forceFill(['created_at' => now()->setYear(2025)])->saveQuietly();

        $result = app(DashboardQueryService::class)->statusChart('2025');

        $this->assertSame([2025], $result['anos_disponiveis']);
        $this->assertSame([
            'status_code' => WorkflowStatus::EM_ANALISE->value,
            'status_nome' => WorkflowStatus::EM_ANALISE->label(),
            'status_cor' => WorkflowStatus::EM_ANALISE->color(),
            'total' => 1,
        ], $result['status_data']->first());
    }

    public function test_build_overview_includes_only_requested_sections(): void
    {
        Terreno::create([
            'nome' => 'Terreno em análise',
            'workflow_status_code' => WorkflowStatus::EM_ANALISE->value,
        ]);

        $result = app(DashboardQueryService::class)->buildOverview(
            include: ['cards', 'status_chart'],
            ano: null,
            mes: null,
            meses: 12,
            topLimit: 5,
            areaLimit: 5,
            responsavelId: null,
        );

        $this->assertSame(['cards', 'status_chart'], array_keys($result));
        $this->assertSame(1, $result['cards']['total_terrenos']);
        $this->assertSame(WorkflowStatus::EM_ANALISE->value, $result['status_chart']->first()['status_code']);
    }

    public function test_management_overview_preserves_shape_clamps_parameters_and_batches_geography_totals(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno contratado',
            'cidade_code' => '3550308',
            'workflow_status_code' => WorkflowStatus::CONTRATO_ASSINADO->value,
        ]);
        TerrenoProduto::create(['terreno_id' => $terreno->id, 'unidades' => 4, 'valor' => 250000]);

        $result = app(DashboardQueryService::class)->managementOverview(0, 200, 100);

        $this->assertSame(['stale_days' => 1, 'critical_days' => 90, 'limit' => 25], $result['parameters']);
        $this->assertSame(1, $result['executive_summary']['total_terrenos']);
        $this->assertSame(1000000.0, $result['executive_summary']['vgv_signed']);
        $this->assertSame(1000000.0, $result['geography'][0]['vgv_total']);
        $this->assertSame(4, $result['geography'][0]['total_unidades']);
        $this->assertCount(count(WorkflowStatus::cases()), $result['funnel']);
        $this->assertContains(
            WorkflowStatus::DESCARTADO->value,
            array_column($result['funnel'], 'status_code'),
        );
        $this->assertSame(
            ['generated_at', 'parameters', 'executive_summary', 'funnel', 'financial_health', 'operational_health', 'alerts', 'team_performance', 'geography'],
            array_keys($result),
        );
    }

    public function test_monthly_queries_keep_the_relative_period_filter_without_an_explicit_year(): void
    {
        $recentOwner = UserFactory::new()->createOne();
        $oldOwner = UserFactory::new()->createOne();
        $recent = Terreno::create(['nome' => 'Recente', 'responsavel_id' => $recentOwner->id]);
        $old = Terreno::create(['nome' => 'Antigo', 'responsavel_id' => $oldOwner->id]);
        $old->forceFill(['created_at' => now()->subMonths(18)])->saveQuietly();

        $service = app(DashboardQueryService::class);
        $monthly = $service->cadastrosMensais(null, 12, null, null);
        $byResponsible = $service->cadastrosMensaisPorResponsavel(null, 12, null, null, null);

        $this->assertSame(1, $monthly['cadastros']->sum('total'));
        $this->assertSame([$recent->responsavel_id], $byResponsible->pluck('responsavel_id')->all());
    }

    public function test_geography_product_totals_are_loaded_in_one_batch_query(): void
    {
        $first = Terreno::create(['nome' => 'São Paulo', 'cidade_code' => '3550308']);
        $second = Terreno::create(['nome' => 'Campinas', 'cidade_code' => '3509502']);
        TerrenoProduto::create(['terreno_id' => $first->id, 'unidades' => 2, 'valor' => 100000]);
        TerrenoProduto::create(['terreno_id' => $second->id, 'unidades' => 3, 'valor' => 200000]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $totals = app(DashboardRepository::class)->productTotalsByCity(['3550308', '3509502']);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
        $this->assertSame(200000.0, (float) $totals->get('3550308')->vgv);
        $this->assertSame(600000.0, (float) $totals->get('3509502')->vgv);
    }
}
