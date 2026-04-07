# Plano Técnico de Implementação de IA

## Objetivo

Este documento descreve um plano técnico de implementação para evoluir o backend atual de IA do SIG.APP de um assistente conversacional básico para uma plataforma de inteligência aplicada ao negócio, com foco em:

- fortalecimento do módulo existente
- expansão do contexto operacional do assistente
- governança, observabilidade e controle de custo
- recomendação personalizada
- automação inteligente
- análise preditiva e detecção de anomalias
- analytics avançado em linguagem natural

O plano foi organizado para maximizar retorno incremental, reduzir risco de implantação e preservar compatibilidade com a arquitetura atual do backend Laravel multi-tenant.

---

## Princípios de implementação

### 1. Evolução incremental

Nenhuma frente deve depender de uma reescrita do módulo atual. A estratégia recomendada é evoluir o `SIG_IA` por etapas, preservando o funcionamento do chat já existente.

### 2. Prioridade para dados internos

Toda capacidade de IA deve ser grounded nos dados internos já existentes:

- terrenos
- viabilidades
- documentos
- comitê
- legalização
- negociação
- contratos
- dashboards

### 3. Human-in-the-loop

Toda ação sensível ou de impacto operacional deve exigir revisão humana antes de efetivação.

### 4. Observabilidade desde o início

Não expandir IA sem registrar:

- tokens
- custo
- latência
- provider/modelo
- tool usage
- feedback de qualidade

### 5. Governança por tenant

Cada tenant deve ter:

- isolamento de contexto
- orçamento de IA
- retenção configurável
- auditoria de conversas e automações

---

## Diagnóstico resumido do ponto de partida

## O que já existe

- agente `SIG_IA`
- endpoint de chat com streaming
- memória persistente por tenant
- feature flag `ai`
- tool calling para terrenos e viabilidades
- pipeline forte de viabilidade financeira com `resultados_dre`

## O que falta

- observabilidade dedicada para IA
- limitação de custo por tenant
- fallback entre provedores
- saída estruturada
- ampliação de tools
- indexação semântica de documentos
- event tracking para aprendizado
- modelos de recomendação e predição

---

## Arquitetura-alvo

### Visão de alto nível

```text
Cliente
  → API de IA
  → Orquestrador de agentes
  → Classificador de intenção / roteamento
  → Tools operacionais
  → Memória curta + resumo de memória
  → RAG documental
  → Camada analítica / scoring
  → Resposta estruturada + streaming
  → Auditoria, telemetria e governança
```

### Componentes-alvo

#### 1. Camada de orquestração

Nova camada para centralizar:

- seleção de modelo
- seleção de provider
- versionamento de prompt
- roteamento por caso de uso
- budget enforcement
- logging e métricas

#### 2. Registry de prompts

Criar uma forma versionada e auditável para gerenciar:

- prompt de sistema
- templates por tarefa
- guardrails
- formatos de output

#### 3. Catálogo de tools

Evoluir de 3 tools para um catálogo modular:

- consulta
- recomendação
- sumarização
- ação assistida

#### 4. Camada de conhecimento

Adicionar busca semântica sobre:

- documentos de terreno
- PDFs exportados
- políticas internas
- textos de workflow
- material regulatório e jurídico permitido

#### 5. Camada de analytics e scoring

Expor sinais e modelos para:

- recomendação
- previsão
- anomalias
- explicações executivas

---

## Roadmap técnico por fases

## Fase 0 — Hardening do módulo atual

### Objetivo

Tornar o assistente atual seguro, observável e operacionalmente controlável antes de expandir escopo.

### Escopo

- criar rate limit específico para IA
- registrar tokens, custo e provider/modelo por requisição
- adicionar fallback de provider/modelo
- implementar response envelope estruturado
- adicionar resumo automático de conversa longa
- definir retenção mínima de conversas
- redigir campos sensíveis antes do envio ao LLM
- criar testes de feature e unit para o módulo AI

### Entregáveis

- middleware de budget/rate limit de IA
- tabela ou log estruturado de telemetria de IA
- policy de retenção inicial
- testes do endpoint `/ai/sig-ai`
- testes das tools existentes

