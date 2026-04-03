<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProdutoRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:255',
            'private_area' => 'nullable|numeric|min:0',
            'm2_cost' => 'nullable|numeric|min:0',
            'infra_cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:255',
            'sinal' => 'nullable|numeric|min:0',
            'parcela_obra' => 'nullable|numeric|min:0',
            'parcela_posChave' => 'nullable|numeric|min:0',
            'qtde_parcelas_posChave' => 'nullable|string|max:255',
            'demanda_minCef' => 'nullable|numeric|min:0',
            'defasagem_pgtoTerreno' => 'nullable|string|max:255',
            'avaliacao_lotesCef' => 'nullable|numeric|min:0',
            'juros_mensalSinal' => 'nullable|numeric',
            'juros_mensalObra' => 'nullable|numeric',
            'juros_mensalPosChave' => 'nullable|numeric',
            'correcao_anualSinal' => 'nullable|numeric',
            'correcao_anualObra' => 'nullable|numeric',
            'correcao_anualPosChave' => 'nullable|numeric',
            'imposto_tributos' => 'nullable|numeric|min:0',
            'imposto_iss' => 'nullable|numeric|min:0',
            'imposto_outros' => 'nullable|numeric|min:0',
            'curva_vendas' => 'nullable|array',
            'incorp_ri' => 'nullable|numeric|min:0',
            'incorp_entrega' => 'nullable|numeric|min:0',
            'incorp_ateLancamento' => 'nullable|numeric|min:0',
            'obra_ateLancamento' => 'nullable|numeric|min:0',
            'assist_tecnica1' => 'nullable|numeric|min:0',
            'assist_tecnica2' => 'nullable|numeric|min:0',
            'assist_tecnica3' => 'nullable|numeric|min:0',
            'assist_tecnica4' => 'nullable|numeric|min:0',
            'assist_tecnica5' => 'nullable|numeric|min:0',
            'meses_inicioConstrucao' => 'nullable|string|max:255',
            'porcentagem_ConstrucaoStand' => 'nullable|numeric|min:0',
            'gastos_mensaisStand' => 'nullable|numeric|min:0',
            'comissao_house' => 'nullable|numeric|min:0',
            'porcentagem_comissaoHouse' => 'nullable|numeric|min:0',
            'porcentagem_comissaoImobs' => 'nullable|numeric|min:0',
            'pagto_comissaoNaVenda' => 'nullable|numeric|min:0',
            'marketing_antesLancamento' => 'nullable|numeric|min:0',
            'marketing_lancamento' => 'nullable|numeric|min:0',
            'custo_contratacaoCef' => 'nullable|numeric|min:0',
            'pj_taxaJuros' => 'nullable|numeric',
            'pj_carenciaPosObra' => 'nullable|string|max:255',
            'pj_qtdeParcelasPosCarencia' => 'nullable|string|max:255',
        ];
    }

    /**
     * Obtém as mensagens personalizadas para erros do validador.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome do produto é obrigatório.',
            'name.max' => 'O nome do produto não pode ter mais de 255 caracteres.',
            'private_area.numeric' => 'A área privativa deve ser um número.',
            'm2_cost.numeric' => 'O custo por m² deve ser um número.',
            'infra_cost.numeric' => 'O custo de infraestrutura deve ser um número.',
            'curva_vendas.array' => 'A curva de vendas deve ser um array.',
        ];
    }
}
