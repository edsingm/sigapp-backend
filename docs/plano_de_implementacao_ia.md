# Plano de Implementação — Evolução IA do SIG.APP

> **Base:** `docs/plano_tecnico_implementacao_ia.md` (arquitetura-alvo e roadmap)
> **Referência:** `docs/ai_analysis_report.md` (inventário e gaps)
> **Data:** 2026-04-05

***

## Contexto

O SIG.APP possui um agente de IA conversacional (`SIG_IA`) com 3 ferramentas read-only operando via OpenRouter. O plano técnico existente (`docs/plano_tecnico_implementacao_ia.md`) define 8 fases de evolução até uma plataforma completa de IA com RAG, predição, automação e analytics. Este documento traduz aquela visão em **tarefas de implementação concretas**, com arquivos a criar/modificar, migrations, e passos de teste.

***

# FASE 0 — Hardening e Observabilidade

**Objetivo:** Tornar o módulo atual seguro, observável e operável antes de expandir.

## 0.1 — Tabela de Telemetria de IA

**Arquivos a criar:**

- `database/migrations/tenant/2026_04_06_000001_create_ai_request_logs_table.php`
- `app/Models/Tenant/AiRequestLog.php`

**Schema:**

```
ai_request_logs (tenant-scoped, no DB de tenant):
- id: bigint PK
- user_id: bigint FK users
- conversation_id: string(36)
- provider: string(50) — "openrouter"
- model: string(100) — "z-ai/glm-4.5-air:free"
- prompt_tokens: int
- completion_tokens: int
- total_tokens: int
- estimated_cost_usd: decimal(10,6)
- duration_ms: int
- tool_calls_count: int
- tool_calls: json
- status: string(20) — "success"|"error"|"rate_limited"
- error_message: text nullable
- ip_address: string(45)
- created_at, updated_at: timestamps
- indexes: user_id, created_at, conversation_id, status
```

**Referência:** Padrão similar ao migration `2026_03_22_193204_create_agent_conversations_table.php` (usa `Laravel\Ai\Migrations\AiMigration` como base)

## 0.2 — Serviço de Telemetria

**Arquivo a criar:** `app/Services/AiTelemetryService.php`

Responsabilidades:

- `logRequest(array $data): void` — registra no banco
- `getCostByUser(int $userId, Carbon $from, Carbon $to): float`
- `getCostByTenant(Carbon $from, Carbon $to): array` — agregado por tenant (central DB)
- `getUsageStats(Carbon $from, Carbon $to): array` — tokens, latência p50/p95, error rate
- `estimateCost($provider, $model, $promptTokens, $completionTokens): float`

## 0.3 — Middleware de Rate Limit de IA

**Arquivo a criar:** `app/Http/Middleware/AiRateLimit.php`

- Rate limit específico para IA (separado do `api-auth` genérico de 1000/min)
- Default: 30 req/min por usuário para endpoints `/ai/sig-ai`
- Redis-backed para cross-instance
- Response: 429 com mensagem customizada

**Registrar em** `bootstrap/app.php`:

```php
'ai.rate_limit' => \App\Http\Middleware\AiRateLimit::class,
```

## 0.4 — Middleware de Budget de IA por Tenant

**Arquivo a criar:** `app/Http/Middleware/AiBudgetCheck.php`

- Verifica custo acumulado no período (mês) vs orçamento configurável
- Orçamento default por tenant: $10/mês (configurável via `.env` `AI_TENANT_BUDGET_DEFAULT`)
- Entitlement `ai_budget` no sistema de planos (tabela `entitlements`)
- Se estourar: retorna 402 `AI_BUDGET_EXCEEDED`

**Integração:** Mesmo padrão do `EnforcePlanLimits` — usa `PlanMatrixService` para resolver limites

## 0.5 — Budget Entitlement

**Ação:** Criar seed/миграção no central DB adicionando entitlement `ai_budget` tipo `LIMIT`, default `10.00`

**Arquivo a modificar:** `database/seeders/PlanSeeder.php` (ou criar migration central para adicionar o entitlement)

## 0.6 — Fallback de Provider

**Arquivo a modificar:** `app/Ai/Agents/SIG_IA.php`

- Adicionar método `fallbackProvider(): string` → `'anthropic'` (ou outro configurado)
- Env `AI_FALLBACK_PROVIDER=anthropic`
- Env `AI_FALLBACK_AGENT_MODEL=claude-sonnet-4-6`

**Arquivo a modificar:** `app/Http/Controllers/Api/V1/Tenant/AiController.php`

- No catch de `RateLimitedException`, tentar com provider fallback antes de retornar 429
- Ou criar wrapper service que tenta provider primário, fallback no erro

**Arquivo a criar:** `app/Services/AiProviderRouter.php`

