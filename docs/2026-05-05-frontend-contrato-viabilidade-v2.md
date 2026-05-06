# Frontend — Contrato Atual da API de Viabilidade

Este documento explica como o frontend deve consumir o novo contrato da API de viabilidade após a redução do payload padrão e a introdução de blocos opcionais via `include`.

## Objetivo

Antes, a API retornava um payload muito grande com DRE, fluxos mensais, dados duplicados de produtos e metadados administrativos juntos.

Agora, o contrato foi reorganizado com a seguinte estratégia:

- o payload padrão entrega apenas o necessário para a tela principal
- os blocos pesados entram somente quando o frontend solicitar via `include`
- a auditoria saiu do payload padrão e virou bloco opcional
- a resposta ficou mais estável para listagem, detalhes, comparação e exportação

## Endpoints afetados

O novo contrato vale principalmente para os endpoints que retornam cálculo:

- `POST /api/v1/viabilidades`
- `GET /api/v1/viabilidades/{id}`
- `PUT /api/v1/viabilidades/{id}`
- `POST /api/v1/viabilidades/{id}/recalcular`

## Headers esperados

- `Authorization: Bearer {token}`
- `X-Tenant-ID: {tenant_id}`
- `Accept: application/json`

## Estrutura padrão da resposta

Sem `include`, a API retorna:

```json
{
  "success": true,
  "data": {
    "viabilidade": {
      "id": 16,
      "terreno_id": 1,
      "version": 1,
      "is_current": true,
      "parceria_vgv": 0,
      "compra_terreno": 0,
      "infra_nao_incidente": 0,
      "porcentagem_lote_proprietario": 0,
      "prazo_obra": 24,
      "prazo_lancamento": 12,
      "prazo_incorporacao": 6,
      "pis_cofins": 4,
      "iss": 0,
      "outros_impostos": 0.5,
      "comissao": 0,
      "incorporacao": 1,
      "area_comum": 2000,
      "contrapartidas": 1,
      "canteiro_mensal": 75000,
      "mo_administrativa": 82000,
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
      "pagamento_comissao_venda": 0,
      "pagamento_comissao_desligamento": 0,
      "parcelamento_comissao_meses": 1,
      "marketing": 1,
      "itbi_iptu": 1.1,
      "registro": 2500,
      "custo_contratacao_cef": 48000,
      "custo_medicao_cef": 1250,
      "contratos_cef": 5000,
      "produtos_cef": 0.5,
      "outras_despesas_financeiras": 0.3,
      "despesas_onerosas_bancos": 10,
      "percentual_antecipacao_pj": 0,
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
      "created_at": "2026-05-05 19:30:00",
      "updated_at": "2026-05-05 19:30:00",
      "deleted_at": null,
      "terreno": {
        "id": 1,
        "nome": "Terreno Exemplo"
      }
    },
    "resumo": {
      "vgv": 236900000,
      "receita_liquida": 210300000,
      "custos_diretos": 120500000,
      "despesas_operacionais": 18500000,
      "lucro_liquido": 41500000,
      "custo_total_projeto": 168800000
    },
    "indicadores": {
      "tir_operacional": 0.23,
      "tir_financeira": 0.19,
      "exposicao_maxima_operacional": 22500000,
      "exposicao_maxima_financeira": 28700000,
      "payback_operacional_meses": 19,
      "payback_financeiro_meses": 24,
      "margem_liquida": 19.7
    },
    "produtos_resumo": [
      {
        "terreno_produto_id": 10,
        "produto_id": 3,
        "nome": "Casa 2Q",
        "quantidade_unidades": 120,
        "permutas": 0,
        "preco": 230000,
        "vgv_produto": 27600000,
        "metragem": 58
      }
    ]
  },
  "message": "Viabilidade criada com sucesso"
}
```

## O que vem sempre

### `data.viabilidade`

Contém os dados persistidos da viabilidade.

Use para:

