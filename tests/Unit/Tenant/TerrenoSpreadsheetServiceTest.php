<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Repositories\Tenant\TerrenoImportReferenceRepository;
use App\Services\Tenant\TerrenoSpreadsheetService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class TerrenoSpreadsheetServiceTest extends TestCase
{
    public function test_template_expoe_abas_cabecalhos_e_referencias(): void
    {
        $references = $this->references([
            'users' => [['id' => 1, 'name' => 'Maria', 'email' => 'maria@example.com']],
            'regionals' => [['id' => 2, 'nome' => 'Sudeste']],
            'corretores' => [['id' => 3, 'nome' => 'João', 'email' => 'joao@example.com']],
        ]);
        $service = new TerrenoSpreadsheetService($references);

        $path = $service->createTemplate();
        $spreadsheet = IOFactory::load($path);

        $this->assertSame(['Terrenos', 'Instruções', 'Referências'], $spreadsheet->getSheetNames());
        $this->assertSame(
            TerrenoSpreadsheetService::HEADERS,
            $spreadsheet->getSheetByName('Terrenos')?->rangeToArray('A1:U1')[0],
        );
        $this->assertSame('maria@example.com', $spreadsheet->getSheetByName('Referências')?->getCell('C2')->getValue());

        $spreadsheet->disconnectWorksheets();
        @unlink($path);
    }

    public function test_read_ignora_linhas_vazias_e_detecta_formulas(): void
    {
        $service = new TerrenoSpreadsheetService($this->references());
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Terrenos');
        $sheet->fromArray(TerrenoSpreadsheetService::HEADERS, null, 'A1');
        $sheet->setCellValue('A2', 'Terreno A');
        $sheet->setCellValueExplicit('H2', '=1+1', DataType::TYPE_FORMULA);
        $sheet->setCellValue('A4', 'Terreno B');
        $path = $this->save($spreadsheet);

        $rows = $service->read($path);

        $this->assertCount(2, $rows);
        $this->assertSame([2, 4], array_column($rows, 'row_number'));
        $this->assertSame(['valor'], $rows[0]['formula_columns']);

        @unlink($path);
    }

    public function test_read_rejeita_cabecalho_desconhecido(): void
    {
        $service = new TerrenoSpreadsheetService($this->references());
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Terrenos');
        $sheet->fromArray(['nome', 'campo_inventado'], null, 'A1');
        $sheet->setCellValue('A2', 'Terreno A');
        $path = $this->save($spreadsheet);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cabeçalho desconhecido');

        try {
            $service->read($path);
        } finally {
            @unlink($path);
        }
    }

    private function save(Spreadsheet $spreadsheet): string
    {
        $basePath = tempnam(sys_get_temp_dir(), 'sigapp-spreadsheet-test-');
        if ($basePath === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário de teste.');
        }
        $path = $basePath.'.xlsx';
        @unlink($basePath);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * @param  array{users: list<array{id: int, name: string, email: string}>, regionals: list<array{id: int, nome: string}>, corretores: list<array{id: int, nome: string, email: string}>}|null  $data
     */
    private function references(?array $data = null): TerrenoImportReferenceRepository
    {
        $data ??= ['users' => [], 'regionals' => [], 'corretores' => []];

        return new class($data) extends TerrenoImportReferenceRepository
        {
            /** @param array{users: list<array{id: int, name: string, email: string}>, regionals: list<array{id: int, nome: string}>, corretores: list<array{id: int, nome: string, email: string}>} $data */
            public function __construct(private readonly array $data) {}

            public function templateReferences(): array
            {
                return $this->data;
            }
        };
    }
}