- `getAgent(): SIG_IA` — retorna agente com provider configurado
- Se provider primário falha (exception ou timeout), tenta fallback
- Registra tentativa e fallback no log de telemetria

## 0.7 — Redaction de Dados Sensíveis

**Arquivo a criar:** `app/Services/AiDataRedactor.php`

- `redactConversationContext(string $content): string` — remove/redacta emails, CPFs, telefones
- `redactTerrenoPayload(array $data): array` — remove campos sensíveis antes de enviar ao LLM
- Campos a redactar: emails de contatos, CPFs de proprietários, telefones
- Regex patterns para CPF (`\d{3}\.\d{3}\.\d{3}-\d{2}`), email, telefone

## 0.8 — Response Envelope Estruturado

**Arquivo a modificar:** `app/Http/Controllers/Api/V1/Tenant/AiController.php`

- Adicionar header `X-AI-Provider` com provider/modelo usado
- Adicionar header `X-AI-Tokens` com consumo de tokens
- Adicionar header `X-AI-Cost` com custo estimado

**Arquivo a criar:** `app/Http/Middleware/AiTelemetryMiddleware.php`

- Middleware que envolve a resposta do chat
- Captura métricas de uso do streamable response
- Salva no `AiRequestLog` via `AiTelemetryService`
- Registra no `bootstrap/app.php`:

```php
'ai.telemetry' => \App\Http\Middleware\AiTelemetryMiddleware::class,
```

## 0.9 — Testes do Módulo AI

**Arquivos a criar:**

- `tests/Unit/AiTelemetryServiceTest.php`
- `tests/Feature/Api/V1/Tenant/AiControllerTest.php` — testa chat, conversations, messages
- `tests/Unit/AiToolsTest.php` — testa cada tool individualmente (mock do Gate)
- `tests/Feature/Middleware/AiRateLimitTest.php`
- `tests/Feature/Middleware/AiBudgetCheckTest.php`

**Padrão:** SQLite in-memory (`phpunit.xml`), mesmas convenções dos testes existentes em `tests/`

## 0.10 — Rate Limiter no routes

**Arquivo a modificar:** `routes/api.php`

Adicionar limiter:

```php
RateLimiter::for('ai-chat', function (Request $request) {
    return Limit::perMinute(30)->by('tenant:' . tenant('id') . ':user:' . $request->user()?->id);
});
```

**Arquivo a modificar:** `routes/tenant.php` — Adicionar `throttle:ai-chat` nas rotas de IA

***

## Checklist Fase 0

- [x] Migration `ai_request_logs`
- [x] Model `AiRequestLog`
- [x] Service `AiTelemetryService`
- [x] Middleware `AiRateLimit`
- [x] Middleware `AiBudgetCheck`
- [x] Entitlement `ai_budget`
- [x] Service `AiDataRedactor`
- [x] Service `AiProviderRouter`
- [x] Middleware `AiTelemetryMiddleware`
- [x] Modificar `AiController` (headers, fallback)
- [x] Modificar `SIG_IA` (fallback provider config)
- [x] Modificar `routes/tenant.php` (novo throttle)
- [x] Modificar `routes/api.php` (novo limiter)
- [x] Modificar `bootstrap/app.php` (aliases)
- [x] 5 arquivos de teste
- [x] `.env.example` — adicionar vars `AI_FALLBACK_*`, `AI_TENANT_BUDGET_DEFAULT`

**Esforço:** 3-5 dias
**Impacto:** Baixo (sem breaking changes, apenas adiciona instrumentação)

***

# FASE 1 — Expansão de Contexto (Novas Tools + Agentes)

**Objetivo:** Transformar SIG\_IA em copiloto com visão completa do tenant.

## 1.1 — Novas Tools

**Arquivos a criar:** (padrão idêntico a `ListTerrenosTool`, `GetTerrenoDetailsTool`, `GetViabilidadesTool`)

| Tool                      | Arquivo                                    | Função                                                                     |
| ------------------------- | ------------------------------------------ | -------------------------------------------------------------------------- |
| `GetLegalizacaoTool`      | `app/Ai/Tools/GetLegalizacaoTool.php`      | Status de legalização, etapas, pendências, custos acumulados               |
| `GetComiteTool`           | `app/Ai/Tools/GetComiteTool.php`           | Decisões de comitê, pareceres departamentais, pendências                   |
| `GetNegociacaoTool`       | `app/Ai/Tools/GetNegociacaoTool.php`       | Resumo de negociação, proposal\_value, eventos, contratos                  |
| `GetDocumentosTool`       | `app/Ai/Tools/GetDocumentosTool.php`       | Lista documentos por terreno (nome, tipo, tamanho, data)                   |
| `GetDashboardSummaryTool` | `app/Ai/Tools/GetDashboardSummaryTool.php` | KPIs agregados: total terrenos, por stage, VGV total, aprovações pendentes |
| `GetTasksTool`            | `app/Ai/Tools/GetTasksTool.php`            | Tarefas abertas, vencidas, por responsável                                 |

