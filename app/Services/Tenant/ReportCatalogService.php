<?php

declare(strict_types=1);

namespace App\Services\Tenant;

/**
 * Catálogo fechado do construtor de relatórios.
 *
 * O JSON do template nunca vira SQL livre: apenas chaves allowlisted daqui
 * são aceitas em definition e materializadas no ReportGenerationService.
 */
class ReportCatalogService
{
    public const MODE_AGGREGATE = 'aggregate';

    public const MODE_DETAIL = 'detail';

    public const AGGREGATE_LIMIT = 500;

    public const DETAIL_LIMIT = 2000;

    /** Máximo de colunas selecionáveis no modo detalhe (por template). */
    public const DETAIL_COLUMNS_MAX = 80;

    /** @return list<string> */
    public function datasetKeys(): array
    {
        return array_keys($this->datasets());
    }

    /** @return list<string> */
    public function formatKeys(): array
    {
        return array_keys($this->formats());
    }

    /** @return list<string> */
    public function modeKeys(): array
    {
        return [self::MODE_AGGREGATE, self::MODE_DETAIL];
    }

    /** @return list<string> */
    public function chartKeys(): array
    {
        return ['table', 'bar', 'line'];
    }

    /** @return list<string> */
    public function dimensionKeysFor(string $dataset): array
    {
        return array_keys($this->datasets()[$dataset]['dimensions'] ?? []);
    }

    /** @return list<string> */
    public function metricKeysFor(string $dataset): array
    {
        return array_keys($this->datasets()[$dataset]['metrics'] ?? []);
    }

    /** @return list<string> */
    public function columnKeysFor(string $dataset): array
    {
        return array_keys($this->datasets()[$dataset]['columns'] ?? []);
    }

    /**
     * @return array<string, string> column_key => physical_column
     */
    public function columnMapFor(string $dataset): array
    {
        $columns = $this->datasets()[$dataset]['columns'] ?? [];
        $map = [];
        foreach ($columns as $key => $meta) {
            $map[$key] = (string) $meta['column'];
        }

        return $map;
    }

    public function tableFor(string $dataset): string
    {
        return (string) ($this->datasets()[$dataset]['table'] ?? 'terrenos');
    }

    public function dimensionColumn(string $dataset, string $dimension): string
    {
        $map = $this->datasets()[$dataset]['dimensions'] ?? [];

        return (string) ($map[$dimension]['column'] ?? array_values($map)[0]['column'] ?? 'status');
    }

    public function valueColumn(string $dataset): ?string
    {
        $column = $this->datasets()[$dataset]['value_column'] ?? null;

        return is_string($column) && $column !== '' ? $column : null;
    }

    public function labelForDataset(string $dataset): string
    {
        return (string) ($this->datasets()[$dataset]['label'] ?? $dataset);
    }

    public function labelForColumn(string $dataset, string $column): string
    {
        return (string) ($this->datasets()[$dataset]['columns'][$column]['label'] ?? $column);
    }

    public function hasSoftDeletes(string $dataset): bool
    {
        return (bool) ($this->datasets()[$dataset]['soft_deletes'] ?? true);
    }

    public function mimeTypeFor(string $format): string
    {
        return match ($format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            default => 'text/csv',
        };
    }

