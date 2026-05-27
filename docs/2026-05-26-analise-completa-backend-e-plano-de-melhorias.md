# Análise Completa do Backend SIGAPP e Plano de Melhorias

**Data:** 26 de maio de 2026  
**Autor:** Análise técnica automatizada  
**Escopo:** Backend Laravel 13+ (plataforma SIGAPP)

---

## Sumário Executivo

O SIGAPP é uma plataforma **SaaS multi-tenant** para **análise de viabilidade de terrenos e gestão imobiliária** voltada ao mercado brasileiro. Empresas se cadastram, recebem subdomínio próprio e gerenciam o pipeline completo de aquisição de terrenos — da prospecção à legalização.

**Stack principal:** Laravel 13 · PostgreSQL (schema-per-tenant via stancl/tenancy) · Stripe Cashier · Spatie Permission · Laravel AI SDK · Spatie PDF · Maatwebsite Excel · Resend

---

## 1. Números do Sistema

| Dimensão | Quantidade |
|----------|-----------|
| Models | 53 (13 central + 38 tenant + 2 shared) |
| Controllers | 57 (10 central + 10 admin + 31 tenant + 5 tenant-admin + 1 base) |
| Services | 65+ |
| Repositories | 31 (15 interfaces) |
| Rotas API | ~272 |
| AI Tools | 25 |
| Testes | 82 arquivos (PHPUnit 13) |
| Enums | 13 |
| Jobs | 4 |
| Notifications | 7 |
| Planos | 4 (Broker R$97 → Pro R$947/mês) |
| Entitlements | 30 (features + limits) |
| Módulos | 13 |

---

## 2. Arquitetura Geral

### 2.1 Estrutura de Pastas

```
app/
  Ai/                    → Agente SIG_IA e 25 tools
    Agents/              → SIG_IA.php (agente principal)
    Tools/               → 25 classes de ferramentas
  Console/Commands/      → 8 comandos Artisan
  Enums/                 → 13 enums PHP (domínio + comuns)
  Exceptions/            → 1 exceção customizada
  Exports/Tenant/        → Export Excel
  Http/
    Controllers/         → 57 controllers (Central + Tenant + Admin)
    Middleware/           → 14 middlewares customizados
    Requests/            → 100+ FormRequests
    Resources/           → 65+ API Resources
  Jobs/                  → 4 jobs assíncronos
  Models/
    Central/             → 13 models (cross-tenant)
    Tenant/              → 38 models (tenant-scoped)
    (root)               → 2 models compartilhados (User, AuditLog)
  Notifications/         → 7 classes de notificação
  Policies/Tenant/       → 1 TenantPolicy universal
  Providers/             → 3 service providers
  Repositories/          → 27 repositórios + 14 contratos/interfaces
  Services/              → 65+ services
  Support/               → Helpers, TenantAppUrl, UserContext, SqlDateParts
  Tenancy/               → Database managers customizados
  Traits/                → HasDashboardCache, LogsAudit
```

### 2.2 Padrão Arquitetural

O sistema segue rigorosamente **Controller → Service → Repository**:

- **Controllers**: thin, apenas recebem requisição, delegam ao service, retornam response
- **Services**: lógica de negócio, orquestram repositórios e outros services
- **Repositories**: único lugar onde Eloquent é usado diretamente
- **Models**: apenas relações, casts, accessors/mutators e escopos locais
- **FormRequests**: validação e autorização de entrada
- **Resources**: formatação de saída da API

---

## 3. Funcionalidades Mapeadas

### 3.1 Gestão Multi-Tenant e Billing

**Modelos envolvidos:** `Tenant`, `Plan`, `Entitlement`, `TenantEntitlement`, `Coupon`, `WebhookEvent`

**Funcionalidades:**
- Signup com Stripe Checkout e trial de 7 dias
- 4 planos de assinatura: Broker (R$97), Básico (R$247), Master (R$597), Pro (R$947)
- Feature gating via sistema de entitlements (30 entitlements entre features e limits)
- Cupons de desconto (percentual ou valor fixo)
- Upgrade/downgrade de plano
- Dunning (retry de pagamento com notificações escalonadas)
- Portal de billing Stripe integrado
- Login broker cross-tenant (login central → seleção de tenant → ticket de transferência one-time)

