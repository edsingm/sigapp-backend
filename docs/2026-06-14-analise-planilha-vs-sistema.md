# Análise Profunda — Planilha "Viabilidade LRG V.02.2026" vs. Sistema Atual

**Data:** 2026-06-14
**Planilha analisada:** `Viabilidade LRG - V.02.2026 - Modelo Rev.01.xlsx` (12 abas, 5,1 MB)
**Sistema:** `app/Services/Tenant/Viabilidade/v1/` (motor `ViabilidadeUnificadoService` + 7 Calculators)
**Método:** extração de fórmulas (não valores) de todas as 12 abas via openpyxl e comparação fórmula-a-fórmula com os Calculators do backend, incluindo os ~70 parâmetros default (`PremissasViabilidade` migration) contra os valores-padrão da planilha (`DRE`/`Premissas`).

**Conclusão de uma frase:** o sistema é uma reimplementação fiel e bem estruturada do motor da planilha — cobre vendas, parcelas, obra, terreno, DRE por POC, fluxo de caixa (operacional/financeiro/acionista) e indicadores, com o split tributário (PIS/COFINS 52% / IRPJ/CSLL 48%) e a estrutura da DRE conferidos linha a linha. Restam **discrepâncias numéricas concretas** (curvas de obra divergentes, inadimplência/atraso só no sistema, regra dos 5% finais ausente, método de TIR diferente, cronograma de compra do terreno) e **lacunas de dados de referência** (Custos Fernando e limites MCMV) que precisam ser fechadas antes de tratar o sistema como espelho da planilha.

---

## 1. Anatomia da planilha (12 abas)

| Aba | Estado | Papel | Tamanho |
|---|---|---|---|
| `Lista de Desenv.` | oculta | Backlog do autor da planilha (status OK/pendente) | 25 linhas |
| `TABELA MUNICÍPIOS_v.01jan2026` | visível | Base de 5.572 municípios + limite Caixa por município | 5.574 × 26 |
| `Custos Fernando` | visível | **Tabela de custos regionais** (infra/lote, hab R$/m², M.O. Adm, canteiro por faixa) | 13 × 11 |
| `DRE` | visível | **Entrada de premissas do projeto + DRE consolidada** (por tipologia: 2 Dorm./3 Dorm./Lotes) | 103 × 36 |
| `Resumo` | visível | KPIs: exposição, payback, TIR (XIRR), DL, antecipação PJ | 63 × 29 |
| `Fluxo e DRE` | visível | DRE e Fluxo de Caixa **anualizados/consolidados** | 99 × 162 |
| `Dados Operacionais` | oculta | VSO, ticket médio por tipologia (em construção na própria planilha) | 22 × 159 |
| `Terreno` | visível | Cronograma mensal de pagamento do terreno | 207 × 20 |
| `Premissas` | visível | **Coração do modelo**: todos os parâmetros de cálculo derivados da DRE | 295 × 8 |
| `Tab_Mestre` | visível | **Motor mensal**: 341 colunas, 1 linha por mês (~205 meses) | 212 × 341 |
| `Aux_Parcelas` | visível | Detalhamento parcela-a-parcela das vendas (juros/correção) | 1.877 × 208 |
| `Aux_Obras` | visível | **Curvas-S de desembolso de obra** por prazo (12 a 43 meses) | 48 × 39 |

### Fluxo de dados da planilha

```
DRE (inputs do projeto: unidades, ticket, m², custos, %)
  → Premissas (deriva ~270 parâmetros: $ sinal, parcelas, curvas, comissões, PJ…)
    → Aux_Parcelas (explode cada venda em parcelas c/ juros + correção INCC/IPCA)
    → Aux_Obras (curva-S financeira e física por prazo de obra)
      → Tab_Mestre (consolida mês a mês: vendas → entradas → saídas → saldo
                    → PJ Caixa → aporte → distribuição de lucro → POC → DRE mensal)
        → Fluxo e DRE / Resumo (anualização, KPIs, TIR, payback, exposição)
```

---

## 2. O motor mensal da planilha (Tab_Mestre) — blocos

A `Tab_Mestre` é a referência canônica. Seus 22 blocos, na ordem do fluxo:

1. **Timeline** (cols B,I–Q): eixo de meses, eventos INCORP/LANÇTO/OBRA/ENTREGA/PÓS-OBRA.
2. **Vendas por tipologia** (AH–CN): % curva (VLOOKUP em `Premissas!B77:E114`), unidades, VGV, permuta física, estoque — para 2 Dorm., 3 Dorm., Lotes.
3. **Total Vendas** (CP–DH).
4. **Desligamentos** (DJ–DO): gatilho de demanda mínima Caixa (30%).
5. **+ Rec. Próprio** (DQ–ED): sinal + parcelas obra + pós-chave, com **juros e correção** vindos do `Aux_Parcelas` (SUMIFS).
6. **+ Terreno** (EF–EP): avaliação por lote Caixa, liberada após demanda mínima, com defasagem.
7. **+ Medição Obra** (ER–EY): curva financeira (`Aux_Obras` col 37) × % vendido.
8. **+ Entradas** (FA).
9. **− Deduções** (FC–FG): RET/PIS/COFINS, ISS, outras.
10. **− Terreno** (FI–FM): permuta financeira (proporcional às entradas), permuta física, comissão terreno.
11. **− Incorporação** (FO–FU): RI (mês −1), entrega, parcelas até/após lançamento.
12. **− Obra** (FW–GE): curva-S parcial (`Aux_Obras` col 31) + 1% no lançamento.
13. **− M.O. Adm** (GG): mensal durante obra.
14. **− Seguro** (GI–GJ): do lançamento ao fim da obra.
15. **− Assistência Técnica** (GL–GR): pós-obra, distribuída em 5 anos.
16. **− Despesas Comerciais** (GT–HF): stand, comissão (venda/desligamento), ajuda de custo, Bônus CCA, bônus equipe comercial, demais.
17. **− Marketing** (HH–HL): lançamento + por unidade vendida.
18. **− ITBI + Registro** (HN–HO).
19. **− Tx Caixa** (HQ–HV): medição mensal, contratação, contratos, produtos.
20. **− Outras Desp. Financeiras** (HX–HY).
21. **= Saldo Operacional** (IA–IE) → **PJ Caixa** (IG–IV: antecipação, carência, amortização, juros) → **Aporte** (IX–JF) → **Distribuição de Lucro** (JH–JO).
22. **POC** (JQ–KZ) e **DRE mensal** (LB–MC): receita apropriada por % de custo incorrido, deduções/custo/pós-obra POC, e a DRE contábil construída sobre o POC.

---

## 3. Mapeamento bloco-a-bloco: planilha → sistema

