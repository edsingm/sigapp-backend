# Documentação Técnica — Motor de Cálculo de Viabilidade

**Arquivo:** `app/Services/Tenant/Viabilidade/v1/ViabilidadeUnificadoService.php`  
**Última revisão:** Maio 2026

---

## Sumário

1. [Visão Geral](#1-visão-geral)
2. [Arquivos Envolvidos](#2-arquivos-envolvidos)
3. [Fases do Projeto](#3-fases-do-projeto)
4. [Pipeline: `gerarFluxoMensal()`](#4-pipeline-gerarfluxomensal)
5. [Receitas Mensais](#5-receitas-mensais)
6. [Despesas Mensais](#6-despesas-mensais)
7. [DRE Consolidada](#7-dre-consolidada)
8. [Indicadores Financeiros](#8-indicadores-financeiros)
9. [Estado de Cálculo — `ViabilidadeFluxoContext`](#9-estado-de-cálculo--viabilidadefluxocontext)
10. [Parâmetros de Entrada](#10-parâmetros-de-entrada)
11. [Constantes Internas](#11-constantes-internas)
12. [Curvas S (CurvaService)](#12-curvas-s-curvaservice)

---

## 1. Visão Geral

O `ViabilidadeUnificadoService` é a porta de entrada pública do motor de cálculo financeiro do módulo de viabilidade. Dado um terreno e seus produtos imobiliários, ele produz:

- **Fluxo de caixa mensal**: receitas, despesas e saldo acumulado mês a mês, desde a incorporação até o pós-obra
- **DRE consolidada**: resultado acumulado do projeto nos formatos gerencial e contábil (POC)
- **Indicadores financeiros**: TIR operacional, TIR sem CEF, TIR financeira, exposição máxima, payback e VSO por janela

A arquitetura segue o padrão **Controller → Service → Repository**, mas o motor foi refatorado em calculadoras especializadas. Hoje o `ViabilidadeUnificadoService` atua como orquestrador: ele carrega `Terreno` e `Viabilidade`, resolve premissas ativas via `PremissasViabilidadeService`, delega o cálculo para classes da pasta `Calculos/`, salva `premissas_snapshot` ao final e mantém o estado mutável do fluxo isolado em `ViabilidadeFluxoContext`.

---

## 2. Arquivos Envolvidos

| Arquivo | Responsabilidade |
|---------|-----------------|
| `ViabilidadeUnificadoService.php` | Orquestra o cálculo, busca dados e salva snapshot |
| `Calculos/FluxoMensalCalculator.php` | Pipeline principal do fluxo mensal |
| `Calculos/ProdutosProcessor.php` | Consolida produtos, VGV, custos e parâmetros derivados |
| `Calculos/ReceitasCalculator.php` | Regras de recebimento por perfil (`cef` ou `proprio`) |
| `Calculos/DespesasCalculator.php` | Custos diretos, deduções, operacionais e terreno |
| `Calculos/DreCalculator.php` | DRE gerencial consolidada |
| `Calculos/IndicadoresCalculator.php` | TIR, payback, exposição e VSO |
| `Calculos/PocCalculator.php` | DRE caixa, DRE contábil POC e reconciliação |
| `ViabilidadeFluxoContext.php` | Estado mutável isolado por chamada |
| `CurvaService.php` | Curvas de venda, curva S de obra e curva financeira de medição |
| `ImpostosService.php` | Impostos consolidados da DRE e juros PJ |
| `PremissasViabilidadeService.php` | Resolve premissas ativas diretamente do banco |
| `app/Models/Tenant/PremissasViabilidade.php` | Premissas default por perfil de financiamento |
| `app/Models/Tenant/Viabilidade.php` | Overrides por estudo/projeto |
| `app/Models/Tenant/Terreno.php` + `TerrenoProduto.php` | Unidades, preços, permutas e tipologias |

---

## 3. Fases do Projeto

O cálculo percorre as seguintes fases em ordem cronológica, produzindo um mês de dados para cada fase:

```
Incorporação → Lançamento → Obra → Entrega → Pós-Obra
```

| Fase | Descrição | Duração típica |
|------|-----------|----------------|
| **Incorporação** | Período anterior ao lançamento. Custos de aprovação e RI | `mesesIncorporacao` (padrão: 6 meses) |
| **Lançamento** | Abertura de vendas. Estande, mobília, comissões iniciais | `mesesLancamento` (padrão: 6 meses) |
| **Obra** | Construção. Repasses do CEF via medição | `mesesObra` (configurável; curvas-base em 18, 20, 24, 30 e 36 meses) |
| **Entrega** | Mês de entrega das chaves. Registro, contratos | 1 mês |
| **Pós-Obra** | Parcelas pós-chave, assistência técnica | `mesesPosObra` (padrão: 60 meses) |

As datas são calculadas em `calcularPeriodos()` a partir de `dataInicio`. Em runtime, esse valor vem de `viabilidades.data_lancamento`; se não existir, usa o fallback de `PremissasViabilidadeService` (`now() + 2 anos`):

```
inicioIncorporacao = dataLancamento - mesesIncorporacao
dataLancamento     = dataInicio
fimLancamento      = dataLancamento + mesesLancamento - 1
inicioObra         = fimLancamento + 1 mês
fimObra            = inicioObra + mesesObra - 1
dataEntrega        = fimObra + 1 mês
inicioPos          = dataEntrega
fimPos             = inicioPos + mesesPosObra - 1
```

---

## 4. Pipeline: `gerarFluxoMensal()`

```
gerarFluxoMensal(terrenoId, viabilidadeRef?, customProdutos?)
│
├── 1. buscarTerreno()            — carrega terreno + produtos + campos financeiros
├── 2. buscarViabilidade()        — resolve a viabilidade de referência
├── 3. montarParametros()         — premissas ativas do banco + overrides da Viabilidade
├── 4. ProdutosProcessor.processar() — agrega unidades, VGV, custos e tributos por produto
├── 5. ProdutosProcessor.mesclarParametros() — aplica defaults derivados do mix de produtos
├── 6. validarCurvasObrigatorias() — exige `curva_vendas` em todos os produtos
├── 7. calcularPeriodos()         — define datas de cada fase
│
├── 8. ViabilidadeFluxoContext    — cria contexto de estado zerado
├── 9. preCalcularRecebiveis()    — escolhe lógica `cef` ou `proprio`
├── 10. aplicarInadimplencia()    — apenas no perfil próprio
├── 11. inicializarCachesCef()    — vendas por mês, demanda mínima e valor de medição
│
├── 12. Loop mês a mês (inicioIncorporacao → fimPos)
│     ├── calcularReceitas()
│     └── calcularDespesas()
│
├── 13. calcularIndicadoresFinanceiros() — TIR, payback, exposição e fluxo financeiro
├── 14. calcularIndicadoresVso()         — VSO acumulado e por janelas
├── 15. calcularDre()                    — DRE consolidada
├── 16. calcularDreCaixa()/POC()         — visões caixa, POC e ponte de reconciliação
└── 17. salvarSnapshot()                 — persiste premissas e indicadores usados
```

**Retorno de `gerarFluxoMensal()`:**

```
{
  terreno, vgv, totalUnidades, unidadesPermuta, areaConstruida, custoTotal,
  produtos,
  dre_itens,               // DRE gerencial consolidada
  dre_caixa,               // visão caixa consolidada
  dre_contabil_poc,        // DRE contábil pelo POC
  dre_contabil_poc_mensal, // mesma DRE por mês
  dre_contabil_poc_mensal_blocos,
  ponte_reconciliacao,     // reconciliação caixa x DRE x POC
  indicadores,             // todos os indicadores financeiros e de VSO
  dados_produtos,
  fluxo_mensal,            // array[mes] com receita, despesas, saldo_acumulado, unidades_vendidas
  fluxo_mensal_financeiro, // fluxo ajustado com aportes, devoluções e PJ
  totais,                  // acumulados: receita, custo_direto, impostos, etc.
  parametros_utilizados
}
```

---

## 5. Receitas Mensais

Calculadas em `ReceitasCalculator.calcular()`. A composição mensal depende do `perfil_financiamento`.

### 5.1 Perfil Próprio (`proprio`)

Pré-calculados em `preCalcularRecebiveisProprio()` antes do loop. Para cada produto e cada mês de venda (coorte) da curva de vendas, o motor distribui recebimentos em `sinal`, `parcelas_obra` e `parcelas_pos`.

#### Sinal
- Se a venda ocorre **dentro do lançamento** (`s ≤ mesesLancamento`): o valor do sinal é fracionado até o fim do lançamento
- Se a venda ocorre **após o lançamento**: o sinal é recebido integralmente no mês da venda

```
numParcelas = mesesLancamento - s + 1
parcelaSinal = (preco × %sinal) / numParcelas
```

#### Parcelas de Obra
- Mensalidades lineares até o fim da obra, descontando balões anuais quando existirem
- `baloes_anuais` entram como parcelas específicas no mês configurado
- O saldo restante pode ser lançado integralmente na entrega via `balao_entrega_modo`

#### Pós-Chave
- Após aplicar sinal, mensalidades e balões, o saldo restante pode virar recebimento único na entrega

### 5.2 Perfil CEF (`cef`)

Pré-calculado em `preCalcularRecebiveisCef()`. A lógica atual difere do fluxo próprio:

- **Sinal**: entra integralmente no mês da venda
- **Parcelas de obra**: são distribuídas linearmente do mês da venda até o fim do prazo total de comercialização + obra
- **Correção de obra**: não corrige cada parcela individualmente; o motor lança `correcao_obra` mensal sobre o saldo remanescente de obra vendido
- **Pós-chave**: usa amortização + juros + correção sobre saldo devedor
- **Inadimplência**: não é aplicada no perfil CEF

### 5.3 Recurso Terrenos (CEF)

O recebimento do terreno não segue mais uma defasagem fixa "mês 2/mês 3+". A regra atual é:

- só libera após a **demanda mínima CEF** ser atingida (`demanda_minCef`)
- para cada venda, o pagamento ocorre em `max(data_da_venda, mes_demanda_atingida) + defasagem_pgtoTerreno`
- `avaliacao_lotesCef` pode ser percentual do preço (`0 < valor <= 1`) ou valor absoluto por unidade

### 5.4 Medição de Obra (CEF)

Financiamento recebido por medição, limitado ao avanço físico e às unidades efetivamente vendidas:

```
1. valorMedicaoTotal = max(0, financiamentoCEF - totalRecursoTerrenos)

2. (a cada mês elegível)
   curvaObraAcumulada += curvaFinanceiraMedicao[mes] / 100
   medicaoTeoricaAcumulada = valorMedicaoTotal × curvaObraAcumulada

3. percVendasAcumulado = vendasAcumuladas / totalUnidades
   medicaoVendidaAcumulada = medicaoTeoricaAcumulada × percVendasAcumulado

4. valorReceberMes = max(0, medicaoVendidaAcumulada - medicaoObraAcumulada)
   medicaoObraAcumulada += valorReceberMes
```

Observações importantes:

- o financiamento CEF parte de `80%` do VGV financiável
- a curva financeira de medição pode se estender por até **5 meses após o fim da obra**
- o retorno de receitas passou a usar estrutura aninhada em `snake_case`, por exemplo `detalhes.recursos_proprios.total_recursos_proprios`

---

## 6. Despesas Mensais

Calculadas em `calcularDespesas()`. Cinco blocos:

### 6.1 Custos Diretos (`calcularCustosDiretos`)

Calculados por fase:

| Item | Fórmula | Fase |
|------|---------|------|
| Incorporação Até Lançamento | rateio do bloco de incorporação até o lançamento | Incorporação + Lançamento |
| Incorporação Pós Lançamento | rateio do saldo de incorporação | Lançamento + Obra |
| Incorporação RI | valor único no último mês antes do lançamento | fim da Incorporação |
| Incorporação Entrega | valor único no mês de entrega | Entrega |
| Obra (Lançamento) | `custoObraTotal × obraAteLancamento / mesesLancamento` | Lançamento |
| Obra | `custoObraTotal × percentualCurvaS[mês]` | Obra |
| Canteiro | `canteiroMensal` (fixo) | Obra |
| Área Comum | `(custoAreaComum × unidades) / mesesObra` | Obra |
| M.O. Administrativa | `moAdministrativa` (fixo) | Obra |
| Seguros | total por tipologia rateado por `mesesObra` | Lançamento + Obra |
| Assistência Técnica | `baseAssistencia × %AT × curvaAnual[ano] / 12` | Entrega + Pós-Obra |

`custoObraTotal = custoObraHabitacao + custoInfraestrutura + custoNaoIncidente`

### 6.2 Deduções e Impostos Mensais

No fluxo mensal, as deduções são calculadas em `DespesasCalculator.calcularDeducoesMensais()`:
- `RET/LP imóveis` e `RET/LP lotes` são separados pela tipologia do produto
- `ISS` e `outras deduções` são rateados proporcionalmente ao VGV por produto
- `outras deduções` usam base sem juros/correção (`receita - juros_correcao`)
- o `ImpostosService` fica concentrado na visão consolidada da DRE e nos juros PJ

### 6.3 Custos Operacionais

Dois sub-blocos:

**Despesas Comerciais Mensais** (`calcularDespesasComerciaisMensais`):

| Item | Fórmula |
|------|---------|
| Stand de Vendas | `standVendas / mesesLancamento` durante a janela de construção do stand |
| Gastos Stand | `VGVsemTerrenista × %gastosMensaisStand` (Lançamento + Obra) |
| Comissão de Venda | `unidVendidasMes × ticketMedio × taxaComissaoMedia × %pagtoVenda` |
| Comissão Desligamento | acumulada até a demanda mínima CEF e liberada no mês em que ela é atingida |
| Bônus CCA | `bonusCca × unidVendidasMes` |
| Ajuda de Custo Gerente | fixo mensal (durante Lançamento + Obra) |
| Ajuda de Custo Gerente Regional | fixo mensal (durante Lançamento + Obra) |
| Reembolso Logística | fixo mensal (durante Lançamento + Obra) |
| Bônus Equipe Comercial | pagamento único quando o estoque comercializável zera |

**Comissão de Desligamento** (`calcularComissaoDesligamentoMensal`):  
O comportamento atual não parcela por `parcelamentoComissaoMeses`. O valor do mês é acumulado até a demanda mínima CEF ser atingida; no mês do atingimento, o acumulado é liberado de uma vez e, dali em diante, passa a ser mensal:

```
taxaComissaoMedia = %vendasHouse × taxaHouse + (1 - %vendasHouse) × taxaImob
desligamentoMesVenda = unidVendidas × ticketMedio × taxaComissaoMedia × %desligamento
```

**Marketing Mensal** (`calcularMarketingMensal`):
- Porção de lançamento: `base × %marketingLancamento / mesesLancamento` (durante Lançamento)
- Porção variável: `base × (1 - %marketingLancamento) × unidVendidasMes / unidadesConstrutora`

### 6.4 Custos Financeiros

```
financeiros = outrasDespesasFinanceirasMensal
```

Observações:

- `outrasDespesasFinanceirasTotal` é rateado igualmente apenas entre os meses que efetivamente têm receita
- custos de `produtos_caixa`, `contratos_caixa`, `medicao_mensal` e `contratacao` ficam no bloco operacional `taxa_caixa`, não neste bloco

### 6.5 Terreno e Pagamentos ao Terrenista

O fluxo mensal separa duas lógicas:

- `calcularCustoTerreno()` rateia apenas `compraTerreno` proporcionalmente à receita do mês
- `calcularPagamentoTerreno()` trata parceria, compra parcelada na obra, permuta física e comissão do corretor do terreno

```
custoTerrenoMes = compraTerreno × receitaMes / receitaTotalProjeto
parceriaMes     = receitaMes × parceriaVgv
compraMensal    = compraTerreno / mesesObra          // apenas na obra
permutaFisica   = custoPermutaFisicaTotal × curvaMes
comissaoTerreno = totalTerrenista × %comissao / parcelamento
```

---

## 7. DRE Consolidada

Calculada em `DreCalculator.calcular()`, que recalcula a visão consolidada por produto/tipologia. Ela não é uma simples soma literal dos blocos do fluxo mensal.

### Estrutura de resultado

```
Receita Total de Vendas     = VGV sem valor terrenista
+ Juros e Correções         = correcaoSobreVgv acumulada no fluxo
= Receita Bruta

- PIS/COFINS
- ISS
- Outras Deduções
= Receita Líquida (ROL)

- Custo Terreno             (compra + permutas física/financeira + infra proprietário)
- Comissão                  (%comissao × |custoTerreno|)
- Incorporação              (%incorporacao × VGV)
- Infra Casas               (custoM2 × área × unidadesConstrutora)
- Infra Lotes               (custoInfra × unidades + custoNaoIncidente)
- Área Comum                (custoAreaComum × totalUnidades)
- Contrapartidas            (%contrapartidas × VGV)
- Canteiro Total            (canteiroMensal × mesesObra)
- M.O. Administrativa       (moAdministrativa × mesesObra)
- Seguros
- Assistência Técnica       (%AT × base × curvaAnual)
= Lucro Bruto

- Despesas Comerciais       (%despesas_comerciais sobre VGV sem permuta)
- Marketing
- ITBI/IPTU
- Registro
- Taxa de Medição/Contratação
- Contratos Caixa
- Produtos Caixa
= EBITDA

- Outras Despesas Financeiras (valor total configurado)
- Despesas Onerosas Bancos   (juros PJ)
= EBIT

- IRPJ/CSLL
= Lucro Líquido
```

### Juros PJ (`calcularJurosPJ`)

Financia um percentual do custo de obra com capital de terceiros (Pessoa Jurídica):

```
valorAntecipado = custoTotalObra × %antecipacaoPj
jurosTotais     = formula planilha com taxa mensal equivalente, meses de obra, carência e amortização
```

---

## 8. Indicadores Financeiros

| Indicador | Descrição |
|-----------|-----------|
| `tir_operacional` | TIR sobre o fluxo de lucro mensal (com receita CEF) |
| `tir_sem_cef` | TIR considerando apenas Recursos Próprios como receita |
| `exposicao_maxima_operacional` | Menor saldo acumulado do fluxo (pior momento de caixa) |
| `margem_liquida` | `lucroTotal / receitaTotal` |
| `margem_liquida_percentual` | `lucroLiquido / receitaTotalVendas × 100` |
| `margem_liquida_sobre_rol` | `lucroLiquido / receitaLiquida × 100` |
| `margem_bruta_percentual` | `lucroBruto / receitaLiquida × 100` |
| `margem_ebitda_percentual` | `EBITDA / receitaLiquida × 100` |
| `margem_ebit_percentual` | `EBIT / receitaLiquida × 100` |
| `tir_financeira` | TIR do fluxo financeiro ajustado com aporte, devolução e PJ |
| `roi_percentual` | `lucroLiquido / custosDiretosTotal × 100` |
| `payback_operacional_meses` | Mês em que o saldo operacional acumulado ≥ 0 |
| `payback_financeiro_meses` | Mês em que o saldo financeiro acumulado ≥ 0 |
| `exposicao_maxima_financeira` | Menor saldo acumulado do fluxo financeiro |
| `exposicao_aplicada_total` | Custo financeiro da exposição negativa até a entrega |
| `vso_total_percentual` | VSO acumulado do projeto |
| `vso_medio_mensal_percentual` | VSO médio considerando meses com venda |
| `vso_mensal_maximo_percentual` | Pico mensal de VSO |
| `vso_*` | Velocidade de Vendas em períodos (3, 6, 12 meses) |

**Fluxo Financeiro** (`calcularIndicadoresFinanceiros`):  
Ajusta o fluxo operacional com:
- Aportes adicionais mensais durante a obra
- Devolução de aportes durante o pós-obra
- Entrada de antecipação PJ no início da obra
- Pagamento mensal de amortização + juros PJ após carência
- Aplicação de custo de exposição quando o saldo financeiro fica negativo até a entrega

---

## 9. Estado de Cálculo — `ViabilidadeFluxoContext`

Todas as variáveis acumuladas entre meses vivem no contexto, não no service. Isso garante que chamadas consecutivas a `gerarFluxoMensal()` nunca acumulem dados entre si.

| Propriedade | Tipo | Descrição |
|-------------|------|-----------|
| `recursosProprios` | `array<string, array<string, float>>` | Cache `[mes][sinal|parcelas_obra|parcelas_pos|juros|correcao]` |
| `vendasPorMes` | `array<string, float>` | Unidades vendidas por mês `[Y-m => float]` |
| `vendasAcumuladas` | `float` | Acumulador de unidades vendidas até o mês atual |
| `valorMedicaoTotal` | `float` | `VGV × 80% - totalRecursoTerrenos` |
| `medicaoObraAcumulada` | `float` | Total já recebido via medição |
| `curvaObraAcumulada` | `float` | % acumulado da curva S de obra (0..1) |
| `mesObraAtual` | `int` | Último mês de obra processado (evita duplicar acúmulo) |
| `demandaMinima` | `float` | Demanda mínima CEF somada de todos os produtos |
| `demandaAtingida` | `bool` | Se a demanda mínima CEF foi atingida |
| `mesDemandaAtingida` | `?string` | Mês (Y-m) em que a demanda foi atingida |
| `txContratacaoPaga` | `bool` | Garante cobrança única da taxa de contratação |
| `bonusEquipeComercialPago` | `bool` | Garante pagamento único do bônus comercial |
| `comissaoDesligamentoAcumulada` | `float` | Acúmulo até atingir demanda mínima CEF |
| `contratosCefAcumulados` | `float` | Acúmulo de contratos CEF antes da liberação |
| `produtosCefAcumulados` | `float` | Acúmulo de produtos CEF antes da liberação |
| `parceriaVgvTotal` | `float` | Total projetado de parceria sobre entradas |
| `parcelasAtrasadas` | `array<string, float>` | Recuperação parcial de inadimplência no perfil próprio |

---

## 10. Parâmetros de Entrada

Montados em `montarParametros()` a partir de `PremissasViabilidadeService` (defaults ativos no banco) e sobrepostos pelos campos do model `Viabilidade`.

Importante: `config/viabilidade.php` não é consultado em runtime; ele serve apenas como apoio de seed/bootstrap inicial.

### Impostos

| Parâmetro | Campo no Model | Padrão |
|-----------|---------------|--------|
| `percentualPisCofins` | `pis_cofins` | 3.65% |
| `percentualIss` | `iss` | 0.0% |
| `percentualOutrosImpostos` | `outros_impostos` | 0.0% |

### Custos de Obra

| Parâmetro | Campo no Model | Padrão |
|-----------|---------------|--------|
| `percentualIncorporacao` | `incorporacao` | 1.0% |
| `incorporacaoRi` | `incorporacao_ri` | — |
| `incorporacaoEntrega` | `incorporacao_entrega` | — |
| `incorporacaoAteLancamento` | `incorporacao_ate_lancamento` | — |
| `infraNaoIncidente` | `infra_nao_incidente` | 1.5% |
| `custoAreaComum` | `area_comum` | R$0 |
| `percentualContrapartidas` | `contrapartidas` | 1.0% |
| `canteiroMensal` | `canteiro_mensal` | R$0 |
| `moAdministrativa` | `mo_administrativa` | R$0 |
| `percentualSeguros` | `seguros` | 0.5% |
| `percentualAssistenciaTecnica` | `assistencia_tecnica` | 1.0% |
| `assistenciaTecnicaCurva` | `assistencia_tecnica_curva` | [50,20,10,10,10] |

### Despesas Comerciais

| Parâmetro | Campo no Model | Padrão |
|-----------|---------------|--------|
| `percentualComissao` | `comissao` | 0.0% |
| `comissaoHousePercentual` | `comissao_house_percentual` | — |
| `comissaoImobiliariasPercentual` | `comissao_imobiliarias_percentual` | — |
| `percentualVendasHouse` | `percentual_vendas_house` | — |
| `pagamentoComissaoVenda` | `pagamento_comissao_venda` | — |
| `pagamentoComissaoDesligamento` | `pagamento_comissao_desligamento` | — |
| `parcelamentoComissaoMeses` | `parcelamento_comissao_meses` | 1 |
| `standVendas` | `stand_vendas` | R$0 |
| `mobiliaDecoracao` | `mobilia_decoracao` | R$0 |
| `gastosMensaisStand` | `gastos_mensais_stand` | — |
| `bonusCca` | `bonus_cca` | R$0 |
| `bonusGerente` | `bonus_gerente` | 0.0% |
| `bonusGerenteRegional` | `bonus_gerente_regional` | 0.0% |
| `bonusCredito` | `bonus_credito` | 0.0% |
| `bonusGestorComercial` | `bonus_gestor_comercial` | 0.0% |
| `ajudaCustoGerente` | `ajuda_custo_gerente` | R$0 |
| `ajudaCustoGerenteRegional` | `ajuda_custo_gerente_regional` | R$0 |
| `reembolsoLogistica` | `reembolso_logistica` | R$0 |
| `percentualMarketing` | `marketing` | 1.0% |
| `marketingLancamento` | `marketing_lancamento` | — |
| `marketingInicioAntesLancamento` | `marketing_inicio_antes_lancamento` | — |
| `parceriaVgv` | `parceria_vgv` | 0.0% |

### Custos Fixos por Unidade

| Parâmetro | Campo no Model | Padrão |
|-----------|---------------|--------|
| `custoItbiIptu` | `itbi_iptu` | 1.1% |
| `custoRegistro` | `registro` | R$2.500 |
| `custoContratacaoCef` | `custo_contratacao_cef` ou `medicao_contratacao` | R$2.000 |
| `custoMedicaoCef` | `custo_medicao_cef` | R$0 |
| `custoContratosCef` | `contratos_cef` | R$300 |

### CEF e Financeiro

| Parâmetro | Campo no Model | Padrão |
|-----------|---------------|--------|
| `percentualProdutosCef` | `produtos_cef` | 0.5% |
| `outrasDespesasFinanceirasTotal` | `outras_despesas_financeiras` | 0.3% |
| `taxaJurosPj` | `taxa_juros_pj` | — |
| `percentualAntecipacaoPj` | `percentual_antecipacao_pj` | — |
| `carenciaPjMeses` | `carencia_pj_meses` | — |
| `amortizacaoPjParcelas` | `amortizacao_pj_parcelas` | — |

### Prazos

| Parâmetro | Origem | Padrão |
|-----------|--------|--------|
| `mesesObra` | `viabilidade.prazo_obra` | 36 |
| `mesesIncorporacao` | `premissas_viabilidade.meses_incorporacao` | — |
| `mesesLancamento` | `premissas_viabilidade.meses_lancamento` | — |
| `mesesEntrega` | `premissas_viabilidade.meses_entrega` | 1 |
| `mesesPosObra` | `premissas_viabilidade.meses_pos_obra` | — |
| `dataLancamento` | `viabilidade.data_lancamento` ou fallback das premissas | `now() + 2 anos` |

### Outros

| Parâmetro | Campo no Model | Descrição |
|-----------|---------------|-----------|
| `compraTerreno` | `compra_terreno` | Valor de compra do terreno (R$) |
| `aporteAdicionalMensal` | `aporte_adicional_mensal` | Aporte financeiro mensal durante a obra |
| `devolucaoAportePercentual` | `devolucao_aporte_percentual` | % do aporte devolvido no pós-obra |
| `distribuicaoLucrosPercentualObra` | `distribuicao_lucros_percentual_obra` | — |
| `taxaExposicaoAplicada` | `taxa_exposicao_aplicada` | Taxa de custo do capital aplicada sobre exposição financeira negativa |

---

## 11. Constantes Internas

| Constante | Valor | Uso |
|-----------|-------|-----|
| `TAXA_CORRECAO_OBRA_ANUAL` | 5.0% a.a. | Correção das parcelas de obra |
| `TAXA_CORRECAO_POS_ANUAL` | 4.5% a.a. | Correção das parcelas pós-chave |
| `JUROS_POS_CHAVE_MENSAL` | 1.0% a.m. | Juros sobre saldo devedor pós-chave |
| `PRAZO_POS_CHAVE_PADRAO` | 36 parcelas | Fallback quando o produto não define `qtde_parcelas_posChave` |
| `PERCENTUAL_FINANCIAMENTO_CEF` | 80% do VGV | Base do cálculo de `valorMedicaoTotal` |

---

## 12. Curvas S (CurvaService)

As curvas de venda vêm da tabela `produtos` do tenant, enquanto a curva de obra e a curva financeira de medição são centralizadas no `CurvaService`:

| Fonte | Tipo | Descrição |
|-------|------|-----------|
| `produtos.curva_vendas` | JSON array | Percentuais de vendas mês a mês (soma ~100%). Tamanho do array = duração da comercialização |
| `CurvaService.getPercentualCustoObra()` | Método | Curva S de obra baseada no prazo mais próximo disponível |
| `CurvaService.getCurvaFinanceiraMedicaoParaPrazo()` | Método | Curva financeira da medição CEF, com retenção final e liberações pós-obra |

A `curva_vendas` é obrigatória por produto. A curva de obra não está mais na tabela `produtos` — o `ViabilidadeUnificadoService` obtém a curva S diretamente do `CurvaService` com base no `prazo_obra` informado na viabilidade.

### O CurvaService (utilitários)

O `CurvaService` centraliza as curvas de desembolso de obra (Curva S) e funções utilitárias para manipular curvas de venda:

| Método | Descrição |
|--------|-----------|
| `getPercentualCustoObra(int $mesesTotal, int $mesAtual): float` | Retorna o % do custo de obra para um mês específico usando Curva S padrão |
| `getCurvaObraParaPrazo(int $meses): array` | Retorna a Curva S normalizada para o prazo mais próximo |
| `getCurvaObraBaseParaPrazo(int $meses): array` | Retorna a curva base/interpolada sem perder a escala original |
| `getCurvaFinanceiraMedicaoParaPrazo(int $meses, float $obraAteLancamento): array` | Gera a curva financeira de medição com retenção de saldo final |
| `extrairCurva(array\|string\|null $valor): array` | Extrai curva de um JSON ou array do produto, filtra negativos |
| `normalizarCurva(array $curva): array` | Ajusta curva para soma = 100% |
| `ajustarCurva(array $curva, int $meses): array` | Corta ou preenche com zeros para atingir exatamente `$meses` elementos |
| `interpolarCurva(array $curva, int $meses): array` | Redimensiona curva via interpolação linear e normaliza |
| `validarCurvasObrigatorias(array $produtos): array` | Valida se todos os produtos têm `curva_vendas` |

### Agregação de Curva de Obra

A curva de obra é obtida por `getCurvaObraParaPrazo()` e a curva financeira de medição por `getCurvaFinanceiraMedicaoParaPrazo()`:

1. O `CurvaService` seleciona a Curva S padrão correspondente ao prazo mais próximo disponível (18, 20, 24, 30 ou 36 meses)
2. Para algumas visões, a curva pode ser interpolada e depois reescalada
3. A curva resultante é normalizada para soma = 100%

Essa curva é usada em:
- **Custos Diretos**: distribuir o custo total de obra mês a mês (`calcularCustosDiretos`)
- **Medição de Obra CEF**: calcular o percentual financeiro acumulado e as liberações pós-obra (`calcularMedicaoObra`)