**Cada tool deve seguir o padrão:**

```php
class GetXXXTool implements Tool
{
    public function description(): Stringable|string { ... }
    public function handle(Request $request): Stringable|string { ... }
    public function schema(JsonSchema $schema): array { ... }
}
```

**Usar como referência exata:** `app/Ai/Tools/GetTerrenoDetailsTool.php`

**ACL:** Todas devem usar `Gate::denies('viewAny', Terreno::class)` como gate mínimo, ou `Gate::denies('view', $entity)` para recursos específicos

## 1.2 — Registrar Tools no SIG\_IA

**Arquivo a modificar:** `app/Ai/Agents/SIG_IA.php`

- Adicionar imports das novas tools
- Adicionar ao método `tools()`
- Atualizar `instructions()` para mencionar as novas ferramentas e quando usá-las

## 1.3 — Atualizar Prompt do SIG\_IA

**Arquivo a modificar:** `app/Ai/Agents/SIG_IA.php` → método `instructions()`

Adicionar seções sobre:

- Legalização (estágios, pendências, etapas atrasadas)
- Comitê (pareceres, decisões, departamentos)
- Negociação (status, valores, contratos)
- Documentos (tipos, status de upload)
- Tarefas (vencimentos, responsáveis)

## 1.4 — Cache de Tool Outputs

**Arquivo a modificar:** Cada nova tool deve ter cache opcional via Redis

```php
$cacheKey = "ai:tool:" . md5(json_encode($request->all()));
if ($cached = Cache::tags(['ai_tools'])->remember($cacheKey, 300, ...)) {
    return $cached;
}
```

**Tag** **`ai_tools`** para flush quando dados mudam

***

## Checklist Fase 1

- [x] 6 novas tools (`GetLegalizacaoTool`, `GetComiteTool`, `GetNegociacaoTool`, `GetDocumentosTool`, `GetDashboardSummaryTool`, `GetTasksTool`)
- [x] Modificar `SIG_IA::tools()` (registrar 6 tools)
- [x] Modificar `SIG_IA::instructions()` (descrever novas tools)
- [x] Testes unitários para cada nova tool
- [x] Cache Redis para tool outputs

**Esforço:** 5-7 dias
**Impacto:** Médio (adiciona funcionalidade, sem breaking change)

***

# FASE 2 — RAG e Conhecimento Documental

**Objetivo:** Permitir consulta semântica sobre documentos não estruturados.

## 2.1 — Tabelas de RAG

**Arquivos a criar:**

- `database/migrations/tenant/2026_04_13_000001_create_ai_document_chunks_table.php`
- `database/migrations/tenant/2026_04_13_000002_create_ai_document_embeddings_table.php`

```
ai_document_chunks:
- id: bigint PK
- document_id: bigint FK terreno_documentos
- terreno_id: bigint FK terrenos
- chunk_index: int
- content: text
- metadata: json
- created_at, updated_at

ai_document_embeddings:
- id: bigint PK
- chunk_id: bigint FK ai_document_chunks
- embedding: json (vetor de floats)
- provider: string(50)
- model: string(100)
- created_at
- indexes: chunk_id
```

## 2.2 — Modelos

**Arquivos a criar:**

- `app/Models/Tenant/AiDocumentChunk.php`
- `app/Models/Tenant/AiDocumentEmbedding.php`

## 2.3 — Serviço de Embedding

**Arquivo a criar:** `app/Services/AiEmbeddingService.php`

- `generateEmbedding(string $text): array` — usa provider de embeddings (OpenAI/VoyageAI via `config/ai.php`)
- `chunkText(string $text, int $maxTokens = 500): array` — divide texto em chunks
- `storeEmbeddings(int $chunkId, array $embedding): void` — persiste vetores
- `searchSimilar(array $queryVector, int $terrenoId = null, int $limit = 10): Collection` — busca por similaridade
- Sem pgvector: dot product via aplicação + order by no DB
- Se PostgreSQL + pgvector no futuro: query `ORDER BY embedding <=> $vector`

## 2.4 — Job de Indexação

**Arquivo a criar:** `app/Jobs/IndexDocumentEmbeddingJob.php`

- Recebe `document_id`
- Extrai texto do documento (se PDF: `spatie/laravel-pdf`, se texto: `file_get_contents`)
- Chunking via `AiEmbeddingService`
- Gera embeddings (batch)
- Armazena chunks + vetores

**Trigger:** `Documento::created` event → dispatch do job

## 2.5 — Tool de Consulta Documental

**Arquivo a criar:** `app/Ai/Tools/SearchDocumentsTool.php`

