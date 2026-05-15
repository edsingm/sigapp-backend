# Plano de Correção: Sistema de Cadastro de Tenant e Pagamento

**Data:** 2026-05-15
**Status:** Concluído (Fase 1, 2 e 3 implementadas)
**Escopo:** Correções críticas, médias e melhorias no fluxo de signup, billing e provisionamento de tenants

---

## Contexto

O review completo do sistema de cadastro de tenant e pagamento identificou **3 problemas críticos, 5 médios e 4 melhorias**. Os problemas críticos podem causar bugs em produção: race condition no provisionamento, falta de transação no signup e escalabilidade do TenantStatusService.

---

## Fase 1: Correções Críticas

### 1.1 — Race condition no provisionamento do tenant

**Problema:** `CreateFullTenantJob` pode ser disparado duas vezes — uma vez por `handleCheckoutSessionCompleted` e outra por `reconcileTenantBillingState`.

**Solução:** Adicionar verificação de `database_created` com refresh do banco ANTES de dispatchar, e usar `dispatch` condicional com lock atômico.

**Arquivos a modificar:**
- `app/Http/Controllers/Api/V1/WebhookController.php`
  - Método `dispatchTenantProvisioning()`: adicionar `$tenant->refresh()` antes da checagem de `database_created`
  - Método `handleCheckoutSessionCompleted()`: remover o dispatch direto (linha 192) e deixar apenas `reconcileTenantBillingState` disparar o provisionamento
  - Método `reconcileTenantBillingState()`: garantir que o check de `!$tenant->database_created` é feito após refresh

**Verificação:** Testar cenário onde checkout.session.completed e invoice.paid chegam em sequência rápida — apenas 1 job deve ser disparado.

---

### 1.2 — Transação e rollback no fluxo de signup

**Problema:** Se a criação do Stripe Customer ou Checkout Session falhar, o tenant fica órfão com `status=pending` e sem possibilidade de retry.

**Solução:** Envolver o fluxo completo em um try/catch que deleta o tenant em caso de falha no Stripe. Adicionar tentativa de retry automático para falhas de rede.

**Arquivos a modificar:**
- `app/Http/Controllers/Api/V1/SignupController.php`
  - Método `store()`: no catch genérico, verificar se o tenant foi criado e deletar se o erro veio do Stripe
  - Adicionar lógica de cleanup: se `$tenant` existe e o erro é de Stripe, deletar tenant + domínios

- `app/Services/Billing/StripeCheckoutService.php`
  - Método `createCustomer()`: envolver em try/catch e relançar como exceção de domínio

**Verificação:** Simular falha na API do Stripe (mock) e verificar que o tenant é removido do banco.

---

### 1.3 — TenantStatusService não escala

**Problema:** Carrega todos os tenants e abre conexão com o banco de cada um sequencialmente.

**Solução:** Usar query aggregation com cache mais agressivo, ou mover para um Job assíncrono que atualiza um campo `stats` no tenant central.

**Arquivos a modificar:**
- `app/Services/TenantStatusService.php`
  - Reescrever para usar cache com TTL maior (1h) e invalidação manual
  - Adicionar opção de usar um Job assíncrono para atualização periódica
  - Considerar usar `tenancy()->run()` em batch ao invés de individual

**Arquivo novo (opcional):**
- `app/Jobs/RefreshTenantStatsJob.php` — Job que roda periodicamente e atualiza as estatísticas em cache

**Verificação:** Testar com 50+ tenants e medir tempo de resposta.

---

## Fase 2: Correções Médias

### 2.1 — Agrupamento de updates no reconcile

**Problema:** Dois updates ao banco para dados que poderiam ser agrupados.

**Arquivo a modificar:**
- `app/Http/Controllers/Api/V1/WebhookController.php`
  - Método `reconcileTenantBillingState()`: agrupar os dois `$tenant->update()` em um único call

**Verificação:** Verificar logs de query SQL — deve haver apenas 1 UPDATE para tenant.

---

### 2.2 — Unificar constantes de status com Enum

**Problema:** `Tenant` model usa constantes (`STATUS_PENDING`, etc.) enquanto `CheckSubscriptionStatus` usa `TenantStatus::PENDING->value`.

**Arquivos a modificar:**
- `app/Models/Central/Tenant.php`
  - Substituir constantes por referências ao Enum: `public const STATUS_PENDING = TenantStatus::PENDING->value;`
  - Ou melhor: usar o Enum diretamente nos métodos `activate()`, `suspend()`, `cancel()`

- `app/Http/Middleware/CheckSubscriptionStatus.php`
  - Já usa o Enum — apenas garantir consistência

**Verificação:** PHPStan nível 8 deve passar.

---

### 2.3 — Separar checkout session do contract acceptance

**Problema:** `stripe_checkout_session_id` está dentro de `signup_contract_acceptance`, misturando dados legais com billing.

**Solução:** Mover o session ID para um campo separado no JSON `data`:
```
data->signup_contract_acceptance  (dados legais)
data->stripe_checkout_session_id  (dados billing)
```

**Arquivos a modificar:**
- `app/Services/Signup/TenantSignupService.php`
  - Método `storeCheckoutSessionId()`: salvar em `data->stripe_checkout_session_id`