| Bloco Tab_Mestre | Implementação no sistema | Fidelidade |
|---|---|---|
| Timeline / eventos | `FluxoMensalCalculator::calcularPeriodos/identificarPeriodo` | ✅ Fiel |
| Vendas + curva (VLOOKUP Premissas) | `ProdutosProcessor` + `CurvaService::extrairCurva/normalizarCurva` | ✅ Fiel |
| Desligamentos / demanda mínima | `ReceitasCalculator` (`demandaAtingida`, `mesDemandaAtingida`) | ✅ Fiel |
| Rec. Próprio (sinal/obra/pós) + juros + correção | `FluxoMensalCalculator::preCalcularRecebiveisCef/Proprio` + `ReceitasCalculator` | ✅ Fiel (juros pós-chave incl.) |
| Terreno a receber (Caixa) | `ReceitasCalculator::calcularRecursoTerrenos` | ✅ Fiel |
| Medição de obra × % vendido | `ReceitasCalculator::calcularMedicaoObra` | ⚠️ Fiel, mas ver §4.2 (5% finais) |
| Deduções (RET/ISS/outras) | `DespesasCalculator::calcularDeducoesMensais` | ⚠️ Fiel; base levemente diferente em projeto misto (sistema rateia por VGV e exclui lotes de ISS/outras; planilha aplica sobre `FA` direto) |
| Terreno (permuta fin./física/comissão) | `DespesasCalculator::calcularPagamentoTerreno` + `calcularComissaoCorretorTerreno` | ✅ Fiel |
| Incorporação (RI/entrega/parcelas) | `DespesasCalculator::calcularCustosDiretos` | ✅ Fiel |
| Obra (curva-S + lançamento) | `DespesasCalculator::agregarCurvaObra` + `CurvaService` | ❌ Curvas divergem — ver §4.1 |
| M.O. Adm / Canteiro | `DespesasCalculator::calcularCustosOperacionais` | ✅ Fiel |
| Seguro | `DespesasCalculator::calcularSegurosMensal` | ✅ Fiel |
| Assistência Técnica (5 anos) | `DespesasCalculator::calcularAssistenciaTecnicaMensal` | ✅ Fiel |
| Despesas Comerciais (stand/comissão/bônus) | `DespesasCalculator::calcularDespesasComerciaisMensais` + `calcularBonusEquipeComercialResidual` | ✅ Fiel |
| Marketing | `DespesasCalculator::calcularMarketingMensal` | ✅ Fiel |
| ITBI/Registro/Tx Caixa | `DespesasCalculator` (blocos dedicados) | ✅ Fiel |
| Saldo Operacional | `FluxoMensalCalculator::calcular` | ✅ Fiel |
| PJ Caixa (antecipação/carência/amortização/juros) | `IndicadoresCalculator::calcularIndicadoresFinanceiros` | ✅ Fiel |
| Aporte / Devolução | `IndicadoresCalculator` (aporte por obra, devolução por pós-obra) | ⚠️ Ver §4.4 |
| Distribuição de Lucro | params `distribuicaoLucrosPercentualObra` | ✅ Presente |
| POC (receita/custo/dedução apropriados) | `PocCalculator` (`calcularQuadroPocMensal`, `calcularDreContabilPoc`) | ✅ Fiel |
| DRE mensal + reconciliação DRE=FC | `DreCalculator` + `PocCalculator::calcularPonteReconcilicao` | ⚠️ Ver §4.5 |
| Resumo: exposição/payback/TIR | `IndicadoresCalculator` | ⚠️ TIR difere — ver §4.3 |

**Veredito de cobertura:** ~90% dos blocos da planilha estão implementados com fidelidade. O motor do sistema é robusto e bem modularizado.

---

## 4. Discrepâncias concretas (com evidência)

### 4.1 ❌ Curvas de obra divergentes — ALTA severidade

A planilha (`Aux_Obras`) traz curvas-S para **todo prazo de 12 a 43 meses**. O sistema (`CurvaService::$curvasObra`) tem apenas **5 prazos hardcoded** (18/20/24/30/36) com interpolação para os demais. Pior: **os valores não batem**.

Exemplo, prazo de obra = 18 meses (em % por mês):

```
Planilha (Aux_Obras col I): 0,75 1,25 1,5 4,5 6 7 8 9 10 11 10 8 7,5 6 4 2,5 2 1   (Σ=100)
Sistema  (CurvaService 18): 1,5  2,0  3,0 4,5 5,5 6,5 7,5 8,5 9 9 8,5 7,5 6,5 5,5 4,5 4 3,5 2,5 (Σ≈99,5)
```

São curvas diferentes: a planilha desembolsa menos no início e tem pico de 11% no mês 10; o sistema é mais achatado. Isso desloca o desembolso de obra no fluxo de caixa e afeta exposição, payback e necessidade de aporte.
**Ação:** importar a `Aux_Obras` oficial como tabela (todos os prazos 12–43) e substituir os arrays hardcoded.

### 4.2 ⚠️ Regra dos "5% finais" da medição ausente — MÉDIA

A planilha tem coluna dedicada "5% Finais" no `Aux_Obras` e o item pendente (`Lista de Desenv.` r19): *"Fazer cálculo dos 5% finais dividindo, 2% 2 meses após entrega e 3% 6 meses após a entrega"*. No sistema, `ReceitasCalculator::calcularMedicaoObra` apenas estende a medição por 5 meses após o fim da obra (`fimObra + 5`) — **não há o split 2%/3% nos marcos +2 e +6 meses**. `grep` por "5%/2%/3%/finais" no Calculator não retorna nada.
**Ação:** implementar a retenção e liberação dos 5% finais conforme a regra (ou confirmar que a planilha oficial já abandonou isso — o item segue **sem status OK**).

### 4.3 ⚠️ Método de TIR diferente — MÉDIA

