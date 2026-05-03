# Catálogo de Variáveis dos Services de Viabilidade

**Data:** 2026-04-29 (atualizado após remoção do `config/viabilidade.php` e refatoração completa do fluxo mensal)  
**Escopo analisado:** `app/Services/Tenant/Viabilidade/v1` e `app/Services/Tenant/Viabilidade/v1/calculos`, `app/Models/Tenant/PremissasViabilidade.php`, `database/migrations/tenant` e `database/seeders/Tenant`.

## Objetivo

Este documento cataloga as variáveis de negócio usadas pelos services de viabilidade, com foco em:

- quantidades de produtos e unidades;
- valores unitários, VGV e custos totais;
- prazos de incorporação, lançamento, obra e pós-obra;
- taxas, juros, multas implícitas e despesas financeiras;
- percentuais comerciais, tributários e operacionais;
- parâmetros configuráveis vindos do usuário, do produto ou do `config`.

## Convenções de Classificação

- **Origem**
  - `usuário`: valor informado na `Viabilidade`.
  - `produto`: valor específico de cada produto do terreno.
  - `config`: **removido**. O arquivo `config/viabilidade.php` foi deletado. Todos os valores vêm da tabela `premissas_viabilidade` no banco (gerida por `PremissasViabilidadeService` e semeada por `PremissasViabilidadeSeeder`).
  - `calculada`: derivada em tempo de execução.
  - `constante`: valor codificado no service.
- **Obrigatoriedade**
  - `obrigatória`: ausência impede execução ou invalida o cálculo.
  - `opcional`: existe fallback, default ou cálculo defensivo.
- **Natureza**
  - `entrada`: premissa recebida do usuário, do produto ou da tabela `premissas_viabilidade`.
  - `calculada`: produzida por fórmula.
  - `estado`: variável mutável do contexto durante o pipeline.

## Regras Gerais Encontradas

- `terreno_id` é a única entrada explicitamente obrigatória em `ViabilidadeService::validarDados()`.
- Para o cálculo completo rodar, também são obrigatórios, na prática:
  - ao menos um produto válido no terreno;
  - `totalUnidades > 0`;
  - `vgv > 0`;
  - `curva_vendas` preenchida em todos os produtos.
- Quase todas as premissas financeiras da `Viabilidade` são opcionais porque possuem fallback na tabela `premissas_viabilidade` via `PremissasViabilidadeService::resolverDefaults()`.
- Os parâmetros por produto também são opcionais, pois `ProdutosProcessor::mesclarParametros()` aplica fallback para o valor global.
- O perfil de financiamento altera profundamente o comportamento:
  - `cef`: habilita `Recurso Terrenos`, `Medição Obra`, taxas CEF e demanda mínima.
  - `proprio`: desliga receitas CEF e ativa tratamento de inadimplência/atraso.

## Fluxo de Dados

1. `ViabilidadeUnificadoService::montarParametros()` consolida premissas globais em `$params`.
2. `ProdutosProcessor::processar()` transforma produtos do terreno em `$dadosProdutos`.
3. `ProdutosProcessor::mesclarParametros()` sobrescreve parte de `$params` com médias ponderadas/valores de produto.
4. `FluxoMensalCalculator` gera:
   - calendário em `$datas`;
   - estado mutável em `$ctx`;
   - receitas, despesas, fluxo mensal, DRE, POC e indicadores.

## 1. Variáveis Globais de Entrada (`$params`)

### 1.1 Financeiras e Tributárias

