# Jobs, notificações, uploads, qualidade e checklist

> **Quando ler:** jobs/filas/events/scheduler, e-mail, PDF/Excel/uploads, LGPD/segurança, testes, PHPStan/Pint, checklist de PR.
> **Hub:** [`AGENTS.md`](../../AGENTS.md)

## Jobs, queues, events e scheduler

- Operações demoradas são assíncronas via Jobs (`app/Jobs/`). Queue: `sync` em teste, **Redis em produção**.
- Jobs de provisionamento, IA e exportação declaram sua fila com `#[Queue(...)]`; notificações implementam `ShouldQueue` e usam a fila `notifications`. Jobs sem classe dedicada permanecem em `default`.
- Jobs sensíveis a concorrência implementam `ShouldBeUnique` com chave tenant-aware e mantêm um claim condicional persistente no PostgreSQL quando existe registro de execução. O lock Redis evita duplicatas comuns; o claim no banco é a defesa final contra reentrega, expiração do lock ou workers concorrentes.
- Exportações pesadas de terrenos/viabilidades usam o pipeline genérico `TenantExportGenerationService` → `GenerateTenantExportJob` na fila `exports`: `POST /api/v1/exports` cria uma solicitação idempotente e retorna `202`; `GET /api/v1/exports/{export}` consulta o status; o download autenticado fica em `/download`. O registro e o artefato são isolados por tenant/solicitante, salvos no disk privado `s3` e expiram logicamente após 24 horas. Os endpoints síncronos legados permanecem apenas durante a migração do frontend e não devem receber novos tipos de exportação.
- Importações de terrenos também usam a fila `exports`: `ValidateTerrenoImportJob` valida a planilha sem criar dados, `CommitTerrenoImportJob` confirma todas as linhas em uma transação e `ParseTerrenoPolygonImportJob` extrai geometrias KML/KMZ. O timeout máximo continua em 600s e todos os Jobs mantêm `failed()`.
- **Todo Job deve implementar `failed(Throwable $e)`** — verificado por `LayerBoundariesTest::test_all_jobs_define_failed_handler`. Defina também `$tries`/`$timeout`/`$backoff`.
- Eventos de domínio em `app/Events/Tenant/` com listeners em `app/Listeners/Tenant/` registrados explicitamente no `EventServiceProvider` — a descoberta automática global está desativada em `bootstrap/app.php` para não duplicar listeners; side-effects nunca inline no Service quando houver evento adequado.
- Agendamentos ficam em **`routes/console.php`** (broker cleanup 5min, consent-logs diário, tenants pendentes por hora, poda diária de referências expiradas de tags Redis às 03:30, verificação de storage 07:00, etapas atrasadas 08:00, digests diário/semanal, scores IA 06:00, stats de tenants por hora). Comando novo recorrente → agende ali com `name()` exclusivo, `onOneServer()` e `withoutOverlapping()` com expiração maior que a duração esperada.
- Comandos Artisan em `app/Console/Commands/` com `$signature`/`$description`; comandos destrutivos (ex.: `WipeAllTenants`, `sigapp:bootstrap --force`) exigem confirmação explícita em TTY. `sigapp:release` é o caminho de deploy (migrate central + `tenants:migrate`, sem seed); `sigapp:bootstrap` só em ambiente vazio. Os binários `/usr/local/bin/sigapp-*` são wrappers desses comandos.

## Notificações e e-mail

- Transporte: **Resend** (`RESEND_API_KEY`). Teste manual: `php artisan mail:test {email}`.
- Solicitações públicas de demonstração são persistidas no central e notificadas por `DemoRequestReceived` → `NotifyDemoRequestReceived` → `DemoRequestNotification`, na fila `notifications`; falha de e-mail não invalida o lead já salvo.
- Notificações de workflow em `app/Notifications/Workflow/` respeitam as **preferências do usuário** (`NotificationPreference` + trait `RespectsEmailPreference` + `NotificationCatalog`). Notificação nova de fluxo deve entrar no catálogo e respeitar preferências/digest (`notifications:send-email-digests`).
- Alertas de storage usam `tenant:check-storage-usage` + `StorageLimitApproachingNotification`, com thresholds persistidos em `tenants.storage_alert_threshold` (80%/90%) para evitar reenvio repetido.
- Views de e-mail em `resources/views/emails/`.

