<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTerrenoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'sometimes|string|max:255',
            'responsavel_id' => 'sometimes|nullable|integer|exists:users,id',
            'endereco' => 'sometimes|string|max:255',
            'corretor_id' => 'sometimes|nullable|integer|exists:corretores_externos,id',
            'estado' => 'sometimes|nullable|string|max:2',
            'cidade_code' => 'sometimes|nullable|string|max:255|exists:central.cidades,code',
            'polygon_coords' => 'sometimes|array',
            'polygon_coords.*.lat' => 'required_with:polygon_coords|numeric',
            'polygon_coords.*.lng' => 'required_with:polygon_coords|numeric',
            'static_map_url' => 'sometimes|nullable|string',
            'area_calculada' => 'sometimes|nullable|numeric',
            'regional_id' => 'sometimes|integer|exists:regionais,id',
            'cep' => 'sometimes|nullable|string|max:10',
            'bairro' => 'sometimes|nullable|string|max:255',
            'observacoes' => 'sometimes|nullable|string',
            'valor' => 'sometimes|nullable|numeric',
            'zona' => 'sometimes|nullable|string|max:255',
            'distrito' => 'sometimes|nullable|string|max:255',
            'operacao_urbana' => 'sometimes|nullable|string|max:255',
            'data_apresentacao' => 'sometimes|nullable|date',
            'data_negociacao' => 'sometimes|nullable|date',
            'data_opcao' => 'sometimes|nullable|date',
            'data_descarte' => 'sometimes|nullable|date',
            'data_contrato' => 'sometimes|nullable|date',
            'comprador_id' => 'sometimes|nullable|integer|exists:users,id',
            'tipo_captacao' => 'sometimes|in:ativa,passiva,indicação',
        ];
    }

    public function messages(): array
    {
        return [
            'responsavel_id.exists' => 'Responsável inválido.',
            'comprador_id.exists' => 'Comprador inválido.',
            'cidade_code.exists' => 'Código da cidade inválido.',
            'corretor_id.exists' => 'Corretor inválido.',
            'regional_id.exists' => 'Regional inválida.',
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
