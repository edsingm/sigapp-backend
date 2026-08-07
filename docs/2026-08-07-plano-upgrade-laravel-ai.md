# Plano: Upgrade `laravel/ai` 0.7.2 → 0.10.x

**Data:** 2026-08-07  
**Ticket:** [SIG-11](https://sigapp.youtrack.cloud/issue/SIG-11)  
**Status:** implementado (2026-08-07) — `laravel/ai` v0.10.3  
**Escopo:** backend SIGAPP — bump do SDK oficial, migration tenant de conversas, ajustes de store/ownership, config, testes e smoke  
**Fora de escopo:** reimplementar análise de PDF no SDK; trocar modelo default do chat; ativar HITL/tool approval na UI; alterar tools de domínio além do que o SDK exigir

---

## 1. Decisões fechadas

| Decisão | Valor |
|---|---|
| Versão atual (lock) | `laravel/ai` **v0.7.2** (`composer.json`: `^0.7.0`) |
| Alvo | **`^0.10.0`** — preferir última 0.10.x estável (**v0.10.3** em 2026-08-06; ticket citava 0.10.2) |
| Stack | PHP 8.4 + Laravel 13 (compatível com require do pacote `^12\|^13`, PHP `^8.3`) |
| Contexto das tabelas | **Tenant** (`database/migrations/tenant/`) — schemas `tenant_{slug}` |
| Participante canônico | `App\Models\Tenant\User` (morph: FQCN ou alias se houver morph map) |
| Store | Default do pacote (`DatabaseConversationStore`) — **sem** `ConversationStore` custom |
| PDF / OpenCode Go | Permanece client HTTP dedicado (`OpenCodeGoDocumentClient`) — **não** depende deste upgrade |
| Estratégia de PR | **Um PR focado** no upgrade (sem features de negócio misturadas) |
| Pré-condição operacional | Smoke do SIG-10 (análise documental) estável em staging antes de promover este PR a prod |

---

## 2. Estado atual no código (baseline)

| Superfície | Estado 0.7.2 |
|---|---|
| `composer.json` / lock | `^0.7.0` / **v0.7.2** |
| Migration tenant | `2026_03_22_193204_create_agent_conversations_table.php` — colunas `user_id` (conversations + messages); **sem** `approval_state` |
| `AiConversationRepository` | `where('user_id', $userId)` em listagem e ownership |
| `AiController@chat` | `ConversationStore::storeConversation($userId, $title)` + `$agent->continue($id, $authUser)->stream(...)` |
| `SIG_IA` | `Promptable` + `RemembersConversations`; `providerOptions(string $provider)` (OpenRouter reasoning) |
| Embeddings | `Embeddings::for([...])->dimensions(...)->generate($provider, $model)` — **não** usa builder `providerOptions()` |
| Tools | `implements Tool` + `Laravel\Ai\Tools\Request` nos testes |
| Config | `config/ai.php` com chaves **SIGAPP** (`agent_provider`, prices, budget, document_*, `opencode_go`, models, fallback) além do stub do pacote |
| Telemetria | `user_id` em `AiRequestLog` / budget — **fora** do schema do pacote; **não migrar** para participant |

Referência oficial: [UPGRADE.md (branch 0.x)](https://github.com/laravel/ai/blob/0.x/UPGRADE.md).

---

## 3. Breaking changes relevantes (0.7 → 0.10)

O `UPGRADE.md` documenta explicitamente **0.9←0.8** e **0.10←0.9**. Não há seção 0.8←0.7; o salto deve ser validado por testes + smoke.

### 3.1 Alto impacto — participantes polimórficos (0.10)

Tabelas passam de `user_id` para:

- `participant_type` (string, nullable)
- `participant_id` (unsignedBigInteger / FK id, nullable)

Índices:

| Tabela | Antigo | Novo |
|---|---|---|
| `agent_conversations` | `(user_id, updated_at)` | `participant_updated_at_index` em `(participant_type, participant_id, updated_at)` |
| `agent_conversation_messages` | `conversation_index` + `(user_id)` | `conversation_index` em `(conversation_id, participant_type, participant_id, updated_at)` + `participant_index` |

`ConversationStore` (assinatura 0.10.3):

```php
storeConversation(?string $participantType, string|int|null $participantId, string $title): string
latestConversationId(string $participantType, string|int $participantId): ?string
// + storeUserMessage / storeAssistantMessage com type+id
// + storeApprovalResults(...)  // só se custom store — não é o caso
```

Helpers do pacote (permanecem):

- `continue($conversationId, $as)` / `forUser($user)` / `forParticipant($participant)`
- `Laravel\Ai\Models\Conversation::participantType($participant)` / `participantKey($participant)`
- Propriedade `conversationUser` no trait **continua** (alias conceitual do participant)

### 3.2 Alto impacto — `approval_state` (0.10)

Coluna `text` nullable em `agent_conversation_messages`. Fresh installs do pacote já trazem; upgrades publicados **não** re-rodam a migration original → **migration tenant aditiva obrigatória**.

HITL **não** será exposto na API neste PR; só schema para o store não quebrar inserts.

### 3.3 Médio — Provider options API (0.9)

- Builders de embeddings/transcription: `providerOptions()` → `withProviderOptions()`.
- No SIGAPP: **embeddings não chamam** o builder antigo → impacto baixo.
- Contrato de agente `HasProviderOptions::providerOptions(Lab|string $provider)` **permanece** em 0.10 — o método em `SIG_IA` deve ser mantido; alinhar type-hint a `Lab|string` se o PHPStan/contrato exigir.

### 3.4 Baixo — loop / fakes / Agent contract (0.9–0.10)

- `TextGateway` removido → `StepTextGateway` / `TextGenerationLoop` (só se gateway custom — **não temos**).
- `Agent::fake()` mais realista (tool não registrada → exception; eventos stream com `ToolCall`).
- `prompt`/`stream` aceitam `Decisions|string` — coberto pelo trait `Promptable` se a classe continuar usando o trait.

### 3.5 Config do pacote 0.10 (merge)

Defaults novos no stub oficial incluem providers extras (`bedrock`, `openai-compatible`), URLs em vários drivers, flags Azure/Anthropic, etc. **Não** incluem as chaves proprietárias SIGAPP — o merge deve ser **aditivo**, nunca substituir o arquivo inteiro pelo publish cego.

---

## 4. Arquitetura alvo (conversas)

```text
POST /api/v1/ai/sig-ai (tenant)
        │
        ▼
AiController@chat
  - ownership: AiConversationRepository (participant_type + participant_id)
  - nova conversa: ConversationStore::storeConversation(
        Conversation::participantType($user),
        Conversation::participantKey($user),
        $title
    )
  - stream: SIG_IA->continue($id, $authUser)->stream($message, provider: $map)
        │
        ▼
DatabaseConversationStore (pacote 0.10)
  → agent_conversations / agent_conversation_messages
     (participant_*, approval_state)
        │
        ▼
AiTelemetryService (inalterado no schema de conversa)
  → ai_request_logs.user_id (tenant user id numérico)
```

**Regra:** `user_id` de telemetria/budget/quota **não** vira morph. Só as tabelas do pacote de conversa.

---

## 5. Escopo de implementação (fases)

### Fase 0 — Pré-voo (sem código de produção)

1. Confirmar smoke SIG-10 em staging (ou decisão explícita de risco aceito).
2. Branch a partir de `main` (ou base já com SIG-10 merged).
3. `composer show laravel/ai` / lock = 0.7.2 (baseline).
4. Ler no vendor pós-update: `UPGRADE.md`, migration canônica, `DatabaseConversationStore`, `config/ai.php` stub, `HasProviderOptions`.
5. Inventário de greps (devem zerar ou ser intencionais ao fim):

```bash
rg "storeConversation\(" app tests
rg "agent_conversations|agent_conversation_messages" app tests database
rg "->where\('user_id'" app/Repositories/AiConversationRepository.php
rg "providerOptions\(" app
rg "Laravel\\\\Ai\\\\" app tests
```

**Verify:** lista de arquivos tocados fechada antes do bump.

---

### Fase 1 — Bump Composer

1. `composer.json`: `"laravel/ai": "^0.10.0"`.
2. `composer update laravel/ai --with-dependencies` (revisar diff do lock; evitar upgrades colaterais desnecessários).
3. Confirmar `composer show laravel/ai` ≥ **0.10.2** (preferência **0.10.3+**).
4. Rodar autoload; checar se o package discovery do `AiServiceProvider` segue ok.

**Verify:** lock aponta 0.10.x; app sobe (`php artisan about` / `config:clear`).

**Risco:** conflito de dependências transitivas — resolver no lock, não pinar cegamente.

---

### Fase 2 — Migration tenant (schema)

**Arquivo novo** (não editar `2026_03_22_193204_...`):

`database/migrations/tenant/2026_08_07_000001_upgrade_agent_conversations_for_laravel_ai_0_10.php`

(data final pode ajustar ao dia do commit; manter pasta **tenant**).

#### 2.1 `up()` — ordem sugerida

Espelhar o [snippet oficial do UPGRADE](https://github.com/laravel/ai/blob/0.x/UPGRADE.md), com estas adaptações SIGAPP:

1. Resolver nomes de tabela via `config('ai.conversations.tables.*')` com fallbacks `agent_conversations` / `agent_conversation_messages`.
2. Drop índices antigos **por nome estável quando possível** (SQLite é sensível a `dropIndex(['user_id'])` vs nome).
3. `renameColumn('user_id', 'participant_id')` em ambas as tabelas.
4. Add `participant_type` string nullable (posição: após `id` em conversations; após `conversation_id` em messages — ideal; se driver limitar `after()`, aceitar append).
5. Backfill:

```php
$participantType = (new \App\Models\Tenant\User)->getMorphClass();

DB::table($conversationsTable)
    ->whereNotNull('participant_id')
    ->whereNull('participant_type')
    ->update(['participant_type' => $participantType]);

// idem messages
```

6. Criar índices novos (`participant_updated_at_index`, `conversation_index`, `participant_index`).
7. Add `approval_state` text nullable em messages (após `meta` se suportado).

#### 2.2 `down()` funcional

- Drop `approval_state`.
- Drop índices novos.
- Nullificar/remover `participant_type` (após garantir que reverse rename é seguro).
- `renameColumn('participant_id', 'user_id')`.
- Recriar índices legados.

#### 2.3 Compatibilidade SQLite (testes) × PostgreSQL (prod/CI)

| Ponto | Ação |
|---|---|
| `renameColumn` | Preferir API Schema do Laravel 13; se SQLite falhar em drop+recreate, usar path condicional `Schema::getConnection()->getDriverName()` |
| Drop index | Preferir **nome** do índice; em pgsql o `conversation_index` já é nomeado na migration original |
| `after()` | Opcional — não falhar migration se o driver ignorar |
| Tenants já provisionados | `php artisan tenants:migrate` (ou fluxo de release `sigapp-release`) |
| Tenants novos | Continuam rodando migration create **antiga** + esta upgrade na ordem de timestamps — **não** reescrever a create legada (histórico + ambientes já migrados) |

**Alternativa rejeitada neste plano:** reescrever a migration create original para o schema 0.10. Quebra ambientes que já rodaram `2026_03_22_*` e viola a regra do projeto (“nunca editar migration aplicada”).

**Verify:**

- Suíte com `RefreshDatabase` / tenancy bootstrap verde em SQLite.
- Se possível, smoke `tenants:migrate` em pgsql local/CI `tests/Feature/Infrastructure`.

---

### Fase 3 — Código de aplicação

#### 3.1 `AiConversationRepository`

| Método | Mudança |
|---|---|
| `getRecentConversations(int $userId, ...)` | Filtrar `participant_id = $userId` **e** `participant_type = Tenant\User morph` |
| `conversationExists($id, $userId)` | Idem (ownership) |
| `getMessages` | Sem mudança de ownership (já por `conversation_id`); opcionalmente expor `approval_state` **não** é necessário na API atual |

Preferir helper privado:

```php
private function participantType(): string
{
    return (new \App\Models\Tenant\User)->getMorphClass();
}
```

Ou `Conversation::participantType($user)` quando houver instância.

#### 3.2 `AiController`

Troca obrigatória na criação de conversa:

```php
// Antes (0.7)
$store->storeConversation($userId, Str::limit($message, 60));

// Depois (0.10)
use Laravel\Ai\Models\Conversation;

$store->storeConversation(
    Conversation::participantType($authUser),
    Conversation::participantKey($authUser),
    Str::limit($message, 60),
);
```

Manter:

- `$agent->continue($conversationId, $authUser)->stream(...)` (API do trait estável em 0.10.3).
- Headers SSE / `X-Conversation-Id` / telemetria com `user_id` numérico.

#### 3.3 `SIG_IA`

- Manter `use Promptable, RemembersConversations`.
- Revisar assinatura de `providerOptions` → `Lab|string $provider` se o contrato 0.10 exigir (e `match` com string providers).
- `conversationUser` permanece no trait; acesso a `name` ok.
- **Não** registrar tools de mutação; catálogo meta-tools inalterado.

#### 3.4 Embeddings / tools / router

| Componente | Ação esperada |
|---|---|
| `AiEmbeddingService` | Smoke após bump; só alterar se builder API quebrar |
| `AiProviderRouter` | Manter mapa `providers => models` no `stream(..., provider: $map)` |
| Tools `implements Tool` | Ajustar só se contract/Request mudar (PHPStan vai apontar) |
| `RedactingToolDecorator` | Validar decorator ainda implementa `Tool` |
| Document pipeline | **Não tocar** |

#### 3.5 Config `config/ai.php`

Merge **manual**:

1. Diff contra stub `vendor/laravel/ai/config/ai.php` (0.10).
2. Incorporar chaves/providers **novos** do pacote que forem inofensivos/úteis (`bedrock`, `openai-compatible`, URLs default, flags).
3. **Preservar obrigatoriamente** chaves SIGAPP:

   - `agent_provider`, `fallback_provider`, `fallback_agent_model`
   - `embedding_*`, budgets, rate limits, `prices_per_million_tokens`, `embedding_prices_*`
   - `document_*` e provider `opencode_go` (mesmo que o pacote não conheça o driver — usado pelo client HTTP / telemetria)
   - `models.{deepseek,gemini,openrouter}.agent`

4. Se o 0.10 introduzir `ai.conversations.*` (connection/tables), declarar explicitamente alinhado ao schema tenant (defaults do pacote já batem com os nomes atuais).

**Verify:** `php artisan config:clear` + teste que lê `config('ai.agent_provider')` e budget keys.

---

### Fase 4 — Testes

#### 4.1 Ajustar existentes

| Suite / arquivo | Foco |
|---|---|
| `tests/Feature/Tenant/AiChatStreamingTest.php` | Stream + fakes 0.10 (eventos extras de tool; empty string) |
| `tests/Unit/AiToolsTest.php` | `TextGenerationOptions::forAgent`, catálogo tools |
| `tests/Unit/AiServicesAndMiddlewareTest.php` | `AiProviderRouter` |
| `tests/Unit/AiEmbeddingServiceTest.php` | generate embedding com fake/Http |
| `tests/Feature/Tenant/AiToolsDataContractTest.php` / `AiToolsAuthorizationBoundaryTest.php` | `Laravel\Ai\Tools\Request` |
| `tests/Unit/MetaToolsTest.php` | tools() do agente |
| Architecture | Jobs `failed()`, layers — não deve quebrar se não criar controller gordo |

#### 4.2 Cobertura nova (mínima obrigatória)

1. **Unit/Feature repository:** ownership por `participant_*` (usuário A não lê conversa de B).
2. **Feature chat setup:** criar conversa grava `participant_type` = morph de `Tenant\User` e `participant_id` = id.
3. **Migration:** teste que sobe schema tenant (já coberto indiretamente por RefreshDatabase); se houver teste de migration explícito no projeto, seguir o padrão.
4. **Regressão documental:** pelo menos um teste existente de `DocumentUnderstandingService` / job de análise deve permanecer verde **sem** alteração de comportamento.

#### 4.3 Comandos de qualidade (checklist PR)

```bash
composer update laravel/ai --with-dependencies   # já na fase 1
composer analyse                                 # PHPStan 8
./vendor/bin/pint --test
php artisan test --filter=Ai
php artisan test --testsuite=Architecture
composer test                                    # suíte completa antes do merge
```

**Verify:** todos verdes; sem ignores novos no PHPStan.

---

### Fase 5 — Smoke staging / produção

| # | Cenário | Esperado |
|---|---|---|
| 1 | `POST /ai/sig-ai` nova conversa (provider primário) | SSE ok; header `X-Conversation-Id`; row com participant_* |
| 2 | Continuar conversa existente | ownership ok; histórico coerente |
| 3 | `GET` listagem / mensagens | só conversas do user |
| 4 | Fallback provider (se configurado) | stream recupera ou falha controlada |
| 5 | Tool call (ex.: SearchPortfolio list) | envelope tools + telemetria budget |
| 6 | Budget status | inalterado |
| 7 | Embedding / search docs | sem regressão |
| 8 | Upload PDF + analysis (SIG-10) | inalterado |
| 9 | `tenants:migrate` em tenant real de staging | migration upgrade aplica; `down` testado em clone se possível |

Release: migration tenant entra no script de release existente (`sigapp-release` / `tenants:migrate`) — **não** no startup do container.

---

### Fase 6 — Documentação

| Arquivo | Atualização |
|---|---|
| `AGENTS.md` | Versão `laravel/ai ^0.10`; nota de conversas com participant polimórfico; tabela `approval_state` se mencionar schema |
| `docs/ia.md` | Se exemplos usarem `storeConversation($userId)` ou `user_id` — alinhar |
| `docs/ARCHITECTURE_DIAGRAM.md` | Versão 0.7 → 0.10 (se listar) |
| Este plano | Status → `implementado` + data do merge |

Não transformar o plano em changelog eterno — update cirúrgico.

---

## 6. Mapa de arquivos (previsto)

| Ação | Path |
|---|---|
| Edit | `composer.json`, `composer.lock` |
| Add | `database/migrations/tenant/2026_08_07_*_upgrade_agent_conversations_for_laravel_ai_0_10.php` |
| Edit | `app/Repositories/AiConversationRepository.php` |
| Edit | `app/Http/Controllers/Api/V1/Tenant/AiController.php` |
| Edit (menor) | `app/Services/Ai/Agents/SIG_IA.php` |
| Edit (merge) | `config/ai.php` |
| Edit | testes AI listados na Fase 4 |
| Edit | `AGENTS.md` (+ docs se necessário) |
| **Não tocar** | `app/Services/Ai/Document/**`, jobs de análise PDF, tools de domínio salvo contract break |

---

## 7. Riscos e mitigações

| Risco | Impacto | Mitigação |
|---|---|---|
| Migration SQLite ≠ pgsql (rename/index) | Testes verdes / prod vermelho | Path por driver; validar CI pgsql; nomes de índice explícitos |
| Backfill morph errado (`App\Models\User` central vs tenant) | Conversas “órfãs” / ownership falha | Usar **somente** `App\Models\Tenant\User`; documentar que central User nunca foi dono dessas rows |
| `storeConversation` antigo esquece type | Runtime TypeError / inserts null type | Grep + teste Feature de criação |
| Merge cego de `config/ai.php` | Perda de budget/document/opencode | Diff controlado; checklist de chaves SIGAPP |
| Fake stream 0.10 quebra asserts de eventos | CI vermelho | Ajustar asserts, não desligar fakes |
| Regressão silenciosa no PDF | Ops | Smoke SIG-10 obrigatório no checklist; zero mudanças no client |
| HITL não usado grava `approval_state` null | Nenhum | Coluna nullable; API não expõe |
| Downtime em `tenants:migrate` com muitos schemas | Ops | Rodar na janela de release; migration só DDL+backfill leve |

---

## 8. Critérios de aceite (SIG-11)

- [ ] `composer show laravel/ai` ≥ 0.10.2 (alvo preferencial ≥ 0.10.3)
- [ ] Constraint `^0.10.0` no `composer.json`
- [ ] Migration tenant nova com `up`/`down`; SQLite testes + path pgsql
- [ ] Backfill `participant_type` = morph de `Tenant\User`
- [ ] Coluna `approval_state` presente em messages
- [ ] Chat SSE + histórico por usuário (ownership participant)
- [ ] Tools + telemetria de budget intactos
- [ ] Embeddings / search docs ok
- [ ] Sem regressão no fluxo documental (SIG-10)
- [ ] PHPStan nível 8 + Pint + Architecture verdes
- [ ] `AGENTS.md` atualizado se a superfície de conversas/IA mudar

---

## 9. Ordem de execução recomendada (checklist curto)

1. Branch + pré-voo greps  
2. Bump `laravel/ai` → 0.10.x + lock  
3. Migration tenant (participant + approval_state)  
4. Repository + Controller (`storeConversation` type/id)  
5. Ajustes finos SIG_IA / config merge  
6. Corrigir testes + adicionar ownership/create  
7. `analyse` + `pint` + `composer test`  
8. Smoke staging (chat + PDF)  
9. Atualizar `AGENTS.md` + marcar plano implementado  
10. Merge / release com `tenants:migrate`

---

## 10. Estimativa

| Item | Esforço |
|---|---|
| Bump + config merge | 0,5 d |
| Migration multi-driver + backfill | 0,5–1 d |
| App (controller/repo/agent) | 0,5 d |
| Testes + PHPStan | 0,5–1 d |
| Smoke staging | 0,25 d |
| **Total** | **~2–3 dias** engenharia (1 dev focado) |

---

## 11. Referências

- Ticket: SIG-11  
- Dependência entregue: SIG-10 / PR #15 (análise documental)  
- Pacote: https://github.com/laravel/ai  
- UPGRADE: https://github.com/laravel/ai/blob/0.x/UPGRADE.md  
- Código local: `AiController`, `AiConversationRepository`, `SIG_IA`, `config/ai.php`, `database/migrations/tenant/2026_03_22_193204_create_agent_conversations_table.php`  
- Plano PDF (isolamento de risco): `docs/2026-08-03-plano-sig-ia-leitura-pdf-documentos.md`
