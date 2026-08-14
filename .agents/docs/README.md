# Documentação operacional para agentes (SIGAPP Backend)

Este diretório é a **fonte detalhada** das regras do backend. O hub enxuto fica na raiz:

- **[`AGENTS.md`](../../AGENTS.md)** — leia sempre; contém regras inegociáveis + mapa sob demanda.

Os arquivos aqui **não** substituem o hub: abra-os quando a tarefa cair no domínio correspondente.

## Mapa tarefa → arquivo

| Se você for tocar em… | Leia |
|---|---|
| Stack, comandos, Docker, deploy, env de prod | [`visao-e-operacao.md`](./visao-e-operacao.md) |
| Central × tenant, host, `X-Tenant`, schemas, cache | [`multi-tenancy.md`](./multi-tenancy.md) |
| Camadas, pastas, FormRequest, API, Eloquent, i18n, nomes | [`arquitetura.md`](./arquitetura.md) |
| Login, MFA, RBAC, middlewares, rotas, throttle, health | [`auth-rbac-rotas.md`](./auth-rbac-rotas.md) |
| Stripe, planos, add-ons, webhooks, entitlements | [`billing.md`](./billing.md) |
| SIG_IA, tools, RAG, budget, análise documental | [`ia.md`](./ia.md) |
| Workflow terreno, módulos de negócio, reports, mobile | [`dominio-tenant.md`](./dominio-tenant.md) |
| Motor de viabilidade, fórmulas, financiamento, premissas | [`viabilidade.md`](./viabilidade.md) |
| Jobs, filas, e-mail, uploads, testes, Pint/PHPStan, PR | [`jobs-qualidade-checklist.md`](./jobs-qualidade-checklist.md) |
| LGPD, aceites, DSAR, wipe D90, cifra de PII, RIPD | [`privacidade-lgpd.md`](./privacidade-lgpd.md) |

## Manutenção

1. **Feature nova ou alteração considerável** → atualize o **arquivo de domínio** acima (não reinflar o hub).
2. Se o mapa de gatilhos mudar (novo domínio, nova área sensível) → atualize este `README.md` **e** a tabela no `AGENTS.md`.
3. Atualização **cirúrgica**: fiel ao código real; não transforme em changelog.
4. Em dúvida, o código e `tests/Architecture/` são a fonte da verdade.

## O que não fica aqui

- Planos de produto e análises datadas → `docs/YYYY-MM-DD-*.md`
- Skills de ferramenta (Stripe, deploy cloud, etc.) → `.agents/skills/`
