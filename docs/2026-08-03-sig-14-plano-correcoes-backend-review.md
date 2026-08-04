# SIG-14 — Plano de correções backend (review SIGAPP)

**Data:** 2026-08-03  
**Ticket:** [SIG-14](https://sigapp.youtrack.cloud/issue/SIG-14)  
**Repositório:** backend SIGAPP (`ajustes-sig-ia-ler-pfds`)  
**Tipo:** Epic / Story  
**Prioridade:** Alta (P0 LGPD)

> **Nota:** SIG-7 já existia (`Implementar novos modelos de viabilidade`). Este plano foi registrado como **SIG-14**.

---

## Contexto

Review minucioso do backend Laravel 13 multi-tenant (~948 arquivos PHP, 184 testes, 33 testes de arquitetura verdes). Identificados gaps em **LGPD/IA**, **performance em hot paths**, **god classes** e **cobertura parcial do padrão Repository**.

Decisões de produto/engineering fechadas em 2026-08-03:

| Tema | Decisão |
|------|---------|
| Prioridade geral | Segurança/LGPD → performance → arquitetura |
| ApiRequestLogger (prod) | Só lentas (>1s), 401/403/429 e 5xx |
| Timeline | Faseado: quick fix → UNION no sprint seguinte |
| Cache | Redis TTL 5–15 min + invalidação |
| Repository | Migração agressiva (≥55 services em arch test) |
| God classes | Webhook, Viabilidade, ReportCatalog, Billing |
| PHPStan | −10% baseline/sprint, sem Larastan |
| Tools IA deprecated | Remover + teste de catálogo SIG_IA |
| Relatório PDF IA | Só `AiDataRedactor` no contexto (mínimo P0) |

---

## Objetivo

Executar plano de correções em 4 fases (~6–8 sprints), começando por fechar brecha de vazamento de PII no pipeline de relatório PDF de terreno.

---

## Fase 0 — P0 Segurança/LGPD (Sprint 1)

### 0.1 Redação no relatório PDF de terreno

- [ ] Criar `AiContextRedactor` (redação recursiva de payload)
- [ ] Aplicar em `TerrenoAiNarrativeService::generate()` **antes** do `json_encode`
- [ ] Teste unitário com CPF/e-mail/telefone no contexto
- [ ] PR: `fix/ai-report-redaction`

**Aceite:** PII chega ao provider como `[email redacted]` / `***.***.***-**`.

### 0.2 Remover tools de IA deprecated

- [ ] Remover `TransitionWorkflowTool`, `CreateTaskTool`, `UpdateTaskStatusTool`
- [ ] Teste: `SIG_IA::tools()` não registra mutações de workflow/tarefa
- [ ] PR: `chore/remove-deprecated-ai-tools`

### 0.3 ApiRequestLogger prod-safe

- [ ] Em `production`: logar só lentas (>1s), 401/403/429 (warning) e 5xx (error)
- [ ] Manter `info` em `local`/`testing`
- [ ] Teste unitário por ambiente
- [ ] PR: `fix/api-logger-prod`

**DoD Fase 0:** `composer test`, Pint, PHPStan sem erros novos; `AGENTS.md` seção IA atualizada (redação PDF).

---

## Fase 1 — Performance quick wins (Sprint 1–2)

### 1.1 Cache Redis — tenant lookup

- [ ] `TenantLookupCacheService` ou método no repository
- [ ] Chaves `tenant:slug:{slug}` / `tenant:id:{id}`, TTL **10 min**
- [ ] Invalidar em suspend/activate/provisionamento
- [ ] PR: `perf/tenant-lookup-cache`

### 1.2 Cache Redis — matriz efetiva

- [ ] Cache `tenant:{id}:effective_matrix`, TTL **10 min**
- [ ] Invalidar com flush de plano + `TenantEntitlement`
- [ ] PR: `perf/effective-matrix-cache`

### 1.3 Timeline — quick fix

- [ ] `take` dinâmico por fonte (`perPage * page + buffer`, cap 200)
- [ ] Teste: página 1/`perPage=20` não carrega 800 registros
- [ ] PR: `perf/timeline-quick-fix`

---

## Fase 2 — Timeline paginação no banco (Sprint 2)

### 2.1 UNION query

- [ ] `TimelineRepository::paginateForTerreno()`
- [ ] UNION de activities, status_histories, tasks, comments
- [ ] Índices: `(terreno_id, timestamp DESC)` por tabela
- [ ] Compat SQLite (testes) + PostgreSQL (prod)
- [ ] PR: `perf/timeline-union-pagination`

---

## Fase 3 — Refatoração god classes (Sprints 3–4)

### 3.1 WebhookController

- [ ] Handlers por evento Stripe + `DisputeRepository`
- [ ] Controller < 150 linhas
- [ ] PR: `refactor/webhook-handlers`

### 3.2 ViabilidadeService

- [ ] Split: Crud, Version, Approval, Activation (+ fachada)
- [ ] **Não alterar** calculators, fórmulas nem `ENGINE_VERSION`
- [ ] PR: `refactor/viabilidade-services`

### 3.3 ReportCatalogService

- [ ] Datasets modulares (`ReportCatalog/Datasets/*`)
- [ ] PR: `refactor/report-catalog-datasets`

### 3.4 TenantBillingService

- [ ] Split: PlanSwap, StripeCharge, Dunning, StripeResolver
- [ ] PR: `refactor/billing-services`

---

## Fase 4 — Repository agressivo (Sprints 5–6)

Meta: `$migratedServices` de ~35 para **≥55**.

| Lote | Services |
|------|----------|
| A | Timeline, Legalização, Comitê, Negociação, Task, Activity |
| B | Coupon, TenantUserDirectory, Auth (Login/Broker/PasswordReset) |
| C | AI Tools com `::query()` |
| D | Produto, Premissas CRUD, ReportGeneration, MobileCapture, Shortlist |

Checklist por service: interface → repository → bind → refatorar → arch test → unit test.

---

## Contínuo — PHPStan

- [ ] −10% baseline/sprint (~130 entradas)
- [ ] Prioridade: redactors, DTOs viabilidade/IA, handlers pós-refactor
- [ ] Baseline monotonicamente decrescente

---

## Testes a adicionar

| Área | Fase |
|------|------|
| Redação PDF IA (PII) | 0 |
| SIG_IA catálogo (sem mutação) | 0 |
| ApiRequestLogger ambientes | 0 |
| Cache tenant/matrix invalidação | 1 |
| Timeline UNION Feature | 2 |
| Signup Feature | 4 |
| Parsers Unit | 4 |

---

## Riscos

| Risco | Mitigação |
|-------|-----------|
| Regressão viabilidade | `ViabilidadeRealOutputTest` no CI |
| Cache stale | TTL curto + invalidação + testes |
| Refactor webhook | Stripe fake + testes idempotência |

---

## Definition of Done (global)

- [ ] `composer test` (incl. Architecture)
- [ ] `./vendor/bin/pint --test`
- [ ] `composer analyse` sem erros novos
- [ ] i18n nos dois JSONs (se mensagens novas)
- [ ] `.env.example` (se envs novas, ex. `TENANT_CACHE_TTL`)
- [ ] `AGENTS.md` quando arquitetura/fluxos mudarem

---

## Cronograma

| Sprint | Entregas |
|--------|----------|
| S1 | Fase 0 + cache tenant |
| S2 | Cache matriz, timeline quick fix, timeline UNION |
| S3 | Webhook + Viabilidade split |
| S4 | ReportCatalog + Billing split |
| S5–S6 | Repository lotes A–D |
| Contínuo | PHPStan −10%/sprint |

---

## Referências

- Review: conversa 2026-08-03 (agent)
- `AGENTS.md` — seções IA, Viabilidade, Billing, Multi-tenancy
- Código crítico: `TerrenoAiNarrativeService`, `ApiRequestLogger`, `TimelineService`, `PlanMatrixService`, `WebhookController`, `ViabilidadeService`, `ReportCatalogService`, `TenantBillingService`
- Padrão tickets anteriores: SIG-5, SIG-6

---

## Sub-tasks sugeridas no YouTrack

| ID | Título | Fase |
|----|--------|------|
| SIG-14.1 | Redação PII relatório PDF IA | 0 |
| SIG-14.2 | Remover tools IA deprecated | 0 |
| SIG-14.3 | ApiRequestLogger prod-safe | 0 |
| SIG-14.4 | Cache tenant lookup Redis | 1 |
| SIG-14.5 | Cache matriz efetiva Redis | 1 |
| SIG-14.6 | Timeline quick fix | 1 |
| SIG-14.7 | Timeline UNION pagination | 2 |
| SIG-14.8 | Refactor WebhookController | 3 |
| SIG-14.9 | Refactor ViabilidadeService | 3 |
| SIG-14.10 | Refactor ReportCatalogService | 3 |
| SIG-14.11 | Refactor TenantBillingService | 3 |
| SIG-14.12 | Repository migration lote A | 4 |
| SIG-14.13 | Repository migration lote B | 4 |
| SIG-14.14 | Repository migration lote C (AI tools) | 4 |
| SIG-14.15 | Repository migration lote D | 4 |
| SIG-14.16 | PHPStan baseline −10% (recorrente) | contínuo |
