# SIGAPP Architecture Diagram

## Overview

SIGAPP is a multi-tenant SaaS platform for real estate management (land prospecting, economic viability, committee approval, negotiation, contracts, legalization, projects, and AI-powered analysis).

```mermaid
graph TB
    subgraph "Client Layer"
        FE[Next.js Frontend]
        MB[Mobile App]
    end
    
    subgraph "API Gateway"
        LB[Load Balancer]
        NGINX[Nginx Proxy]
    end
    
    subgraph "Central Application"
        CENTRAL_ROUTES[Central Routes<br/>api.php]
        CENTRAL_CONTROLLERS[Central Controllers<br/>Api/V1/]
        CENTRAL_MODELS[Central Models<br/>Models/Central/]
        CENTRAL_DB[(Central DB<br/>PostgreSQL)]
    end
    
    subgraph "Tenant Application"
        TENANT_ROUTES[Tenant Routes<br/>tenant.php]
        TENANT_CONTROLLERS[Tenant Controllers<br/>Api/V1/Tenant/]
        TENANT_MODELS[Tenant Models<br/>Models/Tenant/]
        TENANT_DB[(Tenant Schema<br/>PostgreSQL)]
    end
    
    subgraph "Shared Services"
        AI_AGENT[SIG_IA Agent<br/>41 Tools]
        QUEUE[Redis Queues<br/>5 Worker Groups]
        STORAGE[S3 Storage]
        CACHE[Redis Cache]
    end
    
    subgraph "External Services"
        STRIPE[Stripe Billing]
        EMAIL[Resend Email]
        AI_PROVIDERS[DeepSeek/Gemini/OpenRouter]
    end
    
    FE -->|HTTPS| LB
    MB -->|HTTPS| LB
    LB --> NGINX
    NGINX -->|Subdomain| CENTRAL_ROUTES
    NGINX -->|{tenant}.sigapp.com.br| TENANT_ROUTES
    
    CENTRAL_ROUTES --> CENTRAL_CONTROLLERS
    CENTRAL_CONTROLLERS --> CENTRAL_MODELS
    CENTRAL_MODELS --> CENTRAL_DB
    
    TENANT_ROUTES --> TENANT_CONTROLLERS
    TENANT_CONTROLLERS --> TENANT_MODELS
    TENANT_MODELS --> TENANT_DB
    
    CENTRAL_CONTROLLERS --> AI_AGENT
    TENANT_CONTROLLERS --> AI_AGENT
    
    CENTRAL_CONTROLLERS --> QUEUE
    TENANT_CONTROLLERS --> QUEUE
    
    CENTRAL_CONTROLLERS --> STORAGE
    TENANT_CONTROLLERS --> STORAGE
    
    CENTRAL_CONTROLLERS --> CACHE
    TENANT_CONTROLLERS --> CACHE
    
    CENTRAL_CONTROLLERS --> STRIPE
    CENTRAL_CONTROLLERS --> EMAIL
    AI_AGENT --> AI_PROVIDERS
```

## Multi-Tenancy Architecture

```mermaid
graph TB
    subgraph "Request Flow"
        REQ[Incoming Request]
        MIDDLEWARE[Middleware Stack]
        IDENTIFICATION[Tenant Identification]
        BOOTSTRAP[Tenancy Bootstrap]
    end
    
    subgraph "Identification Methods"
        SUBDOMAIN[Subdomain<br/>{tenant}.sigapp.com.br]
        HEADER[X-Tenant Header<br/>API/Mobile fallback]
        PATH[Path Parameter<br/>/tenant/{slug}]
    end
    
    subgraph "Bootstrappers"
        DB_BOOTSTRAP[Database Tenancy<br/>PostgreSQL Schema]
        CACHE_BOOTSTRAP[Cache Tenancy<br/>Redis Prefix]
        FS_BOOTSTRAP[Filesystem Tenancy<br/>Storage Path]
        QUEUE_BOOTSTRAP[Queue Tenancy<br/>Job Context]
    end
    
    subgraph "Database Structure"
        CENTRAL[(Central DB<br/>tenants, domains, plans, users)]
        TENANT1[(Tenant Schema 1<br/>tenant_{slug1})]
        TENANT2[(Tenant Schema 2<br/>tenant_{slug2})]
        TENANT3[(Tenant Schema N<br/>tenant_{slugN})]
    end
    
    REQ --> MIDDLEWARE
    MIDDLEWARE --> IDENTIFICATION
    IDENTIFICATION --> SUBDOMAIN
    IDENTIFICATION --> HEADER
    IDENTIFICATION --> PATH
    IDENTIFICATION --> BOOTSTRAP
    BOOTSTRAP --> DB_BOOTSTRAP
    BOOTSTRAP --> CACHE_BOOTSTRAP
    BOOTSTRAP --> FS_BOOTSTRAP
    BOOTSTRAP --> QUEUE_BOOTSTRAP
    DB_BOOTSTRAP --> CENTRAL
    DB_BOOTSTRAP --> TENANT1
    DB_BOOTSTRAP --> TENANT2
    DB_BOOTSTRAP --> TENANT3
```

