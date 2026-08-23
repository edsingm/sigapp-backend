# Deploy do SIGAPP no Dokploy

Runbook operacional do **backend** na VPS gerenciada pelo **Dokploy**.  
O processo de scripts (`sigapp-bootstrap` / `sigapp-release` / entrypoint) está em
`docs/2026-08-07-processo-release.md`. Os binários delegam a `php artisan sigapp:bootstrap`
e `php artisan sigapp:release`. Este arquivo cobre **plataforma, ordem
de deploy e comandos copy-paste**.

**Dokploy é a única plataforma de deploy.** Coolify, AWS ECS/EKS, Laravel Cloud
e equivalentes não são usados. Em caso de divergência, este documento e o
`AGENTS.md` prevalecem.

| Repositório | Compose | Runbook |
|---|---|---|
| backend (este) | `docker-compose.staging.yml` / `docker-compose.prod.yml` | este arquivo |
| frontend_tenant | idem | `docs/deploy-dokploy.md` no repo tenant |
| frontend_site | idem | `docs/deploy-dokploy.md` no repo site |
| frontend_admin | `docker-compose.prod.yml` | `docs/deploy-dokploy.md` no repo admin |
| database | `docker-compose.prod.yml` (infra, não app) | `docs/deploy-dokploy.md` no repo database |

---

## Estado atual — Cenário B

`main` sobe sozinha **somente** em staging. Produção é deploy **manual** da
mesma revisão que passou no smoke de staging. Merge na `main` **não** é go-live.

