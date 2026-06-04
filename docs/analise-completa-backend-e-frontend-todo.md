# 🔍 Análise Completa do Backend + TODO para Frontend

> **Data:** 04/06/2026
> **Projeto:** SIGAPP — Sistema de Gestão de Terrenos e Incorporação
> **Backend:** Laravel 13+ Multi-Tenant

---

## Sumário

1. [Visão Geral da Arquitetura](#1-visão-geral-da-arquitetura)
2. [Entidades/Recursos Principais](#2-entidadesrecursos-principais)
3. [Endpoints da API](#3-endpoints-da-api)
4. [Fluxos de Autenticação](#4-fluxos-de-autenticação)
5. [Padrões de Resposta e Formato](#5-padrões-de-resposta-e-formato)
6. [Feature Flags e Controle de Acesso](#6-feature-flags-e-controle-de-acesso)
7. [TODO para Frontend](#7-todo-para-frontend)

---

## 1. Visão Geral da Arquitetura

**Stack do Backend:**
- Laravel 13+
- PHP 8.3+
- MySQL / PostgreSQL (com pgvector para busca semântica)
- Multi-Tenancy via `stancl/tenancy` (banco separado por tenant)
- Autenticação: Laravel Sanctum (token-based)
- Pagamento: Stripe via Laravel Cashier
- IA: Laravel AI SDK (`laravel/ai`) — provider-agnóstico (OpenRouter configurado)
- RBAC: `spatie/laravel-permission`
- Exportação: `spatie/laravel-pdf` (Browsershot/Chrome) + `maatwebsite/excel`
- Documentação: `dedoc/scramble`

### Estrutura Multi-Tenant

```
DOMÍNIOS CENTRAIS (config/tenancy.php → central_domains)
  ├── sigapp.com.br (app central)
  └── admin.sigapp.com.br (admin)

CADA TENANT → {slug}.sigapp.com.br
  ├── banco de dados próprio
  └── domínio próprio (ex: construtora.sigapp.com.br)
```

### Separação de Responsabilidades

```
Central App (sigapp.com.br)
  ├── Landing Page, Blog, Planos
  ├── Signup (cria tenant + sessão Stripe Checkout)
  ├── Admin Central (gerencia tenants, planos, usuários, posts, cupons)
  ├── Auth Broker (login central → descoberta de tenants)
  └── Webhooks Stripe

Tenant App ({slug}.sigapp.com.br)
  ├── Gestão completa de terrenos, viabilidades, legalizações
  ├── Comitê, Negociação, Contratos, Projetos
  ├── Dashboard com gráficos e indicadores
  ├── Documentos (upload/download/view)
  ├── Assistente IA (chat com streaming SSE)
  ├── RBAC próprio (usuários, cargos, permissões, departamentos, cargos)
  ├── Billing (troca de plano, histórico, cupons)
  └── Mobile (notificações push)
```

---

## 2. Entidades/Recursos Principais

| Entidade | Descrição | Módulo |
|---|---|---|
| `Terreno` | Terreno/área — entidade central do sistema | Prospecção |
| `Viabilidade` | Estudo de viabilidade financeira com DRE completo | Viabilidade |
| `PremissasViabilidade` | Premissas globais de viabilidade | Configurações |
| `Legalizacao` | Processo de legalização do terreno | Legal |
| `LegalizacaoEtapa` | Etapas do cronograma de legalização (formato Gantt) | Legal |
| `Negociacao` | Negociação com proprietário do terreno | Negociação |
| `NegociacaoEvento` | Eventos/histórico da negociação | Negociação |
| `Contrato` | Contrato assinado | Negociação |
| `ContratoParte` | Partes envolvidas no contrato | Negociação |
| `ComiteRevisao` | Reunião de comitê para aprovação | Comitê |
| `ComiteParecerDepartamento` | Parecer de cada departamento no comitê | Comitê |
| `ComitePendencia` | Pendências apontadas no comitê | Comitê |
| `Projeto` | Projeto do terreno (pós-contrato) | Projetos |
| `Produto` | Tipo de produto (lote, apto, sala, casa, etc) | Configurações |
| `TerrenoProduto` | Associação produto × terreno com quantidades | Prospecção |
| `Proprietario` | Proprietário do terreno (pessoa física/jurídica) | Prospecção |
| `Regional` | Regional/escritório regional | Configurações |
| `CorretorExterno` | Corretor externo terceirizado | Prospecção |
| `Documento` | Documento anexado com upload de arquivo | Dados |
| `TerrenoInfos` | Notas/informações adicionais do terreno | Prospecção |
| `TerrenoContato` | Contatos associados ao terreno | Prospecção |
| `User` (Tenant) | Usuário do tenant (com departamento, cargo, roles) | Admin |
| `Department` | Departamento organizacional | Admin |
| `Position` | Cargo/função | Admin |
| `Role` (Spatie) | Perfil de acesso (RBAC) | Admin |
| `Permission` (Spatie) | Permissão granular | Admin |
| `Task` | Tarefa associada a terreno | Prospecção |
| `Comment` | Comentário em terreno | Prospecção |
| `StatusHistory` | Histórico de mudanças de status | Prospecção |
| `EntityActivity` | Atividades recentes (timeline) | Prospecção |
| `Plan` | Plano de assinatura | Central |
| `Entitlement` | Funcionalidade/feature atômica | Central |
| `Coupon` | Cupom de desconto | Billing |
| `Tenant` (Central) | Cliente/empresa (com domínios, assinatura) | Central |
| `Post` (Blog) | Post do blog institucional | Central |
| `AuditLog` | Log de auditoria | Central |
| `Cidade` | Base de cidades brasileiras (IBGE) | Central |

---

## 3. Endpoints da API

### 3.1 CENTRAL APP — `/api/v1` (domínio central)

#### Públicos (sem autenticação)

| Método | Rota | Descrição | Rate Limit |
|---|---|---|---|
| `GET` | `/tenant/subdomain-availability/{subdomain}` | Verifica disponibilidade de subdomínio | api-public |
| `GET` | `/health` | Health check completo (DB, cache, storage, queue, Stripe, OpenRouter) | api-public |
| `GET` | `/plans` | Lista planos de assinatura ativos | api-public |
| `GET` | `/plans/{slug}` | Detalhe de um plano por slug | api-public |
| `POST` | `/signup` | Cria novo tenant + sessão Stripe Checkout | api-public |
| `GET` | `/signup/{sessionId}/status` | Status do checkout Stripe | signup-status |
| `POST` | `/webhook/stripe` | Webhook do Stripe (sem CSRF/throttle) | — |
| `POST` | `/auth/login` | Login central (broker) — descoberta de tenants | central-login |
| `POST` | `/auth/select-tenant` | Seleciona tenant após múltiplos matches | central-login-select |
| `POST` | `/auth/password/forgot` | Solicita redefinição de senha | password-reset-request |
| `POST` | `/auth/password/reset` | Redefine senha com token | password-reset-submit |
| `GET` | `/blog` | Lista posts do blog (paginado) | api-public |
| `GET` | `/blog/categories` | Categorias do blog | api-public |
| `GET` | `/blog/{slug}` | Post individual do blog | api-public |
| `POST` | `/admin/login` | Login do admin central | admin-login |

#### Admin Central (autenticado: admin)

| Método | Rota | Descrição |
|---|---|---|
| `PUT` | `/locale` | Define idioma do usuário |
| `POST` | `/auth/logout` | Logout (revoga token atual) |
| `POST` | `/auth/logout-all` | Logout de todos os dispositivos |
| `POST` | `/auth/refresh` | Renova token atual |
| `GET` | `/auth/me` | Dados do usuário autenticado |
| `GET` | `/tenant-status` | Visão geral dos status dos tenants |
| `GET` | `/admin/dashboard` | Dashboard do admin |
| `GET/POST` | `/admin/posts` | CRUD posts do blog |
| `GET/PUT/DELETE` | `/admin/posts/{post}` | CRUD post individual |
| `GET` | `/admin/tenants` | Lista tenants (com filtros) |
| `GET` | `/admin/tenants/{tenant}` | Detalhe do tenant |
| `POST` | `/admin/tenants/{tenant}/activate` | Ativa tenant |
| `POST` | `/admin/tenants/{tenant}/suspend` | Suspende tenant |
| `POST` | `/admin/tenants/{id}/plan` | Atribui plano a tenant |
| `PUT` | `/admin/tenants/{id}/plan/upgrade` | Upgrade de plano |
| `PUT` | `/admin/tenants/{id}/plan/downgrade` | Downgrade de plano |
| `GET` | `/admin/tenants/{id}/entitlements` | Lista entitlements extras |
| `POST` | `/admin/tenants/{id}/entitlements` | Adiciona entitlement extra |
| `PUT` | `/admin/tenants/{id}/entitlements/{eId}` | Atualiza entitlement extra |
| `DELETE` | `/admin/tenants/{id}/entitlements/{eId}` | Remove entitlement extra |
| `GET/POST` | `/admin/users` | CRUD usuários centrais |
| `GET/PUT/DELETE` | `/admin/users/{user}` | CRUD usuário individual |
| `GET` | `/admin/audit-logs` | Logs de auditoria |
| `GET` | `/admin/acl/catalog` | Catálogo ACL completo |
| `GET` | `/admin/acl/plans/{planId}/role-matrix` | Matriz plano × roles |
| `GET/POST` | `/admin/plans` | CRUD planos |
| `GET/PUT/DELETE` | `/admin/plans/{plan}` | CRUD plano individual |
| `PUT` | `/admin/plans/{plan}/entitlements` | Sincroniza entitlements do plano |
| `GET/POST` | `/admin/entitlements` | CRUD entitlements |
| `GET/PUT/DELETE` | `/admin/entitlements/{entitlement}` | CRUD entitlement individual |
| `GET/POST` | `/admin/coupons` | CRUD cupons |
| `GET/PUT/DELETE` | `/admin/coupons/{coupon}` | CRUD cupom individual |

### 3.2 TENANT APP — `/api/v1` (domínio {slug}.sigapp.com.br)

#### Públicos (sem autenticação)

| Método | Rota | Descrição | Rate Limit |
|---|---|---|---|
| `POST` | `/auth/login` | Login direto no tenant | api-auth |
| `POST` | `/auth/exchange-ticket` | Troca ticket do broker por token | transfer-ticket |
| `POST` | `/auth/password/forgot` | Solicita redefinição de senha | password-reset-request |
| `POST` | `/auth/password/reset` | Redefine senha | password-reset-submit |

#### Autenticados (qualquer usuário logado)

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/auth/logout` | Logout |
| `POST` | `/auth/logout-all` | Logout todos dispositivos |
| `POST` | `/auth/refresh` | Renova token |
| `GET` | `/auth/me` | Usuário autenticado (com roles, permissões, departamento, cargo) |
| `PUT` | `/auth/me` | Atualiza perfil próprio |
| `PUT` | `/locale` | Define idioma |
| `GET` | `/start` | Bootstrap: módulos habilitados + permissões do usuário |
| `GET` | `/modules` | Lista de módulos disponíveis |

#### Gestão do Tenant (requer role ADMIN ou permissão específica)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/tenant/subscription` | Dados da assinatura Stripe |
| `POST` | `/tenant/billing-portal` | Gera link para portal Stripe |
| `POST` | `/tenant/subscription/swap` | Troca de plano (com prorata) |
| `POST` | `/tenant/billing/setup-intent` | Cria SetupIntent para novo cartão |
| `POST` | `/tenant/billing/payment-method` | Atualiza método de pagamento padrão |
| `POST` | `/tenant/billing/coupon/redeem` | Resgata cupom de desconto |
| `GET` | `/tenant/billing/payment-status` | Status de cobrança (dunning) |
| `POST` | `/tenant/billing/retry-payment` | Retenta pagamento falho |
| `GET` | `/tenant` | Dados do tenant |
| `GET` | `/tenant/usage` | Métricas de uso (limites vs consumo) |
| `GET` | `/tenant/billing/history` | Histórico de faturas |
| `GET` | `/tenant/billing/invoices/{id}` | Detalhe da fatura |
| `GET` | `/tenant/billing/invoices/{id}/pdf` | Download PDF da fatura |

#### Dashboard

| Método | Rota | Descrição | Query Params |
|---|---|---|---|
| `GET` | `/dashboard/overview` | Overview agregado (substitui múltiplas chamadas) | `?include=cards,status_chart,cadastros_mensais,top_cidades,vgv_anual,resumo,area_opcao_detalhe&ano=&mes=&meses=12&force_refresh=true` |
| `GET` | `/dashboard/cards` | Cards principais | — |
| `GET` | `/dashboard/status-chart` | Gráfico de barras por status | `?ano=` |
| `GET` | `/dashboard/cadastros-mensais` | Cadastros por mês (barras) | `?ano=&meses=12` |
| `GET` | `/dashboard/terrenos-responsavel` | Terrenos agrupados por responsável | `?filtro=geral\|ano\|mes&ano=&mes=&limit=` |
| `GET` | `/dashboard/top-cidades` | Top N cidades | `?filtro=geral\|ano\|mes&ano=&mes=&limit=10` |
| `GET` | `/dashboard/vgv-anual` | Soma VGV por ano | — |
| `GET` | `/dashboard/unidades-fechadas-anual` | Unidades fechadas por ano | — |
| `GET` | `/dashboard/cadastros-mensais-responsavel` | Cadastros mensais por responsável | `?ano=&meses=12&responsavel_id=` |
| `GET` | `/dashboard/resumo` | Resumo geral consolidado | — |
| `GET` | `/dashboard/anos-disponiveis` | Lista de anos com dados | — |
| `GET` | `/dashboard/area-opcao-detalhe` | Detalhe áreas em opção | `?ano=&limit=` |

#### Terrenos (feature: prospection)

| Método | Rota | Descrição | Middleware Extra |
|---|---|---|---|
| `GET` | `/terrenos` | Lista paginada | — |
| `POST` | `/terrenos` | Cria terreno | `permission.gate:prospection,terrains` + `enforce.limits:terrenos` |
| `GET` | `/terrenos/{id}` | Detalhe completo | — |
| `PUT` | `/terrenos/{id}` | Atualiza | — |
| `DELETE` | `/terrenos/{id}` | Soft delete | — |
| `GET` | `/terrenos/filter` | Filtro avançado (multi-campo) | — |
| `GET` | `/terrenos/select` | Lista simplificada para selects | — |
| `GET` | `/terrenos/{id}/informacoes` | Notas do terreno | — |
| `POST` | `/terrenos/{id}/informacoes` | Adiciona nota | — |
| `PUT` | `/terrenos/informacoes/{infoId}` | Edita nota | — |
| `DELETE` | `/terrenos/informacoes/{infoId}` | Remove nota | — |
| `GET` | `/terrenos/{id}/workflow` | Status atual + transições disponíveis | — |
| `POST` | `/terrenos/{id}/workflow` | Executa transição de workflow | — |
| `PUT` | `/terrenos/{id}/qualificacao` | Atualiza dados de qualificação | — |
| `POST` | `/terrenos/{id}/import-kmz` | Importa polígono de arquivo KMZ/KML | — |
| `POST` | `/terrenos/{id}/recalculate-area` | Dispara cálculo assíncrono de área útil | — |
| `GET` | `/terrenos/{id}/timeline` | Timeline de atividades | — |
| `GET` | `/terrenos/export/pdf` | Exporta lista em PDF | feature: exports.pdf |
| `GET` | `/terrenos/export/excel` | Exporta lista em Excel | feature: exports.excel |
| `GET` | `/terrenos/{id}/export/pdf-detalhe` | Exporta detalhe em PDF | feature: exports.pdf |
| `POST` | `/terrenos/{id}/export/check-list` | Exporta checklist PDF | feature: exports.pdf |
| `GET` | `/terrenos/{id}/export/viabilidade` | Exporta viabilidade PDF | features: viabilities + exports.pdf |

##### Workflow de Terreno

O workflow é composto por **stage** (agrupador) e **status_code** (posição atual):

```
Stage: captacao
  ├── em_analise (🔵 #0EA5E9)
  └── aguardando_viabilidade (🟡 #F59E0B)

Stage: viabilidade
  └── viabilidade_aprovada (🟢 #10B981)

Stage: comite
  └── aguardando_comite (🔷 #06B6D4)

Stage: negociacao_contrato
  ├── negociacao_minuta (🟣 #8B5CF6)
  └── contrato_assinado (🟢 #047857)

Stage: legalizacao
  └── legalizando (🟡 #65A30D)

Stage: encerramento
  ├── legalizado_finalizado (🟩 #0F766E)
  ├── descartado (🔴 #E11D48)
  └── arquivado (⬜ #475569)
```

Cada transição pode ter **pré-requisitos** (checklist) — o endpoint `GET /terrenos/{id}/workflow` retorna as transições disponíveis e as bloqueadas com o motivo.

#### Viabilidades (feature: viabilities.enabled)

| Método | Rota | Descrição | Middleware Extra |
|---|---|---|---|
| `GET` | `/viabilidades` | Lista paginada | — |
| `POST` | `/viabilidades` | Cria e já gera DRE | — |
| `GET` | `/viabilidades/{id}` | Detalhe com DRE | `?include=dre,indicadores,fluxo_mensal,...` |
| `PUT` | `/viabilidades/{id}` | Atualiza e recalcula DRE | — |
| `DELETE` | `/viabilidades/{id}` | Soft delete | — |
| `GET` | `/viabilidades/for-select` | Para campos de seleção | `?terreno_id=` |
| `GET` | `/viabilidades/terreno/{terrenoId}` | Todas de um terreno | — |
| `GET` | `/viabilidades/terreno/{terrenoId}/latest` | Última versão ativa | — |
| `POST` | `/viabilidades/compare` | Compara duas viabilidades | Body: `{ viabilidade_1_id, viabilidade_2_id }` |
| `GET` | `/viabilidades/{id}/export-pdf` | Exporta PDF formatado | feature: exports.pdf |
| `POST` | `/viabilidades/{id}/solicitar-aprovacao` | Solicita aprovação | throttle: viabilidade-approval |
| `POST` | `/viabilidades/{id}/aprovar` | Aprova | throttle: viabilidade-approval |
| `POST` | `/viabilidades/{id}/reprovar` | Reprova | throttle: viabilidade-approval |
| `POST` | `/viabilidades/{id}/ativar` | Ativa (rascunho → ativo) | — |
| `POST` | `/viabilidades/{id}/duplicate` | Duplica viabilidade | — |
| `POST` | `/viabilidades/{id}/gerar-dre` | Gera/regenera DRE | — |
| `POST` | `/viabilidades/{id}/recalcular` | Recalcula DRE | — |
| `POST` | `/viabilidades/{id}/restore` | Restaura excluída | — |

**Include params da ViabilidadeCalculationResource:**
- Default: `resumo, indicadores, produtos_resumo`
- Opcionais: `dre, dre_caixa, dre_contabil_poc, dre_contabil_poc_mensal, dre_contabil_poc_mensal_blocos, ponte_reconciliacao, fluxo_mensal, fluxo_mensal_financeiro, totais, dados_produtos, parametros_utilizados`

**Body de criação/atualização (ViabilidadeRequest):** Dezenas de campos financeiros:
- `terreno_id`, `version`, `parceria_vgv`, `compra_terreno`, `infra_nao_incidente`
- `porcentagem_lote_proprietario`, `prazo_obra`, `prazo_lancamento`, `prazo_incorporacao`
- `pis_cofins`, `iss`, `outros_impostos`, `comissao`, `incorporacao`, `area_comum`
- `contrapartidas`, `canteiro_mensal`, `mo_administrativa`, `seguros`, `assistencia_tecnica`
- `despesas_comerciais`, `stand_vendas`, `mobilia_decoracao`, `gastos_mensais_stand`
- Múltiplos campos de comissão, bônus, marketing, custos CEF, etc.
- `perfil_financiamento` (enum: `cef`, `proprio`, `misto`)
- `produtos` (array de produtos com quantidades e preços)

#### Premissas de Viabilidade (feature: viabilities.enabled, perm: configurations)

| Método | Rota |
|---|---|
| `GET` `/premissas-viabilidade` | Lista |
| `POST` `/premissas-viabilidade` | Cria |
| `GET` `/premissas-viabilidade/{id}` | Detalhe |
| `PUT` `/premissas-viabilidade/{id}` | Atualiza |
| `DELETE` `/premissas-viabilidade/{id}` | Exclui |

#### Legalizações (feature: legalizations)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/legalizacoes` | Lista paginada |
| `POST` | `/legalizacoes` | Cria |
| `GET` | `/legalizacoes/{id}` | Detalhe (com etapas + dependências) |
| `PUT` | `/legalizacoes/{id}` | Atualiza |
| `DELETE` | `/legalizacoes/{id}` | Exclui |
| `GET` | `/legalizacoes/eligible-terrenos` | Terrenos elegíveis (status opção, sem legalização) |
| `POST` | `/legalizacoes/{id}/sync-gantt` | Sincroniza Gantt completo (etapas + dependências em lote) |
| `POST` | `/legalizacoes/{id}/recalcular-progresso` | Recalcula % concluído baseado nas etapas |

**Etapas de Legalização (sub-recurso):**

| Método | Rota |
|---|---|
| `GET` | `/legalizacoes/{legalizacaoId}/etapas` |
| `POST` | `/legalizacoes/{legalizacaoId}/etapas` |
| `GET` | `/legalizacoes/{legalizacaoId}/etapas/{id}` |
| `PUT` | `/legalizacoes/{legalizacaoId}/etapas/{id}` |
| `DELETE` | `/legalizacoes/{legalizacaoId}/etapas/{id}` |
| `POST` | `/legalizacoes/{legalizacaoId}/etapas/reorder` |
| `PATCH` | `/legalizacoes/{legalizacaoId}/etapas/{id}/status` |

Cada etapa tem: `titulo`, `descricao`, `ordem`, `status` (enum), `inicio_planejado`, `fim_planejado`, `inicio_real`, `fim_real`, `percentual`, `responsavel_id`, `cor`, `custos[]`, `dependencias[]`.

#### Comitê (feature: committee)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/comite` | Lista |
| `POST` | `/comite` | Cria nova revisão |
| `GET` | `/comite/{id}` | Detalhe com pareceres |
| `POST` | `/comite/{id}/department-reviews` | Upsert parecer de departamento |
| `POST` | `/comite/{id}/decision` | Finaliza decisão (aprova/reprova) |

#### Negociação e Contratos (feature: negotiation)

| Método | Rota |
|---|---|
| `GET` `/negociacoes` | Lista |
| `POST` `/negociacoes` | Cria |
| `GET` `/negociacoes/{id}` | Detalhe |
| `PUT` `/negociacoes/{id}` | Atualiza |
| `POST` `/negociacoes/{id}/events` | Adiciona evento |
| `GET` `/contratos` | Lista |
| `POST` `/contratos` | Cria |
| `GET` `/contratos/{id}` | Detalhe |
| `PUT` `/contratos/{id}` | Atualiza |
| `POST` `/contratos/{id}/sign` | Registra assinatura |

#### Projetos (feature: projects_room)

| Método | Rota |
|---|---|
| `GET` `/projetos` | Lista |
| `POST` `/projetos` | Cria |
| `GET` `/projetos/{id}` | Detalhe |
| `PUT` `/projetos/{id}` | Atualiza |
| `GET` `/projetos/eligible-terrenos` | Terrenos elegíveis |
| `POST` `/projetos/{id}/marcar-pronto-registro` | Marca como pronto para registro |
| `POST` `/projetos/{id}/cancelar` | Cancela projeto |

#### Documentos

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/documentos` | Lista (filtros: `terreno_id`, `tipo`, `categoria`, `search`) |
| `POST` | `/documentos` | Upload multipart (`arquivo`, `nome`, `tipo`, `categoria`, `terreno_id`, `descricao`) |
| `GET` | `/documentos/{id}` | Detalhe |
| `PUT` | `/documentos/{id}` | Atualiza metadados |
| `DELETE` | `/documentos/{id}` | Exclui (remove arquivo físico) |
| `GET` | `/documentos/tipos` | Lista tipos disponíveis (enumerator) |
| `GET` | `/documentos/categorias` | Lista categorias disponíveis |
| `GET` | `/documentos/{id}/view` | Visualiza arquivo (inline no browser) |
| `GET` | `/documentos/{id}/download` | Download do arquivo |

**Tipos de Documento:**
```
escritura, matricula, certidao_negativa, iptu, planta,
levantamento_topografico, laudo_ambiental, viabilidade,
contrato, procuracao, rg_cpf, comprovante_residencia, outros
```

**Categorias:**
```
juridico, tecnico, financeiro, ambiental, pessoal
```

#### Corretores Externos

| Método | Rota |
|---|---|
| `GET/POST` | `/corretores-externos` |
| `GET/PUT/DELETE` | `/corretores-externos/{id}` |
| `GET` | `/corretores-externos/select` |

#### Regionais (feature: regionals)

| Método | Rota |
|---|---|
| `GET/POST` | `/regionais` |
| `GET/PUT/DELETE` | `/regionais/{id}` |
| `GET` | `/regionais/select` |

#### Produtos (feature: product_settings)

| Método | Rota | Descrição |
|---|---|---|
| `GET/POST` | `/produtos` | CRUD |
| `GET/PUT/DELETE` | `/produtos/{id}` | CRUD |
| `GET` | `/produtos/select` | Para selects |
| `POST` | `/produtos/{produto}/restore` | Restaura excluído |

#### Proprietários

| Método | Rota |
|---|---|
| `GET/POST` | `/proprietarios` |
| `GET/PUT/DELETE` | `/proprietarios/{proprietario}` |
| `GET` | `/proprietarios/select` |

#### Terreno Produtos

| Método | Rota |
|---|---|
| `GET/POST` | `/terreno-produtos` |
| `GET/PUT/DELETE` | `/terreno-produtos/{id}` |
| `GET` | `/terreno-produtos/by-terreno/{terrenoId}` |

#### Admin do Tenant (RBAC + Organograma)

| Recurso | Rotas | Descrição |
|---|---|---|
| **Usuários** | `GET/POST /tenant-admin/users` | Lista/cria |
| | `GET /tenant-admin/users/{id}` | Detalhe |
| | `PUT /tenant-admin/users/{id}` | Atualiza |
| | `DELETE /tenant-admin/users/{id}` | Exclui |
| | `PUT /tenant-admin/users/{id}/module-permissions` | Permissões por módulo |
| **Roles** | `GET /tenant-admin/roles/select` | Para selects |
| | `GET/POST/PUT/DELETE /tenant-admin/roles` | CRUD |
| **Permissions** | `GET/POST/PUT/DELETE /tenant-admin/permissions` | CRUD |
| **Departments** | `GET /tenant-admin/departments/select` | Para selects |
| | `GET/POST/PUT/DELETE /tenant-admin/departments` | CRUD |
| **Positions** | `GET /tenant-admin/positions/select` | Para selects |
| | `GET/POST/PUT/DELETE /tenant-admin/positions` | CRUD |

#### Cidades (feature: territorial_base)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/cidades/estados` | Lista UFs |
| `GET` | `/cidades/buscar` | Busca cidades (`?q=&estado=`) |
| `GET` | `/cidades/dados` | Dados de uma cidade (`?code=IBGE`) |
| `GET` | `/cidades/{estado}` | Cidades de um estado |

#### IA (feature: ai)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/ai/conversations` | Lista conversas do usuário (50 mais recentes) |
| `GET` | `/ai/conversations/{id}/messages` | Mensagens de uma conversa |
| `GET` | `/ai/budget` | Status do orçamento de IA do tenant |
| `POST` | `/ai/sig-ai` | Chat com IA (resposta em SSE streaming) |
| `GET` | `/ai/scoring/{terreno_id}` | Score de recomendação do terreno |
| `GET` | `/ai/scoring/ranking` | Ranking de scores |
| `POST` | `/ai/scoring/recalculate` | Recalcula todos os scores |
| `POST` | `/ai/automation/tasks` | Cria tarefa automatizada |
| `PUT` | `/ai/automation/tasks/{taskId}` | Atualiza tarefa |
| `POST` | `/ai/automation/workflow/transition` | Transição automática de workflow |
| `GET` | `/ai/automation/monitor` | Monitor de automações |
| `GET` | `/ai/predictive/approval/{terreno_id}` | Predição de chance de aprovação |
| `GET` | `/ai/predictive/vgv/{terreno_id}` | Estimativa de VGV |
| `GET` | `/ai/predictive/stalling` | Previsão de terrenos parados |

**AI Chat (SSE Streaming):**
- Request: `POST /api/v1/ai/sig-ai` com body `{ message, conversation_id? }`
- Response: `text/event-stream` com eventos:
  - `data: {"type":"text_delta","delta":"..."}` — fragmento de texto
  - `data: {"type":"tool_call","tool":"...","input":{...}}` — chamada de ferramenta
  - `data: {"type":"error","message":"..."}` — erro
  - `data: [DONE]` — final do stream
- Headers: `X-Conversation-Id`, `X-AI-Provider`

#### Mobile

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/mobile/devices` | Registra dispositivo para push |
| `DELETE` | `/mobile/devices/{installationId}` | Remove dispositivo |
| `GET` | `/mobile/notifications` | Notificações do usuário |
| `POST` | `/mobile/notifications/{id}/read` | Marca como lida |

---

## 4. Fluxos de Autenticação

### 4.1 Fluxo Central (Login com Broker)

```
USUÁRIO                           FRONTEND                          BACKEND
  │                                  │                                 │
  │  1. Email + Senha                │                                 │
  │─────────────────────────────────>│                                 │
  │                                  │  2. POST /api/v1/auth/login     │
  │                                  │────────────────────────────────>│
  │                                  │                                 │
  │                                  │  3. Resposta:                   │
  │                                  │  ├── Se 1 tenant match:         │
  │                                  │  │   { token, user, abilities } │
  │                                  │  │   → Vai direto pro app       │
  │                                  │  │                              │
  │                                  │  └── Se múltiplos tenants:      │
  │                                  │      { broker_session_id,       │
  │                                  │        tenants: [...] }         │
  │                                  │      → Mostra seletor           │
  │                                  │                                 │
  │  4. Escolhe empresa              │                                 │
  │─────────────────────────────────>│                                 │
  │                                  │  5. POST /auth/select-tenant    │
  │                                  │     { broker_session_id,        │
  │                                  │       tenant_id, device_name }  │
  │                                  │────────────────────────────────>│
  │                                  │                                 │
  │                                  │  6. Resposta: { ticket }        │
  │                                  │                                 │
  │                                  │  7. POST /auth/exchange-ticket  │
  │                                  │     (no domínio do tenant)      │
  │                                  │────────────────────────────────>│
  │                                  │                                 │
  │                                  │  8. Resposta: { token, user }   │
  │                                  │                                 │
```

### 4.2 Fluxo Direto (Login no Tenant)

```
POST /api/v1/auth/login
Body: { email, password, device_name? }
Response: {
  user: { id, name, email, roles, permissions, department, position },
  token: "sanctum_token",
  abilities: ["tenant-api"],
  expires_at: "2026-...Z"
}
```

### 4.3 Fluxo Admin Central

```
POST /api/v1/admin/login
Body: { email, password }
Response: { token, user }
```

### 4.4 Gerenciamento de Token

| Ação | Rota |
|---|---|
| Refresh | `POST /api/v1/auth/refresh` → `{ token, expires_at }` |
| Logout | `POST /api/v1/auth/logout` (revoga token atual) |
| Logout All | `POST /api/v1/auth/logout-all` (revoga todos) |

### 4.5 Recuperação de Senha

```
1. POST /auth/password/forgot  → { email } → "Email enviado"
2. POST /auth/password/reset   → { email, token, password, password_confirmation }
```

### 4.6 Rate Limiters

| Limiter | Limite | Escopo |
|---|---|---|
| `api-public` | 60/min | IP |
| `api-auth` | 1000/min | User + Tenant |
| `central-login` | 5/min | IP + Email |
| `admin-login` | 5/min | IP + Email |
| `password-reset-request` | 5/min | IP + Email |
| `password-reset-submit` | 10/min | IP |
| `signup-status` | 30/min | IP + Session |
| `viabilidade-approval` | 10/min | User + Tenant |

### 4.7 Bootstrap do Tenant

`GET /api/v1/start` retorna:
- Módulos habilitados (baseado no plano)
- Permissões do usuário
- Dados do tenant

Usado para montar o menu lateral e controlar feature gating no frontend.

---

## 5. Padrões de Resposta e Formato

### 5.1 Resposta de Sucesso (200)

```json
{
  "success": true,
  "data": { ... },
  "message": "Operação realizada com sucesso"
}
```

### 5.2 Resposta de Criação (201)

```json
{
  "success": true,
  "data": { ... },
  "message": "Recurso criado com sucesso"
}
```

### 5.3 Resposta Sem Conteúdo (204)

Status `204` com corpo vazio.

### 5.4 Resposta Paginada (200)

```json
{
  "success": true,
  "data": [ ... ],
  "message": "Dados recuperados com sucesso",
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7,
    "from": 1,
    "to": 15
  }
}
```

### 5.5 Resposta de Erro (4xx/5xx)

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Dados inválidos.",
    "details": {
      "email": ["O campo email é obrigatório."]
    }
  }
}
```

### 5.6 Resposta de Erro de Validação (422)

```json
{
  "success": false,
  "message": "Dados inválidos.",
  "errors": {
    "campo": ["Erro de validação"]
  },
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Dados inválidos.",
    "details": {
      "campo": ["Erro de validação"]
    }
  }
}
```

### 5.7 Dashboard Overview

```json
{
  "success": true,
  "filters": {
    "ano": 2026,
    "mes": null,
    "meses": 12,
    "top_cidades_limit": 10,
    "area_opcao_limit": 10,
    "responsavel_id": null
  },
  "data": {
    "cards": { ... },
    "status_chart": [ ... ],
    "cadastros_mensais": [ ... ],
    "top_cidades": [ ... ],
    "vgv_anual": [ ... ],
    "unidades_fechadas_anual": [ ... ],
    "resumo": { ... },
    "cadastros_mensais_responsavel": [ ... ],
    "area_opcao_detalhe": [ ... ]
  }
}
```

### 5.8 Códigos de Erro Comuns

| Código | HTTP | Significado |
|---|---|---|
| `UNAUTHORIZED` | 401 | Não autenticado |
| `FORBIDDEN` | 403 | Sem permissão para o recurso |
| `NOT_FOUND` | 404 | Recurso não encontrado |
| `VALIDATION_ERROR` | 422 | Dados inválidos |
| `CONFLICT` | 409 | Conflito (registro duplicado, protegido) |
| `TOO_MANY_REQUESTS` | 429 | Rate limit excedido |
| `INTERNAL_ERROR` | 500 | Erro interno do servidor |
| `PROTECTED_ROLE` | 400 | Role protegida pelo sistema |
| `PROTECTED_PERMISSION` | 400 | Permissão protegida |
| `LAST_TENANT_ADMIN` | 400 | Último administrador do tenant |
| `CANNOT_DELETE_SELF` | 400 | Não pode excluir próprio usuário |
| `POLYGON_REQUIRED` | 422 | Terreno sem polígono definido |
| `BROKER_SESSION_INVALID` | 410 | Sessão do broker expirada |
| `INVALID_TRANSFER_TICKET` | 401 | Ticket de transferência inválido |
| `DEPARTMENT_IN_USE` | 422 | Departamento com usuários vinculados |
| `POSITION_IN_USE` | 422 | Cargo com usuários vinculados |

---

## 6. Feature Flags e Controle de Acesso

### 6.1 Sistema de Features

O backend usa um sistema de **feature flags** baseado no plano do tenant. Cada plano define quais features estão habilitadas e seus limites.

**Features disponíveis:**

| Feature | Descrição |
|---|---|
| `prospection` | Módulo de prospecção (terrenos, proprietários, corretores) |
| `viabilities.enabled` | Estudos de viabilidade |
| `viabilities.dre` | Geração de DRE detalhada |
| `legalizations` | Legalizações |
| `committee` | Comitê de aprovação |
| `negotiation` | Negociação e contratos |
| `projects_room` | Sala de projetos |
| `ai` | Assistente IA e automações |
| `regionals` | Regionais/escritórios |
| `product_settings` | Configuração de produtos |
| `dashboard.enabled` | Dashboard |
| `dashboard.vgv` | Gráfico VGV no dashboard |
| `dashboard.units_closed` | Unidades fechadas no dashboard |
| `exports.pdf` | Exportação PDF |
| `exports.excel` | Exportação Excel |
| `territorial_base` | Base territorial (cidades/estados) |

**Limites (enforced):**

| Limite | Descrição |
|---|---|
| `terrenos` | Número máximo de terrenos |
| `users` | Número máximo de usuários |
| `products` | Número máximo de produtos |
| `storage_gb` | Armazenamento máximo em GB |

### 6.2 Middlewares Aplicados

| Middleware | Função |
|---|---|
| `tenant.context` | Inicializa o tenant baseado no domínio |
| `central.context` | Contexto do app central |
| `check.feature:{feature}` | Bloqueia acesso se feature não está no plano |
| `enforce.limits:{limit}` | Bloqueia criação se limite foi atingido |
| `permission.gate:{module},{submodule?}` | Permissão granular via Spatie |
| `tenant.admin` | Apenas usuários com role ADMIN |
| `auth.tenant` | Verifica se token é válido no tenant |
| `auth.central` | Verifica se token é do admin central |
| `central.admin` | Verifica role de admin central |
| `ai.rate_limit` | Rate limit específico do AI |
| `ai.budget` | Verifica orçamento de IA disponível |
| `SetUserLocale` | Define idioma do usuário |
| `ForceJsonResponse` | Força header `Accept: application/json` |

### 6.3 RBAC (Spatie Permission)

**Roles do sistema (pré-definidas):**
- `ADMIN` — Acesso total
- `DIRECTOR` — Diretor
- `MANAGER` — Gerente
- `SUPERVISOR` — Supervisor
- `ANALYST` — Analista
- `USER` — Usuário básico

**Roles protegi das:** `ADMIN` não pode ser renomeada nem ter permissões alteradas manualmente.

**Permissões automáticas:** O seeder gera permissões no formato `{modulo}.{recurso}.{nivel}` (ex: `prospection.terrains.create`).

### 6.4 Módulos (Menu)

A enum `ModulesEnum` define a ordem, setor e sub-módulos:

| Módulo | Setor | Ordem |
|---|---|---|
| Dashboard | Principal | 10 |
| Prospecção | Operação | 20 |
| Corretores | Operação | 30 |
| Viabilidade | Operação | 40 |
| Comitê | Operação | 50 |
| Negociação | Operação | 60 |
| Legal | Operação | 70 |
| Projetos | Operação | 80 |
| Configurações | Configuração | 90 |
| Dados | Configuração | 100 |
| Relatórios | Inteligência | 110 |
| Admin | Administração | 120 |
| IA | Operação | 130 |

---

## 7. TODO para Frontend

### 7.1 Stack Recomendada

| Tecnologia | Versão | Justificativa |
|---|---|---|
| **Next.js** | 14+ (App Router) | SSR, rotas aninhadas, SEO para landing/blog |
| **TypeScript** | 5.x | Tipagem segura |
| **Tailwind CSS** | 3.x | Utilitário, consistente |
| **TanStack Query** | 5.x | Cache, loading/error, mutations, refetch |
| **Zustand** | 4.x | Estado global leve (auth, sidebar, tenant) |
| **React Hook Form** | 7.x | Formulários performáticos |
| **Zod** | 3.x | Validação compartilhável |
| **shadcn/ui** | latest | Componentes base acessíveis |
| **Recharts** | 2.x | Gráficos do dashboard |
| **date-fns** | 3.x | Manipulação de datas |
| **axios** | 1.x | HTTP client com interceptors |
| **react-dropzone** | latest | Upload de arquivos |
| **@dnd-kit** | latest | Drag & drop (etapas, reorder) |
| **zodios** | latest | API client tipado a partir das rotas |

### 7.2 Estrutura de Pastas Sugerida

```
src/
├── app/                          # Next.js App Router
│   ├── (central)/                # Rotas do app central
│   │   ├── login/
│   │   ├── cadastro/
│   │   ├── esqueci-senha/
│   │   ├── redefinir-senha/
│   │   ├── planos/
│   │   ├── blog/
│   │   └── (admin)/              # Admin central
│   │       └── admin/
│   │           ├── dashboard/
│   │           ├── tenants/
│   │           ├── plans/
│   │           ├── posts/
│   │           └── ...
│   ├── (tenant)/                 # Rotas do tenant (slug dinâmico)
│   │   └── app/
│   │       ├── dashboard/
│   │       ├── terrenos/
│   │       ├── viabilidades/
│   │       ├── legalizacoes/
│   │       ├── comite/
│   │       ├── negociacoes/
│   │       ├── contratos/
│   │       ├── projetos/
│   │       ├── documentos/
│   │       ├── corretores/
│   │       ├── regionais/
│   │       ├── produtos/
│   │       ├── proprietarios/
│   │       ├── tenant-admin/
│   │       │   ├── usuarios/
│   │       │   ├── cargos/
│   │       │   ├── permissoes/
│   │       │   ├── departamentos/
│   │       │   └── cargos-funcoes/
│   │       ├── ia/
│   │       └── billing/
│   └── layout.tsx
│
├── components/
│   ├── ui/                       # shadcn/ui components
│   ├── layout/
│   │   ├── sidebar.tsx
│   │   ├── header.tsx
│   │   ├── breadcrumb.tsx
│   │   └── app-shell.tsx
│   ├── auth/
│   │   ├── login-form.tsx
│   │   ├── tenant-selector.tsx
│   │   └── forgot-password-form.tsx
│   ├── terrenos/
│   │   ├── terreno-table.tsx
│   │   ├── terreno-form.tsx
│   │   ├── terreno-card.tsx
│   │   ├── workflow-badge.tsx
│   │   ├── workflow-transition-dialog.tsx
│   │   ├── kmz-upload.tsx
│   │   ├── map-view.tsx
│   │   ├── terreno-timeline.tsx
│   │   └── ...
│   ├── viabilidade/
│   │   ├── viabilidade-form.tsx
│   │   ├── dre-table.tsx
│   │   ├── dre-chart.tsx
│   │   ├── indicadores-cards.tsx
│   │   ├── compare-modal.tsx
│   │   └── ...
│   ├── legalizacao/
│   │   ├── gantt-chart.tsx
│   │   ├── etapa-card.tsx
│   │   ├── etapa-form.tsx
│   │   └── ...
│   ├── dashboard/
│   │   ├── stats-card.tsx
│   │   ├── status-chart.tsx
│   │   ├── monthly-chart.tsx
│   │   ├── top-cidades-table.tsx
│   │   └── ...
│   ├── documentos/
│   │   ├── file-upload.tsx
│   │   ├── file-list.tsx
│   │   └── file-preview.tsx
│   ├── ai/
│   │   └── chat-widget.tsx
│   ├── data-table.tsx            # Tabela genérica com filtros
│   ├── pagination.tsx
│   └── empty-state.tsx
│
├── hooks/
│   ├── use-auth.ts
│   ├── use-permissions.ts
│   ├── use-modules.ts
│   ├── use-terrenos.ts
│   ├── use-viabilidades.ts
│   ├── use-legalizacoes.ts
│   ├── use-dashboard.ts
│   └── ...
│
├── lib/
│   ├── api-client.ts             # axios instance com interceptors
│   ├── auth-store.ts             # Zustand store
│   ├── permissions.ts            # helpers de permissão
│   ├── utils.ts                  # cn(), formatadores
│   ├── constants.ts              # ENUMS, workflow stages, cores
│   └── validations/              # Schemas Zod compartilhados
│       ├── auth.ts
│       ├── terreno.ts
│       ├── viabilidade.ts
│       └── ...
│
├── types/
│   ├── api.ts                    # Tipos genéricos (ApiResponse<T>, PaginatedResponse<T>)
│   ├── auth.ts
│   ├── terreno.ts
│   ├── viabilidade.ts
│   ├── legalizacao.ts
│   ├── dashboard.ts
│   └── ...
│
└── middleware.ts                 # Next.js middleware para proteção de rotas
```

### 7.3 Autenticação e Autorização

#### Tasks

- [ ] **Auth Store (Zustand):**
  - [ ] `useAuthStore` com:
    - `token: string | null`
    - `user: User | null`
    - `tenant: Tenant | null`
    - `isAuthenticated: boolean`
    - `login(email, password, deviceName?) → Promise`
    - `loginBroker(email, password) → Promise` (pode retornar lista de tenants)
    - `selectTenant(brokerSessionId, tenantId) → Promise`
    - `exchangeTicket(ticket) → Promise`
    - `logout() → Promise`
    - `refreshToken() → Promise`
    - `loadMe() → Promise`

- [ ] **API Client (axios):**
  - [ ] `apiClient` com base URL dinâmica (detecta subdomínio)
  - [ ] Interceptor de request: adiciona `Authorization: Bearer {token}`
  - [ ] Interceptor de response:
    - 401 → tenta refresh automaticamente
    - Se refresh falha → limpa store + redirect para login
    - 403 → redireciona para página de acesso negado
    - 429 → mostra toast de rate limit
    - 422 → retorna erros de validação

- [ ] **Middleware de Rota (Next.js):**
  - [ ] `middleware.ts` verifica token nas rotas protegidas
  - [ ] Redireciona para `/login` se não autenticado
  - [ ] Redireciona para `/selecionar-tenant` se broker_session ativo
  - [ ] Verifica feature flags para rotas específicas

- [ ] **Telas de Login:**
  - [ ] `/login` — Login direto no tenant
  - [ ] `/admin/login` — Login admin central
  - [ ] `/selecionar-tenant` — Seletor de empresa (broker flow)
  - [ ] `/esqueci-senha` — Formulário de email
  - [ ] `/redefinir-senha` — Formulário com token + nova senha

- [ ] **Componentes de Proteção:**
  - [ ] `ProtectedRoute` — Verifica autenticação
  - [ ] `PermissionGate` — Renderiza condicional baseado em permissões
  - [ ] `FeatureGate` — Renderiza condicional baseado em features do plano
  - [ ] `AdminRoute` — Apenas admin central
  - [ ] `TenantAdminRoute` — Apenas admin do tenant

### 7.4 Layout e Navegação

#### Tasks

- [ ] **App Shell (Layout Principal):**
  - [ ] Sidebar colapsável (com estado persistido em localStorage)
  - [ ] Header fixo com:
    - [ ] Avatar + dropdown (perfil, billing, admin, logout)
    - [ ] Seletor de idioma (chama `PUT /api/v1/locale`)
    - [ ] Indicador de tenant (nome + plano)
    - [ ] Notificações mobile (badge)
  - [ ] Breadcrumb dinâmico
  - [ ] Content area com padding responsivo

- [ ] **Sidebar Dinâmica:**
  - [ ] Menu construído a partir de `GET /api/v1/start` ou `GET /api/v1/modules`
  - [ ] Agrupado por setores (Principal, Operação, Configuração, Inteligência, Administração)
  - [ ] Itens com ícones + labels
  - [ ] Badge de quantidade quando aplicável (ex: terrenos pendentes)
  - [ ] Destaque no item ativo
  - [ ] Sub-menu para módulos com sub-recursos (ex: Prospecção > Terrenos, Corretores)

- [ ] **Responsividade:**
  - [ ] Mobile: sidebar vira bottom navigation ou drawer
  - [ ] Tablet: sidebar colapsada com ícones
  - [ ] Desktop: sidebar expandida

- [ ] **Seletor de Tenant (App Central):**
  - [ ] Tela com cards de empresas disponíveis
  - [ ] Mostra nome, slug, status
  - [ ] Loading skeleton enquanto carrega

### 7.5 Páginas e Telas

#### 7.5.1 Páginas Públicas (App Central)

- [ ] **Landing Page** (`/`):
  - [ ] Hero section com CTA
  - [ ] Seção de funcionalidades
  - [ ] Seção de planos (cards)
  - [ ] FAQ
  - [ ] Footer com links

- [ ] **Planos** (`/planos`):
  - [ ] Cards comparativos de planos
  - [ ] Tabela comparativa de features
  - [ ] CTA "Começar agora" → `/cadastro?plan={slug}`

- [ ] **Signup** (`/cadastro`):
  - [ ] Multi-step wizard:
    - [ ] Step 1: Selecionar plano (cards)
    - [ ] Step 2: Dados da empresa (nome, slug, subdomínio com verificação disponibilidade)
    - [ ] Step 3: Dados do admin (nome, email, senha)
    - [ ] Step 4: Aceite de contrato (termos de uso)
    - [ ] Step 5: Stripe Checkout (redirect)
  - [ ] Polling de status após checkout (`GET /signup/{sessionId}/status`)

- [ ] **Blog** (`/blog`):
  - [ ] Lista de posts com paginação
  - [ ] Filtro por categoria
  - [ ] Post individual com conteúdo renderizado

#### 7.5.2 Admin Central

- [ ] **Dashboard Admin** (`/admin`):
  - [ ] Cards: total tenants, ativos, suspensos, receita
  - [ ] Gráfico de crescimento de tenants
  - [ ] Últimos signups
  - [ ] Alertas (pagamentos falhos, etc)

- [ ] **Tenants** (`/admin/tenants`):
  - [ ] Tabela com search, filtro por status, paginação
  - [ ] Ações: ativar, suspender, atribuir plano
  - [ ] Detalhe do tenant:
    - [ ] Informações gerais
    - [ ] Plano atual + histórico
    - [ ] Entitlements extras (adicionar/remover)
    - [ ] Domínios
    - [ ] Status da assinatura Stripe

- [ ] **Planos** (`/admin/plans`):
  - [ ] CRUD completo
  - [ ] Editor de features (checkboxes)
  - [ ] Editor de limites (campos numéricos)
  - [ ] Matriz de permissões por role

- [ ] **Entitlements** (`/admin/entitlements`):
  - [ ] CRUD de funcionalidades atômicas
  - [ ] Seleção de tipo (feature/limit/billing)

- [ ] **Usuários Admin** (`/admin/users`): CRUD
- [ ] **Posts** (`/admin/posts`): CRUD com editor de conteúdo
- [ ] **Cupons** (`/admin/coupons`): CRUD
- [ ] **Audit Logs** (`/admin/audit-logs`): Tabela com filtros por data, usuário, ação
- [ ] **ACL Catalog** (`/admin/acl`): Visualização da matriz de permissões

#### 7.5.3 Dashboard do Tenant (`/app/dashboard`)

- [ ] **Overview** com `GET /dashboard/overview?include=*`:
  - [ ] Cards de métricas (total terrenos, VGV total, área total, unidades)
  - [ ] Gráfico de status (barras ou pizza)
  - [ ] Gráfico de cadastros mensais (barras)
  - [ ] Top cidades (tabela)
  - [ ] VGV anual (barras)
  - [ ] Unidades fechadas anual
  - [ ] Cadastros por responsável
  - [ ] Filtro global por ano
  - [ ] Botão "Atualizar" (force_refresh)

#### 7.5.4 Terrenos (`/app/terrenos`)

- [ ] **Lista** (`/app/terrenos`):
  - [ ] DataTable com colunas: nome, status (badge colorido), cidade, responsável, área, VGV, data criação
  - [ ] Search por nome/endereço
  - [ ] Filtros: status, regional, cidade, responsável, data
  - [ ] Ações em lote: exportar PDF, exportar Excel
  - [ ] Paginação
  - [ ] Botão "Novo Terreno"

- [ ] **Detalhe** (`/app/terrenos/{id}`):
  - [ ] Tabs:
    - [ ] **Geral:** Dados cadastrais, endereço, mapa (polígono), áreas
    - [ ] **Workflow:** Status atual, transições disponíveis, checklist, timeline
    - [ ] **Produtos:** Tabela de produtos associados
    - [ ] **Proprietários:** Lista de proprietários
    - [ ] **Documentos:** Lista de documentos anexados
    - [ ] **Viabilidades:** Lista de estudos
    - [ ] **Legalização:** Status + Gantt
    - [ ] **Comitê:** Revisões
    - [ ] **Negociação:** Negociações ativas
    - [ ] **Contratos:** Contratos
    - [ ] **Projetos:** Projetos vinculados
    - [ ] **Atividades:** Timeline
    - [ ] **Notas:** Informações adicionais

- [ ] **Formulário de Criação/Edição:**
  - [ ] Dados básicos (nome, endereço, cep, bairro, cidade/estado via search)
  - [ ] Dados de área (importar KMZ, visualizar polígono no mapa)
  - [ )] Dados de zoneamento (zona, distrito, operação urbana)
  - [ ] Responsável, regional, corretor externo (selects)
  - [ ] Datas: apresentação, negociação, opção, descarte, contrato

- [ ] **Workflow:**
  - [ ] Timeline visual (progressão de status)
  - [ ] Botões de transição (quando disponíveis)
  - [ ] Modal de confirmação com reason_code + notes
  - [ ] Checklist de pré-requisitos

- [ ] **Mapa:**
  - [ ] Leaflet ou Google Maps
  - [ ] Exibição do polígono do terreno
  - [ ] Sobreposição de APP, declividade
  - [ ] Static map fallback

- [ ] **Timeline:**
  - [ ] Lista cronológica de atividades
  - [ ] Filter por tipo de atividade
  - [ ] Load more / infinite scroll

- [ ] **Exportação:**
  - [ ] Botão "Exportar PDF" (detalhe)
  - [ ] Botão "Checklist PDF"
  - [ ] Botão "Exportar Viabilidade PDF"

#### 7.5.5 Viabilidades (`/app/viabilidades`)

- [ ] **Lista** (`/app/viabilidades`):
  - [ ] DataTable: terreno, versão, status, VGV, margem, data
  - [ ] Filtro por terreno
  - [ ] Botão "Nova Viabilidade"

- [ ] **Formulário** (criação/edição):
  - [ ] Select de terreno
  - [ ] Seções colapsáveis:
    - [ ] **Parâmetros Gerais:** parceria VGV, compra terreno, infra não incidente, %
    - [ ] **Prazos:** obra, lançamento, incorporação
    - [ ] **Impostos:** PIS/COFINS, ISS, outros
    - [ ] **Despesas:** comissão, incorporação, área comum, contrapartidas
    - [ ] **Canteiro e MO:** canteiro mensal, mão de obra administrativa, seguros
    - [ ] **Comercial:** despesas comerciais, stand, mobília, comissão house/imobiliárias
    - [ ] **Bônus:** CCA, gerente, regional, crédito, comercial
    - [ ] **Marketing:** verba, lançamento
    - [ ] **Financeiro:** ITBI, registro, custos CEF, despesas financeiras
    - [ ] **Perfil Financiamento:** CEF, próprio, misto
    - [ ] **Produtos:** tabela dinâmica (nome, qtd, preço, metragem, permutas)
  - [ ] Botão "Calcular DRE" → chama create/update
  - [ ] Resultados em tempo real:
    - [ ] Cards de resumo (VGV, receita líquida, custos, lucro líquido)
    - [ ] Indicadores (TIR, margem líquida, ROI, payback)
    - [ ] Tabela DRE
    - [ ] Gráficos (fluxo mensal, DRE contábil)
  - [ ] Ações: salvar rascunho, ativar, solicitar aprovação

- [ ] **Detalhe** (`/app/viabilidades/{id}`):
  - [ ] Mesma estrutura de resultados do formulário (read-only)
  - [ ] Botões de ação: duplicar, recalcular, exportar PDF
  - [ ] Histórico de aprovações

- [ ] **Comparação** (`/app/viabilidades/compare`):
  - [ ] Select de duas viabilidades
  - [ ] Tabela comparativa lado a lado
  - [ ] Destaque nas diferenças

#### 7.5.6 Legalizações (`/app/legalizacoes`)

- [ ] **Lista** (`/app/legalizacoes`):
  - [ ] DataTable: terreno, nome, status, % concluído, responsável, datas
  - [ ] Filtro por status
  - [ ] Botão "Nova Legalização"

- [ ] **Detalhe** (`/app/legalizacoes/{id}`):
  - [ ] **Cabeçalho:** nome, status, progresso (barra), responsável
  - [ ] **Gantt Chart** (visualização de cronograma):
    - [ ] Barras horizontais por etapa
    - [ ] Dependências (setas entre etapas)
    - [ ] Cores por status
    - [ ] Drag & drop para reordenar
  - [ ] **Tabela de Etapas:**
    - [ ] Nome, ordem, status, datas planejadas/reais, % concluído, responsável
    - [ ] Ações inline: editar, excluir, mudar status
  - [ ] **Formulário de Etapa:** título, descrição, datas, responsável, cor, custos
  - [ ] **Pendências:** lista com severidade, status, data vencimento
  - [ ] Ações em lote: recalcular progresso, sync Gantt

- [ ] **Formulário de Criação:**
  - [ ] Select de terreno elegível (vindo de `/legalizacoes/eligible-terrenos`)
  - [ ] Nome, responsável, datas planejadas, observações

#### 7.5.7 Comitê (`/app/comite`)

- [ ] **Lista** (`/app/comite`):
  - [ ] DataTable: terreno, viabilidade, status, data, decisão final
  - [ ] Botão "Novo Comitê"

- [ ] **Detalhe** (`/app/comite/{id}`):
  - [ ] Dados do terreno + viabilidade
  - [ ] Pareceres por departamento:
    - [ ] Tabela: departamento, parecer (aprovado/reprovado), justificativa, data
    - [ ] Botão "Adicionar Parecer" (se usuário tem permissão)
  - [ ] Decisão final:
    - [ ] Botões: Aprovar, Reprovar (com campo de justificativa)
    - [ ] Mostra resultado da decisão

#### 7.5.8 Negociação (`/app/negociacoes`)

- [ ] **Lista** (`/app/negociacoes`):
  - [ ] DataTable: terreno, status, valor proposta, modelo de negócio, datas
  - [ ] Botão "Nova Negociação"

- [ ] **Detalhe** (`/app/negociacoes/{id}`):
  - [ ] Dados da negociação (status, valor, modelo)
  - [ ] Timeline de eventos (feed cronológico)
  - [ ] Formulário para adicionar evento (tipo, descrição, data)
  - [ ] Contratos vinculados

#### 7.5.9 Contratos (`/app/contratos`)

- [ ] **Lista** (`/app/contratos`):
  - [ ] DataTable: número, terreno, tipo, status, data assinatura, vigência

- [ ] **Detalhe/Formulário:**
  - [ ] Dados: tipo, número, vigência, status
  - [ ] Partes envolvidas (tabela dinâmica: nome, papel, documento)
  - [ ] Upload do arquivo do contrato
  - [ ] Botão "Registrar Assinatura" (com data)

#### 7.5.10 Projetos (`/app/projetos`)

- [ ] **Lista** (`/app/projetos`):
  - [ ] DataTable: nome, terreno, status, responsável, datas
  - [ ] Botão "Novo Projeto"

- [ ] **Detalhe/Formulário:**
  - [ ] Dados: nome, terreno (select de elegíveis), responsável
  - [ ] Status e ações: marcar pronto para registro, cancelar

#### 7.5.11 Documentos (`/app/documentos`)

- [ ] **Lista** (`/app/documentos`):
  - [ ] DataTable: nome, tipo, categoria, terreno, tamanho, data upload
  - [ ] Filtros: tipo, categoria, terreno
  - [ ] Upload via drag & drop ou botão
  - [ ] Ações: download, visualizar, excluir

- [ ] **Upload:**
  - [ ] Dropzone com preview
  - [ ] Campos: nome, tipo (select), categoria (select), terreno (select), descrição
  - [ ] Barra de progresso

#### 7.5.12 Admin do Tenant (`/app/tenant-admin/...`)

- [ ] **Usuários** (`/app/tenant-admin/usuarios`):
  - [ ] DataTable: nome, email, role, departamento, cargo, status
  - [ ] Formulário: nome, email, senha, role (select), departamento (select), cargo (select)
  - [ ] Modal de permissões por módulo (checkboxes)

- [ ] **Roles** (`/app/tenant-admin/cargos`):
  - [ ] DataTable: nome, permissões count, usuários count
  - [ ] Formulário: nome, permissões (seleção em árvore por módulo)

- [ ] **Permissions** (`/app/tenant-admin/permissoes`):
  - [ ] DataTable: nome, roles vinculadas
  - [ ] Formulário: nome (valida formato `modulo.recurso.acao`)

- [ ] **Departamentos** (`/app/tenant-admin/departamentos`):
  - [ ] DataTable: nome, ativo, usuários count
  - [ ] Formulário: nome, ativo

- [ ] **Cargos/Funções** (`/app/tenant-admin/cargos-funcoes`):
  - [ ] DataTable: nome, nível, ativo, usuários count
  - [ ] Formulário: nome, nível, ativo

- [ ] **Regionais** (`/app/regionais`):
  - [ ] DataTable: nome
  - [ ] Formulário: nome

- [ ] **Produtos** (`/app/produtos`):
  - [ ] DataTable: nome, unidade medida, status
  - [ ] Formulário: nome, unidade medida, preço médio
  - [ ] Ação: restaurar (soft delete)

- [ ] **Proprietários** (`/app/proprietarios`):
  - [ ] DataTable: nome, tipo (PF/PJ), documento, terreno
  - [ ] Formulário completo com dados do proprietário

- [ ] **Corretores Externos** (`/app/corretores`):
  - [ ] DataTable: nome, creci, contato
  - [ ] Formulário: nome, creci, telefone, email

#### 7.5.13 IA (`/app/ia`)

- [ ] **Chat Widget:**
  - [ ] Lista de conversas (sidebar)
  - [ ] Chat com streaming (SSE):
    - [ ] Renderização de mensagens em tempo real
    - [ ] Indicador de "digitando..." durante o stream
    - [ ] Tool calls visíveis (opcional)
  - [ ] Input de texto com envio
  - [ ] Nova conversa / continuar existente

- [ ] **Scoring (dentro do detallhe do terreno):**
  - [ ] Card com score do terreno
  - [ ] Ranking visual

- [ ] **Predictive:**
  - [ ] Card de "Chance de aprovação"
  - [ ] Card de "Estimativa VGV"

#### 7.5.14 Billing (`/app/billing`)

- [ ] **Assinatura:**
  - [ ] Card com plano atual, preço, status
  - [ ] Botão "Gerenciar" → portal Stripe
  - [ ] Botão "Trocar Plano" → seleção de plano + confirmação

- [ ] **Histórico de Faturas:**
  - [ ] DataTable: data, valor, status, PDF

- [ ] **Método de Pagamento:**
  - [ ] Cartão atual (mascarado)
  - [ ] Botão "Alterar" → SetupIntent

- [ ] **Cupom:**
  - [ ] Input para resgatar cupom

### 7.6 Componentes Reutilizáveis

- [ ] **DataTable** — Tabela genérica com:
  - [ ] Colunas configuráveis
  - [ ] Ordenação por coluna
  - [ ] Search
  - [ ] Paginação
  - [ ] Seleção de linhas
  - [ ] Ações em lote
  - [ ] Estados: loading, empty, error

- [ ] **StatusBadge** — Badge com cor baseada no enum:
  - [ ] `WorkflowStatus` → cores do enum
  - [ ] `LegalizacaoStatus`
  - [ ] `ProjetoStatus`
  - [ ] `TenantStatus`

- [ ] **ConfirmDialog** — Modal de confirmação com texto customizável

- [ ] **FormDrawer** — Drawer lateral para formulários

- [ ] **PageHeader** — Título + breadcrumb + ações

- [ ] **EmptyState** — Ilustração + texto + CTA

- [ ] **LoadingSkeleton** — Skeleton loader para páginas

- [ ] **ErrorBoundary** — Captura de erros com fallback

- [ ] **SearchInput** — Input com debounce

- [ ] **FilterBar** — Conjunto de filtros (selects, inputs, date range)

- [ ] **SelectAsync** — Select com busca no backend (`/for-select` endpoints)

- [ ] **FileUpload** — Dropzone com preview e progresso

- [ ] **MapView** — Leaflet/Google Maps wrapper

- [ ] **GanttChart** — Cronograma de legalização

- [ ] **StatCard** — Card de métrica com ícone, valor, label, tendência

- [ ] **TabNavigation** — Navegação por abas

- [ ] **StepWizard** — Multi-step form wizard

- [ ] **PermissionGuard** — Renderiza condicional baseado em permissão

- [ ] **FeatureGuard** — Renderiza condicional baseado em feature

### 7.7 Gerenciamento de Estado

- [ ] **Zustand Stores:**

| Store | Estado | Persistência |
|---|---|---|
| `useAuthStore` | token, user, tenant, isAuthenticated | localStorage (token) |
| `useSidebarStore` | isCollapsed, isMobileOpen | localStorage |
| `useTenantStore` | tenant data, subscription, modules | session |
| `useUiStore` | theme, locale, toasts | localStorage |

- [ ] **TanStack Query (React Query):**

| Query Key | Endpoint | Stale Time | Cache Time |
|---|---|---|---|
| `['auth', 'me']` | `GET /auth/me` | Infinity | 30min |
| `['modules']` | `GET /start` | 30min | 1h |
| `['terrenos', filters]` | `GET /terrenos` | 2min | 10min |
| `['terreno', id]` | `GET /terrenos/{id}` | 5min | 30min |
| `['terreno', id, 'workflow']` | `GET /terrenos/{id}/workflow` | 1min | 5min |
| `['terreno', id, 'timeline']` | `GET /terrenos/{id}/timeline` | 30s | 2min |
| `['viabilidades', filters]` | `GET /viabilidades` | 2min | 10min |
| `['viabilidade', id]` | `GET /viabilidades/{id}` | 5min | 30min |
| `['legalizacoes', filters]` | `GET /legalizacoes` | 2min | 10min |
| `['legalizacao', id]` | `GET /legalizacoes/{id}` | 5min | 30min |
| `['dashboard', type, filters]` | `GET /dashboard/*` | 5min | 30min |
| `['documentos', filters]` | `GET /documentos` | 2min | 10min |
| `['users', filters]` | `GET /tenant-admin/users` | 5min | 30min |
| `['roles']` | `GET /tenant-admin/roles` | 10min | 1h |
| `['departments']` | `GET /tenant-admin/departments` | 10min | 1h |
| `['positions']` | `GET /tenant-admin/positions` | 10min | 1h |
| `['cidades', uf]` | `GET /cidades/{uf}` | 1h | 24h |
| `['plans']` | `GET /plans` | 1h | 24h |
| `['ai', 'conversations']` | `GET /ai/conversations` | 1min | 5min |

- [ ] **Mutation Hooks:**

| Mutação | Invalida |
|---|---|
| `POST /terrenos` | `['terrenos']` |
| `PUT /terrenos/{id}` | `['terrenos']`, `['terreno', id]` |
| `DELETE /terrenos/{id}` | `['terrenos']` |
| `POST /terrenos/{id}/workflow` | `['terrenos']`, `['terreno', id]`, `['terreno', id, 'workflow']` |
| `POST /viabilidades` | `['viabilidades']` |
| `...` | ... |

### 7.8 Integração com a API

- [ ] **API Client (axios):**

```typescript
// lib/api-client.ts
const apiClient = axios.create({
  baseURL: getBaseURL(), // detecta subdomínio
  headers: { 'Accept': 'application/json' },
});

apiClient.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token;
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Tenta refresh
      const refreshed = await useAuthStore.getState().refreshToken();
      if (!refreshed) {
        useAuthStore.getState().logout();
        window.location.href = '/login';
      }
      // Re-tenta request original
      error.config.headers.Authorization = `Bearer ${useAuthStore.getState().token}`;
      return apiClient(error.config);
    }
    return Promise.reject(error);
  }
);
```

- [ ] **Hooks Customizados:**

```typescript
// hooks/use-terrenos.ts
export function useTerrenos(filters: TerrenoFilters) {
  return useQuery({
    queryKey: ['terrenos', filters],
    queryFn: () => apiClient.get('/terrenos', { params: filters }),
    select: (res) => res.data,
  });
}

export function useCreateTerreno() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: CreateTerrenoData) => apiClient.post('/terrenos', data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['terrenos'] }),
  });
}
```

- [ ] **Tratamento de Erros:**

```typescript
// lib/error-handler.ts
export function handleApiError(error: unknown): { message: string; errors?: Record<string, string[]> } {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data;
    if (data?.error) {
      return {
        message: data.error.message || 'Erro desconhecido',
        errors: data.error.details,
      };
    }
    if (data?.errors) {
      return { message: data.message || 'Dados inválidos', errors: data.errors };
    }
    return { message: 'Erro de conexão com o servidor' };
  }
  return { message: 'Erro inesperado' };
}
```

### 7.9 Requisitos Técnicos Específicos

- [ ] **SSE Streaming (AI Chat):**
  ```typescript
  // hooks/use-ai-chat.ts
  export function useAiChatStream() {
    const conversationIdRef = useRef<string | null>(null);
    
    const sendMessage = useCallback(async (message: string) => {
      const response = await fetch(`/api/v1/ai/sig-ai`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({
          message,
          conversation_id: conversationIdRef.current,
        }),
      });
      
      conversationIdRef.current = response.headers.get('X-Conversation-Id');
      const reader = response.body!.getReader();
      const decoder = new TextDecoder();
      
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        
        const chunk = decoder.decode(value);
        const lines = chunk.split('\n').filter(l => l.startsWith('data: '));
        
        for (const line of lines) {
          const data = JSON.parse(line.slice(6));
          if (data.type === 'text_delta') {
            onTextDelta(data.delta);
          } else if (data.type === 'error') {
            onError(data.message);
          } else if (line.includes('[DONE]')) {
            onComplete();
          }
        }
      }
    }, []);
    
    return { sendMessage };
  }
  ```

- [ ] **Upload de Arquivos (multipart):**
  ```typescript
  const uploadMutation = useMutation({
    mutationFn: (formData: FormData) => apiClient.post('/documentos', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (e) => setProgress(Math.round((e.loaded * 100) / e.total)),
    }),
  });
  ```

- [ ] **Importação KMZ:**
  ```typescript
  const importKmzMutation = useMutation({
    mutationFn: ({ id, file }: { id: number; file: File }) => {
      const fd = new FormData();
      fd.append('arquivo', file);
      return apiClient.post(`/terrenos/${id}/import-kmz`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    },
  });
  ```

- [ ] **Detecção de Subdomínio:**
  ```typescript
  function getBaseURL(): string {
    const host = window.location.host;
    const parts = host.split('.');
    
    // Se tem mais de 2 partes, é subdomínio de tenant
    if (parts.length > 2 && !CENTRAL_DOMAINS.includes(host)) {
      const slug = parts[0];
      return `https://${host}/api/v1`; // ou http://localhost/api/v1 em dev
    }
    
    return `${process.env.NEXT_PUBLIC_CENTRAL_URL}/api/v1`;
  }
  ```

- [ ] **FormSelect Async (para `/for-select` endpoints):**
  ```typescript
  function useAsyncSelect(endpoint: string) {
    return useQuery({
      queryKey: [endpoint],
      queryFn: () => apiClient.get(endpoint).then(r => r.data.data),
      staleTime: 10 * 60 * 1000,
    });
  }
  ```

- [ ] **Cache Invalidation após Mutação:**
  ```typescript
  // Padrão para todas as mutações
  const queryClient = useQueryClient();
  
  const mutation = useMutation({
    mutationFn: (data) => apiClient.post('/resource', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['resource-list'] });
      queryClient.invalidateQueries({ queryKey: ['resource-detail'] });
      toast.success('Operação realizada com sucesso');
    },
    onError: (error) => {
      const { message, errors } = handleApiError(error);
      if (errors) {
        // Setar erros no formulário
        Object.entries(errors).forEach(([field, msgs]) => {
          setError(field, { message: msgs[0] });
        });
      }
      toast.error(message);
    },
  });
  ```

### 7.10 Boas Práticas Recomendadas

1. **Tipos Compartilhados:** Extrair tipos das responses da API em `types/api.ts`. Usar `zod` para schemas que espelham a validação do backend.

2. **Error Handling Padronizado:** Criar um `ApiError` class e um hook `useApiError` para tratamento consistente.

3. **Loading States:** Sempre mostrar skeleton ou spinner durante carregamento. Usar `isLoading` do TanStack Query.

4. **Otimismo:** Mutations otimistas para ações rápidas (ex: mudar status, adicionar nota).

5. **Debounce em Search:** Usar `useDebounce` para inputs de busca (300ms).

6. **Refresh de Dashboard:** Botão "Atualizar" que chama `?force_refresh=true`.

7. **Persistência de Filtros:** Salvar filtros em `URLSearchParams` para compartilhar URLs.

8. **Responsividade:** Mobile-first, sidebar vira bottom nav em mobile.

9. **Acessibilidade:** ARIA labels, keyboard navigation, focus management.

10. **Performance:**
    - Virtual scrolling em listas grandes (react-window)
    - Code splitting por rota (Next.js automático)
    - Imagens otimizadas (next/image)
    - Lazy loading de componentes pesados (mapa, Gantt)

11. **Tratamento de 429 (Rate Limit):** Mostrar toast e disabled botões por 60s.

12. **Feature Gating no Frontend:**
    ```typescript
    function useFeature(feature: string): boolean {
      const modules = useModulesStore(s => s.modules);
      return modules.includes(feature);
    }
    ```

13. **Conversão de Enums:** Manter os mesmos valores dos enums PHP no frontend para consistência.

14. **Date/Currency Formatting:** Usar `date-fns` para datas e formatadores consistentes para moeda (BRL).

15. **Versionamento de Cache:** Quando a estrutura da resposta mudar, incrementar query key version.

---

> **Nota:** Este documento deve ser usado como guia de referência para a IA de frontend. Comece implementando na ordem: **Auth → Layout/Shell → Dashboard → Terrenos (CRUD base) → Demais módulos**. O endpoint `GET /start` é seu melhor amigo para descobrir o que está disponível para cada tenant.