| Variável | Tipo | Origem | Default | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|---|
| `percentualImpostos` | `float` | usuário/config | `4.5%` agregado (`pis_cofins + iss + outros_impostos`) | `montarParametros()`, `ImpostosService::calcularTributosMensais()` | opcional; entrada | Soma dos três percentuais tributários. |
| `percentualPisCofins` | `float` | usuário/config | `4.0%` | `montarParametros()`, `DespesasCalculator::calcularDeducoesMensais()` | opcional; entrada | RET/LP sobre receita bruta mensal no fluxo; também usado no DRE. |
| `percentualIss` | `float` | usuário/config | `0.0%` | `montarParametros()`, `DespesasCalculator::calcularDeducoesMensais()` | opcional; entrada | ISS sobre receita bruta mensal no fluxo; usado na DRE. |
| `percentualOutrosImpostos` | `float` | usuário/config | `0.5%` | `montarParametros()`, `DespesasCalculator::calcularDeducoesMensais()` | opcional; entrada | Outras deduções sobre base sem juros/correção no fluxo; usado na DRE. |
| `percentualComissao` | `float` | usuário/config | `1.0%` | `montarParametros()`, `DespesasCalculator::calcularComissaoCorretorTerreno()`, `DreCalculator::calcularCustosDiretosDre()` | opcional; entrada | Comissão do corretor do terreno; no fluxo mensal é parcelada em `parcelamentoComissaoTerreno` meses. |
| `parceriaVgv` | `float` | usuário/config | `0.0%` | `montarParametros()`, `DespesasCalculator::calcularPagamentoTerreno()`, `DreCalculator::calcularCustosDiretosDre()` | opcional; entrada | Define pagamento variável ao terrenista sobre entradas do mês. |
| `parcelamentoComissaoTerreno` | `int` | config | `18` | `montarParametros()`, `DespesasCalculator::calcularComissaoCorretorTerreno()` | opcional; entrada | Parcelas da comissão do corretor do terreno a partir do lançamento. |
| `infraNaoIncidente` | `float` | usuário/config | `1.0%` | `montarParametros()`, `ProdutosProcessor::processar()` | opcional; entrada | Gera `custoNaoIncidente` sobre o `vgv`. |
| `percentualIncorporacao` | `float` | usuário/config | `1.0%` | `montarParametros()`, `DespesasCalculator::calcularCustosDiretos()`, `DreCalculator::calcularCustosDiretosDre()` | opcional; entrada | Base para custo de incorporação. |
| `custoAreaComum` | `float` | usuário/config | `0.00` no `config`, `2000.00` no atributo default do model | `montarParametros()`, `DespesasCalculator::custoObraTotal()`, `DreCalculator::calcularCustosDiretosDre()` | opcional; entrada | Valor absoluto adicionado ao custo total da obra; há divergência entre default do config e default do model. |
| `percentualContrapartidas` | `float` | usuário/config | `0.0%` | `montarParametros()`, `DespesasCalculator::calcularCustosDiretos()`, `DreCalculator::calcularCustosDiretosDre()` | opcional; entrada | Incide sobre `vgv`. |
| `percentualSeguros` | `float` | usuário/config | `0.5%` | `montarParametros()`, `DreCalculator::calcularSegurosPorTipologia()`, `DespesasCalculator::calcularSegurosMensal()` | opcional; entrada | Rateado na obra; base varia entre lote e unidade vertical. |
| `percentualAssistenciaTecnica` | `float` | usuário/config | `1.0%` | `montarParametros()`, `DespesasCalculator::calcularAssistenciaTecnicaMensal()`, `DreCalculator::calcularCustosDiretosDre()` | opcional; entrada | Aplicado sobre base de obra/contrapartidas/área comum. |
| `percentualDespesasComerciais` | `float` | usuário/config | `5.0%` | `montarParametros()`, `DreCalculator::calcularDespesasComerciaisDetalhadas()` | opcional; entrada | Na DRE entra como percentual total sobre `vgvSemUnidPermutas`. |
| `percentualMarketing` | `float` | usuário/config | `1.0%` | `montarParametros()`, `DespesasCalculator::calcularMarketingMensal()`, `DreCalculator::calcularMarketingDetalhado()` | opcional; entrada | Base total de marketing. |
| `custoItbiIptu` | `float` | usuário/config | `1.1%` | `montarParametros()`, `DespesasCalculator::calcular()`, `DreCalculator::calcularItbiPorTipologia()` | opcional; entrada | No fluxo mensal incide por unidade vendida; na DRE incide por tipologia não-lote. |
| `custoRegistro` | `float` | usuário/config | `2500.00` | `montarParametros()`, `DespesasCalculator::calcular()`, `DreCalculator::calcularRegistroPorTipologia()` | opcional; entrada | Custo fixo por unidade não-lote. |
| `custoContratacaoCef` | `float` | usuário/config | `0` | `montarParametros()`, `DespesasCalculator::calcular()` | opcional; entrada | Taxa única no lançamento quando perfil é CEF. |
| `custoMedicaoCef` | `float` | usuário/config | `0` | `montarParametros()`, `DespesasCalculator::calcular()` | opcional; entrada | Taxa mensal durante `Obra` no fluxo mensal. |
| `custoContratosCef` | `float` | usuário/config | `300.00` | `montarParametros()`, `DespesasCalculator::calcular()`, `DreCalculator::calcularContratosCef()` | opcional; entrada | Multiplicado por unidades vendidas ou por total de unidades na DRE. |
| `percentualProdutosCef` | `float` | usuário/config | `0.5%` | `montarParametros()`, `DespesasCalculator::calcular()`, `DreCalculator::calcularProdutosCefPorTipologia()` | opcional; entrada | Só vale quando `perfilFinanciamento = CEF`. |
| `percentualOutrasDespesasFinanceiras` | `float` | usuário/config | `0.3%` | `montarParametros()`, `DespesasCalculator::calcular()`, `DreCalculator::calcular()` | opcional; entrada | Incide sobre receita do mês no fluxo e sobre receita de vendas na DRE. |
| `taxaJurosPj` | `float` | produto/config | `10.5% a.a.` | `ProdutosProcessor::mesclarParametros()`, `ImpostosService::calcularJurosPJ()`, `DreCalculator::calcular()`, `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | opcional; entrada | Pode ser sobrescrita por média ponderada dos produtos. |
| `percentualAntecipacaoPj` | `float` | usuário/config | `10.0%` | `montarParametros()`, `ImpostosService::calcularJurosPJ()`, `DreCalculator::calcular()`, `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | opcional; entrada | Percentual da obra antecipado via PJ. |
| `carenciaPjMeses` | `int` | produto/config | `6` | `ProdutosProcessor::mesclarParametros()`, `ImpostosService::calcularJurosPJ()`, `DreCalculator::calcular()`, `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | opcional; entrada | Pode vir de `pj_carencia_pos_obra` do produto. |
| `amortizacaoPjParcelas` | `int` | produto/config | `18` | `ProdutosProcessor::mesclarParametros()`, `ImpostosService::calcularJurosPJ()`, `DreCalculator::calcular()`, `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | opcional; entrada | Pode vir de `pj_qtde_parcelas` do produto. |
| `taxaExposicaoAplicada` | `float` | usuário/config | `12.5% a.a.` | `montarParametros()`, `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | opcional; entrada | Convertida para taxa mensal de exposição. |
| `aporteAdicionalMensal` | `float` | usuário/config | `0.0` | `montarParametros()`, `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | opcional; entrada | Aporte durante obra. |
| `devolucaoAportePercentual` | `float` | usuário/config | `20.0%` | `montarParametros()`, `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | opcional; entrada | Devolução distribuída no pós-obra. |
| `distribuicaoLucrosPercentualObra` | `float` | usuário/config | `100.0%` | `montarParametros()` | opcional; entrada | Preparada, sem consumo direto identificado nos services analisados. |
| `compraTerreno` | `float` | usuário/config | `0.0` | `montarParametros()`, `DespesasCalculator::calcularCompraTerrenoMensal()`, `DespesasCalculator::calcularCustoTerreno()`, `DreCalculator::calcularCompraTerreno()`, `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | opcional; entrada | No fluxo, rateada linearmente durante a obra no bloco de pagamento do terreno. |
| `porcentagemLoteProprietario` | `float` | usuário/config | `10.0%` fallback interno | `montarParametros()`, `DreCalculator::calcularCustoProprietario()` | opcional; entrada | Estima lotes adicionais do proprietário. |

### 1.2 Operacionais e Comerciais

