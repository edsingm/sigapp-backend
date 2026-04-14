<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProdutoResource extends JsonResource
{
    /**
     * Transformar o recurso em um array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'private_area' => $this->private_area,
            'm2_cost' => $this->m2_cost,
            'infra_cost' => $this->infra_cost,
            'status' => $this->status,
            'sinal' => $this->sinal,
            'parcela_obra' => $this->parcela_obra,
            'parcela_posChave' => $this->parcela_posChave,
            'qtde_parcelas_posChave' => $this->qtde_parcelas_posChave,
            'demanda_minCef' => $this->demanda_minCef,
            'defasagem_pgtoTerreno' => $this->defasagem_pgtoTerreno,
            'avaliacao_lotesCef' => $this->avaliacao_lotesCef,
            'juros_mensalSinal' => $this->juros_mensalSinal,
            'juros_mensalObra' => $this->juros_mensalObra,
            'juros_mensalPosChave' => $this->juros_mensalPosChave,
            'correcao_anualSinal' => $this->correcao_anualSinal,
            'correcao_anualObra' => $this->correcao_anualObra,
            'correcao_anualPosChave' => $this->correcao_anualPosChave,
            'imposto_tributos' => $this->imposto_tributos,
            'imposto_iss' => $this->imposto_iss,
            'imposto_outros' => $this->imposto_outros,
            'curva_vendas' => $this->curva_vendas,
            'incorp_ri' => $this->incorp_ri,
            'incorp_entrega' => $this->incorp_entrega,
            'incorp_ateLancamento' => $this->incorp_ateLancamento,
            'obra_ateLancamento' => $this->obra_ateLancamento,
            'assist_tecnica1' => $this->assist_tecnica1,
            'assist_tecnica2' => $this->assist_tecnica2,
            'assist_tecnica3' => $this->assist_tecnica3,
            'assist_tecnica4' => $this->assist_tecnica4,
            'assist_tecnica5' => $this->assist_tecnica5,
            'meses_inicioConstrucao' => $this->meses_inicioConstrucao,
            'porcentagem_ConstrucaoStand' => $this->porcentagem_ConstrucaoStand,
            'gastos_mensaisStand' => $this->gastos_mensaisStand,
            'comissao_house' => $this->comissao_house,
            'porcentagem_comissaoHouse' => $this->porcentagem_comissaoHouse,
            'porcentagem_comissaoImobs' => $this->porcentagem_comissaoImobs,
            'pagto_comissaoNaVenda' => $this->pagto_comissaoNaVenda,
            'marketing_antesLancamento' => $this->marketing_antesLancamento,
            'marketing_lancamento' => $this->marketing_lancamento,
            'custo_contratacaoCef' => $this->custo_contratacaoCef,
            'pj_taxaJuros' => $this->pj_taxaJuros,
            'pj_carenciaPosObra' => $this->pj_carenciaPosObra,
            'pj_qtdeParcelasPosCarencia' => $this->pj_qtdeParcelasPosCarencia,
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d/m/Y H:i:s'),
        ];
    }
}
