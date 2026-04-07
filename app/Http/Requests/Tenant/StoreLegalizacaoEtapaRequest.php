<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLegalizacaoEtapaRequest extends FormRequest
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
        $merge = [];
        $custos = $this->normalizarCustos((array) $this->input('custos', []));

        if (empty($custos) && $this->temAlgumCampoDeCustoRaiz()) {
            $custos = [[
                'tipo_custo' => $this->input('tipo_custo'),
                'valor_custo' => $this->input('valor_custo'),
                'custo_pago' => $this->normalizarBoolean($this->input('custo_pago', false)),
            ]];
        }

        if (! empty($custos)) {
            $merge['custos'] = $custos;

            if (! $this->exists('tipo_custo')) {
                $merge['tipo_custo'] = count($custos) === 1 ? ($custos[0]['tipo_custo'] ?? null) : 'Diversos';
            }

            if (! $this->exists('valor_custo')) {
                $merge['valor_custo'] = array_sum(array_map(
                    fn ($custo) => (float) ($custo['valor_custo'] ?? 0),
                    $custos
                ));
            }

            if (! $this->exists('custo_pago')) {
                $merge['custo_pago'] = collect($custos)->every(
                    fn ($custo) => (bool) ($custo['custo_pago'] ?? false)
                );
            }
        } elseif ($this->exists('custos')) {
            $merge['custos'] = [];

            if (! $this->exists('tipo_custo')) {
                $merge['tipo_custo'] = null;
            }
            if (! $this->exists('valor_custo')) {
                $merge['valor_custo'] = null;
            }
            if (! $this->exists('custo_pago')) {
                $merge['custo_pago'] = false;
            }
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     */
    public function rules(): array
    {
        $legalizacaoId = $this->route('legalizacaoId');

        return [
            'titulo' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'ordem' => 'nullable|integer|min:0',
            'status' => 'nullable|in:pendente,em_andamento,concluida,bloqueada,atrasada',
            'inicio_planejado' => 'required|date',
            'fim_planejado' => 'required|date|after_or_equal:inicio_planejado',
            'inicio_real' => 'nullable|date',
            'fim_real' => 'nullable|date|after_or_equal:inicio_real',
            'percentual' => 'nullable|integer|min:0|max:100',
            'responsavel_id' => 'nullable|integer|exists:users,id',
            'cor' => 'nullable|string|max:20',
            'tipo_custo' => 'nullable|string|max:120',
            'valor_custo' => 'nullable|numeric|min:0',
            'custo_pago' => 'sometimes|boolean',
            'custos' => 'nullable|array',
            'custos.*.tipo_custo' => 'nullable|string|max:120',
            'custos.*.valor_custo' => 'required|numeric|min:0',
            'custos.*.custo_pago' => 'sometimes|boolean',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('legalizacao_etapas', 'id')->where('legalizacao_id', $legalizacaoId),
            ],
        ];
    }

    /**
     * Obtém as mensagens personalizadas para erros do validador.
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'O título é obrigatório.',
            'ordem.integer' => 'A ordem deve ser um número inteiro.',
            'ordem.min' => 'A ordem não pode ser negativa.',
            'status.in' => 'Status deve ser: pendente, em_andamento, concluida, bloqueada ou atrasada.',
            'inicio_planejado.required' => 'A data de início planejada é obrigatória.',
            'inicio_planejado.date' => 'Data de início planejada inválida.',
            'fim_planejado.required' => 'A data de fim planejada é obrigatória.',
            'fim_planejado.date' => 'Data de fim planejada inválida.',
            'fim_planejado.after_or_equal' => 'A data de fim deve ser igual ou posterior à data de início.',
            'inicio_real.date' => 'Data de início real inválida.',
            'fim_real.date' => 'Data de fim real inválida.',
            'fim_real.after_or_equal' => 'A data de fim real deve ser igual ou posterior à data de início.',
            'percentual.integer' => 'Percentual deve ser um número inteiro.',
            'percentual.min' => 'Percentual não pode ser negativo.',
            'percentual.max' => 'Percentual não pode ser maior que 100.',
            'responsavel_id.exists' => 'Responsável não encontrado.',
            'tipo_custo.max' => 'O tipo de custo deve ter no máximo 120 caracteres.',
            'valor_custo.numeric' => 'O valor do custo deve ser numérico.',
            'valor_custo.min' => 'O valor do custo deve ser maior ou igual a zero.',
            'custo_pago.boolean' => 'O campo custo pago deve ser verdadeiro ou falso.',
            'custos.array' => 'Os custos devem ser enviados em formato de lista.',
            'custos.*.tipo_custo.max' => 'O tipo de custo deve ter no máximo 120 caracteres.',
            'custos.*.valor_custo.required' => 'O valor de cada custo é obrigatório.',
            'custos.*.valor_custo.numeric' => 'O valor do custo deve ser numérico.',
            'custos.*.valor_custo.min' => 'O valor do custo deve ser maior ou igual a zero.',
            'custos.*.custo_pago.boolean' => 'O campo custo pago deve ser verdadeiro ou falso.',
        ];
    }

    /**
     * @param  array<int, mixed>  $custos
     * @return array<int, array<string, mixed>>
     */
    protected function normalizarCustos(array $custos): array
    {
        $normalizados = [];

        foreach ($custos as $custo) {
            if (! is_array($custo)) {
                continue;
            }

            $tipo = $custo['tipo_custo'] ?? null;
            $valor = $custo['valor_custo'] ?? null;
            $pago = $custo['custo_pago'] ?? false;

            if ($tipo === null && $valor === null) {
                continue;
            }

            $normalizados[] = [
                'tipo_custo' => $tipo,
                'valor_custo' => $valor,
                'custo_pago' => $this->normalizarBoolean($pago),
            ];
        }

        return $normalizados;
    }

    protected function temAlgumCampoDeCustoRaiz(): bool
    {
        return $this->exists('tipo_custo')
            || $this->exists('valor_custo')
            || $this->exists('custo_pago');
    }

    protected function normalizarBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $value = mb_strtolower(trim($value));

            return in_array($value, ['1', 'true', 'yes', 'sim'], true);
        }

        return false;
    }
}
