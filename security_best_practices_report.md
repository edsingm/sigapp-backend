# Revisão de Segurança — SIGAPP Backend (Laravel 12 / PHP 8.2)

## Sumário Executivo

O backend está bem estruturado (Sanctum, FormRequests com `authorize()`, rate limiting para auth e webhooks assinados), mas existem riscos relevantes em **dependências vulneráveis** e em **telemetria/logs**, que podem levar a **DoS**, **exposição de dados sensíveis** e, dependendo do uso do PhpSpreadsheet, até **SSRF/RCE**.

## Escopo Observado

- API central: [api.php](file:///Users/edsongmaldonado/Herd/sigapp/backend/routes/api.php)
- API tenant: [tenant.php](file:///Users/edsongmaldonado/Herd/sigapp/backend/routes/tenant.php)
- Config principal: [bootstrap/app.php](file:///Users/edsongmaldonado/Herd/sigapp/backend/bootstrap/app.php)
- Auth: [AuthController](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Controllers/Api/V1/AuthController.php)
- Webhooks Stripe: [WebhookController](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Controllers/Api/V1/WebhookController.php)

---

## Achados Críticos

### [CRIT-01] Dependência vulnerável: phpoffice/phpspreadsheet (CVE-2026-34084 / CVE-2026-40902 / CVE-2026-40863 / etc.)

**Impacto:** exploração pode causar **DoS por CPU** e (conforme advisory) **SSRF/RCE quando `IOFactory::load` recebe `$filename` controlado pelo usuário**.

**Evidências**

- `composer audit` reportou 5 advisories para `phpoffice/phpspreadsheet`.
- Versão instalada: `1.30.2` em [composer.lock:L3506-L3508](file:///Users/edsongmaldonado/Herd/sigapp/backend/composer.lock#L3506-L3508)

**Recomendação**

- Atualizar `phpoffice/phpspreadsheet` para uma versão **não afetada** pelos advisories (o audit indica impacto até `1.30.3` e até `5.6.0`, dependendo da linha).
- Como o projeto usa `maatwebsite/excel`, ajustar a versão desse pacote (ou constraints) para permitir a atualização do PhpSpreadsheet.
- Se houver qualquer fluxo que carregue planilhas a partir de caminhos/URLs informados por usuário, bloquear imediatamente: somente carregar arquivos que passaram pelo upload controlado do app (Storage + validação).

---

## Achados Altos

### [HIGH-01] Logs potencialmente sensíveis (request params + stack trace)

**Impacto:** dados pessoais/segredos podem vazar para logs; stack traces podem expor detalhes internos úteis para exploração.

**Evidências**

- Loga `params => $request->all()` e `trace => $e->getTraceAsString()` em [TerrenoController:L97-L115](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Controllers/Api/V1/Tenant/TerrenoController.php#L97-L115)
- Logger de API registra `fullUrl()` e `user_agent` em [ApiRequestLogger](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Middleware/ApiRequestLogger.php#L23-L53)

**Recomendação**

- Nunca logar `trace` completo em produção (ou, no mínimo, gatear por ambiente).
- Trocar `request->all()` por `request->validated()` (quando disponível) ou por um subconjunto explicitamente permitido (evita logar tokens, arquivos, campos inesperados).
- Evitar logar URL completa quando query string puder carregar dados sensíveis; preferir `path()` + whitelist de query params.

### [HIGH-02] Telemetria de IA persiste entradas de tool-calls sem redação

**Impacto:** pode persistir no banco **inputs de ferramentas** contendo PII/segredos/documentos (e isso costuma ser mais sensível do que o prompt “humano”).

**Evidências**

- `AiDataRedactor` é aplicado apenas no prompt do usuário em [AiController:L66-L78](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Controllers/Api/V1/Tenant/AiController.php#L66-L78)
- `tool_calls` persistidos com `input` bruto em [AiController:L124-L146](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Controllers/Api/V1/Tenant/AiController.php#L124-L146)

**Recomendação**

- Aplicar `AiDataRedactor::redactPayload()` antes de persistir `tool_calls`.
- Considerar armazenar apenas metadados (nome da tool, contagem) e não o payload completo, ou manter payload sob feature flag e com TTL/retention curta.

### [HIGH-03] spatie/laravel-ray em dependência de produção

**Impacto:** aumenta a superfície de ataque e o risco de exposição operacional (depuração em runtime), além de ser um “footgun” em ambientes não dev.

**Evidência**

- `spatie/laravel-ray` está em `"require"` em [composer.json:L11-L24](file:///Users/edsongmaldonado/Herd/sigapp/backend/composer.json#L11-L24)

**Recomendação**

- Mover para `"require-dev"` e garantir que não exista inicialização em produção.

---

## Achados Médios

### [MED-01] Helper `ddApi()` disponível globalmente

**Impacto:** risco de vazamento de dados e interrupção de requisições (DoS acidental) se for chamado em produção.

**Evidência**

- Implementação em [helpers.php:L57-L78](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Support/helpers.php#L57-L78)

**Recomendação**

- Gatear a função por ambiente (`local/testing`) e/ou remover de produção.
- Evitar helpers que chamam `exit()` em runtime de API.

### [MED-02] Identificação de tenant aceita `id` e há múltiplos resolvers habilitados em config

**Impacto:** risco de enumeração/descoberta e de “desvio” de roteamento de tenant caso alguma rota/middleware passe a usar identification por request data (header/query/cookie) inadvertidamente.

**Evidências**

- Resolve tenant por `slug` **ou** `id` em [InitializeTenancyFlexible:L50-L52](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Middleware/InitializeTenancyFlexible.php#L50-L52)
- Resolvers incluem header/cookie/query em [tenancy.php:L171-L199](file:///Users/edsongmaldonado/Herd/sigapp/backend/config/tenancy.php#L171-L199)

**Recomendação**

- Considerar restringir tenant a `slug` (evita uso acidental de IDs previsíveis) e revisar se a identificação por query/cookie faz sentido para produção.
- Manter o fallback `X-Tenant` estritamente limitado a `local/testing` (o middleware já faz isso em [InitializeTenancyFlexible:L94-L100](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Middleware/InitializeTenancyFlexible.php#L94-L100)).

### [MED-03] Ausência de hardening headers no app/nginx

**Impacto:** aumenta exposição a classes de ataques no browser (clickjacking, XSS por falta de políticas adicionais, etc.) quando houver consumo web.

**Evidência**

- Nginx docker não define headers defensivos em [nginx.conf](file:///Users/edsongmaldonado/Herd/sigapp/backend/.docker/nginx.conf)

**Recomendação**

- Configurar no reverse proxy (Nginx/Ingress) headers básicos (por exemplo: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`).
- Evitar habilitar HSTS sem ter 100% certeza do rollout de HTTPS (para não causar lockout).

---

## Achados Baixos / Observações

### [LOW-01] Ajustes de cookies/sessão e CORS devem ser validados por ambiente

**Evidências**

- `supports_credentials` habilitado em [cors.php:L50](file:///Users/edsongmaldonado/Herd/sigapp/backend/config/cors.php#L50)
- `SANCTUM_STATEFUL_DOMAINS` inclui wildcard para subdomínios em [sanctum.php:L42-L45](file:///Users/edsongmaldonado/Herd/sigapp/backend/config/sanctum.php#L42-L45)
- `SESSION_SECURE_COOKIE` sem default em [session.php:L171-L216](file:///Users/edsongmaldonado/Herd/sigapp/backend/config/session.php#L171-L216)

**Recomendação**

- Garantir que em produção cookies estejam com `secure=true` (quando HTTPS) e revisar `same_site` conforme o modo (SPA cross-site vs same-site).
- Manter CORS estritamente alinhado ao(s) domínio(s) do frontend e evitar adicionar origens amplas via env.

### [LOW-02] Credenciais fracas em docker-compose (contexto dev)

**Evidência**

- `DB_PASSWORD=123456` em [docker-compose.yml:L12-L23](file:///Users/edsongmaldonado/Herd/sigapp/backend/docker-compose.yml#L12-L23)

**Recomendação**

- Manter isso estritamente para desenvolvimento local (o arquivo já sugere esse contexto), e evitar replicar essa prática em ambientes compartilhados.

---

## Pontos Positivos (Boas Práticas já presentes)

- Rate limiting para rotas sensíveis (login/reset) em [api.php](file:///Users/edsongmaldonado/Herd/sigapp/backend/routes/api.php)
- Webhook do Stripe com assinatura e idempotência por lock/event store em [WebhookController](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Controllers/Api/V1/WebhookController.php)
- Uso consistente de FormRequests + `authorize()` (ex.: [StoreDocumentoRequest](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Requests/Tenant/StoreDocumentoRequest.php))
- Middleware `X-Tenant` limitado a `local/testing` em [InitializeTenancyFlexible](file:///Users/edsongmaldonado/Herd/sigapp/backend/app/Http/Middleware/InitializeTenancyFlexible.php)