## Uploads, PDF e Excel

- PDF via `spatie/laravel-pdf` (Browsershot/Chromium — env `BROWSERSHOT_CHROME_PATH`/`PUPPETEER_EXECUTABLE_PATH`); templates em `resources/views/pdf/`.
- Excel via `maatwebsite/excel` — classes em `app/Exports/` (ex.: `TerrenosExport` + `TerrenoExportRepository`). Exports volumosos devem implementar `FromQuery` para leitura em chunks; não materialize a coleção completa antes da escrita.
- A importação cadastral aceita somente `.xlsx`, no máximo 10 MB e 1.000 linhas, proíbe fórmulas e usa template próprio em `GET /api/v1/terrenos/imports/template`. O arquivo é temporário no disk `s3`, conta na quota enquanto persistido e é apagado após a validação terminal; o comando diário `tenant:cleanup-terreno-imports` remove os metadados expirados após 30 dias.
- A importação geográfica em lote aceita até 10 KML/KMZ de 10 MB cada e 40 MB agregados. Os arquivos temporários contam na quota até o parsing; as geometrias pendentes permanecem no schema tenant até vínculo ou descarte e nunca sobrescrevem silenciosamente `polygon_coords`.
- Arquivos gerados pelo pipeline assíncrono de exportação são privados no disk `s3`; nunca exponha `storage_path`/`storage_disk` na API. A expiração lógica remove a disponibilidade e bloqueia o download, e a remoção física deve ser garantida pela política de lifecycle do bucket.
- Upload de arquivo: valide tipo MIME, tamanho e extensão no FormRequest (ex.: `UploadKmzRequest`, `StoreDocumentoRequest` — documentos de terreno até **10 MB**). Storage é sufixado por tenant quando local (config tenancy); documentos e relatórios de IA usam o disk `s3`. Respeite `enforce.limits:storage_gb` nas rotas que aumentam uso de armazenamento.

## LGPD, privacidade e segurança

- Consentimento de cookies: `POST /api/v1/consent-log` (público, rate-limited 5/min) grava uma trilha **append-only**; mudanças para o mesmo `consent_id` criam novas linhas e nunca sobrescrevem o histórico. A retenção roda via `privacy:cleanup-consent-logs` (config `privacy.php`); termos de uso versionados no tenant (`TermoDeUsoVersao`). Config `legal.php`.
- Auditoria: trait `LogsAudit` + `AuditLog` (central, consultável em `/admin/audit-logs`); auditoria RBAC em `scripts/security/audit_tenant_rbac.php` e `docs/security/`.
- `SecurityHeaders` é global; **rate limiting em toda rota** (ver seção 9); `APP_DEBUG=false` em produção; nunca commite `.env` (o `.env.example` lista todas as variáveis, sem valores — **atualize-o ao criar variável nova**).
- Nunca confie em dados do cliente para permissões, preços ou tenant-id (o header `X-Tenant` só vale fora de produção).
- Rode `composer audit` periodicamente.

## Testes (obrigatório)

- **PHPUnit 13 puro** (classes estendendo `Tests\TestCase`) — **não** Pest. Suites: `Architecture`, `Unit`, `Feature` (`phpunit.xml`).
- Ambiente de teste local/padrão: SQLite `:memory:` (central e tenancy), queue `sync`, cache `array` (Laravel 13 suporta tags nesse store, portanto invalidação seletiva também deve ser exercitada localmente), `BCRYPT_ROUNDS=4`.
- O CI (`.github/workflows/ci.yml`) roda em paralelo: Tests (SQLite), suíte completa com PostgreSQL 18 + Redis 7 reais, Pint, PHPStan (`composer analyse`, memory 512M) e build da imagem prod (`docker build --target prod -f .docker/Dockerfile .`, sem push). Jobs PHP usam cache de Composer (`composer.lock`). Testes exclusivos de infraestrutura ficam em `tests/Feature/Infrastructure/` e devem se marcar como skipped quando o driver não for `pgsql`/`redis`; nunca substitua essa cobertura por mocks.
- Toda funcionalidade nova exige testes **antes de ser considerada concluída**: Feature cobrindo happy path + pelo menos um cenário de erro (401/403/422), e Unit para services/calculators com lógica.
- Estrutura espelha o código: `tests/Feature/Tenant/`, `tests/Feature/Billing/`, `tests/Unit/Services/Viabilidade/`, etc.
- Padrão Arrange-Act-Assert, nomes descritivos (`test_rejeita_transicao_de_workflow_invalida`), `RefreshDatabase` quando toca o banco, `actingAs()` para rotas autenticadas.
- Mock de externos sempre: `Http::fake()`, `Mail::fake()`, `Notification::fake()`, `Queue::fake()`, `Event::fake()` — **nunca** bata em Stripe/Resend/providers de IA em teste.
- Testes tenant usam o fluxo de inicialização de tenancy dos testes existentes (siga `tests/Feature/Tenant/*` como referência) — não invente bootstrap próprio.
- Fluxos de listagem/detail que serializam Resources complexos devem ter regressão de queries quando houver risco de N+1: compare cardinalidades diferentes ou zere o query log antes da serialização; o total não pode crescer por item.