| Variável | Tipo | Origem | Default | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|---|
| `canteiroMensal` | `float` | usuário/config | `85715.00` | `montarParametros()`, `DespesasCalculator::calcularCustosDiretos()`, `DreCalculator::calcularCustosDiretosDre()` | opcional; entrada | Custo fixo mensal de canteiro durante obra. |
| `moAdministrativa` | `float` | usuário/config | `62502.00` | `montarParametros()`, `DespesasCalculator::calcularCustosDiretos()`, `DreCalculator::calcularCustosDiretosDre()` | opcional; entrada | Despesa mensal administrativa na obra. |
| `standVendas` | `float` | usuário/config | `0.0` | `montarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Parcelado ao longo do lançamento. |
| `mobiliaDecoracao` | `float` | usuário/config | `90000.00` | `montarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Distribuído antes do lançamento. |
| `ajudaCustoGerente` | `float` | usuário/config | `5000.00` | `montarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Vigora de lançamento até fim da obra. |
| `ajudaCustoGerenteRegional` | `float` | usuário/config | `2733.00` | `montarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Mesmo intervalo do gerente. |
| `reembolsoLogistica` | `float` | usuário/config | `5000.00` | `montarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Mesmo intervalo do gerente. |
| `bonusCca` | `float` | usuário/config | `350.00` | `montarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Multiplicado por unidades vendidas no mês. |
| `bonusGerente` | `float` | usuário/config | `0.3%` | `montarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Incide sobre valor vendido do mês. |
| `bonusGerenteRegional` | `float` | usuário/config | `0.12%` | `montarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Incide sobre valor vendido do mês. |
| `bonusCredito` | `float` | usuário/config | `0.05%` | `montarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Incide sobre valor vendido do mês. |
| `bonusGestorComercial` | `float` | usuário/config | `0.05%` | `montarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Incide sobre valor vendido do mês. |
| `pagamentoComissaoDesligamento` | `float` | usuário/config | `50.0%` | `montarParametros()`, `DespesasCalculator::calcularComissaoDesligamentoMensal()` | opcional; entrada | Distribuído em `parcelamentoComissaoMeses`. |
| `parcelamentoComissaoMeses` | `int` | usuário/config | `18` | `montarParametros()`, `DespesasCalculator::calcularComissaoDesligamentoMensal()` | opcional; entrada | Controla o parcelamento da comissão de desligamento. |
| `gastosMensaisStand` | `float` | produto/fallback global | `0.0001` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Percentual mensal sobre `vgvSemPermuta`; vem do produto. |
| `comissaoHousePercentual` | `float` | produto/fallback global | `3.0%` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Composição da taxa média de comissão. |
| `percentualVendasHouse` | `float` | produto/fallback global | `50.0%` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Fração das vendas atribuídas ao canal house. |
| `comissaoImobiliariasPercentual` | `float` | fallback fixo | `3.5%` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Hoje não é lida do produto na mesclagem; usa fallback padrão. |
| `pagamentoComissaoVenda` | `float` | produto/fallback global | `50.0%` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Percentual da comissão pago no momento da venda. |
| `marketingLancamento` | `float` | produto/fallback global | `25.0%` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularMarketingMensal()`, `DreCalculator::calcularMarketingDetalhado()` | opcional; entrada | Divide marketing em fixo de lançamento e variável. |
| `marketingInicioAntesLancamento` | `int` | produto/fallback global | `3` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularDespesasComerciaisMensais()` | opcional; entrada | Meses anteriores ao lançamento para mobiliário/decor. |

### 1.3 Temporais e Perfil

| Variável | Tipo | Origem | Default | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|---|
| `mesesObra` | `int` | usuário/config | `36` | `montarParametros()`, `FluxoMensalCalculator::calcularPeriodos()`, `CurvaService`, `DespesasCalculator`, `IndicadoresCalculator`, `DreCalculator` | opcional; entrada | Duração central do projeto. |
| `mesesIncorporacao` | `int` | usuário/config | `18` | `montarParametros()`, `FluxoMensalCalculator::calcularPeriodos()`, `DespesasCalculator::calcularCustosDiretos()` | opcional; entrada | Meses antes do lançamento. |
| `mesesLancamento` | `int` | usuário/config | `6` | `montarParametros()`, `FluxoMensalCalculator::calcularPeriodos()`, pré-cálculo de recebíveis, despesas comerciais, marketing | opcional; entrada | Também controla distribuição do sinal na venda durante lançamento. |
| `mesesEntrega` | `int` | config | `1` | `montarParametros()` | opcional; constante/config | Atualmente usado apenas para composição sem lógica extensa própria. |
| `mesesPosObra` | `int` | config | `60` | `montarParametros()`, `FluxoMensalCalculator::calcularPeriodos()`, `IndicadoresCalculator` | opcional; entrada/config | Intervalo pós-entrega. |
| `perfilFinanciamento` | `enum PerfilFinanciamento` | usuário/config | `cef` | `montarParametros()`, praticamente todo o pipeline | opcional; entrada | Chave de bifurcação do cálculo. |
| `dataLancamento` | `Carbon` | usuário/config lógica | `now()+2 anos` | `montarParametros()`, `FluxoMensalCalculator::calcularPeriodos()` | opcional; entrada | Âncora temporal de todo o fluxo. |
| `inadimplencia` | `float` | config | `0.10` | `montarParametros()`, `FluxoMensalCalculator::aplicarInadimplencia()` | opcional; entrada/config | Só afeta perfil próprio. |
| `atrasoMeses` | `int` | config | `2` | `montarParametros()`, `FluxoMensalCalculator::aplicarInadimplencia()` | opcional; entrada/config | Define quando a parcela atrasada volta ao caixa. |
| `taxaPerda` | `float` | config | `0.02` | `montarParametros()`, `FluxoMensalCalculator::aplicarInadimplencia()` | opcional; entrada/config | Percentual irrecuperável da inadimplência. |

### 1.4 Alocação de Incorporação e Assistência

| Variável | Tipo | Origem | Default | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|---|
| `assistenciaTecnicaCurva` | `array<float>` | produto/fallback global | `[50,20,10,10,10]` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularAssistenciaTecnicaMensal()` | opcional; entrada | Curva anual de provisão no pós-obra. |
| `incorporacaoRi` | `float` | produto/fallback global | `30.0%` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularCustosDiretos()`, `DreCalculator::calcular()` | opcional; entrada | Lançado uma única vez no último mês da incorporação. |
| `incorporacaoEntrega` | `float` | produto/fallback global | `15.0%` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularCustosDiretos()`, `DreCalculator::calcular()` | opcional; entrada | Lançado uma única vez no mês de entrega. |
| `incorporacaoAteLancamento` | `float` | produto/fallback global | `80.0%` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularCustosDiretos()` | opcional; entrada | Rateado em `incorporacao + lancamento` meses; entra nos períodos de incorporação e lançamento. |
| `obraAteLancamento` | `float` | produto/fallback global | `1.0%` | `ProdutosProcessor::mesclarParametros()`, `DespesasCalculator::calcularCustosDiretos()`, `DespesasCalculator::calcularPagamentoPermutaFisicaTerreno()`, `CurvaService::getCurvaFinanceiraMedicaoParaPrazo()` | opcional; entrada | Reserva fatia da obra para o período de lançamento; usado também na permuta física do terreno e na curva financeira de medição. |
| `custoMedicaoContratacao` | `float` | produto/fallback global | `0` | `ProdutosProcessor::mesclarParametros()`, `DreCalculator::calcularTxMedicao()` | opcional; entrada | Nome ambíguo: é preenchido com `custo_contratacao_cef` do produto, mas usado na DRE como taxa de medição/contratação agregada. |

