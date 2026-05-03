# Documentação Técnica — Motor de Cálculo de Viabilidade

**Arquivo:** `app/Services/Tenant/Viabilidade/v1/ViabilidadeUnificadoService.php`  
**Última revisão:** Abril 2026

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

O `ViabilidadeUnificadoService` é o motor de cálculo financeiro do módulo de viabilidade. Dado um terreno e seus produtos imobiliários, ele produz:

- **Fluxo de caixa mensal**: receitas, despesas e saldo acumulado mês a mês, desde a incorporação até o pós-obra
- **DRE consolidada**: resultado acumulado do projeto nos formatos gerencial e contábil (POC)
- **Indicadores financeiros**: TIR operacional, TIR sem CEF, exposição máxima, payback, VPL e VSO por janela

A arquitetura segue o padrão **Controller → Service → Repository**. Este service é um service puro: não acessa o banco diretamente (usa `Terreno::findOrFail` apenas para carregar dados) e todo o estado mutável do cálculo é isolado em `ViabilidadeFluxoContext`, tornando as chamadas consecutivas na mesma instância completamente independentes.

---

## 2. Arquivos Envolvidos

| Arquivo | Responsabilidade |
|---------|-----------------|
| `ViabilidadeUnificadoService.php` | Motor principal de cálculo |
| `ViabilidadeFluxoContext.php` | Estado mutável isolado por chamada |
| `CurvaService.php` | Utilitários de curva (validação, extração, interpolação) |
| `ImpostosService.php` | PIS/COFINS, ISS, IRPJ, CSLL e juros PJ |
| `config/viabilidade.php` | Defaults de todos os parâmetros |
| `app/Models/Tenant/Viabilidade.php` | Overrides de parâmetros por projeto |
| `app/Models/Tenant/Terreno.php` + `TerrenoProduto.php` | Unidades, preços, tipologias |

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
| **Obra** | Construção. Repasses do CEF via medição | `mesesObra` (18, 24, 36, 48 ou 60) |
| **Entrega** | Mês de entrega das chaves. Registro, contratos | 1 mês |
| **Pós-Obra** | Parcelas pós-chave, assistência técnica | `mesesPosObra` (padrão: 60 meses) |

As datas são calculadas em `calcularPeriodos()` a partir de `dataInicio` (fixada como `now() + 2 anos`):

```
inicioIncorporacao = dataLancamento - mesesIncorporacao
dataLancamento     = dataInicio
fimLancamento      = dataLancamento + mesesLancamento - 1
inicioObra         = fimLancamento + 1 mês
fimObra            = inicioObra + mesesObra - 1
dataEntrega        = fimObra + 1 mês
inicioPos          = dataEntrega + 1 mês
fimPos             = inicioPos + mesesPosObra - 1
```

---

## 4. Pipeline: `gerarFluxoMensal()`

```
gerarFluxoMensal(terrenoId, viabilidadeRef?, customProdutos?)
│
├── 1. montarParametros()         — carrega defaults + overrides da Viabilidade
├── 2. processarProdutos()        — agrega unidades, VGV, custos de todos os produtos
├── 3. calcularPeriodos()         — define datas de cada fase
│
├── 4. ViabilidadeFluxoContext    — cria contexto de estado zerado
├── 5. preCalcularRecursosProprios() — pré-distribui sinal/obra/pós-chave por mês
├── 6. inicializarCachesCef()     — pré-distribui vendas CEF e valorMedicaoTotal
│
├── 7. Loop mês a mês (inicioIncorporacao → fimPos)
│     ├── calcularReceitas()
│     └── calcularDespesas()
│
├── 8. calcularIndicadoresFinanceiros()  — TIR, payback, VPL, fluxo financeiro
├── 9. calcularIndicadoresVso()          — VSO acumulado e por janelas
└── 10. calcularDre()                    — DRE consolidada + DRE contábil POC
```

**Retorno de `gerarFluxoMensal()`:**

