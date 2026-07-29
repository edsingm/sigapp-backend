# Plano de melhorias — Tools do agente SIG IA

**Data:** 2026-07-29  
**Escopo:** backend Laravel — `app/Services/Ai/Agents/SIG_IA.php` e `app/Services/Ai/Tools/*`  
**Origem:** análise de 22 tools registradas + tools órfãs  
**Modo:** plano de execução (não é implementação)

---

## 1. Objetivo

Tornar o SIG IA mais **preciso, barato, seguro e previsível** nas tool calls, sem expandir o catálogo de tools de forma descontrolada.

### Metas mensuráveis

| Meta | Baseline (hoje) | Alvo |
|------|-----------------|------|
| Tools efetivas no prompt do agente | 22 | ≤ 12 (fase 2) ou 8 meta-tools (fase 3 opcional) |
| Steps médios em fluxo “analisa terreno + PDF” | frequentemente > 8 ou truncado | ≤ 8 com sucesso |
| Schemas com `required` + `description` | minoria | 100% das tools ativas |
| Envelope de resposta unificado | não | 100% das tools ativas |
| Listagens com scope de policy `view` | inconsistente | 100% das listagens de domínio |
| PDF no chat com auth | sem Gate | Gate + feature/quota + limite de HTML |
| JSON tool-result | `PRETTY_PRINT` | compacto |
| Payload médio de deep-dive de terreno | grande / full | `summary` default; `full` sob demanda |

### Fora de escopo (neste plano)

- Refator da UI do chat (já coberto por SIG-2).
- Treinar/fine-tune de modelo.
- Novas tools de escrita (criar tarefa, transicionar workflow) — permanecem proibidas no chat, salvo decisão de produto futura.
- Reescrever algoritmos preditivos do zero (apenas contrato/disclaimer e payloads).

---

## 2. Princípios

1. **Menos tools, mais modos** — preferir parâmetros `mode`/`include_*` a N tools sobrepostas.
2. **Contrato estável** — schema rico na entrada; envelope único na saída.
3. **Auth-first** — plan feature + Gate + scope; deny explícito no envelope.
4. **Payload magro** — default `summary`; full só com flag.
5. **Honestidade da “IA”** — heurística rotulada; preditivos com confidence/disclaimer.
6. **Mudanças cirúrgicas por fase** — cada fase com testes e critério de aceite; deploy independente.

---

## 3. Visão por fases

```
Fase 0  Fundação (contrato, schema, JSON, auth helper)
   │
Fase 1  Segurança + PDF confiável
   │
Fase 2  Payloads + desambiguação + budget do agente
   │
Fase 3  (Opcional) Meta-tools / consolidação do catálogo
   │
Fase 4  Observabilidade, limpeza de órfãs, polish
```

Estimativa relativa (backend, 1 dev familiarizado):

| Fase | Esforço | Risco | Valor |
|------|---------|-------|-------|
| 0 | 2–3 dias | Baixo | Alto |
| 1 | 2–3 dias | Médio | Alto |
| 2 | 3–5 dias | Médio | Alto |
| 3 | 5–8 dias | Alto | Muito alto (se P0–P2 ok) |
| 4 | 1–2 dias | Baixo | Médio |

---

## 4. Fase 0 — Fundação de contrato

**Objetivo:** toda tool ativa fala a mesma “língua” com o LLM.

### 4.1 Envelope padrão de resposta

Criar helper (ex.: `App\Services\Ai\Tools\AiToolResponse`) que serializa:

```json
{
  "ok": true,
  "code": "OK",
  "message": "string curta opcional",
  "data": {}
}
```

Códigos obrigatórios:

| code | Uso |
|------|-----|
| `OK` | sucesso com dados |
| `EMPTY` | consulta válida, zero resultados |
| `DENIED` | Gate/permission |
| `PLAN_DENIED` | feature de plano ausente |
| `VALIDATION` | parâmetro inválido/ausente |
| `ERROR` | falha técnica |

**Regras**

- Erros e vazios **não** misturam string solta com JSON.
- `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` — **sem** `JSON_PRETTY_PRINT`.
- `RedactingToolDecorator` continua no output (já redige JSON e texto).

### 4.2 Schema pattern obrigatório

Para cada campo de cada tool ativa:

- `description()` em pt-BR (o que é e quando usar)
- `required()` nos obrigatórios
- enums para códigos de domínio (`workflow_stage`, `workflow_status_code`, `type`, `category`, `focus_area`, `approval_status`, etc. quando fechados)
- `limit`: integer com default documentado e clamp server-side (ex. 1–50)