- **Planilha** (`Resumo!D61`): `=XIRR(Tab_Mestre!$ID$6:$ID$99, I6:I99)` — XIRR com **datas reais** sobre o **saldo operacional acumulado** (`ID`), limitado às linhas 6:99.
- **Sistema** (`IndicadoresCalculator::calcularTir`): Newton-Raphson sobre o **fluxo líquido mensal** com índice de período inteiro `t`, anualizando por `(1+r)^12−1`, sobre o horizonte completo.

Duas diferenças de input (saldo acumulado vs. fluxo líquido; datas reais vs. meses inteiros; janela 94 meses vs. completa) podem produzir TIRs distintas da planilha.
**Ação:** decidir qual é a referência oficial e alinhar input + janela. Se a planilha é a verdade, replicar XIRR com datas e o vetor `ID`.

### 4.4 ⚠️ Devolução de aporte: percentual hardcoded vs. premissa — BAIXA

Na planilha, `Tab_Mestre!JD20` usa `JC20*0.25` (25%), enquanto `Premissas!C268` define "Parcelas Devolução de Aporte" = **0,2** (20%) — inconsistência **dentro da própria planilha**. O sistema usa o param `devolucaoAportePercentual`. Convém confirmar o valor correto com o autor antes de calibrar.

### 4.5 ⚠️ Reconciliação DRE = Fluxo não testada — MÉDIA

O item nº 1 da `Lista de Desenv.` (status OK) é *"Fazer com que o resultado final de DRE e FC sejam iguais sempre"*. O sistema tem `PocCalculator::calcularPonteReconcilicao`, mas os testes (`ViabilidadeUnificadoServiceTest`) só verificam `lucro_liquido = ebit − irpj_csll` e determinismo — **não há assert de que `Σ Saldo Operacional ≈ Lucro Líquido`**. Sem esse invariante automatizado, regressões passam despercebidas.
**Ação:** adicionar teste de invariante contábil (tolerância < R$ 1).

### 4.6 ❌ Inadimplência / atraso / taxa de perda existem só no sistema — ALTA

A planilha **não modela inadimplência**. O sistema adiciona três premissas e a rotina `FluxoMensalCalculator::aplicarInadimplencia`:

| Premissa (sistema) | Default | Equivalente na planilha |
|---|---|---|
| `inadimplencia` | 0,10 | — (inexistente) |
| `atraso_meses` | 2 | — |
| `taxa_perda` | 0,02 | — |

Com os defaults, o sistema atrasa e perde parte dos recebíveis que a planilha recebe integralmente — **divergência sistemática** no fluxo de caixa, exposição e TIR. Para reproduzir a planilha é preciso **zerar `inadimplencia` e `taxa_perda`** (ou confirmar que essas premissas devem mesmo entrar e a planilha é que está defasada).

### 4.7 ⚠️ Cronograma de pagamento da compra direta do terreno difere — MÉDIA

- **Planilha** (`Terreno!T8/T14/T20/T26 = $F$1/4`): a compra direta do terreno é paga em **4 parcelas semestrais** (a cada 6 meses).
- **Sistema** (`DespesasCalculator::calcularCompraTerrenoMensal`): dilui o valor **mensalmente ao longo de toda a obra** (`compraTerreno / mesesObra`, só no período "Obra").

A permuta física (que segue a curva de obra) e a permuta financeira (rateada pelas entradas) **batem**; a divergência é só na modalidade "compra direta".

### 4.8 ⚠️ Parcelamento da comissão do terreno — BAIXA

Planilha `Premissas!C125 = 18` (comissão do terreno em 18 parcelas mensais, `Tab_Mestre!FL`); o sistema usa `parcelamentoComissaoTerreno` default **1** (parcela única). Impacto baixo porque a comissão de terreno default ≈ 0 (`comissao` ≈ 0), mas diverge quando configurada.

---

## 4-bis. Fórmulas conferidas e CONFIRMADAS fiéis

Para equilíbrio, o que foi verificado fórmula-a-fórmula e **bate** com a planilha:

- **Split tributário** (`ImpostosService::calcularImpostosDre`): PIS 9,25% + COFINS 42,75% = **52%** acima do EBIT; IRPJ 31,5% + CSLL 16,5% = **48%** após o EBIT — idêntico a `Premissas!C72:C75` e ao posicionamento na `DRE` (linhas 53 e 97). Item r13 da Lista ("IRPJ/CSLL após LAIR") está corretamente implementado.
- **Estrutura da DRE** (`DreCalculator`): ROB → Deduções → ROL → CSP → Lucro Bruto → Despesas → EBITDA → Desp. Financeiras → EBIT → IRPJ/CSLL → Lucro Líquido — espelha a aba `DRE`.
- **Permuta financeira do terreno**: rateada pela participação das entradas do mês (`parceriaTotal × entradasMes/VGV`) = `Tab_Mestre!FI` (`DRE!F27 × FA/FA_total`).
- **Permuta física do terreno**: segue a curva de obra = `Tab_Mestre!FJ` (`GD × F118`).
- **Medição de obra** ajustada por % vendido (`medição teórica acum × % vendas`) = `Tab_Mestre!EX/EY`.
- **Juros nas parcelas pós-chave** (`preCalcularRecebiveisCef`) = item r5 da Lista, implementado.

---

## 5. Comparação dos parâmetros default (premissas)

Defaults do sistema (`2026_04_27_195000_create_premissas_viabilidade_table.php`) vs. valores-padrão da planilha (`DRE`/`Premissas`). A maioria bate; as divergências:

| Parâmetro | Sistema (default) | Planilha | Observação |
|---|---|---|---|
| `infra_nao_incidente` | **1,0 %** | **1,5 %** (`DRE!D37`) | ❌ divergente |
| `contrapartidas` | **0 %** | **1,0 %** (`DRE!D68`) | ❌ divergente |
| `comissao` (terreno) | **0,1 %** | **0 %** (`DRE!D62`) | ❌ divergente |
| `canteiro_mensal` | 85.715 | 155.516 (ex.) | por projeto (Custos Fernando) |
| `mo_administrativa` | 62.502 | 114.059 (ex.) | por projeto (Custos Fernando) |
| `prazo_obra` | 36 | 24 (ex.) | por projeto |
| `porcentagem_lote_proprietario` | 10 % | 0 % (ex.) | por projeto |

**Conferem** (amostra): `pis_cofins` 4 %, `iss` 0 %, `outros_impostos` 0,5 %, `seguros` 0,5 %, `assistencia_tecnica` 1 %, `despesas_comerciais` 5 %, `marketing` 1 %, `itbi_iptu` 1,1 %, `registro` 2.500, `contratos_cef` 300, `produtos_cef` 0,5 %, `outras_despesas_financeiras` 0,3 %, `taxa_juros_pj` 10,5 %, `percentual_antecipacao_pj` 10 %, `devolucao_aporte_percentual` 20 %, `distribuicao_lucros` 100 %, `taxa_exposicao_aplicada` 12,5 %, `bonus_cca` 350, `bonus_gerente` 0,3 %, `ajuda_custo_gerente` 5.000, `parcelamento_comissao_meses` 18, `meses_incorporacao` 18, `meses_lancamento` 6, `meses_pos_obra` 60.

> Como esses são **defaults** (sobrescritos pelos valores salvos de cada `Viabilidade`/`Premissas`), o impacto real depende de o cadastro do projeto preencher os campos. Ainda assim, os três divergentes (`infra_nao_incidente`, `contrapartidas`, `comissao`) devem ser alinhados ao padrão da planilha para evitar resultados diferentes em projetos que não sobrescrevem esses campos.

---

## 5-bis. Lacunas de dados de referência (confirmam relatório anterior)

| Item | Na planilha | No sistema | Status |
|---|---|---|---|
| **Custos Fernando** (infra/lote, hab R$/m², M.O. Adm e canteiro por faixa de unidades e por regional) | Aba dedicada; alimenta `DRE!D69` (canteiro 155.516), `D70` (M.O. Adm 114.059), `J32` ($/m²), `J35` (infra/lote) | Valores entram **manualmente** por projeto; não há tabela regional | ❌ Ausente |
| **Limites MCMV por município** | `TABELA MUNICÍPIOS` com VLOOKUP de limite (`DRE!S6`) | `Cidade` tem dados socioeconômicos, **sem** `vlr_limite_ate/acima_4700` nem `regional` | ⚠️ Parcial |
| **Dados Operacionais / VSO / ticket por tipologia anualizado** | Aba oculta (em construção na planilha) | Não há no motor de viabilidade | ⚠️ Pendente nos dois lados |
| **FC do Terrenista** | Item r6 da Lista (pendente, sem OK) | Não há | ⚠️ Pendente nos dois lados |

