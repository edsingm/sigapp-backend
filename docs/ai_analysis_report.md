# Análise Completa das Capacidades de IA/ML — SIGAPP

> **Data:** 2026-04-05
> **Escopo:** Backend SIGAPP — módulos de IA, dados disponíveis, oportunidades e propostas de evolução

---

## Sumário Executivo

O SIGAPP possui atualmente **um agente de IA conversacional (SIG_IA)** implementado via pacote `laravel/ai`, conectado ao **OpenRouter** com modelo `z-ai/glm-4.5-air:free`. O agente opera com **3 ferramentas de consulta** sobre terrenos e viabilidades, respondendo via **streaming SSE**. A infraestrutura de 13 provedores de IA está configurada mas **subutilizada** — apenas OpenRouter é ativamente usado. O sistema coleta dados extremamente ricos (95+ campos financeiros, fluxos de workflow, revisões de comitê, negociações, legalização) que **não são explorados** por nenhuma capacidade preditiva, analítica ou de automação inteligente.

---

## 1. Inventário das Capacidades Atuais de IA

### 1.1 Agente SIG_IA

| Atributo | Detalhe |
|----------|---------|
| **Classe** | `App\Ai\Agents\SIG_IA` |
| **Provider** | OpenRouter (`openrouter`) |
| **Modelo** | Env `AI_OPENROUTER_AGENT_MODEL` (default: `z-ai/glm-4.5-air:free`) |
| **Raciocínio** | Chain-of-thought habilitado via `providerOptions()` |
| **Conversas** | Multi-turn via `RemembersConversations` (persiste em DB tenant) |
| **Limite de mensagens** | 100 mensagens por conversa (configurável) |
| **Capacidade** | **Apenas consulta/leitura** — nenhuma ação de escrita no sistema |
| **Idioma** | Exclusivamente português brasileiro |
| **Feature gate** | `check.feature:ai` — disponível apenas em planos com feature AI |

### 1.2 Ferramentas Atuais (Tools)

| Tool | Função | Parâmetros | Acesso |
|------|--------|------------|--------|
| `ListTerrenosTool` | Lista terrenos com filtros | search, workflow_stage, workflow_status_code, cidade_code, limit(máx 50) | Gate `viewAny` Terreno |
| `GetTerrenoDetailsTool` | Detalhes completos de 1 terreno | terreno_id (req), include_viabilidades | Gate `view` Terreno |
| `GetViabilidadesTool` | Consulta viabilidades com DRE | terreno_id, status, approval_status, somente_atual, limit(máx 100) | Gate `viewAny` Terreno |

### 1.3 Endpoints de IA

| Método | Rota | Controlador | Função |
|--------|------|-------------|--------|
| `GET` | `api/v1/ai/conversations` | `AiController@conversations` | Lista 50 últimas conversas do usuário |
| `GET` | `api/v1/ai/conversations/{id}/messages` | `AiController@conversationMessages` | Mensagens de uma conversa |
| `POST` | `api/v1/ai/sig-ai` | `AiController@chat` | Envia mensagem e recebe resposta streaming (SSE) |

### 1.4 Classificação das Capacidades

| Tipo | Status | Detalhe |
|------|--------|---------|
| **Processamento de Linguagem Natural** | Básico | Chat conversacional com LLM genérico, instruções de domínio no prompt |
| **Visão Computacional** | **Inexistente** | Nenhuma análise de imagens, mapas ou documentos |
| **Predição/ML** | **Inexistente** | Nenhuma inferência preditiva sobre dados financeiros ou de workflow |
| **Recomendação** | Parcial (heurística) | O prompt do SIG_IA tem critérios de priorização, mas baseados em regras explícitas, não aprendizado |
| **Embeddings/Busca Semântica** | **Inexistente** | Configuração para OpenAI/Voyage existe, mas nunca usada |
| **Reranking** | **Inexistente** | Cohere configurado mas inutilizado |
| **Texto-para-Áudio** | **Inexistente** | Gemini/ElevenLabs configurados mas não usados |
| **Transcrição** | **Inexistente** | OpenAI configurado mas não usado |
| **Automação** | **Inexistente** | IA não executa ações — apenas responde perguntas |

---

## 2. Provedores de IA Configurados (mas não utilizados)

