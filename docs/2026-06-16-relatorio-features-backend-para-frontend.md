# Relatório de Features & Funcionalidades — Backend SIGAPP

**Data:** 16/06/2026
**Objetivo:** Servir de base para o time de Frontend desenhar o design e o layout do aplicativo.
**Escopo:** Mapeamento completo das funcionalidades expostas pelo backend (API REST), regras de acesso, módulos de negócio e dados disponíveis.

> Este documento descreve **o que o backend oferece**. Não é um contrato de endpoints (para isso há o Scramble/OpenAPI em `/docs/api`), e sim um guia funcional para orientar a arquitetura de telas, navegação, controle de acesso e estados de UI.

---

## 1. Visão Geral do Produto

O **SIGAPP** é uma plataforma **SaaS multi-tenant** voltada para **incorporação imobiliária e desenvolvimento de terrenos**. Cada cliente (incorporadora) é um *tenant* isolado, acessado por subdomínio próprio (`https://{tenant}.sigapp.com.br`).

O produto cobre o ciclo completo de um terreno, da prospecção à legalização:

```
Prospecção → Viabilidade → Comitê → Negociação → Contrato → Legalização → Projeto Registrado
```

Sobre esse fluxo operam recursos transversais: **Dashboard/BI**, **Inteligência Artificial (SIG IA)**, **gestão documental**, **controle de acesso (RBAC)**, **billing/assinatura** e um **app mobile** (push + inbox de notificações).

### Stack técnica relevante para o frontend

| Aspecto | Detalhe |
|---|---|
| Framework | Laravel 13 (PHP 8.4) |
| Autenticação | Laravel Sanctum (tokens Bearer) |
| Multi-tenancy | `stancl/tenancy` — isolamento por subdomínio |
| Permissões | `spatie/laravel-permission` (RBAC) + entitlements por plano |
| Billing | Laravel Cashier + Stripe |
| IA | Laravel AI (agente com tools, embeddings, scoring) |
| Exports | PDF (Browsershot/Spatie) e Excel (Maatwebsite) |
| Idiomas | i18n no backend (pt-BR padrão), locale por usuário |
| Versionamento da API | Prefixo `/api/v1` |

### Dois contextos de aplicação

O frontend deverá tratar **dois "apps" distintos** que compartilham o mesmo backend:

1. **App Central** (`/api/v1` no domínio central) — portal público + painel administrativo do SIGAPP (gestão de tenants, planos, cupons, blog). Público-alvo: equipe interna SIGAPP e visitantes (signup/login).
2. **App Tenant** (`https://{tenant}.sigapp.com.br/api/v1`) — o produto em si, usado pelas incorporadoras. É aqui que está a maior parte das telas.

---

## 2. Modelo de Acesso (crítico para a navegação)

O que cada usuário vê é controlado por **três camadas combinadas**. O frontend precisa respeitar as três para montar menu, rotas e habilitar/desabilitar ações.

### 2.1 Entitlements de Plano (features e limites)

Cada **plano** habilita um conjunto de *features* e define *limites* numéricos. Endpoints checam isso via middleware `check.feature:*` e `enforce.limits:*`. Se a feature não está no plano, a rota retorna erro — o frontend deve **esconder ou bloquear** o recurso.

**Planos comerciais:**

| Plano | Slug | Preço (mensal) |
|---|---|---|
| SIG - Broker | `broker` | R$ 97,00 |
| SIG - Básico | `basico` | R$ 247,00 |
| SIG - Master | `master` | R$ 597,00 |
| SIG - Pro | `pro` | R$ 947,00 |

**Features (booleanas) que ligam/desligam módulos e sub-recursos:**

