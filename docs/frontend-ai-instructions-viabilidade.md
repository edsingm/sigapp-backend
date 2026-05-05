# Instruções para IA — Frontend do Módulo de Viabilidade

Este documento contém **todas** as informações necessárias para uma IA gerar um frontend (React, Vue, Next.js, etc.) que consuma a API de viabilidades do backend Laravel.

---

## 1. Autenticação

Todas as rotas exigem autenticação **Laravel Sanctum**.

- Header obrigatório: `Authorization: Bearer {token}`
- Header de tenant: `X-Tenant-ID: {tenant_id}`
- Header: `Accept: application/json`

Sem autenticação, a API retorna `401 Unauthorized`.

---

## 2. Endpoints da API

Prefix: `/api/v1`

| Método | Rota | Descrição | Permissão necessária |
|--------|------|-----------|---------------------|
| `GET` | `/viabilidades` | Listar (paginado) | `viewAny` |
| `POST` | `/viabilidades` | Criar + calcular DRE | `create` |
| `GET` | `/viabilidades/{id}` | Visualizar detalhes | `view` |
| `PUT` | `/viabilidades/{id}` | Atualizar + recalcular DRE | `update` |
| `DELETE` | `/viabilidades/{id}` | Excluir (soft delete) | `delete` |
| `GET` | `/viabilidades/for-select` | Lista reduzida para dropdowns | `viewAny` |
| `GET` | `/viabilidades/terreno/{terrenoId}` | Filtrar por terreno | `viewAny` |
| `GET` | `/viabilidades/terreno/{terrenoId}/latest` | Última do terreno | `viewAny` |
| `POST` | `/viabilidades/compare` | Comparar 2 viabilidades | `compare` |
| `POST` | `/viabilidades/{id}/ativar` | Ativar viabilidade | `ativar` |
| `POST` | `/viabilidades/{id}/duplicate` | Duplicar | `duplicate` |
| `POST` | `/viabilidades/{id}/gerar-dre` | Gerar DRE | `gerarDre` |
| `POST` | `/viabilidades/{id}/recalcular` | Recalcular | `recalcular` |
| `POST` | `/viabilidades/{id}/restore` | Restaurar (soft delete) | `restore` |
| `POST` | `/viabilidades/{id}/solicitar-aprovacao` | Pedir aprovação | `requestApproval` |
| `POST` | `/viabilidades/{id}/aprovar` | Aprovar | `approve` |
| `POST` | `/viabilidades/{id}/reprovar` | Reprovar | `approve` |
| `GET` | `/viabilidades/{id}/export-pdf` | Exportar PDF | `export` |

### Endpoints especiais — detalhes

#### `POST /viabilidades/compare`
```json
// Request
{
  "viabilidade_1_id": 123,
  "viabilidade_2_id": 456
}

// Response: { "success": true, "data": { "viabilidade_1": {...}, "viabilidade_2": {...} } }
```

#### `POST /viabilidades/{id}/solicitar-aprovacao`
```json
// Request (body opcional)
{
  "approval_notes": "Por favor, revisar os parâmetros de marketing."
}

// Response: 200 com viabilidade atualizada (approval_status = "aguardando_aprovacao")
```

#### `POST /viabilidades/{id}/aprovar` e `/reprovar`
```json
// Request (body opcional)
{
  "approval_notes": "Aprovado conforme revisão."
}

// Response: 200 com viabilidade atualizada
// aprovar → approval_status = "aprovada"
// reprovar → approval_status = "reprovada"
```

Rate limit: **10 requisições por minuto por usuário** nos endpoints de aprovação.

---

## 3. Estrutura do Payload (POST / PUT)

### 3.1 Campos Obrigatórios (apenas no POST)

| Campo | Tipo | Regra |
|-------|------|-------|
| `terreno_id` | integer | Obrigatório. Deve existir na tabela `terrenos` e **ter produtos associados**. |

### 3.2 Campos Opcionais — Parâmetros Gerais

| Campo | Tipo | Valores/Mínimo | Máximo |
|-------|------|----------------|--------|
| `parceria_vgv` | float | ≥ 0 | — |
| `compra_terreno` | float | ≥ 0 | — |
| `infra_nao_incidente` | float | ≥ 0 | ≤ 100 |
| `porcentagem_lote_proprietario` | float | ≥ 0 | ≤ 100 |
| `prazo_obra` | integer | 18, 24, 36, 48, 60 | — |
| `prazo_lancamento` | integer | ≥ 1 | ≤ 24 |
| `prazo_incorporacao` | integer | ≥ 1 | ≤ 60 |

