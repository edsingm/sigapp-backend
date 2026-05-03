# Backend — Retorno ao Criar Viabilidade (POST /api/v1/viabilidades)

Este documento descreve o payload retornado pelo backend ao criar uma nova viabilidade (com cálculo do DRE/Fluxo já executado).

## Endpoint

- Método: `POST`
- Rota (tenant): `/api/v1/viabilidades`
- Controller: `ViabilidadeController@store`
- Response wrapper: `ApiResponseService::created(...)`

## Formato do Response

- Status: `201`
- Content-Type: `application/json`

### JSON (exemplo)

```json
{
  "success": true,
  "data": {
    "viabilidade": {
      "id": 123,
      "terreno_id": 10,
      "version": 3,
      "is_current": true,
      "parceria_vgv": 0,
      "compra_terreno": 0,
      "infra_nao_incidente": 1.5,
      "porcentagem_lote_proprietario": 0,
      "prazo_obra": 24,
      "prazo_lancamento": 12,
      "prazo_incorporacao": 6,
      "pis_cofins": 3.65,
      "iss": 2,
      "outros_impostos": 0,
      "comissao": 0,
      "incorporacao": 1,
      "incorporacao_ri": 0,
      "incorporacao_entrega": 0,
      "incorporacao_ate_lancamento": 0,
      "area_comum": 0,
      "contrapartidas": 1,
      "canteiro_mensal": 0,
      "mo_administrativa": 0,
      "seguros": 0.5,
      "assistencia_tecnica": 1,
      "assistencia_tecnica_curva": [
        50,
        20,
        10,
        10,
        10
      ],
      "despesas_comerciais": 5,
      "stand_vendas": 0,
      "mobilia_decoracao": 0,
      "gastos_mensais_stand": 0,
      "comissao_house_percentual": 0,
      "comissao_imobiliarias_percentual": 0,
      "percentual_vendas_house": 0,
      "ajuda_custo_gerente": 0,
      "ajuda_custo_gerente_regional": 0,
      "reembolso_logistica": 0,
      "bonus_cca": 0,
      "bonus_gerente": 0,
      "bonus_gerente_regional": 0,
      "bonus_credito": 0,
      "bonus_gestor_comercial": 0,
      "bonus_equipe_comercial": 0,
      "pagamento_comissao_venda": 0,
      "pagamento_comissao_desligamento": 0,
      "parcelamento_comissao_meses": 1,
      "marketing": 1,
      "marketing_lancamento": 0,
      "marketing_inicio_antes_lancamento": 0,
      "itbi_iptu": 1.1,
      "registro": 2500,
      "medicao_contratacao": 0,
      "contratos_cef": 300,
      "produtos_cef": 0.5,
      "outras_despesas_financeiras": 0.3,
      "despesas_onerosas_bancos": 10,
      "taxa_juros_pj": 0,
      "percentual_antecipacao_pj": 0,
      "carencia_pj_meses": 0,
      "amortizacao_pj_parcelas": 0,
      "aporte_adicional_mensal": 0,
      "devolucao_aporte_percentual": 0,
      "distribuicao_lucros_percentual_obra": 0,
      "taxa_exposicao_aplicada": 0,
      "perfil_financiamento": "cef",
      "status": "rascunho",
      "approval_status": "pendente",
      "approval_requested_at": null,
      "approval_decided_at": null,
      "approval_notes": null,
      "submitted_at": null,
      "locked_at": null,
      "resultados_dre": {
        "terreno": {
          "id": 10,
          "nome": "Terreno XPTO",
          "area_calculada": 12345.67,
          "data_contrato": "2026-04-01",
          "terreno_produtos": [
            {
              "id": 999,
              "terreno_id": 10,
              "produto_id": 7,
              "unidades": 100,
              "valor": 250000,
              "permuta": 0,
              "pgto_por_lote": 0,
              "produto": {
                "id": 7,
                "name": "Produto A",
                "private_area": 60,
                "m2_cost": 2500,
                "infra_cost": 12000
              }
            }
          ]
        },
        "vgv": 25000000,
        "totalUnidades": 100,
        "unidadesPermuta": 0,
        "areaConstruida": 6000,
        "custoTotal": 12345678.9,
        "produtos": [
          {
            "id": 7,
            "terreno_produto_id": 999,
            "nome": "Produto A",
            "preco": 250000,
            "metragem": 60,
            "quantidade_unidades": 100,
            "custo_m2": 2500,
            "custo_infraestrutura": 12000,
            "vgv_produto": 25000000,
            "avaliacao_lotesCef": 0,
            "permutas": 0,
            "pgto_por_lote": 0,
            "demanda_minCef": 0,
            "curva_vendas": [
              10,
              10,
              10,
              10,
              10,
              10,
              10,
              10,
              10,
              10
            ],
            "baloes_anuais": [],
            "balao_entrega_modo": "saldo_restante",
            "imposto_tributos": 0.0365,
            "imposto_iss": 0.02,
            "imposto_outros": 0,
            "gastos_mensais_stand": 0,
            "comissao_house": 0,
            "porcentagem_comissao_house": 0,
            "porcentagem_comissao_imobs": 0,
            "pagto_comissao_venda": 0,
            "marketing_lancamento": 0,
            "marketing_antes_lancamento": 0,
            "custo_contratacao_cef": 0,
            "pj_taxa_juros": 0.105,
            "pj_carencia_pos_obra": 0,
            "pj_qtde_parcelas": 0,
            "assist_tecnica_curva": [
              50,
              20,
              10,
              10,
              10
            ],
            "incorp_ri": 0,
            "incorp_entrega": 0,
            "incorp_ate_lancamento": 0,
            "financeiro": {
              "sinal": 0,
              "parcela_obra": 0,
              "parcela_posChave": 0,
              "qtde_parcelas_posChave": 0,
              "juros_mensalSinal": 0,
              "juros_mensalObra": 0,
              "juros_mensalPosChave": 0,
              "correcao_anualSinal": 0,
              "correcao_anualObra": 0,
              "correcao_anualPosChave": 0,
              "imposto_pis": 0,
              "imposto_cofins": 0,
              "imposto_iss": 0,
              "outras_deducoes": 0,
              "irrpj": 0,
              "csll": 0
            }
          }
        ],
        "dre_itens": {
          "receita_total_vendas": 25000000,
          "juros_correcoes": 0,
          "receita_bruta": 25000000,
          "pis_cofins_outros": 912500,
          "iss": 500000,
          "outras_deducoes": 0,
          "receita_liquida": 23587500,
          "custo_terreno": 0,
          "comissao": 0,
          "incorporacao": 250000,
          "incorporacao_detalhes": {
            "ri": 0,
            "entrega": 0,
            "projetos": 0
          },
          "infra_casas": 15000000,
          "infra_lotes": 1200000,
          "area_comum": 0,
          "contrapartidas": 250000,
          "canteiro_total": 0,
          "mo_administrativa_total": 0,
          "seguros": 125000,
          "assistencia_tecnica": 162500,
          "custo_total_obra": 0,
          "custos_diretos_total": 16987500,
          "lucro_bruto": 6600000,
          "despesas_comerciais": 1179375,
          "despesas_comerciais_detalhes": {
            "comissao": 0,
            "stand": 0,
            "mobilia": 0
          },
          "marketing": 235875,
          "itbi_iptu": 275000,
          "registro": 2500,
          "tx_medicao_contratacao": 0,
          "contratos_caixa": 300,
          "produtos_caixa": 117937.5,
          "despesas_operacionais_total": 1810987.5,
          "ebitda": 4789012.5,
          "outras_despesas_financeiras": 0,
          "despesas_onerosas_bancos": 0,
          "juros_pj": 0,
          "juros_pj_detalhes": {
            "valor_antecipado": 0,
            "taxa_mensal": 0,
            "carencia_meses": 0,
            "amortizacao_parcelas": 0
          },
          "ebit": 4789012.5,
          "irpj_csll": 0,
          "lucro_liquido_projeto": 4789012.5,
          "custo_total_projeto": 0,
          "indicadores": {
            "vgv_total": 25000000,
            "lucro_liquido": 4789012.5,
            "margem_liquida_percentual": 19.16,
            "margem_liquida_sobre_rol": 20.3,
            "margem_liquida_sobre_vgv_sem_permuta": 19.16,
            "margem_bruta_percentual": 27.98,
            "margem_ebitda_percentual": 20.3,
            "margem_ebit_percentual": 20.3,
            "roi_percentual": 28.18,
            "total_custos_diretos": 16987500,
            "custo_total_projeto": 0
          }
        },
        "dre_caixa": {
          "receita_total": 25000000,
          "custo_total": 0,
          "resultado_total": 0,
          "margem_liquida_percentual": 0
        },
        "dre_contabil_poc": {
          "percentual_execucao_obra": 0,
          "receita_reconhecida_poc": 0,
          "lucro_bruto_contabil": 0,
          "margem_bruta_contabil_percentual": 0
        },
        "dre_contabil_poc_mensal": {},
        "dre_contabil_poc_mensal_blocos": {
          "resumo": {
            "receita_reconhecida_poc_total": 0,
            "resultado_contabil_total": 0,
            "margem_contabil_percentual": 0
          },
          "blocos": []
        },
        "ponte_reconciliacao": {
          "bridge_contabil": {},
          "itens": []
        },
        "indicadores": {
          "tir_operacional": 0,
          "tir_sem_cef": 0,
          "exposicao_maxima_operacional": 0,
          "margem_liquida": 0,
          "tir_financeira": 0,
          "exposicao_maxima_financeira": 0,
          "payback_operacional_meses": null,
          "payback_financeiro_meses": null,
          "exposicao_aplicada_total": 0,
          "vso_total_percentual": 0,
          "vso_medio_mensal_percentual": 0,
          "vso_mensal_maximo_percentual": 0,
          "vso_mes_maximo": null,
          "vso_mes_zeragem_estoque": null,
          "unidades_vendidas_acumuladas": 0,
          "unidades_estoque_final": 0,
          "vso_janelas": {
            "3m": {
              "ultimo_percentual": 0,
              "maximo_percentual": 0,
              "media_percentual": 0
            },
            "6m": {
              "ultimo_percentual": 0,
              "maximo_percentual": 0,
              "media_percentual": 0
            },
            "12m": {
              "ultimo_percentual": 0,
              "maximo_percentual": 0,
              "media_percentual": 0
            }
          }
        },
        "dados_produtos": {
          "total_unidades": 100,
          "unidades_permuta": 0,
          "area_construida_total": 6000
        },
        "fluxo_mensal": {
          "2026-01": {
            "periodo": "Incorporação",
            "receita_total": 0,
            "receitas": {},
            "despesas": {},
            "custos_totais": 0,
            "lucro": 0,
            "saldo_acumulado": 0,
            "unidades_vendidas": 0
          }
        },
        "fluxo_mensal_financeiro": {
          "2026-01": {
            "valor": 0,
            "saldo_acumulado": 0,
            "aporte": 0,
            "devolucao_aporte": 0,
            "entrada_antecipacao_pj": 0,
            "pagamento_pj": 0,
            "exposicao_aplicada": 0
          }
        },
        "totais": {
          "receita": 0,
          "custo_direto": 0,
          "impostos": 0,
          "custos_operacionais": 0,
          "custos_financeiros": 0,
          "lucro": 0
        },
        "parametros_utilizados": {
          "percentualImpostos": 0.0565,
          "percentualPisCofins": 0.0365,
          "percentualIss": 0.02,
          "percentualOutrosImpostos": 0,
          "percentualComissao": 0,
          "parceriaVgv": 0,
          "infraNaoIncidente": 0.015,
          "porcentagemLoteProprietario": 0,
          "percentualIncorporacao": 0.01,
          "custoAreaComum": 0,
          "percentualContrapartidas": 0.01,
          "canteiroMensal": 0,
          "moAdministrativa": 0,
          "percentualSeguros": 0.005,
          "percentualAssistenciaTecnica": 0.01,
          "percentualDespesasComerciais": 0.05,
          "standVendas": 0,
          "mobiliaDecoracao": 0,
          "construcaoStandMesesAntesLancamento": 0,
          "ajudaCustoGerente": 0,
          "ajudaCustoGerenteRegional": 0,
          "reembolsoLogistica": 0,
          "bonusCca": 0,
          "bonusGerente": 0,
          "bonusGerenteRegional": 0,
          "bonusCredito": 0,
          "bonusGestorComercial": 0,
          "bonusEquipeComercial": 0,
          "pagamentoComissaoDesligamento": 0,
          "parcelamentoComissaoMeses": 1,
          "parcelamentoComissaoTerreno": 1,
          "percentualMarketing": 0.01,
          "custoItbiIptu": 0.011,
          "custoRegistro": 2500,
          "custoContratacaoCef": 0,
          "custoMedicaoCef": 0,
          "custoContratosCef": 300,
          "percentualProdutosCef": 0.005,
          "outrasDespesasFinanceirasTotal": 0.3,
          "mesesObra": 24,
          "mesesIncorporacao": 6,
          "mesesLancamento": 12,
          "mesesEntrega": 0,
          "mesesPosObra": 0,
          "compraTerreno": 0,
          "taxaJurosPj": 0.105,
          "carenciaPjMeses": 0,
          "amortizacaoPjParcelas": 0,
          "percentualAntecipacaoPj": 0,
          "aporteAdicionalMensal": 0,
          "devolucaoAportePercentual": 0,
          "distribuicaoLucrosPercentualObra": 0,
          "taxaExposicaoAplicada": 0,
          "incorporacaoRi": 0,
          "incorporacaoEntrega": 0,
          "incorporacaoAteLancamento": 0,
          "obraAteLancamento": 0,
          "marketingInicioAntesLancamento": 0
        }
      },
      "created_at": "2026-05-03 14:55:00",
      "updated_at": "2026-05-03 14:55:00",
      "deleted_at": null,
      "terreno": {
        "id": 10,
        "nome": "Terreno XPTO"
      },
      "created_by_user": {
        "id": 1,
        "name": "Usuário"
      },
      "user": {
        "id": 1,
        "name": "Usuário"
      },
      "approval_decided_by_user": null,
      "sections": [],
      "approvals": []
    },
    "dre_resultados": {
      "terreno": {
        "id": 10,
        "nome": "Terreno XPTO",
        "area_calculada": 12345.67,
        "data_contrato": "2026-04-01",
        "terreno_produtos": []
      },
      "vgv": 25000000,
      "totalUnidades": 100,
      "unidadesPermuta": 0,
      "areaConstruida": 6000,
      "custoTotal": 12345678.9,
      "produtos": [],
      "dre_itens": {},
      "dre_caixa": {},
      "dre_contabil_poc": {},
      "dre_contabil_poc_mensal": {},
      "dre_contabil_poc_mensal_blocos": {},
      "ponte_reconciliacao": {},
      "indicadores": {},
      "dados_produtos": {},
      "fluxo_mensal": {},
      "fluxo_mensal_financeiro": {},
      "totais": {
        "receita": 0,
        "custo_direto": 0,
        "impostos": 0,
        "custos_operacionais": 0,
        "custos_financeiros": 0,
        "lucro": 0
      },
      "parametros_utilizados": {}
    }
  },
  "message": "Viabilidade criada com sucesso"
}
```

## Observações práticas para o frontend

- O campo `data.viabilidade.resultados_dre` normalmente contém o mesmo objeto de `data.dre_resultados` (o backend também salva `resultados_dre` na viabilidade).
- `data.dre_resultados.terreno` é um `Terreno` serializado com `terreno_produtos` e `produto` (subset de campos) porque é retornado diretamente do cálculo.
- As chaves dentro de `dre_resultados.parametros_utilizados` são em camelCase (ex.: `percentualPisCofins`, `mesesObra`) porque refletem o mapa interno de premissas/calculadora.

