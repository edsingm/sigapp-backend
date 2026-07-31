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
    }
}
