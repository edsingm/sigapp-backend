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

## Estado atual — CI/CD controlado pelo GitHub Actions

O GitHub Actions é a única porta automática de deploy. O auto-deploy nativo do
Dokploy fica **OFF em staging e produção**; um push nunca deve contornar os
gates do CI.

| | Staging | Produção |
|---|---|---|
| Compose | `docker-compose.staging.yml` | `docker-compose.prod.yml` |
| Imagem padrão | `ghcr.io/edsingm/sigapp-backend:staging` | `ghcr.io/edsingm/sigapp-backend:production` |
| Origem imutável | `ghcr.io/edsingm/sigapp-backend:<SHA>` | o mesmo SHA validado em staging |
| Gatilho | push na `main`, depois de todos os gates | `Deploy Production` via `workflow_dispatch` |
| Auto-deploy Dokploy | **OFF** | **OFF** |
| API | `https://api.staging.sigapp.com.br` | `https://api.sigapp.com.br` |
| Stripe | test | live |
| Sentry | ambiente `staging`, tracing 100% | ambiente `production`, tracing 10% |
| DB / Redis / S3 / APP_KEY | isolados | isolados |
| Gate de schema | `sigapp:deploy` no entrypoint | idem |

```text
PR → CI verde → revisão humana → merge
   → CI testa novamente a main e publica GHCR:<SHA>
   → promove o mesmo manifest para :staging
   → API do Dokploy recria staging
   → readiness confirma dependências + APP_REVISION=<SHA>
   → workflow Deploy Production recebe esse SHA manualmente
   → confirma que staging ainda executa o SHA
   → promove o mesmo manifest para :production
   → API do Dokploy recria produção
   → readiness de produção confirma o SHA
```

A imagem é construída uma única vez. Os aliases `staging` e `production`
são referências mutáveis para manifests já existentes; o artefato canônico é
sempre a tag completa de 40 caracteres do commit.

Com `SIGAPP_RELEASE_ON_STARTUP=true`, o entrypoint executa `sigapp:deploy`
antes de iniciar nginx/PHP/workers. Um lock Redis serializa réplicas; falha de
migration ou drift encerra o container. `/api/v1/health/ready` expõe
`revision`, gravada na imagem por `APP_REVISION`, para o smoke não aceitar
a versão anterior ainda saudável.

## Configuração única no GitHub e no Dokploy

### GitHub Environments

Crie `staging` e `production` em **Settings → Environments**.

Nos dois environments:

| Tipo | Nome | Valor |
|---|---|---|
| Variable | `DOKPLOY_URL` | URL base da instância, sem `/api` |
| Variable | `DOKPLOY_COMPOSE_ID` | ID do Compose daquele ambiente |
| Variable | `HEALTH_URL` | URL base pública da API, sem `/api/v1/health/ready` |
| Secret | `DOKPLOY_API_TOKEN` | token da API do Dokploy |

No environment `production`, adicione também:

| Tipo | Nome | Valor |
|---|---|---|
| Variable | `STAGING_HEALTH_URL` | `https://api.staging.sigapp.com.br` |

O workflow de produção já é manual. Para dev solo, não configure uma aprovação
que proíba self-review; use o botão **Run workflow** como decisão explícita de
promoção.

### GitHub Actions e GHCR

- Permita ao `GITHUB_TOKEN` publicar packages; o workflow limita o job a
  `contents: read` e `packages: write`.
- O package `ghcr.io/edsingm/sigapp-backend` pode permanecer privado.
- Actions externas ficam fixadas por SHA completo. PRs periódicos de versão do
  Dependabot ficam desabilitados; alerts e security updates permanecem ativos,
  enquanto atualizações comuns entram em manutenção periódica planejada.
- Proteja `main` por ruleset, exigindo PR e os checks `Tests`,
  `PostgreSQL + Redis`, `Code Style`, `PHPStan` e
  `Docker Build (prod)`. Não exija aprovação de outro usuário para o fluxo
  solo.

