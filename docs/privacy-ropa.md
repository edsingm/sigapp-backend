# Inventário de operações de tratamento (art. 37 LGPD)

> **Status:** rascunho de engenharia (2026-08-14). Não é o registro jurídico assinado.
> **Fonte:** código do backend SIGAPP. Atualizar neste arquivo quando nascer superfície nova de dado pessoal.
> **RIPD:** [`2026-08-14-ripd-plataforma-sigapp.md`](./2026-08-14-ripd-plataforma-sigapp.md)
> **Operação:** [`.agents/docs/privacidade-lgpd.md`](../.agents/docs/privacidade-lgpd.md)

SIGAPP é **controlador** dos dados da plataforma e **operador** dos dados de negócio do tenant.

## Plataforma (controlador = SIGAPP)

| Operação | Finalidade | Titulares | Dados | Base (a validar) | Retenção | Acesso | Saída |
|---|---|---|---|---|---|---|---|
| Signup | Criar workspace e conta admin | Admin do tenant | nome, e-mail, senha hash, slug, aceite de contrato | execução de contrato / consentimento do aceite | enquanto o tenant existir; billing após wipe | signup + billing | Stripe (checkout) |
| Billing / cadastro fiscal | Cobrança e nota | Contratante | `billing_*`, `billing_tax_id` cifrado com `APP_KEY` | obrigação legal / contrato | obrigação fiscal — **não** apagar no wipe | admin do tenant, webhooks | Stripe |
| Demo request | Lead comercial | Interessado | nome, e-mail, empresa, cidade, cargo, contexto, `ip_hash` | legítimo interesse / consentimento (checkbox no PR1/PR2) | indefinido hoje | admin central, Resend | Resend |
| Consent-log de cookies | Prova de preferência | Visitante anônimo | `consent_id`, categorias, versão, `ip_hash`, UA | consentimento | 180 dias (`privacy:cleanup-consent-logs`) | sistema | nenhum |
| Admins centrais | Operar a plataforma | Colaborador SIGAPP | nome, e-mail, senha, MFA TOTP cifrado, `is_admin` | contrato de trabalho | enquanto a conta existir | `is_admin` | nenhum (MFA local) |
| Audit log | Trilha de ações privilegiadas | Admin / sistema | action, IP, UA, metadata | legítimo interesse / obrigação | 5 anos (alvo SIG-26) | admin central | nenhum |
| Auth broker | Login central → tenant | Usuário | e-mail, transfer ticket de curta duração | execução de contrato | ticket efêmero | auth | nenhum |

## Tenant (controlador = incorporadora; SIGAPP = operador)

| Operação | Finalidade | Titulares | Dados | Base (do tenant) | Retenção | Acesso | Saída |
|---|---|---|---|---|---|---|---|
| Usuários do workspace | Conta, RBAC, preferências | Colaborador da incorporadora | nome, e-mail, senha, papéis, locale | contrato / legitimidade do empregador | enquanto a conta existir; exclusão self-service no PR4 | ADMIN/usuário | Resend (convite) |
| Proprietários (`terreno_proprietarios`) | Cadastro do dono do imóvel | Pessoa física/jurídica e cônjuge | nome (claro); RG, CPF/CNPJ, e-mail, telefone, endereço, CEP, cônjuge, observações (hoje em claro; cifra no PR9) | execução de contrato / obrigação legal do tenant | soft delete hoje sem purge; 90 dias → `forceDelete` no PR8 | papéis do módulo | nenhum, salvo export |
| Contatos do terreno | Comunicação da captação | Terceiro | nome, cargo, telefone, e-mail, observações | legítimo interesse do tenant | idem soft delete | módulo terrenos | nenhum |
| Corretores externos | Intermediação | Corretor | nome, e-mail, telefone, CRECI | contrato | enquanto vinculado | módulo terrenos | nenhum |
| Documentos / versões | Dossiê do terreno | Titular indireto | PDF, metadados, análises | contrato | S3 até wipe do tenant | módulo documentos | OpenCode Go se análise ligada (PR11 exige aceite) |
| Contratos / legalização / projetos | Workflow imobiliário | Partes do negócio | nomes, status, anexos | contrato | schema do tenant | módulos respectivos | nenhum |
| SIG_IA / embeddings | Assistência e busca | Usuário + titulares em docs | prompts (redigidos), chunks, vetores, `ai_request_logs` | contrato / aceite de transferência (PR11) | logs/chunks 90 dias (PR8) | `ai.*` | provedores em `config/privacy.php` |
| Exports atuais | Relatório operacional | — | PDF/Excel de terreno/viabilidade (não é portabilidade art. 18) | contrato | artefato 24h | solicitante | S3 privado |

## O que este inventário **não** cobre ainda (buracos do SIG-26)

- Tabela `legal_acceptances` (central e tenant) — **PR1 feito**. Central: signup (`tenant_id` + `actor_email`). Tenant: convite/reaceite (`user_id`). JSON legado `tenants.data.signup_contract_acceptance` permanece.
- `privacy_requests` (inbox DSAR) — **PR6 feito**. `export_path` anexa o dump; sem tipo extra em `POST /exports`.
- Colunas `cancelled_at` / `wipe_scheduled_at` / `wiped_at` / `ai_document_transfer_accepted_at` / avisos D60/D83 — **PR7/PR11 feitos**. Wipe automático permanece desligado.
- `cpf_cnpj_hash` e cast `EncryptedWithTenantKey` — **PR9 feito**.
- Tipo de export `subject_portability` — **PR4 feito** (`POST /api/v1/privacy/export`). `tenant_portability` — **PR10 feito** (`POST /privacy/workspace-export` e admin `.../portability-export`).
- Aceite de transferência internacional de PDF — **PR11 feito**. Demo: `accepted_privacy` + versão do doc em `demo_requests`.

## Não são dados pessoais neste recorte

IDs, status de workflow, datas de etapa, valores de viabilidade, polígonos, premissas, embeddings isolados, métricas de plano. Não cifrar (decisão travada no SIG-26).