- `query` (string, req) — texto da busca
- `terreno_id` (int, optional) — filtro por terreno
- Usa `AiEmbeddingService::searchSimilar()`
- Retorna chunks com relevância + metadados do documento fonte

## 2.6 — Tool de Análise de Documento

**Arquivo a criar:** `app/Ai/Tools/AnalyzeDocumentTool.php`

- `document_id` (int, req)
- Extrai texto, envia ao LLM para análise
- Retorna: resumo, dados extraídos, tipo detectado (matrícula, IPTU, etc.)
- Usa provider de visão (Gemini/GPT-4o-Vision) se for imagem

## 2.7 — Configurar Provider de Embeddings

**Arquivo a modificar:** `config/ai.php`

- Habilitar `caching.embeddings.cache = true`
- Adicionar referência ao VoyageAI ou OpenAI como provider de embeddings default

**Arquivo a modificar:** `.env.example`

```
AI_EMBEDDING_PROVIDER=openai
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
```

***

## Checklist Fase 2

- [x] 2 migrations (chunks + embeddings)
- [x] 2 models
- [x] `AiEmbeddingService`
- [x] `IndexDocumentEmbeddingJob`
- [x] Hook `Documento::created` → dispatch job
- [x] Tool `SearchDocumentsTool`
- [x] Tool `AnalyzeDocumentTool`
- [x] Config embeddings + vars .env
- [x] Testes

**Esforço:** 10-15 dias (depende da complexidade de extração de texto de PDFs)
**Impacto:** Médio/Alto (nova capacidade, requer infra)

***

# FASE 3 — Recomendação Personalizada (Scoring)

**Objetivo:** Score automatizado de priorização de terrenos.

## 3.1 — Tabela de Scores

**Arquivo a criar:** `database/migrations/tenant/2026_04_20_000001_create_ai_recommendation_scores_table.php`

```
ai_recommendation_scores:
- id: bigint PK
- terreno_id: bigint FK terrenos
- score: decimal(5,2) — 0.00 a 100.00
- tier: string(20) — "alta_prioridade"|"media"|"atencao"|"baixa"
- factors: json — variáveis que influenciaram
- version: int — versionamento do score
- created_at, updated_at
- indexes: terreno_id, score DESC, tier
```

## 3.2 — Model

**Arquivo a criar:** `app/Models/Tenant/AiRecommendationScore.php`

## 3.3 — Serviço de Scoring (Heurístico — Fase 3.1)

**Arquivo a criar:** `app/Services/AiScoringService.php`

Algoritmo de scoring ponderado (0-100):

| Fator                 | Peso  | Fonte                                                     |
| --------------------- | ----- | --------------------------------------------------------- |
| Viabilidade aprovada  | 25pts | `Viabilidade::approval_status = 'aprovada'`               |
| Estágio avançado      | 20pts | `Terreno::workflow_stage` posição no pipeline             |
| Recência de dados     | 15pts | `updated_at` recente                                      |
| VGV / Valor           | 15pts | `Viabilidade::resultados_dre['indicadores']['vgv_total']` |
| Completude documental | 10pts | ratio docs necessários/existentes                         |
| Sem pendências        | 10pts | 0 pendências no comitê + 0 legalização atrasada           |
| Responsável atribuído | 5pts  | `responsavel_id` não nulo                                 |

`score()`: retorna 0-100 com array de fatores detalhando contribuição de cada um

## 3.4 — Endpoint de Scoring

**Arquivo a modificar:** `routes/tenant.php` — Adicionar rotas

```php
Route::get('/ai/scoring/{terreno_id}', [AiScoringController::class, 'getScore']);
Route::get('/ai/scoring/ranking', [AiScoringController::class, 'getRanking']);
Route::post('/ai/scoring/recalculate', [AiScoringController::class, 'recalculateAll']);
```

**Arquivo a criar:** `app/Http/Controllers/Api/V1/Tenant/AiScoringController.php`

- `getScore($terrenoId)` — retorna score individual com fatores
- `getRanking()` — top terrenos ordenados por score
- `recalculateAll()` — recalcula todos os scores (job async)

## 3.5 — Command de Recálculo

**Arquivo a criar:** `app/Console/Commands/RecalculateAiScoresCommand.php`

- `php artisan ai:recalculate-scores`
- Itera todos os terrenos do tenant
- Chama `AiScoringService::score($terreno)`
- Salva em `ai_recommendation_scores`
- Executável via scheduler

**Arquivo a modificar:** `routes/console.php` — Adicionar schedule

```php
$schedule->command('ai:recalculate-scores')->daily()->at('02:00');
```

## 3.6 — Tool de Score

**Arquivo a criar:** `app/Ai/Tools/GetTerrenoScoreTool.php`

- `terreno_id` (int, req)
- `recalculate` (bool, optional) — força recálculo
- Retorna score + tier + fatores explicativos