### 3.3 Campos Opcionais — Impostos

| Campo | Tipo | Mínimo | Máximo |
|-------|------|--------|--------|
| `pis_cofins` | float | 0 | 100 |
| `iss` | float | 0 | 100 |
| `outros_impostos` | float | 0 | 100 |

### 3.4 Campos Opcionais — Despesas de Incorporação

| Campo | Tipo | Mínimo | Máximo |
|-------|------|--------|--------|
| `comissao` | float | 0 | 100 |
| `incorporacao` | float | 0 | 100 |
| `incorporacao_ri` | float | 0 | 100 |
| `incorporacao_entrega` | float | 0 | 100 |
| `incorporacao_ate_lancamento` | float | 0 | 100 |
| `area_comum` | float | ≥ 0 | — |
| `contrapartidas` | float | 0 | 100 |

### 3.5 Campos Opcionais — Custos de Obra

| Campo | Tipo | Mínimo | Máximo |
|-------|------|--------|--------|
| `canteiro_mensal` | float | ≥ 0 | — |
| `mo_administrativa` | float | ≥ 0 | — |
| `seguros` | float | 0 | 100 |
| `assistencia_tecnica` | float | 0 | 100 |

### 3.6 Campos Opcionais — Despesas Comerciais

| Campo | Tipo | Mínimo | Máximo |
|-------|------|--------|--------|
| `despesas_comerciais` | float | 0 | 100 |
| `stand_vendas` | float | ≥ 0 | — |
| `mobilia_decoracao` | float | ≥ 0 | — |
| `construcao_stand_meses_antes_lancamento` | integer | 0 | 60 |
| `ajuda_custo_gerente` | float | ≥ 0 | — |
| `ajuda_custo_gerente_regional` | float | ≥ 0 | — |
| `reembolso_logistica` | float | ≥ 0 | — |
| `bonus_cca` | float | ≥ 0 | — |
| `bonus_gerente` | float | 0 | 100 |
| `bonus_gerente_regional` | float | 0 | 100 |
| `bonus_credito` | float | 0 | 100 |
| `bonus_gestor_comercial` | float | 0 | 100 |
| `bonus_equipe_comercial` | float | ≥ 0 | — |
| `pagamento_comissao_desligamento` | float | 0 | 100 |
| `parcelamento_comissao_meses` | integer | 1 | 120 |

### 3.7 Campos Opcionais — Marketing e Registro

| Campo | Tipo | Mínimo | Máximo |
|-------|------|--------|--------|
| `marketing` | float | 0 | 100 |
| `itbi_iptu` | float | 0 | 100 |
| `registro` | float | ≥ 0 | — |

### 3.8 Campos Opcionais — Financeiro (CEF)

| Campo | Tipo | Mínimo | Máximo |
|-------|------|--------|--------|
| `contratos_cef` | float | ≥ 0 | — |
| `produtos_cef` | float | 0 | 100 |
| `outras_despesas_financeiras` | float | ≥ 0 | — |
| `despesas_onerosas_bancos` | float | 0 | 100 |
| `percentual_antecipacao_pj` | float | 0 | 100 |
| `aporte_adicional_mensal` | float | ≥ 0 | — |
| `devolucao_aporte_percentual` | float | 0 | 100 |
| `distribuicao_lucros_percentual_obra` | float | 0 | 100 |
| `taxa_exposicao_aplicada` | float | 0 | 100 |

### 3.9 Campo Enum

| Campo | Tipo | Valores |
|-------|------|---------|
| `perfil_financiamento` | string | `"cef"` ou `"proprio"`. Default: `"cef"` |

### 3.10 Produtos (array aninhado)