Prioridade de schemas nesta fase (todas as 22, começando pelas piores):

1. `ListTerrenosTool`
2. `GetViabilidadesTool`
3. `ProactiveMonitorTool`
4. `DetectAnomaliesTool`
5. `GetCityIbgeProfileTool`
6. `GetTasksTool`
7. `GetTerrenoDetailsTool`
8. Demais tools de leitura
9. `CreatePdfsTool` (já bom — só alinhar ao envelope)

### 4.3 Auth helper

Criar `AiToolAuth` (ou nome equivalente) com métodos do tipo:

- `assertViewAny(string $modelClass): ?string` → null ou code DENIED message
- `assertView($model): ?string`
- `assertFeature(string $featureKey): ?string` → PLAN_DENIED
- opcional: `scopeTerrenos(Builder $q): Builder` para listagens

Migrar tools gradualmente **nesta fase** para o helper (sem mudar regras de negócio).

### 4.4 `total` honesto

Em listagens:

```json
"meta": {
  "returned": 10,
  "limit": 10,
  "total": 143,
  "has_more": true
}
```

Aplicar em: List, Viabilidades, Legalização, Comitê, Negociação, Documentos, Tasks, Ranking (quando couber).

### 4.5 Entregáveis Fase 0

- [x] `AiToolResponse` (+ testes unitários) — 2026-07-29
- [x] `AiToolAuth` (+ testes) — 2026-07-29
- [x] Migração de **todas** as 22 tools para envelope + JSON compacto — 2026-07-29
- [x] Schemas enriquecidos nas 22 tools — 2026-07-29
- [x] `total`/`returned`/`has_more` nas listagens — 2026-07-29
- [x] Testes de contrato atualizados (`AiToolsDataContractTest`, `AiToolsTest`) — 2026-07-29

### 4.6 Critério de aceite Fase 0

- Tool call simulada com filtro vazio → `code=EMPTY`, nunca string solta.
- Deny de Gate → `code=DENIED`.
- Deny de plano → `code=PLAN_DENIED`.
- Payload serializado sem newlines de pretty-print.
- Suite unitária de tools verde.

---

## 5. Fase 1 — Segurança e PDF no chat

**Objetivo:** fechar buracos de auth e tornar PDF gerável com previsibilidade.

### 5.1 Hardening de autorização

| Tool | Ação |
|------|------|
| Listagens (terrenos, docs, viab, etc.) | aplicar scope de policy / filtro por terrenos visíveis |
| `DocumentosTool` list | não retornar docs de terrenos sem `view` |
| `SearchDocumentsTool` | filtrar retrieval por terrenos permitidos; Gate adequado |
| `GetTasksTool` | usar policy de Task se existir; senão documentar e amarrar a terreno visível |
| `CreatePdfsTool` | ver 5.2 |
| `PesquisarEmpreendimentos…` | rate limit por tenant/user + timeout (mesmo sem Gate de domínio) |

### 5.2 `CreatePdfsTool` confiável

1. **Auth**
   - Usuário autenticado obrigatório.
   - Se `terreno_id` > 0: `Gate::view` no terreno.
   - Feature de plano (se existir chave AI/reports; senão criar checagem coerente com plan matrix).
2. **Limites**
   - Max length de `html_content` (ex. 150_000 chars).
   - Rate limit (ex. N PDFs / hora / tenant).
3. **Resposta**
   - Envelope: `data.url`, `data.report_id`, `data.filename`, `data.bytes`.
   - Remover dependência de o modelo “copiar” markdown com emoji; o agente formata a mensagem final.
4. **Infra (ops)**
   - Garantir `BROWSERSHOT_NO_SANDBOX` no compose de dev se container roda root.
   - Smoke documentado: gerar 1 PDF no container.

### 5.3 Caminho preferencial de relatório (recomendado nesta fase ou início da 2)

Nova capacidade (pode ser método interno ou tool):

**`CreateTerrenoReportTool`** (ou modo em `CreatePdfsTool`):

- Input: `terreno_id`, `sections[]` (resumo, viabilidade, comitê, legalização, score…)
- Backend monta HTML a partir dos dados reais
- LLM não precisa gerar HTML gigante

Isso reduz steps e alucinação de layout.

### 5.4 Entregáveis Fase 1

- [x] Scopes de listagem + checagem de terreno em filtros `terreno_id` + filterByView — 2026-07-29
- [x] PDF com auth + limites HTML + rate limit + envelope — 2026-07-29
- [x] Report por `terreno_id` server-side (`CreateTerrenoReportTool`) — 2026-07-29
- [x] Rate limit mercado externo — 2026-07-29
- [x] Instruções do SIG_IA: `data.url` + preferência CreateTerrenoReportTool — 2026-07-29
- [x] `BROWSERSHOT_NO_SANDBOX=true` no docker-compose dev — 2026-07-29

