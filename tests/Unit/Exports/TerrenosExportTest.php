<?php

declare(strict_types=1);

namespace Tests\Unit\Exports;

use App\Enums\WorkflowStatus;
use App\Exports\Tenant\TerrenosExport;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TerrenosExportTest extends TestCase
{
    public function test_map_neutralizes_formula_injection_in_text_columns(): void
    {
        $terreno = (object) [
            'id' => 10,
            'nome' => '=HYPERLINK("https://example.com")',
            'cidade' => (object) ['name' => "\tCidade"],
            'cidade_code' => null,
            'estado' => '-UF',
            'area_calculada' => 1200.5,
            'total_unidades' => 20,
            'valor' => -500000,
            'responsavel' => (object) ['name' => '@Responsável'],
            'workflow_status_code' => WorkflowStatus::EM_ANALISE->value,
            'created_at' => Carbon::parse('2026-07-25'),
            'regional' => (object) ['nome' => 'Regional +Centro'],
        ];

        $row = (new TerrenosExport)->map($terreno);

        self::assertSame('\'=HYPERLINK("https://example.com")', $row[1]);
        self::assertSame("'\tCidade", $row[2]);
        self::assertSame("'-UF", $row[3]);
        self::assertSame(-500000, $row[6]);
        self::assertSame("'@Responsável", $row[7]);
        self::assertSame("'+Centro", $row[10]);
    }
}