```json
"produtos": [
  {
    "id": 999,
    "unidades": 100,
    "valor": 250000,
    "permuta": 0,
    "pgto_por_lote": 0,
    "custo_m2": 2500,
    "custo_infra": 12000
  }
]
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | integer | Obrigatório. `terreno_produto_id` — deve existir na tabela `terreno_produtos` |
| `unidades` | float | Obrigatório. ≥ 0 |
| `valor` | float | Obrigatório. ≥ 0 |
| `permuta` | float | Obrigatório. ≥ 0 |
| `pgto_por_lote` | float | Obrigatório. ≥ 0 |
| `custo_m2` | float | Obrigatório. ≥ 0 |
| `custo_infra` | float | Obrigatório. ≥ 0 |

> **Importante**: O terreno informado em `terreno_id` deve ter pelo menos um produto associado via tabela `terreno_produtos`. Caso contrário, a validação retorna erro.

---

## 4. Exemplo de Payload (POST /api/v1/viabilidades)

Payload mínimo funcional:

```json
{
  "terreno_id": 10,
  "prazo_obra": 24,
  "prazo_lancamento": 12,
  "prazo_incorporacao": 6,
  "pis_cofins": 3.65,
  "iss": 2,
  "incorporacao": 1,
  "contrapartidas": 1,
  "seguros": 0.5,
  "assistencia_tecnica": 1,
  "despesas_comerciais": 5,
  "marketing": 1,
  "itbi_iptu": 1.1,
  "registro": 2500,
  "contratos_cef": 300,
  "produtos_cef": 0.5,
  "outras_despesas_financeiras": 0.3,
  "infra_nao_incidente": 1.5,
  "perfil_financiamento": "cef",
  "produtos": [
    {
      "id": 999,
      "unidades": 100,
      "valor": 250000,
      "permuta": 0,
      "pgto_por_lote": 0,
      "custo_m2": 2500,
      "custo_infra": 12000
    }
  ]
}
```

Todos os campos numéricos aceitam `null` (ou podem ser omitidos). Strings vazias (`""`) são convertidas para `null` automaticamente.

---

## 5. Exemplo de Response (POST 201 Created)

O backend cria a viabilidade, roda o cálculo completo do DRE e retorna tudo em um único response.

```json
{
  "success": true,
  "message": "Viabilidade criada com sucesso",
  "data": {
    "viabilidade": {
      "id": 123,
      "terreno_id": 10,
      "version": 1,
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
      "assistencia_tecnica_curva": [50, 20, 10, 10, 10],
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
        "vgv": 25000000,
        "totalUnidades": 100,
        "unidadesPermuta": 0,
        "areaConstruida": 6000,
        "custoTotal": 16987500,
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
            "curva_vendas": [10, 10, 10, 10, 10, 10, 10, 10, 10, 10],
            "baloes_anuais": [],
            "balao_entrega_modo": "saldo_restante",
            "imposto_tributos": 0.0365,
            "imposto_iss": 0.02,
            "financeiro": {
              "sinal": 2,
              "parcela_obra": 9,
              "parcela_posChave": 9,
              "qtde_parcelas_posChave": 36,
              "juros_mensalSinal": 0,
              "juros_mensalObra": 0,
              "juros_mensalPosChave": 1,
              "correcao_anualSinal": 0,
              "correcao_anualObra": 0,
              "correcao_anualPosChave": 4.5
            }
          }
        ],
        "dre_itens": {
          "receita_total_vendas": 25000000,
          "juros_correcoes": 0,
          "receita_bruta": 25000000,
          "pis_cofins_outros": 912500,
          "iss": 500000,
          "receita_liquida": 23587500,
          "custo_terreno": 0,
          "comissao": 0,
          "incorporacao": 250000,
          "custo_total_obra": 15000000,
          "area_comum": 0,
          "contrapartidas": 250000,
          "canteiro_total": 0,
          "mo_administrativa_total": 0,
          "seguros": 125000,
          "assistencia_tecnica": 162500,
          "custos_diretos_total": 16987500,
          "lucro_bruto": 6600000,
          "despesas_comerciais": 1179375,
          "marketing": 235875,
          "itbi_iptu": 275000,
          "registro": 2500,
          "contratos_caixa": 300,
          "produtos_caixa": 117937.5,
          "despesas_operacionais_total": 1810987.5,
          "ebitda": 4789012.5,
          "outras_despesas_financeiras": 0,
          "despesas_onerosas_bancos": 0,
          "ebit": 4789012.5,
          "irpj_csll": 0,
          "lucro_liquido_projeto": 4789012.5,
          "indicadores": {
            "vgv_total": 25000000,
            "lucro_liquido": 4789012.5,
            "margem_liquida_percentual": 19.16,
            "margem_bruta_percentual": 26.4,
            "margem_ebitda_percentual": 20.3,
            "roi_percentual": 28.18
          }
        },
        "indicadores": {
          "tir_operacional": 15.3,
          "tir_sem_cef": 12.1,
          "tir_financeira": 18.7,
          "exposicao_maxima_operacional": -15200000,
          "exposicao_maxima_financeira": -13800000,
          "margem_liquida": 19.16,
          "payback_operacional_meses": 28,
          "payback_financeiro_meses": 24,
          "vso_total_percentual": 100,
          "vso_medio_mensal_percentual": 8.33,
          "vso_mensal_maximo_percentual": 10,
          "vso_mes_maximo": "2027-06",
          "vso_mes_zeragem_estoque": "2028-03",
          "unidades_vendidas_acumuladas": 100,
          "unidades_estoque_final": 0,
          "vso_janelas": {
            "3m": { "ultimo_percentual": 30, "maximo_percentual": 30, "media_percentual": 10 },
            "6m": { "ultimo_percentual": 60, "maximo_percentual": 30, "media_percentual": 10 },
            "12m": { "ultimo_percentual": 100, "maximo_percentual": 30, "media_percentual": 8.33 }
          }
        },
        "dados_produtos": {
          "total_unidades": 100,
          "unidades_permuta": 0,
          "area_construida_total": 6000
        },
        "fluxo_mensal": {
          "2026-07": {
            "periodo": "Incorporação",
            "receita_total": 0,
            "receitas": {
              "Recursos Próprios": 0,
              "Recursos Próprios (Atrasados)": 0,
              "Recurso Terrenos": 0,
              "Medição Obra": 0
            },
            "despesas": {
              "Incorporação Até Lançamento": 12500
            },
            "custos_totais": 12500,
            "lucro": -12500,
            "saldo_acumulado": -12500,
            "unidades_vendidas": 0
          },
          "2027-01": {
            "periodo": "Lançamento",
            "receita_total": 250000,
            "receitas": {
              "Recursos Próprios": 250000,
              "Recursos Próprios (Atrasados)": 0,
              "Recurso Terrenos": 0,
              "Medição Obra": 0
            },
            "despesas": {
              "Incorporação Até Lançamento": 12500,
              "Obra (Lançamento)": 62500,
              "Deduções": 14125,
              "Deduções - RET/LP Imóveis": 9125,
              "Deduções - ISS": 5000,
              "Operacional": 27500,
              "Operacional - Comissão": 15000,
              "Operacional - Marketing": 12500,
              "ITBI/IPTU": 2750,
              "Registro": 2500,
              "Taxa Contratação": 50000,
              "Produtos Caixa": 1250,
              "Contratos Caixa": 300
            },
            "custos_totais": 170675,
            "lucro": 79325,
            "saldo_acumulado": 66825,
            "unidades_vendidas": 10
          },
          "2028-01": {
            "periodo": "Obra",
            "receita_total": 140000,
            "receitas": {
              "Recursos Próprios": 75000,
              "Recursos Próprios (Atrasados)": 0,
              "Recurso Terrenos": 15000,
              "Medição Obra": 50000
            },
            "despesas": {
              "Incorporação Pós Lançamento": 8333.33,
              "Obra": 625000,
              "Canteiro": 5000,
              "Área Comum": 2500,
              "M.O. Administrativa": 8000,
              "Seguros": 2000,
              "Deduções": 7910,
              "Deduções - RET/LP Imóveis": 5110,
              "Deduções - ISS": 2800,
              "Operacional": 40000,
              "Operacional - Comissão": 15000,
              "Operacional - Marketing": 25000,
              "ITBI/IPTU": 2750,
              "Registro": 2500,
              "Taxa Medição": 15000
            },
            "custos_totais": 718993.33,
            "lucro": -578993.33,
            "saldo_acumulado": -4520000,
            "unidades_vendidas": 5
          }
        },
        "fluxo_mensal_financeiro": {
          "2027-01": {
            "valor": 79325,
            "saldo_acumulado": 79325,
            "aporte": 0,
            "devolucao_aporte": 0,
            "entrada_antecipacao_pj": 0,
            "pagamento_pj": 0,
            "exposicao_aplicada": 0
          },
          "2028-01": {
            "valor": -578993.33,
            "saldo_acumulado": -4520000,
            "aporte": 0,
            "devolucao_aporte": 0,
            "entrada_antecipacao_pj": 0,
            "pagamento_pj": 0,
            "exposicao_aplicada": 0
          },
          "2029-01": {
            "valor": 180000,
            "saldo_acumulado": 4789012.5,
            "aporte": 0,
            "devolucao_aporte": 0,
            "entrada_antecipacao_pj": 0,
            "pagamento_pj": 0,
            "exposicao_aplicada": 0
          }
        },
        "totais": {
          "receita": 25000000,
          "custo_direto": 16987500,
          "impostos": 1412500,
          "custos_operacionais": 1810987.5,
          "custos_financeiros": 0,
          "lucro": 4789012.5
        },
        "parametros_utilizados": {
          "percentualPisCofins": 0.0365,
          "percentualIss": 0.02,
          "percentualOutrosImpostos": 0,
          "percentualComissao": 0,
          "percentualIncorporacao": 0.01,
          "incorporacaoRi": 0,
          "incorporacaoEntrega": 0,
          "incorporacaoAteLancamento": 0.5,
          "custoAreaComum": 0,
          "percentualContrapartidas": 0.01,
          "canteiroMensal": 5000,
          "moAdministrativa": 8000,
          "percentualSeguros": 0.005,
          "percentualAssistenciaTecnica": 0.01,
          "percentualDespesasComerciais": 0.05,
          "standVendas": 0,
          "mobiliaDecoracao": 0,
          "percentualMarketing": 0.01,
          "custoItbiIptu": 0.011,
          "custoRegistro": 2500,
          "custoContratacaoCef": 50000,
          "custoMedicaoCef": 15000,
          "custoContratosCef": 300,
          "percentualProdutosCef": 0.005,
          "outrasDespesasFinanceirasTotal": 0,
          "mesesObra": 24,
          "mesesIncorporacao": 6,
          "mesesLancamento": 12,
          "mesesEntrega": 0,
          "mesesPosObra": 36,
          "compraTerreno": 0,
          "perfilFinanciamento": "cef"
        }
    }
  }
}
```

> **Nota**: O DRE (`resultados_dre`) **não** está mais dentro de `data.viabilidade`. Ele fica apenas em `data.dre_resultados`, sem duplicação.

### Estrutura do response resumida:

```
data.viabilidade              → todos os campos da viabilidade + relacionamentos
data.dre_resultados           → DRE + fluxo + indicadores (NÃO está dentro de viabilidade)
data.dre_resultados.terreno   → terreno com seus produtos
data.dre_resultados.produtos  → produtos processados com curvas de venda
data.dre_resultados.dre_itens → DRE Gerencial completo
data.dre_resultados.indicadores → TIR, payback, exposição, VSO, VSO janelas
data.dre_resultados.fluxo_mensal → fluxo operacional mês a mês (chave YYYY-MM)
data.dre_resultados.fluxo_mensal_financeiro → fluxo financeiro mês a mês
data.dre_resultados.totais    → totais agregados (receita, custos, impostos, lucro)
data.dre_resultados.parametros_utilizados → premissas usadas no cálculo (camelCase)
```

---

## 6. Formato de Erro

A API retorna erros no formato padrão Laravel:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "terreno_id": ["O campo terreno é obrigatório."],
    "produtos.0.unidades": ["O campo produtos.0.unidades deve ser maior ou igual a 0."]
  }
}
```

