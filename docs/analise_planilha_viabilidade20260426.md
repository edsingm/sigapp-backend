# Análise Comparativa: Planilha de Viabilidade × Sistema LRG

**Data**: 2026-04-26  
**Planilha**: `Viabilidade LRG - V.01.2026 - Modelo - cópia.xlsx`  
**Sistema**: `ViabilidadeUnificadoService` + `CurvaService`

---

## 1. Estrutura da Planilha (10 abas)

| Aba | Função | Dimensão |
|-----|--------|----------|
| **Lista de Desenv.** | Roadmap de features pendentes e concluídas | 25 linhas |
| **Premissas** | Todos os parâmetros de entrada por tipologia (2 Dorm, 3 Dorm, Lotes) | 295 linhas |
| **Dados Operacionais** | Resumo anual de unidades, VGV e estoque | 22 linhas × 159 colunas |
| **Terreno** | Modelagem específica do terreno e permuta | 207 linhas × 14 colunas |
| **Tab_Mestre** | Motor de cálculo mês a mês | 212 linhas × 341 colunas |
| **Aux_Parcelas** | Cálculo detalhado de parcelamento por tipologia | 1877 linhas × 208 colunas |
| **Aux_Obras** | Múltiplas curvas S de obra (12 a 21 meses) | 48 linhas × 39 colunas |
| **DRE** | Demonstração de resultado consolidada | 103 linhas × 36 colunas |
| **Fluxo e DRE** | Fluxo de caixa + DRE anualizado | 99 linhas × 162 colunas |
| **Resumo** | Indicadores principais (TIR, Payback, Margens) | 63 linhas × 29 colunas |

---

## 2. Como a Tab_Mestre Funciona

A **Tab_Mestre** é uma linha do tempo mês a mês com **341 colunas** que cobrem todos os aspectos do cálculo de viabilidade.

### 2.1 Fases do Projeto (coluna C12, "EVENTOS")

```
Mês -18 a  0 → INCORP      (incorporação/pré-lançamento)
Mês   0 a  6 → LANÇTO      (lançamento)
Mês   6 a 42 → OBRA         (período de obras - 36 meses)
Mês  42 a 102 → PÓS OBRA    (pós-obra - 60 meses)
Mês 102 a 144 → encerrado
```

### 2.2 Blocos de Cálculo

| Bloco | Colunas | Descrição |
|-------|---------|-----------|
| **VENDAS 2 Dorm** | 34-52 | % vendas, unidades, VGV (Venda, s/Permuta, s/Lote Terrenista) |
| **VENDAS 3 Dorm** | 54-72 | Idem para 3 Dormitórios |
| **VENDAS Lotes** | 74-92 | Idem para Lotes |
| **TOTAL VENDAS** | 94-112 | Consolidação de vendas |
| **DESLIGAMENTOS** | 114-119 | Cancelamentos com lógica de demanda mínima |
| **+ REC. PRÓPRIO** | 121-134 | Sinal, parcelas obra, pós-chave + juros + correção monetária + carteira acumulada |
| **+ TERRENO** | 136-146 | Terreno a receber via CEF com validação de demanda mínima |
| **+ MEDIÇÃO OBRA** | 148-155 | Curva S financeira de obra × obra vendida = medição a receber da CEF |
| **+ ENTRADAS** | 157 | Total de entradas do mês |
| **- DEDUÇÕES** | 159-163 | RET (2D+3D), Lotes (Lucro Presumido), ISS, Outras Deduções |
| **- TERRENO** | 165-169 | Permuta financeira, física, comissão terreno |
| **- INCORPORAÇÃO** | 171-177 | RI, Entrega, Projetos até/após lançamento |
| **- OBRA** | 179-187 | Curva parcial + % execução no lançamento = desembolso total |
| **- M.O. ADM** | 189 | Mão de obra administrativa mensal |
| **- SEGUROS** | 191-192 | Seguros durante a obra |
| **- ASSIST. TÉCNICA** | 194-200 | 5 anos pós-obra com distribuição anual |
| **- DESP. COMERCIAIS** | 202-214 | Stand de vendas, comissão, ajuda de custo, bônus |
| **- MARKETING** | 216-220 | Lançamento + unidade vendida |
| **- ITBI/IPTU + REGISTRO** | 222-223 | ITBI + IPTU (só 2D e 3D) e Registro |
| **- TX CAIXA** | 225-230 | Medição mensal, contratação, contratos, produtos CEF |
| **- OUTRAS FINANC** | 232-233 | Outras despesas financeiras |
| **SALDO OPERACIONAL** | 235-239 | Entradas - Saídas mês, acumulado, payback operacional |
| **+ PJ CAIXA** | 241-256 | Antecipação, carência, amortização, juros, saldo final |
| **+/- APORTE** | 258-266 | Aporte na SPE, devolução |
| **- DIST. LUCROS** | 268-275 | Caixa mínimo de 1 mês, distribuição de lucros |
| **POC** | 277-312 | Custos incorridos, % apropriação, receita POC |
| **DRE** | 314-340 | ROB, Deduções, ROL, CSP, Lucro Bruto, EBITDA, EBIT, Lucro Líquido |

