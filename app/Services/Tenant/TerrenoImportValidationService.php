<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\DTOs\TerrenoImportValidationErrors;
use App\Enums\TerrenoImportRowStatus;
use App\Repositories\Tenant\TerrenoImportReferenceRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Normalizer;

class TerrenoImportValidationService
{
    public function __construct(
        private readonly TerrenoImportReferenceRepository $references,
    ) {}

    /**
     * @param  list<array{row_number: int, raw_data: array<string, mixed>, formula_columns: list<string>}>  $sourceRows
     * @return list<array<string, mixed>>
     */
    public function validate(array $sourceRows, int $importId): array
    {
        $seen = [];
        $now = now();
        $rows = [];

        foreach ($sourceRows as $sourceRow) {
            $errors = new TerrenoImportValidationErrors;
            if ($sourceRow['formula_columns'] !== []) {
                $errors->add('formulas', 'Fórmulas não são permitidas: '.implode(', ', $sourceRow['formula_columns']).'.');
            }

            $normalized = $this->normalize($sourceRow['raw_data'], $errors);
            $validator = Validator::make($normalized, [
                'nome' => ['required', 'string', 'max:255'],
                'endereco' => ['nullable', 'string', 'max:255'],
                'cep' => ['nullable', 'string', 'max:10'],
                'bairro' => ['nullable', 'string', 'max:255'],
                'estado' => ['nullable', 'string', 'size:2'],
                'cidade_code' => ['nullable', 'string', 'max:255'],
                'valor' => ['nullable', 'numeric', 'min:0'],
                'zona' => ['nullable', 'string', 'max:255'],
                'distrito' => ['nullable', 'string', 'max:255'],
                'operacao_urbana' => ['nullable', 'string', 'max:255'],
                'observacoes' => ['nullable', 'string', 'max:5000'],
                'data_apresentacao' => ['nullable', 'date_format:Y-m-d'],
                'data_negociacao' => ['nullable', 'date_format:Y-m-d'],
                'data_opcao' => ['nullable', 'date_format:Y-m-d'],
                'data_descarte' => ['nullable', 'date_format:Y-m-d'],
                'data_contrato' => ['nullable', 'date_format:Y-m-d'],
            ]);
            if ($validator->fails()) {
                $errors->merge($validator->errors()->toArray());
            }

            if ($errors->isEmpty()) {
                $key = $this->duplicateKey(
                    (string) $normalized['nome'],
                    $normalized['cidade_code'] ?? null,
                    $normalized['endereco'] ?? null,
                );
                if (isset($seen[$key])) {
                    $errors->add('duplicate', "Terreno duplicado na planilha; primeira ocorrência na linha {$seen[$key]}.");
                } elseif ($this->references->terrainDuplicateExists(
                    (string) $normalized['nome'],
                    $normalized['cidade_code'] ?? null,
                    $normalized['endereco'] ?? null,
                )) {
                    $errors->add('duplicate', 'Já existe um terreno ativo com o mesmo nome, cidade e endereço.');
                } else {
                    $seen[$key] = $sourceRow['row_number'];
                }
            }

            $rows[] = [
                'terreno_import_id' => $importId,
                'row_number' => $sourceRow['row_number'],
                'raw_data' => json_encode($sourceRow['raw_data'], JSON_THROW_ON_ERROR),
                'normalized_data' => $errors->isEmpty() ? json_encode($normalized, JSON_THROW_ON_ERROR) : null,
                'status' => $errors->isEmpty()
                    ? TerrenoImportRowStatus::VALID->value
                    : TerrenoImportRowStatus::INVALID->value,
                'errors' => $errors->isEmpty() ? null : json_encode($errors->all(), JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalize(array $raw, TerrenoImportValidationErrors $errors): array
    {
        $data = [];
        foreach ($raw as $key => $value) {
            $data[$key] = is_string($value) ? trim($value) : $value;
            if ($data[$key] === '') {
                $data[$key] = null;
            }
        }

        $normalized = [
            'nome' => $data['nome'],
            'endereco' => $data['endereco'],
            'cep' => $data['cep'],
            'bairro' => $data['bairro'],
            'estado' => $data['uf'] === null ? null : mb_strtoupper((string) $data['uf']),
            'valor' => $this->normalizeMoney($data['valor'], $errors),
            'zona' => $data['zona'],
            'distrito' => $data['distrito'],
            'operacao_urbana' => $data['operacao_urbana'],
            'observacoes' => $data['observacoes'],
        ];

        foreach (['data_apresentacao', 'data_negociacao', 'data_opcao', 'data_descarte', 'data_contrato'] as $dateField) {
            $normalized[$dateField] = $this->normalizeDate($data[$dateField], $dateField, $errors);
        }

        $this->resolveCity($data, $normalized, $errors);
        $this->resolveReference($data['responsavel_email'], 'responsavel_id', 'responsavel_email', $normalized, $errors, $this->references->userIdByEmail(...));
        $this->resolveReference($data['comprador_email'], 'comprador_id', 'comprador_email', $normalized, $errors, $this->references->userIdByEmail(...));
        $this->resolveReference($data['corretor_email'], 'corretor_id', 'corretor_email', $normalized, $errors, $this->references->corretorIdByEmail(...));
        $this->resolveReference($data['regional_nome'], 'regional_id', 'regional_nome', $normalized, $errors, $this->references->regionalIdByName(...));

        return array_filter($normalized, static fn (mixed $value): bool => $value !== null);
    }

    /** @param array<string, mixed> $raw @param array<string, mixed> $normalized */
    private function resolveCity(array $raw, array &$normalized, TerrenoImportValidationErrors $errors): void
    {
        $code = $raw['cidade_codigo_ibge'];
        $name = $raw['cidade'];
        $state = $normalized['estado'] ?? null;

        if ($code !== null) {
            $city = $this->references->cityByCode((string) $code);
            if ($city === null) {
                $errors->add('cidade_codigo_ibge', 'Código IBGE não encontrado.');

                return;
            }
            if ($name !== null && mb_strtolower((string) $name) !== mb_strtolower($city['city'])) {
                $errors->add('cidade', 'A cidade não corresponde ao código IBGE informado.');
            }
            if ($state !== null && $state !== mb_strtoupper($city['state_code'])) {
                $errors->add('uf', 'A UF não corresponde ao código IBGE informado.');
            }
            $normalized['cidade_code'] = $city['code'];
            $normalized['estado'] = mb_strtoupper($city['state_code']);

            return;
        }

        if ($name === null) {
            return;
        }
        if ($state === null) {
            $errors->add('uf', 'A UF é obrigatória quando a cidade é informada sem código IBGE.');

            return;
        }

        $city = $this->references->cityByNameAndState((string) $name, (string) $state);
        if ($city === null) {
            $errors->add('cidade', 'Cidade e UF não possuem uma correspondência exata.');

            return;
        }
        $normalized['cidade_code'] = $city['code'];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  callable(string): ?int  $resolver
     */
    private function resolveReference(mixed $value, string $target, string $source, array &$normalized, TerrenoImportValidationErrors $errors, callable $resolver): void
    {
        if ($value === null) {
            return;
        }
        $id = $resolver((string) $value);
        if ($id === null) {
            $errors->add($source, 'Referência não encontrada no tenant.');

            return;
        }
        $normalized[$target] = $id;
    }

    private function normalizeMoney(mixed $value, TerrenoImportValidationErrors $errors): float|int|null
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $normalized = str_replace(['R$', ' ', '.'], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);
        if (! is_numeric($normalized)) {
            $errors->add('valor', 'Valor monetário inválido.');

            return null;
        }

        return (float) $normalized;
    }

    private function normalizeDate(mixed $value, string $field, TerrenoImportValidationErrors $errors): ?string
    {
        if ($value === null) {
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', (string) $value);
        } catch (\Throwable) {
            $date = null;
        }
        $lastErrors = CarbonImmutable::getLastErrors();
        if ($date === null || (is_array($lastErrors) && ($lastErrors['warning_count'] > 0 || $lastErrors['error_count'] > 0))) {
            $errors->add($field, 'Use o formato AAAA-MM-DD.');

            return null;
        }

        return $date->format('Y-m-d');
    }

    private function duplicateKey(string $name, ?string $cityCode, ?string $address): string
    {
        return implode('|', [
            $this->fold($name),
            $cityCode ?? '',
            $this->fold($address ?? ''),
        ]);
    }

    private function fold(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim(mb_strtolower($value))) ?? $value;
        $value = Normalizer::normalize($value, Normalizer::FORM_KD) ?: $value;

        return preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
    }
}