#### Testes de arquitetura (rodam no CI — não os enfraqueça)

| Teste | Garante |
|---|---|
| `LayerBoundariesTest` | Controllers listados sem query Eloquent direta; **todos os Jobs com `failed()`** |
| `ServicesArchitectureTest` | Services migrados sem Eloquent direto; Services sem `Illuminate\Http\Request` |
| `AdminControllerArchitectureTest` | Controllers admin sem `->validate()` inline nem queries diretas |
| `PublicControllerArchitectureTest` | Controllers públicos/auth sem validação inline; Blog sem query direta em Post |
| `ModulesControllerArchitectureTest` | ModulesController sem uso direto de Models |
| `TenantAdminRequestAuthorizationTest` | FormRequests tenant sem `authorize()` trivial (`return true;`) |
| `TenantRoutesArchitectureTest` | Módulos tenant carregados uma vez; contrato legado e precedência das rotas preservados |
| `RouteCacheArchitectureTest` | Nomes completos de rota únicos; nomes canônicos preservados no domínio central principal |

Ao criar controller/service/job novo nos escopos cobertos, ele **precisa** nascer conforme — e, quando o teste usa lista explícita de arquivos, adicione o novo arquivo à lista.

---

## Qualidade: PHPStan e Pint

- **PHPStan nível 8** (+ `bleedingEdge`), paths `app` e `tests`, `phpVersion: 80400`. Rode `composer analyse` antes de todo commit.
- O `phpstan.neon` inclui o baseline **`phpstan.baseline.neon`** e uma lista extensa de `ignoreErrors` para a "magia" do Eloquent (o Larastan está instalado como dev-dependency, mas a extensão **não** está incluída no `phpstan.neon` — os falsos positivos são tratados via ignores/baseline). **Não adicione novos padrões ao `ignoreErrors` nem regenere o baseline para esconder erro novo** — corrija o tipo. Ignore novo só com justificativa em comentário.
- **Pint** preset `laravel`: `./vendor/bin/pint --test` deve passar limpo antes de qualquer merge.

## Checklist antes de cada PR

- [ ] `composer analyse` (PHPStan nível 8) sem erros novos
- [ ] `./vendor/bin/pint --test` sem pendências
- [ ] `composer test` verde, incluindo `--testsuite=Architecture`
- [ ] Rota nova: versionada (`/api/v1/...`), no arquivo certo (central × tenant), com `throttle` e middlewares de contexto/permissão
- [ ] Mutação usa FormRequest com `authorize()` real; nenhum `$request->all()` / `->validate()` inline
- [ ] Resposta via `ApiResponseService`/Resource; chaves i18n adicionadas em `pt-br.json` **e** `en-us.json`
- [ ] Sem N+1 (`with()`), sem `all()`/`get()` ilimitado
- [ ] Migration na pasta certa, com `down()` funcional e índices; compatível com SQLite nos testes
- [ ] Job novo com `failed()`, `$tries`, `$timeout`
- [ ] Model novo com `$fillable`, `$casts` e factory
- [ ] Repository novo com interface? Bind registrado no `AppServiceProvider`
- [ ] `.env.example` atualizado se criou variável de ambiente
- [ ] `AGENTS.md` atualizado se a mudança alterou arquitetura, fluxos, rotas, comandos, env/deploy, billing, IA, RBAC, storage ou regras de teste
- [ ] Serviços externos mockados nos testes (Stripe, Resend, IA, HTTP)
