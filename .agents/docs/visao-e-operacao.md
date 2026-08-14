# Visão geral e operação

> **Quando ler:** setup local, stack, comandos, Docker, compose, deploy/prod, env de produção.
> **Hub:** [`AGENTS.md`](../../AGENTS.md) — regras sempre on e mapa de docs.

## Visão geral do projeto

**SIGAPP** é um SaaS multi-tenant de gestão imobiliária para incorporadoras/loteadoras: prospecção e qualificação de **terrenos**, estudo de **viabilidade** econômica (DRE, fluxo mensal, indicadores), **comitê** de aprovação, **negociação**, **contratos**, **legalização** (etapas/checklist), **projetos**, dashboards e um **agente de IA** (SIG_IA) com dezenas de tools. Cada cliente (tenant) acessa via subdomínio (`{tenant}.sigapp.com.br`) e o painel administrativo central roda nos domínios centrais.

| Item | Valor |
|---|---|
| **Framework** | Laravel 13 (`laravel/framework ^13.0`) |
| **Linguagem** | PHP **8.4+** (`php ^8.4`, PHPStan `phpVersion: 80400`) |
| **Banco de dados** | PostgreSQL **16** + `pgvector` 0.8.6 (central + 1 schema por tenant). SQLite `:memory:` nos testes |
| **Storage** | Laravel Storage local/S3 (`league/flysystem-aws-s3-v3`); documentos e relatórios PDF de IA usam o disk `s3`; uploads contam limite `storage_gb` do plano |
| **Multi-tenancy** | `stancl/tenancy ^3.8` — manager customizado `PostgreSQLSchemaPublicManager`, identificação por subdomínio + header `X-Tenant` (fallback local/testing) |
| **Autenticação** | Laravel Sanctum (tokens Bearer) + broker de login central com transfer tickets |
| **Autorização/RBAC** | `spatie/laravel-permission ^7.0` (`teams => false`) + templates de permissão por plano |
| **Billing** | Laravel Cashier (Stripe) `^16.0` — planos, entitlements, cupons, dunning, webhooks |
| **IA** | **Laravel AI SDK** (`laravel/ai ^0.10`) — agente `SIG_IA`, providers DeepSeek/Gemini/OpenRouter via `config/ai.php` |
| **E-mail** | Resend (`resend/resend-laravel`) via Notifications |
| **PDF** | `spatie/laravel-pdf` + `spatie/browsershot` (Chromium — `BROWSERSHOT_CHROME_PATH`) |
| **Excel** | `maatwebsite/excel ^3.1` (`app/Exports/`) |
| **Docs da API** | `dedoc/scramble` — UI em `/docs/api` (alias `/docs`) |
| **Testes** | PHPUnit 13 (suites `Architecture`, `Unit`, `Feature`) — **não** usa Pest; CI (`.github/workflows/ci.yml`): Tests (SQLite), PostgreSQL 16 + pgvector + Redis 7, Pint, **PHPStan** (`composer analyse`) e **Docker build** (`--target prod`) |
| **Formatação** | Laravel Pint, preset `laravel` (`pint.json`) |
| **Análise estática** | PHPStan **nível 8** + bleedingEdge + baseline (`phpstan.baseline.neon`) |
| **Dev local** | Laravel Herd (macOS) ou `composer dev` / Docker (`.docker/` + `docker-compose.yml` — ver seção Docker) |
| **Frontend** | Next.js separado (repositório irmão) — CORS via `CORS_ALLOWED_ORIGINS` (fallback localhost somente em `local`/`testing`), URLs em `FRONTEND_URL`/`LANDING_URL` |

### Comandos essenciais

```bash
composer setup                      # install + .env + key + migrate
composer dev                        # serve + queue:listen + pail + vite (concurrently)
composer test                       # config:clear + php artisan test
composer analyse                    # phpstan (memory 512M)
./vendor/bin/pint --test            # checa formatação (sem alterar)
php artisan test --testsuite=Architecture   # só os testes de arquitetura
php artisan sigapp:release          # deploy: migrate central + tenants (nunca seed)
php artisan sigapp:bootstrap        # só ambiente vazio: migrate + seed
```

---

## Docker e ambientes

Há duas formas de rodar localmente: **Herd/`composer dev`** (nativo, macOS) ou **Docker**. A infra Docker vive em `.docker/` (diretório oculto) + `docker-compose.yml` (dev) + `docker-compose.prod.yml` (prod).

### Imagem (`.docker/Dockerfile`, multi-stage)

| Stage | Conteúdo |
|---|---|
| `base` | `php:8.4-fpm` + extensões (`pdo_pgsql`, `redis` via PECL, `gd`, `intl`, `zip`, `bcmath`, `pcntl`, `exif`, `mbstring`) + **Node 20 + Chromium + Puppeteer** (necessários para Browsershot/`spatie/laravel-pdf`) + Composer |
| `dev` | código via **bind mount** (`.:/var/www`); entrypoint (`entrypoint.dev.sh`) instala `vendor/` se faltar, garante `.env`/`APP_KEY`, roda `optimize:clear` e sobe `php artisan serve` na porta **8000** |
| `prod` | código **embutido na imagem** (`composer install --no-dev` otimizado) + **nginx + php-fpm + supervisord** |

