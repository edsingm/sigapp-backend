# Plano de Implementação: Melhorias Stripe/Cashier

## FASE 0 — Config e Migration
- [ ] Passo 1: Atualizar `config/cashier.php` (mudar `'paper' => env('CASHIER_PAPER', 'A4')`).
- [ ] Passo 2: Adicionar `CASHIER_PAYMENT_NOTIFICATION` em `.env.example`.
- [ ] Passo 3: Criar migration para remover colunas do Cashier na tabela `users` (`stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`).

## FASE 1 — Notifications
- [ ] Passo 4: Criar `app/Notifications/PaymentFailedNotification.php`.
- [ ] Passo 5: Criar `app/Notifications/PaymentRequiresActionNotification.php`.
- [ ] Passo 6: Criar `app/Notifications/TrialEndingNotification.php`.
- [ ] Passo 7: Criar `app/Notifications/AbandonedCheckoutNotification.php`.

## FASE 2 — WebhookController
- [ ] Passo 8: Reescrever `handleInvoicePaymentFailed()` em `WebhookController.php`.
- [ ] Passo 9: Adicionar `handleCustomerSubscriptionTrialWillEnd()` em `WebhookController.php`.
- [ ] Passo 10: Adicionar `handleChargeDisputeCreated()` em `WebhookController.php`.
- [ ] Passo 11: Remover bloco redundante em `handleCheckoutSessionCompleted()` em `WebhookController.php`.
- [ ] Passo 12: Adicionar guard para Boleto em `handleCheckoutSessionCompleted()`.

## FASE 3 — TenantBillingService + StripeCheckoutService
- [ ] Passo 13: Tratar `past_due` no `TenantBillingService.php` (notificar mas não suspender).
- [ ] Passo 14: Sincronizar `trial_ends_at` no `TenantBillingService.php` (`syncSubscription()`).
- [ ] Passo 15: Ajustar `StripeCheckoutService.php` (remover `payment_method_types`, adicionar promo codes, tax ID e customer update).
- [ ] Passo 16: Adicionar idempotency em `createPriceOnTheFly()` no `StripeCheckoutService.php`.

## FASE 4 — Novas Rotas e Controllers
- [ ] Passo 17: Adicionar rate limiter para `signup-status` em `routes/api.php`.
- [ ] Passo 18: Criar `app/Http/Controllers/Api/V1/Tenant/PlanSwapController.php`.
- [ ] Passo 19: Criar `app/Http/Requests/Tenant/PlanSwapRequest.php`.
- [ ] Passo 20: Adicionar rotas de billing (swap, setup-intent, payment-method) em `routes/tenant.php`.
- [ ] Passo 21: Adicionar métodos `createSetupIntent` e `updateDefaultPaymentMethod` em `TenantController.php`.

## FASE 5 — CleanupPendingTenantsJob
- [ ] Passo 22: Atualizar `CleanupPendingTenantsJob.php` para enviar `AbandonedCheckoutNotification` após deleção.

## FASE 6 — Testes
- [ ] Passo 23: Criar `tests/Feature/Billing/WebhookHandlerTest.php` com todos os casos de teste especificados.
