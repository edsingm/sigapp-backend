# Análise Completa do Backend de IA

## Resumo Executivo

O backend possui hoje uma funcionalidade de IA efetivamente implementada em produção: um assistente conversacional tenant-aware chamado `SIG_IA`, com streaming de resposta, memória de conversas e uso de ferramentas para consultar dados reais de terrenos e viabilidades.

Não foram identificadas implementações ativas de visão computacional, OCR, embeddings, RAG, recomendação personalizada, modelos preditivos, detecção de anomalias, speech-to-text, text-to-speech ou automações autônomas com IA. Existe, porém, uma base técnica promissora para evolução, composta por:

- integração com `laravel/ai`
- suporte configurado para múltiplos provedores
- persistência de conversas por tenant
- feature gating por plano
- motor analítico robusto de viabilidade financeira

O principal ativo para expansão de IA no sistema não é o chat em si, mas o pipeline determinístico de viabilidade, que já gera DRE, fluxo mensal, POC e indicadores financeiros detalhados.

---

## Objetivo desta análise

Este documento consolida:

1. inventário das capacidades atuais de IA
2. arquitetura técnica e endpoints existentes
3. avaliação de desempenho e escalabilidade
4. gaps, riscos e oportunidades
5. propostas de evolução alinhadas ao negócio
6. considerações de ética, privacidade e conformidade

---

## Stack e arquitetura relacionados à IA

### Dependências relevantes

- `laravel/ai` — orquestração de agentes, tools, streaming e memória
- `laravel/sanctum` — autenticação para acesso ao módulo
- `stancl/tenancy` — isolamento por tenant
- `redis` — disponível para cache e otimizações futuras

### Configuração de IA

O sistema possui configuração para múltiplos provedores em `config/ai.php`, incluindo:

- OpenAI
- OpenRouter
- Gemini
- Anthropic
- Cohere
- DeepSeek
- Groq
- Ollama
- VoyageAI
- xAI
- Jina
- Azure OpenAI
- ElevenLabs

Apesar disso, o agente em uso hoje está fixado em:

- provider: `openrouter`
- model default: `z-ai/glm-4.5-air:free`

### Arquitetura de alto nível atual

```text
Cliente autenticado
  → endpoint tenant /api/v1/ai/sig-ai
  → AiController
  → agente SIG_IA
  → tools de consulta em dados do tenant
  → retorno em streaming
  → persistência da conversa no banco do tenant
```

---

## Inventário completo das capacidades atuais de IA

### 1. Assistente conversacional de domínio

Capacidade implementada:

- chat textual com streaming
- memória persistente de conversas
- instruções específicas do negócio
- respostas em português brasileiro
- foco em análise executiva de terrenos e viabilidades

Características principais:

- o agente exige consulta às tools antes de responder análises específicas
- o prompt define critérios de priorização, risco e recomendação
- o formato de resposta é estruturado em blocos executivos

### 2. Tool calling sobre dados operacionais

Ferramentas implementadas:

- `ListTerrenosTool`
- `GetTerrenoDetailsTool`
- `GetViabilidadesTool`

Essas ferramentas permitem:

- listar terrenos com filtros
- consultar um terreno específico com contexto operacional
- consultar histórico e estado atual de viabilidades
- acessar `resultados_dre` e informações econômicas persistidas

### 3. Memória de conversação

A memória do assistente é persistida nas tabelas:

- `agent_conversations`
- `agent_conversation_messages`

Campos relevantes suportados no schema:

- conteúdo
- attachments
- tool calls
- tool results
- usage
- meta

Isso habilita histórico por usuário dentro do contexto do tenant.

### 4. Governança por tenant e plano

O módulo de IA está protegido por:

- autenticação Sanctum
- contexto tenant
- middleware de assinatura
- feature flag `ai`

Disponibilidade por plano:

- `broker`: não habilitado
- `basico`: não habilitado
- `master`: habilitado
- `pro`: habilitado

### 5. Base analítica consumível pela IA

Embora não seja ML, o sistema possui um motor analítico avançado de viabilidade que já produz insumos de alto valor para IA:

- fluxo mensal
- DRE consolidada
- DRE contábil POC
- indicadores financeiros
- indicadores VSO
- parâmetros utilizados no cálculo

Essa camada é hoje a principal fonte de inteligência econômica do sistema.

---

## O que NÃO está implementado hoje

### NLP avançado além do chat

Não identificado:

- classificação de intenção
- sumarização automática de documentos
- extração estruturada de texto
- agente multi-intenção com roteamento dinâmico

### Visão computacional

Não identificado:

- OCR
- análise de imagens
- leitura automática de PDFs escaneados
- interpretação de plantas, mapas ou anexos

### ML preditivo

Não identificado:

- churn prediction
- scoring de risco
- probabilidade de aprovação
- previsão de atraso operacional
- forecast de pipeline

### Recomendação personalizada

Não identificado:

- ranking personalizado por usuário
- recomendação de terrenos com base em histórico
- recomendações contextuais por regional, produto ou perfil

### RAG, embeddings e busca vetorial

Não identificado:

- modelos/tabelas de embeddings
- busca semântica
- índice vetorial
- recuperação de documentos por similaridade

Observação:

- `config/ai.php` já prevê defaults para embeddings e reranking
- o cache de embeddings está desabilitado
- há documentação de ideia de RAG em `docs/ia.md`, mas não implementação ativa

### Automação inteligente com ação

Não identificado:

- tool que cria tarefas
- tool que aprova viabilidades
- tool que altera workflow
- tool que dispara jobs ou fluxos externos

As tools atuais são somente leitura.

---

## Endpoints atuais do módulo

### Conversas

#### `GET /api/v1/ai/conversations`

Retorna até 50 conversas do usuário autenticado.

#### `GET /api/v1/ai/conversations/{id}/messages`

Retorna mensagens da conversa, filtrando por `user_id`.

#### `POST /api/v1/ai/sig-ai`

Recebe:

- `message`
- `conversation_id` opcional

Comportamento:

- valida payload
- cria conversa quando necessário
- executa o agente em streaming
- devolve `X-Conversation-Id` no header

---

## Modelos, serviços e pipelines existentes

### Agente

#### `SIG_IA`

Responsabilidades:

- definir provider e modelo
- definir instruções do agente
- registrar ferramentas
- habilitar opções de reasoning para OpenRouter
- lembrar conversas

### Tools

#### `ListTerrenosTool`

Capacidade:

- consulta carteira de terrenos
- aplica filtros por busca, estágio, status, cidade e limite
- carrega viabilidade atual

#### `GetTerrenoDetailsTool`

Capacidade:

- retorna visão operacional de um terreno
- inclui dados de workflow, negociação, contrato, projetos e contadores
- opcionalmente inclui últimas viabilidades

#### `GetViabilidadesTool`

Capacidade:

- filtra viabilidades por terreno, status e aprovação
- permite consultar apenas versão atual
- retorna `resultados_dre`

### Pipeline de dados da viabilidade

O fluxo atual de cálculo de viabilidade é:

```text
Criação/atualização da viabilidade
  → validação dos dados
  → versionamento da viabilidade atual
  → cálculo com ViabilidadeUnificadoService
  → persistência de resultados em resultados_dre
  → uso posterior pelo assistente SIG_IA
```

### Motor financeiro existente

O `ViabilidadeUnificadoService` já entrega:

- `gerarFluxoMensal()`
- `calcularReceitas()`
- `calcularDespesas()`
- `calcularDre()`
- quadro POC contábil
- indicadores financeiros e operacionais

Saídas persistidas/geradas:

- `dre_itens`
- `dre_contabil_poc`
- `dre_contabil_poc_mensal`
- `indicadores`
- `fluxo_mensal`
- `fluxo_mensal_financeiro`
- `totais`
- `parametros_utilizados`

---

## Análise de desempenho e escalabilidade

### Pontos fortes

- isolamento natural por tenant
- persistência de conversas no banco do tenant
- streaming melhora UX e percepção de tempo
- Redis já disponível na stack
- feature flag por plano reduz custo indiscriminado
- pipeline de viabilidade já está consolidado e persistido

### Limitações técnicas atuais

#### 1. Dependência síncrona do LLM

O chat atual executa a chamada ao modelo durante a requisição HTTP. Isso é simples, mas escala pior em cenários de alta concorrência porque o worker fica ocupado durante o streaming.

#### 2. Ausência de rate limit específico para IA

O endpoint usa o throttle genérico `api-auth`, o que é inadequado para uma funcionalidade com custo unitário alto.

#### 3. Sem fallback de provider

Embora existam múltiplos providers configuráveis, o agente hoje depende diretamente de OpenRouter e não possui failover explícito.

#### 4. Ausência de cache de respostas/contexto

Não há:

- cache de respostas recorrentes
- cache de tool outputs
- cache semântico
- resumo de memória por conversa longa

#### 5. Observabilidade incompleta

Há logging geral de duração e memória da API, mas não há telemetria dedicada para IA:

- tokens por requisição
- custo por tenant
- latência por provider/modelo
- taxa de tool calls
- acurácia percebida
- taxa de fallback

#### 6. Ausência de testes específicos do módulo

Não foram encontrados testes de:

- endpoints do assistente
- segurança do acesso às conversas
- comportamento das tools
- robustez do streaming
- tratamento de exceções do provider

### Classificação de maturidade

Situação atual estimada:

- produto: inicial/intermediário
- arquitetura: intermediária
- observabilidade: baixa
- governança: baixa/intermediária
- escalabilidade: intermediária com limitações
- prontidão para expansão: boa base, mas requer hardening

---

## Gaps identificados

### Gaps funcionais

- assistente com contexto restrito a terrenos e viabilidades
- ausência de consulta a documentos, legalização, comitê, contratos e tarefas
- ausência de execução de ações orientadas por IA
- ausência de analytics em linguagem natural

### Gaps técnicos

- sem camada própria de orquestração de IA
- sem resposta estruturada para automação downstream
- sem cache/context summarization
- sem avaliação sistemática de qualidade
- sem fallback/circuit breaker
- sem fila dedicada para tarefas pesadas de IA

### Gaps de dados

- falta de event tracking para aprendizado futuro
- falta de dataset histórico rotulado para recomendação e previsão
- falta de feature store ou data mart analítico

### Gaps de governança

- sem política explícita de retenção de conversas
- sem anonimização/redação de dados sensíveis
- sem dashboard de custo por tenant
- sem critérios formais de revisão humana para ações críticas

---

## Oportunidades de melhoria alinhadas ao negócio

### 1. Expandir o SIG_IA para copiloto operacional

Adicionar novas tools para:

- documentos
- comitê
- legalização
- negociação
- contratos
- projetos
- tarefas
- dashboards

Valor:

- respostas mais completas
- redução de busca manual
- maior aderência ao fluxo real do negócio

### 2. Transformar o motor de viabilidade em base de recomendação

Usar os dados já calculados para:

- priorização de terrenos
- ranking de oportunidades
- identificação de gargalos
- recomendação de próximos passos

Valor:

- aumento de produtividade comercial e operacional
- apoio real à decisão

### 3. Criar base de observabilidade e governança de IA

Adicionar:

- custo por tenant
- tokens por requisição
- latência p50/p95
- taxa de resposta útil
- retenção de conversas
- redaction de dados sensíveis

Valor:

- controle financeiro
- previsibilidade de operação
- preparo para escalar

---

## Propostas de novas funcionalidades de IA

## 1. Chatbot avançado multicapacidade

### Objetivo

Evoluir o assistente atual para um copiloto operacional e analítico capaz de responder com contexto ampliado, buscar conhecimento em documentos e sugerir ações.

### Arquitetura técnica

Componentes sugeridos:

- orquestrador de agentes
- registry/versionamento de prompts
- resposta estruturada em JSON
- tool routing por intenção
- memória resumida por conversa
- RAG sobre documentos e materiais operacionais
- trilha de auditoria das respostas

### Requisitos de infraestrutura

- Redis para cache e memória resumida
- filas para pré-processamento e indexação
- storage de documentos
- índice vetorial no PostgreSQL com pgvector ou serviço externo
- telemetria de uso/custo

### Estimativa de esforço

- alto

### Impacto no sistema

- médio/alto
- impacta documentos, segurança, logs, UX e custos

### Métricas de sucesso

- taxa de resolução sem suporte humano
- groundedness
- latência p95
- custo por conversa
- CSAT/NPS interno

---

## 2. Sistema de recomendação personalizado

### Objetivo

Oferecer ranking de terrenos, recomendações de ação e priorização por usuário, tenant, regional e contexto de negócio.

### Arquitetura técnica

Fase inicial:

- score híbrido baseado em regras + sinais históricos

Fase evolutiva:

- modelo supervisionado para ranking
- feature store simples
- serving via API interna

### Requisitos de infraestrutura

- eventos de uso e decisão
- jobs batch
- data mart analítico
- cache de ranking por usuário/tenant

### Estimativa de esforço

- alto

### Impacto no sistema

- alto valor de produto
- requer novas estruturas analíticas

### Métricas de sucesso

- uplift de conversão
- redução do tempo até decisão
- taxa de aceitação de recomendações
- precisão top-k

---

## 3. Análise preditiva de dados de usuários e operação

### Objetivo

Antecipar riscos e oportunidades, como churn, atraso operacional, risco de reprovação e gargalos do pipeline.

### Arquitetura técnica

- coleta de eventos históricos
- treinamento batch
- serviço de scoring periódico
- exposição dos scores em API e dashboards

### Casos prioritários

- churn de tenant
- atraso em workflow
- probabilidade de aprovação de viabilidade
- risco de inadimplência/queda de uso

### Requisitos de infraestrutura

- data warehouse ou data mart
- jobs agendados
- ambiente de treinamento opcional em Python

### Estimativa de esforço

- alto

### Impacto no sistema

- médio/alto
- depende de maturidade analítica

### Métricas de sucesso

- AUC/F1
- lift em campanhas de retenção
- redução de churn
- redução de atraso operacional