***

## Checklist Fase 3

- [ ] Migration scores
- [ ] Model `AiRecommendationScore`
- [ ] `AiScoringService` (heurístico)
- [ ] `AiScoringController`
- [ ] Command `RecalculateAiScoresCommand`
- [ ] Tool `GetTerrenoScoreTool`
- [ ] 3 rotas novas
- [ ] Schedule no console.php
- [ ] Testes

**Esforço:** 5-7 dias (heurístico) + 10 dias extras (ML supervisionado futuro)
**Impacto:** Alto (decisão de negócio direta)

***

# FASE 4 — Automação Inteligente Assistida

**Objetivo:** Automatizar etapas repetitivas com revisão humana.

## 4.1 — Tabelas de Automação

**Arquivo a criar:** `database/migrations/tenant/2026_05_04_000001_create_ai_automation_tables.php`

```
ai_automation_requests:
- id: bigint PK
- user_id: FK users
- action_type: string(50) — "create_task"|"update_status"|"generate_summary"|"generate_briefing"
- target_type: string(50) — "terreno"|"viabilidade"|"legalizacao"
- target_id: bigint
- payload: json — dados para a ação
- ai_generated_content: text — texto/contexto gerado
- status: string(20) — "pending"|"approved"|"rejected"|"executed"
- created_at, updated_at
- decided_by: FK users nullable
- decided_at: datetime nullable
- executed_at: datetime nullable

ai_feedback:
- id: bigint PK
- user_id: FK users
- entity_type: string(50) — "conversation"|"recommendation"|"automation"|"score"
- entity_id: string(36)
- rating: tinyint — 1-5
- comment: text nullable
- created_at
```

## 4.2 — Models

**Arquivos a criar:**

- `app/Models/Tenant/AiAutomationRequest.php`
- `app/Models/Tenant/AiFeedback.php`

## 4.3 — Serviço de Automação

**Arquivo a criar:** `app/Services/AiAutomationService.php`

- `createRequest(string $action, string $targetType, int $targetId, array $payload): AiAutomationRequest`
- `approveRequest(int $requestId, User $user): void` — executa a ação
- `rejectRequest(int $requestId, User $user): void`
- `executeAction(AiAutomationRequest $request): void` — switch por `action_type`
- `submitFeedback(string $entityType, string $entityId, int $rating): AiFeedback`

## 4.4 — Controllers

**Arquivo a criar:** `app/Http/Controllers/Api/V1/Tenant/AiAutomationController.php`

- `GET /ai/automations/pending` — lista pendentes de aprovação
- `POST /ai/automations/{id}/approve` — aprova e executa
- `POST /ai/automations/{id}/reject` — rejeita
- `POST /ai/automations/suggestions` — solicita sugestões de automação para um terreno

## 4.5 — Rotas

**Arquivo a modificar:** `routes/tenant.php` — Adicionar grupo `/ai/automations`

## 4.6 — Feedback de Conversa

**Arquivo a modificar:** `app/Http/Controllers/Api/V1/Tenant/AiController.php`

- Adicionar método `feedback(Request $request)` — POST `/ai/feedback`
- Salva rating na conversa/mensagem

***

## Checklist Fase 4

- [ ] Migration automação + feedback
- [ ] 2 Models
- [ ] `AiAutomationService`
- [ ] `AiAutomationController`
- [ ] Rotas de automação
- [ ] Endpoint de feedback no `AiController`
- [ ] Testes

**Esforço:** 5-7 dias
**Impacto:** Médio (apenas sugere, não executa automaticamente)

***

# FASE 5 — Análise Preditiva

**Objetivo:** Antecipar riscos com scoring preditivo.

> **Nota:** Esta fase depende dos dados coletados nas fases 0-4. Implementar quando houver 30+ dias de histórico.

## 5.1 — Feature Store (tabela consolidada)

**Arquivo a criar:** `database/migrations/tenant/2026_06_01_000001_create_ai_features_table.php`

- Tabela que materializa features para ML: tempo por estágio, taxa de aprovação, etc.

## 5.2 — Serviço de Predição

**Arquivo a criar:** `app/Services/AiPredictionService.php`

- Fase inicial: regras estatísticas avançadas (sem ML externo)
- `predictWorkflowDelay(int $terrenoId): array` — estima dias até próximo estágio
- `predictApprovalProbability(int $viabilidadeId): array` — probabilidade de aprovação
- `predictStagnationRisk(int $terrenoId): array` — risco de paralisia

## 5.3 — Endpoints

**Arquivo a criar:** `app/Http/Controllers/Api/V1/Tenant/AiPredictionController.php`

- Expõe previsões via API

## 5.4 — Command de Treinamento

**Arquivo a criar:** `app/Console/Commands/AiTrainModelsCommand.php`

