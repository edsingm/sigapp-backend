# RIPD — Relatório de Impacto à Proteção de Dados (plataforma SIGAPP)

> **Data:** 2026-08-14
> **Natureza:** template de engenharia para o advogado validar. **Não** é parecer jurídico nem RIPD assinado.
> **Card:** [SIG-26](https://sigapp.atlassian.net/browse/SIG-26)
> **Inventário:** [`privacy-ropa.md`](./privacy-ropa.md)

## 1. Agentes

| Agente | Papel LGPD | Escopo |
|---|---|---|
| SIGAPP (controlador) | Trata dados da própria plataforma | signup, billing, cookies, demo, admins centrais, audit, inbox DSAR |
| Tenant / incorporadora (controlador) | Trata dados do negócio imobiliário | proprietários, documentos, contratos, usuários do workspace |
| SIGAPP (operador) | Processa os dados do tenant no schema/S3 | isolamento por schema PostgreSQL; sem acesso cruzado entre tenants |

Encarregado (DPO) da plataforma: `dpo@sigapp.com.br` (`config/privacy.php`). Pedidos manuais entram no inbox do admin (PR6); não há formulário público de DSAR.

## 2. Tratamentos de alto risco (foco deste RIPD)

1. **PII de proprietários e cônjuges** no schema do tenant (RG, CPF/CNPJ, contato, endereço) — hoje em claro.
2. **Documentos PDF** (matrícula, RG/CPF, contratos) enviados a provedor de IA fora do Brasil.
3. **Billing** com identificador fiscal (`billing_tax_id`) na base central.
4. **Offboarding** de tenant cancelado: schema e S3 hoje sobrevivem ao `cancelled`.
5. **Acesso privilegiado** de admin da plataforma a um tenant (suporte).

Isolamento por schema já impede tenant A de ler tenant B. Criptografar o banco inteiro **não** entra.

## 3. Necessidade e proporcionalidade

| Tratamento | Por que existe | Limite deliberado |
|---|---|---|
| Cadastro de proprietário | O produto é gestão de terrenos e contratos reais | Cifrar PII (PR9); nome fica em claro; busca de CPF só por hash |
| Análise de PDF | Extrair campos de documentos jurídicos sem digitação | Sem aceite do admin do tenant, o job **não** dispara (PR11); `AiDataRedactor` no chat |
| Tax ID central | Cobrança e obrigação fiscal | Cast `encrypted` com `APP_KEY`; retido no wipe |
| Audit / DSAR | Prova de cumprimento e atendimento art. 18 | 5 anos; sem soft delete em `privacy_requests` |
| Cookies | Medição do site | Consent-log anônimo (`ip_hash`), 180 dias |

## 4. Riscos identificados

| Risco | Impacto | Probabilidade hoje | Mitigação no SIG-26 |
|---|---|---|---|
| Dump do banco central + tenant revela PII e a `encryption_key` em claro | Alto | Média | Cifra por tenant (PR9); envelope KMS no PR12; declarar residual no RIPD assinado |
| PDF já enviado a provider no passado sem aceite | Médio | Já ocorreu se a feature foi usada | Política + aceite **daqui pra frente** (PR11); RIPD registra o legado |
| Wipe apaga dado que o cliente ainda quer | Alto | Baixa se avisado | E-mails D60/D83; dump PR10 antes de ligar o schedule; `PRIVACY_AUTO_WIPE_ENABLED=false` |
| Admin único `is_admin` vê todos os DSARs | Médio | Aceito nesta onda | Toda ação auditada; `is_dpo` é rótulo, não RBAC |
| Soft delete sem purge | Médio | Alta hoje | `privacy:purge-soft-deletes` 90 dias (PR8) |
| Titular sem canal self-service | Médio | Alta hoje | `GET/POST /privacy/*` no app do tenant (PR4) |
| Backfill de cifra em tenant grande | Operacional | Média | Job idempotente por tenant + `pii_encrypted_at` (PR9) |

## 5. Medidas já implementadas

- Schema PostgreSQL por tenant (`PostgreSQLSchemaPublicManager`).
- `encryption_key` gerada no provisionamento (ainda não usada para PII).
- `billing_tax_id` e `admin_mfa_secret` com cast `encrypted`.
- `AiDataRedactor` em argumentos de tool do chat.
- Consent-log append-only + retenção 180 dias.
- Rate limit nomeado; `SecurityHeaders`; Sanctum + MFA TOTP no admin.
- `encryption_key` e dados fiscais `Hidden` nos Resources.

## 6. Medidas previstas (PRs 1–12)

Ver ordem em [`.agents/docs/privacidade-lgpd.md`](../.agents/docs/privacidade-lgpd.md). Resumo: aceites versionados, direitos self-service, anonimização de proprietário, inbox DSAR, wipe D90, purge de soft-deletes, cifra com chave do tenant, dump de portabilidade, aceite de transferência de PDF, KMS.

## 7. Transferências internacionais

Subprocessadores e destinos: `config/privacy.php` → `subprocessors`. Os relevantes para este RIPD:

- Stripe, Resend, storage S3/R2, provedores do `laravel/ai`, OpenCode Go (PDF), Google Maps / OpenTopography / Open-Elevation, Serper, Expo Push.

Não listar PostHog, Intercom ou Google Ads: o backend **não** os chama.

## 8. Direitos do titular

| Direito (art. 18) | Canal plataforma | Canal tenant |
|---|---|---|
| Confirmação / acesso | e-mail DPO → inbox (PR6) | `GET /api/v1/privacy/me` (PR4) |
| Portabilidade | dump admin / anexo DSAR (PR6/PR10) | `POST /api/v1/privacy/export` (PR4) |
| Eliminação da própria conta | — | `POST /api/v1/privacy/erasure` (bloqueia último ADMIN) |
| Anonimizar proprietário | — | `POST /api/v1/proprietarios/{id}/anonymize` (ADMIN/DIRECTOR) |
| Correção / informação | inbox DSAR | cadastro do próprio perfil / CRUD do tenant |

Prazo interno de pedido manual: D15 (`due_at` em `privacy_requests`).

## 9. Residual a declarar no RIPD assinado

A `encryption_key` permanece em claro na tabela `tenants` até o PR12. Um dump conjunto **central + tenant** ainda abre o PII cifrado com a chave do tenant. Isso é risco residual aceito nesta onda.

## 10. Aprovação

| Papel | Nome | Data | Status |
|---|---|---|---|
| Engenharia (fatos do software) | — | 2026-08-14 | rascunho |
| DPO / jurídico | — | — | **pendente** |
| Direção | — | — | **pendente** |