---

## 3. Comparação Detalhada: Planilha × Sistema

### 3.1 O que está alinhado ✅

| Item | Planilha | Sistema | Arquivo |
|------|----------|---------|---------|
| Estrutura DRE | ROB→Deduções→ROL→Custos→Lucro Bruto→Despesas→EBITDA→Financeiro→EBIT→IRPJ→LL | `calcularDre()` | ViabilidadeUnificadoService |
| Múltiplas tipologias | 3 fixas (2D, 3D, Lotes) | Dinâmico (N produtos) | `processarProdutos()` |
| Permuta física | Unidades doadas ao terrenista | `permutas` por produto | TerrenoProduto |
| VGV com 3 níveis | Venda / s/ Permuta / s/ Lote Terrenista | `vgv`, `vgvSemUnidPermutas`, `vgvSemValorTerrenista` | `processarProdutos()` |
| Curva de vendas | % mês a mês por tipologia | `curva_vendas` no produto (array JSON) | Produto |
| Juros pós-chave | 1% a.m. | `JUROS_POS_CHAVE_MENSAL = 0.01` | ViabilidadeUnificadoService |
| Correção obra | 5% a.a. | `TAXA_CORRECAO_OBRA_ANUAL = 0.05` | ViabilidadeUnificadoService |
| Correção pós-chave | 4.5% a.a. | `TAXA_CORRECAO_POS_ANUAL = 0.045` | ViabilidadeUnificadoService |
| Sinal / Obra / Pós | % do valor da unidade | `financeiro.sinal`, `.parcela_obra`, `.parcela_posChave` | Produto |
| Comissão house/imobs | % distintos por canal | `comissao_house`, `porcentagem_comissao_house/imobs` | Produto |
| Marketing | % VGV no lançamento + por unidade | `marketing_lancamento`, `marketing_antes_lancamento` | Produto |
| PJ Caixa | Antecipação + carência + amortização | `PerfilFinanciamento::CEF` | ViabilidadeUnificadoService |
| Assistência técnica | 5 anos pós-obra | `assist_tecnica1` a `5` no produto | Produto |
| Stand de vendas | Construção + gastos mensais | `porcentagem_ConstrucaoStand`, `gastos_mensaisStand` | Produto |
| Balões anuais | Parcelas intermediárias | `baloes_anuais` (array JSON) | Produto |
| Balão na entrega | Saldo restante ou % fixo | `balao_entrega_modo` (`saldo_restante` / percentual) | Produto |
| POC contábil | Receita apropriada × custo incorrido | `calcularDreContabilPoc()` | ViabilidadeUnificadoService |
| TIR / Payback | Indicadores financeiros | `calcularTir()`, `calcularIndicadoresFinanceiros()` | ViabilidadeUnificadoService |
| Exposição máxima | Pior saldo acumulado no período | `exposicao_maxima_operacional` | ViabilidadeUnificadoService |
| VSO | Velocity de vendas | `calcularIndicadoresVso()` | ViabilidadeUnificadoService |
| Curva S de obra | Curva padrão por prazo | `CurvaService.getPercentualCustoObra()` | CurvaService |