**Serviços principais:**
- `StripeCheckoutService`: criação de customers e checkout sessions
- `TenantBillingService`: operações de billing, sync de subscription, retry de pagamento
- `CouponService`: ciclo completo de cupons (criação, validação, redenção)
- `CentralLoginBrokerService`: autenticação cross-tenant com tickets SHA-256

### 3.2 Pipeline de Terrenos (Workflow Engine)

**Modelos envolvidos:** `Terreno`, `StatusHistory`, `EntityActivity`

**Workflow (10 estágios):**
```
em_analise 
  → aguardando_viabilidade 
  → viabilidade_aprovada 
  → aguardando_comite 
  → negociacao_minuta 
  → contrato_assinado 
  → legalizando 
  → legalizado_finalizado

+ descartado (closure)
+ arquivado (closure)
```

**Funcionalidades:**
- Motor de transições com pré-requisitos validados (ex: precisa de viabilidade aprovada para ir a comitê)
- Qualificação do terreno (dados urbanísticos, comerciais, riscos, anexos)
- Histórico completo de transições de status
- Activity feed de todas as ações no terreno
- Side effects automáticos (criação de tasks, atualização de projetos, notificações)

**Serviço principal:**
- `LandWorkflowService`: motor completo de workflow com matriz de transições, validação de pré-requisitos, gravação de histórico e execução de side effects

### 3.3 Motor de Viabilidade Financeira (~3.000 linhas)

**Modelos envolvidos:** `Viabilidade`, `ViabilidadeSecao`, `ViabilidadeAprovacao`, `PremissasViabilidade`

**Funcionalidades:**
- **3 DREs simultâneas:**
  1. **Gerencial** (accrual-based usando VGV e premissas)
  2. **Caixa** (baseada em fluxos mensais reais)
  3. **Contábil POC** (percentage-of-completion, receita proporcional à execução)
- **Ponte de reconciliação** explicando deltas entre as 3 visões
- 2 perfis de financiamento: CEF (Caixa Econômica Federal) e Próprio
- Curvas S de construção (18, 20, 24, 30, 36 meses)
- Curvas de vendas por produto
- Medição CEF (desembolsos atrelados à curva S e ritmo de vendas)
- Cálculo de impostos brasileiros (PIS, COFINS, ISS, IRPJ, CSLL com regimes RET/LP)
- KPIs financeiros: TIR (Newton-Raphson), payback operacional e financeiro, VGV, VSO (rolling 3/6/12 meses)
- Premissas versionáveis por perfil de financiamento
- Comparação entre versões de viabilidade
- Fluxo de aprovação (solicitar → aprovar/reprovar)
- Export PDF de viabilidade

**Estrutura de cálculo (`app/Services/Tenant/Viabilidade/v1/`):**
- `ViabilidadeService`: orquestrador de negócio (CRUD, aprovação, duplicação, comparação)
- `ViabilidadeUnificadoService`: orquestrador de cálculo (fluxo mensal, receitas, despesas, DRE)
- `FluxoMensalCalculator`: pipeline principal (560 linhas)
- `ReceitasCalculator`: receitas mensais (recursos próprios, terreno CEF, medição)
- `DespesasCalculator`: despesas mensais (5 blocos: custos diretos, impostos, operacional, financeiro, terreno)
- `DreCalculator`: DRE consolidada (411 linhas)
- `PocCalculator`: contabilidade POC (290 linhas)
- `IndicadoresCalculator`: KPIs financeiros (TIR, payback, VSO)
- `ImpostosService`: cálculos tributários
- `CurvaService`: gestão de curvas S e vendas

**Estrutura DRE:**
```
Receita Total de Vendas (VGV - valor terreno)
+ Juros e Correções
= Receita Bruta
- PIS/COFINS, ISS, Outras Deduções
= Receita Líquida (ROL)
- Custos Diretos (Terreno, Comissão, Incorporação, Infra, Área Comum, etc.)
= Lucro Bruto
- Despesas Operacionais (Comerciais, Marketing, Stand, etc.)
= EBITDA
- Despesas Financeiras
= EBIT
- IRPJ/CSLL
= Lucro Líquido do Projeto
```

