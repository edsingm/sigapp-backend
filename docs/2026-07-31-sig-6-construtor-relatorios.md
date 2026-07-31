# SIG-6 — Construtor de relatórios profissionais (PDF / Excel)

**Data:** 2026-07-31  
**Ticket:** [SIG-6](https://sigapp.youtrack.cloud/issue/SIG-6)  
**Escopo:** backend (`reports.builder`) + inventário de exports existentes

## Situação anterior

O frontend já tinha uma página de construtor. No backend existia um MVP:

| Peça | Estado |
|---|---|
| Templates (`report_templates`) | OK |
| Runs assíncronos (`report_runs` + `GenerateReportRunJob`) | OK |
| Catálogo fechado | 4 datasets, métricas parciais |
| Formatos | **somente CSV** |
| Métricas | `count` de fato; `sum_valor` listado mas não materializado |
| Datasets | terrenos, viabilidades, comites, legalizacoes |
| Inventário de exports especializados | não exposto ao frontend |

## O que o backend já exportava (fora do construtor)

| Recurso | Formato | Rota / pipeline | Feature |
|---|---|---|---|
| Listagem de terrenos | PDF | `GET /terrenos/export/pdf` + async `exports` | `exports.pdf` |
| Listagem de terrenos | Excel | `GET /terrenos/export/excel` + async `exports` | `exports.excel` |
| Ficha de terreno | PDF | `GET /terrenos/{id}/export/pdf-detalhe` | `exports.pdf` |
| Checklist de fechamento | PDF | `POST /terrenos/{id}/export/check-list` | `exports.pdf` |
| Viabilidade (DRE/indicadores) | PDF | `GET /viabilidades/{id}/export-pdf` | `exports.pdf` |
| Relatório narrativo IA (terreno) | PDF | `POST /ai/terrenos/{id}/relatorio-pdf` (+ job) | `exports.pdf` + IA |
| Dossiê de comitê (IA) | JSON seccionado | `/comite/revisoes/{id}/ai-dossier` | committee |
| Pipeline genérico assíncrono | PDF/XLSX | `POST /api/v1/exports` | por tipo |

**Ainda sem export dedicado (só dados via API):** negociações/deal room, reuniões de comitê, legalização Gantt/checklist em lote, projetos (planning), comparação de viabilidades.

## Entrega SIG-6 (backend)

### 1. Catálogo público

`GET /api/v1/reports/catalog` (feature `reports.builder`)

Retorna:

- **datasets** com dimensões, métricas e formatos recomendados
- **formats** (`csv`, `xlsx`, `pdf`) com orientação de uso
- **charts** (`table`, `bar`, `line`) — dicas de UI
- **predefined_exports** — inventário dos exports especializados
- **recommendations** — sugestões de relatórios (PDF vs Excel)

Fonte de verdade: `App\Services\Tenant\ReportCatalogService`.

### 2. Datasets do construtor

| Dataset | Tabela | Dimensões | `sum_valor` |
|---|---|---|---|
| `terrenos` | `terrenos` | workflow_status_code, estado, created_at | `valor` |
| `viabilidades` | `viabilidades` | status, created_at | `parceria_vgv` |
| `comites` | `comite_revisoes` | status, final_decision, created_at | — |
| `legalizacoes` | `legalizacoes` | status, created_at | — |
| `negociacoes` | `negociacoes` | status, business_model, created_at | `proposal_value` |
| `comite_reunioes` | `comite_meeting_sessions` | status, meeting_mode, created_at | — |
| `projetos` | `projetos` | status, created_at | — |

Regras:

- JSON do template **nunca** vira SQL livre
- validação por dataset (métrica/dimensão inválida → 422)
- geração usa o **primeiro** dataset da definition (agregação `GROUP BY`, limite 500 grupos)
- artefato privado no disk `s3`, contando `storage_gb`, expira em 24h

### 3. Formatos de saída do run

| Format | MIME | Uso |
|---|---|---|
| `csv` | `text/csv` | snapshot leve / BI |
| `xlsx` | spreadsheetml | análise gerencial, pivôs |
| `pdf` | `application/pdf` | comitê, diretoria, arquivo |

`POST /api/v1/reports/runs` aceita `format: csv|xlsx|pdf`.

Download: `relatorio-{id}.{csv|xlsx|pdf}`.

## Sugestões: o que exportar em PDF vs Excel

### Preferir **Excel (xlsx)**

- Pipeline de terrenos por status/UF com soma de valor
- Carteira de viabilidades × VGV
- Book de negociações (status × modelo × proposal_value)
- Andamento de legalizações (volume por status)
- Consolidados para importar em BI ou planilha da diretoria

### Preferir **PDF**

- Snapshot executivo do funil (terrenos + viabilidades + comitês)
- Dossiê de decisões de comitê / reuniões
- Ata consolidada de sessões (modo online/presencial)
- Portfólio de projetos para stakeholders
- Documentos “congelados” para arquivo e compartilhamento

### Preferir **CSV**

- Integrações e data lake
- Snapshots as-of leves

### Preferir **export especializado** (não o construtor)

- Ficha completa de um terreno → `terreno_detail_pdf`
- DRE de uma viabilidade → `viabilidade_pdf`
- Narrativa com mapa/IA → `ai_terreno_report_pdf`
- Checklist de fechamento → `terreno_checklist_pdf`

O construtor cobre **agregações configuráveis**; os exports especializados cobrem **documento rico de um recurso**.

## Wave 2 (2026-07-31) — detail + multi-dataset + system templates

### Modos

| Mode | Comportamento | Limite |
|---|---|---|
| `aggregate` (default) | `GROUP BY` dimensão + métricas | 500 grupos/dataset |
| `detail` | linhas com colunas allowlisted | 2000 linhas/dataset |

### Multi-dataset

- Até **4 datasets** por template
- PDF: **capítulos** por dataset
- Excel: **uma aba por dataset** (`ReportRunWorkbookExport`)
- CSV: seções separadas com cabeçalho `section`

### Templates de sistema

Seed idempotente via `ReportTemplateService::ensureSystemTemplates()` (também no `TenantSeeder` e no list/catalog):

| system_key | Nome | Preferência |
|---|---|---|
| `funil_executivo` | Funil executivo | pdf multi |
| `pipeline_terrenos` | Pipeline de terrenos | xlsx |
| `book_negociacoes` | Book de negociações | xlsx detail |
| `andamento_legalizacoes` | Andamento de legalizações | xlsx |
| `reunioes_comite` | Reuniões de comitê | pdf |
| `carteira_viabilidades` | Carteira de viabilidades | xlsx |

System templates: `is_system=true`, não editáveis/excluíveis pelo usuário.

### Definition (schema)

```json
{
  "mode": "aggregate|detail",
  "datasets": ["terrenos", "viabilidades"],
  "dimensions": ["status", "workflow_status_code"],
  "metrics": ["count", "sum_valor"],
  "columns": ["id", "nome", "valor"],
  "charts": ["table", "bar"]
}
```

- `aggregate`: `dimensions` + `metrics` obrigatórios (catálogo fechado)
- `detail`: `columns` allowlisted (default = primeiras 8 do dataset primário)

## Wave 3 (2026-07-31) — schedules, dossiê PDF, legalização rica, deal room

### Agendamento

| Peça | Detalhe |
|---|---|
| Tabela | `report_schedules` (+ `report_runs.report_schedule_id`) |
| API | `GET/POST/PUT/DELETE /api/v1/reports/schedules` |
| Frequências | `daily`, `weekly`, `monthly` |
| Comando | `reports:run-due-schedules` (a cada 15 min, `onOneServer`, `withoutOverlapping`) |
| E-mail | `ReportScheduleReadyNotification` quando o run do schedule completa |

### PDF dossiê de comitê

`GET /api/v1/comite/{id}/ai-dossier/export-pdf`  
- Feature: `exports.pdf` (+ `committee`)  
- Exige dossiê `status=ready`  
- View: `exports/comite-ai-dossier-pdf.blade.php`

### Legalização — métricas ricas

| Métrica | Significado |
|---|---|
| `sum_custo_planejado` | Soma de custos das etapas |
| `sum_custo_realizado` | Somente itens com `custo_pago` |
| `avg_critical_days` / `sum_critical_days` | Caminho crítico via `LegalizacaoInsightService` |

Colunas virtuais no modo detail: `custo_planejado`, `custo_realizado`, `critical_path_days`.

### Deal room datasets

- `deal_ofertas` → `negociacao_ofertas` (+ `sum_valor` em `amount`)
- `deal_aprovacoes` → `negociacao_aprovacoes`
- `deal_condicoes` → `contrato_condicoes`
- `comite_dossies` → `comite_ai_dossiers`

### Charts server-side + gate de formato

- PDF renderiza barras HTML/CSS quando `charts` inclui `bar`/`line`
- `xlsx` exige feature `exports.excel`; `pdf` exige `exports.pdf` (quando o tenant tem plano resolvido)

### System templates novos (wave 3)

- Legalização — custos e caminho crítico  
- Deal room — ofertas  
- Dossiês de comitê  

## Ideias futuras (wave 4+)

1. Digest consolidado semanal de vários schedules  
2. Charts com imagens (SVG/PNG) além de barras CSS  
3. Dataset de shortlists / cenários de viabilidade  
4. Export assíncrono do dossiê via pipeline `/exports`

## API (contrato resumido)

```http
GET  /api/v1/reports/catalog
GET  /api/v1/reports/templates
POST /api/v1/reports/templates
GET  /api/v1/reports/templates/{id}
PUT  /api/v1/reports/templates/{id}
DELETE /api/v1/reports/templates/{id}
POST /api/v1/reports/runs          # body: template_id, idempotency_key, format?, filters?
GET  /api/v1/reports/runs/{id}
GET  /api/v1/reports/runs/{id}/download
```

Feature: `reports.builder` (Master+).  
Fila: `exports` (`GenerateReportRunJob`).

## Testes

- `tests/Feature/Tenant/ReportBuilderApiTest.php` — catálogo, validação, CSV/XLSX/PDF, download, claim único  
- `tests/Unit/Services/Tenant/ReportCatalogServiceTest.php` — cobertura do catálogo