---

### 3.2 O que está diferente ⚠️

#### 🔴 1. Curva S de Obra — diferença mais crítica

**Planilha**: Curvas S detalhadas para **12, 13, 14, 15, 16, 17, 18, 19, 20, 21 meses** (Aux_Obras), mais **36 meses** com valores específicos.

**Sistema**: Apenas 18, 20, 24, 30, 36 meses.

**Exemplo — Curva de 18 meses**:

| Mês | Planilha | Sistema |
|-----|----------|---------|
| 1 | 1.0% | 1.5% |
| 2 | 3.0% | 2.0% |
| 3 | 6.5% | 3.0% |
| 4 | 9.0% | 4.5% |
| 5 | 11.0% | 5.5% |
| 6 | 13.0% | 6.5% |
| 7 | 14.0% | 7.5% |
| 8 | 14.0% | 8.5% |
| 9 | 10.0% | 9.0% |
| 10 | 9.0% | 9.0% |
| 11 | 6.5% | 8.5% |
| 12 | 3.0% | 7.5% |

> A planilha tem uma Curva S Financeira separada da física (coluna C151). O sistema usa a mesma curva para ambos.

---

#### 🔴 2. Juros e Correção sobre Carteira de Recebíveis

**Planilha**: Mantém um saldo de "Carteira" que acumula mês a mês. Sobre ele incidem:
- Juros mensais (1% a.m. pós-chave)
- Correção monetária (INCC 5% a.a. obra, IPCA 4.5% a.a. pós)
- Ambos entram como **receita no fluxo de caixa mensal** (colunas C125-128 e C129-132)

**Sistema**: A lógica de juros e correção parece ser calculada apenas na DRE consolidada, não no fluxo mensal. O `preCalcularRecebiveisProprio()` calcula sinal e parcelas, mas não mantém uma "carteira acumulada" que gera receita financeira recorrente.

> **Impacto**: A TIR e o fluxo de caixa podem estar subestimados.

---

#### 🟡 3. % de Obra Executado no Lançamento

**Planilha**: Divide o desembolso de obra em duas partes:
- **% Execução Lançamento** (1% do custo total) — gasto durante o período de lançamento
- **% Execução período de obras** (99%) — distribuído pela Curva S durante a obra

**Sistema**: Usa a Curva S diretamente para todo o desembolso, sem separar um percentual para o período de lançamento.

> **Impacto**: Moderado. Desloca ~1% do custo de obra para 6 meses antes.

---

#### 🟡 4. Medição CEF como Entrada de Caixa Real

**Planilha**: A Medição de Obra CEF (colunas C148-155) é calculada como:
```
Medição a Receber = % Vendas × Medição Total × Curva Obra Financeira Acumulada
```
E entra como **entrada de caixa real** no fluxo.

**Sistema**: A lógica CEF está em `inicializarCachesCef()` — conceitualmente alinhada, mas a fórmula exata precisa ser validada contra a planilha.

---

#### 🟡 5. Bônus Comercial Detalhado

**Planilha**: 5 componentes de bônus:
- Gerente (0.3% VGV)
- Gerente Regional (0.12% VGV)
- Crédito (0.05% VGV)
- Gestor Comercial (0.05% VGV)
- Equipe Comercial (valor fixo, calculado por diferença)

**Sistema**: Os parâmetros existem no `montarParametros()` mas nem todos são efetivamente utilizados no `calcularDespesasComerciaisMensais()`.

---

#### 🟢 6. Desligamentos (Cancelamentos)