- cabeçalho da página
- formulário de edição
- status e workflow
- informações básicas do terreno

### `data.resumo`

Contém os números executivos principais.

Use para:

- cards de KPI
- cabeçalho financeiro da tela
- resumo da análise

### `data.indicadores`

Contém os principais indicadores financeiros e operacionais.

Use para:

- cards de TIR
- exposição máxima
- payback
- métricas de margem

### `data.produtos_resumo`

Contém uma visão resumida dos produtos calculados.

Use para:

- tabela rápida de produtos
- resumo comercial
- totalização por produto

## O que não vem mais por padrão

Os blocos abaixo não vêm no payload padrão:

- `dre`
- `dre_caixa`
- `dre_contabil_poc`
- `dre_contabil_poc_mensal`
- `dre_contabil_poc_mensal_blocos`
- `ponte_reconciliacao`
- `fluxo_mensal`
- `fluxo_mensal_financeiro`
- `totais`
- `dados_produtos`
- `parametros_utilizados`
- `auditoria`

O frontend deve pedir explicitamente quando precisar.

## Como usar `include`

O parâmetro `include` é enviado por query string e aceita múltiplos valores separados por vírgula.

### Exemplos

#### Buscar detalhes básicos da viabilidade

```http
GET /api/v1/viabilidades/16
```

#### Buscar DRE resumido e fluxo mensal

```http
GET /api/v1/viabilidades/16?include=dre,fluxo_mensal
```

#### Buscar parâmetros do cálculo para tela técnica

```http
GET /api/v1/viabilidades/16?include=parametros_utilizados
```

#### Buscar auditoria para workflow

```http
GET /api/v1/viabilidades/16?include=auditoria
```

#### Buscar tudo

```http
GET /api/v1/viabilidades/16?include=*
```

## Includes disponíveis

| Include | Finalidade |
|---|---|
| `dre` | DRE principal do projeto |
| `dre_caixa` | visão caixa |
| `dre_contabil_poc` | visão contábil POC consolidada |
| `dre_contabil_poc_mensal` | visão contábil POC mensal |
| `dre_contabil_poc_mensal_blocos` | visão mensal agrupada em blocos |
| `ponte_reconciliacao` | reconciliação entre visões |
| `fluxo_mensal` | fluxo mensal operacional |
| `fluxo_mensal_financeiro` | fluxo mensal financeiro |
| `totais` | totais consolidados |
| `dados_produtos` | totais agregados dos produtos |
| `parametros_utilizados` | parâmetros efetivamente usados no cálculo |
| `auditoria` | usuários, aprovações e seções |

## Exemplo com `include`

```json
{
  "success": true,
  "data": {
    "viabilidade": {
      "id": 16,
      "terreno_id": 1,
      "version": 1,
      "status": "rascunho",
      "approval_status": "pendente"
    },
    "resumo": {
      "vgv": 236900000,
      "receita_liquida": 210300000,
      "custos_diretos": 120500000,
      "despesas_operacionais": 18500000,
      "lucro_liquido": 41500000,
      "custo_total_projeto": 168800000
    },
    "indicadores": {
      "tir_operacional": 0.23,
      "tir_financeira": 0.19
    },
    "produtos_resumo": [],
    "dre": {
      "receita_total_vendas": 236900000,
      "receita_liquida": 210300000,
      "custos_diretos_total": 120500000,
      "despesas_operacionais_total": 18500000,
      "lucro_liquido_projeto": 41500000
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
    }
  }
}
```

## Regra prática para o frontend

### Tela de listagem

Não use endpoint detalhado com includes pesados.

Prefira:

- `GET /api/v1/viabilidades`

### Tela de detalhe da viabilidade

Carregue primeiro o payload padrão.

Depois carregue blocos pesados sob demanda:

- aba resumo: sem `include`
- aba DRE: `include=dre`
- aba fluxo: `include=fluxo_mensal,fluxo_mensal_financeiro`
- aba contábil: `include=dre_contabil_poc,dre_contabil_poc_mensal,dre_contabil_poc_mensal_blocos,ponte_reconciliacao`
- aba auditoria: `include=auditoria`
- aba técnica: `include=parametros_utilizados,totais,dados_produtos`

### Tela de edição

Use `data.viabilidade` como fonte para preencher o formulário.

Não use `parametros_utilizados` para repopular campos de edição, porque esse bloco representa parâmetros efetivamente usados no cálculo e pode misturar:

- valores vindos da viabilidade
- valores padrão vindos das premissas
- aliases internos em camelCase

## Compatibilidade importante

### Campo de contratação CEF

O backend aceita o alias legado:

- request: `medicao_contratacao`

Mas o contrato persistido e retornado ao frontend usa:

- `custo_contratacao_cef`

Se o frontend ainda estiver enviando `medicao_contratacao`, o backend converte para `custo_contratacao_cef`.

Recomendação:

- para telas novas, envie `custo_contratacao_cef`
- mantenha leitura de `custo_contratacao_cef` como campo oficial

### Campos antigos removidos do contrato persistido

Os campos abaixo não devem mais ser tratados como parte do contrato principal da viabilidade:

- `incorporacao_ri`
- `incorporacao_entrega`
- `incorporacao_ate_lancamento`
- `marketing_lancamento`
- `marketing_inicio_antes_lancamento`
- `medicao_contratacao` como campo persistido
- `taxa_juros_pj`
- `carencia_pj_meses`
- `amortizacao_pj_parcelas`

Esses valores podem continuar existindo internamente nas premissas e no cálculo, mas não devem ser assumidos pelo frontend como campos persistidos da entidade `viabilidade`.

## Diferença entre blocos

### `viabilidade`

Representa a entidade persistida.

### `resumo`

Representa um recorte executivo do cálculo.

### `indicadores`

Representa métricas derivadas do cálculo.

### `parametros_utilizados`

Representa a memória de cálculo efetiva, com chaves internas em camelCase.

### `auditoria`

Representa informações administrativas e de workflow.

## Boas práticas no frontend

- trate `resumo`, `indicadores` e `produtos_resumo` como obrigatórios nos endpoints de cálculo
- trate todos os blocos de `include` como opcionais
- não assuma que `fluxo_mensal` e `dre_contabil_poc_mensal` sempre existirão
- não replique lógica financeira no frontend
- não derive formulário a partir de `parametros_utilizados`
- carregue `auditoria` somente quando a interface realmente precisar

## Estratégia recomendada de consumo

### Opção 1: carga progressiva

1. carregar `GET /viabilidades/{id}`
2. renderizar cabeçalho, cards, resumo e produtos
3. ao abrir aba específica, buscar o include correspondente

### Opção 2: tela técnica completa

Se a tela for exclusivamente analítica, usar:

```http
GET /api/v1/viabilidades/{id}?include=dre,dre_caixa,dre_contabil_poc,dre_contabil_poc_mensal,dre_contabil_poc_mensal_blocos,ponte_reconciliacao,fluxo_mensal,fluxo_mensal_financeiro,totais,dados_produtos,parametros_utilizados
```

Use essa abordagem apenas em páginas que realmente precisem do pacote completo.

## Resumo final

O frontend deve seguir esta regra:

- primeiro consumir o payload padrão
- só pedir detalhes quando a interface precisar
- tratar `viabilidade` como entidade persistida
- tratar `resumo` e `indicadores` como visão executiva
- tratar `parametros_utilizados` como bloco técnico
- tratar `auditoria` como bloco administrativo

## Arquivos de referência no backend

Se o time do frontend quiser conferir a origem do contrato:

- [ViabilidadeCalculationResource](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Resources/Tenant/ViabilidadeCalculationResource.php)
- [ViabilidadeResource](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Resources/Tenant/ViabilidadeResource.php)
- [ViabilidadeRequest](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Requests/Tenant/ViabilidadeRequest.php)
