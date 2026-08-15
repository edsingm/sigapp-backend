# Plano: ambiente Staging no Dokploy (backend SIGAPP)

**Data:** 2026-08-08  
> **Histórico.** Cutover da Fase 5 concluído (Cenário B). Runbook vigente: [`docs/deploy-dokploy.md`](./deploy-dokploy.md). Ticket: **SIG-22**.

**Ticket:** SIG-22 (Fase 2; o plano citava SIG-13)  
**Contexto na redação:** Cenário A — auto-deploy de `main` em **produção** (`api.sigapp.com.br`, Stripe live, container `sigapp-backend-j8lepv-back-1`).

**Objetivo:** criar um ambiente **staging** isolado onde `main` redeploya com segurança; produção deixa de receber todo merge automaticamente.

**Runbook de operação:** `docs/deploy-dokploy.md`  
**Scripts de release:** `docs/2026-08-07-processo-release.md`

---

## 1. Princípios (não negociáveis)

| Princípio | Detalhe |
|---|---|
| **Isolamento de dados** | Staging **nunca** usa o PostgreSQL nem o Redis de produção |
| **Isolamento de dinheiro** | Stripe **test** (`sk_test` / `pk_test`); prices de teste; webhook de teste |
| **Isolamento de arquivos** | Bucket S3/R2 **separado** (ou prefixo dedicado com credenciais de escopo mínimo) |
| **Isolamento de e-mail** | Resend sandbox / domínio de teste; idealmente `MAIL_FROM` de staging |
| **Mesma imagem/compose** | Mesmo `docker-compose.prod.yml` e Dockerfile `prod`; diferença só em **envs + redes + domínio** |
| **Mesmo processo de release** | `entrypoint` sem migrate; `sigapp-bootstrap` 1×; depois `sigapp-release` via SSH |
| **Prod protegido** | Auto-deploy de `main` **não** aponta para prod após o cutover |

### Definição de “isolado de verdade”

Se staging e prod compartilharem **qualquer** dos itens abaixo, **não** é staging:

- mesmo `DB_HOST` + `DB_DATABASE` (ou mesmo cluster com mesmo DB)
- mesmo Redis (host + DB index) usado para filas/cache
- Stripe live
- bucket de documentos/exports de produção
- `APP_KEY` de produção (sessões/tokens cruzados são perigosos)

---

## 2. Arquitetura alvo

```text
                    GitHub main
                         │
                         │ auto-deploy
                         ▼
              ┌─────────────────────┐
              │  Dokploy STAGING    │
              │  backend compose    │──► api.staging.sigapp.com.br
              │  PG staging         │
              │  Redis staging      │
              │  Stripe test        │
              │  S3 staging         │
              └─────────────────────┘
                         │
                         │ promoção manual / tag / botão
                         ▼
              ┌─────────────────────┐
              │  Dokploy PRODUÇÃO   │
              │  (já existe)        │──► api.sigapp.com.br
              │  auto-deploy OFF    │
              │  ou branch/tag only │
              └─────────────────────┘
```

### Convenção de nomes sugerida

| Recurso | Nome sugerido |
|---|---|
| Projeto / app backend staging | `sigapp-backend-staging` |
| PostgreSQL staging | `sigapp-database-staging` (Compose separado, como em prod) |
| Redis staging | `sigapp-redis-staging` |
| Domínio API | `api.staging.sigapp.com.br` |
| Front tenant (quando existir) | `app.staging.sigapp.com.br` + wildcard `*.staging.sigapp.com.br` se multi-tenant por subdomínio |
| Database name | `sigapp_staging` |
| Container (exemplo) | `sigapp-backend-staging-…-back-1` (anotar após o 1º deploy) |

Ajuste os hostnames ao DNS real da Hostinger/registrador.

---

## 3. Pré-requisitos

- [ ] Acesso admin ao Dokploy na VPS
- [ ] Acesso SSH à VPS
- [ ] Acesso DNS do domínio `sigapp.com.br` (ou o domínio que for usado)
- [ ] Conta Stripe com modo **test** e prices de teste (ou criar prices test espelhando Broker/Básico/Master/Pro + add-ons)
- [ ] Bucket S3/R2 de staging (vazio)
- [ ] Chave Resend adequada para não spammar clientes (sandbox ou from de staging)
- [ ] CI + branch protection na `main` já ativos (Fase 1)
- [ ] Capacidade de gerar `APP_KEY` novo (`php artisan key:generate --show` local)
- [ ] Decisão sobre frontends de staging (mínimo: API-only com smoke curl; ideal: app + admin staging)

