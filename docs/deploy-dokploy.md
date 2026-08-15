# Deploy do SIGAPP no Dokploy

Runbook operacional do backend na VPS gerenciada pelo **Dokploy**.  
O processo de scripts (`sigapp-bootstrap` / `sigapp-release` / entrypoint) está em
`docs/2026-08-07-processo-release.md`. Os binários delegam a `php artisan sigapp:bootstrap`
e `php artisan sigapp:release`. Este arquivo cobre **plataforma, ordem
de deploy e comandos copy-paste**.

> O runbook legado `docs/deploy-coolify.md` está **obsoleto**. Coolify não é
> mais usado. Em caso de divergência, este documento e o `AGENTS.md` prevalecem.

---

## Estado atual (produção) — Cenário A

| Item | Valor |
|---|---|
| Plataforma | Dokploy |
| Tipo de deploy | Docker Compose (`docker-compose.prod.yml`) |
| Branch | `main` |
| Auto-deploy | **ON** → cada merge/push em `main` redeploya **produção** |
| API | `https://api.sigapp.com.br` |
| Stripe | **produção** (`sk_live` / prices live) |
| PostgreSQL / Redis | Serviços na rede Docker do Dokploy (não expostos na internet) |
| Service Compose | `back` |
| Container backend (referência) | `sigapp-backend-j8lepv-back-1` |
| Staging isolado | **Ainda não existe** |

### Risco deste cenário

```text
merge main → Dokploy sobe app NOVO → schema ainda VELHO até o SSH
```

Enquanto `sigapp-release` não rodar no container novo, produção pode servir
código que depende de migrations ainda não aplicadas. Com Stripe live e dados
reais de tenants, isso é o maior risco operacional do backend.

**Mitigação imediata (enquanto não houver staging):**

1. Branch protection + CI verde obrigatórios na `main` (já em vigor).
2. Ninguém mergeia migration/feature sensível sem alguém disponível para o
   release SSH **na sequência** do deploy.
3. Após todo deploy: healthy → `sigapp-release` → smoke (seção abaixo).
4. **Alvo (Fase 2 do SIG-13):** auto-deploy de `main` só em staging; prod
   sem auto-deploy a cada merge.

---

## Arquitetura na VPS

| Recurso | Observação |
|---|---|
| Backend | Compose Dokploy; service `back`; porta interna `80` (proxy do Dokploy) |
| PostgreSQL | Banco gerenciado do Dokploy (`pgvector/pgvector:0.8.6-pg16`); backend usa rede externa (ver `docker-compose.prod.yml`) |
| Redis | Gerenciado no Dokploy; cache e filas |
| Frontends | Repositórios irmãos (tenant / site / admin) — deploys separados |

O `entrypoint.prod.sh` **não** roda migrations. Releases seguintes usam
`sigapp-release`. Primeiro ambiente vazio usa `sigapp-bootstrap` **uma vez**.

---

## Fluxo de cada release em produção

```text
1. PR → CI verde (Tests, PostgreSQL+Redis, Pint, PHPStan, Docker build)
2. Merge na main
3. Dokploy auto-deploy (compose rebuild/redeploy)
4. Aguardar container healthy (GET /api/v1/health)
5. SSH na VPS → sigapp-release no container back
6. Smoke (health + checagens da mudança)
```

### 1–2. Código e CI

- Só mergear com checks required verdes.
- Não usar `git add .` sem revisar o worktree.
- O Dokploy só vê o que já está no remoto na branch configurada (`main`).

### 3–4. Aguardar o deploy

No painel Dokploy, confirme que o deploy terminou e o healthcheck passou.

Smoke externo rápido:

```bash
curl -fsS https://api.sigapp.com.br/api/v1/health
```

Não rode `sigapp-release` enquanto o container ainda estiver recreando.

### 5. `sigapp-release` via SSH (comando oficial)

```bash
ssh <user>@<host-da-vps>

# Preferir o nome estável documentado:
docker exec sigapp-backend-j8lepv-back-1 /usr/local/bin/sigapp-release
```

O script executa, nesta ordem:

1. `php artisan migrate --force` (central)
2. `php artisan tenants:migrate`
3. `config:cache` / `route:cache` / `view:cache`

**Exit code deve ser 0.** Se falhar, o schema pode estar pela metade —
não ignore; leia o log do comando e trate antes de considerar o deploy pronto.

#### Se o nome do container mudou

Após recriação da stack o sufixo pode mudar. Descobrir o container atual:

```bash
docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}' | grep -i back
# ou
docker ps --filter 'name=sigapp-backend' --format '{{.Names}}'
```

Confirme que é o container **Up** do backend (não um residual de deploy
anterior) e use esse nome no `docker exec`.

Comando inofensivo de sanidade antes do release:

```bash
docker exec sigapp-backend-j8lepv-back-1 php artisan --version
docker exec sigapp-backend-j8lepv-back-1 ls -la /usr/local/bin/sigapp-release
```

### 6. Smoke pós-release

```bash
curl -fsS https://api.sigapp.com.br/api/v1/health
```

Dentro do container (opcional, workers/scheduler):

```bash
docker exec sigapp-backend-j8lepv-back-1 supervisorctl status
```

Checklist genérico:

- [ ] `sigapp-release` terminou com exit 0
- [ ] `GET /api/v1/health` → HTTP 200
- [ ] Workers (`tenant-provisioning`, `ai`, `exports`, `notifications`, `default`) e scheduler ativos
- [ ] Smoke da feature do PR (login, endpoint alterado, job, etc.)
- [ ] Sem erro novo óbvio nos logs do container

---

## Primeiro ambiente (banco vazio)

Somente na **criação inicial** de um ambiente sem schema SIGAPP:

```bash
docker exec sigapp-backend-j8lepv-back-1 /usr/local/bin/sigapp-bootstrap
```

Isso roda `migrate` + `db:seed` + caches. **Não** use bootstrap em produção já
populada (reexecuta seeders).

---

## O que não fazer

| Ação | Motivo |
|---|---|
| Rodar migrate no `entrypoint` | Restart/scale reexecuta; corrida entre réplicas |
| Usar `sigapp-bootstrap` em prod já viva | Seeders em dados reais |
| Esquecer `sigapp-release` após merge com migration | App novo + schema velho |
| Rodar release em container antigo / stack residual | Migrations da imagem errada |
| Testar restore de backup em cima do DB de prod | Destrutivo |
| Confiar só no rollback de imagem após migration destrutiva | Schema em geral **não** volta com o rollback do app |

---

## Rollback

1. **App:** no Dokploy, redeploy da revisão/imagem anterior do compose.
2. **Schema:** se `sigapp-release` já aplicou migrations, rollback de app **não**
   desfaz DDL. Mudanças destrutivas exigem estratégia expand/contract e, se
   necessário, restore de backup planejado.
3. Após qualquer rollback de app, reavalie se o schema e o código estão
   alinhados; se preciso, rode `sigapp-release` de novo na imagem que ficou ativa
   (só se essa imagem contiver as migrations corretas para o estado desejado).

---

## Filas e manutenção

Antes de manutenção de Redis ou troca de topologia de workers:

1. Reduza escritas HTTP / scheduler se possível.
2. `php artisan queue:restart` **dentro** do container (sinaliza workers).
3. Aguarde drenagem das filas; não use `SIGKILL` nem apague chaves `queues:*`.
4. `retry_after=660` > timeout máximo de Job (600s).

```bash
docker exec sigapp-backend-j8lepv-back-1 php artisan queue:restart
docker exec sigapp-backend-j8lepv-back-1 supervisorctl status
```

---

## Variáveis e rede

- Catálogo: `.env.production.example` (sem segredos reais).
- Secrets só no Dokploy (runtime), nunca como build-arg e nunca no Git.
- Se o **projeto Compose do PostgreSQL** mudar no Dokploy, atualize o nome da
  rede externa em `docker-compose.prod.yml` (hoje: `sigapp-database-wlnxuu_default`).
- Staging: o backend **não** cria a rede do banco. Suba o Postgres staging no
  Dokploy primeiro, anote o nome em `docker network ls` e defina
  `DATABASE_DOCKER_NETWORK` no app de staging (`docker-compose.staging.yml`).

---

## Caminho para Cenário B (staging) — próximo estrutural

Objetivo: `main` auto-deploy **não** bater em produção.

| Passo | Descrição |
|---|---|
| 1 | Criar app/stack **staging** no Dokploy (mesmo compose, envs isolados) |
| 2 | DB, Redis, S3, Stripe **test**, Resend sandbox, `APP_URL` de staging |
| 3 | Auto-deploy de `main` → **somente** staging |
| 4 | Produção: auto-deploy **OFF** ou só tag/`workflow_dispatch` / botão manual |
| 5 | Mesmo runbook de `sigapp-release`, com nome de container/domínio de staging |

Até isso existir, trate **todo merge na `main` como go-live**.

---

## Referências

- `docs/2026-08-07-processo-release.md` — bootstrap vs release vs entrypoint
- `docker-compose.prod.yml` — serviço `back`, healthcheck, rede do Postgres
- `.docker/release.prod.sh`, `.docker/bootstrap.prod.sh`, `.docker/entrypoint.prod.sh`
- `.github/workflows/ci.yml` — gates antes do merge
- Ticket **SIG-13** — plano CI/CD completo (staging, automação do release, registry)
