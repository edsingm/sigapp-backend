<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Viabilidade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Viabilidade */
class ViabilidadeResource extends JsonResource
{
    /**
     * @var list<string>
     */
    private const DEFAULT_INCLUDES = [];

    /**
     * Transformar o recurso em um array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $include = $this->parseInclude($request);
        $perfilFinanciamento = $this->getAttribute('perfil_financiamento');
        $terreno = $this->relationLoaded('terreno') ? $this->resource->getRelation('terreno') : null;
        $updatedBy = $this->relationLoaded('updatedBy') ? $this->resource->getRelation('updatedBy') : null;

        $data = [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'version' => $this->version,
            'is_current' => $this->is_current,
            'parceria_vgv' => (float) $this->parceria_vgv,
            'compra_terreno' => (float) $this->compra_terreno,
            'infra_nao_incidente' => (float) $this->infra_nao_incidente,
            'porcentagem_lote_proprietario' => (float) $this->porcentagem_lote_proprietario,
            'prazo_obra' => (int) $this->prazo_obra,
            'prazo_lancamento' => (int) $this->prazo_lancamento,
            'prazo_incorporacao' => (int) $this->prazo_incorporacao,
            'pis_cofins' => (float) $this->pis_cofins,
            'iss' => (float) $this->iss,
            'outros_impostos' => (float) $this->outros_impostos,
            'comissao' => (float) $this->comissao,
            'incorporacao' => (float) $this->incorporacao,
            'area_comum' => (float) $this->area_comum,
            'contrapartidas' => (float) $this->contrapartidas,
            'canteiro_mensal' => (float) $this->canteiro_mensal,
            'mo_administrativa' => (float) $this->mo_administrativa,
            'seguros' => (float) $this->seguros,
            'assistencia_tecnica' => (float) $this->assistencia_tecnica,
            'despesas_comerciais' => (float) $this->despesas_comerciais,
            'stand_vendas' => (float) $this->stand_vendas,
            'mobilia_decoracao' => (float) $this->mobilia_decoracao,
            'gastos_mensais_stand' => (float) $this->gastos_mensais_stand,
            'comissao_house_percentual' => (float) $this->comissao_house_percentual,
            'comissao_imobiliarias_percentual' => (float) $this->comissao_imobiliarias_percentual,
            'percentual_vendas_house' => (float) $this->percentual_vendas_house,
            'construcao_stand_meses_antes_lancamento' => (int) $this->getAttribute('construcao_stand_meses_antes_lancamento'),
            'ajuda_custo_gerente' => (float) $this->ajuda_custo_gerente,
            'ajuda_custo_gerente_regional' => (float) $this->ajuda_custo_gerente_regional,
            'reembolso_logistica' => (float) $this->reembolso_logistica,
            'bonus_cca' => (float) $this->bonus_cca,
            'bonus_gerente' => (float) $this->bonus_gerente,
            'bonus_gerente_regional' => (float) $this->bonus_gerente_regional,
            'bonus_credito' => (float) $this->bonus_credito,
            'bonus_gestor_comercial' => (float) $this->bonus_gestor_comercial,
            'bonus_equipe_comercial' => (float) $this->getAttribute('bonus_equipe_comercial'),
            'pagamento_comissao_venda' => (float) $this->pagamento_comissao_venda,
            'pagamento_comissao_desligamento' => (float) $this->pagamento_comissao_desligamento,
            'parcelamento_comissao_meses' => (int) $this->parcelamento_comissao_meses,
            'marketing' => (float) $this->marketing,
            'marketing_lancamento' => (float) $this->marketing_lancamento,
            'marketing_inicio_antes_lancamento' => (int) $this->marketing_inicio_antes_lancamento,
            'itbi_iptu' => (float) $this->itbi_iptu,
            'registro' => (float) $this->registro,
            'custo_contratacao_cef' => (float) $this->getAttribute('custo_contratacao_cef'),
            'custo_medicao_cef' => (float) $this->getAttribute('custo_medicao_cef'),
            'contratos_cef' => (float) $this->contratos_cef,
            'produtos_cef' => (float) $this->produtos_cef,
            'outras_despesas_financeiras' => (float) $this->outras_despesas_financeiras,
            'despesas_onerosas_bancos' => (float) $this->despesas_onerosas_bancos,
            'percentual_antecipacao_pj' => (float) $this->percentual_antecipacao_pj,
            'aporte_adicional_mensal' => (float) $this->aporte_adicional_mensal,
            'devolucao_aporte_percentual' => (float) $this->devolucao_aporte_percentual,
            'distribuicao_lucros_percentual_obra' => (float) $this->distribuicao_lucros_percentual_obra,
            'taxa_exposicao_aplicada' => (float) $this->taxa_exposicao_aplicada,
            'perfil_financiamento' => $perfilFinanciamento instanceof \App\Enums\PerfilFinanciamento
                ? $perfilFinanciamento->value
                : 'cef',
            'status' => $this->status,
            'approval_status' => $this->approval_status ?? ($this->status === 'ativo' ? 'aprovada' : 'pendente'),
            'approval_requested_at' => $this->approval_requested_at?->toIso8601String(),
            'approval_decided_at' => $this->approval_decided_at?->toIso8601String(),
            'approval_notes' => $this->approval_notes,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'locked_at' => $this->locked_at?->toIso8601String(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'terreno' => $terreno instanceof \App\Models\Tenant\Terreno ? [
                'id' => $terreno->id,
                'nome' => $terreno->getAttribute('nome'),
                'area' => $terreno->getAttribute('area_calculada'),
            ] : null,
        ];

        if ($this->shouldInclude($include, 'auditoria')) {
            $data['auditoria'] = [
                'created_by_user' => $this->relationLoaded('createdBy') && $this->createdBy ? [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ] : null,
                'updated_by_user' => $updatedBy instanceof \App\Models\Tenant\User ? [
                    'id' => $updatedBy->id,
                    'name' => $updatedBy->name,
                ] : null,
                'approval_decided_by_user' => $this->relationLoaded('approvalDecidedBy') && $this->approvalDecidedBy ? [
                    'id' => $this->approvalDecidedBy->id,
                    'name' => $this->approvalDecidedBy->name,
                ] : null,
                'sections' => $this->whenLoaded('secoes', fn () => $this->secoes->map(fn ($secao) => [
                    'id' => $secao->id,
                    'section_code' => $secao->section_code,
                    'section_name' => $secao->section_name,
                    'content_json' => $secao->content_json,
                    'status' => $secao->status,
                ])->values()),
                'approvals' => $this->whenLoaded('aprovacoes', fn () => $this->aprovacoes->map(fn ($approval) => [
                    'id' => $approval->id,
                    'decision' => $approval->decision,
                    'comments' => $approval->comments,
                    'created_at' => $approval->created_at?->toIso8601String(),
                    'user' => $approval->relationLoaded('user') && $approval->user ? [
                        'id' => $approval->user->id,
                        'name' => $approval->user->name,
                    ] : null,
                ])->values()),
            ];
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function parseInclude(Request $request): array
    {
        $raw = $request->query('include');
        if (! is_string($raw) || $raw === '') {
            return self::DEFAULT_INCLUDES;
        }

        /** @var list<string> $include */
        $include = array_values(collect(explode(',', $raw))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all());

        return $include;
    }

    /**
     * @param  list<string>  $include
     */
    private function shouldInclude(array $include, string $key): bool
    {
        return in_array('*', $include, true) || in_array($key, $include, true);
    }
}