| Provedor | Config Key | Status | Uso Potencial |
|----------|-----------|--------|---------------|
| `anthropic` | `ANTHROPIC_API_KEY` | Configurado, não usado | Modelos Claude para análises profundas, geração de documentos |
| `azure` | `AZURE_OPENAI_API_KEY` | Configurado, não usado | Embeddings, GPT para tarefas enterprise |
| `openai` | `OPENAI_API_KEY` | Configurado, não usado | Embeddings, transcrição, imagens |
| `gemini` | `GEMINI_API_KEY` | Configurado, não usado | Análise de imagens, texto-para-áudio |
| `groq` | `GROQ_API_KEY` | Configurado, não usado | Inferência ultrarrápida para predições em tempo real |
| `cohere` | `COHERE_API_KEY` | Configurado, não usado | Reranking, embeddings especializados |
| `voyageai` | `VOYAGEAI_API_KEY` | Configurado, não usado | Embeddings especializados para RAG |
| `jina` | `JINA_API_KEY` | Configurado, não usado | Embeddings, reranking |
| `mistral` | `MISTRAL_API_KEY` | Configurado, não usado | Modelos open-source eficientes |
| `deepseek` | `DEEPSEEK_API_KEY` | Configurado, não usado | Modelos de raciocínio econômico |
| `eleven` | `ELEVENLABS_API_KEY` | Configurado, não usado | Síntese de voz |
| `xai` | `XAI_API_KEY` | Configurado, não usado | Modelos Grok |
| `ollama` | `OLLAMA_BASE_URL` | Configurado, não usado | Modelos locais (privacidade total) |

---

## 3. Dados Disponíveis (Não Explorados por IA)

### 3.1 Modelo Terreno (33 relacionamentos)

O modelo `Terreno` é o centro do sistema, conectado a:
- **Viabilidades** com 95+ campos financeiros e DRE calculado
- **Negociações** com proposal_value, business_model, eventos
- **Contratos** com tipos, valores, assinaturas
- **Legalização** com etapas, fases, pendências, documentos
- **Comitê de Revisão** com pareceres departamentais, decisões
- **Projetos** com status, em_viabilidade, em_legalização
- **Documentos** anexados
- **Contatos** e **Proprietários**
- **StatusHistory** com trilha completa de mudanças
- **Comments** e **EntityActivity** com logs de atividade
- **Tasks** com datas de vencimento

### 3.2 Modelo Viabilidade (95+ campos financeiros)

O modelo `Viabilidade` contém um dataset financeiro excepcional:

**Indicadores Fiscais:** PIS/COFINS, ISS, ITBI/IPTU, outros impostos
**Custos de Construção:** Canteiro mensal, MO administrativa, incorporação, área comum
**Comercial:** Comissões (house, imobiliárias), marketing, stand de vendas
**Financeiro:** Taxa de juros PJ, carencia, amortização, antecipação, aportes
**Bonificações:** CCA, gerente, regional, crédito, gestor comercial
**Legalização:** Registro, medição, contratos CEF

O campo `resultados_dre` (JSON) armazena:
- Demonstração completa de resultados (DRE)
- Indicadores financeiros (VGV, lucro, margens, IRR, etc.)
- Fluxo de caixa mensal projetado

### 3.3 Workflow de Terrenos

Pipeline completo com 8+ estágios:
`em_analise → aguardando_viabilidade → viabilidade_aprovada → aguardando_comite → negociacao_minuta → contrato_assinado → legalizando → legalizado_finalizado`

+ Status de encerramento: `descartado`, `arquivado`

Cada transição tem: `workflow_status_changed_at`, `workflow_reason_code`, `workflow_reason_notes`

### 3.4 Dados Temporais

O sistema registra timestamps para:
- Criação e atualização de todas as entidades
- Datas de negócio: apresentação, negociação, opção, contrato, descarte
- Datas de aprovação: requested_at, decided_at
- Datas de negociação: started_at, closed_at
- Histórico completo de status com `StatusHistory`

### 3.5 Dados Não Capturados (Gaps de Coleta)

- Não há tracking de **tempo gasto** em cada etapa do workflow (derivable mas não materializado)
- Não há **feedback loop** sobre recomendações do SIG_IA (aceitas/rejeitadas?)
- Não há **scoring automático** de terrenos — tudo é manual
- Não há **benchmark cross-tenant** (cada tenant é isolado)
- Não há registro de **motivos de descarte** estruturados (apenas notes)
- Não há **dados de mercado externo** (preços do bairro, tendências)