### 5.5 Critério de aceite Fase 1

- Usuário sem permissão no terreno X não lista docs/viab de X e não gera PDF de X.
- PDF happy path devolve `ok=true` + URL válida.
- Chrome ausente → `code=ERROR` com message de infra (sem stack sensível no chat se possível).
- Testes de segurança AI tools verdes.

---

## 6. Fase 2 — Payloads, desambiguação e budget

**Objetivo:** caber análises reais em `MaxSteps(8)` e `MaxTokens` razoável.

### 6.1 Modos de payload

| Tool | Mudança |
|------|---------|
| `GetTerrenoDetailsTool` | `mode=summary\|full` (default `summary`); flags `include_viabilidades`, `include_negociacao`, `include_contrato` |
| `GetViabilidadesTool` | default `somente_atual=true` quando `terreno_id` presente; `include_dre=summary\|full` |
| `GetLegalizacaoTool` | resumo de etapas + `include_etapas=true` |
| `GetNegociacaoTool` | **não** devolver `payload_json` cru; eventos sumarizados |
| `GetTerrenoGeoAnalysisTool` | manter; documentar radius min/max; cache se viável |
| `GetDashboardSummaryTool` | VGV sem carregar todas as rows em PHP; SQL/cache; alinhar a API de dashboard se possível |

### 6.2 `ListTerrenosTool` mais usável pelo LLM

- Filtro por **nome de cidade** (além de `cidade_code`)
- Opcionais: `somente_parados` (ex. updated_at > N dias), `order_by`
- Meta de paginação (Fase 0)

### 6.3 Desambiguação monitor / anomalias / stalling / dashboard

**Opção preferida (menor breaking change):** enriquecer `description()` + instruções estáticas:

| Intent do usuário | Tool |
|-------------------|------|
| “o que está parado **agora**” | `ProactiveMonitorTool` |
| “risco de **travar** no futuro” | `PredictStallingTool` |
| “dados inconsistentes / duplicados / VGV estranho” | `DetectAnomaliesTool` |
| “números da carteira / totais” | `GetDashboardSummaryTool` |
| “conversão, tendências, compare” | `AnalyticsTool` |

**Opção alternativa:** `PortfolioHealthTool(mode=monitor|anomalies|stalling|summary)` — pode ser feito na Fase 3.

### 6.4 Honestidade das análises

| Tool | Ajuste de payload |
|------|-------------------|
| `DocumentosTool` | renomear `ai_analysis` → `heuristica_tipo` (ou campo `source=rule`) |
| `PredictViabilityTool`, `EstimateVgvTool`, `PredictStallingTool` | `confidence`, `sample_size`/`basis`, `disclaimer`, `as_of` |
| `GetTerrenoScoreTool` | já explica fatores — garantir campos estáveis |

Instruções: proibir apresentar preditivos como valor contábil oficial.

### 6.5 Budget do agente

Avaliar após 6.1–6.4 (medir steps reais):

| Parâmetro | Hoje | Ajuste sugerido |
|-----------|------|-----------------|
| `MaxSteps` | 8 | manter 8 se deep-dive/report server-side; senão 10–12 |
| `MaxTokens` | 2048 | 3072–4096 se respostas analíticas longas + PDF |
| Nº tools no prompt | 22 | reduzir descrições duplicadas; preparar Fase 3 |

### 6.6 Entregáveis Fase 2

- [x] Modes summary/full nas tools pesadas — 2026-07-29
- [x] Negociação sem payload cru — 2026-07-29
- [x] Dashboard VGV eficiente (PG SQL / pluck fallback) — 2026-07-29
- [x] List com cidade por nome + somente_parados + order_by — 2026-07-29
- [x] Disclaimers preditivos + rename heurística docs — 2026-07-29
- [x] Instruções anti-overlap atualizadas — 2026-07-29
- [x] Budget: MaxSteps=8 mantido; MaxTokens 2048→3072 — 2026-07-29

### 6.7 Critério de aceite Fase 2

- “Analisa o terreno {id}” completa com ≤ 5 tool calls em summary.
- “Gera PDF do terreno {id}” com report server-side ≤ 3 tool calls (ideal: 1–2).
- Nenhuma tool preditiva sem `disclaimer` no JSON.
- Testes de payload/regression verdes.

---