### Acesso do Dokploy ao GHCR

Configure no host/Dokploy uma credencial de registry com acesso somente de
leitura ao package privado (`read:packages`). O token de registry não vai para
o GitHub Actions nem para o Compose. Confirme que os dois Composes conseguem
fazer pull antes de desativar o fluxo antigo.

### Sentry

Configure `SENTRY_LARAVEL_DSN` como secret nos dois Composes do Dokploy. O mesmo
projeto Sentry pode atender ambos: o Compose fixa `SENTRY_ENVIRONMENT` como
`staging` ou `production`, permitindo filtros e alertas separados. O DSN é
obrigatório e sua ausência interrompe a interpolação do Compose antes do deploy.

Não configure `SENTRY_RELEASE` normalmente. O backend usa `APP_REVISION`, gravado
na imagem pelo GitHub Actions com o SHA completo, como release do Sentry. Assim,
staging e produção associam eventos ao mesmo artefato imutável promovido.

Os defaults operacionais são: 100% dos erros nos dois ambientes, 100% das
transações em staging e 10% em produção. `SENTRY_SEND_DEFAULT_PII`, bindings SQL
e tracing das interações de IA ficam desabilitados por LGPD. Não os habilite sem
revisão de privacidade específica.

### Ordem segura de ativação

1. Configure environments, variables e secrets no GitHub.
2. Configure a credencial GHCR no Dokploy.
3. Desligue **Auto Deploy** nos Composes de staging e produção **antes do merge**.
4. Abra o PR desta mudança e deixe os cinco checks passarem.
5. Faça o merge; o primeiro push criará `<SHA>` e `staging`, depois acionará
   o Dokploy.
6. Só depois de staging verde execute `Deploy Production` com o SHA completo.

## Arquitetura na VPS

| Recurso | Observação |
|---|---|
| Backend staging | Compose consome alias GHCR `staging`; porta interna `80` |
| Backend prod | Compose consome alias GHCR `production`; porta interna `80` |
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

O fluxo é automático:

1. o push da `main` repete todos os gates;
2. o build publica somente `ghcr.io/edsingm/sigapp-backend:<SHA>`;
3. depois de todos os gates verdes, o manifest recebe o alias `staging`;
4. o Actions chama `POST /api/compose.deploy`;
5. o Dokploy faz pull e recria o Compose;
6. o entrypoint executa release/inventário de schema;
7. o Actions consulta readiness até obter `status=ok` e o SHA esperado.

No GitHub, o job `Deploy Staging` verde é a evidência do deploy e do smoke.
Se falhar, produção não deve ser promovida. O endpoint pode ser conferido
manualmente:

```bash
curl -fsS https://api.staging.sigapp.com.br/api/v1/health/ready
```

## Checklist de promoção staging → prod

1. Abra **Actions → Deploy Production → Run workflow**.
2. Informe o SHA completo de 40 caracteres mostrado no job `Deploy Staging`.
3. O workflow valida que o commit existe e pertence à `main`.
4. Ele exige que o readiness de staging esteja saudável e exponha exatamente
   esse SHA.
5. O manifest imutável `<SHA>` recebe o alias `production`.
6. O Actions aciona o Compose de produção no Dokploy.
7. O entrypoint executa `sigapp:deploy`; o smoke só passa quando readiness
   confirma o mesmo SHA.

Antes de clicar:

- [ ] `Deploy Staging` está verde para o SHA.
- [ ] Smoke funcional da feature foi feito em staging.
- [ ] Não há incidente ativo nem migration destrutiva sem expand/contract.
- [ ] Backup/restore e rollback aplicáveis à mudança são conhecidos.
- [ ] O auto-deploy nativo do Dokploy continua OFF.

Depois:

- [ ] `Deploy Production` ficou verde.
- [ ] `GET /api/v1/health/ready` retorna `status=ok` e o SHA promovido.
- [ ] Login, endpoint/job alterado e logs não mostram regressão.
- [ ] Workers e scheduler permanecem ativos.