`home`, `prospection`, `committee`, `negotiation`, `legalizations`, `projects_room`, `product_settings`, `regionals`, `territorial_base`, `ai`,
`dashboard.enabled`, `dashboard.overview`, `dashboard.units_closed`, `dashboard.vgv`, `dashboard.funnel`,
`viabilities.enabled`, `viabilities.summary`, `viabilities.dre`, `viabilities.comercial`, `viabilities.cash_flow`, `viabilities.charts`, `viabilities.premises`, `viabilities.kpis`,
`exports.excel`, `exports.pdf`.

**Limites (numéricos):** `users`, `terrenos`, `products`, `storage_gb`, `ai_budget`.

> **Implicação de UI:** O endpoint de bootstrap (`GET /api/v1/start`) devolve módulos liberados + RBAC do usuário, pensado exatamente para montar navbar e *feature gating*. O frontend deve consumir isso no login e renderizar a navegação dinamicamente. Telas devem exibir estados de "recurso não incluído no seu plano" e "limite atingido" (ex.: `enforce.limits:terrenos`).

### 2.2 Módulos (estrutura de navegação)

Os módulos são organizados por **setores** e têm ordem de exibição definida no backend:

| Setor | Módulos (em ordem) |
|---|---|
| **Principal** | Dashboard |
| **Operação** | Prospecção, Corretores, Viabilidade, Comitê, Negociação, Legalização, Projetos, IA |
| **Configuração** | Configurações, Dados |
| **Inteligência** | Relatórios |
| **Administração** | Admin |

A Prospecção possui sub-módulos: **Terrenos** e **Mapas**.

### 2.3 RBAC — Papéis e níveis de acesso

**Papéis (roles):** `ADMIN`, `DIRECTOR`, `MANAGER`, `SUPERVISOR`, `ANALYST`, `USER`.

**Níveis de acesso por recurso:** `viewer` (ver), `editor` (editar), `manager` (gerenciar).

As permissões seguem o padrão `{módulo}.{recurso}.{nível}` (ex.: `prospection.terrains.editor`). O frontend deve usar isso para decidir botões/ações visíveis (criar, editar, excluir, aprovar). Há ainda **permissões de módulo por usuário**, ajustáveis pelo admin do tenant.

> **Resumo da regra de ouro para o frontend:** um recurso só aparece habilitado se **(a)** o plano inclui a feature, **(b)** o usuário tem a permissão RBAC, e **(c)** o limite não foi excedido.

---

## 3. Autenticação & Onboarding

### 3.1 Fluxo de Signup (público, no app central)

- `POST /signup` — cria a incorporadora (tenant). Provisionamento é **assíncrono**.
- `GET /signup/{sessionId}/status` — *polling* do status do provisionamento.
- `GET /tenant/subdomain-availability/{subdomain}` — valida disponibilidade do subdomínio em tempo real (input de cadastro).
- `GET /plans` e `GET /plans/{slug}` — catálogo público de planos (telas de pricing/checkout).

> **UI:** o signup precisa de tela de progresso/aguarde com polling até o tenant ficar `active` (estados: `pending`, `active`, `suspended`, `cancelled`, `setup_failed`).

### 3.2 Login — fluxo "broker" central

1. `POST /auth/login` (central) — autentica e, se o usuário pertence a múltiplos tenants, retorna a lista para seleção.
2. `POST /auth/select-tenant` — escolhe o tenant e gera um **ticket de transferência**.
3. No domínio do tenant: `POST /auth/exchange-ticket` — troca o ticket por um token de sessão do tenant.

### 3.3 Login direto no tenant

- `POST /auth/login` (no subdomínio do tenant).
- `POST /auth/password/forgot` e `POST /auth/password/reset` — recuperação de senha (com rate limit).

### 3.4 Sessão autenticada (tenant e central)

- `GET /auth/me` / `PUT /auth/me` — perfil do usuário logado.
- `POST /auth/refresh` — renova token.
- `POST /auth/logout` / `POST /auth/logout-all` — encerra sessão (atual ou todas).
- `PUT /locale` — troca idioma do usuário.