---

## 4. Análise de Desemempenho e Escalabilidade

### 4.1 Arquitetura Atual

```
Usuário → HTTP → AiController → SIG_IA → OpenRouter API → SSE Stream → Usuário
                                    ↓
                         Tools (DB queries)
                                    ↓
                        Tenant Database (SQLite isolado)
                        agent_conversations + agent_conversation_messages
```

### 4.2 Avaliação

| Aspecto | Status | Observação |
|---------|--------|------------|
| **Escalabilidade horizontal** | Boa | Stateless, pode replicar workers |
| **Rate limiting** | Parcial | Handler de `RateLimitedException` existe, mas sem retry automático |
| **Caching de respostas** | Inexistente | Cada consulta ao LLM é uma nova requisição, mesmo para perguntas similares |
| **Embedding caching** | Desabilitado | `config/ai.php` → `caching.embeddings.cache = false` |
| **Query optimization** | Razoável | Tools usam `with()`, `select()` e `withCount()` |
| **Streaming** | Implementado | SSE via `StreamableAgentResponse` |
| **Isolamento multi-tenant** | Completo | Conversas no DB de cada tenant, ACL por Gate |
| **Observabilidade** | Inexistente | Sem logging de uso da IA, latência, custo por tenant |
| **Modelo atual** | Gratuito/free | `z-ai/glm-4.5-air:free` — qualidade/question-limited, sem SLA |
| **Fallback** | Inexistente | Se OpenRouter cai, não há provider de backup |

### 4.3 Riscos de Escalabilidade

1. **Modelo free sem garantia** — o modelo atual pode não escalar com crescimento de usuários
2. **Sem circuit breaker** — falha no provider paralisa completamente a feature de IA
3. **Consultas de tools sem paginação eficiente** — limit hardcoded (50/100), sem cursor pagination
4. **Nenhuma métrica de custo** — impossível otimizar gastos ou alocar por tenant

---

## 5. Gaps e Oportunidades de Melhoria

### 5.1 Gaps Críticos

| Gap | Impacto | Esforço para Fechar |
|-----|---------|---------------------|
| IA é apenas read-only | Baixo ROI — usuário pode fazer tudo via UI | Alto (requer novas tools de escrita) |
| Sem análise de documentos | 34+ campos de documentos não processados por IA | Médio |
| Sem embeddings/semântica | Busca no SIG_IA depende apenas do filtro do LLM | Médio |
| Sem análise preditiva | 95+ campos financeiros sem inferência | Alto |
| Sem feedback loop | Impossível melhorar respostas do agente | Médio |
| Sem observabilidade de IA | Sem métricas de uso, custo, qualidade | Baixo-Médio |
| Sem fallback de provider | Risco de indisponibilidade total | Baixo |
| Modelo free genérico | Qualidade de resposta limitada para domínio financeiro | Baixo (troca de modelo) |

### 5.2 Quick Wins (Alto Impacto, Baixo Esforço)

1. **Trocar para modelo pago** — migrar de `z-ai/glm-4.5-air:free` para modelo com melhor raciocínio financeiro
2. **Adicionar métricas de uso** — log de tokens, custo, latência por tenant
3. **Adicionar fallback provider** — se OpenRouter falhar, fallback para Anthropic/Anthropic
4. **Habilitar embedding cache** — reduzir custo de buscas repetidas
5. **Adicionar 2-3 tools novas ao SIG_IA:**
   - `GetLegalizacaoTool` — consultar status de legalização
   - `GetNegociacaoTool` — consultar dados de negociação
   - `GetComiteTool` — consultar decisões de comitê

---

## 6. Propostas de Novas Funcionalidades de IA

### 6.1 Funcionalidade: Terreno Score (Scoring Inteligente)

**O que é:** Sistema automatizado de scoring de terrenos baseado em dados históricos de viabilidade, workflow e resultado final.

**Arquitetura Técnica:**
```
Pipeline de Features → Modelo ML → API de Scoring → UI/Agent
      ↓
[Campos Viabilidade] + [Tempo em cada etapa] + [Status final]
      ↓
  Modelo: XGBoost/LightGBM (classificação: sucesso/fracasso)
  ou LLM com prompt estruturado (zero-shot scoring)
```

