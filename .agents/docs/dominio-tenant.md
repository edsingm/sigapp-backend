# Domínio tenant — módulos principais

> **Quando ler:** workflow do terreno, prospecção, comitê, negociação, contratos, legalização, projetos, dashboard, mobile, cadastros, reports/exports/onboarding (regras de prioridade).
> **Hub:** [`AGENTS.md`](../../AGENTS.md)
> **Viabilidade (motor/fórmulas):** ver [`viabilidade.md`](./viabilidade.md)

## Módulos

Fluxo macro do terreno (enum `WorkflowStatus`, orquestrado por `LandWorkflowService`/`TerrenoWorkflowService`):
`em_analise → aguardando_viabilidade → viabilidade_aprovada → aguardando_comite → negociacao_minuta → contrato_assinado → legalizando → legalizado_finalizado` (+ `descartado`, `arquivado`). Transições disparam `WorkflowTransitioned` → listeners gravam `StatusHistory`, `EntityActivity`, notificam e transicionam `Projeto`s relacionados.

- **Prospecção/Terrenos**: `TerrenoService`, filtros (`TerrenoFilterService`), export Excel (campos textuais neutralizam fórmulas), importação cadastral Excel (`TerrenoImport`/`TerrenoImportRow`: validação assíncrona, preview e confirmação atômica sob o lock do limite `terrenos`), KMZ individual e em lote (`KmzParserService::parseMany()`, `TerrenoPolygonImport` e polígonos pendentes vinculáveis pelo mapa), cálculo de área útil (`Services/Tenant/Area/` — topografia, hidrografia, polígonos; `CalculateUsableAreaJob`), geoproximidade, scraper/enriquecimento de portal (`PortalTerrenoScraperService`, `Services/Parsers/Hiperdados/`), proprietários, corretores externos, contatos, produtos por terreno.
- **Roadmap operacional**: atividades genéricas em `ActivityController`/`ActivityService`, tarefas colaborativas em `TaskController`/`TaskService` e cards de pipeline em `TerrenoController@pipeline`. Essas superfícies usam as tabelas existentes `entity_activities`/`tasks` e as migrations tenant `2026_07_12_000001_extend_tasks_for_collaboration`. Elas continuam protegidas pelas features `collaboration.inbox`, `collaboration.tasks` e `prospection.pipeline_board`.
- **Comparação**: comparação de 2–4 terrenos e shortlists ficam em `ShortlistController`/`ShortlistService`, com as tabelas tenant `shortlists` e `shortlist_items`. A feature `prospection.comparison` é habilitada a partir do plano Básico; o backend não transforma comparação em recomendação automática. Cenários de viabilidade (`viabilities.scenarios`) começam no Master.
- **Recorte comercial (A)**: Broker só capta; Básico analisa; Master decide e fecha até o contrato (`committee` + `negotiation`) com IA só de chat (`ai`); Pro opera o ciclo completo (`legalizations`, `legalization.control_center`, `negotiation.deal_room`, `projects.*`, `documents.intelligence`, `ai.advanced`, `ai.contextual`).

- **Viabilidade:** motor, premissas, aprovação, snapshot e financiamento — detalhe completo em [`viabilidade.md`](./viabilidade.md).

- **Comitê**: `CommitteeService` — revisões, pareceres por departamento, pendências, decisão final.
- **Negociação**: `NegotiationService` — negociações + eventos.
- **Contratos**: `ContractService`/`ContractRepository` — partes, assinatura (`ContratoSigned` → e-mail + atividade).
- **Legalização**: etapas com dependências, status, prazos (`LegalizacaoEtapaStatus`), progresso recalculável, Gantt (`SyncGanttRequest`), PDF de checklist, notificação de atraso (`tenant:notify-overdue-legalizacao-etapas`).
- **Projetos**: `ProjetoService` — ciclo próprio (`ProjetoStatus`), integrado ao workflow do terreno.
- **Dashboard/Timeline**: `DashboardQueryService` + cache (`HasDashboardCache`), `TimelineService`.
- **Mobile**: registro de devices (`MobileDeviceInstallation`), inbox de notificações, push (`MobilePushService`).
- **Cadastros**: regionais, departamentos, produtos (com auditoria/histórico `ProdutoHistorico`), usuários do tenant com `status`. O módulo/tabela `positions` foi removido do schema tenant; não reintroduza cargos/positions sem decisão explícita.

## Regras de domínio (prioridade alta)

14. O planejamento de projetos usa `projeto_milestones`, `projeto_dependencies` e `projeto_risks`, com mutações em `ProjetoPlanningService`; dependências devem ser validadas contra ciclos antes de persistir.
15. As rotas de milestones, dependências e riscos de projetos ficam sob `check.feature:projects.planning`; o CRUD do módulo usa `check.feature:projects.enabled`. Ambas devem resolver explicitamente o projeto pai antes de operar recursos aninhados.
16. O modo reunião do comitê usa `comite_meeting_*` e deve chamar o `CommitteeService` existente para qualquer decisão; fechar uma sessão não inventa decisão para pauta pendente.
17. O deal room estende negociação/contrato com ofertas, aprovações e condições; aceitar uma oferta não assina contrato. A referência documental de condições permanece opcional até existir tabela tenant canônica de documentos.
18. A central de legalização reutiliza etapas e dependências existentes. O caminho crítico deve detectar ciclos e custos realizados só podem ser derivados de itens marcados como pagos; custo comprometido permanece indisponível sem lançamento fonte.
19. Captura mobile usa `mobile_captures`/`mobile_capture_attachments`: `client_id` é UUID idempotente por usuário, toda sincronização exige `base_version` e conflitos respondem `409` com payload seguro. Anexos são multipart em storage privado; nunca aceite foto/áudio inline em base64.
20. Onboarding usa catálogo servidor versionado em `UserOnboardingService`, eventos allowlisted e idempotentes em `user_onboarding_events`; não aceite nomes livres de evento nem use onboarding para liberar permissões ou rotas.
21. Relatórios configuráveis usam `report_templates`/`report_runs`/`report_schedules`, catálogo fechado em `ReportCatalogService` (datasets/métricas/dimensões/colunas/formatos/modos), `GET /reports/catalog` e `GenerateReportRunJob`. Datasets: terrenos, viabilidades, comites, legalizacoes (métricas ricas de custo planejado/realizado e caminho crítico), negociacoes, comite_reunioes, projetos, deal_ofertas, deal_aprovacoes, deal_condicoes, comite_dossies. Modos: `aggregate` (GROUP BY, até 500 grupos) e `detail` (colunas allowlisted, até 2000 linhas). Multi-dataset (até 4): PDF em capítulos com barras server-side, Excel em abas, CSV em seções. Formatos de run: `csv`, `xlsx` (`exports.excel`), `pdf` (`exports.pdf`) — artefato privado no disk `s3`. Schedules: CRUD `/reports/schedules` (daily/weekly/monthly), comando `reports:run-due-schedules` a cada 15 min com `onOneServer`/`withoutOverlapping`, e-mail `ReportScheduleReadyNotification`. Templates de sistema seedados via `ensureSystemTemplates()` / `ReportSystemTemplateSeeder` (`is_system`). PDF de dossiê de comitê: `GET /comite/{id}/ai-dossier/export-pdf`. O JSON do template nunca vira SQL; a execução persiste snapshot, as-of, expiração e erro seguro.