HTTP status codes:
- `200` — Sucesso (GET, PUT, ações especiais)
- `201` — Criado (POST)
- `204` — Sem conteúdo (DELETE)
- `401` — Não autenticado
- `403` — Sem permissão
- `404` — Não encontrado
- `422` — Erro de validação
- `429` — Rate limit excedido (aprovação)

---

## 7. Máquina de Estados (Workflow)

```
                    ┌──────────┐
                    │ rascunho │  ← viabilidade recém-criada
                    └────┬─────┘
                         │ solicitar-aprovacao
                         ▼
              ┌──────────────────────┐
              │ aguardando_aprovacao │
              └─────────┬────────────┘
                   ┌────┴────┐
                   ▼         ▼
            ┌─────────┐ ┌──────────┐
            │ aprovada│ │reprovada │
            └────┬────┘ └──────────┘
                 │ ativar
                 ▼
            ┌────────┐
            │ ativo  │
            └────────┘
```

Regras de negócio:
- Só é possível solicitar aprovação se o status for `rascunho`
- Só é possível aprovar/reprovar se o status for `aguardando_aprovacao`
- Só é possível ativar se o status for `aprovada` ou `ativo`
- O campo `approval_status` reflete o estado da aprovação: `pendente`, `aguardando_aprovacao`, `aprovada`, `reprovada`
- O campo `status` reflete o estado da viabilidade: `rascunho`, `aguardando_aprovacao`, `aprovada`, `ativo`, `reprovada`

