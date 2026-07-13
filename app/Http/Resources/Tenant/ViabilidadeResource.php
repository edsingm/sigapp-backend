<?php

namespace App\Http\Resources\Tenant;

use App\Enums\PerfilFinanciamento;
use App\Enums\ViabilidadeApprovalStatus;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Services\Tenant\Viabilidade\v1\PremissasViabilidadeService;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeSnapshotService;
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
     * @var array<string, array<string, mixed>>
     */
    private static array $defaultsCache = [];

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
        $perfil = $perfilFinanciamento instanceof PerfilFinanciamento
            ? $perfilFinanciamento->value
            : 'cef';
        $approvalStatus = ViabilidadeApprovalStatus::fromMixed(
            $this->approval_status,
            is_string($this->status) ? $this->status : null,
        );
        $snapshot = $this->snapshot();
        $engineVersion = is_string($snapshot['calculation_engine_version'] ?? null)
            ? $snapshot['calculation_engine_version']
            : null;
        $inputHash = is_string($snapshot['input_hash'] ?? null) ? $snapshot['input_hash'] : null;
        $resultHash = is_string($snapshot['result_hash'] ?? null) ? $snapshot['result_hash'] : null;

        $dataLancamento = $this->getAttribute('data_lancamento');
        if ($dataLancamento instanceof \DateTimeInterface) {
            $dataLancamento = $dataLancamento->format('Y-m-d');
        } elseif (is_string($dataLancamento) && $dataLancamento !== '') {
            $dataLancamento = substr($dataLancamento, 0, 10);
        } else {
            $dataLancamento = null;
        }

        $data = [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'version' => $this->version,
            'is_current' => $this->is_current,
            'data_lancamento' => $dataLancamento,
            'calculation_engine_version' => $engineVersion,
            'input_hash' => $inputHash,
            'result_hash' => $resultHash,
            'allowed_actions' => $approvalStatus->allowedActions(),
            'parceria_vgv' => (float) $this->parceria_vgv,
            'compra_terreno' => (float) $this->compra_terreno,
            'infra_nao_incidente' => (float) $this->infra_nao_incidente,
            'porcentagem_lote_proprietario' => (float) $this->porcentagem_lote_proprietario,
            'prazo_obra' => (int) $this->prazo_obra,
            'prazo_lancamento' => (int) $this->prazo_lancamento,
            'prazo_incorporacao' => (int) $this->prazo_incorporacao,
            'meses_entrega' => $this->resolveIntValue([], 'meses_entrega', 'mesesEntrega', 'meses_entrega'),
            'meses_pos_obra' => $this->resolveIntValue([], 'meses_pos_obra', 'mesesPosObra', 'meses_pos_obra'),
            'pis_cofins' => (float) $this->pis_cofins,
            'iss' => (float) $this->iss,
            'outros_impostos' => (float) $this->outros_impostos,
            'comissao' => (float) $this->comissao,
            'incorporacao' => (float) $this->incorporacao,
            'variavel_correcao' => $this->resolveFloatValue([], 'variavel_correcao', null, 'variavel_correcao'),
            'incorp_ri' => $this->resolveFloatValue(['incorporacao_ri'], 'incorp_ri', 'incorporacaoRi', 'incorp_ri', true),
            'incorp_entrega' => $this->resolveFloatValue(['incorporacao_entrega'], 'incorp_entrega', 'incorporacaoEntrega', 'incorp_entrega', true),
            'incorp_ate_lancamento' => $this->resolveFloatValue(['incorporacao_ate_lancamento'], 'incorp_ate_lancamento', 'incorporacaoAteLancamento', 'incorp_ate_lancamento', true),
            'obra_ate_lancamento' => $this->resolveFloatValue([], 'obra_ate_lancamento', 'obraAteLancamento', 'obra_ate_lancamento', true),
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
            'parcelamento_comissao_terreno' => $this->resolveIntValue([], 'parcelamento_comissao_terreno', 'parcelamentoComissaoTerreno', 'parcelamento_comissao_terreno'),
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
            'taxa_juros_pj' => $this->resolveFloatValue(['taxa_juros_pj'], 'taxa_juros_pj', 'taxaJurosPj', 'taxa_juros_pj', true),
            'carencia_pj_meses' => $this->resolveIntValue(['carencia_pj_meses'], 'carencia_pj_meses', 'carenciaPjMeses', 'carencia_pj_meses'),
            'amortizacao_pj_parcelas' => $this->resolveIntValue(['amortizacao_pj_parcelas'], 'amortizacao_pj_parcelas', 'amortizacaoPjParcelas', 'amortizacao_pj_parcelas'),
            'percentual_antecipacao_pj' => (float) $this->percentual_antecipacao_pj,
            'aporte_adicional_mensal' => (float) $this->aporte_adicional_mensal,
            'devolucao_aporte_percentual' => (float) $this->devolucao_aporte_percentual,
            'distribuicao_lucros_percentual_obra' => (float) $this->distribuicao_lucros_percentual_obra,
            'taxa_exposicao_aplicada' => (float) $this->taxa_exposicao_aplicada,
            'inadimplencia' => $this->resolveFloatValue([], 'inadimplencia', 'inadimplencia', 'inadimplencia', true),
            'atraso_meses' => $this->resolveIntValue([], 'atraso_meses', 'atrasoMeses', 'atraso_meses'),
            'taxa_perda' => $this->resolveFloatValue([], 'taxa_perda', 'taxaPerda', 'taxa_perda', true),
            'produtos' => $this->resolveProdutos(),
            'perfil_financiamento' => $perfil,
            'status' => $this->status,
            'approval_status' => $approvalStatus->value,
            'approval_requested_at' => $this->approval_requested_at?->toIso8601String(),
            'approval_decided_at' => $this->approval_decided_at?->toIso8601String(),
            'approval_notes' => $this->approval_notes,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'locked_at' => $this->locked_at?->toIso8601String(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            // Fluxo mensal completo sob demanda: include=monthly_cash_flow|resultados_dre|*.
            // Por padrão devolve o payload persistido (compatibilidade). Listagens leves
            // devem usar select sem a coluna JSON no repository.
            'resultados_dre' => $this->getAttribute('resultados_dre'),
            'terreno' => $terreno instanceof Terreno ? [
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
                'updated_by_user' => $updatedBy instanceof User ? [
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

        if ($this->shouldInclude($include, 'premissas_snapshot')) {
            $data['premissas_snapshot'] = $this->snapshot();
        }

        return $data;
    }

    /**
     * @param  list<string>  $attributes
     * @return array{0: mixed, 1: string|null}
     */
    private function resolveValue(
        array $attributes,
        ?string $formKey,
        ?string $snapshotParamKey,
        ?string $defaultKey
    ): array {
        foreach ($attributes as $attribute) {
            $value = $this->getAttribute($attribute);
            if ($value !== null) {
                return [$value, 'attribute'];
            }
        }

        $formValues = $this->snapshotFormValues();
        if ($formKey !== null && array_key_exists($formKey, $formValues) && $formValues[$formKey] !== null) {
            return [$formValues[$formKey], 'form'];
        }

        $snapshotParams = $this->snapshotParametros();
        if ($snapshotParamKey !== null && array_key_exists($snapshotParamKey, $snapshotParams) && $snapshotParams[$snapshotParamKey] !== null) {
            return [$snapshotParams[$snapshotParamKey], 'param'];
        }

        $defaults = $this->resolvedDefaults();
        if ($defaultKey !== null && array_key_exists($defaultKey, $defaults)) {
            return [$defaults[$defaultKey], 'default'];
        }

        return [null, null];
    }

    /**
     * @param  list<string>  $attributes
     */
    private function resolveFloatValue(
        array $attributes,
        ?string $formKey,
        ?string $snapshotParamKey,
        ?string $defaultKey,
        bool $snapshotParamIsFraction = false
    ): float {
        [$value, $source] = $this->resolveValue($attributes, $formKey, $snapshotParamKey, $defaultKey);

        if ($value === null) {
            return 0.0;
        }

        $resolved = (float) $value;

        if ($source === 'param' && $snapshotParamIsFraction) {
            return $resolved * 100;
        }

        return $resolved;
    }

    /**
     * @param  list<string>  $attributes
     */
    private function resolveIntValue(
        array $attributes,
        ?string $formKey,
        ?string $snapshotParamKey,
        ?string $defaultKey
    ): int {
        [$value] = $this->resolveValue($attributes, $formKey, $snapshotParamKey, $defaultKey);

        return $value === null ? 0 : (int) $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveProdutos(): array
    {
        $snapshotProdutos = app(ViabilidadeSnapshotService::class)->extractProdutos($this->snapshot());
        if ($snapshotProdutos !== []) {
            /** @var list<array<string, mixed>> $items */
            $items = array_values(collect($snapshotProdutos)
                ->filter(fn (mixed $produto): bool => is_array($produto))
                ->map(function (array $produto): array {
                    return [
                        'id' => (int) ($produto['id'] ?? 0),
                        'unidades' => (float) ($produto['unidades'] ?? 0),
                        'valor' => (float) ($produto['valor'] ?? 0),
                        'permuta' => (float) ($produto['permuta'] ?? 0),
                        'pgto_por_lote' => (float) ($produto['pgto_por_lote'] ?? 0),
                        'custo_m2' => (float) ($produto['custo_m2'] ?? 0),
                        'custo_infra' => (float) ($produto['custo_infra'] ?? 0),
                        '_nome' => $produto['_nome'] ?? null,
                        '_area_privativa' => isset($produto['_area_privativa'])
                            ? (float) $produto['_area_privativa']
                            : null,
                    ];
                })
                ->values()
                ->all());

            return $items;
        }

        $formValues = $this->snapshotFormValues();
        $produtos = $formValues['produtos'] ?? null;

        if (is_array($produtos)) {
            /** @var list<array<string, mixed>> $items */
            $items = array_values(collect($produtos)
                ->filter(fn (mixed $produto): bool => is_array($produto))
                ->map(function (array $produto): array {
                    return [
                        'id' => (int) ($produto['id'] ?? 0),
                        'unidades' => (float) ($produto['unidades'] ?? 0),
                        'valor' => (float) ($produto['valor'] ?? 0),
                        'permuta' => (float) ($produto['permuta'] ?? 0),
                        'pgto_por_lote' => (float) ($produto['pgto_por_lote'] ?? 0),
                        'custo_m2' => (float) ($produto['custo_m2'] ?? 0),
                        'custo_infra' => (float) ($produto['custo_infra'] ?? 0),
                        '_nome' => $produto['_nome'] ?? null,
                        '_area_privativa' => isset($produto['_area_privativa'])
                            ? (float) $produto['_area_privativa']
                            : null,
                    ];
                })
                ->values()
                ->all());

            return $items;
        }

        $resultadosDre = $this->getAttribute('resultados_dre');
        $produtosDre = is_array($resultadosDre) && is_array($resultadosDre['produtos'] ?? null)
            ? $resultadosDre['produtos']
            : [];

        /** @var list<array<string, mixed>> $items */
        $items = array_values(collect($produtosDre)
            ->filter(fn (mixed $produto): bool => is_array($produto))
            ->map(function (array $produto): array {
                return [
                    'id' => (int) ($produto['terreno_produto_id'] ?? $produto['id'] ?? 0),
                    'unidades' => (float) ($produto['quantidade_unidades'] ?? 0),
                    'valor' => (float) ($produto['preco'] ?? 0),
                    'permuta' => (float) ($produto['permutas'] ?? 0),
                    'pgto_por_lote' => (float) ($produto['pgto_por_lote'] ?? 0),
                    'custo_m2' => (float) ($produto['custo_m2'] ?? 0),
                    'custo_infra' => (float) ($produto['custo_infraestrutura'] ?? 0),
                    '_nome' => $produto['nome'] ?? null,
                    '_area_privativa' => isset($produto['metragem'])
                        ? (float) $produto['metragem']
                        : null,
                ];
            })
            ->values()
            ->all());

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $snapshot = $this->getAttribute('premissas_snapshot');

        return is_array($snapshot) ? $snapshot : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFormValues(): array
    {
        $snapshot = $this->snapshot();

        return is_array($snapshot['form_values'] ?? null)
            ? $snapshot['form_values']
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotParametros(): array
    {
        $snapshot = $this->snapshot();

        return is_array($snapshot['parametros'] ?? null)
            ? $snapshot['parametros']
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedDefaults(): array
    {
        $perfilFinanciamento = $this->getAttribute('perfil_financiamento');
        $perfil = $perfilFinanciamento instanceof PerfilFinanciamento
            ? $perfilFinanciamento->value
            : 'cef';

        if (! array_key_exists($perfil, self::$defaultsCache)) {
            self::$defaultsCache[$perfil] = app(PremissasViabilidadeService::class)
                ->resolverDefaults($perfil);
        }

        return self::$defaultsCache[$perfil];
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

    /**
     * Lista/detalhe leve: KPIs sem fluxo mensal completo.
     *
     * @return array<string, mixed>|null
     */
    private function summarizeResultados(mixed $resultados): ?array
    {
        if (! is_array($resultados)) {
            return null;
        }

        return [
            'vgv' => $resultados['vgv'] ?? null,
            'totalUnidades' => $resultados['totalUnidades'] ?? null,
            'indicadores' => $resultados['indicadores'] ?? null,
            'dre_itens' => $resultados['dre_itens'] ?? null,
            'totais' => $resultados['totais'] ?? null,
            'reconciliation' => $resultados['reconciliation'] ?? null,
            'produtos_resumo' => is_array($resultados['produtos'] ?? null)
                ? array_map(static function (mixed $produto): array {
                    if (! is_array($produto)) {
                        return [];
                    }

                    return [
                        'id' => $produto['terreno_produto_id'] ?? $produto['id'] ?? null,
                        'nome' => $produto['nome'] ?? null,
                        'unidades' => $produto['quantidade_unidades'] ?? null,
                        'vgv_produto' => $produto['vgv_produto'] ?? null,
                    ];
                }, $resultados['produtos'])
                : [],
        ];
    }
}
