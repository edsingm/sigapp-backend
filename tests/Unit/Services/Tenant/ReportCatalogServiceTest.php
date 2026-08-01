<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tenant;

use App\Services\Tenant\ReportCatalogService;
use Tests\TestCase;

final class ReportCatalogServiceTest extends TestCase
{
    public function test_catalog_covers_wave3_datasets_and_legal_metrics(): void
    {
        $catalog = new ReportCatalogService;
        $payload = $catalog->catalog();

        $datasetKeys = array_column($payload['datasets'], 'key');
        $this->assertEqualsCanonicalizing(
            [
                'terrenos',
                'viabilidades',
                'comites',
                'legalizacoes',
                'negociacoes',
                'comite_reunioes',
                'projetos',
                'deal_ofertas',
                'deal_aprovacoes',
                'deal_condicoes',
                'comite_dossies',
            ],
            $datasetKeys,
        );
        $this->assertSame(['csv', 'xlsx', 'pdf'], $catalog->formatKeys());
        $this->assertEqualsCanonicalizing(['aggregate', 'detail'], $catalog->modeKeys());
        $this->assertSame('exports.excel', $catalog->featureForFormat('xlsx'));
        $this->assertSame('exports.pdf', $catalog->featureForFormat('pdf'));
        $this->assertNull($catalog->featureForFormat('csv'));
        $this->assertContains('sum_custo_realizado', $catalog->metricKeysFor('legalizacoes'));
        $this->assertContains('avg_critical_days', $catalog->metricKeysFor('legalizacoes'));
        $this->assertContains('amount', $catalog->columnKeysFor('deal_ofertas'));
        $this->assertSame(['daily', 'weekly', 'monthly'], $payload['schedule_frequencies']);
        $this->assertTrue(
            collect($payload['predefined_exports'])->contains(fn (array $item): bool => $item['key'] === 'committee_ai_dossier_pdf'),
        );
        $this->assertTrue(
            collect($payload['system_templates'])->contains(fn (array $item): bool => $item['system_key'] === 'deal_room_ofertas'),
        );

        $terrenosColumns = $catalog->columnKeysFor('terrenos');
        $this->assertContains('nome', $terrenosColumns);
        $this->assertContains('endereco', $terrenosColumns);
        $this->assertContains('responsavel_id', $terrenosColumns);
        $this->assertContains('workflow_status_code', $terrenosColumns);
        $this->assertContains('area_total', $terrenosColumns);
        $this->assertContains('data_contrato', $terrenosColumns);
        $this->assertContains('observacoes', $terrenosColumns);
        $this->assertGreaterThanOrEqual(30, count($terrenosColumns));
        $this->assertSame(
            ReportCatalogService::DETAIL_COLUMNS_MAX,
            $payload['limits']['columns_per_template'] ?? null,
        );
        // JSON pesados não entram no modo detalhe tabular.
        $this->assertNotContains('polygon_coords', $terrenosColumns);
        $this->assertNotContains('app_polygons', $terrenosColumns);
        $this->assertNotContains('qualification_data', $terrenosColumns);

        $viabilidadesColumns = $catalog->columnKeysFor('viabilidades');
        $this->assertContains('terreno_id', $viabilidadesColumns);
        $this->assertContains('version', $viabilidadesColumns);
        $this->assertContains('is_current', $viabilidadesColumns);
        $this->assertContains('approval_status', $viabilidadesColumns);
        $this->assertContains('parceria_vgv', $viabilidadesColumns);
        $this->assertContains('data_lancamento', $viabilidadesColumns);
        $this->assertContains('perfil_financiamento', $viabilidadesColumns);
        $this->assertContains('taxa_juros_pj', $viabilidadesColumns);
        $this->assertContains('usar_antecipacao_pj', $viabilidadesColumns);
        $this->assertContains('bonus_equipe_comercial', $viabilidadesColumns);
        $this->assertGreaterThanOrEqual(50, count($viabilidadesColumns));
        $this->assertNotContains('resultados_dre', $viabilidadesColumns);
        $this->assertNotContains('premissas_snapshot', $viabilidadesColumns);
        $this->assertNotContains('assistencia_tecnica_curva', $viabilidadesColumns);

        $negociacoesColumns = $catalog->columnKeysFor('negociacoes');
        $this->assertContains('terreno_id', $negociacoesColumns);
        $this->assertContains('proposal_value', $negociacoesColumns);
        $this->assertContains('business_model', $negociacoesColumns);
        $this->assertContains('notes', $negociacoesColumns);
        $this->assertContains('created_by', $negociacoesColumns);
        $this->assertContains('updated_at', $negociacoesColumns);
        $this->assertGreaterThanOrEqual(10, count($negociacoesColumns));
    }
}