## Route Organization

```mermaid
graph LR
    subgraph "Central Routes (routes/api.php)"
        PUBLIC[Public Routes<br/>plans, signup, auth, blog]
        WEBHOOK[Webhook Routes<br/>stripe webhook]
        AUTH[Authenticated Routes<br/>locale, health, auth/me]
        ADMIN[Admin Routes<br/>/admin/*]
    end
    
    subgraph "Tenant Routes (routes/tenant.php)"
        PUBLIC_AUTH[Public Auth<br/>tenant login]
        BILLING[Account Billing<br/>subscription, invoices]
        ADMIN_WS[Workspace Admin<br/>users, roles, permissions]
        PROSPECTION[Prospection<br/>terrenos, proprietarios]
        VIABILITY[Viability & AI<br/>viabilidades, sig-ia]
        COMMITTEE[Committee<br/>comite, meetings]
        NEGOTIATION[Negotiation<br/>negociacao, deal-room]
        LEGAL[Legalization<br/>legalizacao, documentos]
    end
    
    PUBLIC -->|central.context| AUTH
    AUTH --> ADMIN
    PUBLIC_AUTH -->|tenant.context| BILLING
    BILLING -->|CheckSubscriptionStatus| ADMIN_WS
    ADMIN_WS --> PROSPECTION
    PROSPECTION --> VIABILITY
    VIABILITY --> COMMITTEE
    COMMITTEE --> NEGOTIATION
    NEGOTIATION --> LEGAL
```

## Service Layer Architecture

```mermaid
graph TB
    subgraph "Service Layer (app/Services/)"
        ADMIN[Admin Services<br/>3 services]
        AI[Ai Services<br/>42 services]
        AUTH[Auth Services<br/>5 services]
        BILLING[Billing Services<br/>5 services]
        TENANT[Tenant Services<br/>73 services]
    end
    
    subgraph "AI Services Detail"
        AGENTS[Agents<br/>SIG_IA]
        TOOLS[Tools<br/>41 specialized tools]
        EMBEDDING[Embedding Service<br/>pgvector]
        SCORING[Scoring Service<br/>land attractiveness]
        PREDICTION[Predictive Analysis<br/>stalling, viability]
        ANOMALY[Anomaly Detection<br/>data quality]
        INSIGHTS[Insight Generator<br/>analytics, trends]
    end
    
    subgraph "Tenant Services Detail"
        WORKFLOW[Workflow Services<br/>LandWorkflow, TerrenoWorkflow]
        LEGAL[Legalization Services<br/>Legalizacao, DocumentIntelligence]
        COMMITTEE[Committee Services<br/>Committee, CommitteeMeeting]
        NEGOTIATION[Negotiation Services<br/>Negotiation, DealRoom]
        VIABILITY[Viability Services<br/>15 services in Viability/]
        PROJECT[Project Services<br/>Projeto, ProjetoPlanning]
        DOCUMENT[Document Services<br/>Documento, DocumentIntelligence]
        EXPORT[Export Services<br/>TenantExport, TerrenoExport]
        MOBILE[Mobile Services<br/>MobileCapture, MobilePush]
        AI_MONITOR[AiMonitorService<br/>contextual recommendations]
    end
    
    ADMIN --> AUTH
    AUTH --> BILLING
    BILLING --> TENANT
    TENANT --> AI
    AI --> AGENTS
    AGENTS --> TOOLS
    TOOLS --> EMBEDDING
    TOOLS --> SCORING
    TOOLS --> PREDICTION
    TOOLS --> ANOMALY
    TOOLS --> INSIGHTS
    TENANT --> WORKFLOW
    TENANT --> LEGAL
    TENANT --> COMMITTEE
    TENANT --> NEGOTIATION
    TENANT --> VIABILITY
    TENANT --> PROJECT
    TENANT --> DOCUMENT
    TENANT --> EXPORT
    TENANT --> MOBILE
    TENANT --> AI_MONITOR
```

## AI Agent (SIG_IA) Architecture