### Decisão de escopo MVP vs completo

| Nível | O que sobe em staging | Quando usar |
|---|---|---|
| **MVP (recomendado 1ª semana)** | Só **backend** + PG + Redis + DNS API | Validar migrate, health, login API, webhooks Stripe test |
| **Completo** | Backend + front tenant + admin (+ site se preciso) | QA de fluxo broker/transfer ticket de ponta a ponta |

Este plano detalha o **MVP backend** e marca frontends como fase opcional 2b.

---

## 4. Plano em fases (ordem obrigatória)

> **Não** desligue o auto-deploy de produção **antes** de staging estar healthy e o time saber rodar `sigapp-release` nele — senão vocês ficam sem caminho de deploy automático e sem rede de segurança.  
> Ordem: **criar staging → validar → só então cortar auto-deploy de prod**.

### Visão das fases

| Fase | Nome | Resultado |
|---|---|---|
| **0** | Inventário de prod | Lista de envs, redes Docker, volumes, backups |
| **1** | Infra staging (PG + Redis) | Banco e Redis novos, redes conhecidas |
| **2** | DNS + TLS | `api.staging…` resolve e tem HTTPS |
| **3** | App backend staging no Dokploy | Compose deployável, envs isolados |
| **4** | Bootstrap + smoke | Schema + seed + health + login básico |
| **5** | Cutover de auto-deploy | `main` → só staging; prod manual |
| **6** | Runbook + disciplina de time | Dois comandos SSH, checklist PR |
| **7** | (Opcional) Frontends staging | Fluxo UI completo |
| **8** | (Opcional) Automação release | SIG-13 Fase 3 |

---

## Fase 0 — Inventário de produção (30–60 min)

Anotar (sem colar secrets no Git):

| Item | Onde achar | Anotar |
|---|---|---|
| App Dokploy prod | UI | nome exato, compose path, branch, auto-deploy |
| Container back | `docker ps` | `sigapp-backend-j8lepv-back-1` (já conhecido) |
| Rede do Postgres prod | `docker network ls` / compose | `sigapp-database-wlnxuu_default` |
| Hostname interno do DB | envs do container | valor de `DB_HOST` |
| Redis host prod | envs | `REDIS_HOST` |
| Lista de env keys | Dokploy env UI | copiar **nomes** das chaves (não valores) |
| Domínios Traefik/proxy | Dokploy domains | api, app, wildcards |
| Backup PG | rotina atual | existe? frequência? |

**Critério de saída:** planilha/nota interna com redes e lista de variáveis; ninguém “chuta” rede na Fase 1.

---

## Fase 1 — PostgreSQL e Redis de staging

### 1.1 PostgreSQL staging

No Dokploy, criar recurso/projeto de banco **separado** (espelhando o padrão de prod: Compose próprio).

Recomendações:

| Config | Valor sugerido |
|---|---|
| Imagem | PostgreSQL **16** + **pgvector** (`pgvector/pgvector:0.8.6-pg16`, alinhado ao banco gerenciado do Dokploy e ao CI) |
| Database | `sigapp_staging` |
| User | `sigapp_staging` (ou `sigapp`) |
| Password | forte, **diferente** de prod |
| Porta no host | **não publicar** `5432` na internet; só rede Docker |
| Rede Compose | nome próprio, ex. rede gerada `sigapp-database-staging-…_default` |

Após subir:

```bash
# Na VPS — confirmar rede e alias do serviço
docker network ls | grep -i stag
docker ps | grep -i postgres
```

Anotar:

- nome da **rede externa** que o backend staging vai declarar no compose  
- hostname do serviço DB na rede (em prod o alias é `database` — se staging for igual no compose do DB, manter `database` **na rede de staging**, não na de prod)

### 1.2 Ajuste de rede no backend staging

Hoje o `docker-compose.prod.yml` fixa:

```yaml
networks:
  sigapp-database-wlnxuu_default:
    external: true
```

**Opções (escolher uma):**

| Opção | Prós | Contras |
|---|---|---|
| **A. Compose de staging no Dokploy** com override da rede (env/UI se Dokploy permitir) | Prod intocado no Git | Depende do que a UI permite |
| **B. Arquivo `docker-compose.staging.yml`** no repo apontando rede staging | Explícito no Git | Dois arquivos para manter |
| **C. Variável / rede com nome estável** documentada por ambiente | Flexível | Requer disciplina |

