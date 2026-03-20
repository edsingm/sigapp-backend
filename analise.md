Aqui está o relatório completo do sistema de viabilidade:

---

# Relatório Completo — Sistema de Viabilidade

## Visão Geral

O sistema é uma **engine de análise financeira de projetos imobiliários** que gera DRE (Demonstração de Resultado de Exercício) e fluxo de caixa mensal, com versionamento, workflow de aprovação e exportação em PDF.

---

## Arquivos do Sistema

| Tipo | Arquivo |
|------|---------|
| Model | `app/Models/Tenant/Viabilidade.php` |
| Model | `app/Models/Tenant/ViabilidadeAprovacao.php` |
| Model | `app/Models/Tenant/ViabilidadeSecao.php` |
| Controller | `app/Http/Controllers/Api/V1/Tenant/ViabilidadeController.php` |
| Service | `app/Services/Tenant/Viabilidade/ViabilidadeService.php` |
| Service | `app/Services/Tenant/Viabilidade/ViabilidadeUnificadoService.php` (1079 linhas) |
| Service | `app/Services/Tenant/Viabilidade/CurvaService.php` |
| Service | `app/Services/Tenant/Viabilidade/ImpostosService.php` |
| Request | `app/Http/Requests/Tenant/ViabilidadeRequest.php` |
| Config | `config/viabilidade.php` |
| Migration | `database/migrations/tenant/2026_02_07_000000_create_viabilidades_table.php` |
| Migration | `database/migrations/tenant/2026_03_03_000002_add_approval_fields_to_viabilidades_table.php` |
| View | `resources/views/exports/viabilidade-pdf.blade.php` |

---

## Modelo Viabilidade — 25 Campos de Entrada

### Impostos (percentuais)
| Campo | Padrão | Descrição |
|-------|--------|-----------|
| `pis_cofins` | 3.65% | PIS/COFINS |
| `iss` | 0.0% | ISS |
| `outros_impostos` | 0.0% | Outros impostos |

### Custos de Obra (percentuais sobre VGV)
| Campo | Padrão | Descrição |
|-------|--------|-----------|
| `infra_nao_incidente` | 1.5% | Infraestrutura não incidente |
| `incorporacao` | 1.0% | Taxa de incorporação |
| `area_comum` | 0.0% | Área comum |
| `contrapartidas` | 1.0% | Contrapartidas |
| `seguros` | 0.5% | Seguros |
| `assistencia_tecnica` | 1.0% | Assistência técnica |

### Despesas Operacionais (percentuais)
| Campo | Padrão | Descrição |
|-------|--------|-----------|
| `despesas_comerciais` | 5.0% | Despesas comerciais |
| `marketing` | 1.0% | Marketing |
| `comissao` | 0.0% | Comissão de vendas |
| `itbi_iptu` | 1.1% | ITBI/IPTU |

### Custos Fixos (R$)
| Campo | Padrão | Descrição |
|-------|--------|-----------|
| `registro` | R$2.500 | Registro |
| `medicao_contratacao` | R$2.000 | Taxa de medição |
| `contratos_cef` | R$300 | Contratos CEF |
| `canteiro_mensal` | 0.0% | Canteiro mensal |
| `mo_administrativa` | 0.0% | Mão de obra administrativa |

### Financiamento CEF
| Campo | Padrão | Descrição |
|-------|--------|-----------|
| `produtos_cef` | 0.5% | Produtos CEF |
| `outras_despesas_financeiras` | 0.3% | Outras despesas financeiras |
| `despesas_onerosas_bancos` | 10.0% | Despesas onerosas bancos |

### Configuração Geral
| Campo | Padrão | Descrição |
|-------|--------|-----------|
| `prazo_obra` | 36 meses | Prazo (18, 24, 36, 48 ou 60) |
| `parceria_vgv` | 0.0% | Parceria no VGV |
| `porcentagem_lote_proprietario` | — | % do lote para proprietário |
| `compra_terreno` | — | Valor de compra do terreno |

---