---

## 8. Paginação (GET /viabilidades)

O endpoint de listagem é paginado. Query parameters aceitos:

| Parâmetro | Tipo | Padrão | Descrição |
|-----------|------|--------|-----------|
| `page` | integer | 1 | Número da página |
| `per_page` | integer | 15 | Itens por página |

Response (exemplo):

```json
{
  "success": true,
  "data": [
    { /* ViabilidadeResource */ },
    { /* ViabilidadeResource */ }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  },
  "links": {
    "first": "/api/v1/viabilidades?page=1",
    "last": "/api/v1/viabilidades?page=5",
    "prev": null,
    "next": "/api/v1/viabilidades?page=2"
  }
}
```

---

## 9. Relacionamentos Importantes

### terreno_id → Terreno

O terreno **precisa ter produtos associados** (tabela `terreno_produtos`). Sem produtos, a criação da viabilidade falha com erro de validação.

### produtos[*].id → terreno_produtos

Cada item no array `produtos` referencia um registro da tabela `terreno_produtos` (que liga um terreno a um produto com quantidade, valor, etc.). O `id` aqui é o `id` da tabela `terreno_produtos`.

---

## 10. Estrutura do Fluxo Mensal

O `fluxo_mensal` é um **objeto cujas chaves são meses no formato `YYYY-MM`**, ordenados cronologicamente. Cada entrada representa o resultado financeiro daquele mês.