### 3.4 Comitê de Aprovação

**Modelos envolvidos:** `ComiteRevisao`, `ComiteParecerDepartamento`, `ComitePendencia`

**Funcionalidades:**
- Revisão multi-departamental com pareceres por setor
- Decisão final (aprovado / aprovado com condições / rejeitado)
- Validação de que todos os departamentos obrigatórios submeteram parecer
- Criação automática de pendências para aprovações condicionais

**Serviço principal:**
- `CommitteeService`: gestão completa de comitês (criação, upsert de pareceres, finalização)

### 3.5 Negociação e Contratos

**Modelos envolvidos:** `Negociacao`, `NegociacaoEvento`, `Contrato`, `ContratoParte`

**Funcionalidades:**
- Negociação com audit trail de eventos (proposta, modelo de negócio, fechamento)
- Contratos com partes (pessoas/entidades), tipos, assinatura
- Transição automática de workflow após assinatura de contrato

**Serviço principal:**
- `NegotiationService`: ciclo completo de negociação e contratos (criação, eventos, assinatura)

### 3.6 Legalização

**Modelos envolvidos:** `Legalizacao`, `LegalizacaoEtapa`, `LegalizacaoDependencia`, `LegalizacaoDocumentoFase`, `LegalizacaoPendencia`

**Funcionalidades:**
- Processo de legalização com etapas hierárquicas (parent-child)
- Grafo de dependências entre etapas (predecessor/successor)
- Gantt sync em lote (criação/atualização/exclusão de etapas e dependências)
- Detecção de ciclos em dependências
- Cálculo automático de progresso (% concluído)
- Custos por etapa (previsto vs pago)
- Pendências e documentos por fase
- Notificações push para etapas atrasadas

**Serviço principal:**
- `LegalizacaoService`: gestão completa de legalização (CRUD, Gantt sync, recálculo de progresso)

### 3.7 IA (SIG_IA)

**Modelos envolvidos:** `AiRequestLog`, `AiRecommendationScore`, `AiDocumentChunk`, `AiDocumentEmbedding`

**Funcionalidades:**
- Assistente conversacional (SIG_IA) com 25 tools
- Scoring heurístico de priorização (0-100, 7 fatores ponderados):
  - Aprovação de viabilidade (25 pts)
  - Estágio avançado (20 pts)
  - Recência (15 pts)
  - VGV (15 pts)
  - Documentação (10 pts)
  - Sem pendências (10 pts)
  - Responsável atribuído (5 pts)
- Classificação em tiers: alta_prioridade / media / atencao / baixa
- Análises preditivas:
  - Probabilidade de aprovação (baseado em histórico)
  - Estimativa de VGV (benchmark de viabilidades similares)
  - Previsão de estagnação (terrenos em risco)
- Detecção de anomalias:
  - Inconsistências de workflow
  - Anomalias financeiras (VGV/área desproporcional)
  - Terrenos duplicados
  - Problemas de qualidade de dados
- Geração de insights automáticos:
  - Taxas de conversão
  - Gargalos de workflow
  - Tendências por cidade/responsável/mês
  - Concentração de risco
- Busca semântica em documentos (embeddings pgvector, cosine similarity)
- Telemetria de uso/budget por tenant (estimativa de custo por provider)
- Redação de dados sensíveis (CPF, CNPJ, email, telefone) antes de enviar ao LLM
- Rate limiting e budget check por tenant