### Compose

- **Dev (`docker-compose.yml`)**: services `back` (`sigapp-backend:1.0-dev`, porta 8000) e `redis` (`redis:7-alpine`). O **PostgreSQL não está no compose** — é um container/host externo chamado `database`, alcançado pela rede externa `database_sigapp` (precisa existir: `docker network create database_sigapp`). As variáveis de ambiente de dev (DB, Redis, CORS, Sanctum, `CENTRAL_DOMAINS=localhost,127.0.0.1,sigapp-backend`, Chromium) já vêm definidas no compose.
- **Prod (`docker-compose.prod.yml`)**: target `prod`, porta interna `80` via `expose` (sem publicação no host), PostgreSQL **16** + `pgvector` e Redis externos **gerenciados pelo Dokploy** (não o repositório `database`), envs obrigatórios via `${VAR:?}` e healthcheck em `GET /api/v1/health`. O serviço `back` também se conecta à rede externa do Compose PostgreSQL (`sigapp-database-wlnxuu_default`) para resolver o alias `database`; se o projeto do banco mudar, atualize esse nome de rede. Cookies de sessão são seguros por padrão em produção; `TRUSTED_PROXIES` aceita somente IPs/CIDRs explícitos do proxy (nunca `*`).
- **Runbook de deploy:** `docs/deploy-dokploy.md` (Dokploy + Compose + `sigapp-release` via SSH). `docs/deploy-coolify.md` está **obsoleto**. Em produção atual: auto-deploy de `main` aponta para **prod** (`api.sigapp.com.br`, Stripe live); após cada deploy é obrigatório rodar `/usr/local/bin/sigapp-release` no container backend (referência: `sigapp-backend-j8lepv-back-1`). Staging isolado ainda não existe — todo merge em `main` é go-live até a Fase 2 do SIG-13.

### Produção — quem roda o quê

- `entrypoint.prod.sh` prepara caches e sobe o supervisord; ele **não executa migrations** durante restart/scale.
- Primeiro deploy em banco vazio: execute `/usr/local/bin/sigapp-bootstrap` uma única vez (wrapper de `php artisan sigapp:bootstrap` → `migrate` + `db:seed`). Releases seguintes executam `/usr/local/bin/sigapp-release` (wrapper de `php artisan sigapp:release` → `migrate` central + `tenants:migrate`) antes de trocar o tráfego. O bootstrap recusa banco já inicializado, salvo `--force`. O release nunca executa seeders.
- `supervisord.conf` mantém **nginx**, **php-fpm**, **`schedule:work`** e cinco grupos isolados de workers Redis: `tenant-provisioning`, `ai`, `exports`, `notifications` e `default`. A concorrência de cada grupo é configurada por `QUEUE_*_PROCESSES`; `retry_after=660` permanece acima do maior timeout de Job (600s). O scheduler pode rodar em todas as réplicas porque cada evento de `routes/console.php` tem nome único, `onOneServer()` e `withoutOverlapping()` sobre o Redis compartilhado; nunca adicione um schedule sem essas três proteções.
- `nginx.conf`: root em `public/`, `client_max_body_size 50M` (limite de upload), `fastcgi_read_timeout 120s` (teto para requests longos — PDFs/exports pesados devem ir para Jobs).

### Implicações para quem altera o código

- Dependência nova de **sistema** (extensão PHP, binário, fonte) → editar `.docker/Dockerfile` (e lembrar que o stage `base` serve dev e prod).
- Dependência/driver novo de **storage externo** (ex.: S3, MinIO) → atualizar `composer.json` se necessário, `config/filesystems.php`, `.env.example`, compose/deploy e esta seção.
- Migrations de produção rodam explicitamente pelo script de release, nunca implicitamente no startup. Todo `down()` continua obrigatório e migrations aplicadas nunca devem ser editadas.
- `route:cache`/`config:cache` rodam no deploy — não use closures em rotas de `routes/api.php`/`tenant.php` que quebrem o cache de rotas fora dos padrões já existentes, nem `env()` fora de `config/`.
- Ao alterar proxy/CORS/sessão, mantenha `TRUSTED_PROXIES` em `config/trustedproxy.php`, origens de produção explícitas em `CORS_ALLOWED_ORIGINS` e `SESSION_SECURE_COOKIE=true`; atualize `.env.example`, `.env.production.example` e os arquivos Compose.
- O `.dockerignore` exclui `.env*` (exceto `.env.example`) — configuração de prod entra **somente** por variável de ambiente do compose.
- O catálogo canônico de configuração fica em `.env.example` (local) e `.env.production.example` (Dokploy/produção); eles devem documentar as integrações ativas, sem segredos reais. Variáveis antigas não consumidas pelo backend, como `CASHIER_MODEL`, `NVIDIA_NIM_*`, `NIXPACKS_*` e `SERVICE_*`, não devem ser reintroduzidas. Documentos, relatórios e exports continuam exigindo as credenciais do disk `s3`, mesmo quando `FILESYSTEM_DISK=local` é usado no desenvolvimento.
- O healthcheck de prod depende de `GET /api/v1/health` (definido em `routes/api.php`) — não remova nem proteja essa rota com auth/throttle agressivo.