**Recomendação:** **B** — `docker-compose.staging.yml` quase idêntico ao prod, mudando só:

- nome da rede externa do Postgres staging  
- (opcional) `container_name` omitido para o Dokploy gerar `…-staging-…`

O app staging no Dokploy usa o compose **staging**; prod continua no `docker-compose.prod.yml`.

### 1.3 Redis staging

Criar Redis **separado**:

| Config | Valor |
|---|---|
| Imagem | `redis:7-alpine` (ou a mesma de prod) |
| Password | diferente de prod |
| Persistência | opcional em staging (AOF/RDB) |
| Exposição | só rede Docker |

No backend staging:

- `REDIS_HOST` = hostname interno do Redis staging  
- `REDIS_PASSWORD` = do staging  
- Opcional: `CACHE_PREFIX=sigapp-staging-` e `REDIS_PREFIX=sigapp-staging-` para defesa em profundidade se um dia alguém errar o host

**Critério de saída Fase 1:**

- [ ] `psql`/client de dentro de um container temporário na rede staging conecta em `sigapp_staging`
- [ ] `redis-cli -a … ping` no Redis staging → `PONG`
- [ ] **Zero** containers de staging na rede `sigapp-database-wlnxuu_default` de prod (verificação explícita)

---

## Fase 2 — DNS e TLS

### 2.1 Registros DNS

| Tipo | Nome | Destino |
|---|---|---|
| `A` ou `CNAME` | `api.staging` | IP da VPS / proxy Dokploy |
| (MVP opcional) `A`/`CNAME` | `app.staging` | mesma VPS |
| (MVP opcional) `A`/`CNAME` | `admin.staging` | mesma VPS |
| (multi-tenant UI) wildcard | `*.staging` | mesma VPS |

Não reutilizar `api.sigapp.com.br` para staging.

### 2.2 Domínio no Dokploy

No app backend staging:

- Domain: `https://api.staging.sigapp.com.br`
- HTTPS gerenciado pelo proxy do Dokploy (Let’s Encrypt / DNS challenge se necessário)

**Critério de saída:**

```bash
curl -fsSI https://api.staging.sigapp.com.br/api/v1/health
# pode 502 até o app existir; DNS e TLS já devem resolver
```

---

## Fase 3 — Aplicação backend staging

### 3.1 Criar app no Dokploy

| Campo | Valor |
|---|---|
| Tipo | Docker Compose |
| Repositório | mesmo `sigapp-backend` |
| Branch | `main` |
| Compose file | `docker-compose.staging.yml` (após existir no repo) **ou** override da rede se opção A |
| Auto-deploy | **ON** |
| Domínio | `api.staging.sigapp.com.br` |

### 3.2 Variáveis de ambiente (checklist de isolamento)

Partir de `.env.production.example`, gerar **valores novos**.

#### Identidade e URLs

| Variável | Staging (exemplo) | Prod (referência) |
|---|---|---|
| `APP_ENV` | `production` *ou* `staging` se o app tratar; se só `local/testing/production`, use `production` + URLs de staging | `production` |
| `APP_DEBUG` | `false` (mesmo em staging) | `false` |
| `APP_KEY` | **novo** | atual |
| `APP_URL` | `https://api.staging.sigapp.com.br` | `https://api.sigapp.com.br` |
| `APP_DOMAIN` | `staging.sigapp.com.br` | `sigapp.com.br` |
| `CENTRAL_DOMAINS` | `staging.sigapp.com.br,api.staging.sigapp.com.br,app.staging.sigapp.com.br,admin.staging.sigapp.com.br` (+ host interno se necessário) | domínios prod |
| `FRONTEND_URL` | `https://app.staging.sigapp.com.br` (ou placeholder se front ainda não existe) | `https://app.sigapp.com.br` |
| `LANDING_URL` | landing staging ou a de prod **somente leitura** (preferir staging) | prod |
| `SANCTUM_STATEFUL_DOMAINS` | hosts staging + `*.staging.sigapp.com.br` | hosts prod |
| `CORS_ALLOWED_ORIGINS` | origens HTTPS staging | origens prod |
| `SESSION_SECURE_COOKIE` | `true` | `true` |
| `TRUSTED_PROXIES` | IP/CIDR do proxy Dokploy (**nunca** `*`) | idem prod |