```
{
  terreno, vgv, totalUnidades, unidadesPermuta, areaConstruida, custoTotal,
  produtos,
  dre_itens,               // DRE gerencial consolidada
  dre_contabil_poc,        // DRE contábil pelo POC
  dre_contabil_poc_mensal, // mesma DRE por mês
  dre_contabil_poc_mensal_blocos,
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

Calculadas em `calcularReceitas()`. Três fontes distintas:

### 5.1 Recursos Próprios

Pré-calculados em `preCalcularRecursosProprios()` antes do loop. Para cada produto e cada mês de venda (coorte) da curva de vendas, distribui os recebimentos em três sub-fluxos:

#### Sinal
- Se a venda ocorre **dentro do lançamento** (mês de venda `s ≤ mesesLancamento`): o valor do sinal é fracionado em parcelas iguais pelos meses restantes do lançamento
- Se a venda ocorre **após o lançamento**: o sinal é recebido integralmente no mês da venda

```
numParcelas = mesesLancamento - s + 1
parcelaSinal = (preco × %sinal) / numParcelas
```

#### Parcelas de Obra
- Distribuídas pelos meses da obra, a partir do mês em que a unidade foi vendida
- Cada parcela recebe **correção monetária composta** por `r_obra` pelo tempo decorrido desde a venda:

```
r_obra = (1 + 0.05)^(1/12) - 1   ← 5% a.a. convertido para mensal
parcelaAjustada = parcelaNominal × (1 + r_obra)^mesesDecorridos
```

#### Pós-Chave
- Calculado de forma **agregada** (não por coorte): `qtdParcelasPos` parcelas após a entrega
- Cada parcela = amortização + juros sobre saldo devedor + correção:

```
amortizacao = valorPosTotal / qtdParcelasPos
jurosMes    = saldoDevedor × 0.01        ← 1% a.m.
correcaoMes = saldoDevedor × r_pos       ← (1 + 0.045)^(1/12) - 1
pagamentoMes = amortizacao + jurosMes + correcaoMes
```

### 5.2 Recurso Terrenos (CEF)

Recebido **durante a obra** com a seguinte lógica de defasagem:

- **Mês 1 de obra**: nada (aguarda acumulação)
- **Mês 2 de obra**: libera o acumulado das vendas dos meses 1 a 4 do lançamento
- **Mês 3+**: libera o valor do mês de venda correspondente (defasagem de 2 meses):

```
mesObraNumero = 3 → mesVenda = 5
mesObraNumero = 4 → mesVenda = 6
...
```

O valor de cada unidade vendida é: `avaliacaoCef × preco` (onde `avaliacaoCef` é um percentual ou valor absoluto configurado por produto).

### 5.3 Medição de Obra (CEF)

Financiamento recebido **durante a obra** através de medições:

```
1. valorMedicaoTotal = max(0, VGV × 80% - totalRecursoTerrenos)

2. (a cada mês de obra)
   curvaObraAcumulada += getPercentualCustoObra(mesesObra, mesObraAtual) / 100
   medicaoTeoricaAcumulada = valorMedicaoTotal × curvaObraAcumulada

3. percVendasAcumulado = vendasAcumuladas / totalUnidades
   medicaoVendidaAcumulada = medicaoTeoricaAcumulada × percVendasAcumulado

4. valorReceberMes = max(0, medicaoVendidaAcumulada - medicaoObraAcumulada)
   medicaoObraAcumulada += valorReceberMes