**25 AI Tools:**
1. `ListTerrenosTool` - Listagem de terrenos com filtros
2. `GetTerrenoDetailsTool` - Detalhes completos de um terreno
3. `GetViabilidadesTool` - Consulta de viabilidades e DRE
4. `GetLegalizacaoTool` - Status de legalização
5. `GetComiteTool` - Status de comitê
6. `GetNegociacaoTool` - Status de negociação
7. `GetDocumentosTool` - Listagem de documentos
8. `GetDashboardSummaryTool` - Resumo executivo do portfólio
9. `GetTasksTool` - Listagem de tarefas
10. `SearchDocumentsTool` - Busca semântica em documentos
11. `AnalyzeDocumentTool` - Análise de documento específico
12. `GetTerrenoScoreTool` - Score de priorização
13. `GetRankingTool` - Ranking de terrenos
14. `CreateTaskTool` - Criação de tarefas
15. `UpdateTaskStatusTool` - Atualização de status de tarefa
16. `TransitionWorkflowTool` - Transição de workflow
17. `ProactiveMonitorTool` - Monitoramento proativo (atrasos, inconsistências)
18. `PredictViabilityTool` - Predição de aprovação
19. `EstimateVgvTool` - Estimativa de VGV
20. `PredictStallingTool` - Predição de estagnação
21. `DetectAnomaliesTool` - Detecção de anomalias
22. `GenerateInsightsTool` - Geração de insights
23. `GetTrendsTool` - Tendências
24. `CompareAreasTool` - Comparação de áreas/responsáveis
25. `CreatePdfsTool` - Geração de PDFs

**Serviços principais:**
- `AiScoringService`: scoring heurístico
- `AiPredictiveAnalysisService`: análises preditivas
- `AiAnomalyDetectionService`: detecção de anomalias
- `AiInsightGeneratorService`: geração de insights
- `AiTelemetryService`: telemetria de uso
- `AiProviderRouter`: roteamento com fallback
- `AiDataRedactor`: redação de dados sensíveis
- `AiEmbeddingService`: pipeline de embeddings

### 3.8 Dashboard e Relatórios

**Modelos envolvidos:** `Terreno`, `Viabilidade`, `TerrenoProduto`

**Funcionalidades:**
- Cards com totais (terrenos, viabilidades, projetos)
- Distribuição por cidade
- Gráficos de status (pie/bar)
- Cadastros mensais (evolução temporal)
- Terrenos por responsável
- Top cidades (por VGV, por quantidade)
- VGV anual
- Unidades fechadas anualmente
- Resumo geral do portfólio
- Export PDF de terrenos (lista e detalhe)
- Export Excel de terrenos
- Export PDF de viabilidade
- Checklist PDF por terreno

**Serviço principal:**
- `DashboardQueryService`: queries de dashboard (cards, gráficos, rankings)
- `TerrenoExportService`: exportação PDF/Excel

### 3.9 Administração e Segurança

**Modelos envolvidos:** `User` (tenant), `Department`, `Position`, `Role`, `Permission` (Spatie)

**Funcionalidades:**
- RBAC com Spatie Permission (6 roles: admin, director, manager, supervisor, analyst, user)
- Permissões granulares no formato `module.resource.level` (viewer/editor/manager)
- Gestão de departamentos e cargos
- Permissões por módulo para usuários
- Audit logs de ações
- Rate limiting por rota
- Enforce limits por plano (users, terrenos, products, storage)
- Permission gates por módulo
- Painel admin central completo:
  - Gestão de tenants (ativar, suspender)
  - Gestão de planos (CRUD, sync de entitlements)
  - Gestão de entitlements (CRUD)
  - Gestão de cupons (CRUD)
  - Gestão de usuários centrais (CRUD)
  - Gestão de blog posts (CRUD)
  - Audit logs
  - Dashboard admin

**Serviços principais:**
- `TenantUserService`: gestão de usuários tenant
- `RoleService`: gestão de roles
- `PermissionService`: gestão de permissões
- `PermissionNameResolver`: resolução de permissões a partir de HTTP method + módulo

### 3.10 Mobile

**Modelos envolvidos:** `MobileDeviceInstallation`, `MobileNotification`

**Funcionalidades:**
- Registro de dispositivos (Expo Push)
- Notificações push com deduplicação
- Permissões por módulo para targeting
- Notificações de etapas de legalização atrasadas

**Serviço principal:**
- `MobilePushService`: gestão de dispositivos e envio de notificações

---

## 4. Pontos Fortes

### 4.1 Arquitetura Bem Definida
- Padrão Controller → Service → Repository respeitado consistentemente
- Separação clara de responsabilidades
- FormRequests para validação/autorização
- Resources para formatação de saída

### 4.2 Motor Financeiro Sofisticado
- 3 DREs simultâneas (gerencial, caixa, POC) com reconciliação
- Cálculos complexos (TIR via Newton-Raphson, curvas S, medição CEF)
- ~3.000 linhas de código financeiro bem estruturado
- Nível enterprise