- Para fase supervisionada futura (Python microserviço ou PHP com RubixML)

***

## Checklist Fase 5

- [ ] Migration features
- [ ] `AiPredictionService`
- [ ] `AiPredictionController`
- [ ] Command de treino
- [ ] Testes

**Esforço:** 7-10 dias (regras avançadas) + 15-20 dias (ML supervisionado)
**Impacto:** Alto

***

# FASE 6 — Detecção de Anomalias

**Objetivo:** Alerta automático de desvios.

## 6.1 — Tabela de Alertas

**Arquivo a criar:** `database/migrations/tenant/2026_06_15_000001_create_ai_anomaly_alerts_table.php`

```
ai_anomaly_alerts:
- id: bigint PK
- type: string(50) — "financial"|"workflow"|"data_quality"|"usage"
- severity: string(20) — "low"|"medium"|"high"|"critical"
- entity_type: string(50)
- entity_id: bigint
- description: text
- details: json
- detected_at: datetime
- notified_at: datetime nullable
- dismissed_at: datetime nullable
- dismissed_by: FK users nullable
- indexes: type, severity, detected_at
```

## 6.2 — Model

**Arquivo a criar:** `app/Models/Tenant/AiAnomalyAlert.php`

## 6.3 — Serviço de Detecção

**Arquivo a criar:** `app/Services/AiAnomalyDetectionService.php`

Detectores (cada um é um método que retorna array de anomalias):

1. **`detectFinancialAnomalies()`** — viabilidades com margem < 10% ou custo > 2σ da média
2. **`detectWorkflowStagnation()`** — terrenos > 30 dias no mesmo estágio
3. **`detectDataInconsistency()`** — workflow\_stage vs approval\_status inconsistentes
4. **`detectCostSpike()`** — custo de IA do tenant > 3x média semanal
5. **`detectOverdueLegalizacao()`** — etapas de legalização vencidas (já existe comando `notify:overdue-legalizacoes`, estender)

## 6.4 — Command Agendado

**Arquivo a criar:** `app/Console/Commands/DetectAiAnomaliesCommand.php`

- `php artisan ai:detect-anomalies`
- Executa todos os detectores
- Cria alertas em `ai_anomaly_alerts`
- Se `severity >= "high"`: dispara notificação (email via Resend)

**Arquivo a modificar:** `routes/console.php`

```php
$schedule->command('ai:detect-anomalies')->hourly();
```

## 6.5 — Endpoint de Alertas

**Arquivo a criar:** `app/Http/Controllers/Api/V1/Tenant/AiAnomalyController.php`

- `GET /ai/anomalies` — lista alertas (com filtros severity, type)
- `POST /ai/anomalies/{id}/dismiss` — dispensar alerta
- `GET /ai/anomalies/summary` — contagem por tipo/severidade

***

## Checklist Fase 6

- [ ] Migration anomaly\_alerts
- [ ] Model `AiAnomalyAlert`
- [ ] `AiAnomalyDetectionService`
- [ ] Command `DetectAiAnomaliesCommand`
- [ ] `AiAnomalyController`
- [ ] Rotas de anomalias
- [ ] Schedule no console.php
- [ ] Testes

**Esforço:** 5-7 dias
**Impacto:** Alto (prevenção de perdas)

***

# FASE 7 — Analytics Avançado em Linguagem Natural

**Objetivo:** NLQ (Natural Language Query) sobre dados do tenant.

## 7.1 — Semantic Layer

**Arquivo a criar:** `app/Services/AiSemanticService.php`

- Define métricas e dimensões disponíveis para query
- Mapeia NL → query estruturada
- Métricas disponíveis:
  - `terrenos_por_stag`e, `vgv_total`, `taxa_aprovacao`, `tempo_medio_por_etapa`, `custo_medio_viabilidade`

## 7.2 — Controller e Rota

**Arquivo a modificar:** `app/Http/Controllers/Api/V1/Tenant/AiController.php`

- Adicionar método `analytics(Request $request)` — POST `/ai/analytics`
- Recebe pergunta em NL, retorna dados tabulares + narrativa executiva

## 7.3 — Cache de Consultas

- Cache Redis de 1h para queries analíticas frequentes
- Tag `ai_analytics`

***

## Checklist Fase 7

- [ ] `AiSemanticService`
- [ ] Método `analytics` no `AiController`
- [ ] Rota `/ai/analytics`
- [ ] Testes

**Esforço:** 7-10 dias
**Impacto:** Alto (gestão executiva)

***

# Sumário de Todos os Arquivos

## Migrations (Tenant) — 8 arquivos

