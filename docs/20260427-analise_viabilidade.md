# Análise Comparativa: Tab_Mestre (Planilha) vs Fluxo de Caixa (Sistema)

**Data**: 2026-04-27
**Planilha**: `docs/viabilidade-modelo/Viabilidade LRG - V.01.2026 - Modelo - cópia.xlsx`
**Sistema**: `app/Services/Tenant/Viabilidade/v1/ViabilidadeUnificadoService.php`

---

## Estrutura Geral — Alinhada

Ambos seguem a mesma estrutura de pipeline:

```
ENTRADAS (FA) = Rec. Próprios (ED) + Terreno CEF (EP) + Medição Obra (EY)
  - DEDUÇÕES (FG)
  - TERRENO (FM)
  - INCORPORAÇÃO (FU)
  - OBRA (GE)
  - M.O. ADM + SEGURO + ASSIST. TÉCNICA
  - DESPESAS COMERCIAIS (HF)
  - MARKETING (HL)
  - ITBI + REGISTRO
  - TX CAIXA
  - OUTRAS DESP. FINANC
= SALDO OPERACIONAL (IC/ID)
  + PJ CAIXA (financiamento)
  +/- APORTE
  +/- DISTRIBUIÇÃO LUCRO
= SALDO FINAL (JF/JO)
```

---

## Comparação Detalhada por Bloco

### 1. RECURSOS PRÓPRIOS (Colunas DQ-ED da planilha)

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| Fonte dos dados | `Aux_Parcelas` (tabelas de amortização pré-calculadas) | `preCalcularRecebiveis()` — calcula inline | Diferente mecanismo, mesma lógica |
| Sinal | Parcelado no lançamento se venda antes do fim do lançamento; à vista depois | Idêntico | OK |
| Parcelas de Obra (CEF) | Correção composta `pow(1 + r_obra, meses_passados)` | Idêntico | OK |
| Pós-Chave (CEF) | SAC: amortização + juros + correção sobre saldo devedor | Idêntico | OK |
| Balões (Próprio) | N/A na planilha (a planilha é CEF) | Implementado como `preCalcularRecebiveisProprio()` | Recurso adicional do sistema |

**Possível divergência**: A planilha usa `Aux_Parcelas` que é uma tabela de amortização montada com TODAS as unidades e vendas, enquanto o sistema calcula por coorte de vendas. O resultado final deve ser igual, mas o arredondamento pode variar.

### 2. RECURSO TERRENOS — CEF (Colunas EF-EP da planilha)

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| Cálculo por produto | `AM24 * Premissas!C$49` (unidades LRG × avaliacao_cef) | `calcularRtMesVenda()`: `unidadesVendidas × avaliacaoCef × preco` ou `unidadesVendidas × avaliacaoCef` | OK |
| Defasagem | Acumula meses 1-4, libera no mês 4; mês 5+ libera normal | MÊS 4 — sistema usa `diffInMonths + 1 == 4` | OK |
| Demanda mínima | `EK/EL/EM` — controla se atingiu demanda mínima para liberar | `demandaAtingida` no contexto | OK |

### 3. MEDIÇÃO DE OBRA — CEF (Colunas ER-EY da planilha)

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| Início | Mês 6 da obra (5 meses após lançamento) | `inicioObra->addMonths(5)` | OK |
| Curva | `VLOOKUP` em `Aux_Obras` (curva S pré-calculada) | `curvaObraAgregada` via `CurvaService->agregarCurvaObra()` | Verificar |
| Medição Teórica | `%ObraAcum × ValorTotal` | `curvaObraAcumulada × valorMedicaoTotal` | OK |
| Medição Vendida | `MediçãoTeórica × %VendasAcum` | `medicaoTeoricaAcumulada × percVendasAcumulado` | OK |
| Valor mês | `EX_atual - EX_anterior` (diferença do acumulado) | `medicaoVendidaAcumulada - medicaoObraAcumulada` | OK |
| Valor Total Financiamento | `(VGV s/Permuta × 80%) - Recurso Terrenos` | Idêntico | OK |