> **UI:** prever seletor de tenant (caso multi-tenant), seletor de idioma, tela de perfil editável e tratamento de expiração/refresh de token.

---

## 4. Módulos de Negócio (App Tenant)

### 4.1 Bootstrap da aplicação

- `GET /start` — payload inicial: módulos liberados, plano e RBAC do usuário (monta navbar e gating).
- `GET /modules` — lista de módulos.
- `GET /tenant` / `GET /tenant/usage` — dados do tenant e consumo vs. limites (barras de uso: terrenos, usuários, storage, etc.).

### 4.2 Prospecção — Terrenos (núcleo do produto)

Entidade central. Cada terreno carrega dados cadastrais, geográficos, financeiros e um **workflow** de estágio.

**Campos principais:** nome, endereço, estado, cidade, CEP, bairro, zona, distrito, operação urbana, `polygon_coords` (polígono do terreno), `static_map_url`, `area_calculada`, valor, observações, e datas do processo (apresentação, negociação, opção, descarte, contrato). Há também campos de **área útil**, **declividade** e **APP** (áreas de preservação).

**Operações (CRUD + ações):**
- `GET /terrenos` (listagem) · `GET /terrenos/filter` (busca avançada) · `GET /terrenos/select` (autocomplete).
- `POST /terrenos` · `GET/PUT/DELETE /terrenos/{id}`.
- **Informações complementares:** `GET/POST /terrenos/{id}/informacoes`, `PUT/DELETE /terrenos/informacoes/{infoId}`.
- **Geo:** `POST /terrenos/{id}/import-kmz` (importa polígono de arquivo KMZ), `POST /terrenos/{id}/recalculate-area` (recalcula área/declividade/APP).
- **Workflow:** `GET/POST /terrenos/{id}/workflow`, `PUT /terrenos/{id}/qualificacao`.
- **Timeline:** `GET /terrenos/{id}/timeline` (histórico de eventos do terreno).

**Workflow do terreno** (estágios, na sequência esperada):
```
em_analise → aguardando_viabilidade → viabilidade_aprovada → aguardando_comite →
negociacao_minuta → contrato_assinado → legalizando → legalizado_finalizado
```
Estados de encerramento: `descartado`, `arquivado`.

> **UI:** prever uma tela de detalhe de terreno rica, com mapa (polígono), abas (dados, informações, geo/declividade, viabilidades, documentos, timeline, tarefas) e um indicador visual de estágio do workflow (kanban ou stepper). Há um analisador geográfico (vias próximas, pontos de apoio: escolas, hospitais, mercados, bancos, etc.) exposto via IA — vale uma seção visual de "entorno".

### 4.3 Exportação de Terrenos

- `GET /terrenos/export/pdf` e `GET /terrenos/export/excel` (listagem completa).
- `GET /terrenos/{id}/export/pdf-detalhe` (detalhe de um terreno).
- `POST /terrenos/{id}/export/check-list` (PDF de checklist).
- `GET /terrenos/{id}/export/viabilidade` (PDF de viabilidade).

> Gated por `exports.pdf` / `exports.excel`. UI deve oferecer botões de exportação condicionais ao plano.

### 4.4 Viabilidade (motor financeiro)

Análise econômico-financeira de um terreno. Suporta **versionamento** (`version`, `is_current`), **snapshots**, **fluxo de aprovação** e geração de **DRE**.

**Operações:**
- CRUD: `apiResource viabilidades` + `GET /viabilidades/for-select`.
- Por terreno: `GET /viabilidades/terreno/{terrenoId}`, `.../latest`.
- `POST /viabilidades/compare` — comparar cenários.
- `POST /viabilidades/{id}/duplicate` · `.../recalcular` · `.../restore` · `.../ativar`.
- `POST /viabilidades/{id}/gerar-dre` — gera DRE (gated por `viabilities.dre`).
- **Aprovação:** `.../solicitar-aprovacao`, `.../aprovar`, `.../reprovar` (com rate limit dedicado).
- `GET /viabilidades/{id}/export-pdf`.