## Fluxo de Cálculo — ViabilidadeUnificadoService

### Sequência Principal (`gerarFluxoMensal`)

```
1. Busca terreno + produtos
2. Monta parâmetros (config + overrides da viabilidade)
3. Processa produtos → VGV, custo, áreas, curvas de venda
4. Calcula períodos:
   ├── Incorporação: 18 meses antes do lançamento
   ├── Lançamento:   4 meses
   ├── Obra:         prazo_obra (18–60 meses)
   ├── Entrega:      1 mês
   └── Pós-obra:     60 meses
5. Pré-calcula Recursos Próprios (cache mês a mês)
6. Inicializa caches CEF
7. LOOP mensal → calcularReceitas() + calcularDespesas()
8. Calcula indicadores: TIR, Exposição Máxima, Margem Líquida
9. Consolida DRE final
```

---

## Cálculo de Receitas (por mês)

Três fontes:

### 1. Recursos Próprios
```
Sinal            → parcelas durante lançamento
Parcelas de obra → com correção composta (5% a.a. mensal)
Parcelas pós-chave → amortização + juros (1%/mês) + correção (4.5%/a.a.)
                     36 parcelas sobre saldo devedor decrescente
```

### 2. Recurso Terrenos (CEF)
```
- Acumula vendas dos primeiros 4 meses de lançamento
- Defasagem de 2 meses após fim do lançamento
- 2º mês de obra: libera acumulado (meses 1–4)
- 3º+ mês de obra: liberação normal com defasagem
```

### 3. Medição de Obra (CEF)
```
Medição Teórica = % Obra Acumulada (Curva S) × Valor Total Financiado
Medição Vendida = Medição Teórica × % Vendas Acumulada
Valor recebido  = Diferença do acumulado do mês anterior
```

---

## Cálculo de Despesas (por mês)

Cinco categorias:

| Categoria | Cálculo |
|-----------|---------|
| Custos Diretos | Proporcionais ao período (Curva S de obra) |
| Tributos | PIS, COFINS, ISS, IRPJ, CSLL por produto |
| Custos Operacionais | % da receita (comerciais + marketing) |
| Custos Financeiros | % da receita (produtos CEF + outras despesas) |
| Custo Terreno | Proporcional à receita do mês |

---

## Estrutura da DRE Gerada

```
RECEITA BRUTA
  Receita total de vendas (VGV sem terrenista)
  + Juros e correções
  = Receita Bruta

DEDUÇÕES
  − PIS/COFINS
  − ISS
  − Outras deduções
  = RECEITA LÍQUIDA

CUSTOS DIRETOS
  − Custo do terreno
  − Comissão
  − Incorporação
  − Infraestrutura (casas + lotes)
  − Área comum, contrapartidas
  − Canteiro, MO administrativa
  − Seguros, assistência técnica
  = LUCRO BRUTO

DESPESAS OPERACIONAIS
  − Despesas comerciais, marketing
  − ITBI/IPTU, registro
  − Taxa de medição, contratos CEF
  − Produtos CEF
  = EBITDA

DESPESAS FINANCEIRAS
  − Outras despesas financeiras
  − Despesas onerosas bancos
  − Juros PJ (antecipação de recebíveis — 10% do valor, taxa 15.23% a.a.)
  = EBIT

IMPOSTOS
  − IRPJ
  − CSLL
  = LUCRO LÍQUIDO
```

---

## Indicadores Financeiros

| Indicador | Descrição |
|-----------|-----------|
| **TIR** | Taxa Interna de Retorno (calculada sobre fluxo mensal) |
| **Margem Líquida** | Lucro Líquido / VGV |
| **ROI** | Lucro Líquido / Custo Total |
| **Exposição Máxima** | Pior saldo acumulado no fluxo mensal |

---

## CurvaService — Distribuição Temporal

**Curva S de obra** disponível para: 18, 20, 24, 30, 36 meses
- Distribui % do custo total por mês de obra