## 2. Variáveis de Produto Processadas (`$dadosProdutos['produtos'][]`)

### 2.1 Campos Base do Produto

| Variável | Tipo | Origem | Default | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|---|
| `id` | `int` | produto | sem fallback | `ProdutosProcessor::processar()` | obrigatória; entrada | Identificador do produto. |
| `terreno_produto_id` | `int` | produto | sem fallback | `ProdutosProcessor::processar()` | obrigatória; entrada | Identificador da relação com o terreno; usado para customização. |
| `nome` | `string` | produto | `''` implícito | `ProdutosProcessor`, `CurvaService`, `DreCalculator` | opcional; entrada | Também define heurística de tipologia de lote. |
| `preco` | `float` | custom produto / terreno produto | `0` | praticamente todo o pipeline | obrigatória na prática; entrada | Se `0`, pode invalidar o `vgv`. |
| `metragem` | `float` | produto | `0` | `ProdutosProcessor`, `DreCalculator` | opcional; entrada | Base de custo de construção. |
| `quantidade_unidades` | `int` | custom produto / terreno produto | `1` | todo o pipeline | obrigatória na prática; entrada | Define `totalUnidades`, vendas e VGV. |
| `custo_m2` | `float` | custom produto / produto | `0` | `ProdutosProcessor`, `DreCalculator` | opcional; entrada | Custo de habitação por m². |
| `custo_infraestrutura` | `float` | custom produto / produto | `0` | `ProdutosProcessor`, `DreCalculator` | opcional; entrada | Custo de infraestrutura por unidade. |
| `vgv_produto` | `float` | calculada | `preco * quantidade_unidades` | `ProdutosProcessor`, `ImpostosService`, `DreCalculator` | calculada | Base de rateio por produto. |
| `avaliacao_lotesCef` | `float` | produto | `0` | `ReceitasCalculator::calcularRtMesVenda()`, `ReceitasCalculator::inicializarValorMedicaoTotal()` | opcional; entrada | Pode ser valor absoluto ou percentual do preço se `<= 1`. |
| `permutas` | `int` | custom produto / terreno produto | `0` | todo o pipeline | opcional; entrada | Reduz unidades da construtora e altera VGV líquido. |
| `pgto_por_lote` | `float` | custom produto / terreno produto | `0` | `ProdutosProcessor`, `DreCalculator::calcularBaseSemTerrenistaProduto()` | opcional; entrada | Desconto do terrenista por unidade. |
| `demanda_minCef` | `float` | produto | `0` | `FluxoMensalCalculator::inicializarCachesCef()`, `ReceitasCalculator` | opcional; entrada | Percentual usado para compor demanda mínima acumulada. |
| `defasagem_pgtoTerreno` | `int` | produto | `1` | `ReceitasCalculator::calcularRtMesProduto()` | opcional; entrada | Meses de defasagem entre liberação e recebimento do recurso terrenos. |
| `curva_vendas` | `array` ou JSON | produto | `[]` | `CurvaService::extrairCurva()`, recebíveis, RT, medição | obrigatória na prática; entrada | Se vazia, `validarCurvasObrigatorias()` bloqueia a execução. |
| `baloes_anuais` | `array` | produto | `[]` | `FluxoMensalCalculator::preCalcularRecebiveisProprio()` | opcional; entrada | Só usado no perfil próprio. |
| `balao_entrega_modo` | `string|float` | produto | `saldo_restante` | `FluxoMensalCalculator::preCalcularRecebiveisProprio()` | opcional; entrada | Pode usar saldo restante ou percentual explícito. |

### 2.2 Impostos e Comercial por Produto

| Variável | Tipo | Origem | Default | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|---|
| `imposto_tributos` | `float` | produto | `0` | `ProdutosProcessor`, `ImpostosService` | opcional; entrada | Convertido para decimal (`/100`). |
| `imposto_iss` | `float` | produto | `0` | `ProdutosProcessor`, `ImpostosService` | opcional; entrada | Convertido para decimal (`/100`). |
| `imposto_outros` | `float` | produto | `0` | `ProdutosProcessor`, `ImpostosService` | opcional; entrada | Convertido para decimal (`/100`). |
| `gastos_mensais_stand` | `float` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Alimenta o parâmetro global mesclado. |
| `comissao_house` | `float` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Convertido para decimal. |
| `porcentagem_comissao_house` | `float` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Convertido para decimal. |
| `porcentagem_comissao_imobs` | `float` | produto | `0` | armazenado em `ProdutosProcessor` | opcional; entrada | Hoje não é lido na mesclagem global. |
| `pagto_comissao_venda` | `float` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Convertido para decimal. |
| `marketing_lancamento` | `float` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Convertido para decimal. |
| `marketing_antes_lancamento` | `int` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Meses antes do lançamento. |
| `custo_contratacao_cef` | `float` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Alimenta `custoMedicaoContratacao`. |
| `pj_taxa_juros` | `float` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Convertido para decimal. |
| `pj_carencia_pos_obra` | `int` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Carência do PJ. |
| `pj_qtde_parcelas` | `int` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Parcelas de amortização do PJ. |
| `assist_tecnica_curva` | `array<float>` | produto | `[50,20,10,10,10]` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Vem dos cinco campos `assist_tecnica1..5`. |
| `incorp_ri` | `float` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Convertido para decimal. |
| `incorp_entrega` | `float` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Convertido para decimal. |
| `incorp_ate_lancamento` | `float` | produto | `0` | `ProdutosProcessor::mesclarParametros()` | opcional; entrada | Convertido para decimal. |

### 2.3 Subestrutura `financeiro`