#### Banco e Redis

| Variável | Staging |
|---|---|
| `DB_HOST` | hostname **staging** na rede staging |
| `DB_DATABASE` | `sigapp_staging` |
| `DB_USERNAME` / `DB_PASSWORD` | credenciais staging |
| `REDIS_HOST` / `REDIS_PASSWORD` | Redis staging |
| `REDIS_DB` / `REDIS_CACHE_DB` | 0 / 1 (no Redis **staging**) |

#### Billing

| Variável | Staging |
|---|---|
| `STRIPE_KEY` | `pk_test_…` |
| `STRIPE_SECRET` | `sk_test_…` |
| `STRIPE_WEBHOOK_SECRET` | secret do endpoint **test** apontando para `https://api.staging.sigapp.com.br/webhook/stripe` (path real do app) |
| `STRIPE_PRICE_*` / add-ons | **Price IDs de test mode** |
| `STRIPE_PORTAL_CONFIGURATION_ID` | config de test, se houver |

#### Storage

| Variável | Staging |
|---|---|
| `AWS_BUCKET` | bucket staging |
| `AWS_*` keys | keys com acesso só ao bucket staging |

#### E-mail e admin

| Variável | Staging |
|---|---|
| `RESEND_API_KEY` | chave de teste / projeto staging |
| `MAIL_FROM_ADDRESS` | ex. `staging@…` ou domínio verificado de teste |
| `CENTRAL_ADMIN_EMAIL` | e-mail da equipe (não cliente) |
| `CENTRAL_ADMIN_PASSWORD` | senha forte **diferente** de prod |
| `CENTRAL_ADMIN_NAME` | ok reutilizar label |

#### IA

| Variável | Staging |
|---|---|
| Chaves de provider | preferir chaves/projeto com **budget baixo** |
| `AI_TENANT_BUDGET_DEFAULT` | valor baixo (ex. 1–2) |

### 3.3 O que **copiar** de prod sem copiar

- **Copiar:** nomes das chaves, estrutura do compose, versão de imagem base  
- **Não copiar:** `APP_KEY`, senhas DB/Redis, Stripe live, webhook secret live, bucket prod, admin password

### 3.4 Deploy inicial

1. Push do `docker-compose.staging.yml` (se opção B) na `main` — **atenção:** com auto-deploy prod ainda ON, esse push também redeploya **prod**.  
   - Mitigação: merge do compose staging **só muda arquivo não usado por prod** → rebuild prod é inofensivo mas gera janela; combine com janela curta + `sigapp-release` em prod se o deploy rodar.  
   - Alternativa: criar o arquivo e mergear em horário controlado.
2. No Dokploy staging: Deploy.
3. Confirmar container up:

```bash
docker ps | grep -i staging
```

Anotar o nome real: `______________________________-back-1`.

**Critério de saída Fase 3:** container back staging healthy o suficiente para executar comandos (mesmo que `/api/v1/health` falhe por DB ainda sem migrate).

---

## Fase 4 — Bootstrap e smoke

### 4.1 Bootstrap (uma vez)

```bash
ssh <user>@<vps>
docker exec <CONTAINER_STAGING_BACK> /usr/local/bin/sigapp-bootstrap
```

Esperado: migrate central + seed + caches, exit 0.

### 4.2 Health

```bash
curl -fsS https://api.staging.sigapp.com.br/api/v1/health
```

### 4.3 Smoke funcional mínimo (API)

| # | Teste | Como |
|---|---|---|
| 1 | Health | `GET /api/v1/health` → 200 |
| 2 | Docs (se exposto) | `/docs/api` ou política de não expor em staging |
| 3 | Admin login challenge | `POST /api/v1/admin/login` com admin seedado (MFA se aplicável) |
| 4 | Signup/tenant de teste **ou** seed de tenant | criar 1 tenant `staging-demo` |
| 5 | Login tenant / transfer ticket | fluxo auth central → exchange no host staging |
| 6 | Workers | `supervisorctl status` no container |
| 7 | Stripe webhook test | Stripe CLI ou Dashboard test → staging URL |
| 8 | Upload pequeno (se S3 staging) | 1 documento de teste e apagar |

### 4.4 Dados de seed úteis

Após bootstrap, criar manualmente ou por seeder controlado:

- 1–2 tenants fictícios (`staging-a`, `staging-b`)
- 1 usuário ADMIN por tenant
- 1 terreno mínimo (opcional)
- **Não** importar dump de produção sem anonimização (LGPD)

**Critério de saída Fase 4:** checklist smoke 1–6 verde; time consegue repetir `sigapp-release` em staging sem dúvida.

---

## Fase 5 — Cutover de auto-deploy (o ponto crítico)

### 5.1 Ordem do cutover (janela ~15–30 min)

| Passo | Ação | Rollback se der errado |
|---|---|---|
| 1 | Confirmar staging healthy + smoke OK | — |
| 2 | **Desligar auto-deploy** no app **produção** | Religar auto-deploy prod |
| 3 | Confirmar staging continua com auto-deploy **ON** em `main` | — |
| 4 | Documentar: “prod deploy = botão manual no Dokploy” | — |
| 5 | Fazer um commit no-op ou PR vazio de teste em `main` | Ver se **só** staging redeployou |
| 6 | Em staging: `sigapp-release` + health | — |
| 7 | Confirmar prod **não** redeployou (mesma imagem/hora no `docker ps` / UI) | — |

### 5.2 Como sobe produção daqui pra frente (até Fase 3 do SIG-13)

```text
1. Validar em staging (auto)
2. Alguém com acesso: Deploy manual no app prod (mesma revisão/commit)
3. SSH: docker exec sigapp-backend-j8lepv-back-1 /usr/local/bin/sigapp-release
4. Smoke: https://api.sigapp.com.br/api/v1/health
```

Opcional depois: tag `vX.Y.Z` + workflow_dispatch (SIG-13 Fase 3).

### 5.3 Política de merge pós-cutover

- Merge na `main` = **vai para staging automaticamente**
- Produção = **ato consciente** (deploy manual + release SSH)
- Migration em PR: testar `sigapp-release` em staging antes de promover prod

**Critério de saída Fase 5:** Cenário **B** — auto-deploy de `main` não toca Stripe live nem DB prod.

---

## Fase 6 — Runbook e disciplina de time

Atualizar `docs/deploy-dokploy.md` com tabela dos dois ambientes:

| | Staging | Produção |
|---|---|---|
| URL | `api.staging.sigapp.com.br` | `api.sigapp.com.br` |
| Auto-deploy | ON (`main`) | OFF |
| Container | *(preencher)* | `sigapp-backend-j8lepv-back-1` |
| Release | `docker exec … sigapp-release` | idem prod |
| Bootstrap | só 1º dia | nunca de novo |

Checklist de PR (sugestão):

```markdown
- [ ] CI verde
- [ ] Após merge: staging redeployou
- [ ] SSH staging: sigapp-release (se houve migration ou se for política sempre)
- [ ] Smoke staging
- [ ] (Se promover) Deploy manual prod + sigapp-release + smoke prod
```

Política de `sigapp-release` em staging:

- **Mínimo:** sempre que o PR tiver migration (central ou tenant)
- **Mais seguro:** **sempre** após cada deploy de staging (barato e evita esquecer)

---

## Fase 7 — Frontends staging (opcional, 2b)

Quando a API staging estiver estável:

| App | Domínio sugerido | Env importantes |
|---|---|---|
| Tenant | `app.staging.sigapp.com.br` + `*.staging…` | `NEXT_PUBLIC_API_URL=https://api.staging…` |
| Admin | `admin.staging.sigapp.com.br` | idem |
| Site | `staging.sigapp.com.br` | opcional |

Backend: alinhar `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_URL`, `CENTRAL_DOMAINS`.

Wildcard TLS para `*.staging.sigapp.com.br` pode exigir DNS challenge (mesmo tema de prod).

---

## Fase 8 — Depois (SIG-13 Fase 3+)

- Job GitHub que SSH e roda `sigapp-release` no container staging após deploy  
- Deploy prod por tag + smoke  
- Alerta se health staging cair  
- Backup + restore test no PG staging (ensaio do procedimento de prod)

---

## 5. Trabalho no repositório (código)

| Entrega | Obrigatório? | Descrição |
|---|---|---|
| `docker-compose.staging.yml` | Recomendado | Clone de prod com rede externa do PG staging |
| `.env.staging.example` | Recomendado | Catálogo de envs sem secrets; URLs staging |
| Atualizar `docs/deploy-dokploy.md` | Sim (pós cutover) | Dois ambientes, dois comandos |
| Atualizar `AGENTS.md` | Sim | “main → staging; prod manual” |
| Remover leftovers Coolify em examples | Já em andamento | `SET_IN_COOLIFY` → `SET_IN_DOKPLOY` se ainda restar |

