<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncGanttRequest extends FormRequest
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
        $etapas = $this->input('etapas');
        if (! is_array($etapas)) {
            return;
        }

        $normalizedEtapas = array_map(function ($etapa) {
            if (! is_array($etapa)) {
                return $etapa;
            }

            $custos = $this->normalizarCustos((array) ($etapa['custos'] ?? []));

            if (empty($custos) && $this->etapaTemAlgumCampoDeCustoRaiz($etapa)) {
                $custos = [[
                    'tipo_custo' => $etapa['tipo_custo'] ?? null,
                    'valor_custo' => $etapa['valor_custo'] ?? null,
                    'custo_pago' => $this->normalizarBoolean($etapa['custo_pago'] ?? false),
                ]];
            }

            if (! empty($custos)) {
                $etapa['custos'] = $custos;

                if (! array_key_exists('tipo_custo', $etapa)) {
                    $etapa['tipo_custo'] = count($custos) === 1 ? ($custos[0]['tipo_custo'] ?? null) : 'Diversos';
                }

                if (! array_key_exists('valor_custo', $etapa)) {
                    $etapa['valor_custo'] = array_sum(array_map(
                        fn ($custo) => (float) ($custo['valor_custo'] ?? 0),
                        $custos
                    ));
                }

                if (! array_key_exists('custo_pago', $etapa)) {
                    $etapa['custo_pago'] = collect($custos)->every(
                        fn ($custo) => (bool) ($custo['custo_pago'] ?? false)
                    );
                }
            } elseif (array_key_exists('custos', $etapa)) {
                $etapa['custos'] = [];
                if (! array_key_exists('tipo_custo', $etapa)) {
                    $etapa['tipo_custo'] = null;
                }
                if (! array_key_exists('valor_custo', $etapa)) {
                    $etapa['valor_custo'] = null;
                }
                if (! array_key_exists('custo_pago', $etapa)) {
                    $etapa['custo_pago'] = false;
                }
            }

            return $etapa;
        }, $etapas);

        $this->merge([
            'etapas' => $normalizedEtapas,
        ]);
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $legalizacaoId = $this->route('id');

        return [
            'etapas' => 'nullable|array',
            'etapas.*.id' => 'sometimes|integer|exists:legalizacao_etapas,id',
            'etapas.*.titulo' => 'sometimes|string|max:255',
            'etapas.*.nome' => 'sometimes|string|max:255',
            'etapas.*.descricao' => 'sometimes|nullable|string',
            'etapas.*.ordem' => 'sometimes|integer|min:0',
            'etapas.*.status' => 'sometimes|in:pendente,em_andamento,concluida,bloqueada,atrasada,nao_iniciada,cancelada',
            'etapas.*.inicio_planejado' => 'sometimes|date',
            'etapas.*.fim_planejado' => 'sometimes|date',
            'etapas.*.data_prevista' => 'sometimes|date',
            'etapas.*.data_conclusao' => 'sometimes|date',
            'etapas.*.inicio_real' => 'sometimes|nullable|date',
            'etapas.*.fim_real' => 'sometimes|nullable|date',
            'etapas.*.percentual' => 'sometimes|integer|min:0|max:100',
            'etapas.*.responsavel_id' => 'sometimes|nullable|integer|exists:users,id',
            'etapas.*.cor' => 'sometimes|nullable|string|max:20',
            'etapas.*.tipo_custo' => 'sometimes|nullable|string|max:120',
            'etapas.*.valor_custo' => 'sometimes|nullable|numeric|min:0',
            'etapas.*.custo_pago' => 'sometimes|boolean',
            'etapas.*.custos' => 'sometimes|array',
            'etapas.*.custos.*.tipo_custo' => 'nullable|string|max:120',
            'etapas.*.custos.*.valor_custo' => 'required|numeric|min:0',
            'etapas.*.custos.*.custo_pago' => 'sometimes|boolean',
            'etapas.*.parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('legalizacao_etapas', 'id')->where('legalizacao_id', $legalizacaoId),
            ],
            'deleted_etapa_ids' => 'nullable|array',
            'deleted_etapa_ids.*' => 'integer|exists:legalizacao_etapas,id',
            'dependencias' => 'nullable|array',
            'dependencias.*.id' => 'sometimes|integer|exists:legalizacao_dependencias,id',
            'dependencias.*.etapa_origem_id' => 'required|integer|exists:legalizacao_etapas,id',
            'dependencias.*.etapa_destino_id' => 'required|integer|exists:legalizacao_etapas,id|different:etapa_origem_id',
            'dependencias.*.tipo' => 'sometimes|in:FS',
            'deleted_dependencia_ids' => 'nullable|array',
            'deleted_dependencia_ids.*' => 'integer|exists:legalizacao_dependencias,id',
        ];
    }

    /**
     * Obtém as mensagens de erro para as regras de validação definidas.
     */
    public function messages(): array
    {
        return [
            'etapas.array' => 'Etapas deve ser um array.',
            'deleted_etapa_ids.array' => 'IDs de etapas deletadas deve ser um array.',
            'dependencias.array' => 'Dependências deve ser um array.',
            'dependencias.*.etapa_origem_id.required' => 'ID da etapa origem é obrigatório.',
            'dependencias.*.etapa_origem_id.exists' => 'Etapa origem não encontrada.',
            'dependencias.*.etapa_destino_id.required' => 'ID da etapa destino é obrigatório.',
            'dependencias.*.etapa_destino_id.exists' => 'Etapa destino não encontrada.',
            'dependencias.*.etapa_destino_id.different' => 'A etapa destino deve ser diferente da origem.',
            'deleted_dependencia_ids.array' => 'IDs de dependências deletadas deve ser um array.',
            'etapas.*.tipo_custo.max' => 'O tipo de custo deve ter no máximo 120 caracteres.',
            'etapas.*.valor_custo.numeric' => 'O valor do custo deve ser numérico.',
            'etapas.*.valor_custo.min' => 'O valor do custo deve ser maior ou igual a zero.',
            'etapas.*.custo_pago.boolean' => 'O campo custo pago deve ser verdadeiro ou falso.',
            'etapas.*.custos.array' => 'Os custos devem ser enviados em formato de lista.',
            'etapas.*.custos.*.tipo_custo.max' => 'O tipo de custo deve ter no máximo 120 caracteres.',
            'etapas.*.custos.*.valor_custo.required' => 'O valor de cada custo é obrigatório.',
            'etapas.*.custos.*.valor_custo.numeric' => 'O valor do custo deve ser numérico.',
            'etapas.*.custos.*.valor_custo.min' => 'O valor do custo deve ser maior ou igual a zero.',
            'etapas.*.custos.*.custo_pago.boolean' => 'O campo custo pago deve ser verdadeiro ou falso.',
        ];
    }

    /**
     * Normaliza os custos para o formato esperado.
     *
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

    /**
     * Verifica se a etapa possui algum campo de custo raiz.
     */
    protected function etapaTemAlgumCampoDeCustoRaiz(array $etapa): bool
    {
        return array_key_exists('tipo_custo', $etapa)
            || array_key_exists('valor_custo', $etapa)
            || array_key_exists('custo_pago', $etapa);
    }

    /**
     * Normaliza um valor para booleano.
     */
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