```mermaid
graph TB
    subgraph "SIG_IA Agent"
        PROMPT[Instructions<br/>Business rules + Context]
        TOOLS[41 Tools<br/>Domain-specific]
        PROVIDER[AI Provider<br/>DeepSeek/Gemini/OpenRouter]
    end
    
    subgraph "Tool Categories"
        DATA[Data Retrieval<br/>ListTerrenos, GetTerrenoDetails<br/>GetViabilidades, GetLegalizacao<br/>GetComite, GetNegociacao]
        DOC[Document Tools<br/>DocumentosTool, SearchDocuments<br/>Semantic search via pgvector]
        ANALYSIS[Analysis Tools<br/>GetTerrenoScore, GetRanking<br/>AnalyticsTool, DetectAnomalies]
        PREDICTION[Prediction Tools<br/>PredictStalling, PredictViability<br/>EstimateVgv]
        MONITOR[Monitoring Tools<br/>ProactiveMonitor, GetTasks<br/>GetDashboardSummary]
        MARKET[Market Intelligence<br/>GetCityIbgeProfile<br/>PesquisarEmpreendimentos]
        GEO[Geo Analysis<br/>GetTerrenoGeoAnalysis<br/>PolygonCalculator, GeoProximity]
    end
    
    subgraph "Tool Decorators"
        REDACTOR[AiDataRedactor<br/>PII protection]
        WRAPPER[RedactingToolDecorator<br/>Wraps all tools]
    end
    
    PROMPT --> TOOLS
    TOOLS --> DATA
    TOOLS --> DOC
    TOOLS --> ANALYSIS
    TOOLS --> PREDICTION
    TOOLS --> MONITOR
    TOOLS --> MARKET
    TOOLS --> GEO
    TOOLS --> WRAPPER
    WRAPPER --> REDACTOR
    PROVIDER --> TOOLS
```

## Database Schema Organization

```mermaid
graph TB
    subgraph "Central Database (PostgreSQL)"
        TENANTS[tenants<br/>id, slug, plan_id, status]
        DOMAINS[domains<br/>id, tenant_id, domain]
        PLANS[plans<br/>id, slug, price, entitlements]
        ENTITLEMENTS[entitlements<br/>id, slug, description]
        PLAN_ENTITLEMENTS[plan_entitlements<br/>plan_id, entitlement_id]
        TENANT_ENTITLEMENTS[tenant_entitlements<br/>tenant_id, entitlement_id]
        USERS[users<br/>id, name, email, role]
        COUPONS[coupons<br/>id, code, discount]
        POSTS[posts<br/>id, slug, content]
        WEBHOOK_EVENTS[webhook_events<br/>id, type, processing_state]
        AUDIT_LOGS[audit_logs<br/>id, user_id, action]
        CONSENT_LOGS[consent_logs<br/>id, consent_id, timestamp]
    end
    
    subgraph "Tenant Schema (tenant_{slug})"
        T_USERS[users<br/>id, name, email, department_id]
        DEPARTMENTS[departments<br/>id, name]
        TERRENOS[terrenos<br/>id, nome, endereco, cidade<br/>workflow_stage, workflow_status]
        TERR_PRODUTOS[terreno_produto<br/>terreno_id, produto_id]
        DOCUMENTOS[documentos<br/>id, terreno_id, tipo, categoria]
        VIABILIDADES[viabilidades<br/>id, terreno_id, version<br/>is_current, approval_status]
        PREMISSAS[premissas_viabilidade<br/>id, viabilidade_id]
        LEGALIZACOES[legalizacoes<br/>id, terreno_id, status]
        LEG_ETAPAS[legalizacao_etapas<br/>id, legalizacao_id, parent_id]
        COMITE_SESSIONS[comite_meeting_sessions<br/>id, terreno_id]
        COMITE_DOSSIERS[comite_ai_dossiers<br/>id, terreno_id]
        NEGOCIACOES[negociacoes<br/>id, terreno_id, status]
        PROJETOS[projetos<br/>id, terreno_id]
        TASKS[tasks<br/>id, assignable_type]
        AI_REQUEST_LOGS[ai_request_logs<br/>id, user_id, tool]
        AI_EMBEDDINGS[ai_document_embeddings<br/>id, document_id, embedding]
        AI_REPORTS[ai_generated_reports<br/>id, terreno_id, type]
    end
    
    TENANTS --> DOMAINS
    TENANTS --> PLANS
    PLANS --> PLAN_ENTITLEMENTS
    PLAN_ENTITLEMENTS --> ENTITLEMENTS
    TENANTS --> TENANT_ENTITLEMENTS
    TENANT_ENTITLEMENTS --> ENTITLEMENTS
    TENANTS --> WEBHOOK_EVENTS
    USERS --> AUDIT_LOGS
    
    T_USERS --> DEPARTMENTS
    TERRENOS --> TERR_PRODUTOS
    TERRENOS --> DOCUMENTOS
    TERRENOS --> VIABILIDADES
    TERRENOS --> LEGALIZACOES
    TERRENOS --> COMITE_SESSIONS
    TERRENOS --> COMITE_DOSSIERS
    TERRENOS --> NEGOCIACOES
    TERRENOS --> PROJETOS
    VIABILIDADES --> PREMISSAS
    LEGALIZACOES --> LEG_ETAPAS
    DOCUMENTOS --> AI_EMBEDDINGS
    TERRENOS --> AI_REPORTS
    T_USERS --> AI_REQUEST_LOGS
```