| Variável | Tipo | Origem | Default | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|---|
| `financeiro.sinal` | `float` | produto | `0` | recebíveis CEF/próprio | opcional; entrada | Percentual nominal do sinal. |
| `financeiro.parcela_obra` | `float` | produto | `0` | recebíveis CEF | opcional; entrada | Percentual destinado à obra. |
| `financeiro.parcela_posChave` | `float` | produto | `0` | recebíveis CEF | opcional; entrada | Percentual do pós-chave. |
| `financeiro.qtde_parcelas_posChave` | `int` | produto | `0` | recebíveis CEF | opcional; entrada | Fallback local para `36`. |
| `financeiro.juros_mensalSinal` | `float` | produto | `0` | armazenado | opcional; entrada | Não consumido diretamente nos services analisados. |
| `financeiro.juros_mensalObra` | `float` | produto | `0` | armazenado | opcional; entrada | Não consumido diretamente nos services analisados. |
| `financeiro.juros_mensalPosChave` | `float` | produto | `0` | recebíveis CEF | opcional; entrada | Juros mensais do pós-chave. |
| `financeiro.correcao_anualSinal` | `float` | produto | `0` | armazenado | opcional; entrada | Não consumido diretamente nos services analisados. |
| `financeiro.correcao_anualObra` | `float` | produto | `0` | recebíveis CEF | opcional; entrada | Base de `r_obra`. |
| `financeiro.correcao_anualPosChave` | `float` | produto | `0` | recebíveis CEF | opcional; entrada | Base de `r_pos`. |
| `financeiro.imposto_pis` | `float` | calculada | `0` | `ProdutosProcessor`, `ImpostosService::calcularImpostosDre()` | calculada | Pré-cálculo por produto. |
| `financeiro.imposto_cofins` | `float` | calculada | `0` | `ProdutosProcessor`, `ImpostosService::calcularImpostosDre()` | calculada | Pré-cálculo por produto. |
| `financeiro.imposto_iss` | `float` | calculada | `0` | `ProdutosProcessor`, `ImpostosService::calcularImpostosDre()` | calculada | Pré-cálculo por produto. |
| `financeiro.outras_deducoes` | `float` | calculada | `0` | `ProdutosProcessor`, `ImpostosService::calcularImpostosDre()` | calculada | Pré-cálculo por produto. |
| `financeiro.irrpj` | `float` | calculada | `0` | `ProdutosProcessor`, `ImpostosService::calcularImpostosDre()` | calculada | Observação: a chave ficou nomeada `irrpj` no código. |
| `financeiro.csll` | `float` | calculada | `0` | `ProdutosProcessor`, `ImpostosService::calcularImpostosDre()` | calculada | Pré-cálculo por produto. |

## 3. Variáveis Agregadas de Produto (`$dadosProdutos`)

| Variável | Tipo | Origem | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|
| `vgv` | `float` | calculada | `ProdutosProcessor`, `DespesasCalculator`, `DreCalculator` | calculada | Soma de `preco * quantidade_unidades`. |
| `areaConstruida` | `float` | calculada | `ProdutosProcessor`, retorno final | calculada | Soma de `metragem * unidades`. |
| `custoObraHabitacao` | `float` | calculada | `ProdutosProcessor`, `DreCalculator`, `IndicadoresCalculator` | calculada | `custo_m2 * metragem * unidadesConstrutora`. |
| `custoInfraestrutura` | `float` | calculada | `ProdutosProcessor`, `DreCalculator`, `IndicadoresCalculator` | calculada | `custo_infraestrutura * unidadesConstrutora`. |
| `totalUnidades` | `int` | calculada | todo o pipeline | calculada | Soma bruta de unidades. |
| `permutas` | `int` | calculada | todo o pipeline | calculada | Soma de unidades em permuta. |
| `dataInicio` | `Carbon` | calculada | `ProdutosProcessor`, `FluxoMensalCalculator::calcularPeriodos()` | calculada | Recebe `dataLancamento`. |
| `vgvSemUnidPermutas` | `float` | calculada | `ProdutosProcessor`, despesas/marketing/DRE | calculada | Exclui permutas físicas. |
| `vgvSemValorTerrenista` | `float` | calculada | `ProdutosProcessor`, `DreCalculator` | calculada | Exclui pagamento por lote/terrenista. |
| `correcaoSobreVgv` | `float` | calculada | `ProdutosProcessor`, atualizado em `FluxoMensalCalculator` | calculada | Inicialmente zerado; depois recebe juros/correções do fluxo (substituiu `variavelCorrecao`). |
| `vgvComCorrecao` | `float` | calculada | `ProdutosProcessor`, `DespesasCalculator::calcularCustoTerreno()` | calculada | `vgvSemValorTerrenista + correcaoSobreVgv`. |
| `receita_bruta_dre` | `float` | calculada | `DreCalculator`, `DespesasCalculator::calcularComissaoCorretorTerreno()` | calculada | Receita total de vendas + juros/correções, usada como base da comissão do corretor do terreno. |
| `custoNaoIncidente` | `float` | calculada | `ProdutosProcessor`, `DespesasCalculator::custoObraTotal()` | calculada | `infraNaoIncidente * vgv`. |
| `totalUnidadesConstrutora` | `int` | calculada | todo o pipeline | calculada | `totalUnidades - permutas`. |
| `imposto_pis` | `float` | calculada | `ProdutosProcessor` | calculada | Agregado de produto. |
| `imposto_cofins` | `float` | calculada | `ProdutosProcessor` | calculada | Agregado de produto. |
| `imposto_iss` | `float` | calculada | `ProdutosProcessor` | calculada | Agregado de produto. |
| `irrpj` | `float` | calculada | `ProdutosProcessor` | calculada | Agregado com a mesma grafia do código. |
| `csll` | `float` | calculada | `ProdutosProcessor` | calculada | Agregado de produto. |
| `curvaObraAgregada` | `array<float>` | calculada | `FluxoMensalCalculator`, `ReceitasCalculator`, `DespesasCalculator` | calculada | Curva S normalizada pelo prazo de obra. |
| `curvaFinanceiraMedicaoAgregada` | `array<float>` | calculada | `CurvaService::getCurvaFinanceiraMedicaoParaPrazo()`, `ReceitasCalculator::calcularMedicaoObra()` | calculada | Curva financeira de medição CEF com trava em 95% e parcelas finais em +2 e +5 meses pós-obra. |
| `custoCasaM2` | `float` | calculada | `ProdutosProcessor` | calculada | Observação: é sobrescrita a cada iteração e termina com o valor do último produto. |
| `custoInfraM2` | `float` | calculada | `ProdutosProcessor` | calculada | Observação: idem; representa o último produto processado. |

## 4. Variáveis de Calendário (`$datas`)