## 7. Fase 3 — Consolidação (opcional, alto impacto)

**Só iniciar se Fases 0–2 estiverem estáveis em produção/homolog.**

### 7.1 Catálogo alvo (~8 meta-tools)

| Meta-tool | Substitui / absorve |
|-----------|---------------------|
| `SearchPortfolio` | ListTerrenos + Dashboard + Ranking (modos) |
| `GetTerreno` | Details + Geo + Score (flags) |
| `GetTerrenoProcess` | Viabilidade + Legalização + Comitê + Negociação |
| `GetDocuments` | Documentos + SearchDocuments |
| `GetTasks` | GetTasks |
| `AnalyzePortfolio` | Monitor + Anomalies + Stalling + Analytics |
| `MarketIntel` | IBGE + Empreendimentos |
| `ExportPdf` | CreatePdfs + report templates |

### 7.2 Estratégia de migração

1. Implementar meta-tools **por baixo** chamando services existentes.
2. Registrar meta-tools no `SIG_IA` e **desregistrar** granulares.
3. Manter classes granulares privadas/internas se úteis a jobs/reports.
4. Atualizar testes de registro (`test_sig_ai_registers_all_tools`).
5. Reescrever `staticInstructions()` para o catálogo novo (mais curto).

### 7.3 Critério de aceite Fase 3

- ≤ 8 tools no `tools()`.
- Mesma cobertura funcional dos fluxos P0 de produto (carteira, terreno, docs, preditivo, mercado, PDF).
- Latência/tool-error rate ≤ baseline da fase 2.

### 7.4 Entregáveis Fase 3 (implementado 2026-07-29)

- [x] Meta-tools em `app/Services/Ai/Tools/Meta/` (8): SearchPortfolio, GetTerreno, GetTerrenoProcess, GetDocumentsHub, GetTasksHub, AnalyzePortfolio, MarketIntel, ExportPdf
- [x] Delegação às tools granulares (mantidas para jobs/testes internos)
- [x] `SIG_IA::tools()` só registra as 8 meta-tools
- [x] Instruções reescritas para catálogo consolidado + `action`
- [x] Testes: `MetaToolsTest`, registro AiTools, embedding RAG hub

---

## 8. Fase 4 — Observabilidade e limpeza

### 8.1 Telemetria

Além de `ToolCall` name/input redigido:

- `code` do envelope (OK/EMPTY/DENIED/…)
- duração ms
- tamanho do output (bytes)
- (opcional) step index na run

Dashboard mínimo: top tools, top codes de erro, p95 latency.

### 8.2 Limpeza de órfãs

| Tool órfã | Ação |
|-----------|------|
| `GetDocumentosTool`, `AnalyzeDocumentTool` | deprecar → remover após Fase 0/2 se só `DocumentosTool` for canônica |
| `GenerateInsightsTool`, `GetTrendsTool`, `CompareAreasTool` | deprecar → `AnalyticsTool` only |
| `CreateTaskTool`, `UpdateTaskStatusTool`, `TransitionWorkflowTool` | manter no repo **fora** do agente até decisão de produto; documentar “UI only” |

Atualizar testes que ainda mockam órfãs de leitura (`TerrenoAiReportServiceTest` etc.).

### 8.3 Testes de qualidade contínuos

- Contrato de envelope por tool.
- Isolation test multi-user.
- Budget test: pergunta fixa não excede K steps (feature com fake LLM se possível).
- Teto de tamanho de payload (assert em summary mode).

### 8.4 Entregáveis Fase 4 (implementado 2026-07-29)

- [x] `AiToolCallTelemetry` — code, step, result_bytes, duration_ms, successful/error
- [x] `AiController` grava telemetria enriquecida; `getUsageStats()['tools']` + `getToolUsageStats()`
- [x] Remoção de órfãs de leitura: GetDocumentos, AnalyzeDocument, GenerateInsights, GetTrends, CompareAreas
- [x] Report de terreno usa DocumentosTool + AnalyticsTool; `toolArray` unwrap envelope
- [x] Write tools documentadas `@deprecated` UI only
- [x] Catálogo canônico: `docs/2026-07-29-ai-tools-catalog.md`
- [x] Testes: `AiToolCallTelemetryTest`, `MetaToolsEnvelopeContractTest`

---

## 9. Ordem de implementação sugerida (checklist executável)

### Sprint A — Fundação (Fase 0)

1. `AiToolResponse` + testes
2. `AiToolAuth` + testes
3. Migrar 5 tools críticas (List, Details, Viabilidades, Documentos, CreatePdfs)
4. Migrar restante das 22
5. Schemas ricos
6. Meta total/returned