### 4.3 Multi-Tenancy Robusto
- Schema isolation via stancl/tenancy
- Login broker cross-tenant
- Feature gating granular via entitlements
- Billing integrado com Stripe

### 4.4 AI Bem Integrada
- 25 tools cobrindo todo o domínio
- Telemetria e budget control
- Redação de dados sensíveis
- Busca semântica com embeddings
- Provider routing com fallback

### 4.5 Workflow Engine Completa
- 10 estágios com pré-requisitos validados
- Side effects automáticos
- Audit trail completo
- Activity feed

### 4.6 Testes Abrangentes
- 82 arquivos de teste
- Cobertura de unit, feature e architecture tests
- PHPUnit 13 (não Pest)
- Testes de arquitetura validando padrões

### 4.7 Segurança
- Rate limiting em todas as rotas
- Enforce limits por plano
- Permission gates por módulo
- Audit logs
- Dunning com notificações escalonadas

---

## 5. Pontos de Atenção

### 5.1 Factories
**Problema:** Apenas 2 de 53 models têm factories (`Legalizacao` e `LegalizacaoEtapa`).  
**Impacto:** Testes criam fixtures inline com dados hardcoded, tornando-os frágeis a mudanças de schema.  
**Recomendação:** Criar factories para todos os models principais (Terreno, Viabilidade, User, Negotiation, Contract, Committee, Produto, Proprietario, etc.).

### 5.2 Events/Listeners
**Problema:** Zero eventos customizados. Side effects estão acoplados inline nos services.  
**Impacto:** Dificulta testes, impede adicionar novos efeitos sem modificar código existente, viola princípio aberto/fechado.  
**Recomendação:** Extrair side effects para Events (`TerrenoStatusChanged`, `ViabilidadeApproved`, `ContratoSigned`, etc.) + Listeners.

### 5.3 PremissasViabilidadeController
**Problema:** Usa Eloquent diretamente, violando a arquitetura Controller → Service → Repository.  
**Impacto:** Inconsistência arquitetural, lógica de acesso a dados no controller.  
**Recomendação:** Criar `PremissasViabilidadeService` e `PremissasViabilidadeRepository`.

### 5.4 Model Projeto
**Problema:** `$fillable` não declarado explicitamente.  
**Impacto:** Viola regra do AGENTS.md que exige `$fillable` explícito em todos os models.  
**Recomendação:** Declarar `$fillable` com todos os campos preenchíveis.

### 5.5 Migrações Duplicadas
**Problema:** `drop_cashier_columns_from_users_table` aparece 2x nas migrations.  
**Impacto:** Confusão, possível erro em rollback.  
**Recomendação:** Remover duplicata.

### 5.6 Notificações
**Problema:** Apenas notificações push mobile (Expo). Sem notificações email para transições críticas de workflow.  
**Impacto:** Usuários podem perder eventos importantes se não estiverem com o app aberto.  
**Recomendação:** Adicionar notificações email para: viabilidade aprovada/reprovada, comitê decidido, contrato assinado, etapa de legalização atrasada.

### 5.7 API Versioning
**Problema:** Rotas em `/api/v1/` mas sem estrutura para v2.  
**Impacto:** Dificulta evolução da API sem breaking changes.  
**Recomendação:** Documentar estratégia de versioning (header-based vs URL-based) e preparar estrutura.

---

## 6. Plano de Recomendações (25 itens)

### FASE 1 — Correções Rápidas (1-2 semanas)

| # | Recomendação | Justificativa | Esforço |
|---|-------------|---------------|---------|
| 1 | **Corrigir PremissasViabilidadeController** | Usa Eloquent direto, violando arquitetura. Criar Service + Repository. | Baixo |
| 2 | **Declarar `$fillable` no model Projeto** | AGENTS.md exige `$fillable` explícito em todos os models. | Mínimo |
| 3 | **Health check detalhado** | `/api/health` retorna apenas `{"status":"ok"}`. Adicionar checks de: DB central, DB tenant, Stripe API, Resend API, fila de jobs, storage. | Baixo |
| 4 | **Soft delete consistente** | Alguns models têm SoftDeletes (Terreno, Viabilidade, Legalizacao, Negociacao, Contrato, Produto, Documento), outros não (ComiteRevisao, ComiteParecerDepartamento, Proprietario, TerrenoContato, TerrenoInfos). Padronizar. | Baixo |
| 5 | **Rate limiting por plano** | Rate limiting atual é genérico (`throttle:api`). Implementar rate limits diferenciados por plano (Broker: 60/min, Master: 300/min, Pro: ilimitado). | Baixo |