| | Staging | Produção |
|---|---|---|
| App Dokploy | stack staging (compose `docker-compose.staging.yml`) | stack prod (compose `docker-compose.prod.yml`) |
| Branch | `main` | `main` (revisão escolhida no deploy) |
| Auto-deploy | **ON** | **OFF** |
| API | `https://api.staging.sigapp.com.br` | `https://api.sigapp.com.br` |
| Stripe | **test** (`sk_test` / prices test) | **live** (`sk_live` / prices live) |
| PostgreSQL / Redis / S3 | isolados (não os de prod) | isolados (não os de staging) |
| Service Compose | `back` | `back` |
| Container (referência) | descobrir — ver [Containers](#descobrir-o-container-back) | `sigapp-backend-j8lepv-back-1` |
| Gate de schema | `sigapp:deploy` automático no entrypoint | idem |
| Bootstrap | automático somente se o catálogo estiver vazio | **nunca** de novo (catálogo já populado) |

```text
local → PR + CI verde → merge main
      → Dokploy auto-deploy STAGING
      → entrypoint serializa migrate + inventário de schema → smoke
      → (ok) Deploy MANUAL no app de prod (mesmo commit)
      → entrypoint serializa migrate + inventário de schema → smoke prod
```

Com `SIGAPP_RELEASE_ON_STARTUP=true` (default nos dois Compose), o entrypoint
executa `sigapp:deploy` antes de iniciar nginx/PHP/workers. Um lock Redis
serializa réplicas; falha de migration ou drift encerra o container e impede
que código novo sirva sobre schema incompatível.

CI no GitHub (Tests, PostgreSQL+Redis, Pint, PHPStan, Docker build) é gate de
**merge**. O Dokploy não espera o Actions: um push em `main` redeploya staging
mesmo se o CI ainda estiver vermelho. Não mergeie sem checks verdes.

Ticket restante: **SIG-22** Fase 3 (smoke no pipeline e deploy prod por tag/
`workflow_dispatch`).

---

## Arquitetura na VPS

| Recurso | Observação |
|---|---|
| Backend staging | Compose `docker-compose.staging.yml`; target `staging`; porta interna `80` |
| Backend prod | Compose `docker-compose.prod.yml`; target `prod`; porta interna `80` |
| PostgreSQL | Um Compose Dokploy **por ambiente** (`pgvector/pgvector:0.8.6-pg16`); rede externa no backend |
| Redis | Um por ambiente; cache e filas |
| Frontends | Repositórios irmãos — deploys separados; não sobem com este runbook |

O `entrypoint.prod.sh` roda o gate `sigapp:deploy`: ambiente vazio recebe
bootstrap; ambiente existente recebe release central + tenants + inventário.
Os wrappers manuais continuam disponíveis para recuperação operacional.

---

## Descobrir o container `back`

Após recriação da stack o sufixo muda. **Não** rode release em residual de
deploy anterior.

```bash
ssh <user>@<host-da-vps>

docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}' \
  | grep -iE 'sigapp|back'
```

Separar os dois ambientes:

```bash
# Staging — o nome costuma conter "staging"
docker ps --filter 'name=staging' --format '{{.Names}}\t{{.Status}}'

# Prod — referência estável; confirme que está Up
docker ps --filter 'name=sigapp-backend-j8lepv' --format '{{.Names}}\t{{.Status}}'
```

Critérios antes do `docker exec`:

- status **Up** (não `Exited` / container de deploy antigo)
- nome do app certo (staging ≠ `sigapp-backend-j8lepv-…`)
- acabou de ser recriado pelo deploy que você está promovendo

Sanidade inofensiva (troque `CONTAINER` pelo nome descoberto):

```bash
docker exec CONTAINER php artisan --version
docker exec CONTAINER ls -la /usr/local/bin/sigapp-release
```

Anote o nome atual de staging neste runbook quando estabilizar.

---

## Gate automático e diagnóstico manual

Fluxo automático do entrypoint:

1. adquire `deploy:sigapp-schema` no cache compartilhado;
2. executa `sigapp:bootstrap` apenas se o ambiente estiver vazio, senão `sigapp:release`;
3. roda `sigapp:schema-status --fail-on-drift`;
4. cria caches e inicia o supervisor somente após exit code 0.

Para inspecionar depois do deploy:

```bash
docker exec CONTAINER php artisan sigapp:schema-status --json --fail-on-drift
```

**Exit code deve ser 0.** Se falhar, o schema pode estar pela metade —
não ignore; leia o log e trate antes de considerar o ambiente pronto.

`/usr/local/bin/sigapp-release` permanece disponível para recuperação manual,
mas não deve ser necessário no fluxo normal. Não desabilite
`SIGAPP_RELEASE_ON_STARTUP` sem um procedimento externo equivalente.

---

## Depois de cada merge (staging)

```text
1. PR → CI verde
2. Merge na main
3. Dokploy auto-deploy só do app staging
4. Aguardar container passar pelo gate e ficar healthy
5. Consultar `sigapp:schema-status --json --fail-on-drift`
6. Smoke staging
```

```bash
curl -fsS https://api.staging.sigapp.com.br/api/v1/health
```

Confirme no Dokploy que **prod não redeployou** (mesma revisão/hora de antes
do merge).

Smoke mínimo de staging:

- [ ] `sigapp:schema-status --json --fail-on-drift` exit 0
- [ ] `GET /api/v1/health` → HTTP 200
- [ ] Workers e scheduler ativos (`supervisorctl status`)
- [ ] Smoke da feature do PR (login, endpoint, job, etc.)
- [ ] Sem erro novo óbvio nos logs do container

```bash
docker exec CONTAINER_STAGING supervisorctl status
```

---

## Checklist de promoção staging → prod

Use **uma linha por promoção**. Promova o **mesmo commit** que passou no
staging — não “o que estiver na `main` agora” se outro merge entrou no meio.

### 0. Pré-condições

- [ ] CI verde no commit a promover (Actions: Tests, PostgreSQL+Redis, Pint, PHPStan, Docker build)
- [ ] Auto-deploy do app **prod** continua **OFF**
- [ ] Staging isolado (Stripe `sk_test`, DB/Redis/S3/`APP_KEY` ≠ prod)
- [ ] Migration segue expand/contract; imagem anterior tolera o schema expandido
- [ ] (Se o PR tem migration destrutiva) expand/contract já planejado; rollback de imagem **não** desfaz DDL

### 1. Staging — validar a revisão

- [ ] No GitHub: anotar o **SHA** (7+ chars) do merge que vai para prod
- [ ] No Dokploy staging: deploy dessa revisão **terminou** e está healthy
- [ ] SSH: descobrir o container staging ([acima](#descobrir-o-container-back))
- [ ] `docker exec CONTAINER_STAGING php artisan sigapp:schema-status --json --fail-on-drift` → exit 0
- [ ] `curl -fsS https://api.staging.sigapp.com.br/api/v1/health` → 200
- [ ] `docker exec CONTAINER_STAGING supervisorctl status` — nginx, php-fpm, `schedule:work` e as filas `tenant-provisioning`, `ai`, `exports`, `notifications`, `default`
- [ ] Smoke funcional da mudança (login / transfer ticket / endpoint / job)
- [ ] Nenhum merge posterior na `main` que você **não** queira levar junto

Se qualquer item falhar: **não promova**. Corrija em staging (ou reverta o
merge) e recomece.

### 2. Produção — mesmo commit

- [ ] No Dokploy, app **prod**: Deploy **manual** da **mesma revisão/SHA** do passo 1
- [ ] Aguardar recreate, gate automático e healthcheck do compose
- [ ] `curl -fsS https://api.sigapp.com.br/api/v1/health` responde (container no ar)
- [ ] SSH: confirmar o container prod **Up** (`sigapp-backend-j8lepv-back-1` ou o nome atual)
- [ ] `docker exec sigapp-backend-j8lepv-back-1 php artisan sigapp:schema-status --json --fail-on-drift` → exit 0
- [ ] `curl -fsS https://api.sigapp.com.br/api/v1/health` → 200
- [ ] `docker exec sigapp-backend-j8lepv-back-1 supervisorctl status`
- [ ] Smoke da feature em prod
- [ ] Sem erro novo óbvio nos logs de prod

Não execute comandos de recuperação enquanto o container prod ainda estiver
recriando. Nunca rode release manual no container **antigo**.

### 3. Se der errado

1. **App:** no Dokploy prod, redeploy da revisão/imagem anterior.
2. **Schema:** se o `sigapp-release` de prod já aplicou migrations, rollback
   de app **não** desfaz DDL. Trate como incidente de schema (expand/contract
   ou restore planejado).
3. Depois do rollback de app, realinhe código × schema; só rode
   `sigapp-release` de novo se a imagem que ficou ativa tiver as migrations
   corretas para o estado desejado.

---

## Primeiro ambiente (banco vazio)

Somente na **criação inicial** de um ambiente sem schema SIGAPP (hoje: só se
alguém recriar staging do zero):

```bash
docker exec CONTAINER /usr/local/bin/sigapp-bootstrap
```

Isso roda `migrate` + `db:seed` + caches. **Não** use bootstrap em produção já
populada (reexecuta seeders).

---

## O que não fazer

| Ação | Motivo |
|---|---|
| Rodar `migrate` cru fora de `sigapp:deploy` | Ignora lock distribuído e inventário de schema |
| Usar `sigapp-bootstrap` em prod já viva | Seeders em dados reais |
| Desabilitar `SIGAPP_RELEASE_ON_STARTUP` sem gate externo | App novo pode iniciar com schema velho |
| Rodar release em container antigo / stack residual | Migrations da imagem errada |
| Religar auto-deploy de prod | Cada merge volta a ser go-live |
| Promover “o que estiver na main” sem checar SHA | Leva commit não validado em staging |
| Testar restore de backup em cima do DB de prod | Destrutivo |
| Confiar só no rollback de imagem após migration destrutiva | Schema em geral **não** volta com o rollback do app |
| Colar Stripe live / `APP_KEY` / bucket de prod no staging | Não é staging |

---

## Rollback

1. **App:** no Dokploy, redeploy da revisão/imagem anterior do compose.
2. **Schema:** se `sigapp-release` já aplicou migrations, rollback de app **não**
   desfaz DDL. Mudanças destrutivas exigem estratégia expand/contract e, se
   necessário, restore de backup planejado.
3. Após qualquer rollback de app, reavalie se o schema e o código estão
   alinhados; se preciso, rode `sigapp-release` de novo na imagem que ficou ativa
   (só se essa imagem contiver as migrations corretas para o estado desejado).

Ensaie o rollback de **app** em staging antes de precisar em prod.

---

## Filas e manutenção

Antes de manutenção de Redis ou troca de topologia de workers:

1. Reduza escritas HTTP / scheduler se possível.
2. `php artisan queue:restart` **dentro** do container (sinaliza workers).
3. Aguarde drenagem das filas; não use `SIGKILL` nem apague chaves `queues:*`.
4. `retry_after=660` > timeout máximo de Job (600s).

```bash
docker exec CONTAINER php artisan queue:restart
docker exec CONTAINER supervisorctl status
```

---

## Variáveis e rede

- Catálogo prod: `.env.production.example` (sem segredos reais).
- Secrets só no Dokploy (runtime), nunca como build-arg e nunca no Git.
- Se o **projeto Compose do PostgreSQL de prod** mudar no Dokploy, atualize o
  nome da rede externa em `docker-compose.prod.yml` (hoje: `sigapp-database-wlnxuu_default`).
- Staging: o backend **não** cria a rede do banco. Suba o Postgres staging no
  Dokploy primeiro, anote o nome em `docker network ls` e defina
  `DATABASE_DOCKER_NETWORK` no app de staging (`docker-compose.staging.yml`).
- Login tenant em staging (`/{slug}.frontend…/login/callback`): o frontend
  precisa receber o **wildcard** do mesmo `APP_DOMAIN`/`NEXT_PUBLIC_APP_DOMAIN`
  (ex. `*.sigapp-frontend-….sslip.io`). Sem isso o Traefik responde 404 no
  subdomínio mesmo com o ticket válido. No compose do tenant, o
  `HostRegexp` usa `${APP_DOMAIN}`.

---

## Próximo (SIG-22 Fase 3+)

Operação atual = Modelo A do SIG-22 (Dokploy decide o deploy; release/smoke
semi-manuais). Ainda falta no ticket:

- smoke health automático após deploy
- deploy prod por tag `v*` ou `workflow_dispatch` com CI verde
- alerta de falha + rollback versionado no pipeline

---

## Referências

- `docs/2026-08-07-processo-release.md` — bootstrap vs release vs entrypoint
- `docs/2026-08-08-plano-staging-dokploy.md` — plano histórico do cutover (não é o runbook)
- `docker-compose.prod.yml` / `docker-compose.staging.yml`
- `.docker/release.prod.sh`, `.docker/bootstrap.prod.sh`, `.docker/entrypoint.prod.sh`
- `.github/workflows/ci.yml` — gates antes do merge
- Ticket **SIG-22** — CI/CD (Fase 2 = este cenário; Fase 3 = automação)
- Frontends e banco: runbook `docs/deploy-dokploy.md` em cada repositório irmão