**Curvas de venda** por tipo de produto:
- `2_dorm`: começa em 10% no lançamento, reduz gradualmente
- `3_dorm`: mesma lógica
- `lotes`: 0% durante incorporação/lançamento, inicia na obra

---

## ImpostosService — Distribuição dos Tributos

| Tributo | % da receita de impostos |
|---------|--------------------------|
| PIS | 9.25% |
| COFINS | 42.75% |
| IRPJ | 31.50% |
| CSLL | 16.50% |

**Juros PJ:** 10% do valor de obra, taxa 15.23% a.a., suporta simples ou compostos.

---

## Endpoints REST (17 rotas)

| Método | Rota | Ação |
|--------|------|------|
| POST | `/viabilidades` | Criar + gerar DRE |
| GET | `/viabilidades` | Listar (paginado, cache 30min) |
| GET | `/viabilidades/{id}` | Buscar com DRE |
| PUT | `/viabilidades/{id}` | Atualizar + recalcular |
| DELETE | `/viabilidades/{id}` | Soft delete |
| POST | `/viabilidades/{id}/restore` | Restaurar |
| POST | `/viabilidades/{id}/duplicate` | Clonar versão |
| POST | `/viabilidades/{id}/ativar` | Rascunho → Ativo |
| POST | `/viabilidades/{id}/solicitar-aprovacao` | Submeter para aprovação |
| POST | `/viabilidades/{id}/aprovar` | Aprovar |
| POST | `/viabilidades/{id}/reprovar` | Reprovar |
| POST | `/viabilidades/{id}/gerar-dre` | Gerar DRE |
| POST | `/viabilidades/{id}/recalcular` | Recalcular DRE |
| POST | `/viabilidades/compare` | Comparar duas viabilidades |
| GET | `/viabilidades/for-select` | Para dropdowns |
| GET | `/viabilidades/terreno/{id}` | Por terreno |
| GET | `/viabilidades/{id}/export-pdf` | Exportar PDF |

---

## Workflow de Aprovação

```
rascunho (pendente)
    ↓ solicitar-aprovacao
em_aprovacao
    ↓ aprovar              ↓ reprovar
ativo (locked_at=now)   rascunho
```

Integrações:
- Push notification ao solicitar aprovação
- Transição de workflow: `AGUARDANDO_VIABILIDADE` → `VIABILIDADE_APROVADA` ou `EM_ANALISE`
- Histórico em `ViabilidadeAprovacao` (cada decisão registrada)

---

## Valores Padrão (config/viabilidade.php)

Prazos fixos de workflow:
- `meses_incorporacao`: 18
- `meses_lancamento`: 4
- `meses_entrega`: 1
- `meses_pos_obra`: 60
- `variavel_correcao`: 0.027545

---

Entendido o sistema por completo. O que precisamos fazer nele?



Aqui está o resultado completo da análise após ler todas as abas da planilha:

---

## Resultado da Análise: Planilha vs Sistema

### O que está CORRETO ✅

Os seguintes cálculos batem com a planilha:
- Prazos: incorporação (18m), obra (36m), pós-obra (60m)
- Distribuição de impostos na DRE: PIS 9.25%, COFINS 42.75%, IRPJ 31.5%, CSLL 16.5%
- Demanda mínima CEF: 30%
- Estrutura da DRE completa (receita bruta → líquida → EBITDA → EBIT → lucro líquido)
- Curva de vendas (estrutura decrescente por tipologia)
- Parcelas pós-chave: 36 parcelas, 1%/mês, correção 4.5%/a.a.

---

### DIVERGÊNCIAS — valores calculados errados ❌

| # | Item | Planilha | Sistema | Impacto |
|---|------|---------|---------|---------|
| 1 | **Prazo de Lançamento** | **6 meses** | **4 meses** | Toda curva de vendas e acumulação CEF deslocada |
| 2 | **Taxa Juros PJ** | **10.5% a.a.** | **15.23% a.a.** | Despesas financeiras ~45% maiores do que deveriam |
| 3 | **Avaliação CEF 2 Dorm** | **20%** | **15% fixo** | Recurso Terrenos e Medição de Obra errados |
| 4 | **Defasagem Terrenista** | **1 mês** | **2 meses** | Saída de caixa deslocada no tempo |
| 5 | **Carência Antecipação PJ** | **6 meses após obra** | Não implementado | Fluxo pós-obra com timing errado |

