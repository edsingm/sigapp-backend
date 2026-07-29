# Catálogo SIG IA (canônico)

**Atualizado:** 2026-07-29 (Fases 0–4)

## Chat (`SIG_IA::tools`)

Oito **meta-tools** em `app/Services/Ai/Tools/Meta/`:

| Meta-tool | Actions |
|-----------|---------|
| SearchPortfolio | list, dashboard, ranking |
| GetTerreno | details, geo, score, predict_viability, estimate_vgv |
| GetTerrenoProcess | viabilidades, legalizacao, comite, negociacao |
| GetDocumentsHub | list, search |
| GetTasksHub | (filtros diretos) |
| AnalyzePortfolio | monitor, anomalies, stalling, analytics |
| MarketIntel | ibge, empreendimentos |
| ExportPdf | terreno_report, custom |

Envelope de resposta: `{ ok, code, message, data }`.

## Tools granulares

Permanecem para **delegação interna**, jobs e relatório de terreno. Não registrar no chat.

## UI only (não no agente)

- `CreateTaskTool`
- `UpdateTaskStatusTool`
- `TransitionWorkflowTool`

Mutações de domínio ficam na interface do produto.

## Removidas (órfãs de leitura, Fase 4)

Substituídas por tools canônicas:

| Removida | Canônica |
|----------|----------|
| GetDocumentosTool | DocumentosTool |
| AnalyzeDocumentTool | DocumentosTool (document_id) |
| GenerateInsightsTool | AnalyticsTool type=insights |
| GetTrendsTool | AnalyticsTool type=trends |
| CompareAreasTool | AnalyticsTool type=compare |

## Telemetria

`AiToolCallTelemetry` enriquece `ai_request_logs.tool_calls` com:

- `tool`, `input` (redigido), `step`
- `code` (envelope), `successful`, `result_bytes`, `duration_ms`, `error`

Agregação: `AiTelemetryService::getToolUsageStats()` / `getUsageStats()['tools']`.