**Abordagem 1 — LLM Scoring (rápida, 1 sprint):**
- Nova tool `ScoreTerrenoTool` que chama LLM com dados estruturados do terreno
- Prompt com critérios de avaliação financeira e de risco
- Retorna score 0-100 com justificativa

**Abordagem 2 — ML Tradicional (mais precisa, 3-4 sprints):**
- Extrair features das viabilidades históricas
- Treinar modelo de classificação (sucesso vs descarte/arquivado)
- Deploy como endpoint REST separado
- Modelo re-treinado semanalmente via comando agendado

**Requisitos de Infraestrutura:**
- Abordagem 1: Nenhuma infra adicional
- Abordagem 2: Redis para cache de scores, Python service ou Laravel job para treino
- Tabela nova: `terreno_scores` (terreno_id, score, version, created_at, features_json)

**Estimativa de Esforço:**
- Abordagem 1: 2-3 dias
- Abordagem 2: 3-4 semanas

**Impacto:** Alto — priorização objetiva de terrenos, redução de análise manual
**Métricas de Sucesso:** Acurácia > 70% vs resultado real, adoção por 60% dos usuários ativos

---

### 6.2 Funcionalidade: Previsão de Viabilidade (Predictive DRE)

**O que é:** Sugestão automática de valores para campos de viabilidade baseada em viabilidades similares anteriores.

**Arquitetura Técnica:**
```
Nova Viabilidade → Similarity Search → KNN/Média Ponderada → Sugestão de Valores → Usuário Aprova
      ↓
Embedding dos campos da viabilidade (vetor de 95+ dimensões)
Busca no vetor space (FAISS ou PostgreSQL pgvector)
```

- Usar embeddings das viabilidades anteriores para encontrar clusters similares
- Sugerir intervalos ótimos para PIS/COFINS, canteiro, MO administrativa, etc.
- Alertar quando valores fogem significativamente da média do cluster

**Requisitos de Infraestrutura:**
- Provider de embeddings (VoyageAI ou OpenAI) ativado e configurado
- Tabela `viabilidade_embeddings` (viabilidade_id, embedding_vector, created_at)
- Job para gerar embeddings ao salvar viabilidade
- Serviço de similaridade (pode ser dot product simples em SQL)

**Estimativa de Esforço:** 2-3 semanas
**Impacto:** Alto — acelera criação de viabilidades, reduz erros de input, padroniza cálculos
**Métricas de Sucesso:** 40% redução no tempo de criação de viabilidade, 80% dos campos sugeridos aceitos

---

### 6.3 Funcionalidade: Chatbot Avançado Multicanal

**O que é:** Evolução do SIG_IA para chatbot com múltiplas capacidades e canais.

**Arquitetura Técnica:**
```
                    ┌──────────────────────────────┐
                    │     Router/Orchestrator      │
                    │   (classifica intenção)       │
                    └──────┬─────┬─────┬────┬──────┘
                           │     │     │    │
              ┌────────┐ ┌─▼──┐ ┌▼──┐ ┌▼─┐┌▼────┐
              │Consulta│ │DRE │ │Legal│ │Com│Docs │
              │Terreno │ │Anal│ │izac.│ │ite│Anal.│
              └────────┘ └────┘ └────┘ └──┘└─────┘
```

**Novas Tools:**
- `GetLegalizacaoTool` — status de legalização, pendências, etapas atrasadas
- `GetComiteTool` — decisões pendentes, pareceres de departamentos
- `GetNegociacaoTool` — status de negociação, valores, contratos
- `GetDashboardTool` — resumo executivo do tenant (KPIs agregados)
- `GetDocumentoTool` — análise de documentos anexados (OCR + NLP)
- `WriteNoteTool` — criar notas/comentários no terreno

**Melhorias no Core:**
- Múltiplos agentes: SIG_IA_Analista, SIG_IA_Legalizacao, SIG_IA_Financeiro
- RAG (Retrieval Augmented Generation) sobre documentos do terreno
- Geração automática de resumos periódicos (semanal/mensal)
- Detecção de intenção para rotear ao agente correto

**Requisitos de Infraestrutura:**
- Ativar provider de embeddings (OpenAI/VoyageAI)
- Ativar Anthropic Claude para análises mais profundas
- Tabela `document_embeddings` para RAG
- Fila/processamento assíncrono para análise de documentos