**Planilha**: Lógica refinada com 3 estados:
- Vendas acumuladas > Demanda Mínima CEF → há desligamento
- Vendas acumuladas < Demanda Mínima → sem desligamento
- Vendas acumuladas = Demanda Mínima → sem desligamento

**Sistema**: Parâmetros `inadimplencia` (10%), `atrasoMeses` (2), `taxaPerda` (2%) existem na configuração, mas a lógica de desligamento pode ser mais simples que a planilha.

---

#### 🟢 7. ITBI/IPTU por Tipologia

**Planilha**: Lotes **não pagam** ITBI/IPTU (Premissas: 0 para Lotes).

**Sistema**: `custoItbiIptu` é um percentual **global** da viabilidade, aplicado sobre o VGV total — pode superestimar o custo para cenários com lotes.

---

#### 🟢 8. Distribuição de Lucros

**Planilha**: DL só ocorre quando 100% da obra está finalizada. Mantém caixa mínimo de 1 mês antes de distribuir.

**Sistema**: Tem `distribuicaoLucrosPercentualObra` no `montarParametros()`. A lógica parece similar, mas precisa validação.

---

#### 🟢 9. Prazo de Pós-Obra

**Planilha**: Pós-obra de **60 meses** (padrão do modelo).

**Sistema**: `mesesPosObra` vem da configuração `config('viabilidade.prazos')`. Se diferente de 60, os fluxos de assistência técnica, juros pós-chave e recebíveis pós-obra divergem.

---

#### 🟢 10. Curvas S Financeiras × Físicas

**Planilha**: Mantém duas curvas de obra distintas:
- **Curva de Obra Financeira** (C151, Tab_Mestre) — usada para medição CEF
- **Curva de Obra Física** (Aux_Obras) — usada para desembolso

**Sistema**: Usa a mesma Curva S para ambos os propósitos (medição e desembolso).

---

### 3.3 O que a planilha faz e o sistema não faz

| Funcionalidade | Onde na planilha | Prioridade |
|----------------|------------------|------------|
| **Carteira de recebíveis com rendimento de juros e correção** | Tab_Mestre C121-134 | 🔴 Alta |
| **Curvas S por prazo curto (12-21 meses)** | Aux_Obras | 🟡 Média |
| **% de obra executado no lançamento (1%)** | Tab_Mestre C183-185 | 🟡 Média |
| **Desligamentos com lógica de demanda mínima CEF** | Tab_Mestre C114-119 | 🟢 Baixa |
| **Bônus comercial por tipo (5 componentes)** | Premissas L218-228 | 🟢 Baixa |
| **Análise de sensibilidade por tipologia** | Várias abas | 🟢 Baixa |
| **Aba "Terreno" dedicada com modelagem complexa** | Aba Terreno | 🟢 Baixa |

---

## 4. Parâmetros-Chave da Planilha (Premissas)

### Dados do Projeto

| Parâmetro | Valor |
|-----------|-------|
| Nome | Area Teste |
| Cidade | Marilia - SP |
| Data Lançamento | 2029-06-01 |
| Prazo Incorporação | 18 meses |
| Prazo Lançamento | 6 meses |
| Prazo Obra | 36 meses |
| Prazo Pós-Obra | 60 meses |

### Produtos

| | 2 Dorm | 3 Dorm | Lotes | Total |
|---|--------|--------|-------|-------|
| Metragem (m²) | 47.2 | 61.33 | - | - |
| Unidades | 1000 | 100 | 200 | 1300 |
| Ticket Médio | R$220.000 | R$250.000 | R$120.000 | R$206.923 |
| VGV Total | R$220M | R$25M | R$24M | R$269M |
| Permuta Física (unid) | 80 | 10 | 0 | 90 |
| VGV LRG (s/ Permuta) | R$202.4M | R$22.5M | R$24M | R$248.9M |

### Financeiro