---

## 4. Automação inteligente de processos

### Objetivo

Reduzir trabalho operacional por meio de automações assistidas por IA com revisão humana.

### Possíveis automações

- sumarização de comitês
- geração de parecer inicial
- criação automática de tarefas
- recomendação de próximos passos de legalização
- preparação de briefing executivo de terrenos

### Arquitetura técnica

- motor de regras + LLM
- fila de execução
- fila de revisão humana
- logs e auditoria detalhada

### Requisitos de infraestrutura

- jobs assíncronos
- tabelas de aprovação
- notificações

### Estimativa de esforço

- médio/alto

### Impacto no sistema

- médio
- forte impacto operacional positivo

### Métricas de sucesso

- redução de tempo de ciclo
- percentual de tarefas automatizadas
- taxa de override humano
- erros evitados

---

## 5. Detecção de anomalias

### Objetivo

Identificar cedo comportamentos fora do padrão em finanças, workflow, uso do sistema e qualidade de dados.

### Casos potenciais

- viabilidades com margens fora da curva
- terrenos parados por tempo excessivo
- inconsistências entre workflow e aprovação
- comportamento suspeito de uso
- consumo anômalo de recursos

### Arquitetura técnica

- modelos estatísticos ou unsupervised
- regras de alerta
- painel de exceções

### Requisitos de infraestrutura

- jobs periódicos
- armazenamento histórico
- mecanismo de alerta

### Estimativa de esforço

- médio

### Impacto no sistema

- médio

### Métricas de sucesso

- precisão dos alertas
- falso positivo
- tempo médio até detecção
- perda evitada

---

## 6. Otimização de recursos e custo de IA

### Objetivo

Escalar IA com custo previsível por tenant e por caso de uso.

### Arquitetura técnica

- roteamento de modelo por complexidade
- fallback por provider
- budget por tenant
- cache de contexto
- compressão/sumarização de histórico

### Requisitos de infraestrutura

- Redis
- observabilidade de tokens/custo
- regras de budget

### Estimativa de esforço

- médio

### Impacto no sistema

- baixo/médio
- alto retorno financeiro

### Métricas de sucesso

- custo por tenant
- custo por resposta
- disponibilidade
- taxa de fallback bem-sucedido

---

## 7. Analytics avançado com IA

### Objetivo

Permitir consulta em linguagem natural sobre métricas do sistema e geração automática de insights executivos.

### Arquitetura técnica

- data mart consolidado
- semantic layer
- NLQ com output estruturado
- geração de narrativas automáticas

### Requisitos de infraestrutura

- ETL/modelagem analítica
- visões materializadas
- telemetria confiável

### Estimativa de esforço

- médio/alto

### Impacto no sistema

- médio
- forte valor para gestão

### Métricas de sucesso

- adoção do analytics
- tempo até insight
- frequência de uso por gestores

---

## Considerações éticas, privacidade e conformidade

### LGPD

Pontos críticos para o módulo de IA:

- base legal para uso dos dados em prompts
- transparência sobre envio a provedores externos
- retenção de histórico de conversa
- direito de exclusão e anonimização

### Minimização de dados

Recomendações:

- enviar ao modelo apenas campos estritamente necessários
- redigir ou mascarar dados sensíveis antes do prompt
- evitar persistência excessiva em `tool_results`

### Explicabilidade

Para recomendações e previsões futuras:

- apresentar evidências
- mostrar fatores principais
- indicar nível de confiança
- exigir revisão humana em casos críticos

### Segurança

Recomendações:

- mitigação de prompt injection
- whitelisting de campos por tool
- auditoria por usuário e tenant
- validação de respostas estruturadas

### Bias e fairness

Futuras soluções de recomendação/predição devem ser avaliadas para evitar enviesamento por:

- regional
- tipo de terreno
- perfil de usuário
- histórico incompleto de operação

---

## Conclusão

O backend já possui uma base sólida para um copiloto de negócio, mas ainda está em estágio inicial em termos de maturidade de IA.

### Situação atual em uma frase

Hoje o sistema tem um assistente conversacional com grounding em dados de terrenos e viabilidades, mas ainda não possui um ecossistema completo de IA analítica, preditiva e automatizada.

### Melhor caminho de evolução

1. fortalecer o módulo atual
2. ampliar o contexto do assistente
3. estruturar telemetria e governança
4. construir base de dados para ML
5. lançar recomendação, automação e previsão

### Prioridade estratégica

Se o objetivo for maximizar impacto de negócio com risco controlado, a sequência recomendada é:

- hardening do SIG_IA atual
- expansão de tools e contexto
- observabilidade e controle de custo
- recomendação personalizada
- automação inteligente
- análise preditiva e anomaly detection