## Queue System Architecture

```mermaid
graph TB
    subgraph "Redis Queue System"
        DEFAULT[default<br/>General jobs]
        TENANT_PROV[tenant-provisioning<br/>Tenant creation/setup]
        AI[ai<br/>AI agent tasks]
        EXPORTS[exports<br/>PDF/Excel generation]
        NOTIFICATIONS[notifications<br/>Email/Push notifications]
    end
    
    subgraph "Job Types"
        PROVISIONING[TenantProvisioningJob<br/>Create schema, migrate, seed]
        AI_REPORT[AiReportGenerationJob<br/>Generate AI reports]
        PDF_EXPORT[PdfExportJob<br/>Generate PDF reports]
        EXCEL_EXPORT[ExcelExportJob<br/>Generate Excel exports]
        EMAIL[EmailNotificationJob<br/>Send via Resend]
        PUSH[PushNotificationJob<br/>Mobile push notifications]
    end
    
    subgraph "Scheduler (schedule:work)"
        SCHEDULER[Laravel Scheduler<br/>Runs every minute]
        EVENTS[Scheduled Events<br/>onOneServer + withoutOverlapping]
    end
    
    DEFAULT --> PROVISIONING
    TENANT_PROV --> PROVISIONING
    AI --> AI_REPORT
    EXPORTS --> PDF_EXPORT
    EXPORTS --> EXCEL_EXPORT
    NOTIFICATIONS --> EMAIL
    NOTIFICATIONS --> PUSH
    SCHEDULER --> EVENTS
```

## Key Integrations

```mermaid
graph LR
    subgraph "Billing Integration"
        CASHIER[Laravel Cashier<br/>Stripe]
        WEBHOOKS[Stripe Webhooks<br/>subscription.created, etc.]
        PLANS_DB[Plans Database<br/>Plan management]
        ENTITLEMENTS[Entitlements<br/>Feature access control]
    end
    
    subgraph "AI Integration"
        SDK[Laravel AI SDK<br/>laravel/ai]
        PROVIDERS[AI Providers<br/>DeepSeek, Gemini, OpenRouter]
        PGVECTOR[pgvector Extension<br/>Embedding storage]
        TOOLS[41 AI Tools<br/>Domain-specific]
    end
    
    subgraph "Storage Integration"
        S3[AWS S3 / Compatible<br/>Document storage]
        LOCAL[Local Storage<br/>Development]
        TENANT_FS[Tenant Filesystem<br/>Isolated per tenant]
    end
    
    subgraph "Email Integration"
        RESEND[Resend API<br/>Transactional email]
        NOTIFICATIONS[Laravel Notifications<br/>Email channels]
    end
    
    CASHIER --> WEBHOOKS
    WEBHOOKS --> PLANS_DB
    PLANS_DB --> ENTITLEMENTS
    SDK --> PROVIDERS
    SDK --> TOOLS
    TOOLS --> PGVECTOR
    S3 --> TENANT_FS
    LOCAL --> TENANT_FS
    RESEND --> NOTIFICATIONS
```

## Security & Authorization

