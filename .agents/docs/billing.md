# Billing (Cashier / Stripe)

> **Quando ler:** planos, entitlements, add-ons, checkout, webhooks, cupons, dunning, storage quota ligada a plano, matriz de features.
> **Hub:** [`AGENTS.md`](../../AGENTS.md)
> **Regra crítica:** nunca processe webhook Stripe fora do `WebhookController`/`WebhookEventService`; nunca confie em dados do cliente para preço/plano.

## Regras de billing

- Entidades centrais: `Plan`, `Entitlement`, `TenantEntitlement`, `BillingAddon`, `TenantAddonSubscription`, `TenantAddonPurchase`, `AiCreditTransaction`, `Coupon`, `WebhookEvent`, `Dispute` (em `Models/Central/`). Add-ons aceitam Price Stripe recorrente mensal ou `one_time`; compras avulsas nunca entram como item da assinatura.
- Serviços em `app/Services/Billing/`: `StripeCheckoutService`, `TenantBillingService`, `StripeTenantReconciliationService`, `BillingHistoryService`, `CouponService`, `BillingAddonService`, `TenantAddonService`, `TenantAddonPurchaseService`, `AiCreditService`, `TenantAddonAdminService`, `AddonReconciliationService`, `WebhookEventService` (idempotência de webhooks via `WebhookEvent`). Cada evento persiste `pending → processing → processed|failed`, tentativas, início e último erro; o contador de tentativas funciona como fencing token para impedir que um worker antigo finalize após um takeover.
- Fluxos do tenant: assinatura/portal (`TenantController@subscription`, `billingPortal`), troca de plano (`PlanSwapController`), dunning/retry de pagamento (`DunningController`), cupons (`CouponController`), histórico (`BillingHistoryController`).
- Troca de plano: **upgrade** cobra imediatamente via Stripe (`pendingIfPaymentFails()->swapAndInvoice()`) e só concede o plano local se a chamada confirmar; **downgrade** mantém `plan_id` atual e grava `scheduled_plan_id` até a renovação (`invoice.paid`). O snapshot de assinatura expõe `scheduled_plan`.
- Add-ons Stripe: o catálogo fica em `billing_addons`; concessões recorrentes atuais ficam em `tenant_addon_subscriptions` e compras avulsas repetíveis ficam em `tenant_addon_purchases`. O cliente envia somente `addon_slug` e quantidade (1–100). Preço mensal usa `updateQuantity` e permite cancelamento; preço avulso cria Checkout Session `mode=payment` e só concede o snapshot de grants após `checkout.session.completed` pago ou `checkout.session.async_payment_succeeded`. Cancelamento recorrente remove o item **imediatamente** com proration (`create_prorations` / crédito proporcional), opera sobre o `stripe_price_id` do **item contratado** (não o do catálogo), é idempotente se já `canceled`, e falha se o acesso permanecer ativo após a operação. O campo `cancel_at_period_end` do add-on **não** herda o da assinatura do plano (sempre `false` até existir cancelamento agendado por item).
- A definição JSON do catálogo usa `definition.grants`, com `limit_pack` aceitando apenas limites, `feature_unlock` apenas features e `bundle` podendo combinar ambos. Os grants são validados contra `Entitlement` pelo `BillingAddonDefinitionService`; add-ons não criam ACL automaticamente. O `PlanMatrixService` aplica a ordem **plano-base → add-ons recorrentes ativos + compras avulsas pagas → `tenant_entitlements` manual (override final)**; limites ilimitados (`-1`) não são somados. A exceção é `ai_budget` avulso: ele vive no ledger consumível, não na matriz permanente.
- A reconciliação recorrente é idempotente por `stripe_subscription_item_id`, executada no `WebhookController` para eventos de assinatura e disponível para operação admin. Preços Stripe desconhecidos não concedem acesso; itens removidos são marcados como `canceled` localmente. Estados `active` e `past_due` concedem acesso; add-ons não concedem acesso durante o trial do plano.
- Falha transitória de reconciliação não pode ser convertida em HTTP 2xx: o evento fica `failed`, sem `processed_at`, para retry do Stripe. A rede de segurança horária `billing:reconcile-tenants` despacha `ReconcileTenantBillingJob` em chunks; execução pontual aceita `--tenant=<id|slug>`.
- Add-on contratado não pode ter `slug`, `type`, `definition` ou `stripe_price_id` alterado. Para rotação de preço/concessão, crie novo slug/SKU; `is_active=false` impede novas compras sem remover o acesso já reconciliado. Não há criação automática de Price Stripe para add-ons: os IDs vêm de `STRIPE_PRICE_ADDON_*` por ambiente ou do CRUD admin.
- As APIs de catálogo admin são `/api/v1/admin/billing-addons`; operações admin por tenant são `GET|POST /api/v1/admin/tenants/{tenant}/addons` (`/reconcile`) e `GET /api/v1/admin/tenants/{tenant}/access-matrix`. No contexto tenant, o admin do cliente usa `GET /api/v1/tenant/addons`, `GET /mine`, `POST /purchase`, `PATCH /{addon}` e `POST /{addon}/cancel`.
- O catálogo tenant retorna o preço estruturado (`price.unit_amount` em centavos, `price.currency`, `price.interval`, `price.type`), `price_type`, `formatted_price`, `is_purchasable`, quantidade avulsa já paga e o resumo de créditos de IA quando aplicável. Esses dados são consultados/cacheados a partir do Price real do Stripe. O frontend deve bloquear compra/alteração quando `is_purchasable=false` ou `formatted_price=null`, mas o backend também valida Price ativo e aceita apenas recorrência mensal ou preço avulso.
- Créditos avulsos de `ai_budget` exigem que o plano efetivo já possua orçamento mensal de IA. A franquia do plano zera por mês; créditos comprados não expiram e acumulam. `AiTelemetryService` consome primeiro a franquia mensal e reconcilia em `ai_credit_transactions` somente o excedente, inclusive reservas; liquidação menor ou expiração devolve automaticamente a diferença ao saldo.
- Ao trocar plano com múltiplos itens, `TenantBillingService` deve enviar ao Cashier o novo preço-base junto dos add-ons ativos; um `swap()` apenas com o preço do plano remove os add-ons.
- O portal de billing deve usar `STRIPE_PORTAL_CONFIGURATION_ID` quando configurado para impedir troca de plano fora do `PlanSwapController`.
- Os Prices recorrentes dos planos são configurados por ambiente em `config/cashier.php` via `STRIPE_PRICE_BROKER`, `STRIPE_PRICE_BASICO`, `STRIPE_PRICE_MASTER` e `STRIPE_PRICE_PRO`; o `PlanSeeder` não deve embutir IDs de uma conta Stripe específica. Sem um ID configurado, o checkout cria um Price emergencial com valor em centavos.
- Os Prices dos add-ons são configurados por ambiente em `config/cashier.php` via `STRIPE_PRICE_ADDON_STORAGE_10GB`, `STRIPE_PRICE_ADDON_AI_BUDGET_5`, `STRIPE_PRICE_ADDON_REPORTS_BUILDER` e `STRIPE_PRICE_ADDON_GROWTH_BUNDLE`; `ai-budget-5` é avulso e os demais podem continuar mensais. O `BillingAddonSeeder` apenas sincroniza esses IDs no catálogo.
- Enforcement de plano: middlewares `subscription.active`, `enforce.limits`, `check.feature` + `EntitlementService`/`PlanMatrixService`.
- Alterações de catálogo/matriz são transacionais e invalidam os caches dos planos afetados somente após o commit. Valores administrativos são estritos: feature é boolean, limites são inteiros `>= 0` ou `-1`, e `ai_budget` aceita número não negativo. Todo upload ou arquivo gerado deve registrar seus metadados por `StorageQuotaService::commitFile()`, que mantém check de quota + persistência sob o mesmo lock e remove o objeto em caso de falha. O middleware `enforce.limits:storage_gb` é apenas rejeição antecipada. Use `plans:audit-entitlements` para auditoria read-only de catálogo, matrizes, aliases, dependências, arquivos ausentes e órfãos de storage.
- O catálogo de features fica em `EntitlementSeeder::planMatrix()` +
  `roadmapFeatureMatrix()` e segue o recorte A pelo fluxo do terreno:
  Broker (captação), Básico (análise usável), Master (decisão e fechamento),
  Pro (operação completa). Básico libera `viabilities.summary`, `dre`, `kpis`
  e `premises`; cenários (`viabilities.scenarios`) começam no Master.
  Master tem `negotiation` e para no contrato; a IA do Master é só o chat
  (`ai`). `legalizations`, `legalization.control_center`,
  `negotiation.deal_room`, `projects.enabled`/`projects.planning`,
  `documents.intelligence`, `ai.advanced` e `ai.contextual` são Pro.
  `onboarding.profile` e `experience.accessibility` ficam em todos os planos.
  Storage: Broker 1 GB, Básico 5 GB, Master 10 GB, Pro 20 GB. Básico não tem
  `ai` nem `ai_budget`. Todo entitlement possui
  `scope` (`api`, `ui`, `composite` ou `internal`); features `api` precisam
  de gate `check.feature` ou projeção registrada e limites usam sempre
  `internal`. `default_value` é somente template administrativo: toda
  associação persiste valor explícito e a autorização usa plano + override.
  Projetos usam `projects.enabled` para CRUD e `projects.planning` para
  milestones/dependências/riscos. `projects_room` e `projects.room` são aliases
  temporários resolvidos/serializados para compatibilidade, não itens comerciais.
