# Arquitetura de Cálculos — Viabilidade Financeira

## 1. Fluxo de Dados (Dependência entre Abas)

```
Premissas ─────────────────────────────────────────────────────────┐
  │  (inputs manuais: unidades, ticket, prazos, %s, custos)        │
  │                                                                 │
  ├──► Tab_Mestre ◄── Aux_Parcelas ◄── Premissas (% sinal/obra/pós)│
  │       │              (1877 linhas: simulador de parcelas        │
  │       │                por lote, com juros e correção)          │
  │       │                                                        │
  │       ├──► Aux_Obras (curva de obra 36 meses)                   │
  │       │                                                        │
  │       ├──► Terreno (pagamento do terreno mês a mês)             │
  │       │                                                        │
  │       ├──► Fluxo e DRE (consolidação anual)                     │
  │       │                                                        │
  │       └──► Resumo ─── (TIR, Payback, Exposição)                 │
  │                                                                 │
  └──► DRE (demonstração de resultado contábil via POC)             │
```

---

## 2. Premissas — A Raiz de Tudo

**Aba:** `Premissas` — 295 linhas de inputs manuais

| Bloco | Linhas | Conteúdo |
|---|---|---|
| Dados do Projeto | 4–25 | Nome, cidade, modalidade, unidades, ticket, VGV |
| Permuta | 27–35 | Unidades e VGV do terrenista e LRG |
| Estrutura de Recebíveis | 37–50 | % Sinal, % Obra, % Pós-chave, % Financiamento |
| Preço médio m² | 52 | Cálculo (VGV / m² total) |
| Custos de Construção | 54–74 | Habitação/m², Infra/lote, Canteiro |
| Curva de Vendas | 77–114 | Tabela VLOOKUP: mês → % vendas por tipologia |
| Custos Terreno | 117–126 | Permuta financeira, comissão |
| Incorporação | 128–143 | RI, certidões, taxas |
| Obra (Custo) | 145–152 | Curva de obra % vs valor total |
| Despesas Operacionais | 155–252 | M.O. adm, seguros, assistência técnica, stand, comissões, marketing |
| Antecipação PJ | 255–263 | Principal, spread, taxas de juros |

### Principais fórmulas em Premissas

```
VGV LRG (s/ $ Terrenista) = VGV LRG (s/ Permuta) - Valor Terrenista
                           = 202.400.000 - 10.000.000 = 192.400.000

$ Sinal  = VGV LRG (s/ $ Terrenista) × 2%  = 4.048.000
$ Obra   = VGV LRG (s/ $ Terrenista) × 9%  = 18.216.000
$ Pós    = VGV LRG (s/ $ Terrenista) × 9%  = 18.216.000
$ Financ = residual — o que sobra após Sinal, Obra e Pós = 151.920.000
```

A coluna F (TOTAL) é a soma das 3 tipologias (2 Dorm., 3 Dorm., Lotes):

```excel
F18 = SUM(C18:E18)
F22 = SUM(C22:E22)
F25 = SUM(C25:E25)
F30 = SUM(C30:E30)
F35 = F30 - F33
```

---

## 3. Tab_Mestre — O Coração do Modelo

**Aba:** `Tab_Mestre` — 212 linhas × 341 colunas

É uma **linha do tempo mensal**. Cada linha representa 1 mês. As linhas vão do mês -18 (pré-incorporação) até o mês +102 (fim do pós-obra).

### 3.1 Coluna B — Meses Relativos

```excel
B6 = 0 - M2          → começa em -18 (M2 = 18 meses de incorporação)
B7 = B6 + 1           → incrementa até chegar em +102
```

### 3.2 Coluna I — Data Real

```excel
I6 = EDATE(L2, -M2)  → EDATE(data_lançamento, -meses_incorporação)
```

### 3.3 Colunas M-Q — Fases (flags booleanas)

```excel
M6 = IF(B6 < 0, 1, 0)                     → INCORP (meses negativos)
N6 = IF(B6 >= 0, IF(B6 < N2, 1, 0), 0)    → LANÇAMENTO (mês 0 a 5)
O6 = IF(B6 >= O3, IF(B6 < O4, 1, 0), 0)   → OBRA (mês 6 a 41)
P6 = IF(B6 = P3, 1, 0)                     → ENTREGA (mês 42)
Q6 = IF(B6 >= Q3, IF(B6 < Q4, 1, 0), 0)   → PÓS OBRA (mês 42 a 101)
```