### 10.1 Quantos meses?

O número total de meses no fluxo é:

```
prazo_incorporacao + prazo_lancamento + prazo_obra + (pós-obra)
```

Exemplo: `incorporacao=6, lancamento=12, obra=24, pos_obra=36` → **78 meses** no fluxo.

### 10.2 Períodos (valor do campo `periodo`)

| Período | Quando ocorre |
|---------|--------------|
| `"Incorporação"` | Meses antes do lançamento (`prazo_incorporacao`) |
| `"Lançamento"` | Durante o período de vendas (`prazo_lancamento`) |
| `"Obra"` | Durante a construção (`prazo_obra`) |
| `"Entrega"` | Mês da entrega das chaves |
| `"Pós-Obra"` | Meses após a entrega (recebimento de parcelas pós-chave) |
| `"Transição"` | Meses entre períodos (raro) |

### 10.3 Estrutura de cada mês

```json
"2027-01": {
  "periodo": "Lançamento",
  "receita_total": 250000.00,
  "receitas": {
    "Recursos Próprios": 250000.00,
    "Recursos Próprios (Atrasados)": 0.00,
    "Recurso Terrenos": 0.00,
    "Medição Obra": 0.00
  },
  "despesas": {
    "Incorporação Até Lançamento": 12500.00,
    "Obra (Lançamento)": 62500.00,
    "Deduções": 14125.00,
    "Deduções - RET/LP Imóveis": 9125.00,
    "Deduções - ISS": 5000.00,
    "Operacional": 27500.00,
    "Operacional - Comissão": 15000.00,
    "Operacional - Marketing": 12500.00,
    "ITBI/IPTU": 2750.00,
    "Registro": 2500.00,
    "Taxa Contratação": 50000.00,
    "Produtos Caixa": 1250.00,
    "Contratos Caixa": 300.00
  },
  "custos_totais": 170675.00,
  "lucro": 79325.00,
  "saldo_acumulado": 66825.00,
  "unidades_vendidas": 10
}
```

#### Chaves possíveis de `receitas`:

| Chave | Significado |
|-------|------------|
| `Recursos Próprios` | Sinal + parcelas obra + parcelas pós-chave (perfil CEF) ou parcelas próprias (perfil próprio) |
| `Recursos Próprios (Atrasados)` | Parcelas em atraso recuperadas (perfil próprio com inadimplência) |
| `Recurso Terrenos` | Repasses da CEF para o terreno (perfil CEF, após demanda mínima atingida) |
| `Medição Obra` | Repasses da CEF para a obra (perfil CEF, conforme curva de medição) |

> Perfil `proprio` só terá `Recursos Próprios` com valor > 0. As demais chaves (`Recurso Terrenos`, `Medição Obra`) só aparecem no perfil `cef`.

#### Chaves possíveis de `despesas` (o backend filtra valores ≤ 0.01):

| Categoria | Chaves |
|-----------|--------|
| **Incorporação** | `Incorporação Até Lançamento`, `Incorporação Pós Lançamento`, `Incorporação RI`, `Incorporação Entrega` |
| **Obra** | `Obra`, `Obra (Lançamento)`, `Canteiro`, `Área Comum`, `M.O. Administrativa`, `Seguros` |
| **Deduções (impostos)** | `Deduções`, `Deduções - RET/LP Imóveis`, `Deduções - RET/LP Lotes`, `Deduções - ISS`, `Deduções - Outras` |
| **Operacional** | `Operacional`, `Operacional - Comissão`, `Operacional - Stand`, `Operacional - Mobília`, `Operacional - Marketing` |
| **CEF** | `ITBI/IPTU`, `Registro`, `Taxa Contratação`, `Taxa Medição`, `Produtos Caixa`, `Contratos Caixa` |
| **Terreno** | `Custo Terreno`, `Pagamento Terreno`, `Pagamento Terreno - Parceria VGV`, `Pagamento Terreno - Permuta Física`, `Pagamento Terreno - Comissão Corretor` |
| **Financeiro** | `Outras Despesas Financeiras` |