### Esqueleto sugerido de `docker-compose.staging.yml`

- Copiar `docker-compose.prod.yml`
- Trocar rede externa para a do Postgres **staging** (nome real após Fase 1)
- Manter `target: prod`, healthcheck, `expose: 80`
- Não commitar secrets

---

## 6. Riscos e armadilhas

| Risco | Mitigação |
|---|---|
| Staging na rede Docker de **prod** | Checklist Fase 1: inspecionar `docker network inspect` |
| Stripe live colado por engano | Revisar prefixo `sk_test` / `pk_test` antes do 1º deploy |
| Webhook Stripe de prod apontando para staging (ou o contrário) | Endpoints separados no Dashboard |
| Push na `main` durante criação do compose staging redeploya prod | Horário controlado + pessoa pronta para `sigapp-release` prod |
| `APP_KEY` igual a prod | Gerar sempre novo |
| Dump de prod em staging | LGPD; usar seed sintético |
| Esquecer `sigapp-release` em staging | Política “sempre após deploy” |
| `CENTRAL_DOMAINS` / multi-tenant errados | Host de tenant staging deve casar com `APP_DOMAIN` |
| Custo de IA em staging | Budget baixo + chaves separadas |
| Nome de container muda | Runbook com `docker ps` de descoberta |

---

## 7. Cronograma sugerido

| Dia | Atividade |
|---|---|
| **D0** | Fase 0 inventário + decisão DNS + Stripe test prices + bucket staging |
| **D1** | Fase 1 PG/Redis + PR `docker-compose.staging.yml` + `.env.staging.example` |
| **D1–D2** | Fase 2–3 app Dokploy + envs + 1º deploy |
| **D2** | Fase 4 bootstrap + smoke |
| **D2–D3** | Fase 5 cutover auto-deploy (janela curta, 2 pessoas) |
| **D3** | Fase 6 docs no repo + avisar o time |
| **D+** | Fase 7 frontends se necessário |

Esforço típico: **1–2 dias** de ops para MVP backend, se DNS e Stripe test já existirem.

---

## 8. Critérios de aceite (Fase 2 do SIG-13)

- [ ] Existe stack staging no Dokploy com Compose
- [ ] PostgreSQL e Redis **não** são os de produção
- [ ] Stripe, S3 e e-mail de staging são de teste/isolados
- [ ] `https://api.staging.sigapp.com.br/api/v1/health` → 200
- [ ] `sigapp-bootstrap` rodou 1×; deploys seguintes usam `sigapp-release` via SSH
- [ ] Auto-deploy de `main` **não** atualiza `api.sigapp.com.br`
- [ ] Deploy de produção é **manual** (ou por tag, se já evoluído)
- [ ] Runbook documenta os dois containers e os dois fluxos
- [ ] Smoke de login/tenant básico passou em staging
- [ ] Zero vazamento de secrets de prod no app staging (revisão de envs)

---

## 9. Papéis

| Papel | Responsabilidades |
|---|---|
| Ops / quem tem Dokploy | Criar PG, Redis, app, DNS, cutover auto-deploy |
| Backend | Compose staging, examples, smoke API, migrations |
| Quem tem Stripe | Prices test, webhook staging |
| Time todo | Após cutover: não mergear e “assumir que foi pra prod” |

---

## 10. Primeira ação concreta (agora)

1. Na VPS: `docker network ls` e anotar rede do Postgres **prod** (já conhecida) vs espaço livre de nomes para staging.  
2. Criar no Dokploy o **PostgreSQL staging** (vazio) e o **Redis staging**.  
3. No DNS: criar `api.staging.sigapp.com.br` → VPS.  
4. No repo: PR com `docker-compose.staging.yml` + `.env.staging.example` (quando a rede do PG staging tiver nome definitivo).  
5. Só depois: app backend staging + bootstrap + cutover.

---

**Referências**

- `docs/deploy-dokploy.md`
- `docs/2026-08-07-processo-release.md`
- `docker-compose.prod.yml`
- `.env.production.example`
- SIG-13 — Completar CI/CD (Fase 2 = este plano)