**Estimativa de Esforço:** 4-6 semanas
**Impacto:** Muito Alto — IA deixa de ser consultiva e se torna operacional
**Métricas de Sucesso:** 50% das interações resolvidas sem UI, >4/5 satisfação

---

### 6.4 Funcionalidade: Otimizador Financeiro de Viabilidade

**O que é:** Sistema que simula cenários e sugere configurações ótimas para maximizar lucro/VGV.

**Arquitetura Técnica:**
```
Viabilidade Atual → Motor de Simulação → Grid Search → Ranking de Cenários → Sugestões
      ↓
Variáveis: taxa_juros, prazo_obra, canteiro_mensal, marketing, comissao
Objetivo: Maximizar lucro_liquido e IRR
Restrições: orçamento_máximo, prazo_máximo
```

- Abordagem 1: Otimização com LLM — pedir ao modelo para explorar cenários
- Abordagem 2: Algoritmo de otimização numérica (scipy/Nelder-Mead) + DRE recalculation

**Requisitos:**
- Serviço de simulação (Pode ser um job que recalcula DRE)
- Endpoint POST `/api/v1/ai/simulacoes` para requisições
- Cache de resultados (similações frequentes)

**Estimativa de Esforço:** 3-4 semanas
**Impacto:** Muito Alto — impacto financeiro direto, otimização de milhões
**Métricas de Sucesso:** +5-15% lucro líquido nos cenários simulados, adoção em 40% das viabilidades

---

### 6.5 Funcionalidade: Detecção de Anomalias

**O que é:** Monitoramento automático para detectar valores, prazos e comportamentos fora do padrão.

**Arquitetura Técnica:**
```
Dados em Tempo Real → Pipeline de Anomalia → Alertas
      ↓
Detecções:
- Valores de viabilidade > 3σ da média do tenant
- Terrenos parados > N dias por estágio
- Prazos de legalização extrapolando histórico
- Alterações bruscas em indicadores de um version para outro
- Discrepâncias entre workflow_stage atual e dados da viabilidade
```

**Abordagem:** Regras estatísticas + thresholds dinâmicos baseados em histórico do tenant
**Requisitos:**
- Tabela `anomaly_alerts` (type, entity_type, entity_id, score, details, notified_at)
- Job agendado (scheduler) para executar detecções
- Notificações via canal existente (email/push)
- Dashboard de anomalias na UI

**Estimativa de Esforço:** 2-3 semanas
**Impacto:** Alto — previne perdas financeiras, erros de cálculo, atrases não detectados
**Métricas de Sucesso:** <5% falsos positivos, 90% detecção de anomalias reportadas pelo usuário

---

### 6.6 Funcionalidade: Processamento Inteligente de Documentos

**O que é:** Extração automática de dados de documentos anexados (escrituras, matrículas, IPTU).

**Arquitetura Técnica:**
```
Documento Upload → OCR (Gemini Vision/OpenAI) → LLM Extração → Validação → Preenchimento Automático
      ↓
Documentos suportados: Matricula, IPTU, Planta, Escritura, Contrato
Dados extraídos: Área, valor venal, proprietário, endereço, zoneamento
```

**Providers:** Gemini Vision ou GPT-4o-Vision para OCR inteligente
**Requisitos:**
- Ativar provider de imagens (`default_for_images` no config/ai.php)
- Tool `AnalyzeDocumentTool` para o SIG_IA
- Tabela `document_analysis` (documento_id, extracted_fields, confidence, raw_response)
- Queue job para processamento assíncrono de documentos grandes

**Estimativa de Esforço:** 3-4 semanas
**Impacto:** Alto — elimina entrada manual de dados, aumenta precisão
**Métricas de Sucesso:** >85% precisão de extração (comparado vs manual), 70% redução no tempo de cadastro

---

### 6.7 Funcionalidade: Analytics Avançado com IA

**O que é:** Dashboards inteligentes com análise automática e insights proativos.

**Arquitetura Técnica:**
```
    ┌─────────────────┐
    │  Data Aggregator│
    │  (cache Redis)  │
    └────────┬────────┘
             ↓
    ┌─────────────────┐     ┌──────────────────┐
    │  LLM Analyst    │ →──→│  Insight Engine  │
    │  (agendado)     │     │  (prioriza)      │
    └─────────────────┘     └──────────────────┘
```