| Fase | Arquivo                                                       |
| ---- | ------------------------------------------------------------- |
| 0    | `2026_04_06_000001_create_ai_request_logs_table.php`          |
| 2    | `2026_04_13_000001_create_ai_document_chunks_table.php`       |
| 2    | `2026_04_13_000002_create_ai_document_embeddings_table.php`   |
| 3    | `2026_04_20_000001_create_ai_recommendation_scores_table.php` |
| 4    | `2026_05_04_000001_create_ai_automation_tables.php`           |
| 5    | `2026_06_01_000001_create_ai_features_table.php`              |
| 6    | `2026_06_15_000001_create_ai_anomaly_alerts_table.php`        |

## Models (Tenant) — 6 arquivos

| Fase | Arquivo                                       |
| ---- | --------------------------------------------- |
| 0    | `app/Models/Tenant/AiRequestLog.php`          |
| 2    | `app/Models/Tenant/AiDocumentChunk.php`       |
| 2    | `app/Models/Tenant/AiDocumentEmbedding.php`   |
| 3    | `app/Models/Tenant/AiRecommendationScore.php` |
| 4    | `app/Models/Tenant/AiAutomationRequest.php`   |
| 4    | `app/Models/Tenant/AiFeedback.php`            |
| 6    | `app/Models/Tenant/AiAnomalyAlert.php`        |

## Services — 10 arquivos

| Fase | Arquivo                                      |
| ---- | -------------------------------------------- |
| 0    | `app/Services/AiTelemetryService.php`        |
| 0    | `app/Services/AiDataRedactor.php`            |
| 0    | `app/Services/AiProviderRouter.php`          |
| 2    | `app/Services/AiEmbeddingService.php`        |
| 3    | `app/Services/AiScoringService.php`          |
| 4    | `app/Services/AiAutomationService.php`       |
| 5    | `app/Services/AiPredictionService.php`       |
| 6    | `app/Services/AiAnomalyDetectionService.php` |
| 7    | `app/Services/AiSemanticService.php`         |
| 0    | `app/Services/AiFeedbackService.php`         |

## Middleware — 3 arquivos

| Fase | Arquivo                                         |
| ---- | ----------------------------------------------- |
| 0    | `app/Http/Middleware/AiRateLimit.php`           |
| 0    | `app/Http/Middleware/AiBudgetCheck.php`         |
| 0    | `app/Http/Middleware/AiTelemetryMiddleware.php` |

## Tools — 10 arquivos

| Fase | Arquivo                                    |
| ---- | ------------------------------------------ |
| 1    | `app/Ai/Tools/GetLegalizacaoTool.php`      |
| 1    | `app/Ai/Tools/GetComiteTool.php`           |
| 1    | `app/Ai/Tools/GetNegociacaoTool.php`       |
| 1    | `app/Ai/Tools/GetDocumentosTool.php`       |
| 1    | `app/Ai/Tools/GetDashboardSummaryTool.php` |
| 1    | `app/Ai/Tools/GetTasksTool.php`            |
| 2    | `app/Ai/Tools/SearchDocumentsTool.php`     |
| 2    | `app/Ai/Tools/AnalyzeDocumentTool.php`     |
| 3    | `app/Ai/Tools/GetTerrenoScoreTool.php`     |

## Controllers — 4 arquivos (+ modificações)

| Fase | Arquivo                          |
| ---- | -------------------------------- |
| 0    | **Modificar** `AiController.php` |
| 3    | `AiScoringController.php`        |
| 4    | `AiAutomationController.php`     |
| 6    | `AiAnomalyController.php`        |

## Jobs — 1 arquivo

| Fase | Arquivo                                  |
| ---- | ---------------------------------------- |
| 2    | `app/Jobs/IndexDocumentEmbeddingJob.php` |

## Commands — 3 arquivos

| Fase | Arquivo                          |
| ---- | -------------------------------- |
| 3    | `RecalculateAiScoresCommand.php` |
| 5    | `AiTrainModelsCommand.php`       |
| 6    | `DetectAiAnomaliesCommand.php`   |

## Testes — 5+ arquivos

| Fase | Arquivo                                            |
| ---- | -------------------------------------------------- |
| 0    | `tests/Unit/AiTelemetryServiceTest.php`            |
| 0    | `tests/Feature/Api/V1/Tenant/AiControllerTest.php` |
| 0    | `tests/Unit/AiToolsTest.php`                       |
| 0    | `tests/Feature/Middleware/AiRateLimitTest.php`     |
| 0    | `tests/Feature/Middleware/AiBudgetCheckTest.php`   |

## Modificações em Arquivos Existentes