```

O passo 3 garante que a construtora só recebe financiamento sobre as unidades efetivamente vendidas.

---

## 6. Despesas Mensais

Calculadas em `calcularDespesas()`. Cinco blocos:

### 6.1 Custos Diretos (`calcularCustosDiretos`)

Calculados por fase:

| Item | Fórmula | Fase |
|------|---------|------|
| Incorporação Até Lançamento | `(VGV × %incorp × %ateLanc) / mesesIncorporacao` | Incorporação |
| Incorporação Pós Lançamento | `(VGV × %incorp × (1-%ateLanc)) / (mesesLanc + mesesObra)` | Lançamento + Obra |
| Obra | `custoObraTotal × percentualCurvaS[mês]` | Obra |
| Canteiro | `canteiroMensal` (fixo) | Obra |
| Área Comum | `(custoAreaComum × unidades) / mesesObra` | Obra |
| M.O. Administrativa | `moAdministrativa` (fixo) | Obra |
| Seguros | `(VGV × %seguros) / (mesesLanc + mesesObra)` | Lançamento + Obra |
| Assistência Técnica | `baseAssistencia × %AT × curvaAnual[ano] / 12` | Pós-Obra |
| Medição/Contratação | `unidadesConstrutora × custoMedicaoContratacao` | 1º mês de Obra |
| Contratos CEF | `unidadesConstrutora × custoContratosCef` | 1º mês de Obra |

`custoObraTotal = custoObraHabitacao + custoInfraestrutura + custoNaoIncidente`

### 6.2 Tributos

Delegados ao `ImpostosService.calcularTributosPorProduto()`:
- PIS/COFINS calculados sobre a receita do mês por produto
- ISS sobre a receita do mês por produto
- Juros e correção monetária deduzidos da base tributável

### 6.3 Custos Operacionais

Dois sub-blocos:

**Despesas Comerciais Mensais** (`calcularDespesasComerciaisMensais`):

| Item | Fórmula |
|------|---------|
| Stand de Vendas | `standVendas / mesesLancamento` (durante Lançamento) |
| Mobiliário | `mobiliaDecoracao / mesesAntesLancamento` (antes do Lançamento) |
| Gastos Stand | `VGVsemTerrenista × %gastosMensaisStand` (Lançamento + Obra) |
| Comissão de Venda | `unidVendidasMes × ticketMedio × taxaComissaoMedia × %pagtoVenda` |
| Comissão Desligamento | Parcelada sobre vendas anteriores (ver abaixo) |
| Bônus CCA | `bonusCca × unidVendidasMes` |
| Bônus Gerente | `comissaoBaseMes × %bonusGerente` |
| Bônus Gerente Regional | `comissaoBaseMes × %bonusGerenteRegional` |
| Bônus Crédito | `comissaoBaseMes × %bonusCredito` |
| Bônus Gestor Comercial | `comissaoBaseMes × %bonusGestorComercial` |
| Ajuda de Custo Gerente | fixo mensal (durante Lançamento + Obra) |
| Ajuda de Custo Gerente Regional | fixo mensal (durante Lançamento + Obra) |
| Reembolso Logística | fixo mensal (durante Lançamento + Obra) |

**Comissão de Desligamento** (`calcularComissaoDesligamentoMensal`):  
Para cada mês de venda anterior, distribui o valor de desligamento em `parcelamentoComissaoMeses` parcelas:

```
taxaComissaoMedia = %vendasHouse × taxaHouse + (1 - %vendasHouse) × taxaImob
desligamentoMesVenda = unidVendidas × ticketMedio × taxaComissaoMedia × %desligamento
parcelaMes = desligamentoMesVenda / parcelamentoComissaoMeses
```

**Marketing Mensal** (`calcularMarketingMensal`):
- Porção de lançamento: `base × %marketingLancamento / mesesLancamento` (durante Lançamento)
- Porção variável: `base × (1 - %marketingLancamento) × unidVendidasMes / unidadesConstrutora`

### 6.4 Custos Financeiros

```
financeiros = receita['total'] × (%produtosCef + %outrasDespFinanceiras)
```

Nota: esta linha captura custos como tarifas bancárias mensais proporcionais à receita.

### 6.5 Custo Terreno (proporcional)

O custo total do terreno (compra + permutas + parceria) é rateado mensalmente proporcional à receita do mês:

```
totalCustoTerreno = permutas × preco + compraTerreno
custoParceria     = parceriaVgv × VGVcomCorrecao
custoTerrenoMes   = (totalCustoTerreno + custoParceria) × receitaMes / VGVcomCorrecao
```

---

## 7. DRE Consolidada

Calculada em `calcularDre()`, que delega para `calcularCustosDiretosDre()` e `calcularDespesasOperacionaisDre()`.

### Estrutura de resultado

```
Receita Total de Vendas     = VGVsemTerrenista
+ Juros e Correções         = VGVsemTerrenista × variavelCorrecao
= Receita Bruta

- PIS/COFINS/Outros
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

- Despesas Comerciais       (stand, mobília, comissões, bônus, ajudas)
- Marketing
- ITBI/IPTU
- Registro
- Taxa de Medição/Contratação
- Contratos CEF
- Produtos CEF
= EBITDA

- Outras Despesas Financeiras (%outrasDespFinanceiras × receita)
- Despesas Onerosas Bancos   (juros PJ)
= EBIT

- IRPJ/CSLL
= Lucro Líquido
```

### Juros PJ (`calcularJurosPJ`)

Financia um percentual do custo de obra com capital de terceiros (Pessoa Jurídica):

```
valorAntecipado = custoTotalObra × %antecipacaoPj + custoTerreno × %antecipacaoPj
jurosTotais     = SAC com taxa composta por mesesObra meses, após carência
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
| `roi_percentual` | `lucroLiquido / custosDiretosTotal × 100` |
| `payback_operacional_meses` | Mês em que o saldo operacional acumulado ≥ 0 |
| `payback_financeiro_meses` | Mês em que o saldo financeiro acumulado ≥ 0 |
| `vpl_operacional` | VPL do fluxo operacional à taxa de exposição aplicada |
| `vpl_financeiro` | VPL do fluxo financeiro à taxa de exposição aplicada |
| `vso_*` | Velocidade de Vendas em períodos (3, 6, 12 meses) |

**Fluxo Financeiro** (`calcularIndicadoresFinanceiros`):  
Ajusta o fluxo operacional com:
- Aportes adicionais mensais durante a obra
- Devolução de aportes durante o pós-obra
- Entrada de antecipação PJ no início da obra
- Pagamento mensal de amortização + juros PJ após carência

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

---