    public function extensionFor(string $format): string
    {
        return match ($format) {
            'xlsx' => 'xlsx',
            'pdf' => 'pdf',
            default => 'csv',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $datasets = [];
        foreach ($this->datasets() as $key => $meta) {
            $datasets[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'dimensions' => array_map(
                    static fn (string $dimKey, array $dim): array => [
                        'key' => $dimKey,
                        'label' => $dim['label'],
                    ],
                    array_keys($meta['dimensions']),
                    array_values($meta['dimensions']),
                ),
                'metrics' => array_map(
                    static fn (string $metricKey, array $metric): array => [
                        'key' => $metricKey,
                        'label' => $metric['label'],
                    ],
                    array_keys($meta['metrics']),
                    array_values($meta['metrics']),
                ),
                'columns' => array_map(
                    static fn (string $columnKey, array $column): array => [
                        'key' => $columnKey,
                        'label' => $column['label'],
                    ],
                    array_keys($meta['columns']),
                    array_values($meta['columns']),
                ),
                'recommended_formats' => $meta['recommended_formats'],
                'supports_value_metric' => $this->valueColumn($key) !== null,
                'supports_detail' => $meta['columns'] !== [],
            ];
        }

        return [
            'modes' => [
                [
                    'key' => self::MODE_AGGREGATE,
                    'label' => 'Agregado',
                    'description' => 'Agrupa por dimensão e calcula métricas (count, sum_valor). Ideal para funil e KPI.',
                    'limits' => ['groups' => self::AGGREGATE_LIMIT],
                ],
                [
                    'key' => self::MODE_DETAIL,
                    'label' => 'Detalhe',
                    'description' => 'Linhas allowlisted por dataset (sem SQL livre). Ideal para books e listagens.',
                    'limits' => ['rows' => self::DETAIL_LIMIT],
                ],
            ],
            'datasets' => $datasets,
            'formats' => array_map(
                static fn (string $key, array $meta): array => [
                    'key' => $key,
                    'label' => $meta['label'],
                    'description' => $meta['description'],
                    'best_for' => $meta['best_for'],
                    'feature' => $meta['feature'],
                ],
                array_keys($this->formats()),
                array_values($this->formats()),
            ),
            'schedule_frequencies' => ['daily', 'weekly', 'monthly'],
            'charts' => array_map(
                static fn (string $key): array => [
                    'key' => $key,
                    'label' => match ($key) {
                        'bar' => 'Barras',
                        'line' => 'Linha',
                        default => 'Tabela',
                    },
                ],
                $this->chartKeys(),
            ),
            'predefined_exports' => $this->predefinedExports(),
            'recommendations' => $this->recommendations(),
            'system_templates' => $this->systemTemplateBlueprints(),
            'limits' => [
                'datasets_per_template' => 4,
                'aggregate_groups' => self::AGGREGATE_LIMIT,
                'detail_rows' => self::DETAIL_LIMIT,
                'columns_per_template' => self::DETAIL_COLUMNS_MAX,
            ],
        ];
    }

    /**
     * Blueprints dos templates de sistema (seed idempotente).
     *
     * @return list<array{system_key: string, name: string, preferred_format: string, definition: array<string, mixed>}>
     */
    public function systemTemplateBlueprints(): array
    {
        return [
            [
                'system_key' => 'funil_executivo',
                'name' => '[Sistema] Funil executivo',
                'preferred_format' => 'pdf',
                'definition' => [
                    'mode' => self::MODE_AGGREGATE,
                    'datasets' => ['terrenos', 'viabilidades', 'comites'],
                    'dimensions' => ['status', 'workflow_status_code'],
                    'metrics' => ['count', 'sum_valor'],
                    'charts' => ['table', 'bar'],
                ],
            ],
            [
                'system_key' => 'pipeline_terrenos',
                'name' => '[Sistema] Pipeline de terrenos',
                'preferred_format' => 'xlsx',
                'definition' => [
                    'mode' => self::MODE_AGGREGATE,
                    'datasets' => ['terrenos'],
                    'dimensions' => ['workflow_status_code'],
                    'metrics' => ['count', 'sum_valor'],
                    'charts' => ['bar'],
                ],
            ],
            [
                'system_key' => 'book_negociacoes',
                'name' => '[Sistema] Book de negociações',
                'preferred_format' => 'xlsx',
                'definition' => [
                    'mode' => self::MODE_DETAIL,
                    'datasets' => ['negociacoes'],
                    'dimensions' => ['status'],
                    'metrics' => ['count'],
                    'columns' => ['id', 'terreno_id', 'status', 'proposal_value', 'business_model', 'started_at', 'closed_at', 'created_at'],
                    'charts' => ['table'],
                ],
            ],
            [
                'system_key' => 'andamento_legalizacoes',
                'name' => '[Sistema] Andamento de legalizações',
                'preferred_format' => 'xlsx',
                'definition' => [
                    'mode' => self::MODE_AGGREGATE,
                    'datasets' => ['legalizacoes'],
                    'dimensions' => ['status'],
                    'metrics' => ['count'],
                    'charts' => ['table', 'bar'],
                ],
            ],
            [
                'system_key' => 'reunioes_comite',
                'name' => '[Sistema] Reuniões de comitê',
                'preferred_format' => 'pdf',
                'definition' => [
                    'mode' => self::MODE_AGGREGATE,
                    'datasets' => ['comite_reunioes'],
                    'dimensions' => ['status', 'meeting_mode'],
                    'metrics' => ['count'],
                    'charts' => ['table'],
                ],
            ],
            [
                'system_key' => 'carteira_viabilidades',
                'name' => '[Sistema] Carteira de viabilidades',
                'preferred_format' => 'xlsx',
                'definition' => [
                    'mode' => self::MODE_AGGREGATE,
                    'datasets' => ['viabilidades'],
                    'dimensions' => ['status'],
                    'metrics' => ['count', 'sum_valor'],
                    'charts' => ['bar'],
                ],
            ],
            [
                'system_key' => 'legalizacao_custos_critico',
                'name' => '[Sistema] Legalização — custos e caminho crítico',
                'preferred_format' => 'xlsx',
                'definition' => [
                    'mode' => self::MODE_AGGREGATE,
                    'datasets' => ['legalizacoes'],
                    'dimensions' => ['status'],
                    'metrics' => ['count', 'sum_custo_planejado', 'sum_custo_realizado', 'avg_critical_days'],
                    'charts' => ['table', 'bar'],
                ],
            ],
            [
                'system_key' => 'deal_room_ofertas',
                'name' => '[Sistema] Deal room — ofertas',
                'preferred_format' => 'xlsx',
                'definition' => [
                    'mode' => self::MODE_DETAIL,
                    'datasets' => ['deal_ofertas'],
                    'dimensions' => ['status'],
                    'metrics' => ['count', 'sum_valor'],
                    'columns' => ['id', 'negociacao_id', 'version', 'offer_type', 'amount', 'business_model', 'status', 'valid_until', 'created_at'],
                    'charts' => ['table'],
                ],
            ],
            [
                'system_key' => 'comite_dossies_status',
                'name' => '[Sistema] Dossiês de comitê',
                'preferred_format' => 'pdf',
                'definition' => [
                    'mode' => self::MODE_AGGREGATE,
                    'datasets' => ['comite_dossies'],
                    'dimensions' => ['status'],
                    'metrics' => ['count'],
                    'charts' => ['bar', 'table'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     table: string,
     *     soft_deletes: bool,
     *     value_column: ?string,
     *     dimensions: array<string, array{label: string, column: string}>,
     *     metrics: array<string, array{label: string}>,
     *     columns: array<string, array{label: string, column: string}>,
     *     recommended_formats: list<string>
     * }>
     */
    public function datasets(): array
    {
        return [
            'terrenos' => [
                'label' => 'Terrenos',
                'description' => 'Pipeline de prospecção e status de workflow dos terrenos.',
                'table' => 'terrenos',
                'soft_deletes' => true,
                'value_column' => 'valor',
                'dimensions' => [
                    'workflow_status_code' => ['label' => 'Status do workflow', 'column' => 'workflow_status_code'],
                    'status' => ['label' => 'Status (alias workflow)', 'column' => 'workflow_status_code'],
                    'estado' => ['label' => 'UF', 'column' => 'estado'],
                    'created_at' => ['label' => 'Data de criação', 'column' => 'created_at'],
                ],
                'metrics' => [
                    'count' => ['label' => 'Quantidade'],
                    'sum_valor' => ['label' => 'Soma de valor'],
                ],
                // Colunas tabulares da tabela terrenos (sem JSONs pesados:
                // polygon_coords, app_polygons, steep_polygons, qualification_data, static_map_url).
                'columns' => [
                    'id' => ['label' => 'ID', 'column' => 'id'],
                    'nome' => ['label' => 'Nome', 'column' => 'nome'],
                    'responsavel_id' => ['label' => 'Responsável', 'column' => 'responsavel_id'],
                    'corretor_id' => ['label' => 'Corretor externo', 'column' => 'corretor_id'],
                    'comprador_id' => ['label' => 'Comprador', 'column' => 'comprador_id'],
                    'regional_id' => ['label' => 'Regional', 'column' => 'regional_id'],
                    'endereco' => ['label' => 'Endereço', 'column' => 'endereco'],
                    'cep' => ['label' => 'CEP', 'column' => 'cep'],
                    'bairro' => ['label' => 'Bairro', 'column' => 'bairro'],
                    'estado' => ['label' => 'UF', 'column' => 'estado'],
                    'cidade_code' => ['label' => 'Cidade (IBGE)', 'column' => 'cidade_code'],
                    'zona' => ['label' => 'Zona', 'column' => 'zona'],
                    'distrito' => ['label' => 'Distrito', 'column' => 'distrito'],
                    'operacao_urbana' => ['label' => 'Operação urbana', 'column' => 'operacao_urbana'],
                    'valor' => ['label' => 'Valor', 'column' => 'valor'],
                    'area_calculada' => ['label' => 'Área calculada', 'column' => 'area_calculada'],
                    'area_total' => ['label' => 'Área total', 'column' => 'area_total'],
                    'area_declividade' => ['label' => 'Área declividade', 'column' => 'area_declividade'],
                    'area_app' => ['label' => 'Área APP', 'column' => 'area_app'],
                    'area_util' => ['label' => 'Área útil', 'column' => 'area_util'],
                    'percentual_aproveitamento' => ['label' => '% aproveitamento', 'column' => 'percentual_aproveitamento'],
                    'declividade_classificacao' => ['label' => 'Declividade (classificação)', 'column' => 'declividade_classificacao'],
                    'declividade_avaliacao' => ['label' => 'Declividade (avaliação)', 'column' => 'declividade_avaliacao'],
                    'declividade_impacto_custo' => ['label' => 'Declividade (impacto custo)', 'column' => 'declividade_impacto_custo'],
                    'declividade_percentual_maximo' => ['label' => 'Declividade % máximo', 'column' => 'declividade_percentual_maximo'],
                    'declividade_percentual_medio' => ['label' => 'Declividade % médio', 'column' => 'declividade_percentual_medio'],
                    'area_calculada_em' => ['label' => 'Área calculada em', 'column' => 'area_calculada_em'],
                    'area_calculo_status' => ['label' => 'Status cálculo de área', 'column' => 'area_calculo_status'],
                    'workflow_stage' => ['label' => 'Etapa do workflow', 'column' => 'workflow_stage'],
                    'workflow_status_code' => ['label' => 'Status do workflow', 'column' => 'workflow_status_code'],
                    'workflow_status_changed_at' => ['label' => 'Status alterado em', 'column' => 'workflow_status_changed_at'],
                    'workflow_reason_code' => ['label' => 'Motivo (código)', 'column' => 'workflow_reason_code'],
                    'workflow_reason_notes' => ['label' => 'Motivo (notas)', 'column' => 'workflow_reason_notes'],
                    'qualification_completed_at' => ['label' => 'Qualificação concluída em', 'column' => 'qualification_completed_at'],
                    'qualification_completed_by' => ['label' => 'Qualificação por', 'column' => 'qualification_completed_by'],
                    'observacoes' => ['label' => 'Observações', 'column' => 'observacoes'],
                    'data_apresentacao' => ['label' => 'Data apresentação', 'column' => 'data_apresentacao'],
                    'data_negociacao' => ['label' => 'Data negociação', 'column' => 'data_negociacao'],
                    'data_opcao' => ['label' => 'Data opção', 'column' => 'data_opcao'],
                    'data_descarte' => ['label' => 'Data descarte', 'column' => 'data_descarte'],
                    'data_contrato' => ['label' => 'Data contrato', 'column' => 'data_contrato'],
                    'created_by' => ['label' => 'Criado por', 'column' => 'created_by'],
                    'updated_by' => ['label' => 'Atualizado por', 'column' => 'updated_by'],
                    'created_at' => ['label' => 'Criado em', 'column' => 'created_at'],
                    'updated_at' => ['label' => 'Atualizado em', 'column' => 'updated_at'],
                ],
                'recommended_formats' => ['xlsx', 'pdf', 'csv'],
            ],
            'viabilidades' => [
                'label' => 'Viabilidades',
                'description' => 'Estudos de viabilidade e status de aprovação.',
                'table' => 'viabilidades',
                'soft_deletes' => true,
                'value_column' => 'parceria_vgv',
                'dimensions' => [
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'workflow_status_code' => ['label' => 'Status (alias)', 'column' => 'status'],
                    'created_at' => ['label' => 'Data de criação', 'column' => 'created_at'],
                ],
                'metrics' => [
                    'count' => ['label' => 'Quantidade'],
                    'sum_valor' => ['label' => 'Soma de VGV (parceria)'],
                ],
                // Colunas tabulares (sem JSONs: resultados_dre, premissas_snapshot, assistencia_tecnica_curva).
                'columns' => [
                    'id' => ['label' => 'ID', 'column' => 'id'],
                    'terreno_id' => ['label' => 'Terreno', 'column' => 'terreno_id'],
                    'version' => ['label' => 'Versão', 'column' => 'version'],
                    'is_current' => ['label' => 'Versão atual', 'column' => 'is_current'],
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'approval_status' => ['label' => 'Status de aprovação', 'column' => 'approval_status'],
                    'approval_requested_at' => ['label' => 'Aprovação solicitada em', 'column' => 'approval_requested_at'],
                    'approval_decided_at' => ['label' => 'Aprovação decidida em', 'column' => 'approval_decided_at'],
                    'approval_decided_by' => ['label' => 'Aprovação decidida por', 'column' => 'approval_decided_by'],
                    'approval_notes' => ['label' => 'Notas de aprovação', 'column' => 'approval_notes'],
                    'submitted_at' => ['label' => 'Submetida em', 'column' => 'submitted_at'],
                    'locked_at' => ['label' => 'Bloqueada em', 'column' => 'locked_at'],
                    'parceria_vgv' => ['label' => 'VGV parceria', 'column' => 'parceria_vgv'],
                    'compra_terreno' => ['label' => 'Compra terreno', 'column' => 'compra_terreno'],
                    'infra_nao_incidente' => ['label' => 'Infra não incidente', 'column' => 'infra_nao_incidente'],
                    'porcentagem_lote_proprietario' => ['label' => '% lote proprietário', 'column' => 'porcentagem_lote_proprietario'],
                    'prazo_obra' => ['label' => 'Prazo obra (meses)', 'column' => 'prazo_obra'],
                    'prazo_lancamento' => ['label' => 'Prazo lançamento (meses)', 'column' => 'prazo_lancamento'],
                    'prazo_incorporacao' => ['label' => 'Prazo incorporação (meses)', 'column' => 'prazo_incorporacao'],
                    'data_lancamento' => ['label' => 'Data de lançamento', 'column' => 'data_lancamento'],
                    'perfil_financiamento' => ['label' => 'Perfil financiamento', 'column' => 'perfil_financiamento'],
                    'pis_cofins' => ['label' => 'PIS/COFINS', 'column' => 'pis_cofins'],
                    'iss' => ['label' => 'ISS', 'column' => 'iss'],
                    'outros_impostos' => ['label' => 'Outros impostos', 'column' => 'outros_impostos'],
                    'comissao' => ['label' => 'Comissão', 'column' => 'comissao'],
                    'incorporacao' => ['label' => 'Incorporação', 'column' => 'incorporacao'],
                    'incorporacao_ri' => ['label' => 'Incorporação RI', 'column' => 'incorporacao_ri'],
                    'incorporacao_entrega' => ['label' => 'Incorporação entrega', 'column' => 'incorporacao_entrega'],
                    'incorporacao_ate_lancamento' => ['label' => 'Incorporação até lançamento', 'column' => 'incorporacao_ate_lancamento'],
                    'area_comum' => ['label' => 'Área comum', 'column' => 'area_comum'],
                    'contrapartidas' => ['label' => 'Contrapartidas', 'column' => 'contrapartidas'],
                    'canteiro_mensal' => ['label' => 'Canteiro mensal', 'column' => 'canteiro_mensal'],
                    'mo_administrativa' => ['label' => 'M.O. administrativa', 'column' => 'mo_administrativa'],
                    'seguros' => ['label' => 'Seguros', 'column' => 'seguros'],
                    'assistencia_tecnica' => ['label' => 'Assistência técnica', 'column' => 'assistencia_tecnica'],
                    'despesas_comerciais' => ['label' => 'Despesas comerciais', 'column' => 'despesas_comerciais'],
                    'stand_vendas' => ['label' => 'Stand de vendas', 'column' => 'stand_vendas'],
                    'mobilia_decoracao' => ['label' => 'Mobília/decoração', 'column' => 'mobilia_decoracao'],
                    'gastos_mensais_stand' => ['label' => 'Gastos mensais stand', 'column' => 'gastos_mensais_stand'],
                    'comissao_house_percentual' => ['label' => '% comissão house', 'column' => 'comissao_house_percentual'],
                    'comissao_imobiliarias_percentual' => ['label' => '% comissão imobiliárias', 'column' => 'comissao_imobiliarias_percentual'],
                    'percentual_vendas_house' => ['label' => '% vendas house', 'column' => 'percentual_vendas_house'],
                    'construcao_stand_meses_antes_lancamento' => ['label' => 'Stand (meses antes lançamento)', 'column' => 'construcao_stand_meses_antes_lancamento'],
                    'ajuda_custo_gerente' => ['label' => 'Ajuda de custo gerente', 'column' => 'ajuda_custo_gerente'],
                    'ajuda_custo_gerente_regional' => ['label' => 'Ajuda de custo gerente regional', 'column' => 'ajuda_custo_gerente_regional'],
                    'reembolso_logistica' => ['label' => 'Reembolso logística', 'column' => 'reembolso_logistica'],
                    'bonus_cca' => ['label' => 'Bônus CCA', 'column' => 'bonus_cca'],
                    'bonus_gerente' => ['label' => 'Bônus gerente', 'column' => 'bonus_gerente'],
                    'bonus_gerente_regional' => ['label' => 'Bônus gerente regional', 'column' => 'bonus_gerente_regional'],
                    'bonus_credito' => ['label' => 'Bônus crédito', 'column' => 'bonus_credito'],
                    'bonus_gestor_comercial' => ['label' => 'Bônus gestor comercial', 'column' => 'bonus_gestor_comercial'],
                    'bonus_equipe_comercial' => ['label' => 'Bônus equipe comercial', 'column' => 'bonus_equipe_comercial'],
                    'pagamento_comissao_venda' => ['label' => 'Pagamento comissão venda', 'column' => 'pagamento_comissao_venda'],
                    'pagamento_comissao_desligamento' => ['label' => 'Pagamento comissão desligamento', 'column' => 'pagamento_comissao_desligamento'],
                    'parcelamento_comissao_meses' => ['label' => 'Parcelamento comissão (meses)', 'column' => 'parcelamento_comissao_meses'],
                    'marketing' => ['label' => 'Marketing', 'column' => 'marketing'],
                    'marketing_lancamento' => ['label' => 'Marketing lançamento', 'column' => 'marketing_lancamento'],
                    'marketing_inicio_antes_lancamento' => ['label' => 'Marketing início antes lançamento', 'column' => 'marketing_inicio_antes_lancamento'],
                    'itbi_iptu' => ['label' => 'ITBI/IPTU', 'column' => 'itbi_iptu'],
                    'registro' => ['label' => 'Registro', 'column' => 'registro'],
                    'custo_contratacao_cef' => ['label' => 'Custo contratação CEF', 'column' => 'custo_contratacao_cef'],
                    'custo_medicao_cef' => ['label' => 'Custo medição CEF', 'column' => 'custo_medicao_cef'],
                    'contratos_cef' => ['label' => 'Contratos CEF', 'column' => 'contratos_cef'],
                    'produtos_cef' => ['label' => 'Produtos CEF', 'column' => 'produtos_cef'],
                    'outras_despesas_financeiras' => ['label' => 'Outras despesas financeiras', 'column' => 'outras_despesas_financeiras'],
                    'despesas_onerosas_bancos' => ['label' => 'Despesas onerosas bancos', 'column' => 'despesas_onerosas_bancos'],
                    'taxa_juros_pj' => ['label' => 'Taxa juros PJ', 'column' => 'taxa_juros_pj'],
                    'carencia_pj_meses' => ['label' => 'Carência PJ (meses)', 'column' => 'carencia_pj_meses'],
                    'amortizacao_pj_parcelas' => ['label' => 'Amortização PJ (parcelas)', 'column' => 'amortizacao_pj_parcelas'],
                    'percentual_antecipacao_pj' => ['label' => '% antecipação PJ', 'column' => 'percentual_antecipacao_pj'],
                    'usar_antecipacao_pj' => ['label' => 'Usar antecipação PJ', 'column' => 'usar_antecipacao_pj'],
                    'aporte_adicional_mensal' => ['label' => 'Aporte adicional mensal', 'column' => 'aporte_adicional_mensal'],
                    'devolucao_aporte_percentual' => ['label' => '% devolução aporte', 'column' => 'devolucao_aporte_percentual'],
                    'distribuicao_lucros_percentual_obra' => ['label' => '% distribuição lucros obra', 'column' => 'distribuicao_lucros_percentual_obra'],
                    'taxa_exposicao_aplicada' => ['label' => 'Taxa exposição aplicada', 'column' => 'taxa_exposicao_aplicada'],
                    'created_by' => ['label' => 'Criado por', 'column' => 'created_by'],
                    'updated_by' => ['label' => 'Atualizado por', 'column' => 'updated_by'],
                    'created_at' => ['label' => 'Criado em', 'column' => 'created_at'],
                    'updated_at' => ['label' => 'Atualizado em', 'column' => 'updated_at'],
                ],
                'recommended_formats' => ['xlsx', 'pdf'],
            ],
            'comites' => [
                'label' => 'Comitês',
                'description' => 'Revisões de comitê e decisões finais.',
                'table' => 'comite_revisoes',
                'soft_deletes' => true,
                'value_column' => null,
                'dimensions' => [
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'workflow_status_code' => ['label' => 'Status (alias)', 'column' => 'status'],
                    'final_decision' => ['label' => 'Decisão final', 'column' => 'final_decision'],
                    'created_at' => ['label' => 'Data de criação', 'column' => 'created_at'],
                ],
                'metrics' => [
                    'count' => ['label' => 'Quantidade'],
                ],
                'columns' => [
                    'id' => ['label' => 'ID', 'column' => 'id'],
                    'terreno_id' => ['label' => 'Terreno', 'column' => 'terreno_id'],
                    'viabilidade_id' => ['label' => 'Viabilidade', 'column' => 'viabilidade_id'],
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'final_decision' => ['label' => 'Decisão final', 'column' => 'final_decision'],
                    'decided_at' => ['label' => 'Decidido em', 'column' => 'decided_at'],
                    'created_at' => ['label' => 'Criado em', 'column' => 'created_at'],
                ],
                'recommended_formats' => ['pdf', 'xlsx'],
            ],
            'legalizacoes' => [
                'label' => 'Legalizações',
                'description' => 'Processos de legalização, custos pagos e caminho crítico.',
                'table' => 'legalizacoes',
                'soft_deletes' => true,
                'value_column' => null,
                'dimensions' => [
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'workflow_status_code' => ['label' => 'Status (alias)', 'column' => 'status'],
                    'created_at' => ['label' => 'Data de criação', 'column' => 'created_at'],
                ],
                'metrics' => [
                    'count' => ['label' => 'Quantidade'],
                    'sum_custo_planejado' => ['label' => 'Soma custo planejado'],
                    'sum_custo_realizado' => ['label' => 'Soma custo realizado (pago)'],
                    'avg_critical_days' => ['label' => 'Média dias caminho crítico'],
                    'sum_critical_days' => ['label' => 'Soma dias caminho crítico'],
                ],
                'columns' => [
                    'id' => ['label' => 'ID', 'column' => 'id'],
                    'terreno_id' => ['label' => 'Terreno', 'column' => 'terreno_id'],
                    'nome' => ['label' => 'Nome', 'column' => 'nome'],
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'percentual_concluido' => ['label' => '% concluído', 'column' => 'percentual_concluido'],
                    'data_inicio_planejada' => ['label' => 'Início planejado', 'column' => 'data_inicio_planejada'],
                    'data_fim_planejada' => ['label' => 'Fim planejado', 'column' => 'data_fim_planejada'],
                    'custo_planejado' => ['label' => 'Custo planejado', 'column' => 'id'],
                    'custo_realizado' => ['label' => 'Custo realizado', 'column' => 'id'],
                    'critical_path_days' => ['label' => 'Dias caminho crítico', 'column' => 'id'],
                    'created_at' => ['label' => 'Criado em', 'column' => 'created_at'],
                ],
                'recommended_formats' => ['xlsx', 'pdf'],
            ],
            'negociacoes' => [
                'label' => 'Negociações',
                'description' => 'Negociações em andamento, valores propostos e modelos de negócio.',
                'table' => 'negociacoes',
                'soft_deletes' => true,
                'value_column' => 'proposal_value',
                'dimensions' => [
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'workflow_status_code' => ['label' => 'Status (alias)', 'column' => 'status'],
                    'business_model' => ['label' => 'Modelo de negócio', 'column' => 'business_model'],
                    'created_at' => ['label' => 'Data de criação', 'column' => 'created_at'],
                ],
                'metrics' => [
                    'count' => ['label' => 'Quantidade'],
                    'sum_valor' => ['label' => 'Soma de valor proposto'],
                ],
                // Tabela enxuta: todas as colunas tabulares da entidade negociacoes.
                'columns' => [
                    'id' => ['label' => 'ID', 'column' => 'id'],
                    'terreno_id' => ['label' => 'Terreno', 'column' => 'terreno_id'],
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'proposal_value' => ['label' => 'Valor proposto', 'column' => 'proposal_value'],
                    'business_model' => ['label' => 'Modelo de negócio', 'column' => 'business_model'],
                    'started_at' => ['label' => 'Início', 'column' => 'started_at'],
                    'closed_at' => ['label' => 'Fechamento', 'column' => 'closed_at'],
                    'notes' => ['label' => 'Notas', 'column' => 'notes'],
                    'created_by' => ['label' => 'Criado por', 'column' => 'created_by'],
                    'updated_by' => ['label' => 'Atualizado por', 'column' => 'updated_by'],
                    'created_at' => ['label' => 'Criado em', 'column' => 'created_at'],
                    'updated_at' => ['label' => 'Atualizado em', 'column' => 'updated_at'],
                ],
                'recommended_formats' => ['xlsx', 'pdf'],
            ],
            'comite_reunioes' => [
                'label' => 'Reuniões de comitê',
                'description' => 'Sessões de reunião (modo, status e agenda operacional).',
                'table' => 'comite_meeting_sessions',
                'soft_deletes' => false,
                'value_column' => null,
                'dimensions' => [
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'workflow_status_code' => ['label' => 'Status (alias)', 'column' => 'status'],
                    'meeting_mode' => ['label' => 'Modo da reunião', 'column' => 'meeting_mode'],
                    'created_at' => ['label' => 'Data de criação', 'column' => 'created_at'],
                ],
                'metrics' => [
                    'count' => ['label' => 'Quantidade'],
                ],
                'columns' => [
                    'id' => ['label' => 'ID', 'column' => 'id'],
                    'comite_revisao_id' => ['label' => 'Revisão', 'column' => 'comite_revisao_id'],
                    'title' => ['label' => 'Título', 'column' => 'title'],
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'meeting_mode' => ['label' => 'Modo', 'column' => 'meeting_mode'],
                    'scheduled_at' => ['label' => 'Agendada em', 'column' => 'scheduled_at'],
                    'started_at' => ['label' => 'Início', 'column' => 'started_at'],
                    'ended_at' => ['label' => 'Fim', 'column' => 'ended_at'],
                    'created_at' => ['label' => 'Criado em', 'column' => 'created_at'],
                ],
                'recommended_formats' => ['pdf', 'xlsx'],
            ],
            'projetos' => [
                'label' => 'Projetos',
                'description' => 'Projetos vinculados a terrenos e status do ciclo de vida.',
                'table' => 'projetos',
                'soft_deletes' => true,
                'value_column' => null,
                'dimensions' => [
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'workflow_status_code' => ['label' => 'Status (alias)', 'column' => 'status'],
                    'created_at' => ['label' => 'Data de criação', 'column' => 'created_at'],
                ],
                'metrics' => [
                    'count' => ['label' => 'Quantidade'],
                ],
                'columns' => [
                    'id' => ['label' => 'ID', 'column' => 'id'],
                    'nome' => ['label' => 'Nome', 'column' => 'nome'],
                    'terreno_id' => ['label' => 'Terreno', 'column' => 'terreno_id'],
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'responsavel_id' => ['label' => 'Responsável', 'column' => 'responsavel_id'],
                    'created_at' => ['label' => 'Criado em', 'column' => 'created_at'],
                ],
                'recommended_formats' => ['xlsx', 'pdf'],
            ],
            'deal_ofertas' => [
                'label' => 'Deal room — Ofertas',
                'description' => 'Ofertas da negociação (amount, status, modelo de negócio).',
                'table' => 'negociacao_ofertas',
                'soft_deletes' => false,
                'value_column' => 'amount',
                'dimensions' => [
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'workflow_status_code' => ['label' => 'Status (alias)', 'column' => 'status'],
                    'offer_type' => ['label' => 'Tipo de oferta', 'column' => 'offer_type'],
                    'business_model' => ['label' => 'Modelo de negócio', 'column' => 'business_model'],
                    'created_at' => ['label' => 'Data de criação', 'column' => 'created_at'],
                ],
                'metrics' => [
                    'count' => ['label' => 'Quantidade'],
                    'sum_valor' => ['label' => 'Soma de amount'],
                ],
                'columns' => [
                    'id' => ['label' => 'ID', 'column' => 'id'],
                    'negociacao_id' => ['label' => 'Negociação', 'column' => 'negociacao_id'],
                    'version' => ['label' => 'Versão', 'column' => 'version'],
                    'offer_type' => ['label' => 'Tipo', 'column' => 'offer_type'],
                    'amount' => ['label' => 'Valor', 'column' => 'amount'],
                    'business_model' => ['label' => 'Modelo', 'column' => 'business_model'],
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'valid_until' => ['label' => 'Válida até', 'column' => 'valid_until'],
                    'accepted_at' => ['label' => 'Aceita em', 'column' => 'accepted_at'],
                    'created_at' => ['label' => 'Criado em', 'column' => 'created_at'],
                ],
                'recommended_formats' => ['xlsx', 'pdf'],
            ],
            'deal_aprovacoes' => [
                'label' => 'Deal room — Aprovações',
                'description' => 'Aprovações por área da negociação.',
                'table' => 'negociacao_aprovacoes',
                'soft_deletes' => false,
                'value_column' => null,
                'dimensions' => [
                    'status' => ['label' => 'Decisão', 'column' => 'decision'],
                    'workflow_status_code' => ['label' => 'Decisão (alias)', 'column' => 'decision'],
                    'decision' => ['label' => 'Decisão', 'column' => 'decision'],
                    'area' => ['label' => 'Área', 'column' => 'area'],
                    'created_at' => ['label' => 'Data de criação', 'column' => 'created_at'],
                ],
                'metrics' => [
                    'count' => ['label' => 'Quantidade'],
                ],
                'columns' => [
                    'id' => ['label' => 'ID', 'column' => 'id'],
                    'negociacao_id' => ['label' => 'Negociação', 'column' => 'negociacao_id'],
                    'area' => ['label' => 'Área', 'column' => 'area'],
                    'decision' => ['label' => 'Decisão', 'column' => 'decision'],
                    'decided_at' => ['label' => 'Decidido em', 'column' => 'decided_at'],
                    'created_at' => ['label' => 'Criado em', 'column' => 'created_at'],
                ],
                'recommended_formats' => ['xlsx', 'pdf'],
            ],
            'deal_condicoes' => [
                'label' => 'Deal room — Condições',
                'description' => 'Condições contratuais do deal room.',
                'table' => 'contrato_condicoes',
                'soft_deletes' => false,
                'value_column' => null,
                'dimensions' => [
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'workflow_status_code' => ['label' => 'Status (alias)', 'column' => 'status'],
                    'created_at' => ['label' => 'Data de criação', 'column' => 'created_at'],
                ],
                'metrics' => [
                    'count' => ['label' => 'Quantidade'],
                ],
                'columns' => [
                    'id' => ['label' => 'ID', 'column' => 'id'],
                    'contrato_id' => ['label' => 'Contrato', 'column' => 'contrato_id'],
                    'title' => ['label' => 'Título', 'column' => 'title'],
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'due_date' => ['label' => 'Vencimento', 'column' => 'due_date'],
                    'fulfilled_at' => ['label' => 'Cumprida em', 'column' => 'fulfilled_at'],
                    'created_at' => ['label' => 'Criado em', 'column' => 'created_at'],
                ],
                'recommended_formats' => ['xlsx', 'pdf'],
            ],
            'comite_dossies' => [
                'label' => 'Dossiês de comitê (IA)',
                'description' => 'Status dos dossiês assistidos por IA vinculados a revisões.',
                'table' => 'comite_ai_dossiers',
                'soft_deletes' => false,
                'value_column' => null,
                'dimensions' => [
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'workflow_status_code' => ['label' => 'Status (alias)', 'column' => 'status'],
                    'created_at' => ['label' => 'Data de criação', 'column' => 'created_at'],
                ],
                'metrics' => [
                    'count' => ['label' => 'Quantidade'],
                ],
                'columns' => [
                    'id' => ['label' => 'ID', 'column' => 'id'],
                    'comite_revisao_id' => ['label' => 'Revisão', 'column' => 'comite_revisao_id'],
                    'terreno_id' => ['label' => 'Terreno', 'column' => 'terreno_id'],
                    'viabilidade_id' => ['label' => 'Viabilidade', 'column' => 'viabilidade_id'],
                    'status' => ['label' => 'Status', 'column' => 'status'],
                    'provider' => ['label' => 'Provider', 'column' => 'provider'],
                    'model' => ['label' => 'Modelo', 'column' => 'model'],
                    'generated_at' => ['label' => 'Gerado em', 'column' => 'generated_at'],
                    'created_at' => ['label' => 'Criado em', 'column' => 'created_at'],
                ],
                'recommended_formats' => ['pdf', 'xlsx'],
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, description: string, best_for: list<string>, feature: string|null}>
     */
    public function formats(): array
    {
        return [
            'csv' => [
                'label' => 'CSV',
                'description' => 'Snapshot tabular leve para integração e análise rápida.',
                'best_for' => ['Dados brutos agregados ou detalhe', 'Pipelines de BI externos'],
                'feature' => null,
            ],
            'xlsx' => [
                'label' => 'Excel (.xlsx)',
                'description' => 'Planilha profissional; multi-dataset vira abas.',
                'best_for' => [
                    'Listagens e consolidados operacionais',
                    'Comparativos financeiros (VGV, valores propostos)',
                    'Books de detalhe (negociações, legalizações)',
                ],
                'feature' => 'exports.excel',
            ],
            'pdf' => [
                'label' => 'PDF',
                'description' => 'Relatório visual multi-capítulo com barras server-side para compartilhamento executivo.',
                'best_for' => [
                    'Comitês e apresentações',
                    'Funil executivo multi-dataset',
                    'Documentos oficiais para stakeholders',
                ],
                'feature' => 'exports.pdf',
            ],
        ];
    }

    public function featureForFormat(string $format): ?string
    {
        $definition = $this->formats()[$format] ?? null;
        if ($definition === null) {
            return null;
        }

        $feature = $definition['feature'];

        return $feature !== '' ? $feature : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function predefinedExports(): array
    {
        return [
            [
                'key' => 'terrenos_list_pdf',
                'module' => 'prospection',
                'format' => 'pdf',
                'route' => 'GET /api/v1/terrenos/export/pdf',
                'feature' => 'exports.pdf',
                'description' => 'Listagem filtrada de terrenos em PDF paisagem.',
            ],
            [
                'key' => 'terrenos_list_excel',
                'module' => 'prospection',
                'format' => 'xlsx',
                'route' => 'GET /api/v1/terrenos/export/excel',
                'feature' => 'exports.excel',
                'description' => 'Listagem filtrada de terrenos em Excel (chunked).',
            ],
            [
                'key' => 'terreno_detail_pdf',
                'module' => 'prospection',
                'format' => 'pdf',
                'route' => 'GET /api/v1/terrenos/{id}/export/pdf-detalhe',
                'feature' => 'exports.pdf',
                'description' => 'Ficha detalhada de um terreno.',
            ],
            [
                'key' => 'terreno_checklist_pdf',
                'module' => 'prospection',
                'format' => 'pdf',
                'route' => 'POST /api/v1/terrenos/{id}/export/check-list',
                'feature' => 'exports.pdf',
                'description' => 'Checklist de fechamento do terreno.',
            ],
            [
                'key' => 'viabilidade_pdf',
                'module' => 'viability',
                'format' => 'pdf',
                'route' => 'GET /api/v1/viabilidades/{id}/export-pdf',
                'feature' => 'exports.pdf',
                'description' => 'Relatório financeiro da viabilidade (DRE/indicadores).',
            ],
            [
                'key' => 'ai_terreno_report_pdf',
                'module' => 'ai',
                'format' => 'pdf',
                'route' => 'POST /api/v1/ai/terrenos/{id}/relatorio-pdf',
                'feature' => 'exports.pdf',
                'description' => 'Relatório narrativo de terreno gerado por IA (também assíncrono).',
            ],
            [
                'key' => 'async_tenant_exports',
                'module' => 'exports',
                'format' => 'mixed',
                'route' => 'POST /api/v1/exports',
                'feature' => 'exports.pdf|exports.excel',
                'description' => 'Pipeline assíncrono genérico (terrenos PDF/Excel, detalhe, checklist, viabilidade).',
            ],
            [
                'key' => 'committee_ai_dossier',
                'module' => 'committee',
                'format' => 'json',
                'route' => 'GET/POST /api/v1/comite/{id}/ai-dossier',
                'feature' => 'committee',
                'description' => 'Dossiê assistido de comitê (JSON seccionado).',
            ],
            [
                'key' => 'committee_ai_dossier_pdf',
                'module' => 'committee',
                'format' => 'pdf',
                'route' => 'GET /api/v1/comite/{id}/ai-dossier/export-pdf',
                'feature' => 'exports.pdf',
                'description' => 'PDF profissional das seções do dossiê de comitê (status ready).',
            ],
            [
                'key' => 'report_schedules',
                'module' => 'reports',
                'format' => 'mixed',
                'route' => 'CRUD /api/v1/reports/schedules + comando reports:run-due-schedules',
                'feature' => 'reports.builder',
                'description' => 'Agendamento recorrente de templates (daily/weekly/monthly) com e-mail ao concluir.',
            ],
        ];
    }

    /**
     * @return list<array{title: string, format: string, reason: string, datasets: list<string>}>
     */
    public function recommendations(): array
    {
        return [
            [
                'title' => 'Pipeline de terrenos por status',
                'format' => 'xlsx',
                'reason' => 'Tabela pivô para gestores filtrarem UF/status e somarem valor do pipeline.',
                'datasets' => ['terrenos'],
            ],
            [
                'title' => 'Snapshot executivo do funil',
                'format' => 'pdf',
                'reason' => 'Multi-capítulo (terrenos + viabilidades + comitês) para diretoria.',
                'datasets' => ['terrenos', 'viabilidades', 'comites'],
            ],
            [
                'title' => 'Book detalhado de negociações',
                'format' => 'xlsx',
                'reason' => 'Modo detail com proposal_value e business_model linha a linha.',
                'datasets' => ['negociacoes'],
            ],
            [
                'title' => 'Carteira de viabilidades e VGV',
                'format' => 'xlsx',
                'reason' => 'Análise financeira tabular (status × VGV parceria).',
                'datasets' => ['viabilidades'],
            ],
            [
                'title' => 'Dossiê de decisões de comitê',
                'format' => 'pdf',
                'reason' => 'Documento de auditoria com contagem por status/decisão final.',
                'datasets' => ['comites', 'comite_reunioes'],
            ],
            [
                'title' => 'Andamento de legalizações',
                'format' => 'xlsx',
                'reason' => 'Controle operacional de processos planejados/em andamento/concluídos.',
                'datasets' => ['legalizacoes'],
            ],
            [
                'title' => 'Portfólio de projetos',
                'format' => 'pdf',
                'reason' => 'Visão de status do ciclo de projetos para stakeholders.',
                'datasets' => ['projetos'],
            ],
            [
                'title' => 'Integração BI / data lake',
                'format' => 'csv',
                'reason' => 'Snapshot as-of leve (agregado ou detalhe) para ferramentas externas.',
                'datasets' => ['terrenos', 'viabilidades', 'negociacoes', 'legalizacoes'],
            ],
        ];
    }
}
