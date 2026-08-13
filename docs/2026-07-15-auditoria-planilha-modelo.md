# Auditoria da viabilidade Cimcal — Osvaldo Cruz

## Escopo e fonte

- Arquivo: `docs/viabilidade-modelo/20260710 - Viabilidade Cimcal - Osvaldo Cruz.xlsx`
- SHA-256: `b7ad9ff34b69dc6b1ea172ea967ce805e6dc2f28cc5aee19096fd171fb40f4f2`
- Modelo: `v.02.2026`
- Projeto: `Lot Flores da Terra`, Osvaldo Cruz/SP
- Cenário: incorporação com financiamento CEF, 2.000 unidades e 80 unidades de permuta física

Os resultados da planilha foram lidos dos valores armazenados no próprio XLSX. O arquivo está configurado para recálculo completo ao abrir e contém um vínculo externo em `DRE!S6`. Esse vínculo não altera os valores principais deste cenário, mas reduz a reprodutibilidade fora do ambiente original da planilha.

## Premissas reproduzidas

| Premissa | Valor |
|---|---:|
| Lançamento | 01/06/2029 |
| Incorporação / lançamento / obra / pós-obra | 18 / 6 / 36 / 60 meses |
| 2 Dorm. | 1.700 un.; R$ 200.000; 70 permutas; 47,20 m² |
| 3 Dorm. | 300 un.; R$ 350.000; 10 permutas; 61,33 m² |
| Custo de construção | R$ 1.400/m² |
| Infraestrutura | R$ 22.000/unidade |
| VGV bruto | R$ 445.000.000 |
| VGV sem permutas | R$ 427.500.000 |
| Pagamento fixo ao terrenista | R$ 20.000.000 |
| VGV líquido do terrenista | R$ 407.500.000 |
| Compra do terreno / parceria financeira | R$ 10.000.000 / 5% |
| PIS/COFINS / outras deduções | 4% / 0,5% |
| Incorporação / contrapartidas / seguros | 1% / 1% / 0,5% |
| Canteiro / M.O. administrativa | R$ 85.000 / R$ 60.000 por mês |
| Despesas comerciais / marketing | 4% / 1% |
| Antecipação PJ / juros | 10% / 10,5% a.a. |

A curva de vendas de 21 meses foi transcrita integralmente. Inadimplência, atraso e perda foram zerados porque não existem como ajustes explícitos no cenário da planilha.

## Resultado da comparação

> Atualização: as fórmulas corrigidas foram consolidadas no motor `2.4.0`. DRE, fluxo operacional, calendário mensal, aportes, devoluções, distribuição, exposições, paybacks e TIRs reconciliam com a planilha.

### DRE

A DRE corresponde ao XLSX dentro da tolerância de 0,10%. Os principais valores coincidiram sem diferença material:

| Métrica | Planilha | Sistema | Diferença |
|---|---:|---:|---:|
| Receita de vendas | R$ 407.500.000,00 | R$ 407.500.000,00 | 0,000% |
| Juros e correções | R$ 10.430.752,43 | R$ 10.430.752,43 | 0,000% |
| Receita líquida | R$ 407.200.292,78 | R$ 407.200.292,78 | 0,000% |
| Custo do terreno | R$ 38.140.757,62 | R$ 38.140.757,62 | 0,000% |
| Custos diretos | R$ 235.623.641,42 | R$ 235.623.641,42 | 0,000% |
| EBITDA | R$ 137.793.651,36 | R$ 137.793.651,36 | 0,000% |
| Juros PJ | R$ 8.038.273,51 | R$ 8.038.273,51 | 0,000% |
| Lucro líquido | R$ 120.508.607,40 | R$ 120.508.607,40 | 0,000% |
| Margem sobre ROL | 29,5944% | 29,59% | -0,0044 p.p. |

### Fluxo e indicadores

| Métrica | Planilha | Sistema | Diferença |
|---|---:|---:|---:|
| Entradas | R$ 417.930.752,43 | R$ 417.930.752,41 | ≈ 0% |
| Saídas operacionais | R$ 289.383.871,52 | R$ 289.383.871,23 | ≈ 0% |
| FCO / saldo operacional final | R$ 128.546.880,91 | R$ 128.546.881,18 | ≈ 0% |
| Aporte total | R$ 8.324.694,15 | R$ 8.324.693,05 | -R$ 1,10 |
| Devolução total de aporte | R$ 8.324.694,15 | R$ 8.324.693,05 | -R$ 1,10 |
| Saldo elegível antes da distribuição | R$ 120.508.606,40 | R$ 120.508.607,84 | +R$ 1,44 |
| Distribuição total de lucros | R$ 120.508.606,40 | R$ 120.508.607,84 | +R$ 1,44 |
| Saldo financeiro final após distribuições | R$ 0,00 | R$ 0,00 | igual |
| Principal PJ | R$ 18.681.038,00 | R$ 18.681.038,00 | 0,000% |
| Juros PJ no fluxo | R$ 8.038.273,51 | R$ 8.038.273,51 | 0,000% |
| Exposição operacional | -R$ 13.019.359,35 | -R$ 13.019.358,25 | R$ 1,10 |
| Exposição financeira | -R$ 8.324.694,15 | -R$ 8.324.693,05 | R$ 1,10 |
| Payback operacional | 23 meses | 23 meses | igual |
| Payback financeiro | 22 meses | 22 meses | igual |
| TIR operacional anual | 600,53% | 600,55% | +0,02 p.p. |
| TIR financeira anual | 991,81% | 991,81% | igual |

## Achados

