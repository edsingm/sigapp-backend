<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Enums\TerrenoImportRowStatus;
use App\Repositories\Tenant\TerrenoImportReferenceRepository;
use App\Services\Tenant\TerrenoImportValidationService;
use App\Services\Tenant\TerrenoSpreadsheetService;
use Tests\TestCase;

class TerrenoImportValidationServiceTest extends TestCase
{
    public function test_normaliza_valor_data_cidade_e_referencias_exatas(): void
    {
        $service = new TerrenoImportValidationService($this->references());

        $rows = $service->validate([
            $this->sourceRow(2, [
                'nome' => '  Terreno Central  ',
                'uf' => 'sp',
                'cidade_codigo_ibge' => '3550308',
                'cidade' => 'São Paulo',
                'valor' => 'R$ 1.250,50',
                'data_apresentacao' => '2026-07-30',
                'responsavel_email' => 'responsavel@example.com',
                'comprador_email' => 'comprador@example.com',
                'corretor_email' => 'corretor@example.com',
                'regional_nome' => 'Sudeste',
            ]),
        ], 10);

        $this->assertSame(TerrenoImportRowStatus::VALID->value, $rows[0]['status']);
        $normalized = json_decode((string) $rows[0]['normalized_data'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Terreno Central', $normalized['nome']);
        $this->assertSame('SP', $normalized['estado']);
        $this->assertSame('3550308', $normalized['cidade_code']);
        $this->assertSame(1250.5, $normalized['valor']);
        $this->assertSame(11, $normalized['responsavel_id']);
        $this->assertSame(12, $normalized['comprador_id']);
        $this->assertSame(21, $normalized['corretor_id']);
        $this->assertSame(31, $normalized['regional_id']);
    }

    public function test_detecta_duplicata_na_planilha_ignorando_acentos_caixa_e_espacos(): void
    {
        $service = new TerrenoImportValidationService($this->references());

        $rows = $service->validate([
            $this->sourceRow(2, ['nome' => 'Área São José', 'endereco' => 'Rua Um']),
            $this->sourceRow(3, ['nome' => ' area sao jose ', 'endereco' => '  rua   um ']),
        ], 10);

        $this->assertSame(TerrenoImportRowStatus::VALID->value, $rows[0]['status']);
        $this->assertSame(TerrenoImportRowStatus::INVALID->value, $rows[1]['status']);
        $errors = json_decode((string) $rows[1]['errors'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('duplicate', $errors);
    }

    public function test_rejeita_formula_referencia_data_e_valor_invalidos(): void
    {
        $service = new TerrenoImportValidationService($this->references());
        $source = $this->sourceRow(2, [
            'nome' => 'Terreno Inválido',
            'valor' => 'não é dinheiro',
            'data_contrato' => '30/07/2026',
            'responsavel_email' => 'ausente@example.com',
        ]);
        $source['formula_columns'] = ['valor'];

        $rows = $service->validate([$source], 10);
        $errors = json_decode((string) $rows[0]['errors'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(TerrenoImportRowStatus::INVALID->value, $rows[0]['status']);
        $this->assertArrayHasKey('formulas', $errors);
        $this->assertArrayHasKey('valor', $errors);
        $this->assertArrayHasKey('data_contrato', $errors);
        $this->assertArrayHasKey('responsavel_email', $errors);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{row_number: int, raw_data: array<string, mixed>, formula_columns: list<string>}
     */
    private function sourceRow(int $rowNumber, array $values): array
    {
        return [
            'row_number' => $rowNumber,
            'raw_data' => array_replace(
                array_fill_keys(TerrenoSpreadsheetService::HEADERS, null),
                $values,
            ),
            'formula_columns' => [],
        ];
    }

    private function references(): TerrenoImportReferenceRepository
    {
        return new class extends TerrenoImportReferenceRepository
        {
            public function userIdByEmail(string $email): ?int
            {
                return match (mb_strtolower($email)) {
                    'responsavel@example.com' => 11,
                    'comprador@example.com' => 12,
                    default => null,
                };
            }

            public function corretorIdByEmail(string $email): ?int
            {
                return mb_strtolower($email) === 'corretor@example.com' ? 21 : null;
            }

            public function regionalIdByName(string $name): ?int
            {
                return mb_strtolower($name) === 'sudeste' ? 31 : null;
            }

            public function cityByCode(string $code): ?array
            {
                return $code === '3550308'
                    ? ['code' => '3550308', 'city' => 'São Paulo', 'state_code' => 'SP']
                    : null;
            }

            public function terrainDuplicateExists(string $name, ?string $cityCode, ?string $address): bool
            {
                return false;
            }
        };
    }
}