### 3.4 Status do Estoque (Colunas S-V, X-AA, AC-AF)

São instantâneos de status do projeto:

- **Lançamento:** Só no mês do lançamento (`I6 = L2`)
- **Em Andamento:** Durante lançamento OU obra
- **Entregue:** No mês da entrega (`P6 = 1`)

```excel
S6  = IF(I6 = L2, Premissas!F22, 0)      → Unidades lançadas (no mês 0)
T6  = IF(I6 = L2, Premissas!F25, 0)      → VGV lançado
U6  = IF(I6 = L2, Premissas!F30, 0)      → VGV LRG (s/ permuta) lançado
V6  = IF(I6 = L2, Premissas!F35, 0)      → VGV LRG (s/ lote terrenista) lançado

X6  = IF(N6=1, Premissas!F22, IF(O6=1, Premissas!F22, 0))  → Em andamento
AC6 = IF(P6=1, Premissas!F22, 0)         → Entregue
```

### 3.5 Vendas por Tipologia

Três blocos estruturalmente idênticos:

| Bloco | Colunas | Tipologia |
|---|---|---|
| AH–AZ | 2 Dorm. |
| BB–BT | 3 Dorm. |
| BV–CN | Lotes Comerciais |

#### Fluxo de cálculo (exemplo: 2 Dorm.)

```excel
AH6 = VLOOKUP(B6, Premissas!B77:E114, 2, 0)  → % vendas do mês via tabela de curva
AI6 = AH6                                    → % vendas (igual)
AJ6 = AH4                                    → % estoque = % total

AK6 = AH6 * AK4                              → Unidades totais do mês
AL6 = AH6 * Premissas!C27                    → Unidades permuta física
AM6 = AK6 - AL6                              → Unidades LRG (líquidas)
AN6 = AM6                                    → Acumulado LRG (arraste)

AP6 = AH6 * AP4                              → VGV Venda do mês
AS6 = AH6 * AS4                              → VGV Permuta Física
AT6 = AP6 - AS6                              → VGV LRG (s/ permuta)
AU6 = AT6                                    → Acumulado

AW6 = AW4 * AH6                              → Valor por lote (fração terrenista)
AX6 = AT6 - AW6                              → VGV LRG (s/ lote terrenista)
AY6 = AX6                                    → Acumulado ← RECEITA LÍQUIDA
```

### 3.6 Total Vendas (Colunas CP-DH)

Soma as 3 tipologias:

```excel
CS6 = AK6 + BE6 + BY6      → Unidades totais
CT6 = AL6 + BF6 + BZ6      → Unidades permuta física
CU6 = AM6 + BG6 + CA6      → Unidades LRG
CX6 = AP6 + BJ6 + CD6      → VGV Venda total
CY6 = CX6                  → Acumulado
DA6 = AS6 + BM6 + CG6      → VGV Permuta Física
DB6 = CX6 - DA6            → VGV LRG (s/ permuta)
DC6 = DB6                  → Acumulado
DE6 = AW6 + BQ6 + CK6      → Valor total terrenista
DF6 = DB6 - DE6            → VGV LRG (s/ lote terrenista) ← **RECEITA BASE**
DG6 = DF6                  → Acumulado
```

**Premissa crítica:** A coluna `DF` (VGV LRG s/ Lote Terrenista) é a base para TODOS os cálculos de receita, incluindo medição de obra, repasses Caixa e POC.

### 3.7 Desligamento — Repasse CAIXA (Colunas DJ-DO)

O "desligamento" é o momento em que a CAIXA libera o financiamento ao atingir a demanda mínima (30%):

```excel
DJ6 = CQ6                                 → % vendas acumulado
DK6 = IF(DJ6 > Premissas!C46, 1, 0)      → Atingiu > 30% de demanda mínima?
DL6 = IF(DK5=1, 0, 1)                    → Ainda abaixo da demanda mínima
DM6 = IF(DK6=1, IF(DL6=1, 1, 0), 0)     → Exatamente no mês de atingimento

DN6 = IF(DM6=1, SUM(DB$6:DB6), IF(DK6=1, DB6, 0))
     → No mês do atingimento: acumula todo o VGV LRG; depois: valor do mês
DO6 = IF(DM6=1, SUM(CU$6:CU6), IF(DK6=1, CU6, 0))
     → Mesma lógica para unidades LRG
```