### Corrigido — Custos CEF e despesas de venda no total mensal

ITBI/IPTU, registro, contratação, medição, produtos e contratos CEF agora entram no total e na categoria operacional, além do detalhamento mensal.

Também foi corrigido o carregamento de frações da curva de vendas entre meses. Assim, os custos por unidade alcançam todas as 1.920 unidades comercializáveis, sem perder unidades por arredondamento mensal.

### Corrigido — Base do principal PJ

A base do principal passou a incluir habitação, infraestrutura incidente e não incidente, contrapartidas, área comum e canteiro. O principal reconciliou em R$ 18.681.038,00 e os juros em R$ 8.038.273,51.

### Corrigido — Fluxo de obra e assistência técnica

A base mensal de obra passou a incluir contrapartidas. Quando existe desembolso pré-lançamento, a curva posterior é aplicada somente sobre o percentual restante. A assistência técnica também passou a incluir a infraestrutura não incidente na base.

Com isso, o fluxo operacional final reconciliou com a planilha, restando apenas R$ 0,22 de diferença acumulada por arredondamentos mensais.

### Corrigido — Unidade do gasto mensal de stand

A planilha e o motor agora usam razão decimal: `0,0001` equivale a 0,01%. Nesse cenário, o gasto é R$ 44.500 por mês. O teste de conformidade protege essa unidade contra regressões.

### Corrigido — Aporte, devolução e distribuição conforme `JA:JO`

O motor `2.4.0` passou a reproduzir a sequência mensal da planilha:

1. aporta o déficit incremental enquanto o saldo livre acumulado permanece negativo (`JA`);
2. após completar os aportes, devolve até 25% do saldo acumulado no mês, limitada ao total aportado (`JD:JF`);
3. reserva caixa equivalente às saídas operacionais de um mês (`JH:JJ`); e
4. distribui os incrementos positivos do excedente acumulado, respeitando o percentual configurado e o saldo elegível final (`JK:JO`).

Também foi corrigida a leitura do benchmark: R$ 120.508.606,40 é o saldo elegível antes da distribuição e o total distribuído. A coluna `JO` termina em R$ 0,00, igual ao sistema. As diferenças de R$ 1,10 nos aportes e R$ 1,44 na distribuição decorrem do arredondamento mensal em centavos já observado no fluxo operacional.

### Corrigido — Residual comercial negativo

O motor passou a preservar o residual negativo de R$ 728.286,00 usado pelo cenário canônico. As despesas comerciais reconciliaram em R$ 17.100.000,00.

Esse comportamento replica a planilha, mas a modelagem de negócio ainda pode ser aprimorada no futuro para expor explicitamente o ajuste de reconciliação.

### Corrigido — Calendário mensal, seguros e medição CEF

O seguro passou a ser desembolsado linearmente entre lançamento e fim da obra, fora da curva física. A medição CEF agora conserva a curva oficial até o acumulado anterior a 95%, retém o restante e libera 55%/45% em `prazo+2`/`prazo+5`. A compra direta do terreno é paga mensalmente durante a obra e os custos por unidade preservam frações mensais de vendas. Com isso, a exposição operacional e o payback reconciliaram.

### Corrigido — TIR conforme o XIRR da planilha

A planilha calcula `XIRR(Tab_Mestre!ID6:ID99, Tab_Mestre!I6:I99)` para a TIR operacional e `XIRR(Tab_Mestre!IU6:IU99, Tab_Mestre!I6:I99)` para a financeira. O motor passou a usar os mesmos conceitos: saldos operacionais acumulados e saldos acumulados após funding e serviço da dívida PJ, sempre com a quantidade real de dias entre as datas.

O solucionador não descarta retornos anuais acima de 500%. Em fluxos não convencionais, procura todas as raízes: usa a única raiz não negativa e retorna `null` quando existem múltiplas raízes não negativas, pois nesse caso a TIR é matematicamente ambígua.

No cenário Cimcal, o motor resulta em 600,55% a.a. operacional e 991,81% a.a. financeira, contra 600,53% e 991,81% do XLSX. A diferença operacional de 0,02 p.p. decorre dos arredondamentos mensais em centavos.

O cronograma PJ também foi alinhado: desembolso ao atingir a demanda mínima, juros no próprio mês do desembolso, janela regular de juros a partir da obra e amortização SAC após a carência. Isso reconciliou exposição financeira, payback e TIR financeira.

### P2 — Risco de reprodutibilidade da planilha

O XLSX possui recálculo completo ao abrir, vínculo externo e células auxiliares com `#DIV/0!` quando a tipologia de lotes está zerada. Os valores principais estavam armazenados e foram auditáveis, mas recomenda-se eliminar o vínculo externo ou incorporar a tabela referenciada ao arquivo canônico.

## Teste de conformidade

O teste `tests/Feature/Tenant/PlanilhaConformidadeTest.php` agora:

1. monta exatamente o cenário Cimcal;
2. valida VGV, unidades e todas as linhas relevantes da DRE com tolerância de 0,10%;
3. valida entradas, saídas, FCO, saldo operacional, aporte, devolução, saldo elegível, distribuição, saldo final, principal e juros PJ do fluxo com tolerância de 0,001%;
4. valida exposições em até R$ 2,00, paybacks de forma exata e TIRs em até 0,10 ponto percentual;
5. imprime a comparação completa e mantém um baseline explícito do motor `2.4.0`.

Os valores, células-fonte, hash e divergências estão registrados em `tests/Fixtures/Viabilidade/cimcal_osvaldo_cruz_v02_2026.json`.