### 10.4 Estrutura do `fluxo_mensal_financeiro`

Objeto complementar com as mesmas chaves `YYYY-MM`, focado no fluxo de caixa financeiro:

```json
"2028-01": {
  "valor": -578993.33,
  "saldo_acumulado": -4520000.00,
  "aporte": 0.00,
  "devolucao_aporte": 0.00,
  "entrada_antecipacao_pj": 0.00,
  "pagamento_pj": 0.00,
  "exposicao_aplicada": 0.00
}
```

| Campo | Significado |
|-------|------------|
| `valor` | Resultado financeiro líquido do mês |
| `saldo_acumulado` | Saldo acumulado até o mês (equivale à necessidade de capital) |
| `aporte` | Aporte adicional do incorporador naquele mês |
| `devolucao_aporte` | Devolução de aporte ao incorporador |
| `entrada_antecipacao_pj` | Entrada de antecipação de recebíveis PJ |
| `pagamento_pj` | Pagamento de parcelas PJ (juros + amortização) |
| `exposicao_aplicada` | Exposição financeira com taxa aplicada |

### 10.5 Derivando dados do fluxo para gráficos

- **Receita vs Despesa (barras empilhadas)**: `receita_total` (barra positiva) e `custos_totais` (barra negativa) por mês
- **Saldo acumulado (linha)**: `saldo_acumulado` ao longo dos meses — mostra a curva de necessidade de capital. O valor mais negativo é a `exposicao_maxima_operacional`
- **VSO — Velocity of Sales (linha)**: acumular `unidades_vendidas` mês a mês e dividir pelo total de unidades
- **Lucro mensal (barras)**: `lucro` (pode ser positivo ou negativo)
- **Payback**: mês em que `saldo_acumulado` se torna positivo

### 10.6 O objeto `totais`

Agregação de todos os meses do fluxo:

```json
"totais": {
  "receita": 25000000.00,
  "custo_direto": 16987500.00,
  "impostos": 1412500.00,
  "custos_operacionais": 1810987.50,
  "custos_financeiros": 0.00,
  "lucro": 4789012.50
}
```

> `lucro` = `receita` - `custo_direto` - `impostos` - `custos_operacionais` - `custos_financeiros`

---

## 11. Instruções para o Frontend

### 11.1 Estrutura de Telas Recomendada

1. **Lista de Viabilidades** (`/viabilidades`)
   - Tabela paginada com colunas: ID, Terreno, Status, VGV, Lucro Líquido, Margem, Data de criação
   - Filtro por terreno (dropdown)
   - Botões: Nova Viabilidade, Ver, Editar, Excluir, Duplicar
   - Ações em lote: nenhuma (operações individuais)

2. **Formulário de Criação/Edição** (`/viabilidades/nova` e `/viabilidades/{id}/editar`)
   - Selecionar terreno (dropdown ou busca)
   - **Seções colapsáveis** agrupando campos por categoria:
     - Parâmetros Gerais (prazo_obra, prazo_lancamento, prazo_incorporacao, perfil_financiamento)
     - Impostos (pis_cofins, iss, outros_impostos)
     - Despesas de Incorporação (incorporacao, area_comum, contrapartidas, comissao)
     - Custos de Obra (canteiro_mensal, mo_administrativa, seguros, assistencia_tecnica)
     - Despesas Comerciais (despesas_comerciais, stand_vendas, marketing, bonus_*)
     - Financeiro (contratos_cef, produtos_cef, percentual_antecipacao_pj, etc.)
     - Produtos (tabela editável de produtos do terreno)
   - Todos os campos numéricos devem aceitar valores decimais com ponto (.)
   - Campos percentuais devem usar `type="number"` com `step="0.01"`
   - Mostrar loading state durante POST/PUT (o cálculo do DRE pode demorar)

3. **Visualização** (`/viabilidades/{id}`)
   - Cards de KPI: VGV, Lucro Líquido, Margem Líquida (%), ROI (%)
   - Tabela do DRE Gerencial completo
   - Gráficos (ver seção 10 para detalhes da estrutura dos dados):
     - **Receita vs Custos** (barras empilhadas): `fluxo_mensal[mes].receita_total` vs `fluxo_mensal[mes].custos_totais`
     - **Saldo Acumulado** (linha): `fluxo_mensal[mes].saldo_acumulado` — curva de necessidade de capital
     - **VSO — Velocity of Sales** (linha): acumular `fluxo_mensal[mes].unidades_vendidas` mês a mês
     - **Lucro Mensal** (barras): `fluxo_mensal[mes].lucro` (positivo/negativo)
     - **DRE Gerencial**: usar `dre_itens` para tabela de DRE padrão (receita bruta → lucro líquido)
   - Botões de ação: Editar, Solicitar Aprovação, Duplicar, Exportar PDF, Excluir
   - Timeline de aprovações (se houver)