**Capacidades:**
- **Relatório semanal automático** gerado por IA para cada tenant
- **Comparativo cross-temporal** — como o portfólio mudou vs mês anterior
- **Benchmarking interno** — como este terreno vs média do portfólio
- **Insights proativos** — "3 terrenos têm viabilidade expirando em 30 dias"
- **Previsão de pipeline** — estimativa de fechamentos nos próximos 90 dias
- **Análise de gargalos** — onde os terrenos param no workflow

**Requisitos:**
- Comando agendado `php artisan ai:generate-weekly-report`
- Tabela `ai_insights` (tenant_id, insight_type, content, priority, created_at)
- Provider mais capaz (Claude Sonnet/Anthropic) para análises complexas

**Estimativa de Esforço:** 4-5 semanas
**Impacto:** Alto — valor percebido pela gestão, retenção de clientes
**Métricas de Sucesso:** 80% dos tenants acessam relatórios semanais, NPS +15

---

### 6.8 Funcionalidade: Sistema de Recomendação de Próximas Ações

**O que é:** Para cada terreno, a IA sugere a próxima melhor ação baseada no estado atual, histórico e padrões de sucesso.

**Arquitetura Técnica:**
```
Estado do Terreno → Regras de Negócio + ML → Ranking de Ações → Sugestão ao Usuário
      ↓
Ações possíveis:
- "Criar viabilidade" (quando em_analise > 15 dias)
- "Solicitar aprovação no comitê" (quando viabilidade estável)
- "Iniciar legalização" (quando comitê aprovado)
- "Revisar valores" (quando indicators abaixo da média)
- "Documentação pendente" (quando legalização travada)
```

**Implementação:**
- Fase 1: Regras expert-driven (1 semana) — baseadas no conhecimento de negócio
- Fase 2: ML-driven (3 semanas) — aprende com histórico do que funcionou

**Estimativa de Esforço (Fase 1):** 1 semana
**Estimativa de Esforço (Fase 2):** 3 semanas
**Impacto:** Médio-Alto — acelera fluxo, reduz terrenos parados
**Métricas de Sucesso:** -20% no tempo médio por estágio, +30% adoção de ações sugeridas

---

## 7. Matriz de Priorização

| # | Funcionalidade | Impacto | Esforço | ROI | Prioridade |
|---|---------------|---------|---------|-----|------------|
| 1 | Quick Wins (Seção 5.2) | Médio | Baixo | Alto | P0 — Imediato |
| 2 | Scoring de Terrenos | Alto | Baixo | Alto | P1 — Sprint 1-2 |
| 3 | Detecção de Anomalias | Alto | Médio | Alto | P1 — Sprint 1-2 |
| 4 | Previsão de Viabilidade | Alto | Médio | Alto | P2 — Sprint 3-5 |
| 5 | Otimizador Financeiro | Muito Alto | Médio | Muito Alto | P2 — Sprint 3-5 |
| 6 | Processamento de Documentos | Alto | Médio | Alto | P2 — Sprint 3-5 |
| 7 | Chatbot Avançado | Muito Alto | Alto | Muito Alto | P3 — Sprint 5-10 |
| 8 | Analytics com IA | Alto | Alto | Alto | P3 — Sprint 5-10 |
| 9 | Recomendação de Ações | Médio-Alto | Baixo | Alto | P1 — em paralelo |

---

## 8. Considerações sobre Ética, Privacidade e Conformidade

### 8.1 Privacidade de Dados (LGPD)

**Situação Atual:**
- Multi-tenancy com isolamento de banco de dados por tenant — bom
- Conversas de IA persistidas no DB do tenant — isolamento adequado
- Nenhum Dado Pessoal enviado para o LLM (apenas dados de terrenos/imóveis)

**Ações Necessárias:**
- Revisar se prompts enviados ao OpenRouter contêm dados que possam identificar pessoas (nomes de usuários, emails de contatos)
- Implementar **anonimização** antes de enviar dados ao LLM para novas funcionalidades
- Adicionar cláusulas de processamento de dados nos contratos com OpenRouter/Anthropic/OpenAI
- Garantir **direito ao esquecimento** — exclusão de conversas de IA quando usuário deleta conta
- Log de auditoria para quais dados foram enviados a providers externos

### 8.2 Transparência e Explicabilidade