### 4. DEDUÇÕES (Colunas FC-FG da planilha)

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| RET/LP (imóveis) | `(FA - DS - DW - EA) × Premissas!C65` — subtrai receita de lotes | `ImpostosService->calcularTributosPorProduto()` | Possível divergência — planilha subtrai receita de lotes da base |
| RET/LP (lotes) | `(DS + DW + EA) × Premissas!E65` — alíquota separada | Delegado ao produto | Verificar |
| ISS | `FA × Premissas!C66` | Delegado ao produto | OK |
| Outras Deduções | `(FA - EC) × Premissas!C67` — exclui juros+correção da base | Delegado ao produto | Possível divergência se o sistema não excluir juros+correção |

**Divergência potencial**: A planilha separa lotes vs imóveis na base de cálculo do RET e exclui juros+correção da base de "Outras Deduções". O sistema delega isso ao `ImpostosService` — seria importante verificar se ele faz a mesma distinção.

### 5. TERRENO — DESPESA (Colunas FI-FM da planilha)

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| Permuta Financeira | `DRE!F27 × FA/FA4` (proporcional à receita do mês) | `calcularCustoTerreno()` — proporcional à receita | Divergência — planilha usa `DRE!F27` (valor fixo), sistema calcula `permutas × preco + compraTerreno` |
| Permuta Física | `GD6 × Premissas!F118` | Via `calcularPagamentoParceriaTerreno()` — 8% da receita a partir da obra | Lógica diferente |
| Comissão Terreno | `Premissas!F126` se flag ativo, senão 0 | Fixo 1% no DRE, não aparece no fluxo mensal | Divergência |

### 6. INCORPORAÇÃO (Colunas FO-FU da planilha)

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| RI | Parcela única no mês B=-1 | Rateado? | Verificar — planilha pode concentrar, sistema pode ratear |
| Entrega | Parcela única no mês da entrega | `calcularCustosDiretos()` não tem lógica de entrega | Lacuna |
| Até Lançamento | Rateado nos meses de incorporação `Premissas!F139` | `calcularCustosDiretos()` rateia `incorporacaoAtéLançamento / mesesIncorporacao` | OK |
| Após Lançamento | Rateado nos meses de obra `Premissas!F143` | Rateia o restante nos meses de obra | OK |

### 7. OBRA (Colunas FW-GE da planilha)

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| Curva parcial (obra) | `FY = VLOOKUP(FX, Aux_Obras, 31)` — % da curva S | `curvaObraAgregada` via `CurvaService` | OK se mesma curva |
| Desembolso obra | `FZ = FY × Premissas!F145` | `custoObraTotal × (percentualMes / 100)` | OK |
| Curva lançamento | `GB = 0.01 / prazo_lançamento` durante lançamento | Não existe separação lançamento/obra na curva de obra | Divergência — planilha separa 1% do custo obra no lançamento |
| Desembolso total | `GE = FZ + GC` | Só tem `custoObra × curva` | Divergência — sistema não separa fatia de lançamento |

### 8. DESPESAS COMERCIAIS (Colunas GT-HF da planilha)

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| Stand | Rateado no lançamento + gastos mensais | `standParcelado` + `gastosMensaisStand` | OK |
| Comissão Venda | `CU × Premissas!F205` (unidades vendidas × valor por unidade) | `comissaoBaseMes × pagamentoComissaoVenda` | OK |
| Comissão Desligamento | `DO × Premissas!F206` (desligamentos × valor) | `calcularComissaoDesligamentoMensal()` — parcelada | Divergência — sistema parcela, planilha parece concentrar |
| Bônus CCA | `DO × Premissas!C218` | `bonusCca × unidadesVendidasMes` | OK |
| Bônus Equipe | Só quando 100% vendido | `bonusGerente + bonusGerenteRegional + etc` a cada mês | Divergência — planilha condiciona a 100% vendas |