### Infraestrutura

- Redis
- banco atual
- fila opcional para tarefas de pós-processamento

### Esforço estimado

- médio

### Impacto no sistema

- baixo/médio

### Métricas de sucesso

- latência p95 estável
- custo por conversa visível
- redução de falhas por provider
- zero regressão funcional do chat

---

## Fase 1 — Expansão de contexto do assistente

### Objetivo

Transformar o SIG_IA em um copiloto com visão real do pipeline operacional do tenant.

### Novas tools recomendadas

- `GetDocumentosTool`
- `GetCommitteeStatusTool`
- `GetLegalizacaoStatusTool`
- `GetNegotiationSummaryTool`
- `GetContractStatusTool`
- `GetProjetoStatusTool`
- `GetDashboardInsightsTool`
- `ListOpenTasksTool`

### Regras técnicas

- tools devem ser preferencialmente orientadas a leitura
- toda tool deve ter schema explícito
- toda tool deve ter whitelisting de campos
- toda tool deve validar autorização por usuário
- toda tool deve registrar telemetria de uso

### Entregáveis

- catálogo inicial de tools de contexto ampliado
- prompt atualizado para interpretação cross-domain
- respostas com referências ao objeto consultado

### Infraestrutura

- sem necessidade adicional além da stack atual
- Redis recomendado para cache de tool outputs

### Esforço estimado

- médio/alto

### Impacto no sistema

- médio

### Métricas de sucesso

- aumento de cobertura de perguntas respondidas
- redução da necessidade de navegação manual
- maior adoção do assistente por usuários avançados

---

## Fase 2 — RAG e conhecimento documental

### Objetivo

Permitir que o assistente consulte conhecimento não estruturado, sem depender apenas de tabelas operacionais.

### Casos de uso

- perguntas sobre documentos de terreno
- interpretação de políticas internas
- consulta a manuais e procedimentos
- apoio a legalização e conformidade

### Arquitetura técnica

```text
Upload/ingestão de documento
  → extração de texto
  → chunking
  → geração de embedding
  → indexação vetorial
  → retrieval no momento da pergunta
  → resposta com grounding documental
```

### Decisões recomendadas

- usar PostgreSQL com `pgvector` se a carga inicial for moderada
- usar storage atual para blobs e índice separado para chunks
- manter metadados por tenant e por tipo documental

### Requisitos de infraestrutura

- extensão `pgvector` ou alternativa externa
- job queue para indexação
- storage estruturado de documentos
- tabela de chunks e embeddings

### Entregáveis

- pipeline de ingestão documental
- indexador assíncrono
- retrieval semântico com filtros por tenant
- citação de fontes na resposta

### Esforço estimado

- alto

### Impacto no sistema

- médio/alto

### Métricas de sucesso

- groundedness documental
- precisão percebida
- tempo médio de busca
- cobertura de respostas com citação de fonte

---

## Fase 3 — Recomendação personalizada

### Objetivo

Recomendar prioridades e próximos passos de forma personalizada por tenant, usuário e contexto.

### Estratégia de implementação

#### Etapa 1 — ranking heurístico

Score com base em:

- estágio do workflow
- viabilidade atual
- aprovação/reprovação
- tempo parado
- regional
- valor estimado
- completude documental

#### Etapa 2 — ranking supervisionado

Treinar modelo a partir de histórico real:

- terrenos aprovados
- terrenos descartados
- tempo até avanço
- decisões de comitê
- ações dos usuários

### Arquitetura técnica

- feature mart no banco
- jobs de atualização de features
- endpoint de ranking
- consumo pelo assistente e dashboards

### Entregáveis

- score de priorização inicial
- API interna de recomendação
- explicação das variáveis que influenciaram o score

### Infraestrutura

- jobs agendados
- Redis para cache de ranking
- opcional: microserviço Python na etapa supervisionada

### Esforço estimado

- alto

### Impacto no sistema

- alto

### Métricas de sucesso

- taxa de aceitação de recomendações
- redução do tempo até decisão
- uplift de progressão de pipeline
- precisão top-k

---

## Fase 4 — Automação inteligente de processos

