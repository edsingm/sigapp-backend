<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTerrenoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'responsavel_id' => 'nullable|integer|exists:users,id',
            'endereco' => 'nullable|string|max:255',
            'corretor_id' => 'nullable|integer',
            'estado' => 'nullable|string|max:2',
            'cidade_code' => 'nullable|string|max:255|exists:central.cidades,code',
            'polygon_coords' => 'nullable|array',
            'polygon_coords.*.lat' => 'required_with:polygon_coords|numeric',
            'polygon_coords.*.lng' => 'required_with:polygon_coords|numeric',
            'static_map_url' => 'nullable|string',
            'area_calculada' => 'nullable|numeric',
            'workflow_status_code' => ['nullable', 'string', Rule::in(['em_analise'])],
            'regional_id' => 'nullable|integer|exists:regionais,id',
            'cep' => 'nullable|string|max:10',
            'bairro' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
            'valor' => 'nullable|numeric',
            'zona' => 'nullable|string|max:255',
            'distrito' => 'nullable|string|max:255',
            'operacao_urbana' => 'nullable|string|max:255',
            'data_apresentacao' => 'nullable|date',
            'data_negociacao' => 'nullable|date',
            'data_opcao' => 'nullable|date',
            'data_descarte' => 'nullable|date',
            'data_contrato' => 'nullable|date',
            'comprador_id' => 'nullable|integer|exists:users,id',
            'tipo_captacao' => 'nullable|in:ativa,passiva,indicação',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome é obrigatório.',
            'estado.required' => 'O estado (UF) é obrigatório.',
            'cidade_code.exists' => 'Código da cidade inválido.',
            'responsavel_id.exists' => 'Responsável inválido.',
            'comprador_id.exists' => 'Comprador inválido.',
            'polygon_coords.array' => 'polygon_coords deve ser um array.',
            'polygon_coords.*.lat.required_with' => 'Latitude é obrigatória quando polygon_coords é enviado.',
            'polygon_coords.*.lng.required_with' => 'Longitude é obrigatória quando polygon_coords é enviado.',
            'data_apresentacao.date' => 'Data de apresentação inválida.',
            'data_negociacao.date' => 'Data de negociação inválida.',
            'data_opcao.date' => 'Data de opção inválida.',
            'data_descarte.date' => 'Data de descarte inválida.',
            'data_contrato.date' => 'Data de contrato inválida.',
            'tipo_captacao.in' => 'Tipo de captação deve ser ativa, passiva ou indicação.',
        ];
    }
}