**Saída financeira (`resultados_dre`):** indicadores, totais, **fluxo de caixa mensal** e estrutura **DRE** completa. O motor calcula receitas, despesas, impostos (PIS/COFINS, ISS, ITBI/IPTU), comissões, custos de obra, despesas financeiras (CEF), curva de obra, VGV, e indicadores. Há perfis de financiamento: `cef` e `proprio`.

**Premissas de Viabilidade:** `apiResource premissas-viabilidade` — parâmetros padrão reaproveitáveis (gated por `viabilities.enabled` + permissão `configurations`).

> **UI:** esta é a tela mais densa em dados. Prever: formulário de premissas, tabela/gráficos de DRE, fluxo de caixa mensal (gráfico de linha/barras), cards de KPIs (VGV, margem, TIR), comparador de cenários lado a lado, e um fluxo de aprovação com estados (rascunho → solicitado → aprovado/reprovado). Sub-features (`summary`, `cash_flow`, `charts`, `kpis`) podem ser gated individualmente.

### 4.5 Comitê (aprovação multidisciplinar)

Revisão de um terreno por múltiplos departamentos antes de avançar.

- `GET /comite` (listagem) · `POST /comite` · `GET /comite/{id}`.
- `POST /comite/{id}/department-reviews` — parecer por departamento (decisão, comentários, checklist).
- `POST /comite/{id}/decision` — decisão final.

Inclui **pendências** (título, descrição, severidade, status) e **pareceres por departamento**. Status: `aguardando_comite` → finalizado.

> **UI:** painel de comitê com cards por departamento (status do parecer), lista de pendências com severidade (cores), e ação de decisão final.

### 4.6 Negociação & Contratos

**Negociação:**
- `GET/POST /negociacoes` · `GET/PUT /negociacoes/{id}`.
- `POST /negociacoes/{id}/events` — registra eventos/andamentos (histórico/timeline da negociação).

**Contratos:**
- `GET/POST /contratos` · `GET/PUT /contratos/{id}`.
- `POST /contratos/{id}/sign` — assinatura.
- Contratos têm **partes** (`ContratoParte`).

> **UI:** timeline de eventos da negociação, valores de proposta/modelo de negócio, e gestão de contrato com partes e ação de assinatura.

### 4.7 Legalização (gestão de obra/processo com Gantt)

Acompanhamento do processo de legalização com **etapas**, **dependências** e **custos**.

- `GET /legalizacoes/eligible-terrenos` · `apiResource legalizacoes`.
- `POST /legalizacoes/{id}/sync-gantt` — sincroniza cronograma (Gantt).
- `POST /legalizacoes/{id}/recalcular-progresso`.
- **Etapas:** CRUD em `/legalizacoes/{legalizacaoId}/etapas`, com `POST .../reorder` e `PATCH .../{id}/status`.

Campos da legalização: datas planejadas e reais (início/fim), `percentual_concluido`. Etapas têm fase/subfase, criticidade, obrigatoriedade, custos e dependências (estrutura de Gantt).

Status legalização: `planejado`, `em_andamento`, `concluido`, `cancelado`.
Status etapa: `pendente`, `em_andamento`, `concluida`, `bloqueada`, `atrasada`.

> **UI:** **gráfico de Gantt** com etapas, dependências, reordenação (drag), barra de progresso, e destaque para etapas críticas/atrasadas (cores por status).

### 4.8 Projetos (sala de projetos)

Agrupamento final do empreendimento.
- `apiResource projetos` (index, store, show, update) · `GET /projetos/eligible-terrenos`.
- `POST /projetos/{id}/marcar-pronto-registro` · `POST /projetos/{id}/cancelar`.

Status: `em_viabilidade`, `em_legalizacao`, `pronto_para_registro`, `finalizado`, `cancelado`.

