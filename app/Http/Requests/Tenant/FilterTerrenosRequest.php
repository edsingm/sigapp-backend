<?php

namespace App\Http\Requests\Tenant;

use App\Enums\WorkflowStatus;
use Illuminate\Foundation\Http\FormRequest;

class FilterTerrenosRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepara os dados para validação.
     */
    protected function prepareForValidation(): void
    {
        $toArray = function ($value) {
            if (is_array($value)) {
                return array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
            }
            if (is_string($value)) {
                return array_values(array_filter(preg_split('/[,\s]+/', $value), fn ($v) => $v !== ''));
            }
            if ($value === null) {
                return [];
            }

            return [$value];
        };

        $nome = $this->query('nome', $this->query('q'));
        $ufs = $this->query('ufs', $this->query('uf', $this->query('estado')));
        $cidades = $this->query('cidades', $this->query('cidade_code'));
        $gestores = $this->query('gestor_ids', $this->query('responsavel_ids', $this->query('responsavel_id', $this->query('gestor'))));
        $corretores = $this->query('corretor_ids', $this->query('corretor_id', $this->query('corretor')));
        $regionais = $this->query('regional_ids', $this->query('regional_id', $this->query('regional')));
        $dataInicio = $this->query('data_inicio', $this->query('data_inicial'));
        $dataFim = $this->query('data_fim', $this->query('data_final'));
        $dateField = $this->query('date_field', $this->query('campo_data'));
        $sortBy = $this->query('sort_by', $this->query('sort'));
        $sortDir = $this->query('sort_dir', $this->query('order'));
        $workflowStatuses = $this->query('workflow_statuses', $this->query('workflow_status', $this->query('status')));

        // Converter strings vazias para null
        $emptyToNull = fn ($value) => (is_string($value) && trim($value) === '') ? null : $value;

        $this->merge([
            'nome' => $emptyToNull($nome),
            'ufs' => $toArray($ufs),
            'cidades' => $toArray($cidades),
            'gestor_ids' => $toArray($gestores),
            'corretor_ids' => $toArray($corretores),
            'regional_ids' => $toArray($regionais),
            'data_inicio' => $emptyToNull($dataInicio),
            'data_fim' => $emptyToNull($dataFim),
            'date_field' => $emptyToNull($dateField),
            'sort_by' => $emptyToNull($sortBy),
            'sort_dir' => $emptyToNull($sortDir),
            'workflow_statuses' => $toArray($workflowStatuses),
        ]);
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     */
    public function rules(): array
    {
        $sortFields = [
            'nome',
            'created_at',
            'updated_at',
            'estado',
            'cidade_code',
            'area_calculada',
            'valor',
            'data_apresentacao',
            'data_negociacao',
            'data_contrato',
            'workflow_status_code',
        ];

        $dateFields = [
            'created_at',
            'data_apresentacao',
            'data_negociacao',
            'data_contrato',
        ];

        return [
            'nome' => ['nullable', 'string', 'max:255'],
            'ufs' => ['nullable', 'array'],
            'ufs.*' => ['string', 'size:2'],
            'cidades' => ['nullable', 'array'],
            'cidades.*' => ['string', 'max:255'],
            'gestor_ids' => ['nullable', 'array'],
            'gestor_ids.*' => ['integer'],
            'corretor_ids' => ['nullable', 'array'],
            'corretor_ids.*' => ['integer'],
            'regional_ids' => ['nullable', 'array'],
            'regional_ids.*' => ['integer'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'ano' => ['nullable', 'integer', 'digits:4'],
            'date_field' => ['nullable', 'in:'.implode(',', $dateFields)],
            'sort_by' => ['nullable', 'in:'.implode(',', $sortFields)],
            'sort_dir' => ['nullable', 'in:asc,desc'],
            'workflow_statuses' => ['nullable', 'array'],
            'workflow_statuses.*' => ['string', 'in:'.implode(',', WorkflowStatus::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