4. **Workflow / Ações**
   - Botão "Solicitar Aprovação" visível apenas quando `status === "rascunho"`
   - Botões "Aprovar" / "Reprovar" visíveis apenas quando `status === "aguardando_aprovacao"` e usuário tem permissão
   - Botão "Ativar" visível quando `status === "aprovada"`
   - Modal de confirmação com campo `approval_notes` (textarea, opcional, máx 5000 chars)

5. **Comparação** (`/viabilidades/comparar`)
   - Dois dropdowns para selecionar viabilidades do mesmo terreno
   - Tabela lado a lado com os principais indicadores

### 11.2 Regras de Formulário

- **Terreno deve ter produtos**: Ao selecionar um terreno, validar se ele tem produtos. Se não tiver, mostrar mensagem orientando o usuário a cadastrar produtos primeiro.
- **Campos vazios**: Enviar como `null` ou omitir do payload. Não enviar string vazia `""`.
- **Produtos**: No formulário de edição, carregar os produtos já salvos. No de criação, carregar os produtos do terreno selecionado.
- **Valores percentuais**: São enviados como números decimais (ex: `3.65` para 3,65%, NÃO `0.0365`). A API espera o valor percentual bruto.
- **Ao editar**: O backend recalcula o DRE automaticamente após salvar.

### 11.3 Tratamento de Erros no Frontend

- Erro `422`: exibir mensagens de validação campo a campo
- Erro `403`: redirecionar ou mostrar mensagem de "sem permissão"
- Erro `429`: mostrar mensagem "muitas tentativas, aguarde X segundos"
- Erro `404`: mostrar "não encontrado"
- Em `DELETE`, esperar status `204` (sem corpo de resposta)

### 11.4 Cores e Status Badges

| Status | Cor sugerida |
|--------|-------------|
| `rascunho` | cinza / slate |
| `aguardando_aprovacao` | amarelo / amber |
| `aprovada` | verde / emerald |
| `reprovada` | vermelho / red |
| `ativo` | azul / sky |

### 11.5 Cache

A API usa cache tags. Após criar, atualizar ou excluir uma viabilidade, a listagem é automaticamente invalidada. Se o frontend fizer refresh após mutações, os dados estarão atualizados.

---

## 12. Dependências e Pré-requisitos

Antes de consumir a API de viabilidades, o frontend precisa de:

1. **Autenticação funcional** — login que retorne token Sanctum
2. **Lista de terrenos** — endpoint `GET /api/v1/terrenos` (ou similar)
3. **Produtos do terreno** — endpoint para buscar `terreno_produtos` de um terreno específico
4. **Permissões do usuário** — o frontend deve saber quais ações o usuário pode executar (view, create, update, delete, approve, etc.)

---

## 13. Resumo dos Endpoints por Ordem de Uso no Frontend

```
1. GET  /terrenos                    → carregar dropdown de terrenos
2. GET  /terrenos/{id}/produtos      → carregar produtos do terreno (validar antes de criar)
3. POST /viabilidades                → criar nova (com produtos)
4. GET  /viabilidades                → listar (com paginação)
5. GET  /viabilidades/{id}           → visualizar detalhes + DRE
6. PUT  /viabilidades/{id}           → editar
7. POST /viabilidades/{id}/solicitar-aprovacao → workflow
8. POST /viabilidades/{id}/aprovar   → workflow
9. POST /viabilidades/{id}/reprovar  → workflow
10.POST /viabilidades/{id}/ativar    → workflow
11.POST /viabilidades/{id}/duplicate → duplicar
12.POST /viabilidades/{id}/recalcular→ recalcular DRE
13.POST /viabilidades/compare        → comparar 2 viabilidades
14.GET  /viabilidades/{id}/export-pdf → baixar PDF
15.DELETE /viabilidades/{id}         → excluir
16.POST /viabilidades/{id}/restore   → restaurar
17.GET  /viabilidades/for-select     → dropdown leve (id + label)
18.GET  /viabilidades/terreno/{id}/latest → última viabilidade do terreno
```