---

## 6. Itens da "Lista de Desenv." da planilha × sistema

| Item (planilha) | Status na planilha | No sistema |
|---|---|---|
| DRE e FC resultam iguais | OK | ✅ ponte existe, falta teste (§4.5) |
| FC: necessidade de aporte e DL | pendente | ✅ implementado |
| FC: recurso PJ Caixa | pendente | ✅ implementado |
| FC: juros nas parcelas pós-obra | pendente | ✅ implementado (`preCalcularRecebiveisCef`) |
| FC Terrenista | pendente | ❌ não implementado |
| POC: reconhecimento de permutas/terrenos | OK | ✅ `PocCalculator` |
| Resumo: Dados Operacionais / DRE-FC anualizado / TIR-Payback | pendente (parte) | TIR/Payback ✅; anualização parcial; Dados Op. ❌ |
| Tab Mestre: dados de projeto/cidade/regional | OK | ✅ |
| Tab Mestre: medição por velocidade de vendas | pendente | ✅ implementado |
| Tab Mestre: 5% finais (2%+3%) | pendente | ❌ §4.2 |
| Tab Mestre: curva de repasses em unidades/VGV | pendente | ❌ não implementado |

---

## 7. Recomendações priorizadas

1. **(Alta) Substituir as curvas de obra hardcoded** por importação da `Aux_Obras` oficial (prazos 12–43), tabelada. Sem isso, todo o fluxo de obra diverge da planilha — §4.1.
2. **(Alta) Definir o papel da inadimplência/atraso/perda** — §4.6. Para paridade com a planilha, zerar `inadimplencia`/`taxa_perda` por default (a planilha não os modela); se forem premissas desejadas, documentar que o sistema é deliberadamente mais conservador que a planilha.
3. **(Alta) Importar a tabela "Custos Fernando"** como dado estruturado (por regional × modalidade × faixa de unidades), eliminando entrada manual de canteiro/M.O. Adm/$ m²/infra.
4. **(Média) Alinhar os 3 defaults divergentes** — `infra_nao_incidente` (1,0→1,5 %), `contrapartidas` (0→1,0 %), `comissao` terreno (0,1→0 %) — §5.
5. **(Média) Adicionar teste de invariante DRE = Fluxo** (`Σ Saldo Operacional ≈ Lucro Líquido`, tol. < R$ 1) e somas de curvas = 100% — §4.5.
6. **(Média) Alinhar o método de TIR** com a planilha (XIRR com datas reais) ou documentar formalmente a divergência — §4.3.
7. **(Média) Implementar a regra dos 5% finais** (2% em entrega+2, 3% em entrega+6) — §4.2 — *após confirmar com o autor que ainda é desejada*.
8. **(Média) Corrigir o cronograma da compra direta do terreno** (4 parcelas semestrais, não diluído na obra) — §4.7.
9. **(Média) Completar os limites MCMV** na tabela de municípios (`limite_ate/acima_4700`, `regional`).
10. **(Baixa) Calibração com a planilha:** rodar um projeto idêntico nos dois e comparar linha a linha do `Tab_Mestre` — é a única forma de provar paridade numérica. As divergências de §4.4 (0,25 vs 0,20), §4.6 e §4.7 só aparecem nesse teste.

> **Importante:** este relatório compara **estrutura e fórmulas**, não valores calculados. A fidelidade numérica final só se confirma rodando o mesmo empreendimento na planilha e no sistema e reconciliando o `Tab_Mestre` mês a mês. Recomendo isso como próximo passo antes de qualquer go-live.

---

*Gerado a partir da extração de fórmulas das 12 abas da planilha (openpyxl) e leitura dos Calculators em `app/Services/Tenant/Viabilidade/v1/`. As células de exemplo citadas (ex.: `Aux_Obras col I`, `Resumo!D61`, `Premissas!C268`) são verificáveis diretamente no arquivo.*