---

### O que FALTA implementar 🔧

| # | Item | Descrição |
|---|------|-----------|
| A | **Stand de Vendas** | R$200k em 6x + R$90k em 3x antes do lançamento + gastos mensais 0.01% VGV/42m |
| B | **Comissão detalhada** | House 3% vs Imobiliárias 3.5%, 50% na venda + 50% no desligamento + bônus comerciais |
| C | **Marketing temporal** | 25% no lançamento + 75% por unidade vendida (não % genérico da receita) |
| D | **Incorporação parcelada** | 80% em 24 parcelas até lançamento + 20% em 42 parcelas após |
| E | **Assistência Técnica por anos** | Ano1: 50%, Ano2: 20%, Anos3-5: 10% (distribuído mensalmente em pós-obra) |
| F | **TIR dupla** | TIR Operacional (sem aporte) + TIR Financeira (com antecipação PJ) |
| G | **Exposição dupla** | Exposição Operacional + Financeira separadas, com payback próprio |
| H | **Exposição aplicada** | Custo de oportunidade (12.5% a.a.) sobre exposição até payback e entrega |
| I | **Permuta financeira proporcional** | % VGV pago ao terrenista proporcional à entrada de caixa, com/sem deduções |
| J | **Seguros parcelado** | Parcelas mensais do lançamento até fim da obra (não % genérico mensal) |

---

**Valores de referência para validação** (1000 unidades, 2 Dorm, R$220k):
- Receita Bruta: R$ 225.367.872
- Lucro Líquido: R$ 65.993.867 (**margem 30.05%**)
- TIR Operacional: **6.26% a.a.**
- TIR Financeira: **10.20% a.a.**

Como quer prosseguir? Podemos atacar primeiro as divergências críticas (prazo lançamento, taxa PJ, avaliação CEF) ou prefere que eu liste em ordem de prioridade para implementação?


Aqui está o resumo do relatório completo que preparei:

---

## Relatório: Sistema de Viabilidade vs Planilha Excel

### O que está CORRETO (maioria dos cálculos bate):
- VGV, Receita Bruta, Receita Líquida
- Distribuição de impostos (PIS 9.25%, COFINS 42.75%, IRPJ 31.5%, CSLL 16.5%)
- Custos diretos: Incorporação, Casas, Lotes/Infra, Contrapartidas, Seguros, Assist. Técnica
- Despesas operacionais: Comerciais, Marketing, ITBI, Registro, Medição, Contratos CEF, Produtos CEF
- Fluxo de caixa: Curvas S, Curvas de vendas, Sinal, Parcelas Obra/Pós-Chave, CEF (RT + Medição)
- Indicadores: TIR, Exposição Máxima

### DIVERGÊNCIAS encontradas (4 críticas):
1. **Comissão** — sistema calcula sobre VGV, planilha calcula sobre custo terreno
2. **Juros PJ** — sistema usa 15.23% a.a. sem carência; planilha usa 10.5% a.a. com carência 6m + 18 parcelas
3. **Prazo Lançamento** — sistema: 4 meses, planilha: 6 meses
4. **Área Comum** — sistema: valor fixo total; planilha: valor × unidades

### FALTAM no sistema (21 itens):
- Despesas Comerciais detalhadas (12 sub-itens: Stand, Comissões House/Imob, Bônus, Ajuda de custo...)
- Incorporação detalhada (RI 30%, Entrega 15%, Projetos 55%)
- Assistência Técnica por anos (50/20/10/10/10%)
- Financiamento PJ completo (carência + amortização)
- TIR e Exposição dupla (operacional/financeira)
- Pay-back, DRE Contábil (POC), VSO, e mais...

---