```mermaid
graph TB
    subgraph "Authentication"
        SANCTUM[Laravel Sanctum<br/>Token-based auth]
        CENTRAL_AUTH[Central Auth<br/>Platform admins]
        TENANT_AUTH[Tenant Auth<br/>Tenant users]
        BROKER[Login Broker<br/>Cross-tenant login]
    end
    
    subgraph "Authorization (RBAC)"
        PERMISSIONS[Spatie Permissions<br/>roles, permissions]
        TEMPLATES[Plan Templates<br/>Role-per-plan matrix]
        ENTITLEMENTS[Entitlements<br/>Feature-based access]
        POLICIES[Policies<br/>Model-level authorization]
    end
    
    subgraph "Middleware Stack"
        FORCE_JSON[ForceJsonResponse<br/>JSON responses only]
        CENTRAL_CTX[EnsureCentralContext<br/>Central routes only]
        TENANT_CTX[EnsureTenantContext<br/>Tenant routes only]
        AUTH_CENTRAL[auth.central<br/>Central user guard]
        AUTH_TENANT[auth.tenant<br/>Tenant user guard]
        CENTRAL_ADMIN[central.admin<br/>Admin role required]
        CHECK_SUB[CheckSubscriptionStatus<br/>Active subscription required]
        ENFORCE_HOST[EnforceHostAccess<br/>Domain validation]
    end
    
    SANCTUM --> CENTRAL_AUTH
    SANCTUM --> TENANT_AUTH
    CENTRAL_AUTH --> BROKER
    PERMISSIONS --> TEMPLATES
    TEMPLATES --> ENTITLEMENTS
    ENTITLEMENTS --> POLICIES
    FORCE_JSON --> CENTRAL_CTX
    FORCE_JSON --> TENANT_CTX
    CENTRAL_CTX --> AUTH_CENTRAL
    AUTH_CENTRAL --> CENTRAL_ADMIN
    TENANT_CTX --> AUTH_TENANT
    AUTH_TENANT --> CHECK_SUB
    CENTRAL_CTX --> ENFORCE_HOST
    TENANT_CTX --> ENFORCE_HOST
```

## Deployment Architecture

```mermaid
graph TB
    subgraph "Production Deployment"
        DOCKER[Docker Container<br/>Multi-stage build]
        SUPERVISOR[Supervisord<br/>Process manager]
        NGINX_PROD[Nginx<br/>Web server]
        PHP_FPM[PHP-FPM<br/>Application server]
        SCHEDULER[schedule:work<br/>Cron jobs]
        WORKERS[Queue Workers<br/>5 groups]
    end
    
    subgraph "Infrastructure"
        DOKPLOY[Dokploy<br/>Deployment platform]
        PG_EXTERNAL[PostgreSQL 16 + pgvector<br/>Dokploy managed]
        REDIS_EXT[Redis 7<br/>External cache/queue]
        S3_EXT[S3 Compatible<br/>External storage]
    end
    
    subgraph "Deployment Scripts"
        BOOTSTRAP[sigapp-bootstrap<br/>First-time setup<br/>migrate + seed]
        RELEASE[sigapp-release<br/>Subsequent deploys<br/>migrate central + tenants]
    end
    
    DOCKER --> SUPERVISOR
    SUPERVISOR --> NGINX_PROD
    SUPERVISOR --> PHP_FPM
    SUPERVISOR --> SCHEDULER
    SUPERVISOR --> WORKERS
    DOKPLOY --> DOCKER
    PHP_FPM --> PG_EXTERNAL
    PHP_FPM --> REDIS_EXT
    PHP_FPM --> S3_EXT
    WORKERS --> REDIS_EXT
    BOOTSTRAP --> PG_EXTERNAL
    RELEASE --> PG_EXTERNAL
```

## Technology Stack Summary

| Layer | Technology | Purpose |
|-------|-----------|---------|
| **Framework** | Laravel 13 | PHP framework |
| **Language** | PHP 8.4+ | Application language |
| **Database** | PostgreSQL 16 + pgvector | Central + tenant schemas |
| **Multi-tenancy** | stancl/tenancy 3.8 | Schema-based isolation |
| **Authentication** | Laravel Sanctum | Token-based auth |
| **Authorization** | spatie/laravel-permission 7.0 | RBAC |
| **Billing** | Laravel Cashier 16.0 | Stripe integration |
| **AI** | laravel/ai 0.10 | AI agent framework |
| **AI Providers** | DeepSeek, Gemini, OpenRouter | LLM providers |
| **Vector DB** | pgvector | Embedding storage |
| **Cache** | Redis 7 | Caching & queues |
| **Storage** | S3 / Local | File storage |
| **Email** | Resend | Transactional email |
| **PDF** | spatie/laravel-pdf + Browsershot | PDF generation |
| **Excel** | maatwebsite/excel 3.1 | Excel exports |
| **API Docs** | dedoc/scramble | OpenAPI documentation |
| **Testing** | PHPUnit 13 | Test framework |
| **Static Analysis** | PHPStan level 8 | Code quality |
| **Formatting** | Laravel Pint | Code formatting |
| **Frontend** | Next.js (separate repo) | Client application |
| **Deployment** | Docker + Dokploy | Container orchestration |
| **Process Manager** | Supervisord | Multi-process management |
