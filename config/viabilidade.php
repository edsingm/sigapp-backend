<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Parâmetros Padrão da Viabilidade
    |--------------------------------------------------------------------------
    |
    | Valores utilizados quando não há uma viabilidade específica cadastrada
    | ou como fallback para cálculos.
    |
    */
    'defaults' => [
        'pis_cofins' => 3.65,
        'iss' => 0.0,
        'outros_impostos' => 0.0,
        'comissao' => 0.0,
        'parceria_vgv' => 0.0,
        'infra_nao_incidente' => 1.5,
        'incorporacao' => 1.0,
        'area_comum' => 0.00,
        'contrapartidas' => 1.0,
        'canteiro_mensal' => 0.0,
        'mo_administrativa' => 0.0,
        'seguros' => 0.5,
        'assistencia_tecnica' => 1.0,
        'despesas_comerciais' => 5.0,
        'marketing' => 1.0,
        'itbi_iptu' => 1.1,
        'registro' => 2500.00,
        'medicao_contratacao' => 2000.00,
        'contratos_cef' => 300.00,
        'produtos_cef' => 0.5,
        'outras_despesas_financeiras' => 0.3,
        'despesas_onerosas_bancos' => 10.0,
        'prazo_obra' => 36,
        'juros_pj' => 15.23,
        'avaliacao_lotes_cef' => 15.0, // Percentual de avaliação de lotes CEF (15%)
    ],

    /*
    |--------------------------------------------------------------------------
    | Prazos e Variáveis Globais
    |--------------------------------------------------------------------------
    |
    */
    'prazos' => [
        'meses_incorporacao' => 18, // Ajustado de 18 para 12 conforme L2 da Aux_Obras
        'meses_lancamento' => 4,
        'meses_entrega' => 1,
        'meses_pos_obra' => 60,
        'variavel_correcao' => 0.027545, // corrige as parcelas mensais para adicionar no VGV.
    ],

    /*
    |--------------------------------------------------------------------------
    | Curvas de Vendas
    |--------------------------------------------------------------------------
    |
    | Distribuição percentual de vendas por tipo de produto.
    |
    */
    'curvas_vendas' => [
        '2_dorm' => [10.0, 9.0, 8.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1],
        '3_dorm' => [10.0, 9.0, 8.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1],
        'lotes'  => [0, 0, 0, 10.0, 9.0, 8.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1],
    ],
];