### 9. MARKETING (Colunas HH-HL da planilha)

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| Lançamento | Parcelado no lançamento | `totalLancamento / mesesLancamento` durante lançamento | OK |
| Unidade Vendida | `CU × Premissas!F234` (por unidade) | `totalVariavel × (unidadesVendidas / totalUnidades)` | Divergência: planilha distribui por unidade vendida, sistema rateia sobre total |

### 10. ITBI / REGISTRO / TX CAIXA

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| ITBI | `CU × Premissas!F237` — por unidade vendida | Rateado (não aparece por mês no código de despesas) | Lacuna — ITBI não aparece no fluxo mensal do sistema |
| Registro | `CU × Premissas!F240` — por unidade vendida | Rateado (não aparece por mês) | Lacuna |
| Tx Medição | Mensal durante obra | Só no mês 1 da obra (`inicioObra`) | Divergência — planilha é mensal |
| Contratos Caixa | `CU × Premissas!F247` — por unidade vendida | Só no mês 1 da obra | Divergência |
| Produtos Caixa | `DO × Premissas!F250` — por unidade vendida (DO=desligamentos) | `calcularProdutosCefPorTipologia()` — total, não por mês | Divergência |

### 11. PJ CAIXA (Colunas IG-IV da planilha)

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| Entrada Antecipação | No mês do desligamento (`DM`) | No mês `inicioObra` | Divergência — timing diferente |
| Amortização | Durante período de amortização | Após carência pós-obra | OK |
| Juros | Saldo devedor × taxa mensal | Idem | OK |

### 12. DRE (Colunas LB-MA da planilha)

| Aspecto | Planilha | Sistema | Status |
|---------|----------|---------|--------|
| Receita | Referencia POC (`KH`) | VGV s/Terrenista + Juros/Correções | Abordagem diferente |
| Deduções | Sobre base POC | Sobre VGV s/Terrenista | Diferente |
| Assist. Técnica | Provisão separada (LG) | Somada ao CSP | Diferente |
| Despesas | Valores da planilha mês a mês | Totais orçados (não mensais) | Divergência |

**Resultado final DRE (planilha)**:
- Margem Líquida = **23.25%** sobre VGV Venda (R$ 57.06M / R$ 245.4M ROL)
- Margem Líquida sobre VGV s/Permuta = **22.92%**

---

## Resumo das Divergências Mais Relevantes

1. **ITBI e Registro ausentes no fluxo mensal**: A planilha debita ITBI e Registro por unidade vendida a cada mês. O sistema parece não incluí-los no fluxo mensal (apenas no DRE gerencial).

2. **OBRA — Separação Lançamento vs Execução**: A planilha separa 1% do custo de obra no período de lançamento (`GB = 0.01 / prazo_lancamento`). O sistema aplica a curva uniformemente sem essa separação.

3. **Tx Medição e Contratos Caixa**: A planilha cobra mensalmente durante toda a obra; o sistema concentra no primeiro mês.

4. **Comissão Desligamento**: A planilha concentra no desligamento; o sistema parcela.

5. **Deduções — Base de cálculo**: A planilha separa lotes de imóveis na base do RET e exclui juros+correção das "Outras Deduções". Precisa verificar se o `ImpostosService` faz o mesmo.

6. **PJ Caixa — Timing da entrada**: Planilha entrada no desligamento, sistema no início da obra.

7. **Bônus Equipe Comercial**: Planilha só libera quando atinge 100% de vendas; sistema paga proporcionalmente a cada mês.

8. **DRE**: A planilha usa DRE contábil via POC (percentual de execução); o sistema usa DRE gerencial (base orçada). São duas visões diferentes — a planilha tem ambas, o sistema gera a gerencial e calcula a contábil separadamente.

