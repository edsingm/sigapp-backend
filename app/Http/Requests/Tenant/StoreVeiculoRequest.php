<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreVeiculoRequest extends FormRequest
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
            'placa' => 'required|string|max:10|unique:veiculos,placa',
            'modelo' => 'required|string|max:100',
            'marca' => 'required|string|max:50',
            'ano' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'cor' => 'nullable|string|max:30',
            'chassi' => 'nullable|string|max:50',
            'renavam' => 'nullable|string|max:20',
            'quilometragem' => 'nullable|integer|min:0',
            'tipo_combustivel' => 'nullable|in:gasolina,etanol,flex,diesel,eletrico,hibrido',
            'capacidade_passageiros' => 'nullable|integer|min:1|max:50',
            'status' => 'nullable|in:disponivel,em_uso,manutencao,inativo',
            'observacoes' => 'nullable|string|max:1000',
            'foto' => 'nullable|string|max:255',
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
            'placa.required' => 'A placa é obrigatória.',
            'placa.unique' => 'Esta placa já está cadastrada.',
            'placa.max' => 'A placa deve ter no máximo 10 caracteres.',
            'modelo.required' => 'O modelo é obrigatório.',
            'modelo.max' => 'O modelo deve ter no máximo 100 caracteres.',
            'marca.required' => 'A marca é obrigatória.',
            'marca.max' => 'A marca deve ter no máximo 50 caracteres.',
            'ano.required' => 'O ano é obrigatório.',
            'ano.integer' => 'O ano deve ser um número inteiro.',
            'ano.min' => 'O ano deve ser maior que 1900.',
            'ano.max' => 'O ano não pode ser maior que o próximo ano.',
            'quilometragem.integer' => 'A quilometragem deve ser um número inteiro.',
            'quilometragem.min' => 'A quilometragem não pode ser negativa.',
            'tipo_combustivel.in' => 'Tipo de combustível inválido.',
            'capacidade_passageiros.integer' => 'A capacidade deve ser um número inteiro.',
            'capacidade_passageiros.min' => 'A capacidade mínima é 1 passageiro.',
            'capacidade_passageiros.max' => 'A capacidade máxima é 50 passageiros.',
            'status.in' => 'Status inválido.',
        ];
    }
}
