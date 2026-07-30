<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Repositories\Tenant\TerrenoImportReferenceRepository;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class TerrenoSpreadsheetService
{
    public const int MAX_ROWS = 1000;

    /** @var list<string> */
    public const array HEADERS = [
        'nome',
        'endereco',
        'cep',
        'bairro',
        'uf',
        'cidade_codigo_ibge',
        'cidade',
        'valor',
        'zona',
        'distrito',
        'operacao_urbana',
        'data_apresentacao',
        'data_negociacao',
        'data_opcao',
        'data_descarte',
        'data_contrato',
        'observacoes',
        'responsavel_email',
        'comprador_email',
        'regional_nome',
        'corretor_email',
    ];

    /** @var list<string> */
    private const DATE_HEADERS = [
        'data_apresentacao',
        'data_negociacao',
        'data_opcao',
        'data_descarte',
        'data_contrato',
    ];

    public function __construct(
        private readonly TerrenoImportReferenceRepository $references,
    ) {}

    public function createTemplate(): string
    {
        $spreadsheet = new Spreadsheet;
        $terrainSheet = $spreadsheet->getActiveSheet();
        $terrainSheet->setTitle('Terrenos');
        $terrainSheet->fromArray(self::HEADERS, null, 'A1');
        $terrainSheet->freezePane('A2');
        $terrainSheet->getStyle('A1:U1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $terrainSheet->getStyle('A1:U1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F4E78');
        $terrainSheet->getColumnDimension('A')->setWidth(28);
        foreach (range('B', 'U') as $column) {
            $terrainSheet->getColumnDimension($column)->setWidth(22);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instruções');
        $instructions->fromArray([
            ['Importação de terrenos'],
            ['Somente a coluna nome é obrigatória. Não altere os cabeçalhos da aba Terrenos.'],
            ['Use datas no formato AAAA-MM-DD e não utilize fórmulas.'],
            ['Cidade: informe o código IBGE ou cidade + UF. Se ambos forem informados, devem ser compatíveis.'],
            ['Responsável, comprador e corretor são resolvidos por e-mail; regional é resolvida pelo nome exato.'],
            ['A importação é atômica: nenhuma linha será criada enquanto houver erros.'],
        ], null, 'A1');
        $instructions->getColumnDimension('A')->setWidth(120);
        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $referenceData = $this->references->templateReferences();
        $referenceSheet = $spreadsheet->createSheet();
        $referenceSheet->setTitle('Referências');
        $referenceSheet->fromArray(
            ['tipo', 'nome', 'email'],
            null,
            'A1',
        );
        $row = 2;
        foreach ($referenceData['users'] as $user) {
            $referenceSheet->fromArray(['usuario', $user['name'], $user['email']], null, "A{$row}");
            $row++;
        }
        foreach ($referenceData['regionals'] as $regional) {
            $referenceSheet->fromArray(['regional', $regional['nome'], null], null, "A{$row}");
            $row++;
        }
        foreach ($referenceData['corretores'] as $corretor) {
            $referenceSheet->fromArray(['corretor', $corretor['nome'], $corretor['email']], null, "A{$row}");
            $row++;
        }
        $referenceSheet->getStyle('A1:C1')->getFont()->setBold(true);
        $referenceSheet->getColumnDimension('A')->setWidth(18);
        $referenceSheet->getColumnDimension('B')->setWidth(35);
        $referenceSheet->getColumnDimension('C')->setWidth(45);

        $path = tempnam(sys_get_temp_dir(), 'sigapp-terrenos-template-');
        if ($path === false) {
            throw new RuntimeException('Não foi possível criar o template temporário.');
        }
        $xlsxPath = $path.'.xlsx';
        @unlink($path);
        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        return $xlsxPath;
    }

    /**
     * @return list<array{row_number: int, raw_data: array<string, mixed>, formula_columns: list<string>}>
     */
    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName('Terrenos');
        if ($sheet === null) {
            $spreadsheet->disconnectWorksheets();

            throw new RuntimeException('A planilha deve conter uma aba chamada Terrenos.');
        }

        $highestColumn = $sheet->getHighestDataColumn();
        $highestRow = $sheet->getHighestDataRow();
        $headers = [];
        foreach ($sheet->rangeToArray("A1:{$highestColumn}1", null, false, false, false)[0] ?? [] as $index => $header) {
            $normalized = mb_strtolower(trim((string) $header));
            if ($normalized === '') {
                continue;
            }
            if (! in_array($normalized, self::HEADERS, true)) {
                throw new RuntimeException("Cabeçalho desconhecido: {$normalized}.");
            }
            if (in_array($normalized, $headers, true)) {
                throw new RuntimeException("Cabeçalho duplicado: {$normalized}.");
            }
            $headers[$index + 1] = $normalized;
        }

        if (! in_array('nome', $headers, true)) {
            throw new RuntimeException('A coluna obrigatória nome não foi encontrada.');
        }

        if (($highestRow - 1) > self::MAX_ROWS) {
            throw new RuntimeException('A planilha não pode conter mais de 1.000 linhas.');
        }

        $rows = [];
        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $raw = array_fill_keys(self::HEADERS, null);
            $formulaColumns = [];
            $hasValue = false;
            foreach ($headers as $columnIndex => $header) {
                $cell = $sheet->getCell([$columnIndex, $rowNumber]);
                $value = $cell->getValue();
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    $formulaColumns[] = $header;
                    $value = (string) $value;
                } elseif (is_string($value) && str_starts_with(ltrim($value), '=')) {
                    $formulaColumns[] = $header;
                } elseif (in_array($header, self::DATE_HEADERS, true)
                    && is_numeric($value)
                    && Date::isDateTime($cell)) {
                    $value = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                }

                if ($value !== null && trim((string) $value) !== '') {
                    $hasValue = true;
                }
                $raw[$header] = is_scalar($value) || $value === null ? $value : (string) $value;
            }

            if ($hasValue) {
                $rows[] = [
                    'row_number' => $rowNumber,
                    'raw_data' => $raw,
                    'formula_columns' => $formulaColumns,
                ];
            }
        }
        $spreadsheet->disconnectWorksheets();

        if ($rows === []) {
            throw new RuntimeException('A planilha não contém linhas para importação.');
        }

        return $rows;
    }
}