Se staging mudou depois do SHA informado, o workflow falha antes de alterar o
alias de produção. Se o deploy de produção falhar depois da promoção do alias,
o container anterior pode continuar servindo, mas o incidente precisa ser
tratado; não considere a tag `production` como evidência de deploy concluído.

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
| Ligar Auto Deploy no Dokploy | Contorna os gates e cria corrida com o Actions |
| Usar webhook de push direto no Dokploy | Mesmo problema: deploy começa antes do CI terminar |
| Recompilar em produção | Produção deixaria de receber os mesmos bytes testados |
| Promover SHA diferente do readiness de staging | O workflow bloqueia para impedir artefato não testado |
| Rodar `migrate` cru | Ignora lock e inventário de schema de `sigapp:deploy` |
| Usar `sigapp-bootstrap` em ambiente existente | Reexecuta seeders |
| Colocar Stripe/DB/S3/APP_KEY no GitHub | Esses secrets pertencem somente ao runtime do Dokploy |
| Expor o token do Dokploy em YAML/log | Permite deploy remoto não autorizado |
| Confiar no rollback da imagem para desfazer DDL | Migration aplicada não é revertida pela troca de imagem |

## Rollback

Cada imagem por SHA permanece endereçável no GHCR. Para rollback emergencial
de aplicação:

1. identifique o último SHA saudável no histórico de deployments;
2. no Compose de produção, defina temporariamente
   `SIGAPP_IMAGE=ghcr.io/edsingm/sigapp-backend:<SHA_ANTERIOR>`;
3. acione o deploy no Dokploy e confirme readiness/revision;
4. trate o schema separadamente — trocar imagem não desfaz migrations;
5. após resolver o incidente, remova o override `SIGAPP_IMAGE` antes da
   próxima promoção normal.

Ensaie esse procedimento em staging. Não use apenas “redeploy anterior” se ele
referenciar o alias mutável `production`, pois o alias pode já apontar para a
imagem problemática.

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

## Variáveis, registry e rede

- Catálogo de runtime: `.env.production.example`; secrets da aplicação ficam
  somente no Dokploy.
- `APP_REVISION` é gravada no build pelo Actions; não sobrescreva no Compose.
- `SENTRY_LARAVEL_DSN` é obrigatório nos dois Composes; mantenha o valor somente
  no Dokploy. Ambiente e amostragem têm defaults seguros definidos no Compose.
- `SIGAPP_IMAGE` é um override operacional para rollback. Normalmente fica
  ausente e cada Compose usa seu alias padrão.
- Staging e produção precisam de acesso de leitura ao GHCR privado.
- Se o projeto PostgreSQL de produção mudar, atualize a rede externa em
  `docker-compose.prod.yml`.
- Staging exige `DATABASE_DOCKER_NETWORK` e as redes externas
  `dokploy-network` e `sigapp-staging`.
- O token da API do Dokploy usado pelo Actions deve ter o menor escopo
  disponível e ser rotacionado periodicamente.

## Próximas melhorias

- automatizar alerta de falha de deploy;
- criar workflow explícito de rollback por SHA com confirmação;
- publicar SBOM/attestation da imagem;
- adicionar smoke autenticado de baixo risco além do readiness.

## Referências

- `docs/2026-08-07-processo-release.md` — bootstrap vs release vs entrypoint
- `docs/2026-08-08-plano-staging-dokploy.md` — plano histórico do cutover (não é o runbook)
- `docker-compose.prod.yml` / `docker-compose.staging.yml`
- `.docker/release.prod.sh`, `.docker/bootstrap.prod.sh`, `.docker/entrypoint.prod.sh`
- `.github/workflows/ci.yml` — gates, publicação por SHA e deploy de staging
- `.github/workflows/deploy-production.yml` — promoção manual do mesmo SHA
- Frontends e banco: runbook `docs/deploy-dokploy.md` em cada repositório irmão