### 3.8 Receitas Próprias — Aux_Parcelas (Colunas DQ-ED)

Busca da `Aux_Parcelas` via `SUMIFS` no mês `B6`:

```excel
DQ6 = SUMIFS(Aux_Parcelas!$625:$625, Aux_Parcelas!$2:$2, Tab_Mestre!$B6)
     → Soma parcelas de sinal+obra+pós para 2 Dorm. no mês B6

DT6 = SUM(DQ6:DS6)           → Total recursos próprios

DU6 = SUMIFS(Aux_Parcelas!GW:GW, Aux_Parcelas!A:A, Premissas!C2, Aux_Parcelas!B:B, B6)
     → Juros sobre parcelas (2 Dorm.)

DX6 = SUM(DU6:DW6)           → Total juros
EB6 = SUM(DY6:EA6)           → Total correção monetária
EC6 = DX6 + EB6              → Juros + Correção
ED6 = DT6 + EC6              → Carteira + Juros + Correção ← **ENTRADA CLIENTE**
```

### 3.9 Terreno a Receber (Colunas EF-EP)

```excel
EG6 = IFERROR(AM6 * Premissas!C49, 0)  → Terreno 2 Dorm = Unid. LRG × Avaliação/lote
EJ6 = SUM(EG6:EI6)                     → Total terreno do mês

EN6 = IF(EM6=1, SUM($EJ$6:EJ6), IF(EK6=1, EJ6, 0))
     → Acumula ou pega valor, mesma lógica do desligamento

EP6 = IFERROR(VLOOKUP(EO6, $B$5:$EN$210, $EP$3, 0), 0)
     → Terreno defasado: busca o EN do mês com defasagem (Premissas!C47)
```

### 3.10 Medição de Obra (Colunas ER-EY)

```excel
EU6 = IFERROR(VLOOKUP(ET6, Aux_Obras!B:AL, 37, 0), 0)  → % da curva de obra financeira
EV6 = EU6                                                → % obra acumulado
EW6 = EV6 * EW4                                          → Medição total
EX6 = EW6 * ER6                                          → Medição × % vendido
EY6 = EX6                                                → **VALOR A RECEBER DA CAIXA**
```

**Lógica:** Só se recebe a medição proporcional ao % vendido. Se vendeu 52% das unidades, recebe-se 52% da medição de obra.

### 3.11 Total de Entradas (FA)

```excel
FA6 = ED6 + EP6 + EY6
    = Carteira(Juros+Correção) + TerrenoCaixa + MediçãoObra
```

### 3.12 Deduções (FC-FG)

```excel
FC6 = (FA6 - DS6 - DW6 - EA6) * Premissas!C65  → RET sobre recursos próprios
FD6 = (DS6 + DW6 + EA6) * Premissas!E65        → RET sobre repasse CAIXA
FE6 = FA6 * Premissas!C66                       → ISS (sobre entrada total)
FF6 = (FA6 - EC6) * Premissas!C67              → Outras deduções
FG6 = SUM(FC6:FF6)                              → Total deduções
```

### 3.13 Saídas — Terreno (FI-FM)

```excel
FI6 = DRE!F27 * (FA6 / FA4) + Terreno!H3
     → Permuta financeira: % do VGV proporcional à entrada
FJ6 = GD6 * Premissas!F118    → Custo de permuta física
FL6 = IF(FK6=1, Premissas!F126, 0)  → Comissão terreno (após início)
FM6 = FI6 + FJ6 + FL6
```

### 3.14 Saídas — Obra (FW-GE)

```excel
FY6 = IFERROR(VLOOKUP(FX6, Aux_Obras!B2:AF41, 31, 0), 0)  → Curva de obra parcial (%)
FZ6 = FY6 * Premissas!F145          → Desembolso = % × custo total da obra
GB6 = Premissas!C151 / Premissas!C14 → Curva no lançamento (rateio)
GC6 = Premissas!F151 / Premissas!C14
GD6 = FY6 + GB6                      → Curva total (obra + lançamento)
```