**Total estimado:** 3-5 dias de desenvolvimento

---

### FASE 2 — Robustez (2-4 semanas)

| # | Recomendação | Justificativa | Esforço |
|---|-------------|---------------|---------|
| 6 | **Events/Listeners para side effects** | `LandWorkflowService` dispara criação de tasks, atualização de projetos, notificações push, etc. Tudo inline. Extrair para Events (`TerrenoStatusChanged`, `ViabilidadeApproved`, `ContratoSigned`, etc.) + Listeners desacopla, facilita testes e permite adicionar novos efeitos sem modificar código existente. | Médio |
| 7 | **Factories para todos os models** | Apenas 2 de 53 models têm factory. Testes criam fixtures inline com dados hardcoded, tornando-os frágeis a mudanças de schema. Criar factories para Terreno, Viabilidade, User, Negotiation, Contract, Committee, Produto, Proprietario, etc. | Médio |
| 8 | **Notificações email para transições de workflow** | Atualmente só notificações push mobile (Expo). Adicionar notificações email para transições críticas: viabilidade aprovada/reprovada, comitê decidido, contrato assinado, etapa de legalização atrasada. | Médio |
| 9 | **Cache invalidation centralizado** | Cache é invalidado em boot events de models e em services de forma espalhada. Centralizar em um `CacheManager` por domínio (TerrenoCache, ViabilidadeCache, etc.). | Médio |
| 10 | **Checklist de conformidade por terreno** | Endpoint que retorne: quais documentos faltam, quais etapas pendentes, quais aprovações necessárias para o próximo estágio. Útil para gestores acompanharem múltiplos terrenos. | Baixo |

**Total estimado:** 2-3 semanas de desenvolvimento

---

### FASE 3 — Features de Produto (4-8 semanas)

| # | Recomendação | Justificativa | Esforço |
|---|-------------|---------------|---------|
| 11 | **Timeline unificada por terreno** | Já existe `EntityActivity` e `StatusHistory`, mas são separados. Criar endpoint unificado de timeline que combine: transições de workflow, comentários, tarefas, eventos de negociação, decisões de comitê, etapas de legalização, uploads de documentos. Frontend ganharia visão cronológica completa do terreno. | Médio |
| 12 | **Comparador de terrenos side-by-side** | Viabilidade já tem `compare`, mas não existe comparação entre terrenos. Endpoint que retorne 2+ terrenos lado a lado com: área, VGV, status workflow, score IA, dados de viabilidade, cidade, proprietário. | Baixo |
| 13 | **Importação em massa CSV/Excel** | Atualmente só existe importação de polígono KMZ individual. Adicionar importação via planilha com validação, preview e relatório de erros. Essencial para empresas migrando de planilhas. | Médio |
| 14 | **Kanban board API** | Endpoint que retorne terrenos agrupados por `workflow_stage` com contagens, ideal para visualização Kanban. Filtros por responsável, regional, cidade. | Baixo |
| 15 | **Modo sandbox para viabilidade** | Permitir criar viabilidades "what-if" sem vincular ao terreno real, para explorar cenários (e se o VGV fosse X? e se o prazo de obra fosse Y?). Hoje a duplicação existe, mas não há modo sandbox dedicado. | Médio |
| 16 | **Notificações configuráveis** | Permitir que cada usuário configure quais notificações deseja receber (email, push, in-app) por tipo de evento. Hoje é tudo-ou-nada. | Médio |
| 17 | **API de webhooks para integrações** | Permitir que tenants configurem webhooks para eventos (terreno criado, viabilidade aprovada, contrato assinado). Habilita integrações com ERPs, CRMs e sistemas externos. | Alto |

**Total estimado:** 4-6 semanas de desenvolvimento

---

### FASE 4 — Expansão (8-16 semanas)