| Variável | Tipo | Origem | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|
| `inicioIncorporacao` | `Carbon` | calculada | `FluxoMensalCalculator::calcularPeriodos()` | calculada | `dataLancamento - mesesIncorporacao`. |
| `dataLancamento` | `Carbon` | calculada/entrada | `FluxoMensalCalculator`, recebíveis, despesas | calculada | Espelha `params['dataLancamento']`. |
| `fimLancamento` | `Carbon` | calculada | `FluxoMensalCalculator::calcularPeriodos()` | calculada | `dataLancamento + mesesLancamento - 1`. |
| `inicioObra` | `Carbon` | calculada | `FluxoMensalCalculator::calcularPeriodos()` | calculada | No código coincide com `dataLancamento`. |
| `fimObra` | `Carbon` | calculada | `FluxoMensalCalculator::calcularPeriodos()` | calculada | `dataLancamento + mesesObra - 1`. |
| `dataEntrega` | `Carbon` | calculada | `FluxoMensalCalculator::calcularPeriodos()` | calculada | `fimObra + 1 mês`. |
| `inicioPos` | `Carbon` | calculada | `FluxoMensalCalculator::calcularPeriodos()` | calculada | Igual a `dataEntrega`. |
| `fimPos` | `Carbon` | calculada | `FluxoMensalCalculator::calcularPeriodos()` | calculada | `inicioPos + mesesPosObra - 1`. |

## 5. Variáveis de Estado Mutável (`ViabilidadeFluxoContext`)

| Variável | Tipo | Origem | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|
| `perfil` | `PerfilFinanciamento` | calculada a partir de `params` | `FluxoMensalCalculator`, `ReceitasCalculator`, `DespesasCalculator` | estado | Define bifurcação CEF/próprio. |
| `recursosProprios` | `array<string, array<string, float>>` | calculada | pré-cálculo de recebíveis, `ReceitasCalculator` | estado | Armazena `sinal`, `parcelas_obra`, `parcelas_pos`, `juros`, `correcao`, `correcao_obra`. |
| `vendasPorMes` | `array<string, float>` | calculada | `ReceitasCalculator::inicializarValorMedicaoTotal()`, despesas, indicadores | estado | Unidades vendidas por mês. |
| `vendasAcumuladas` | `float` | calculada | `ReceitasCalculator::calcular()` | estado | Base para demanda mínima e medição de obra. |
| `valorMedicaoTotal` | `float` | calculada | `ReceitasCalculator::inicializarValorMedicaoTotal()` | estado | `financiamento (vgvSemTerrenista - RP) - recurso terrenos`. |
| `medicaoObraAcumulada` | `float` | calculada | `ReceitasCalculator::calcularMedicaoObra()` | estado | Evita dupla apropriação. |
| `curvaObraAcumulada` | `float` | calculada | `ReceitasCalculator::calcularMedicaoObra()` | estado | Percentual acumulado da curva S. |
| `mesObraAtual` | `int` | calculada | `ReceitasCalculator::calcularMedicaoObra()` | estado | Controle técnico para atualizar acumulado uma vez por mês. |
| `demandaMinima` | `float` | calculada | `FluxoMensalCalculator::inicializarCachesCef()` | estado | Soma ponderada dos percentuais `demanda_minCef`. |
| `demandaAtingida` | `bool` | calculada | `ReceitasCalculator::calcular()`, `DespesasCalculator::calcular()` | estado | Libera produtos/contratos CEF. |
| `mesDemandaAtingida` | `?string` | calculada | `ReceitasCalculator::calcular()` | estado | Primeiro mês em que a demanda mínima é atingida. |
| `txContratacaoPaga` | `bool` | calculada | `DespesasCalculator::calcular()` | estado | Garante cobrança única da taxa de contratação. |
| `parceriaVgvTotal` | `float` | calculada | `FluxoMensalCalculator::calcular()`, `DespesasCalculator::calcularParceriaTerrenoTotal()` | estado | Teto da parceria sobre entradas totais. |
| `parceriaVgvPago` | `float` | calculada | `DespesasCalculator::calcularComissaoCorretorTerreno()` | estado | Acumulador do já pago (usado indiretamente via `calcularParceriaTerrenoTotal`). |
| `parcelasAtrasadas` | `array<string,float>` | calculada | `FluxoMensalCalculator::aplicarInadimplencia()`, `ReceitasCalculator::calcular()` | estado | Recuperação parcial de inadimplência no perfil próprio. |

## 6. Variáveis de Receita

| Variável | Tipo | Origem | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|
| `totalRp` | `float` | calculada | `ReceitasCalculator::calcular()` | calculada | Soma de sinal, obra e pós-chave/entrega. |
| `totalAtrasadas` | `float` | calculada | `ReceitasCalculator::calcular()` | calculada | Recuperação de inadimplência. |
| `rt['valor']` | `float` | calculada | `ReceitasCalculator::calcularRecursoTerrenos()` | calculada | Só no perfil CEF e a partir da demanda mínima atingida, com defasagem `defasagem_pgtoTerreno`. |
| `mo['valor']` | `float` | calculada | `ReceitasCalculator::calcularMedicaoObra()` | calculada | Só no perfil CEF e a partir do 1º mês da obra; usa curva financeira de medição. |
| `juros_correcao` | `float` | calculada | `ReceitasCalculator::calcular()` | calculada | Soma das chaves `juros`, `correcao` e `correcao_obra` de `recursosProprios`; incluído em `receita_total`. |
| `valorRtMes` | `float` | calculada | `ReceitasCalculator::calcularRtMesVenda()` | calculada | Usa unidades vendidas do mês e `avaliacao_lotesCef`. |
| `valorReceberMes` | `float` | calculada | `ReceitasCalculator::calcularMedicaoObra()` | calculada | Diferença entre medição vendida acumulada e medição já apropriada. |

## 7. Variáveis de Despesa