### 3.15 Saldo Final — Operacional e Financeiro

Colunas `ID` (operacional) e `IU` (com PJ):

```excel
ID6 = DRE!F99 - DRE!F91
IU6 = saldo acumulado operacional + antecipação PJ
```

---

## 4. Aux_Parcelas — Simulador de Parcelas

**Aba:** `Aux_Parcelas` — 1877 linhas × 208 colunas

### Estrutura

- **Linha 2:** Meses 1 a 200 (`H2=1, I2=H2+1, ...`)
- **Colunas GW-GY:** Cálculo de juros, correção monetária e soma
- **Blocos por tipologia:**
  - 2 Dorm.: linhas ~3 a 627
  - 3 Dorm.: linhas ~628 a 1252
  - Lotes Com.: linhas ~1253 a 1877

### Funcionamento

Cada lote gera parcelas de sinal (2%), obra (9%) e pós-chave (9%) distribuídas ao longo dos meses. A Tab_Mestre consulta:

```excel
DQ6 = SUMIFS(Aux_Parcelas!$625:$625, Aux_Parcelas!$2:$2, Tab_Mestre!$B6)
```

Isto soma todas as parcelas (de todos os lotes) que vencem no mês `B6`.

As colunas **GW** (Juros Mensal) e **GX** (Correção Monetária) calculam o custo financeiro de cada parcela usando taxas definidas em Premissas.

### Referências cruzadas na Tab_Mestre

```excel
DU4 = Aux_Parcelas!GW625   → Total juros 2 Dorm.
DV4 = Aux_Parcelas!GW1250  → Total juros 3 Dorm.
DW4 = Aux_Parcelas!GW1875  → Total juros Lotes Com.
DX4 = Aux_Parcelas!GW1877  → Total juros geral
DY4 = Aux_Parcelas!GX625   → Total correção 2 Dorm.
DZ4 = Aux_Parcelas!GX1250  → Total correção 3 Dorm.
EA4 = Aux_Parcelas!GX1875  → Total correção Lotes Com.
EB4 = Aux_Parcelas!GX1877  → Total correção geral
EC4 = Aux_Parcelas!GY1877  → Total juros + correção
```

---

## 5. Aux_Obras — Curva de Obra

**Aba:** `Aux_Obras` — 48 linhas × 39 colunas

### Estrutura

Matriz de % de avanço físico por mês de obra (1 a 36). Linhas 2-5 são cabeçalhos, linhas 6-41 são os 36 meses de obra, linhas 38-43 tratam da retenção de 5%.

### Cálculo principal

```excel
AC2 = Premissas!C15                  → Prazo de obra (36 meses)
AF2 = Premissas!C152                 → Custo total da obra

C6:AA6 → % físico mensal (valores manuais somando 100%)

AC6 = INDEX(C6:AA6, MATCH(AC2, C2:AA2, 0))  → % do mês correto
AD6 = AC6                                     → % simples
AF6 = AC6 * AF2                               → % × Custo total = R$ desembolso
```

### Retenção de 5% finais

A coluna AL divide os 5% finais da obra em 3 parcelas:

```excel
AI6 = IF(AD6 < 95%, AC6, 0)           → 95% durante a obra
AJ6 = IF(B6 = AC2+2, (100% - SUM(AI6:AI41)) * 0.55, 0)  → 2,75% no mês obra+2
AK6 = IF(B6 = AC2+5, (100% - SUM(AI6:AI41)) * 0.45, 0)  → 2,25% no mês obra+5
AL6 = AI6 + AJ6 + AK6                 → Total da medição financeira
```

### Como a Tab_Mestre consulta

```excel
' Busca a % financeira acumulada da obra
EU6 = VLOOKUP(ET6, Aux_Obras!B:AL, 37, 0)
' Coluna 37 = coluna AK (Obra % Financ. Acum.)
```

---

## 6. Terreno — Pagamento do Terreno

**Aba:** `Terreno` — 207 linhas

Espelha a Tab_Mestre (colunas B, C, F, H, I) com fórmulas de referência direta:

```excel
B3 = Tab_Mestre!B6                    → Mês relativo
C3 = Tab_Mestre!I6                    → Data
F3 = Tab_Mestre!L6                    → Eventos
I3 = H3                                → Terreno a pagar
```

O terreno de R\$ 10.000.000 é parcelado em 36 meses durante a obra a R\$ 277.777/mês:

```excel
H1 = DRE!F28                          → Valor total do terreno (10.000.000)
H27:H62 → 277.777 por mês durante 36 meses de obra
```

---

## 7. Fluxo e DRE — Consolidação Mensal/Anual

**Aba:** `Fluxo e DRE` — 99 linhas × 162 colunas

Consolida os dados mensais da Tab_Mestre em somas anuais e totais.

### Estrutura da aba

| Linhas | Seção |
|---|---|
| 1–4 | Cabeçalhos (meses, anos, eventos) |
| 5–8 | Vendas e % acumulado |
| 9–10 | Obra % |
| 12–49 | **Fluxo de Caixa** (entradas, saídas, saldo) |
| 48–55 | Antecipação PJ |
| 57–66 | Total entradas/saídas PJ |
| 68–99 | DRE consolidado |

### Fórmulas principais (coluna C = TOTAL)

```excel
C15 (Carteira)         = SUM(E15:Q15)
C17 (Correção Monet.)  = SUM(E17:Q17)
C18 (Recurso Próprio)  = SUM(E18:Q18)
C19 (Terreno)          = SUM(E19:Q19)
C20 (Medição)          = SUM(E20:Q20)
C21 (Total Entradas)   = SUM(C18:C20)

C31 (TOTAL Custos)     = SUM(C23:C30)
C39 (TOTAL Despesas)   = SUM(C32:C38)
C42 (Saídas)           = C31 + C39 + C41

C44 (FCO)              = C21 + C42         → Fluxo de Caixa Operacional
C46 (Saldo Operacional)= C44 acumulado

C55 (Total Entradas PJ)= C49 + C53
C68 (Saldo Final)      = C44 + C55 + C66
```

### Seção DRE (linhas 71–99)

```excel
C71 (Receita de Vendas)    = SUM(E71:Q71)
C72 (Deduções)             = SUM(E72:Q72)
C73 (Receita Líquida ROL)  = C71 + C72
C76 (Custo CSP)            = SUM(E76:Q76)
C77 (Lucro Bruto)          = C73 + C76
C78 (Margem Bruta)         = IFERROR(C77/C73, 0)
C87 (Despesas Totais)      = SUM(E87:Q87)
C88 (EBITDA)               = C77 + C87
C92 (EBIT / LAIR)          = C88 + C92
C93 (Lucro Líquido)        = C92 + C95
C94 (Margem Líquida)       = IFERROR(C93/C73, 0)
C96 (Margem s/ VGV Venda)  = C93 + C95
C97 (Margem s/ VGV LRG)    = IFERROR(C96/C73, 0)
```

---

## 8. DRE — Demonstração de Resultado (POC)

**Aba:** `DRE` — 103 linhas × 36 colunas

Aplica o método **POC (Percentage of Completion)** contábil. Divide o resultado em 4 tipologias (CONSOLIDADO, 2 Dorm., 3 Dorm., Lotes).

### Premissas do DRE (linhas 1–46)

```excel
F9  = J9 + N9 + R9                              → Total de unidades
F12 = J12 + N12 + R12                            → VGV Venda total
F14 = J14 + N14 + R14                            → Permuta física (unidades)
F15 = J15 + N15 + R15                            → VGV terrenista
F18 = F9 - F14                                   → Unidades LRG
F19 = F12 - F15                                  → VGV LRG (s/ permuta)
F21 = IFERROR(F22/F9, 0)                        → % terreno por lote
F24 = F19 - F22                                  → VGV LRG (s/ $ terrenista) = 192.400.000
```

### Custos do DRE (linhas 48–76)