| # | Recomendação | Justificativa | Esforço |
|---|-------------|---------------|---------|
| 18 | **CRM integrado** | Rastrear interações com proprietários e corretores externos: ligações, emails, reuniões, propostas. Hoje o sistema gerencia o terreno mas não o relacionamento com as partes. | Alto |
| 19 | **Financeiro pós-contrato** | Após contrato assinado, rastrear pagamentos, liberações CEF, cronograma financeiro. Hoje o sistema foca na aquisição mas não no acompanhamento financeiro da obra. | Alto |
| 20 | **Módulo de permuta** | Gerenciar cenários de permuta (terreno por unidades), incluindo cálculo de equivalência, impostos específicos, e impacto na viabilidade. Campo `permuta` existe em TerrenoProduto mas não há fluxo dedicado. | Médio |
| 21 | **API pública / marketplace de dados** | Dados agregados e anonimizados de mercado por cidade/estado (preço médio por m², VGV médio, tempo médio de legalização). Pode ser produto adicional monetizável. | Alto |
| 22 | **Integração com cartórios** | API para consulta de matrícula, certidões negativas, situação cadastral. Automatizar due diligence na fase de prospecção. | Alto |
| 23 | **Multi-idioma (espanhol)** | LanguageService suporta pt-br e en-us, mas tradução real dos JSON files pode estar incompleta. Expandir para espanhol (mercado LATAM). | Médio |
| 24 | **Onboarding guiado por tenant** | Após signup, wizard de setup: criar primeiro terreno, configurar premissas, adicionar usuários, definir regionais. Reduz churn nos primeiros dias. | Médio |
| 25 | **API versioning strategy** | Rotas estão em `/api/v1/` mas não há mecanismo para v2. Documentar estratégia de versioning (header-based vs URL-based) e preparar estrutura. | Baixo |

**Total estimado:** 8-12 semanas de desenvolvimento

---

## 7. Roadmap Visual

```
FASE 1 — Correções (1-2 semanas)
├── #1 PremissasViabilidadeController → Service/Repository
├── #2 Projeto $fillable
├── #3 Health check detalhado
├── #4 Soft delete consistente
└── #5 Rate limiting por plano

FASE 2 — Robustez (2-4 semanas)
├── #6 Events/Listeners para side effects
├── #7 Factories para todos os models
├── #8 Notificações email para workflow
├── #9 Cache invalidation centralizado
└── #10 Checklist de conformidade por terreno

FASE 3 — Features de produto (4-8 semanas)
├── #11 Timeline unificada por terreno
├── #12 Comparador de terrenos
├── #13 Importação em massa CSV/Excel
├── #14 Kanban board API
├── #15 Modo sandbox para viabilidade
├── #16 Notificações configuráveis
└── #17 Webhooks para integrações

FASE 4 — Expansão (8-16 semanas)
├── #18 CRM integrado
├── #19 Financeiro pós-contrato
├── #20 Módulo de permuta
├── #21 API pública / marketplace de dados
├── #22 Integração com cartórios
├── #23 Multi-idioma (espanhol)
├── #24 Onboarding guiado
└── #25 API versioning strategy
```

---

## 8. Conclusão

O backend do SIGAPP é uma plataforma **sólida e bem arquitetada**, com:
- Motor financeiro de nível enterprise (~3.000 linhas)
- Multi-tenancy robusto com schema isolation
- AI bem integrada com 25 tools
- Workflow engine completa
- Testes abrangentes (82 arquivos)

As principais oportunidades de melhoria estão em:
1. **Desacoplamento** (Events/Listeners para side effects)
2. **Testabilidade** (factories para todos os models)
3. **Comunicação** (notificações email para workflow)
4. **Features de produto** (timeline, comparador, importação em massa)
5. **Expansão** (CRM, financeiro pós-contrato, integrações)

O plano de 25 recomendações está organizado em 4 fases com esforço estimado total de **15-25 semanas** de desenvolvimento, priorizando correções rápidas e robustez antes de novas features.

---

**Próximos passos:** Priorizar itens da FASE 1 (correções rápidas) e iniciar FASE 2 (robustez) em paralelo com desenvolvimento de features da FASE 3 conforme demanda do produto.
