# SIG-18 — Plano de implementação de add-ons Stripe

## Decisões do MVP

- Quantidade enviada pelo cliente é a quantidade final desejada (`PATCH quantity: 3` → 3 unidades).
- Cancelamento remove o item imediatamente da assinatura Stripe, com crédito proporcional (`proration_behavior=create_prorations`), sem agendamento para o fim do ciclo. O `cancel_at_period_end` do add-on não herda o da assinatura do plano.
- Cada ambiente informa seus próprios Prices por `STRIPE_PRICE_ADDON_*`; não há criação automática de Price para add-ons.
- Bundles usam `definition.grants` em JSON e podem combinar limites e features.
- A matriz efetiva é resolvida na ordem: plano-base, add-ons Stripe ativos e, por último, `tenant_entitlements` manuais como override final.

## Modelo e fonte da verdade

`billing_addons` é o catálogo central de SKUs recorrentes mensais. `tenant_addon_subscriptions` é o espelho local dos itens atuais da assinatura Stripe. Stripe é a fonte da verdade; a reconciliação é idempotente por `stripe_subscription_item_id`.

Estados `active` e `past_due` concedem acesso; `trialing` do plano **não** libera add-on (mapeado para `incomplete` na reconciliação). Preços desconhecidos não concedem acesso e itens removidos são marcados como cancelados. A alteração de `slug`, concessão, tipo ou Price de um SKU já contratado é proibida; a rotação exige um novo SKU.

## Superfícies implementadas

1. Enums, migrations, models, factories, repositories e seed do catálogo.
2. `PlanMatrixService` com soma de add-ons e override manual final.
3. `AddonReconciliationService` integrado aos webhooks e à operação admin.
4. CRUD central de catálogo em `/api/v1/admin/billing-addons`.
5. Operação central por tenant: listagem, reconciliação e matriz efetiva.
6. APIs tenant para catálogo, contratação, quantidade e cancelamento.
7. Troca de plano Cashier preservando os itens de add-on ativos.
8. Snapshot de billing expondo add-ons sem IDs internos Stripe.
9. Catálogo tenant expondo o Price recorrente real do Stripe em `price.unit_amount`, `price.currency`, `price.interval`, `formatted_price` e `is_purchasable`, com cache curto e validação no backend para contratação e alteração de quantidade.
10. Configuração/seed por ambiente, traduções, AGENTS e testes direcionados.

## Validação

- PHPUnit cobre reconciliação, soma/override da matriz, troca de plano multi-item, snapshot e contratos de planos admin/públicos.
- Pint deve passar antes do commit.
- `composer analyse` deve ser interpretado separando os erros preexistentes dos arquivos alterados; erros novos de add-ons devem ser corrigidos antes do merge.

## Fora do MVP

- Prices one-time ou metered.
- Criação de Products/Prices no Stripe pelo CRUD.
- Concessão automática de roles/permissões ACL.
- Cobrança avulsa fora da assinatura recorrente.