| Variável | Tipo | Origem | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|
| `custoObraTotal` | `float` | calculada | `DespesasCalculator::custoObraTotal()`, `DreCalculator::calcularCustosDiretosDre()` | calculada | Habitacional + infraestrutura + não incidente. |
| `tributos` | `float` | calculada | `ImpostosService::calcularTributosPorProduto()` | calculada | Proporcional por produto. |
| `financeiros` | `float` | calculada | `DespesasCalculator::calcular()` | calculada | `receitas['total'] * (percentualProdutosCef + percentualOutrasDespesasFinanceiras)`. |
| `custoTerreno` | `float` | calculada | `DespesasCalculator::calcularCustoTerreno()` | calculada | Proporcional à receita do mês (legado; na prática substituído pelo bloco `pagamentoTerreno`). |
| `pagamentoTerreno` | `array` | calculada | `DespesasCalculator::calcularPagamentoTerreno()` | calculada | Bloco com 3 subitens: `parceria` (VGV sobre entradas), `permuta_fisica` (custo de construção das permutas pela curva de obra), `comissao_corretor` (parcelada). Inclui `compraTerreno` rateada na obra. |
| `pagamentoTerreno.parceria` | `float` | calculada | `DespesasCalculator::calcularPagamentoTerreno()` | calculada | `receita_total_mes * parceriaVgv + compraTerreno/mesesObra` (na obra). |
| `itbiMensal` | `float` | calculada | `DespesasCalculator::calcular()` | calculada | Só CEF, por unidade vendida. |
| `registroMensal` | `float` | calculada | `DespesasCalculator::calcular()` | calculada | Só CEF, por unidade vendida. |
| `produtosCefMensal` | `float` | calculada | `DespesasCalculator::calcular()` | calculada | Só CEF, após demanda mínima. |
| `contratosCefMensal` | `float` | calculada | `DespesasCalculator::calcular()` | calculada | Só CEF, após demanda mínima. |
| `seguroMensal` | `float` | calculada | `DespesasCalculator::calcularSegurosMensal()` | calculada | Total de seguros dividido por `mesesObra`. |
| `comissaoBaseMes` | `float` | calculada | `DespesasCalculator::calcularDespesasComerciaisMensais()` | calculada | `valorVendidoMes * taxaComissaoMedia`. |
| `comissaoVenda` | `float` | calculada | `DespesasCalculator::calcularDespesasComerciaisMensais()` | calculada | Usa `pagamentoComissaoVenda`. |
| `comissaoDesligamento` | `float` | calculada | `DespesasCalculator::calcularComissaoDesligamentoMensal()` | calculada | Rateio histórico por vendas passadas. |
| `marketingLancamentoMensal` | `float` | calculada | `DespesasCalculator::calcularMarketingMensal()` | calculada | Parte fixa do marketing durante o lançamento. |
| `marketingVariavelMensal` | `float` | calculada | `DespesasCalculator::calcularMarketingMensal()` | calculada | Proporcional a unidades vendidas / estoque comercializável. |

## 8. Variáveis de DRE, POC e Indicadores