- **Sempre indicar** quando uma resposta é gerada por IA (já implícito pelo contexto de chatbot)
- **Sugestões de scoring/predição** devem incluir justificativa e nível de confiança
- **Não tomar decisões automáticas** de negócio — IA deve sugerir, humano decide
- **Versionar prompts** para rastreabilidade de mudanças no comportamento do agente

### 8.3 Segurança

- **Proteger API keys** — usar Laravel `env()` para todos os segredos de IA (já implementado)
- **Rate limiting** por tenant para evitar abuso do provider externo (e custos inesperados)
- **Validação de input** para prompts — prevenir injection no prompt do sistema
- **Sanitização de documentos** antes de enviar para OCR/visão
- **Log de custos** — rastrear gastos por tenant/provider/modelo

### 8.4 Viés e Justiça

- Modelos de scoring devem ser **auditados regularmente** para viés (ex.: favorecer certas regiões)
- **Diversidade de dados de treino** — garantir que viabilidades de diferentes regiões estejam representadas
- **Transparência de critérios** — usuário deve entender por que um terreno recebeu score X
- **Appeal process** — permitir que usuário conteste scoring/recomendações da IA

---

## 9. Roadmap Sugerido

### Fase 1 — Fundação (Semanas 1-2)
- [ ] Implementar métricas de uso (tokens, custo, latência)
- [ ] Configurar fallback provider
- [ ] Adicionar tools básicas ao SIG_IA (legalização, comitê, negociação)
- [ ] Trocar para modelo pago de melhor qualidade
- [ ] Implementar Terreno Score (LLM-based)
- [ ] Implementar Detecção de Anomalias (regras estatísticas)

### Fase 2 — Preditivo (Semanas 3-5)
- [ ] Ativar embeddings e configurar similaridade de viabilidades
- [ ] Implementar Previsão de Viabilidade
- [ ] Implementar Otimizador Financeiro
- [ ] Desenvolver Recomendação de Ações (expert rules)
- [ ] Dashboard de observabilidade de IA

### Fase 3 — Avançado (Semanas 6-10)
- [ ] Múltiplos agentes especializados
- [ ] RAG sobre documentos do terreno
- [ ] Processamento inteligente de documentos (OCR + LLM)
- [ ] Analytics com relatórios semanais automáticos
- [ ] Pipeline de ML para Terreno Score (tradicional)

### Fase 4 — Maturidade (Semanas 11+)
- [ ] Modelos próprios/finetuned para domínio imobiliário
- [ ] Integração com dados de mercado externo
- [ ] Benchmarking cross-tenant (agregado, anônimo)
- [ ] API de IA externa para parceiros

---

## 10. Resumo de Infraestrutura Necessária

| Recurso | Atual | Necessário | Justificativa |
|---------|-------|------------|---------------|
| OpenRouter API | Usado | Manter + budget maior | Modelo pago necessário |
| Anthropic/Claude | Configurado, não usado | **Ativar** | Análises profundas, relatórios |
| OpenAI Embeddings | Configurado, não usado | **Ativar** | Similaridade, RAG |
| Python ML service | N/A | Novo (opcional) | Modelos de ML tradicionais |
| Redis (cache embeddings) | Existente | Aproveitar | Já tem Redis no stack |
| Queue workers | Existente | Aproveitar | Jobs assíncronos para IA |
| Storage | Existente | Expandir | Armazenar embeddings, análises |
| Scheduler | Existente | Novo job | Relatórios semanais, detecção |
| Nova tabela AI insights | N/A | Criar migration | Armazenar insights gerados |
| Nova tabela terreno_scores | N/A | Criar migration | Armazenar scores |
| Nova tabela anomaly_alerts | N/A | Criar migration | Alertas de anomalia |

---

## Conclusão

O SIGAPP tem uma **excelente fundação de IA** com arquitetura limpa e multi-tenant, mas está utilizando **apenas 5% do potencial** da infraestrutura já configurada. Os dados disponíveis (95+ campos financeiros, workflows rastreáveis, histórico completo) são um **dataset premium** para ML e IA.

A prioridade imediata deve ser: (1) quick wins de infraestrutura e ferramentas, (2) scoring inteligente de terrenos, e (3) detecção de anomalias. Estes três entregam valor tangível em 2-3 semanas com investimento baixo. As funcionalidades preditivas e de otimização financeira no médio prazo podem gerar retorno direto e mensurável para os clientes.