| Parâmetro | 2 Dorm | 3 Dorm | Lotes |
|-----------|--------|--------|-------|
| Sinal | 2% | 2% | 10% |
| Parcelas Obra | 9% | 9% | 10% |
| Parcelas Pós-Chave | 9% | 9% | 80% |
| Qtde Parcelas Pós | 36 | 36 | 80 |
| Financiamento CEF | 80% | 80% | 0% |
| Juros Sinal (a.m.) | 0% | - | - |
| Juros Obra (a.m.) | 0% | - | - |
| Juros Pós-Chave (a.m.) | 1% | - | - |
| Correção Sinal (a.a.) | 0% | - | - |
| Correção Obra (a.a.) | 5% (INCC) | - | - |
| Correção Pós (a.a.) | 4.5% (IPCA) | - | - |

### Custos e Despesas (% VGV salvo indicação)

| Item | % |
|------|----|
| Tributos (RET/LP) | 4% (2D/3D), 6.73% (Lotes) |
| ISS | 0% |
| Outras Deduções | 0.5% |
| Compra Terreno | 8% VGV |
| Incorporação | 1% VGV |
| Seguros | 0.5% VGV |
| Assistência Técnica | 1% VGV (5 anos) |
| Despesas Comerciais | 5% VGV |
| Marketing | 1% VGV |
| ITBI + IPTU | 0.8% VGV |
| Registro | R$2.500/unidade |

### PJ Caixa

| Parâmetro | Valor |
|-----------|-------|
| Antecipação PJ | 10% custo obra |
| Taxa Juros a.a. | 10.5% |
| Carência pós-obra | 6 meses |
| Parcelas amortização | 18 |

### Resultado (Planilha)

| Indicador | Valor |
|-----------|-------|
| Lucro Líquido | R$57.056.248 |
| Margem Líquida | 23.25% (s/ VGV) / 24.08% (s/ ROL) |
| Exposição Máxima | -R$7.227.961 |
| Payback Operacional | 22.3 meses |
| TIR a.a. | 4.21% |

---

## 5. Recomendações Prioritárias

### 🔴 Alta Prioridade

1. **Alinhar Curva S de obra**: Atualizar `CurvaService::$curvasObra` com os valores exatos da planilha (Aux_Obras) para todos os prazos (12-21, 24, 30, 36 meses). Separar curva financeira da física.

2. **Implementar carteira de recebíveis com rendimento**: Adicionar no `ViabilidadeFluxoContext` um campo `carteiraAcumulada` e calcular juros + correção mensais que entram como receita no fluxo de caixa.

3. **Adicionar % de obra no lançamento**: Separar 1% do custo de obra para o período de lançamento (`porcentagem_ConstrucaoStand` expandido ou novo campo).

### 🟡 Média Prioridade

4. **Validar fórmula de Medição CEF** contra a planilha (colunas C148-155).
5. **Completar lógica de bônus comercial** com os 5 componentes da planilha.
6. **Separar ITBI/IPTU por produto** (lotes não pagam).

### 🟢 Baixa Prioridade

7. **Implementar desligamentos com lógica de demanda mínima CEF**.
8. **Distribuição de Lucros**: Validar lógica de caixa mínimo e % obra finalizada.
9. **Análise de sensibilidade por tipologia** (dashboard).
10. **Modelagem dedicada de terreno/permuta** (aba Terreno da planilha).

---

## 6. Conclusão

O sistema de viabilidade cobre aproximadamente **85%** da funcionalidade da planilha modelo, com uma arquitetura superior (Service → Repository → Model, sem limites de tipologias, extensível). As 3 diferenças mais impactantes são:

1. **Curva S**: valores diferentes para os mesmos prazos (mudança de dados, não de código)
2. **Juros/Correção na carteira**: funcionalidade ausente no fluxo de caixa (mudança de lógica)
3. **% Obra no lançamento**: 1% do custo precisa ser alocado no período de lançamento (mudança de lógica)

Nenhuma dessas diferenças é estrutural — todas podem ser resolvidas com ajustes pontuais no `CurvaService` e `ViabilidadeUnificadoService`.
