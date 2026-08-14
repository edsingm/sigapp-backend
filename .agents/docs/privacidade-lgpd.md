# Privacidade e LGPD

> **Quando ler:** aceites legais, consent-log, direitos do titular, DSAR, offboarding/wipe, cifra de PII, RIPD/ROPA, transferência de PDF para IA.
> **Hub:** [`AGENTS.md`](../../AGENTS.md)
> **Card:** [SIG-26](https://sigapp.atlassian.net/browse/SIG-26)
> **Isto não é parecer jurídico.** Textos de política/RIPD/contrato precisam de revisão de advogado. Engenharia alinha o software aos fatos.

## Papéis (não misturar)

| Papel | Quem | Dados |
|---|---|---|
| Controlador da plataforma | SIGAPP | signup, billing, cookies, demo, admins centrais, audit |
| Controlador do negócio | Tenant (incorporadora) | proprietários, documentos, contratos, usuários do workspace |
| Operador do negócio | SIGAPP | schema/S3 do tenant — o app **ajuda** o cliente a cumprir o art. 18 |

O titular dono do terreno pede à incorporadora, não ao SIGAPP. Canal público da plataforma: `config('privacy.dpo_email')` (`dpo@sigapp.com.br`). Sem formulário DSAR no site.

## Já existe (reaproveitar)

- `POST /api/v1/consent-log` (público, throttle `consent-log`, append-only) + `privacy:cleanup-consent-logs`.
- Aceite de signup em `tenants.data.signup_contract_acceptance` (JSON legado — **não migrar**). Novos aceites: `legal_acceptances` (central no signup; tenant no convite/`POST /legal/acceptances`).
- `GET /api/v1/legal/documents` (catálogo; no tenant autenticado inclui `needs_reacceptance`). `POST /api/v1/legal/acceptances` (`auth.tenant`).
- `billing_tax_id` com cast `encrypted` (`APP_KEY`).
- `tenants.encryption_key` gerada em `CreateFullTenantJob` e **nunca lida**.
- `AiDataRedactor` no chat/tools; `AuditLog` central.
- Drop de schema só quando o model `Tenant` é *deleted*. `Tenant::cancel()` agenda wipe D90 (`cancelled_at` + `wipe_scheduled_at`). O wipe do ciclo de vida **não** chama `Tenant::delete()` para reter `stripe_id` / `billing_tax_id`. `DeleteTenantStorage` permanece comentado: o prefixo `tenants/{id}` no disk `s3` é apagado em `TenantLifecycleService`.

## Configuração

| Arquivo | Conteúdo |
|---|---|
| `config/legal.php` | Documentos versionados (`key`, `title`, `version`, `effective_at`, `url`, `hash`). Paths canônicos `/legal/*`. |
| `config/privacy.php` | DPO, retenções, `auto_wipe_enabled`, avisos D60/D83, subprocessadores do backend. |

Ao mudar o texto publicado de um documento, atualize `version`, `effective_at` e `hash` no mesmo PR. Hash novo dispara reaceite (PR1).

## Superfície planejada (SIG-26)

Novo domínio: `app/Services/Privacy/`, `Controllers/Api/V1/Tenant/Privacy*`, `Controllers/Api/V1/Admin/Privacy*`. Sem módulo Spatie novo.

| Quem | Rotas | Auth |
|---|---|---|
| Usuário do tenant | `GET /api/v1/privacy/me`, `POST/GET /privacy/export`, `POST /privacy/erasure` | `auth.tenant` (própria conta). **PR4 feito.** |
| Admin/diretor do tenant | `POST /api/v1/proprietarios/{id}/anonymize`; export `tenant_portability` | ADMIN/DIRECTOR |
| Admin da plataforma | `/api/v1/admin/privacy/requests`, `.../tenants/{id}/offboard\|wipe\|portability-export` | `is_admin` (`is_dpo` é rótulo) |

Rotas: kebab-case, `/api/v1/`, throttle nomeado, `central.context` ou `tenant.context`, FormRequest com `authorize()` real, envelope `ApiResponseService`, i18n nos dois JSONs.

Reusar `TenantExportGenerationService` (fila `exports`, S3 privado, download 24h) para:

- `subject_portability` — pacote do usuário logado
- `tenant_portability` — dump do workspace
- `privacy_request_export` — anexo de DSAR manual

## Regras sempre on deste domínio

- `billing_tax_id` permanece em `APP_KEY`. PII do schema tenant usa `EncryptedWithTenantKey` (PR9), não `APP_KEY`.
- Não cifrar IDs, status, datas, valores, polígonos, premissas, embeddings. Busca de CPF só por `cpf_cnpj_hash`.
- Wipe **não** cancela assinatura Stripe e **não** apaga `stripe_id` / `billing_tax_id`.
- `PRIVACY_AUTO_WIPE_ENABLED=false` até o dump (PR10) estar verde.
- Jobs de fila **não** geram `tenant.privileged_access`. Instrumentar `tenancy()->initialize` só em caminhos de suporte/admin.
- Sem aceite de transferência de PDF: upload continua; `AnalyzeDocumentJob` **não** dispara (PR11).
- Jobs novos: `failed()`, `$tries`, `$timeout`. Models: `$fillable`, `$casts`, factory. Sem `$guarded = []`.

## Ordem dos PRs (backend)

`PR0`–`PR12` backend implementados neste repo. Wipe automático permanece `PRIVACY_AUTO_WIPE_ENABLED=false`. O comando diário `privacy:purge-cancelled-tenants` envia avisos D60/D83 mesmo com a flag desligada. `POST /demo-request` exige `accepted_privacy` e carimba versão/hash de `legal.privacy_policy`. Fronts (site/tenant/admin) ficam de fora.

Redirects `/juridico/*` → `/legal/*` e Consent Mode v2 são `frontend_site`, não este repo.

## Fora de escopo

Cifrar viabilidade/DRE/polígono; zero-knowledge; form DSAR público; RBAC granular entre admins centrais; impersonation; reescrever `TermoDeUsoVersao`; textos “validados ANPD”.

## Docs irmãos

- Inventário art. 37: [`docs/privacy-ropa.md`](../../docs/privacy-ropa.md)
- RIPD (template de engenharia): [`docs/2026-08-14-ripd-plataforma-sigapp.md`](../../docs/2026-08-14-ripd-plataforma-sigapp.md)
