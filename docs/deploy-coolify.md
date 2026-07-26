# Deploy do SIGAPP no Coolify

Runbook do primeiro deploy do SIGAPP em uma VPS gerenciada pelo Coolify. O
cenário considerado é um banco de produção vazio, Traefik como proxy, quatro
aplicações separadas e PostgreSQL/Redis gerenciados pelo Coolify.

## Arquitetura de produção

| Recurso | Repositório | Serviço Compose | Porta interna | Domínios |
| --- | --- | --- | --- | --- |
| Backend | `sigapp-backend` | `back` | `80` | `api.sigapp.com.br` |
| Frontend tenant | `sigapp` | `front` | `3000` | `app.sigapp.com.br` e `*.sigapp.com.br` |
| Site | `sigapp-front_site` | `front` | `3000` | `sigapp.com.br` e `www.sigapp.com.br` |
| Admin | `sigapp-front_admin` | `front` | `3000` | `admin.sigapp.com.br` |
| PostgreSQL | Recurso gerenciado | - | `5432` | Somente rede privada |
| Redis | Recurso gerenciado | - | `6379` | Somente rede privada |

O repositório `sigapp-database` contém um Compose alternativo para banco
autogerenciado. Não o implante quando usar o PostgreSQL gerenciado do Coolify.

## 1. Publicar o código aprovado

O Coolify só consegue implantar alterações disponíveis no Git remoto. Em cada
repositório, revise e envie apenas os arquivos aprovados:

```bash
git status
git diff --check
git add <arquivos-aprovados>
git commit -m "Prepare production deployment for Coolify"
git push origin main
```

Não use `git add .` sem revisar o worktree. Confirme no GitHub que os commits
estão na branch que será configurada no Coolify.

## 2. Preparar DNS, firewall e TLS

Crie os registros DNS:

| Tipo | Nome | Destino |
| --- | --- | --- |
| `A` | `@` | IP público da VPS |
| `A` | `*` | IP público da VPS |
| `CNAME` | `www` | `sigapp.com.br` |

O registro wildcard cobre `app`, `api`, `admin` e os tenants. Registros `A`
explícitos para esses três hosts são opcionais. Não coloque portas no DNS.

No firewall:

- libere TCP `80` e `443`;
- restrinja o acesso SSH `22`;
- não exponha PostgreSQL `5432` nem Redis `6379`.