- Nunca processe webhook Stripe fora do `WebhookController`/`WebhookEventService`; nunca confie em dados do cliente para preço/plano.

## Dunning e bloqueio de assinatura (política canônica)

Fonte da verdade de pagamento = Stripe (webhooks + Cashier). Status local do tenant é derivado por `TenantBillingService::applyStripeSubscriptionStatus` e handlers de `invoice.*` no `WebhookController`.

### Status Stripe → tenant

| Stripe | Efeito local |
|---|---|
| `active`, `trialing` | `activate()` → `active` |
| `past_due` | **só notifica** (`PaymentRetryNotification`); **não suspende** (grace do retry Stripe) |
| `unpaid`, `incomplete_expired` | `suspend()` → `suspended` |
| `canceled` | `cancel()` → `cancelled` |
| outros | noop |

Além disso, `invoice.payment_failed` com `attempt_count >= 3` chama `suspend()`; tentativas 1 e 2 apenas notificam. `invoice.payment_action_required` (SCA/3DS) notifica e **não** suspende. `charge.dispute.created` coloca o tenant em `under_review` (também bloqueado nos módulos).

### Enforcement de rotas (`CheckSubscriptionStatus` / `subscription.active`)

- **Bloqueia** módulos de negócio (`workspace-admin`, prospection, viability-ai, projects-committee, negotiation, platform-legal) com `403 SUBSCRIPTION_INACTIVE` quando o tenant não está `active` (e `TRIAL_ENDED` se trial encerrou sem assinatura).
- Mensagens do middleware usam chaves i18n: `SUBSCRIPTION_PENDING`, `SUBSCRIPTION_SUSPENDED`, `SUBSCRIPTION_CANCELLED`, `SUBSCRIPTION_SETUP_FAILED`, `SUBSCRIPTION_UNDER_REVIEW`, `TRIAL_ENDED` (`pt-br` + `en-us`). Payload inclui `status`, `support_url`, `billing_portal_available`.
- **Fora do middleware** (regularização, em `routes/tenant/account-billing.php`): auth, locale, prefs, bootstrap (`/start`, `/modules`), billing portal, payment-status, retry-payment, setup-intent, payment-method, cupom, plan swap, **histórico de faturas/PDF**, catálogo/mine de add-ons.
- **Add-ons:** `purchase` e `updateQuantity` são bloqueados no `TenantAddonService` se `!isActive()` (tenant inadimplente não contrata/altera). **Cancel** de add-on permanece permitido (reduzir custo). Plan swap e atualização de cartão ficam liberados para recovery.