| Variável | Tipo | Origem | Métodos principais | Classificação | Dependências / observações |
|---|---|---|---|---|---|
| `receitaTotalVendas` | `float` | calculada | `DreCalculator::calcular()` | calculada | Usa `vgvSemValorTerrenista`. |
| `receitaBruta` | `float` | calculada | `DreCalculator::calcular()` | calculada | `receitaTotalVendas + jurosCorrecoes`. |
| `receitaLiquida` | `float` | calculada | `DreCalculator::calcular()` | calculada | Desconta impostos e deduções. |
| `lucroBruto` | `float` | calculada | `DreCalculator::calcular()` | calculada | Receita líquida menos custos diretos. |
| `ebitda` | `float` | calculada | `DreCalculator::calcular()` | calculada | Lucro bruto menos despesas operacionais. |
| `ebit` | `float` | calculada | `DreCalculator::calcular()` | calculada | EBITDA menos despesas financeiras e onerosas. |
| `lucroLiquido` | `float` | calculada | `DreCalculator::calcular()` | calculada | EBIT menos IRPJ/CSLL. |
| `custoTotalProjeto` | `float` | calculada | `DreCalculator::calcular()` | calculada | Soma total do projeto. |
| `jurosPJ` | `array` | calculada | `ImpostosService::calcularJurosPJ()`, DRE e indicadores | calculada | Estrutura com antecipação, juros, prazo e parcela média. |
| `fluxoFinanceiro` | `array` | calculada | `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | calculada | Inclui aporte, devolução, PJ e exposição. |
| `tir_operacional` | `float` | calculada | `FluxoMensalCalculator::calcular()` | calculada | TIR do fluxo operacional. |
| `tir_financeira` | `float` | calculada | `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | calculada | TIR considerando estrutura financeira. |
| `tir_sem_cef` | `float` | calculada | `FluxoMensalCalculator::calcular()` | calculada | TIR sem RT/MO. |
| `payback_operacional_meses` | `?int` | calculada | `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | calculada | Só é preenchido se houve saldo negativo antes. |
| `payback_financeiro_meses` | `?int` | calculada | `IndicadoresCalculator::calcularIndicadoresFinanceiros()` | calculada | Idem, na visão financeira. |
| `vso_total_percentual` | `float` | calculada | `IndicadoresCalculator::calcularIndicadoresVso()` | calculada | Vendas acumuladas sobre estoque da construtora. |
| `vso_janelas` | `array` | calculada | `IndicadoresCalculator::calcularIndicadoresVsoJanelas()` | calculada | Janelas móveis de 3, 6 e 12 meses. |
| `percentual_execucao_obra` | `float` | calculada | `PocCalculator::calcularDreContabilPoc()` | calculada | Relação entre custo incorrido e custo orçado. |
| `receita_reconhecida_poc` | `float` | calculada | `PocCalculator` | calculada | Apropriação contábil por execução. |
| `ponte_reconciliacao` | `array` | calculada | `PocCalculator::calcularPonteReconcilicao()` | calculada | Reconcilia caixa, DRE gerencial e POC. |

## 9. Constantes e Defaults Relevantes

| Variável | Tipo | Origem | Onde aparece | Observações |
|---|---|---|---|---|
| `ReceitasCalculator::PERCENTUAL_FINANCIAMENTO_CEF` | `float` | constante | `ReceitasCalculator` | Valor fixo `0.80` para cálculo do financiamento CEF. |
| `CurvaService` | `class` | service | `CurvaService` | Métodos: `getCurvaObraParaPrazo()`, `getCurvaObraBaseParaPrazo()`, `getCurvaFinanceiraMedicaoParaPrazo()`, `normalizarCurva()`, `extrairCurva()`, `ajustarCurva()`, `interpolarCurva()`, `distribuirPorCurva()`. |
| `curvasObra` | `array<int,array<float>>` | constante de classe | `CurvaService` | Curvas S de desembolso para 18, 20, 24, 30 e 36 meses. |

## 10. Variáveis de Entrada do Usuário Validadas no Modelo/Service

Os campos numéricos explicitamente tratados como premissas da `Viabilidade` são os listados em `Viabilidade::CAMPOS_FINANCEIROS`:

- `parceria_vgv`
- `compra_terreno`
- `infra_nao_incidente`
- `porcentagem_lote_proprietario`
- `pis_cofins`
- `iss`
- `outros_impostos`
- `comissao`
- `incorporacao`
- `area_comum`
- `contrapartidas`
- `canteiro_mensal`
- `mo_administrativa`
- `seguros`
- `assistencia_tecnica`
- `despesas_comerciais`
- `stand_vendas`
- `mobilia_decoracao`
- `ajuda_custo_gerente`
- `ajuda_custo_gerente_regional`
- `reembolso_logistica`
- `bonus_cca`
- `bonus_gerente`
- `bonus_gerente_regional`
- `bonus_credito`
- `bonus_gestor_comercial`
- `pagamento_comissao_desligamento`
- `parcelamento_comissao_meses`
- `marketing`
- `itbi_iptu`
- `registro`
- `custo_contratacao_cef`
- `custo_medicao_cef`
- `contratos_cef`
- `produtos_cef`
- `outras_despesas_financeiras`
- `despesas_onerosas_bancos`
- `percentual_antecipacao_pj`
- `aporte_adicional_mensal`
- `devolucao_aporte_percentual`
- `distribuicao_lucros_percentual_obra`
- `taxa_exposicao_aplicada`

Além deles, o pipeline usa fortemente:

- `prazo_obra`
- `prazo_lancamento`
- `prazo_incorporacao`
- `data_lancamento`
- `perfil_financiamento`
- `terreno_id`
- `produtos` customizados na criação/edição da viabilidade

## 11. Dependências Mais Importantes

- `preco`, `quantidade_unidades`, `permutas` e `pgto_por_lote` impactam:
  - `vgv`;
  - `vgvSemUnidPermutas`;
  - `vgvSemValorTerrenista`;
  - custos do terreno;
  - tributos;
  - indicadores de venda.
- `curva_vendas` impacta:
  - recebíveis;
  - vendas por mês;
  - recurso terrenos;
  - demanda mínima;
  - VSO.
- `mesesObra`, `mesesLancamento`, `mesesIncorporacao`, `mesesPosObra` impactam:
  - calendário;
  - distribuição de custos;
  - recebíveis;
  - PJ;
  - exposição;
  - POC.
- `perfilFinanciamento` impacta:
  - habilitação de RT e medição;
  - cobrança de taxas CEF;
  - inadimplência no próprio;
  - indicadores financeiros.
- `taxaJurosPj`, `percentualAntecipacaoPj`, `carenciaPjMeses`, `amortizacaoPjParcelas` impactam:
  - `jurosPJ`;
  - `despesas_onerosas_bancos`;
  - TIR financeira;
  - fluxo financeiro.

## 12. Pontos de Atenção Encontrados

- `percentualComissao` agora é consumido no fluxo mensal via `DespesasCalculator::calcularComissaoCorretorTerreno()` para cálculo da comissão do corretor do terreno, parcelada em `parcelamentoComissaoTerreno` meses.
- `distribuicaoLucrosPercentualObra` é preparada, mas não participa do cálculo atual.
- `despesas_onerosas_bancos` existe no model/config, porém a DRE usa o valor calculado de `jurosPJ['juros_totais']`, não o campo bruto.
- `custoMedicaoContratacao` tem nomenclatura ambígua:
  - recebe o valor do campo de produto `custo_contratacao_cef`;
  - é usado na DRE como base de `calcularTxMedicao()`.
- `custoCasaM2` e `custoInfraM2` em `$dadosProdutos` são sobrescritos a cada produto e terminam refletindo apenas o último item processado.
- `porcentagem_comissao_imobs` é carregada no produto, mas a mesclagem global usa fallback fixo `0.035` para `comissaoImobiliariasPercentual`.
- A `variavelCorrecao` foi removida do sistema; `correcaoSobreVgv` agora é zerado inicialmente e preenchido com juros/correções reais do fluxo.
- O `FluxoMensalCalculator` foi refatorado para calcular juros e correção diretamente do fluxo (sinal lump sum, obra sem INCC, pós-chave SAC com correção).
- O `ReceitasCalculator` agora inclui `juros_correcao` dentro de `receita_total`, alinhado com a planilha (`Entradas = RP + J/C + RT + MO`).
- O `Recurso Terrenos` não é mais fixo no 4º mês; usa `demandaMinima` + `defasagem_pgtoTerreno` por produto.
- A `Medição Obra` começa no 1º mês da obra e usa `curvaFinanceiraMedicaoAgregada`.
- O bloco de `Pagamento Terreno` foi desmembrado em 3 componentes: parceria VGV, permuta física (curva de obra) e comissão do corretor (parcelada).
- A `Incorporação` foi redistribuída em 4 componentes: RI (último mês incorp), Entrega (mês entrega), Até Lançamento (incorp + lanç) e Após Lançamento (lanç + obra).
- As `Deduções` mensais agora separam RET/LP Imóveis, RET/LP Lotes, ISS e Outras Deduções.

## 13. Resumo Executivo

- O núcleo de entrada do cálculo está concentrado em `$params`, abastecido por `Viabilidade`, `config` e parcialmente sobrescrito por parâmetros de produto.
- O núcleo de produto está em `$dadosProdutos['produtos']`, que combina dados cadastrais, customizações da requisição e pré-cálculos fiscais/financeiros.
- O núcleo de estado em execução está em `ViabilidadeFluxoContext`, que controla vendas, demanda mínima, medição de obra, parceria e inadimplência.
- A `variavelCorrecao` foi removida; `juros_correcao` agora é calculado a partir do fluxo real e incluído em `receita_total`.
- O `ReceitasCalculator` unifica `Entradas = RP + RP atrasado + J/C + RT + MO`.
- O bloco de `Pagamento Terreno` (`DespesasCalculator::calcularPagamentoTerreno()`) tem 3 componentes: parceria VGV, permuta física e comissão do corretor do terreno.
- A `Incorporação` usa 4 componentes: RI, Entrega, Até Lançamento e Após Lançamento, com distribuição por período (`Incorporação`/`Lançamento`/`Obra`/`Entrega`).
- As saídas se dividem em três visões:
  - `fluxo_mensal` e `fluxo_mensal_financeiro`;
  - `dre_itens` e `dre_caixa`;
  - `dre_contabil_poc` e quadros mensais POC.