9. **Marketing por unidade**: A planilha multiplica unidades vendidas pelo valor por unidade (`Premissas!F234`); o sistema rateia o total variável pelo percentual de unidades vendidas.

10. **Produtos Caixa**: Planilha usa `DO × Premissas!F250` (desligamentos × valor por unidade); sistema calcula sobre a base sem terrenista usando `percentualProdutosCef`.

---

## Parâmetros da Planilha (Premissas)

| Parâmetro | Valor |
|-----------|-------|
| Data Lançamento | 2029-06-01 |
| Incorporação | 18 meses |
| Lançamento | 6 meses |
| Obra | 36 meses |
| Pós-Obra | 60 meses |
| Total Lotes | 1444.44 |
| Unidades a Comercializar | 1300 |
| VGV Total | R$ 269.000.000 |
| VGV LRG (S/ Permuta) | R$ 248.900.000 |
| VGV LRG (S/ Terrenista) | R$ 236.900.000 |
| Permutas Físicas | 90 unidades |
| Sinal | 2% |
| Parcelas Obra | 9% |
| Parcelas Pós-Chave | 9% (36 parcelas) |
| Financiamento CEF | 80% |
| Demanda Mínima CEF | 30% |
| Juros Pós-Chave | 1% a.m. |
| Correção Obra | 5% a.a. (INCC) |
| Correção Pós-Chave | 4.5% a.a. (IPCA) |
| Impostos (imóveis) | 4% RET |
| Impostos (lotes) | 6.73% |
| ISS | 0% |
| Outras Deduções | 0.5% |
| Permuta Financeira | 8% do VGV |
| Incorporação | 1% do VGV |
| Despesas Comerciais | 5% do VGV |
| Marketing | 1% do VGV |
| Comissão House | 3% |
| Comissão Imobs | 3.5% |
| Seguros | 0.5% do VGV |
| Assistência Técnica | 1% do custo obra |
| Antecipação PJ | 10% do custo obra |
| Taxa Juros PJ | 10.5% a.a. |



  Gaps Críticos (7 itens ausentes ou com timing errado no fluxo mensal)
                                                                                                                                                                   
  1. ITBI/IPTU (HN): Planilha debita CU × Premissas!F237 por mês conforme unidades vendidas. Sistema não tem no fluxo mensal.
  2. Registro (HO): Planilha debita CU × Premissas!F240 por mês. Sistema não tem no fluxo mensal.                                                                  
  3. Tx Medição (HQ): Planilha cobra mensalmente durante toda obra (Premissas!F243). Sistema só no primeiro mês.
  4. Tx Contratação (HS): Planilha cobra por unidade vendida. Sistema só no primeiro mês.                                                                          
  5. Contratos Caixa (HU): Planilha cobra CU × Premissas!F247 (unidades vendidas × valor). Sistema só no primeiro mês.
  6. Produtos Caixa (HV): Planilha cobra DO × Premissas!F250 por desligamentos. Sistema ausente do fluxo mensal.                                                   
  7. Obra — Curva de Lançamento (GC): Planilha separa 1% do custo total da obra para o período de lançamento (GB=0.01/prazo_lanc). Sistema não faz essa separação. 
                                                                                                                                                                   
  Gaps Médios (4 itens)                                                                                                                                            
                                                                                                                                                                   
  8. Comissão Terreno (FL): Planilha debita Premissas!F126 parcelado (18 meses). Sistema tem 1% mas timing diferente.                                              
  9. Deduções separadas por regime: Planilha separa FC (RET imóveis), FD (RET lotes), FE (ISS), FF (Outras). Sistema agrupa em "Tributos".                         
  10. Bônus Equipe (HE): Planilha só libera quando 100% vendido. Sistema paga a cada mês.                                                                          
  11. M.O. ADM: Planilha debita mensal. Sistema está nos custos diretos agregados.                                                                                 
                                                       