- `app/Services/Billing/TenantBillingService.php`
  - Métodos `getSignupCheckoutSessionId()`, `storeSignupCheckoutSessionId()`, `findTenantBySignupCheckoutSessionId()`: atualizar caminhos JSON
  - Manter compatibilidade com dados antigos (fallback para caminho antigo)

**Verificação:** Testar signup completo e verificar que o session ID é encontrado corretamente.

---

### 2.4 — Marcar tenant como falhado no job

**Problema:** Se `CreateFullTenantJob` falha definitivamente, o tenant fica `pending` para sempre.

**Solução:** No método `failed()` do job, atualizar o tenant com um status de falha ou campo `setup_failed_at`.

**Arquivos a modificar:**
- `app/Jobs/CreateFullTenantJob.php`
  - Método `failed()`: adicionar `$this->tenant->update(['status' => 'setup_failed'])` ou campo similar

- `app/Models/Central/Tenant.php`
  - Adicionar constante `STATUS_SETUP_FAILED = 'setup_failed'` (ou usar o Enum)

- `app/Http/Middleware/CheckSubscriptionStatus.php`
  - Adicionar case para `setup_failed` na mensagem de erro

**Verificação:** Forçar falha no job e verificar que o tenant recebe status correto.

---

### 2.5 — Validação de customer_id no webhook

**Problema:** `findByStripeId` pode retornar tenant errado se houver reutilização de customer ID.

**Arquivo a modificar:**
- `app/Http/Controllers/Api/V1/WebhookController.php`
  - Adicionar validação pós-busca: verificar se o tenant encontrado tem status compatível com a ação

**Verificação:** Testar com customer ID inexistente e com tenant em status incompatível.

---

## Fase 3: Melhorias

### 3.1 — Cache invalidation no plan swap

**Problema:** Cache do tenant fica desatualizado por 24h após troca de plano.

**Arquivo a modificar:**
- `app/Http/Controllers/Api/V1/Tenant/PlanSwapController.php`
  - Após `$tenant->update(['plan_id' => $newPlan->id])`, invalidar cache: `cache()->forget('tenant:'.$tenant->slug)`

**Verificação:** Verificar que o cache é atualizado após swap.

---

### 3.2 — Otimizar getters de limites no Tenant model

**Problema:** Cada acesso a `max_users`, `max_terrenos`, etc. resolve a matrix inteira.

**Arquivo a modificar:**
- `app/Models/Central/Tenant.php`
  - Usar `PlanMatrixService::resolveForTenant()` com cache local (via propriedade estática ou `once()`)

**Verificação:** Acessar múltiplos atributos e verificar que a matrix é resolvida apenas 1 vez.

---

### 3.3 — Remover `method_exists` fragil

**Problema:** `method_exists(parent::class, ...)` é frágil contra mudanças do Cashier.

**Arquivo a modificar:**
- `app/Http/Controllers/Api/V1/WebhookController.php`
  - Substituir chamadas condicionais por `parent::` direto nos handlers `handleCustomerSubscriptionUpdated`, `handleCustomerSubscriptionCreated`, `handleCustomerSubscriptionDeleted`

**Verificação:** Testar que webhooks ainda funcionam normalmente.

---

### 3.4 — Documentar workaround do stancl/tenancy

**Problema:** O bloco defensivo em `TenantSignupService.php:88-92` não explica por que existe.

**Arquivo a modificar:**
- `app/Services/Signup/TenantSignupService.php`
  - Adicionar comentário explicativo ou link para issue do stancl/tenancy

---

## Status de Implementação

| # | Tarefa | Status | Arquivos Modificados |
|---|--------|--------|---------------------|
| 1.1 | Race condition provisionamento | ✅ Concluído | `WebhookController.php` |
| 1.2 | Transação no signup | ✅ Concluído | `SignupController.php` |
| 1.3 | TenantStatusService escalabilidade | ✅ Concluído | `TenantStatusService.php`, `RefreshTenantStatsJob.php`, `console.php` |
| 2.1 | Agrupamento de updates | ✅ Concluído | `WebhookController.php` |
| 2.2 | Unificar constantes/Enum | ✅ Concluído | `Tenant.php` |
| 2.3 | Separar session do contract | ✅ Concluído | `TenantSignupService.php`, `TenantBillingService.php`, `TenantBillingServiceTest.php` |
| 2.4 | Marcar tenant como falhado | ✅ Concluído | `CreateFullTenantJob.php`, `TenantStatus.php`, `Tenant.php`, `CheckSubscriptionStatus.php` |
| 2.5 | Validação customer_id | ✅ Concluído | `WebhookController.php` |
| 3.1 | Cache invalidation | ✅ Concluído | `TenantRepository.php`, `PlanSwapController.php` |
| 3.2 | Otimizar getters | ✅ Concluído | `Tenant.php` |
| 3.3 | Remover method_exists | ✅ Concluído | `WebhookController.php` |
| 3.4 | Documentar workaround | ✅ Concluído | `TenantSignupService.php` |

---

## Verificação Final

1. ✅ PHPStan nível 8 — 1 erro pré-existente (stancl/tenancy `run()` method)
2. ✅ Testes — 17/17 passando (TenantBillingService + WebhookHandler)
3. ✅ WebhookHandlerTest — 16/16 passando
4. ⏳ Teste manual: signup completo → checkout → webhook → provisionamento
5. ⏳ Teste de cenário: falha no Stripe durante signup → tenant removido
6. ⏳ Teste de cenário: webhooks duplicados → apenas 1 provisionamento