### 4.9 Dados de apoio (módulo Configuração/Dados)

- **Corretores Externos:** `apiResource corretores-externos` + `/select`.
- **Regionais:** `apiResource regionais` + `/select` (gated `regionals`).
- **Produtos:** `apiResource produtos` + `/select` + `POST /produtos/{produto}/restore` (gated `product_settings`, limite `products`). Produtos têm curva de obra e balões.
- **Proprietários:** `apiResource proprietarios` + `/select`.
- **Terreno-Produtos:** vínculo produto↔terreno — `apiResource terreno-produtos`, `GET /terreno-produtos/by-terreno/{terrenoId}`.

### 4.10 Documentos

Gestão documental anexada a terrenos (arquivos privados).
- `apiResource documentos` (limite `storage_gb` no upload).
- `GET /documentos/tipos` · `/categorias` (taxonomia para selects).
- `GET /documentos/{id}/view` · `/download` (visualização e download seguros).

Tipos comuns: matrícula, escritura, IPTU, etc., com categoria e status (pendente/aprovado).

### 4.11 Base territorial (Cidades / IBGE)

- `GET /cidades/estados` · `/cidades/{estado}` · `/cidades/buscar` · `/cidades/dados` (gated `territorial_base`).
- `GET /municipios/{ibge_codigo}/dados-sidra` — dados externos do IBGE (SIDRA): panorama, PIB, renda, habitação, trabalho.

> **UI:** selects de estado/cidade, e possivelmente um "perfil de município" com indicadores IBGE.

---

## 5. Dashboard / BI

Endpoints prontos para alimentar gráficos (gated por `dashboard.*`):

| Endpoint | Conteúdo | Gate |
|---|---|---|
| `GET /dashboard/overview` | Visão geral | `dashboard.enabled` |
| `GET /dashboard/cards` | Cards/KPIs resumo | |
| `GET /dashboard/resumo` | Resumo geral | |
| `GET /dashboard/status-chart` | Distribuição por status (pizza/donut) | |
| `GET /dashboard/cadastros-mensais` | Série temporal de cadastros | |
| `GET /dashboard/cadastros-mensais-responsavel` | Cadastros por responsável | |
| `GET /dashboard/terrenos-responsavel` | Terrenos por responsável | |
| `GET /dashboard/top-cidades` | Ranking de cidades | |
| `GET /dashboard/vgv-anual` | VGV anual | `dashboard.vgv` |
| `GET /dashboard/unidades-fechadas-anual` | Unidades fechadas/ano | `dashboard.units_closed` |
| `GET /dashboard/anos-disponiveis` | Filtro de anos | |
| `GET /dashboard/area-opcao-detalhe` | Detalhe de área/opção | |

> **UI:** dashboard com cards de KPI no topo + grid de gráficos (linha, barra, pizza, ranking). Filtros por ano e responsável. Gráficos sensíveis ao plano (VGV e unidades fechadas podem estar ocultos).

---

## 6. Inteligência Artificial (SIG IA)

Diferencial do produto. Gated por feature `ai` e por **orçamento** (`ai_budget`) — middleware `ai.budget` e `ai.rate_limit`.

### 6.1 Chat com agente (assistente conversacional)

- `POST /ai/sig-ai` — conversa com o agente **SIG IA** (especialista em terrenos/viabilidade, responde em pt-BR).
- `GET /ai/conversations` · `GET /ai/conversations/{id}/messages` — histórico de conversas.
- `GET /ai/budget` — status do orçamento de IA (consumo vs. limite).

O agente acessa o sistema via **ferramentas** (tools): listar/detalhar terrenos, viabilidades, legalização, comitê, negociação, documentos, dashboard, tarefas, análise geográfica, perfil IBGE, score, tendências, anomalias, e ações diretas (criar/atualizar tarefas, transicionar workflow, gerar PDFs, gerar insights).