```
F50 (Receita Vendas)         = VGV LRG = 192.400.000
F51 (Juros + Correção)       = Tab_Mestre!DU4 + Tab_Mestre!DY4 + ...
F52 (Total Carteira)         = F50 + F51
F53 (Deságio/Desconto)       = -F52 * %Deságio * %Financiamento

F58 (Custo Total)            = F52 + F56 (CSP + Provisão + Despesas)
F60 (Deduções Obra)          = custos de obra + juros + taxa
F63 (Deduções IRPJ/CSLL)     = soma de PIS, COFINS, CSLL
F71 (Total Deduções)         = SUM(F65:F70)
F74 (Resultado antes IR)     = F63 + F64 + F71 + F72 + F73
F76 (Custo + Deduções)       = F58 + F74
```

### Resultado (linhas 78–99)

```
F78 (Custo Obra)             = -% * VGV LRG
F86 (Total Deduções Finais)  = SUM(F78:F84)
F88 (Resultado Operacional)  = F76 + F86
F92 (Despesas Financeiras)   = juros bancários
F95 (LAIR)                   = F88 + F92
F99 (Lucro Líquido)          = F95 + F97
F100 (Margem Líquida / VGV)  = IFERROR(F99 / F19, 0)
F101 (Margem Líquida / LRG)  = IFERROR(F99 / F24, 0)
```

---

## 9. Resumo — TIR, Payback e Exposição

**Aba:** `Resumo` — 63 linhas

### Dados do Projeto (linhas 4–7)

Referências diretas a Premissas:
```excel
D4 = Premissas!C4       → Nome do Projeto
D5 = Premissas!C5       → Nome Comercial
D6 = Premissas!C7       → Cidade
D7 = Premissas!C8       → Estado
Q4 = Tab_Mestre!I6      → Início Incorporação
Q5 = Premissas!C12      → Lançamento
V5 = Premissas!F14      → Início de Obra
V6 = Premissas!F15      → Término de Obra
V7 = Premissas!F16      → Fim do Pós Obra
```

### Exposição Máxima (linhas 42–47)

```excel
D42 = SMALL(Tab_Mestre!ID6:ID210, 1)
     → Menor saldo operacional = Exposição máxima

D43 = INDEX(Tab_Mestre!I6:I210, MATCH(D42, Tab_Mestre!ID6:ID210, 0))
     → Data da exposição máxima

D44 = (D43 - Q4) / 30         → Meses desde o início
D45 = (D43 - Q5) / 30         → Meses após o lançamento
D46 = -D42 / D17              → % Exposição / VGV Venda
D47 = -D42 / D19              → % Exposição / VGV LRG
```

### Payback (linhas 49–51)

```excel
D49 = INDEX(Tab_Mestre!I6:I210, MATCH(0, Tab_Mestre!IE6:IE210, 0))
     → Data do payback (quando o saldo zera/vira positivo)

D50 = (D49 - Q4) / 30         → Meses desde o início
D51 = (D49 - Q5) / 30         → Meses após o lançamento
```

### TIR — Taxa Interna de Retorno (linhas 61–62)

```excel
D61 = XIRR(Tab_Mestre!ID6:ID99, Tab_Mestre!I6:I99)
     → TIR a.a. via XIRR (fluxos operacionais + datas)

D62 = ((1 + D61)^(1/12)) - 1  → TIR a.m.
```

---

## 10. Glossário de Termos

| Termo | Significado |
|---|---|
| **VGV** | Valor Geral de Vendas — receita bruta total |
| **LRG** | Líquido de Repasse Gerencial — unidades do incorporador |
| **Permuta Física** | Pagamento do terreno com unidades prontas |
| **Permuta Financeira** | Pagamento do terreno atrelado ao % do VGV |
| **Desligamento** | Momento em que a CAIXA libera o financiamento (30% vendido) |
| **POC** | Percentage of Completion — método contábil de reconhecimento de receita |
| **FCO** | Fluxo de Caixa Operacional |
| **TIR** | Taxa Interna de Retorno (IRR) |
| **Payback** | Momento em que o saldo acumulado zera (retorno do investimento) |
| **Exposição Máxima** | Maior saldo negativo acumulado (pior momento de caixa) |
| **Carteira** | Recebíveis de clientes (sinal + parcelas) |
| **Medição** | Valor a receber da CAIXA por obra executada |
| **Stand** | Stand de vendas + despesas comerciais do plantão |
| **PJ** | Pessoa Jurídica — antecipação de recursos via financiamento |
| **SPE** | Sociedade de Propósito Específico — veículo do projeto |