**Verify:** `php artisan test --filter=AiTools`

### Sprint B — Segurança + PDF (Fase 1)

1. Scopes listagens + testes isolation
2. PDF auth + limites + envelope
3. Report server-side por terreno (recomendado)
4. Rate limit mercado
5. Instruções SIG_IA

**Verify:** security tests + smoke PDF no container

### Sprint C — Payloads e clareza (Fase 2)

1. summary/full + flags
2. Dashboard VGV
3. Negociação slim
4. Preditivos com disclaimer
5. Instruções anti-overlap
6. Ajuste MaxSteps/MaxTokens se medição pedir

**Verify:** cenários manuais de chat + testes de payload

### Sprint D — (Opcional) Meta-tools (Fase 3)

1. 2–3 meta-tools piloto (`GetTerreno`, `ExportPdf`, `AnalyzePortfolio`)
2. Troca no agente
3. Restante do catálogo
4. Instruções compactas

### Sprint E — Polish (Fase 4)

1. Telemetria
2. Remover órfãs de leitura
3. Docs internas + tickets fechados

---

## 10. Riscos e mitigações

| Risco | Mitigação |
|-------|-----------|
| Quebrar consumidores que parseiam string solta | envelope em tudo; buscar parses no backend/frontend de tool results |
| Aumentar tokens com envelope | JSON compacto; summary default — ganho líquido |
| Report server-side engessado | `sections[]` configurável |
| Meta-tools (Fase 3) regressão grande | só após 0–2; feature flag de catálogo se necessário |
| MaxSteps baixo demais | report server-side + summary modes antes de subir steps |

---

## 11. Dependências e ambientes

- Chromium + `BROWSERSHOT_*` no Docker (dev e prod).
- Disco `s3` (ou compatível) para PDFs.
- Plan matrix keys existentes (`viabilities.enabled`, `committee`, `legalizations`, `negotiation`, storage).
- Confirmar se existe / criar feature key para AI reports.

---

## 12. Critérios de “plano concluído” (produto)

O plano pode ser considerado **entregue** (sem Fase 3) quando:

1. Chat gera PDF de terreno com link válido e auth correta.
2. “Analisa terreno X” responde com dados reais em summary sem estourar steps.
3. Deny de permissão/plano retorna código estruturado (não alucinação).
4. Não há `JSON_PRETTY_PRINT` nas tools ativas.
5. Preditivos e heurísticas de doc não se passam por fato contábil.
6. Testes AI tools + security verdes no CI.

Fase 3 é **melhoria estrutural opcional** se ainda houver confusão de intent ou custo alto de tool schemas.

---

## 13. Mapeamento YouTrack (sugestão de issues)

| ID sugerido | Título | Fase |
|-------------|--------|------|
| SIG-x1 | SIG IA tools: envelope + JSON compacto + schemas | 0 |
| SIG-x2 | SIG IA tools: AiToolAuth + scopes de listagem | 0–1 |
| SIG-x3 | SIG IA: hardening CreatePdfsTool + report por terreno | 1 |
| SIG-x4 | SIG IA: payloads summary/full + dashboard VGV | 2 |
| SIG-x5 | SIG IA: disclaimers preditivos + anti-overlap instruções | 2 |
| SIG-x6 | SIG IA: consolidação meta-tools (opcional) | 3 |
| SIG-x7 | SIG IA: telemetria de tool codes + limpeza órfãs | 4 |

*(IDs reais a criar no YouTrack quando for priorizar.)*

---

## 14. Decisões que precisam de produto (antes ou durante)

1. **Report server-side** é o caminho oficial de PDF de terreno? (recomendado: sim)
2. **Meta-tools (Fase 3)** entra no roadmap ou só P0–P2?
3. Tools de **escrita** (tarefa/workflow) permanecem UI-only? (recomendado: sim)
4. Feature key de plano para **AI / PDF** — qual nome e em quais planos?

---

## 15. Referências de código

- Agente: `app/Services/Ai/Agents/SIG_IA.php`
- Tools: `app/Services/Ai/Tools/*`
- Decorator: `RedactingToolDecorator.php`
- Chat/stream: `app/Http/Controllers/Api/V1/Tenant/AiController.php`
- Testes: `tests/Unit/AiToolsTest.php`, `AiToolsSecurityTest.php`, `AiToolsDataContractTest.php`
- Análise origem: conversa 2026-07-29 (tools SIG IA)

---

**Próximo passo de execução:** abrir Sprint A (Fase 0) ou criar as issues no YouTrack e puxar SIG-x1.
