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
        'pis_cofins' => 4.0,
        'iss' => 0.0,
        'outros_impostos' => 0.5,
        'comissao' => 0.0,
        'parceria_vgv' => 0.0,
        'infra_nao_incidente' => 1.0,
        'incorporacao' => 1.0,
        'incorporacao_ri' => 30.0,
        'incorporacao_entrega' => 15.0,
        'incorporacao_ate_lancamento' => 80.0,
        'area_comum' => 0.00,
        'contrapartidas' => 0.0,
        'canteiro_mensal' => 85715.0,
        'mo_administrativa' => 62502.0,
        'seguros' => 0.5,
        'assistencia_tecnica' => 1.0,
        'assistencia_tecnica_curva' => [50, 20, 10, 10, 10],
        'despesas_comerciais' => 5.0,
        'stand_vendas' => 0.0,
        'mobilia_decoracao' => 90000.0,
        'gastos_mensais_stand' => 0.01,
        'comissao_house_percentual' => 3.0,
        'comissao_imobiliarias_percentual' => 3.5,
        'percentual_vendas_house' => 50.0,
        'ajuda_custo_gerente' => 5000.0,
        'ajuda_custo_gerente_regional' => 2733.0,
        'reembolso_logistica' => 5000.0,
        'bonus_cca' => 350.0,
        'bonus_gerente' => 0.3,
        'bonus_gerente_regional' => 0.12,
        'bonus_credito' => 0.05,
        'bonus_gestor_comercial' => 0.05,
        'pagamento_comissao_venda' => 50.0,
        'pagamento_comissao_desligamento' => 50.0,
        'parcelamento_comissao_meses' => 18,
        'marketing' => 1.0,
        'marketing_lancamento' => 25.0,
        'marketing_inicio_antes_lancamento' => 3,
        'itbi_iptu' => 1.1,
        'registro' => 2500.00,
        'medicao_contratacao' => 2000.00,
        'contratos_cef' => 300.00,
        'produtos_cef' => 0.5,
        'outras_despesas_financeiras' => 0.3,
        'despesas_onerosas_bancos' => 10.0,
        'prazo_obra' => 36,
        'taxa_juros_pj' => 10.5,
        'percentual_antecipacao_pj' => 10.0,
        'carencia_pj_meses' => 6,
        'amortizacao_pj_parcelas' => 18,
        'aporte_adicional_mensal' => 0.0,
        'devolucao_aporte_percentual' => 20.0,
        'distribuicao_lucros_percentual_obra' => 100.0,
        'taxa_exposicao_aplicada' => 12.5,
        'avaliacao_lotes_cef' => [
            '2_dorm' => 20.0,
            '3_dorm' => 15.0,
            'lotes' => 0.0,
        ],

        /*
        |----------------------------------------------------------------------
        | Perfil de Financiamento
        |----------------------------------------------------------------------
        | 'cef'     = Repasse Caixa (RT + Medição de Obra)
        | 'proprio' = Financiamento próprio (apenas recebíveis do cliente)
        */
        'perfil_financiamento' => 'cef',

        /*
        |----------------------------------------------------------------------
        | Modelo de Recebíveis — Financiamento Próprio (Balões)
        |----------------------------------------------------------------------
        | Usado quando perfil_financiamento = 'proprio'.
        | baloes_anuais: array de {mes, percentual} — ex: [{mes: 12, percentual: 5}, {mes: 24, percentual: 5}]
        | balao_entrega: 'saldo_restante' (quitação) ou percentual fixo
        */
        'baloes_anuais' => [
            ['mes' => 12, 'percentual' => 5.0],
            ['mes' => 24, 'percentual' => 5.0],
        ],
        'balao_entrega_modo' => 'saldo_restante',

        /*
        |----------------------------------------------------------------------
        | Inadimplência / Atraso (apenas perfil próprio)
        |----------------------------------------------------------------------
        | inadimplencia:     % das parcelas que atrasam (0.0 a 1.0)
        | atraso_meses:      quantos meses depois a parcela atrasada entra
        |                    (0 = haircut direto, sem recuperação)
        | taxa_perda:        % da parcela atrasada que NUNCA entra (0.0 a 1.0)
        */
        'inadimplencia' => 0.10,
        'atraso_meses' => 2,
        'taxa_perda' => 0.02,
    ],

    /*
    |--------------------------------------------------------------------------
    | Prazos e Variáveis Globais
    |--------------------------------------------------------------------------
    |
    */
    'prazos' => [
        'meses_incorporacao' => 18,
        'meses_lancamento' => 6,
        'meses_entrega' => 1,
        'meses_pos_obra' => 60,
        'variavel_correcao' => 0.027545,
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
        'lotes' => [0, 0, 0, 10.0, 9.0, 8.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1, 6.1],
    ],
];