### Objetivo

Automatizar etapas repetitivas do fluxo operacional mantendo revisão humana.

### Casos de uso prioritários

- resumo de reuniões e pareceres de comitê
- geração de briefing executivo de terreno
- sugestão de próxima ação de legalização
- proposta de criação de tarefas
- preenchimento assistido de observações e justificativas

### Arquitetura técnica

```text
Evento do domínio
  → job assíncrono
  → LLM + contexto do objeto
  → proposta de automação
  → fila de aprovação humana
  → execução auditada
```

### Requisitos

- fila de jobs
- tabela de solicitações de automação
- logs de auditoria
- permissão específica por ação

### Entregáveis

- fluxo de automação assistida
- painel de revisão humana
- trilha de auditoria por tenant e usuário

### Esforço estimado

- médio/alto

### Impacto no sistema

- médio

### Métricas de sucesso

- tempo economizado por operação
- taxa de aprovação da automação
- taxa de correção humana posterior

---

## Fase 5 — Análise preditiva

### Objetivo

Antecipar riscos e oportunidades com modelos de scoring e previsão.

### Casos de uso recomendados

- previsão de churn de tenant
- risco de atraso em workflow
- probabilidade de aprovação de viabilidade
- risco de estagnação de terreno
- forecast de uso da plataforma

### Arquitetura técnica

- coleta de eventos históricos
- preparação de dataset
- treinamento batch
- scoring periódico
- exposição via APIs e dashboard

### Requisitos de infraestrutura

- data mart analítico
- pipeline ETL
- ambiente de treinamento
- versionamento de modelos

### Entregáveis

- primeiro score operacional
- pipeline de treinamento batch
- inferência agendada
- explicação de score

### Esforço estimado

- alto

### Impacto no sistema

- médio/alto

### Métricas de sucesso

- AUC/F1
- redução de churn
- redução de atraso operacional
- melhora de previsibilidade do pipeline

---

## Fase 6 — Detecção de anomalias

### Objetivo

Detectar desvios operacionais, financeiros e comportamentais com antecedência.

### Casos iniciais

- viabilidades com margem fora do padrão
- inconsistências entre aprovação e workflow
- terrenos sem atualização por tempo excessivo
- variação anômala de uso por tenant
- aumento abrupto de custo de IA

### Estratégia

- iniciar com regras + estatística descritiva
- evoluir depois para modelos unsupervised

### Entregáveis

- score de anomalia
- feed de alertas
- painel de exceções

### Infraestrutura

- jobs agendados
- histórico consolidado
- mecanismo de alerta

### Esforço estimado

- médio

### Impacto no sistema

- baixo/médio

### Métricas de sucesso

- falso positivo
- precisão dos alertas
- tempo até detecção

---

## Fase 7 — Analytics avançado em linguagem natural

### Objetivo

Permitir perguntas em linguagem natural sobre dados e métricas do sistema.

### Casos de uso

- “quais regionais estão com mais terrenos parados?”
- “qual o impacto das viabilidades reprovadas no pipeline?”
- “quais usuários têm maior taxa de avanço?”
- “qual foi o custo total estimado das oportunidades em análise?”

### Arquitetura técnica

- semantic layer
- data mart com métricas consolidadas
- query planner controlado
- output estruturado com narrativa executiva

### Entregáveis

- endpoints analíticos preparados
- camada semântica de métricas
- NLQ com saída textual + tabular

### Infraestrutura

- ETL/modelagem analítica
- views/materialized views
- cache de consultas

### Esforço estimado

- médio/alto

### Impacto no sistema

- médio

### Métricas de sucesso

- adoção por gestores
- tempo até insight
- frequência de uso analítico

---

## Backlog técnico transversal

## Observabilidade

Itens obrigatórios:

- registrar `conversation_id`
- registrar `tenant_id`
- registrar `user_id`
- registrar provider e modelo
- registrar tokens de entrada e saída
- registrar custo estimado
- registrar duração total
- registrar duração por tool
- registrar falhas e fallback

## Segurança

Itens obrigatórios:

- redaction de dados sensíveis
- whitelisting de campos por tool
- retenção configurável
- proteção contra prompt injection
- segregação rígida por tenant
- revisão humana para ações críticas

## Qualidade

Itens obrigatórios:

- testes de feature do chat
- testes unitários das tools
- testes de autorização
- testes de regressão de prompts
- conjunto de perguntas de avaliação offline

## Produto

Itens obrigatórios:

- feedback explícito do usuário
- marcação de resposta útil/não útil
- feedback sobre recomendação
- acompanhamento de adoção por perfil

---

## Requisitos de infraestrutura consolidados

## Requisitos mínimos para Fases 0 e 1

- backend Laravel atual
- banco tenant atual
- Redis
- logs estruturados

## Requisitos para Fase 2 em diante

- índice vetorial
- fila robusta
- ETL analítico
- storage organizado para documentos
- telemetria persistida

## Requisitos opcionais evolutivos

- microserviço Python para ML
- feature store dedicada
- warehouse analítico
- gateway de inferência especializado

---

## Modelo de dados recomendado para expansão

Tabelas/estruturas sugeridas:

- `ai_request_logs`
- `ai_provider_usage`
- `ai_prompt_versions`
- `ai_feedback`
- `ai_budget_policies`
- `document_chunks`
- `document_embeddings`
- `recommendation_scores`
- `prediction_scores`
- `anomaly_alerts`
- `automation_requests`
- `automation_reviews`

Objetivos:

- rastreabilidade
- governança
- custo por tenant
- avaliação contínua
- suporte a ML futuro

---

## Riscos principais

### Risco 1 — Escalar custo sem governança

Mitigação:

- budget por tenant
- roteamento de modelo
- cache
- rate limit específico

### Risco 2 — Respostas sem grounding suficiente

Mitigação:

- tool-first policy
- RAG com citação
- output estruturado
- feedback de qualidade

### Risco 3 — Vazamento de dados sensíveis

Mitigação:

- redaction
- minimização de prompt
- retenção limitada
- auditoria

### Risco 4 — Automação indevida

Mitigação:

- human-in-the-loop
- política de aprovação
- logs imutáveis

### Risco 5 — Falta de dados para ML

Mitigação:

- iniciar com heurística
- instrumentar eventos desde cedo
- montar trilha histórica progressiva

---

## Métricas executivas de sucesso

## Eficiência operacional

- redução de tempo por análise
- redução de buscas manuais
- redução de retrabalho

## Produto

- adoção do assistente
- usuários ativos mensais no módulo de IA
- sessões por usuário
- taxa de retorno

## Qualidade

- groundedness
- taxa de resposta útil
- taxa de aceitação de recomendação
- precisão dos alertas

## Financeiro

- custo por tenant
- custo por conversa
- custo por insight útil
- economia operacional estimada

## Plataforma

- latência p95
- disponibilidade
- taxa de falha por provider
- taxa de fallback bem-sucedido

---

## Sequência recomendada de entrega

### Ordem sugerida

1. Fase 0 — hardening e governança
2. Fase 1 — expansão de tools
3. Fase 2 — RAG documental
4. Fase 3 — recomendação personalizada
5. Fase 4 — automação inteligente
6. Fase 5 — análise preditiva
7. Fase 6 — detecção de anomalias
8. Fase 7 — analytics avançado

### Justificativa

- reduz risco operacional
- aumenta valor incremental rapidamente
- cria base de dados para ML antes de depender de ML
- evita escalar custo sem controle

---

## Recomendação final

O melhor caminho técnico para o SIG.APP é evoluir a IA em três ondas:

### Onda 1 — estabilização

- observabilidade
- segurança
- custo
- resposta estruturada

### Onda 2 — inteligência assistida

- novas tools
- RAG
- copiloto operacional
- automação assistida

### Onda 3 — inteligência preditiva

- recomendação personalizada
- previsão
- detecção de anomalias
- analytics executivo em linguagem natural

Essa sequência maximiza reaproveitamento da arquitetura atual, aproveita o forte pipeline de viabilidade já existente e reduz o risco de construir soluções de IA sofisticadas sobre uma base ainda sem telemetria e governança suficientes.
