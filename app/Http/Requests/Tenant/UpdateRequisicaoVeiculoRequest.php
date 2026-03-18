<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Veiculo;

class UpdateRequisicaoVeiculoRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'veiculo_id' => 'sometimes|exists:veiculos,id',
            'motorista_id' => 'nullable|exists:users,id',
            'nome_motorista' => 'nullable|string|max:255',
            'cnh_motorista' => 'nullable|string|max:20',
            'data_inicio' => 'sometimes|date',
            'data_fim' => 'sometimes|date|after:data_inicio',
            'data_retirada' => 'nullable|date',
            'data_devolucao' => 'nullable|date|after_or_equal:data_retirada',
            'destino' => 'sometimes|string|max:255',
            'motivo_uso' => 'sometimes|string|max:1000',
            'observacoes' => 'nullable|string|max:1000',
            'km_inicial' => 'nullable|integer|min:0',
            'km_final' => 'nullable|integer|min:0|gte:km_inicial',
            'status' => 'sometimes|in:pendente,aprovada,em_uso,concluida,cancelada,rejeitada',
            'motivo_cancelamento' => 'nullable|required_if:status,cancelada,rejeitada|string|max:500',
        ];
    }

    /**
     * Obtém as mensagens personalizadas para erros do validador.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'veiculo_id.exists' => 'O veículo selecionado não existe.',
            'nome_motorista.max' => 'O nome do motorista deve ter no máximo 255 caracteres.',
            'data_inicio.date' => 'A data de início deve ser uma data válida.',
            'data_fim.date' => 'A data de fim deve ser uma data válida.',
            'data_fim.after' => 'A data de fim deve ser posterior à data de início.',
            'data_retirada.date' => 'A data de retirada deve ser uma data válida.',
            'data_devolucao.date' => 'A data de devolução deve ser uma data válida.',
            'data_devolucao.after_or_equal' => 'A data de devolução deve ser posterior ou igual à data de retirada.',
            'destino.max' => 'O destino deve ter no máximo 255 caracteres.',
            'motivo_uso.max' => 'O motivo deve ter no máximo 1000 caracteres.',
            'km_inicial.integer' => 'O KM inicial deve ser um número inteiro.',
            'km_inicial.min' => 'O KM inicial não pode ser negativo.',
            'km_final.integer' => 'O KM final deve ser um número inteiro.',
            'km_final.min' => 'O KM final não pode ser negativo.',
            'km_final.gte' => 'O KM final deve ser maior ou igual ao KM inicial.',
            'status.in' => 'Status inválido.',
            'motivo_cancelamento.required_if' => 'O motivo do cancelamento/rejeição é obrigatório.',
        ];
    }

    /**
     * Configura a instância do validador.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $requisicaoId = $this->route('requisicoes_veiculo') ?? $this->route('id');
            
            if ($this->veiculo_id && $this->data_inicio && $this->data_fim) {
                $veiculo = Veiculo::find($this->veiculo_id);
                
                if ($veiculo) {
                    $dataInicio = new \DateTime($this->data_inicio);
                    $dataFim = new \DateTime($this->data_fim);
                    
                    if (!$veiculo->disponivelNoPeriodo($dataInicio, $dataFim, $requisicaoId)) {
                        $validator->errors()->add('veiculo_id', 'O veículo não está disponível no período selecionado.');
                    }
                }
            }
        });
    }
}