### Login com conta suspensa / em revisão

- `TenantStatus::allowsLogin()` / `Tenant::allowsLogin()`: elegíveis `active`, `suspended`, `under_review`.
- Broker central (`CentralLoginBrokerService`) e directory (`TenantUserDirectoryService`) **incluem** esses status e **excluem** `pending`, `cancelled`, `setup_failed`.
- Resposta do broker expõe `tenant.status` (e `tenants[].status` no seletor) para o FE redirecionar à tela de billing.
- Login direto no subdomínio não filtra status do tenant; o bloqueio de negócio continua no middleware de assinatura.

### Reativação automática

1. Cliente paga fatura aberta (hosted invoice / portal / cartão).
2. Stripe envia `invoice.paid` e/ou `customer.subscription.updated` com `active`/`trialing`.
3. `reconcileTenantBillingState` → `applyStripeSubscriptionStatus` → `activate()`.

`validateTenantForWebhook` **não** ignora `suspended` (só `cancelled` e `setup_failed`).

### Arquivos e testes de regressão

- Middleware: `app/Http/Middleware/CheckSubscriptionStatus.php`
- Status Stripe: `TenantBillingService::applyStripeSubscriptionStatus`
- Webhooks: `WebhookController` (`handleInvoicePaymentFailed`, `handleInvoicePaid`, reconciliação)
- Dunning: `DunningController`; rotas em `routes/tenant/account-billing.php`
- Auth: `CentralLoginBrokerService`, `TenantUserDirectoryService`
- Testes: `tests/Feature/Billing/WebhookHandlerTest.php`, `PaymentRetryTest.php`, `SubscriptionEnforcementTest.php`; unit de broker/directory/`TenantStatus`/`TenantAddonService`

Mínimo a não regredir: 3ª falha → `suspended`; `past_due` sem suspender; negócio 403 com suspenso; dunning/history liberados; `invoice.paid` reativa; broker lista suspended; purchase de add-on com inativo falha.