| Arquivo                                               | Mudança                                                                 | Fase       |
| ----------------------------------------------------- | ----------------------------------------------------------------------- | ---------- |
| `app/Ai/Agents/SIG_IA.php`                            | Novas tools no `tools()`, fallback provider, prompt atualizado          | 0, 1       |
| `app/Http/Controllers/Api/V1/Tenant/AiController.php` | Headers telemetria, fallback, feedback, analytics                       | 0, 4, 7    |
| `routes/tenant.php`                                   | Novas rotas (scoring, automations, anomalies, analytics), novo throttle | 3, 4, 6, 7 |
| `routes/api.php`                                      | Rate limiter `ai-chat`                                                  | 0          |
| `routes/console.php`                                  | Schedules (scores, anomaly, train)                                      | 3, 5, 6    |
| `bootstrap/app.php`                                   | Aliases de middleware                                                   | 0          |
| `config/ai.php`                                       | Embeddings caching, novos provider settings                             | 2          |
| `.env.example`                                        | Novas vars de ambiente                                                  | 0, 2       |

***

# Cronograma Consolidado

| Fase               | Semana       | Duração    | Dependência              |
| ------------------ | ------------ | ---------- | ------------------------ |
| 0 — Hardening      | Semanas 1-2  | 5-7 dias   | Nenhuma                  |
| 1 — Expansão Tools | Semanas 2-3  | 5-7 dias   | Fase 0                   |
| 2 — RAG            | Semanas 3-5  | 10-15 dias | Fase 0, 1                |
| 3 — Scoring        | Semanas 4-5  | 5-7 dias   | Fase 0                   |
| 4 — Automação      | Semanas 5-6  | 5-7 dias   | Fase 0, 1, 3             |
| 5 — Preditivo      | Semanas 8-10 | 7-20 dias  | 30+ dias de dados de 0-4 |
| 6 — Anomalias      | Semanas 6-7  | 5-7 dias   | Fase 0                   |
| 7 — Analytics NL   | Semanas 8-10 | 7-10 dias  | Fase 0, 1                |

**Total estimado:** 8-10 semanas para Fases 0-6, +2-4 semanas para Fase 5 (ML supervisionado)

***

# Verificação End-to-End

## Fase 0

```bash
# Telemetria funcionando
php artisan test tests/Feature/Api/V1/Tenant/AiControllerTest.php
# Verificar no banco
SELECT * FROM ai_request_logs ORDER BY created_at DESC LIMIT 10;
# Rate limit
curl -X POST /api/v1/ai/sig-ai -H "Authorization: Bearer $TOKEN" -d '{"message":"teste"}' (30x em 1 min → 429)
# Budget
Simular custo > $10 → verificar 402
```

## Fase 1

```bash
# No chat do SIG_IA, perguntar:
# "Qual a situação da legalização do terreno 123?"
# "Quais são as pendências do comitê?"
# "Liste minhas tarefas vencidas"
# Verificar que as tools novas são chamadas
```

## Fase 3

```bash
# Recalcular scores
php artisan ai:recalculate-scores
# Verificar ranking
curl /api/v1/ai/scoring/ranking
# Score deve ser 0-100 com fatores detalhando
```

## Fase 6

```bash
# Detectar anomalias
php artisan ai:detect-anomalies
# Verificar alertas
curl /api/v1/ai/anomalies
# Deve retornar alertas com tipo, severidade, descrição
```

***

# Configuração `.env.example` — Adições

```env
# ==========================================
# AI — Novas configurações (Fases 0-7)
# ==========================================

# Fallback provider
AI_FALLBACK_PROVIDER=anthropic
AI_FALLBACK_AGENT_MODEL=claude-sonnet-4-6

# Budget por tenant (USD/mês)
AI_TENANT_BUDGET_DEFAULT=10.00

# Rate limit de IA (req/min por usuário)
AI_RATE_LIMIT_PER_MINUTE=30

# Embeddings (Fase 2)
AI_EMBEDDING_PROVIDER=openai
OPENAI_EMBEDDING_MODEL=text-embedding-3-small

# Preço por 1M tokens (para estimativa de custo)
AI_OPENROUTER_INPUT_PRICE_PER_M=0.00
AI_OPENROUTER_OUTPUT_PRICE_PER_M=0.00
AI_ANTHROPIC_INPUT_PRICE_PER_M=3.00
AI_ANTHROPIC_OUTPUT_PRICE_PER_M=15.00
AI_OPENAI_EMBEDDING_PRICE_PER_M=0.02
```

***

# Observações Finais

1. **Nenhuma fase requer reescrita** do módulo atual — tudo é aditivo
2. **Fase 5 (ML supervisionado)** depende de dados históricos — iniciar com regras estatísticas antes
3. **RAG (Fase 2)** é o maior esforço individual — avaliar se pgvector vale o investimento vs. aplicação-level similarity
4. **Testes devem ser escritos junto** — cada PR de feature deve incluir testes
5. **Padrão de código:** Seguir PSR-2 via `./vendor/bin/pint`, PHPStan nível 8
6. **Commits pequenos e frequentes** — 1 PR por sub-fase, não tudo de uma vez