## 10. Parâmetros de Entrada

Montados em `montarParametros()` a partir de `config/viabilidade.php` (defaults) e sobrepostos pelos campos do model `Viabilidade`.

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
| `custoMedicaoContratacao` | `medicao_contratacao` | R$2.000 |
| `custoContratosCef` | `contratos_cef` | R$300 |

### CEF e Financeiro

| Parâmetro | Campo no Model | Padrão |
|-----------|---------------|--------|
| `percentualProdutosCef` | `produtos_cef` | 0.5% |
| `percentualOutrasDespesasFinanceiras` | `outras_despesas_financeiras` | 0.3% |
| `taxaJurosPj` | `taxa_juros_pj` | — |
| `percentualAntecipacaoPj` | `percentual_antecipacao_pj` | — |
| `carenciaPjMeses` | `carencia_pj_meses` | — |
| `amortizacaoPjParcelas` | `amortizacao_pj_parcelas` | — |

### Prazos

| Parâmetro | Origem | Padrão |
|-----------|--------|--------|
| `mesesObra` | `viabilidade.prazo_obra` | 36 |
| `mesesIncorporacao` | `config.prazos.meses_incorporacao` | — |
| `mesesLancamento` | `config.prazos.meses_lancamento` | — |
| `mesesEntrega` | `config.prazos.meses_entrega` | 1 |
| `mesesPosObra` | `config.prazos.meses_pos_obra` | — |
| `variavelCorrecao` | `config.prazos.variavel_correcao` | — |

### Outros

| Parâmetro | Campo no Model | Descrição |
|-----------|---------------|-----------|
| `compraTerreno` | `compra_terreno` | Valor de compra do terreno (R$) |
| `aporteAdicionalMensal` | `aporte_adicional_mensal` | Aporte financeiro mensal durante a obra |
| `devolucaoAportePercentual` | `devolucao_aporte_percentual` | % do aporte devolvido no pós-obra |
| `distribuicaoLucrosPercentualObra` | `distribuicao_lucros_percentual_obra` | — |
| `taxaExposicaoAplicada` | `taxa_exposicao_aplicada` | Taxa de custo do capital (para VPL e exposição financeira) |

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

As curvas de venda vêm da tabela `produtos` do tenant, enquanto a curva de obra é centralizada no `CurvaService`:

| Fonte | Tipo | Descrição |
|-------|------|-----------|
| `produtos.curva_vendas` | JSON array | Percentuais de vendas mês a mês (soma ~100%). Tamanho do array = duração da comercialização |
| `CurvaService.getPercentualCustoObra()` | Método | Curva S de obra — avanço físico mês a mês baseado no prazo total da obra (18, 20, 24, 30, 36 meses) |

A `curva_vendas` é obrigatória por produto. A curva de obra não está mais na tabela `produtos` — o `ViabilidadeUnificadoService` obtém a curva S diretamente do `CurvaService` com base no `prazo_obra` informado na viabilidade.

### O CurvaService (utilitários)

O `CurvaService` centraliza as curvas de desembolso de obra (Curva S) e funções utilitárias para manipular curvas de venda:

| Método | Descrição |
|--------|-----------|
| `getPercentualCustoObra(int $mesesTotal, int $mesAtual): float` | Retorna o % do custo de obra para um mês específico usando Curva S padrão |
| `getCurvaObraParaPrazo(int $meses): array` | Retorna a Curva S completa para um prazo de obra |
| `extrairCurva(array\|string\|null $valor): array` | Extrai curva de um JSON ou array do produto, filtra negativos |
| `normalizarCurva(array $curva): array` | Ajusta curva para soma = 100% |
| `ajustarCurva(array $curva, int $meses): array` | Corta ou preenche com zeros para atingir exatamente `$meses` elementos |
| `interpolarCurva(array $curva, int $meses): array` | Redimensiona curva via interpolação linear e normaliza |
| `validarCurvasObrigatorias(array $produtos): array` | Valida se todos os produtos têm `curva_vendas` |

### Agregação de Curva de Obra

A curva de obra é calculada via `CurvaService.agregarCurvaObra(mesesObra)` — sem dependência de `curva_obra` por produto:

1. O `CurvaService` seleciona a Curva S padrão correspondente ao prazo de obra (18, 20, 24, 30 ou 36 meses)
2. Para prazos intermediários, utiliza interpolação linear entre as curvas vizinhas
3. A curva resultante é normalizada para soma = 100%

Essa curva é usada em:
- **Custos Diretos**: distribuir o custo total de obra mês a mês (`calcularCustosDiretos`)
- **Medição de Obra CEF**: calcular o percentual físico acumulado (`calcularMedicaoObra`)