O certificado de `*.sigapp.com.br` exige DNS challenge. Em
`Servers > servidor > Proxy`, configure o Traefik com o provider DNS que
gerencia o domínio, salve e reinicie o proxy. Se o DNS estiver na Hostinger,
use o modelo Hostinger da
[documentação de DNS challenge](https://coolify.io/docs/knowledge-base/proxy/traefik/dns-challenge).
Faça backup da configuração atual do proxy antes de alterá-la.

## 3. Criar projeto, ambiente e destino

1. Crie o projeto `SIGAPP` no Coolify.
2. Crie o ambiente `production`.
3. Selecione a VPS da Hostinger.
4. Use o mesmo `Destination` para banco, Redis e as quatro aplicações.

Em cada aplicação baseada em Docker Compose, habilite
`Connect to Predefined Network`. Stacks Compose são isolados por padrão; essa
opção permite a comunicação entre recursos. Use os hostnames completos que o
Coolify gerar, como `postgres-<uuid>`, `redis-<uuid>` e `back-<uuid>`.

Referência: [rede no Docker Compose do Coolify](https://coolify.io/docs/knowledge-base/docker/compose#connect-to-predefined-networks).

## 4. Criar o PostgreSQL

1. No ambiente `production`, selecione `New Resource > PostgreSQL` ou crie
   um recurso compatível com a imagem oficial
   `pgvector/pgvector:0.8.2-pg18`.
2. Use PostgreSQL 18 com `pgvector` disponível e o nome `sigapp-postgres`.
3. Configure banco e usuário:

   ```text
   database: sigapp
   username: sigapp
   password: uma senha longa e exclusiva
   ```

4. Habilite persistência.
5. Não configure porta pública.
6. Implante e aguarde o recurso ficar saudável.
7. Copie a URL interna exibida pelo Coolify.

Separe os dados da URL interna para configurar o backend:

```dotenv
DB_HOST=postgres-UUID
DB_PORT=5432
DB_DATABASE=sigapp
DB_USERNAME=sigapp
DB_PASSWORD=SENHA_DO_POSTGRES
```

Antes do bootstrap, confirme que o pacote da extensão está disponível:

```sql
SELECT name, default_version, installed_version
FROM pg_available_extensions
WHERE name = 'vector';
```

A migration tenant executa `CREATE EXTENSION IF NOT EXISTS vector` e cria um
índice HNSW de cosseno em cada schema. O usuário de release precisa ter
permissão para instalar a extensão na primeira execução. A ausência do pacote
ou dessa permissão interrompe a migration intencionalmente, pois a busca
vetorial em PostgreSQL depende do `pgvector`; o fallback em memória existe
somente para drivers de desenvolvimento/teste, como SQLite.

## 5. Criar o Redis

1. Selecione `New Resource > Redis`.
2. Use o nome `sigapp-redis` e gere uma senha exclusiva.
3. Não configure porta pública.
4. Implante e copie o hostname e a senha da URL interna.

```dotenv
REDIS_HOST=redis-UUID
REDIS_PORT=6379
REDIS_PASSWORD=SENHA_DO_REDIS
QUEUE_CONNECTION=redis
REDIS_QUEUE_RETRY_AFTER=660
REDIS_QUEUE_BLOCK_FOR=5
```

O Compose de produção usa Redis para cache e filas. Ative persistência
durável (AOF e/ou snapshots com retenção adequada) antes de liberar o ambiente:
reiniciar ou recriar o Redis não pode descartar jobs pendentes.

O `retry_after` de 660 segundos precisa permanecer acima do maior timeout de
Job, atualmente 600 segundos. A produção mantém workers separados para
`tenant-provisioning`, `ai`, `exports`, `notifications` e `default`; ajuste a
concorrência com `QUEUE_<NOME>_PROCESSES`, sempre usando inteiros a partir de 1.

O Redis de cache também é o coordenador dos locks do scheduler e dos Jobs
únicos. Todas as réplicas podem manter `schedule:work` ativo: os eventos
nomeados de `routes/console.php` usam `onOneServer()` e
`withoutOverlapping()`. Por isso, todas as réplicas precisam apontar para o
mesmo Redis/prefixo de aplicação; não use cache local ou Redis isolado por
réplica em produção.

## 6. Criar o backend

1. Selecione `New Resource`.
2. Escolha GitHub App ou Deploy Key para repositório privado.
3. Selecione `edsingm/sigapp-backend` e a branch `main`.
4. Escolha o build pack `Docker Compose`.
5. Informe `/docker-compose.prod.yml` como Compose file.
6. Habilite `Connect to Predefined Network`.
7. Não habilite `Raw Compose Deployment`.
8. Ainda não execute o deploy.

No serviço `back`, configure o domínio:

```text
https://api.sigapp.com.br
```

O backend escuta na porta interna `80`; não é necessário acrescentá-la ao
domínio.

### Variáveis do backend

Use `.env.production.example` como fonte. No `Developer View` do Coolify,
copie as variáveis, remova comentários e substitua todos os placeholders.

Gere uma chave exclusiva sem salvá-la no Git:

```bash
php artisan key:generate --show
```

Os valores principais devem ficar assim:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.sigapp.com.br
APP_DOMAIN=sigapp.com.br

DB_HOST=postgres-UUID
DB_PORT=5432
DB_DATABASE=sigapp
DB_USERNAME=sigapp
DB_PASSWORD=SENHA_DO_POSTGRES

REDIS_HOST=redis-UUID
REDIS_PORT=6379
REDIS_PASSWORD=SENHA_DO_REDIS

FRONTEND_URL=https://app.sigapp.com.br
LANDING_URL=https://sigapp.com.br
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=IP_OU_CIDR_DA_REDE_DO_TRAEFIK
```

Identifique o hostname interno exato do serviço `back`, normalmente
`back-<uuid>`, e inclua-o nos domínios centrais:

```dotenv
CENTRAL_DOMAINS=sigapp.com.br,api.sigapp.com.br,app.sigapp.com.br,back-UUID
```

Em `TRUSTED_PROXIES`, informe somente o IP ou CIDR efetivo da rede pela qual
o Traefik alcança o backend. Não use `*`: aceitar proxies arbitrários permite
forjar headers `X-Forwarded-*`. Sem esse valor, o backend ignora esses headers
por padrão.

Também são obrigatórios valores reais para:

- S3: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, região e bucket;
- Resend: `RESEND_API_KEY` e remetente;
- Stripe: chave pública, secret e signing secret do webhook;
- administrador central: nome, e-mail e senha.

Use chaves de teste do Stripe até concluir os smoke tests. IA e Google Maps no
backend são opcionais. Segredos do backend devem ser somente runtime, nunca
Build Variables.

## 7. Implantar e inicializar o backend

1. Execute `Deploy`.
2. Acompanhe o build e os logs do container.
3. Não prossiga se houver falha de banco, Redis, healthcheck ou variável
   obrigatória.
4. Depois que o backend estiver rodando, abra o Terminal do container `back`.
5. Execute uma única vez:

   ```bash
   /usr/local/bin/sigapp-bootstrap
   ```

O bootstrap executa migrations centrais, seeders e caches de produção. Não o
adicione ao entrypoint e não o execute em todo restart.

Valide no mesmo terminal:

```bash
cd /var/www
php artisan migrate:status
supervisorctl status
```

Nginx, PHP-FPM, os cinco grupos de workers e o scheduler devem aparecer como
`RUNNING`. Em seguida, teste externamente:

```bash
curl -fsS https://api.sigapp.com.br/api/health
```

O comando deve encerrar com status zero e a API deve responder HTTP 200.

## 8. Criar os frontends

Repita a criação de recurso para cada frontend:

| Aplicação | Repositório |
| --- | --- |
| Tenant | `edsingm/sigapp` |
| Site | `edsingm/sigapp-front_site` |
| Admin | `edsingm/sigapp-front_admin` |

Em todos eles:

1. use a branch `main`;
2. escolha o build pack `Docker Compose`;
3. informe `/docker-compose.prod.yml`;
4. habilite `Connect to Predefined Network`;
5. mantenha `Raw Compose Deployment` desabilitado.

### Frontend tenant

Copie o `.env.production.example` do repositório tenant:

```dotenv
API_URL=http://back-UUID
APP_DOMAIN=sigapp.com.br
NEXT_PUBLIC_API_URL=https://api.sigapp.com.br
NEXT_PUBLIC_APP_DOMAIN=sigapp.com.br
NEXT_PUBLIC_GOOGLE_MAPS_API_KEY=SUA_CHAVE
NEXT_PUBLIC_SITE_URL=https://sigapp.com.br
```

No serviço `front`, configure:

```text
https://app.sigapp.com.br:3000
```

### Site

```dotenv
API_URL=http://back-UUID
NEXT_PUBLIC_API_URL=https://api.sigapp.com.br
NEXT_PUBLIC_APP_URL=https://app.sigapp.com.br
NEXT_PUBLIC_SITE_URL=https://sigapp.com.br
NEXT_PUBLIC_GA4_ID=
NEXT_PUBLIC_META_PIXEL_ID=
```

Domínios do serviço `front`:

```text
https://sigapp.com.br:3000,https://www.sigapp.com.br:3000
```

### Admin

```dotenv
API_URL=http://back-UUID
APP_DOMAIN=admin.sigapp.com.br
NEXT_PUBLIC_API_URL=https://api.sigapp.com.br
NEXT_PUBLIC_APP_DOMAIN=admin.sigapp.com.br
NEXT_PUBLIC_SITE_URL=https://sigapp.com.br
```

Domínio do serviço `front`:

```text
https://admin.sigapp.com.br:3000
```

Nos frontends, marque todas as variáveis `NEXT_PUBLIC_*` como Build Variable e
Runtime Variable. `API_URL` e outras variáveis privadas devem ser somente
runtime. A porta `:3000` no campo de domínio informa apenas o destino interno;
o acesso público continua em HTTPS 443.

Referências:

- [domínios no Coolify](https://coolify.io/docs/knowledge-base/domains);
- [variáveis de build e runtime](https://coolify.io/docs/knowledge-base/environment-variables).

## 9. Configurar wildcard dos tenants

O frontend tenant precisa receber hosts dinâmicos como
`empresa.sigapp.com.br`. Para isso, configure no Traefik:

1. certificado válido para `*.sigapp.com.br` via DNS challenge;
2. regra `HostRegexp` direcionada ao serviço tenant `front` na porta `3000`;
3. prioridade que não sobreponha as rotas exatas de `api`, `admin`, `app` e
   `www`.

Os nomes das labels contêm o UUID do recurso. Edite os `Container Labels` do
recurso tenant preservando as labels geradas pelo Coolify; não cole labels
genéricas por cima do conjunto inteiro.

Antes de prosseguir, valide a separação:

```text
teste.sigapp.com.br  -> frontend tenant
api.sigapp.com.br    -> backend
admin.sigapp.com.br  -> frontend admin
sigapp.com.br        -> site
```

Referência: [multitenancy e HostRegexp no Coolify](https://coolify.io/docs/knowledge-base/domains#catch-multiple-domains).

## 10. Configurar serviços externos

### Google Maps

Restrinja a chave pelos HTTP referrers:

```text
https://app.sigapp.com.br/*
https://*.sigapp.com.br/*
```

Habilite somente as APIs utilizadas pelo projeto.

### Stripe

Comece em modo de teste e configure o endpoint:

```text
https://api.sigapp.com.br/api/v1/webhook/stripe
```

Copie o signing secret para `STRIPE_WEBHOOK_SECRET` e reinicie o backend se a
variável for alterada.

### Resend

Verifique o domínio remetente. Se utilizar o webhook do Resend, configure:

```text
https://api.sigapp.com.br/resend/webhook
```

Teste o envio pelo terminal do backend:

```bash
php artisan mail:test email@exemplo.com
```

### S3

Use preferencialmente um bucket exclusivo para arquivos da aplicação. Teste
upload, leitura e exclusão. Separe esse bucket do bucket de backups e use
credenciais com permissões mínimas.

## 11. Smoke tests antes do go-live

### Infraestrutura

```bash
curl -fsS https://api.sigapp.com.br/api/health
curl -I https://sigapp.com.br
curl -I https://admin.sigapp.com.br/login
curl -I https://app.sigapp.com.br/login
curl -I https://teste.sigapp.com.br/login
```

Não prossiga com respostas `502`, `504`, certificado inválido ou healthcheck
falhando.

Com um token Sanctum de administrador central, consulte o relatório detalhado:

```bash
curl -fsS \
  -H "Authorization: Bearer TOKEN_ADMIN" \
  https://api.sigapp.com.br/api/v1/health/details
```

O check `pgvector` deve informar `ok` e a versão instalada. Confirme também os
índices de todos os schemas tenant:

```sql
SELECT schemaname, indexname
FROM pg_indexes
WHERE indexname = 'ai_document_embeddings_embedding_hnsw_idx'
ORDER BY schemaname;
```

As buscas RAG escrevem o evento estruturado
`AI embedding similarity search completed`, com `strategy`,
`candidate_count`, `result_count` e `duration_ms`. O log nunca deve conter o
texto pesquisado, o vetor ou o conteúdo do documento. Monitore separadamente
p95/p99 de `duration_ms`, crescimento do índice e backlog das filas `ai` e
`default`; aumento simultâneo de latência e backlog indica saturação do banco
ou dos workers.

### Autenticação

- faça login e logout no admin central;
- teste recuperação de senha;
- crie um tenant;
- teste login, seleção do tenant, transfer ticket e callback;
- confirme que o token permanece somente em cookie httpOnly.

### Isolamento multi-tenant

Crie dois tenants de teste, por exemplo `empresa-a` e `empresa-b`. Em cada um:

1. crie um usuário e um terreno;
2. confirme que dados do tenant A não aparecem no tenant B;
3. confirme no PostgreSQL que os schemas são distintos.

### Processos e integrações

No backend, execute `supervisorctl status` e confirme os grupos
`queue-tenant-provisioning`, `queue-ai`, `queue-exports`,
`queue-notifications`, `queue-default` e o scheduler como `RUNNING`. Depois
valide:

- consumo de jobs;
- envio de e-mail;
- upload e download no S3;
- Google Maps;
- geração de PDF;
- checkout e webhook Stripe em modo de teste;
- IA, se configurada.

### Persistência

Reinicie, um por vez, backend, Redis, PostgreSQL e frontends. Confirme que
usuários, tenants, terrenos e jobs continuam disponíveis.

## 12. Configurar e testar backups

1. Cadastre um storage S3 compatível no Coolify.
2. No PostgreSQL, abra `Backups`.
3. Configure backup diário, por exemplo `0 3 * * *`.
4. Defina retenção adequada.
5. Execute `Backup Now`.
6. Confirme o arquivo e o log de sucesso no storage.

Teste a restauração em outro PostgreSQL temporário:

1. crie o banco temporário;
2. restaure o backup;
3. confirme tabelas centrais e schemas tenant;
4. remova o recurso temporário depois do teste.

Nunca teste restauração sobre o banco de produção. O backup da instância do
Coolify não substitui o backup do banco da aplicação.

Referência: [backups PostgreSQL no Coolify](https://coolify.io/docs/databases/backups).

## 13. Releases posteriores

No primeiro deploy, execute somente:

```bash
/usr/local/bin/sigapp-bootstrap
```

Nas releases seguintes, o script de migrations é:

```bash
/usr/local/bin/sigapp-release
```

Não configure `sigapp-release` cegamente como pre-deployment. Segundo o
comportamento documentado do Coolify, o pre-deployment roda no container
existente, que pode não conter as migrations da nova imagem. Prefira executar o
script no container da nova imagem por um comando pós-deploy ou por um fluxo
one-off validado para a versão instalada do Coolify.

Migrations de produção devem ser retrocompatíveis. Para mudanças destrutivas,
use uma estratégia expand/contract, backup validado e janela de manutenção.
Ao introduzir o índice HNSW em uma base já populada, planeje a release em uma
janela de menor escrita: a criação inicial do índice percorre os embeddings de
cada schema tenant.

### Reinício controlado e drenagem das filas

Para recarregar código/configuração sem abandonar o Job em execução:

```bash
cd /var/www
php artisan queue:restart
supervisorctl status
```

`queue:restart` sinaliza os workers pelo cache; cada processo conclui o Job
atual e o Supervisor o inicia novamente. O `stopwaitsecs=660` também dá ao
container tempo suficiente para concluir o Job máximo de 600 segundos durante
um stop gracioso.

Relatórios, dossiês de IA, embeddings e webhooks Stripe possuem defesa
persistente contra reentrega. Não limpe manualmente estados `running`,
`processing` ou tentativas de webhook durante uma drenagem: os workers retomam
claims obsoletos após o prazo definido e o índice anterior de embeddings
permanece ativo até a substituição transacional terminar.

Antes de uma manutenção de Redis ou de uma mudança na topologia de workers:

1. interrompa novas escritas HTTP e o scheduler;
2. acompanhe as cinco filas até não haver jobs `pending` ou `reserved`;
3. confirme que não existem jobs falhos inesperados;
4. faça o stop gracioso do backend;
5. execute a manutenção preservando os dados persistidos do Redis;
6. suba o backend e confirme todos os grupos com `supervisorctl status`;
7. reative scheduler e tráfego somente após um Job de smoke test ser consumido.

Não use `SIGKILL` nem remova as chaves `queues:*` para drenar: jobs reservados
dependem de `retry_after` para voltar à fila após uma falha abrupta.

## 14. Critérios de liberação

O ambiente só pode ser liberado quando:

- [ ] todos os recursos estiverem saudáveis;
- [ ] HTTPS estiver válido nos domínios exatos e wildcard;
- [ ] dois tenants tiverem sido criados e validados como isolados;
- [ ] login, transfer ticket e callback estiverem funcionando;
- [ ] os cinco grupos de workers e o scheduler estiverem em execução;
- [ ] o check detalhado de `pgvector` e os índices HNSW dos tenants estiverem saudáveis;
- [ ] S3, Resend, Maps, PDF e Stripe tiverem sido testados;
- [ ] os recursos tiverem sobrevivido a restart sem perda de dados;
- [ ] um backup PostgreSQL tiver sido criado e restaurado com sucesso;
- [ ] nenhum segredo estiver no Git ou exposto como Build Variable;
- [ ] houver uma revisão disponível para rollback no Coolify.
