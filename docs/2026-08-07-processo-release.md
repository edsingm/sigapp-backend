# Processo de Release do Backend

## Visão geral

O backend possui dois processos operacionais diferentes:

- `sigapp-bootstrap`: usado somente na criação inicial de um ambiente com banco vazio.
- `sigapp-release`: usado nos deploys seguintes, antes de liberar a nova versão para receber tráfego.

O processo de inicialização do container (`entrypoint.prod.sh`) é separado dos dois. Ele não executa migrations; apenas prepara caches, cria o link de storage e inicia os processos da aplicação.

## Bootstrap inicial

Quando o banco ainda não possui a estrutura do SIGAPP, execute uma única vez:

```bash
/usr/local/bin/sigapp-bootstrap
```

O script executa:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

O bootstrap cria as migrations centrais e popula os dados iniciais. Não deve ser usado em cada deploy, porque executa os seeders.

Arquivo: `.docker/bootstrap.prod.sh`.

## Release de uma nova versão

Para publicar uma versão em um ambiente já inicializado, execute:

```bash
/usr/local/bin/sigapp-release
```

O script executa, nesta ordem:

```bash
php artisan migrate --force
php artisan tenants:migrate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### O que cada etapa faz

1. `migrate --force` aplica as migrations do banco central.
2. `tenants:migrate` aplica as migrations nos schemas dos tenants.
3. `config:cache` recria o cache de configuração.
4. `route:cache` valida e recria o cache das rotas.
5. `view:cache` compila as views Blade utilizadas por e-mails, PDFs e exports.

O release deve terminar com código de saída zero antes da troca de tráfego. Migrations não devem ser executadas automaticamente durante o restart ou scale dos containers.

Arquivo: `.docker/release.prod.sh`.

## Inicialização do container

Depois que o release é concluído, cada container novo executa:

```bash
php artisan optimize:clear
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Em seguida, o Supervisor inicia:

- Nginx;
- PHP-FPM;
- workers Redis das filas `tenant-provisioning`, `ai`, `exports`, `notifications` e `default`;
- scheduler (`schedule:work`).

Arquivo: `.docker/entrypoint.prod.sh`.

## Fluxo recomendado de deploy

```text
Construir a nova imagem
        ↓
Executar /usr/local/bin/sigapp-release uma única vez
        ↓
Aplicar migrations central e dos tenants
        ↓
Liberar a nova imagem para o tráfego
        ↓
Executar healthcheck em /api/v1/health
        ↓
Verificar logs do PHP-FPM, workers e scheduler
        ↓
Executar smoke tests da alteração publicada
```

O healthcheck de produção usa:

```text
GET /api/v1/health
```

## Alteração da solicitação de demonstração

A funcionalidade de demonstração adiciona uma migration central:

```text
database/migrations/2026_08_07_120000_create_demo_requests_table.php
```

Essa tabela será criada por:

```bash
php artisan migrate --force
```

Ela não é criada por `tenants:migrate`, pois pertence ao banco central.

Após o release, o endpoint deve responder:

```text
POST /api/v1/demo-request
```

As solicitações são persistidas no banco central e a notificação interna é colocada na fila `notifications`. O ambiente de produção precisa ter `CENTRAL_ADMIN_EMAIL` configurado e o worker dessa fila ativo.

## Variáveis e serviços necessários

O deploy de produção precisa manter configurados:

- conexão PostgreSQL central;
- Redis para cache e filas;
- `CENTRAL_ADMIN_EMAIL`;
- credenciais do Resend, para envio de e-mail;
- domínio central incluindo o host da API;
- variáveis de storage, Stripe e demais integrações exigidas pelo Compose.

O Compose de produção valida as variáveis obrigatórias antes de iniciar o serviço. Falhas de configuração devem interromper o deploy antes da troca de tráfego.

## Checklist pós-release

- [ ] O comando `sigapp-release` terminou com sucesso.
- [ ] A migration `demo_requests` existe no banco central.
- [ ] O healthcheck `/api/v1/health` retorna HTTP 200.
- [ ] O endpoint `POST /api/v1/demo-request` retorna HTTP 201 para uma solicitação válida.
- [ ] O worker `notifications` está ativo.
- [ ] O e-mail de teste foi processado sem erro pelo Resend.
- [ ] Não há erros novos nos logs do backend, Nginx ou workers.