> **UI:** interface de chat (estilo assistente) com histórico de conversas na lateral, indicador de orçamento/uso de IA, e renderização de respostas ricas (tabelas, insights, links para terrenos).

### 6.2 Scoring (ranking inteligente de terrenos)

- `GET /ai/scoring/ranking` — ranking de terrenos por score.
- `GET /ai/scoring/{terreno_id}` — score de um terreno.
- `POST /ai/scoring/recalculate` — recalcular todos.

> **UI:** tela/aba de ranking com score visual (badge, barra), e score no detalhe do terreno.

### 6.3 Análise Preditiva

- `GET /ai/predictive/approval/{terreno_id}` — probabilidade de aprovação.
- `GET /ai/predictive/vgv/{terreno_id}` — estimativa de VGV.
- `GET /ai/predictive/stalling` — previsão de terrenos "travando" no funil.

### 6.4 Automação

- `POST /ai/automation/tasks` · `PUT /ai/automation/tasks/{taskId}` — tarefas automatizadas.
- `POST /ai/automation/workflow/transition` — transição de workflow assistida.
- `GET /ai/automation/monitor` — monitor proativo (alertas/anomalias).

> **UI:** painel de "insights/alertas" (monitor proativo), badges preditivos no terreno (chance de aprovação, VGV estimado), e widget de "terrenos em risco de estagnação".

---

## 7. Billing & Assinatura (App Tenant, perfil admin)

Acessível ao **admin do tenant**. Parte dos endpoints funciona mesmo com assinatura suspensa (para reativação).

- `GET /tenant/subscription` — dados da assinatura atual.
- `POST /tenant/subscription/swap` — troca de plano (upgrade/downgrade).
- `POST /tenant/billing-portal` — portal Stripe.
- `POST /tenant/billing/setup-intent` · `POST /tenant/billing/payment-method` — método de pagamento.
- `POST /tenant/billing/coupon/redeem` — resgatar cupom.
- `GET /tenant/billing/payment-status` · `POST /tenant/billing/retry-payment` — **dunning** (cobrança em atraso).
- **Histórico:** `GET /tenant/billing/history`, `GET /tenant/billing/invoices/{id}`, `GET /tenant/billing/invoices/{id}/pdf`.

> **UI:** página de assinatura com plano atual + uso, comparador de planos para swap, gestão de cartão, banner de pagamento em atraso (dunning) com CTA de "tentar novamente", e lista de faturas com download de PDF. Estados de assinatura bloqueiam o app (middleware `CheckSubscriptionStatus`) — prever **tela/bloqueio de "assinatura suspensa"**.

---

## 8. Administração do Tenant (RBAC interno)

Perfil **admin do tenant** gerencia sua própria equipe:

- **Usuários:** `apiResource tenant-admin/users` + `PUT .../{id}/module-permissions` (limite `users`).
- **Papéis:** `apiResource tenant-admin/roles` + `/select`.
- **Permissões:** `apiResource tenant-admin/permissions`.
- **Departamentos:** `apiResource tenant-admin/departments` + `/select`.
- **Cargos (Positions):** `apiResource tenant-admin/positions` + `/select`.
- `GET /users/for-select` — usuários para selects em formulários.

> **UI:** área de administração com gestão de usuários (com vínculo a departamento/cargo), matriz de permissões por módulo, e CRUD de papéis/departamentos/cargos.

---

## 9. App Mobile (push + notificações)

- `POST /mobile/devices` · `DELETE /mobile/devices/{installationId}` — registro/baixa de dispositivos (push).
- `GET /mobile/notifications` · `POST /mobile/notifications/{id}/read` — inbox de notificações.

> **UI:** o backend já suporta um cliente mobile com push e inbox de notificações (badge de não lidas, marcar como lida).

---

## 10. Painel Administrativo Central (equipe SIGAPP)

Aplicação separada, para a equipe interna (`central.admin`):

