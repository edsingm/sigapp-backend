<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Veiculo;

class StoreRequisicaoVeiculoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'veiculo_id' => 'required|exists:veiculos,id',
            'motorista_id' => 'nullable|exists:users,id',
            'nome_motorista' => 'nullable|required_without:motorista_id|string|max:255',
            'cnh_motorista' => 'nullable|string|max:20',
            'data_inicio' => 'required|date|after_or_equal:now',
            'data_fim' => 'required|date|after:data_inicio',
            'destino' => 'required|string|max:255',
            'motivo_uso' => 'required|string|max:1000',
            'observacoes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'veiculo_id.required' => 'O veículo é obrigatório.',
            'veiculo_id.exists' => 'O veículo selecionado não existe.',
            'nome_motorista.required_without' => 'Informe o motorista do sistema ou o nome do motorista externo.',
            'nome_motorista.max' => 'O nome do motorista deve ter no máximo 255 caracteres.',
            'cnh_motorista.max' => 'A CNH deve ter no máximo 20 caracteres.',
            'data_inicio.required' => 'A data de início é obrigatória.',
            'data_inicio.date' => 'A data de início deve ser uma data válida.',
            'data_inicio.after_or_equal' => 'A data de início deve ser a partir de hoje.',
            'data_fim.required' => 'A data de fim é obrigatória.',
            'data_fim.date' => 'A data de fim deve ser uma data válida.',
            'data_fim.after' => 'A data de fim deve ser posterior à data de início.',
            'destino.required' => 'O destino é obrigatório.',
            'destino.max' => 'O destino deve ter no máximo 255 caracteres.',
            'motivo_uso.required' => 'O motivo de uso é obrigatório.',
            'motivo_uso.max' => 'O motivo deve ter no máximo 1000 caracteres.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->veiculo_id && $this->data_inicio && $this->data_fim) {
                $veiculo = Veiculo::find($this->veiculo_id);
                
                if ($veiculo) {
                    $dataInicio = new \DateTime($this->data_inicio);
                    $dataFim = new \DateTime($this->data_fim);
                    
                    if (!$veiculo->disponivelNoPeriodo($dataInicio, $dataFim)) {
                        $validator->errors()->add('veiculo_id', 'O veículo não está disponível no período selecionado.');
                    }
                }
            }
        });
    }
}