- **Dashboard administrativo:** `GET /admin/dashboard`.
- **Tenants:** `GET /admin/tenants`, `GET /admin/tenants/{id}`, `POST .../activate`, `POST .../suspend`.
- **Planos do tenant:** atribuir/upgrade/downgrade (`/admin/tenants/{id}/plan/...`).
- **Entitlements extras por tenant:** CRUD em `/admin/tenants/{id}/entitlements`.
- **Planos (catálogo):** `apiResource admin/plans` + `PUT /admin/plans/{plan}/entitlements`.
- **Entitlements (catálogo):** `apiResource admin/entitlements`.
- **Cupons:** `apiResource admin/coupons`.
- **Usuários internos:** `apiResource admin/users`.
- **ACL:** `GET /admin/acl/catalog`, `GET /admin/acl/plans/{planId}/role-matrix` (matriz papel×permissão por plano).
- **Auditoria:** `GET /admin/audit-logs`.
- **Blog:** `apiResource admin/posts` (público: `GET /blog`, `/blog/categories`, `/blog/{slug}`).
- **Status de tenants:** `GET /tenant-status`.

> **UI:** painel admin "back-office" — tabelas de tenants com ações de ativar/suspender, editor de planos e entitlements, gestão de cupons, CMS de blog, e visualizador de logs de auditoria.

---

## 11. Funcionalidades Transversais

- **i18n:** `PUT /locale` (central e tenant). Backend já localiza labels de módulos/enums — UI deve suportar troca de idioma por usuário.
- **LGPD / Consentimento:** `POST /consent-log` (público) — registro de consentimento de cookies. Prever banner de cookies.
- **Health checks:** `GET /api/v1/health` (público), `GET /api/health` (tenant, autenticado), `GET /health/details` (admin).
- **Rate limiting:** quase todas as rotas têm limites; o frontend deve tratar **HTTP 429** com mensagens amigáveis (login, reset de senha, IA, aprovações).
- **Auditoria & Timeline:** ações relevantes geram registros de atividade/histórico — alimentam timelines de terreno e negociação.
- **Documentação de API:** Scramble disponível (OpenAPI) para o contrato detalhado de cada endpoint.

---

## 12. Recomendações para o Frontend

1. **Navegação data-driven:** montar o menu a partir de `GET /start` (módulos + RBAC + plano). Não hardcodar a navbar — ela varia por plano e por usuário.
2. **Feature gating em três camadas:** sempre cruzar plano (feature) × permissão (RBAC) × limite. Definir componentes reutilizáveis: `<FeatureGate>`, `<Can>`, `<LimitGuard>`.
3. **Estados de bloqueio explícitos:** "recurso não incluído no plano" (com CTA de upgrade), "limite atingido", "assinatura suspensa", "sem permissão". São jornadas, não apenas erros.
4. **Terreno como hub central:** desenhar o detalhe de terreno como página-âncora com abas integrando viabilidade, comitê, negociação, legalização, documentos, geo, timeline, tarefas e score de IA.
5. **Visualizações pesadas:** mapa com polígono (terreno), Gantt (legalização), gráficos financeiros (viabilidade/DRE/fluxo de caixa), dashboards de BI, chat de IA. Avaliar bibliotecas adequadas cedo.
6. **Workflow visual:** representar o fluxo do terreno (stepper/kanban) com os 8 estágios + estados de encerramento.
7. **Dois shells de aplicação:** App Central (público + admin SIGAPP) e App Tenant. Podem ser projetos/temas distintos compartilhando design system.
8. **Tratamento de assíncrono:** signup (polling), exports (PDF/Excel podem demorar), e IA (respostas longas) precisam de estados de loading/progresso bem definidos.

---

*Documento gerado a partir da análise do código do backend (rotas, controllers, services, models, enums e seeders). Para o contrato técnico exato de cada endpoint (payloads, schemas), consultar a documentação OpenAPI/Scramble do projeto.*
