# Plano: SIG IA — leitura de PDF e análise documental

**Data:** 2026-08-03  
**Status:** em implementação (fundações + auto + tools entregues no branch)  
**Escopo:** backend SIGAPP — documentos de terreno, `AnalyzeDocumentJob`, embeddings, tools do SIG IA, provider OpenCode Go (GPT-5.6 Luna) exclusivo para leitura de PDF  
**Fora de escopo:** upload de arquivo no chat; análise de imagens/Office/KMZ/DWG; schema extrator por tipo de documento; troca do modelo default do chat SIG IA

---

## 1. Decisões de produto (fechadas)

| Decisão | Valor |
|---|---|
| Superfícies | Análise auto no upload + sob demanda + tool do agente (chat por referência a documento do terreno) |
| Chat com arquivo | **Sem multipart no chat.** Usuário/agente referencia `document_id` / `terreno_id` já cadastrados |
| Formatos analisáveis | **Somente PDF** |
| Auto no upload | Tipos allowlist (abaixo) **e** extensão/MIME PDF |
| Sob demanda (API ou chat) | Qualquer PDF do terreno, com feature `documents.intelligence` |
| Provider de documentos | **OpenCode Go** — modelo **`gpt-5.6-luna`** |
| Provider do chat SIG IA | Inalterado (`AI_PROVIDER` / DeepSeek etc.) |
| Feature gate | `documents.intelligence` (existente) |
| Payload MVP | Resumo + campos-chave **genéricos** + confidence + limitations |
| Falha de análise | Nunca bloqueia upload; status `failed` + `limitations` / `error_message` seguro |
| Limite de upload terreno | **3 MB → 10 MB** |
| Envio a provider externo | Sim; telemetria sem conteúdo; redaction básica |
| Credenciais | Wiring + `.env.example` prontos; chave inserida pelo time |

### Allowlist de análise automática no upload

Disparar `AnalyzeDocumentJob` automaticamente somente se **PDF** e `tipo` ∈:

- `matricula`
- `escritura`
- `certidao_negativa`
- `iptu`
- `contrato`
- `procuracao`
- `rg_cpf`
- `laudo_ambiental`
- `levantamento_topografico`
- `viabilidade`

**Não auto:** `planta`, `comprovante_residencia`, `outros` (permanecem analisáveis **sob demanda** se forem PDF).

### Schema de saída da análise (MVP)

Persistir em `document_analyses.extracted_fields` (JSON) no formato:

```json
{
  "summary": "2–5 frases em pt-BR",
  "key_fields": {
    "titulo_ou_tipo": null,
    "partes": [],
    "datas": [],
    "numeros_referencia": [],
    "valores": [],
    "local_ou_cartorio": null,
    "observacoes": null
  }
}
```

Colunas já existentes: `status`, `provider`, `model`, `confidence`, `extracted_fields`, `limitations`, `error_message`, `completed_at`.

Regras:

- Não inventar dados; ausência → `null` / lista vazia + limitation.
- Não alterar campos de negócio do terreno/documento.
- Revisao humana permanece opcional via API de reviews existente.

---

## 2. Arquitetura alvo

```text
Upload documento (terreno) / POST .../analysis / tool GetDocuments
        │
        ▼
DocumentIntelligenceService
        │  (auto se allowlist+PDF; sempre sob demanda se PDF)
        ▼
AnalyzeDocumentJob  [queue: ai]
        │
        ▼
DocumentUnderstandingService
        │  — lê PDF do S3 (privado)
        │  — redaction mínima de prompt
        │  — chama OpenCode Go (gpt-5.6-luna) via Responses API
        │  — reserva/settle budget + telemetria (sem conteúdo)
        ▼
document_analyses (summary + key_fields + confidence + limitations)
        │
        ├─► (opcional no mesmo fluxo ou job seguinte)
        │   reindex embeddings com texto/resumo extraído
        │
        ▼
DocumentosTool / GetDocumentsHubTool  →  SIG_IA (DeepSeek) no chat
```

Princípios:

1. **Modelo dual:** DeepSeek (ou provider de chat) para diálogo/tools; Luna **somente** em `DocumentUnderstandingService`.
2. **Isolamento:** PDF não entra no stream SSE do `/ai/sig-ai`.
3. **Idempotência:** análise pendente reutilizada; re-request com análise completed pode criar nova se versão/arquivo mudou (ver §5.3).
4. **Feature + RBAC:** gate `documents.intelligence` + Gate de documento/terreno já existentes nas tools/controllers.

---

## 3. Provider OpenCode Go — configuração

### 3.1 Variáveis de ambiente (`.env.example` + `config/ai.php`)

```env
# Chat SIG IA (inalterado conceitualmente)
AI_PROVIDER=deepseek
# ...

# Análise documental (OpenCode Go / GPT-5.6 Luna)
AI_DOCUMENT_PROVIDER=opencode_go
AI_DOCUMENT_MODEL=gpt-5.6-luna
OPENCODE_GO_API_KEY=
OPENCODE_GO_BASE_URL=https://opencode.ai/zen/go/v1
AI_DOCUMENT_TIMEOUT_SECONDS=120
AI_DOCUMENT_MAX_BYTES=10485760
AI_DOCUMENT_MAX_PAGES=30
AI_DOCUMENT_BUDGET_RESERVATION_USD=0.05
AI_OPENCODE_GO_INPUT_PRICE_PER_M=0.20
AI_OPENCODE_GO_OUTPUT_PRICE_PER_M=1.20
```

Notas:

- Endpoint documentado para Luna: Responses API em `.../responses` (SDK OpenAI-style). Base URL configurável para não hardcodar.
- Preços Luna (referência pública Go): input ~$0.20 / output ~$1.20 por 1M tokens (≤ 272K); ajustar via env se a tabela mudar.
- **Não** registrar `opencode_go` como `AI_PROVIDER` default do agente; só `AI_DOCUMENT_*`.

### 3.2 Cliente HTTP

Criar `App\Services\Ai\Document\OpenCodeGoDocumentClient` (ou sob `Services/Ai/Tools/` se preferir colocalizar):

- Autenticação: `Authorization: Bearer {OPENCODE_GO_API_KEY}`
- Envio do PDF: base64 data URL / input file no formato Responses API (validar contra docs atuais na implementação)
- Prompt fixo de extração JSON (summary + key_fields genéricos)
- Timeout, retry leve (já coberto por tries do Job), erros mapeados para `DomainException` / falha de job
- **Sem** logar corpo do PDF nem resposta completa com dados sensíveis

Se o Laravel AI SDK não expuser driver OpenCode Go, **não** forçar plugin no SDK do agente — cliente dedicado é o caminho preferido (cirúrgico, testável).

### 3.3 Fallback local opcional (não obrigatório no MVP)

- Se `OPENCODE_GO_API_KEY` vazio em `local`/`testing`: job marca `failed` com limitation clara **ou** fake no teste.
- Em produção sem chave: `failed` + mensagem operacional genérica (sem stack).

---

## 4. Mudanças por camada

### 4.1 Upload e limite 10 MB

| Arquivo | Mudança |
|---|---|
| `StoreDocumentoRequest` | `max:3072` → `max:10240` (10 MB) |
| `DocumentVersionRequest` (se tiver max) | Alinhar a 10 MB |
| `DocumentoService::ALLOWED_EXTENSIONS` | Sem mudança de lista; análise só PDF |
| Mensagens de validação / i18n se existirem | Atualizar se citarem 3 MB |
| Frontend contract / docs internos | Mencionar 10 MB em `AGENTS.md` |

**Não** alterar `nginx client_max_body_size` (já 50M) nem quota `storage_gb` (continua via `StorageQuotaService`).

### 4.2 Critério “é PDF analisável”

Helper único, ex.: `App\Services\Tenant\DocumentAnalysisEligibility`:

```php
isPdf(Documento|path|mime): bool
isAutoAnalyzableTipo(string $tipo): bool
shouldAutoAnalyze(Documento $doc): bool  // feature check fica no service caller
canAnalyzeOnDemand(Documento $doc): bool // PDF + feature + auth no caller
```

Centraliza allowlist para upload, job e tool.

### 4.3 `DocumentIntelligenceService`

- `requestAnalysis(Documento, User)`:
  - Rejeitar com erro de domínio/validação se **não** for PDF (422 ou DomainException com code `DOCUMENT_ANALYSIS_UNSUPPORTED_TYPE`).
  - Exigir feature `documents.intelligence` (middleware da rota já cobre API; service deve ser seguro se chamado internamente).
  - Manter dedupe de análise `queued`/`processing` pendente.
  - Preencher `provider` = `opencode_go`, `model` = config model (não `sigapp` stub).
- Novo método interno: `dispatchAutoAnalysisIfEligible(Documento, ?User)` chamado no create/version.
- `createVersion`: após commit do arquivo, se nova versão PDF + tipo allowlist → auto-dispatch.

### 4.4 `DocumentoService::createFromUpload`

Após criar documento e `IndexDocumentEmbeddingJob`:

```php
if (eligible auto) {
    app(DocumentIntelligenceService::class)->dispatchAutoAnalysisIfEligible($documento, $user);
}
```

Upload **nunca** espera o job de análise.

### 4.5 `AnalyzeDocumentJob` (substituir stub)

1. Carregar `DocumentAnalysis` + `Documento` (+ path S3).
2. Status → `processing`.
3. Validar PDF + existência no disk.
4. Chamar `DocumentUnderstandingService::analyze(Documento): AnalysisResult`.
5. Persistir `extracted_fields`, `confidence`, `limitations`, `provider`, `model`, `completed_at`, `status=completed`.
6. Em falha: `status=failed`, `error_message` genérico, `limitations` com causa classificada (`provider_error`, `empty_pdf`, `timeout`, `unsupported`).
7. Após sucesso: enfileirar reindex de embeddings **com texto da análise** (ver §4.6) se ainda não houver chunks úteis — ou sempre reindex com summary + key_fields serializados para melhorar search.
8. Manter `failed(Throwable)`, queue `ai`, tries/timeout/backoff (timeout ≥ `AI_DOCUMENT_TIMEOUT_SECONDS`).

### 4.6 Extração + embeddings

| Componente | Ação |
|---|---|
| `IndexDocumentEmbeddingJob::extractText` | Remover fallback “só nome” para PDF quando existir análise completed; preferir `summary` + campos + eventual texto bruto retornado pelo understanding service (se guardarmos `raw_text` opcional em metadata/limitations **não** — melhor coluna ou campo em `extracted_fields.full_text` truncado **somente se necessário para RAG**; default MVP: indexar `summary` + serialização de `key_fields`) |
| `AiEmbeddingService` | Sem mudança de contrato; continua 1536 dims / budget |

Opcional MVP+: guardar `extracted_fields.text_for_rag` (texto médio truncado, ex. 20–50k chars) se a API Luna devolver texto completo; se só devolver JSON resumido, indexar o JSON.

### 4.7 Tools do SIG IA

| Tool | Mudança |
|---|---|
| `DocumentosTool` | Em `document_id`: incluir `analysis` (última completed) com summary/key_fields/confidence/limitations/status; manter `heuristica_tipo` como fallback quando sem análise; **remover disclaimer de “nunca é IA”** quando houver análise real |
| `GetDocumentsHubTool` | Estender schema: `action=analyze` (opcional) **ou** documentar que `list` + `document_id` já devolve análise e que o agente deve pedir análise via tool se status ausente |
| Novo comportamento recomendado | `action=list` + `document_id` → detalhe + latest analysis; se PDF, feature ok, e usuário pede análise no chat sem registro → tool **pode** chamar `requestAnalysis` e retornar `status=queued` pedindo ao agente informar que está processando **ou** executar análise síncrona curta (evitar síncrono no tool: preferir enqueue + “análise enfileirada; consulte novamente”) |

**Decisão de implementação no plano:**  
No MVP da tool, **não** bloquear o chat esperando Luna (timeout de tool). Fluxo:

1. Tool lê análise completed → devolve.
2. Se não houver e PDF elegível sob demanda → `requestAnalysis` → retorna `{ status: queued, analysis_id }` com code `ACCEPTED` / envelope ok.
3. Instruções do SIG_IA: se `queued`, informar ao usuário que a análise foi solicitada e que pode perguntar de novo em instantes; **não** inventar conteúdo.

Opcional P1: polling curto no job de chat não existe; frontend de documentos já tem GET analysis.

Atualizar `SIG_IA` static instructions: seção GetDocuments — conteúdo real de PDF via análise; proibir inventar trechos.

### 4.8 Resources / API de leitura

| Item | Mudança |
|---|---|
| `DocumentAnalysisResource` | Já adequado; garantir summary dentro de `extracted_fields` |
| `DocumentoResource` | Adicionar `latest_analysis` resumido (id, status, summary, confidence, completed_at) quando eager-loaded — opcional mas útil ao frontend |
| Rotas | Sem rotas novas; reutilizar intelligence routes com `check.feature:documents.intelligence` |

### 4.9 Budget e telemetria

- `DocumentUnderstandingService` chama `AiTelemetryService::reserveBudget` / `settle` / `fail` com `tool_calls` tipo `document.analyze`.
- Não gravar prompt/PDF/resposta completa nos logs de telemetria (mesmo padrão de redaction de tools).
- Estimar custo via prices `opencode_go` em `config/ai.php`.
- Se budget excedido no auto-upload: **não** falhar o upload; criar analysis `failed` com limitation `AI_BUDGET_EXCEEDED` **ou** simplesmente não dispatch + log warning. **Preferência do plano:** dispatch job; job tenta reserve; se 402/budget → analysis `failed` com limitation orçamento (upload já concluído).

### 4.10 Segurança e LGPD

- PDF privado no S3: nunca URL pública permanente; preferir stream local/base64 no request ao provider.
- `AiDataRedactor` no texto de system/user prompt se anexarmos metadados de usuário.
- Mensagens de erro ao cliente sem path S3, sem API key, sem stack.
- Documentar em `AGENTS.md`: retenção OpenCode Go Luna (abuse logs até 30 dias no provider) e que análise envia PDF ao provider externo.

---

## 5. Fluxos detalhados

### 5.1 Upload allowlist (auto)

```text
POST /documentos (PDF, tipo=matricula, feature on)
  → 201 Documento
  → IndexDocumentEmbeddingJob
  → AnalyzeDocumentJob (auto)
  → completed + embeddings reindex (se aplicável)
```

### 5.2 Upload fora da allowlist

```text
POST /documentos (PDF, tipo=outros)
  → 201
  → IndexDocumentEmbeddingJob (texto fraco até análise manual)
  → sem AnalyzeDocumentJob
```

### 5.3 Sob demanda API

```text
POST /documentos/{id}/analysis
  → 202 DocumentAnalysisResource (queued)
  → job → completed|failed
GET  /documentos/{id}/analysis
  → latest
```

Regras de re-análise:

- Se existe `queued`/`processing` → devolver a existente (já implementado).
- Se última é `completed` e mesmo arquivo → **MVP:** criar nova análise sob demanda (histórico) **ou** devolver a completed. **Preferência:** devolver completed se o `checksum`/path não mudou e `?force=1` ausente; com `force` ou nova versão → nova análise. (Se quiser mínimo: sempre enfileirar nova se completed, aceitando custo.)

**Decisão MVP mínima:** manter comportamento atual (dedupe só pending); completed permite nova análise ao POST novamente (simples, já esperado por testes se ajustados).

### 5.4 Chat SIG IA

```text
User: "Resuma o documento 45 do terreno 12"
  → SIG_IA (DeepSeek) → GetDocuments list document_id=45
  → se analysis completed: responde com summary/campos
  → se ausente e PDF: tool enfileira análise e informa "enfileirada"
  → user pergunta de novo / frontend poll analysis no módulo documentos
```

Sem mudança em `ChatAiRequest` (sem files).

---

## 6. Arquivos principais a tocar

### Criar

- `app/Services/Ai/Document/DocumentUnderstandingService.php`
- `app/Services/Ai/Document/OpenCodeGoDocumentClient.php`
- `app/Services/Ai/Document/DocumentAnalysisResult.php` (DTO readonly)
- `app/Services/Tenant/DocumentAnalysisEligibility.php`
- `tests/Unit/Services/Ai/DocumentUnderstandingServiceTest.php` (Http::fake)
- `tests/Unit/Jobs/AnalyzeDocumentJobTest.php` (substituir/estender se existir só stub)
- `tests/Feature/Tenant/DocumentAutoAnalysisTest.php` (upload allowlist vs não)
- `tests/Unit/Services/Ai/Tools/DocumentosToolAnalysisTest.php` (ou feature de tool)

### Alterar

- `config/ai.php` — document provider, prices, timeouts
- `.env.example` — chaves OpenCode Go + AI_DOCUMENT_*
- `app/Jobs/AnalyzeDocumentJob.php` — implementação real
- `app/Jobs/IndexDocumentEmbeddingJob.php` — texto a partir da análise
- `app/Services/Tenant/DocumentIntelligenceService.php`
- `app/Services/Tenant/DocumentoService.php` — auto-dispatch + (implícito) 10 MB no request
- `app/Http/Requests/Tenant/StoreDocumentoRequest.php` — max 10240
- `app/Http/Requests/Tenant/DocumentVersionRequest.php` — alinhar max se houver
- `app/Services/Ai/Tools/DocumentosTool.php`
- `app/Services/Ai/Tools/Meta/GetDocumentsHubTool.php` — schema/description
- `app/Services/Ai/Agents/SIG_IA.php` — instruções GetDocuments
- `app/Http/Resources/Tenant/DocumentoResource.php` — latest_analysis (opcional recomendado)
- `AGENTS.md` — seção IA + uploads (10 MB, OpenCode Go, auto allowlist, chat sem anexo)
- Testes existentes: `DocumentIntelligenceApiTest`, `DocumentosApiTest`, `IndexDocumentEmbeddingJobTest`

### Não criar

- Migration nova se o JSON `extracted_fields` bastar (preferir **sem migration** no MVP).
- Rota nova de chat com upload.
- Driver genérico OpenCode Go no agente de chat.

---

## 7. Prompt de análise (orientação)

System/user prompt do Luna (versionado no service):

- Idioma: pt-BR
- Extrair **apenas** o que está no PDF
- Preencher schema JSON estrito (summary + key_fields)
- `confidence` 0–1
- Listar `limitations` (scan ruim, páginas cortadas, campos ausentes)
- Não dar parecer jurídico definitivo; não alterar fatos do terreno

Resposta parseada com validação defensiva (JSON decode + defaults).

---

## 8. Fases de implementação (ordem de PRs)

### PR 1 — Fundações de config + cliente + understanding (sem auto)

1. Config/env OpenCode Go + prices + budget reservation de documento  
2. `OpenCodeGoDocumentClient` + `DocumentUnderstandingService` + DTO  
3. `DocumentAnalysisEligibility`  
4. `AnalyzeDocumentJob` real  
5. Testes unitários com `Http::fake`  
6. Ajuste `DocumentIntelligenceService` (provider/model, rejeitar non-PDF)

**Verify:** `POST .../analysis` em PDF com Http fake → `completed` com summary; non-PDF → 422; job failed path coberto.

### PR 2 — Auto no upload + limite 10 MB + embeddings

1. `StoreDocumentoRequest` / version max 10 MB  
2. Auto-dispatch allowlist no create/version  
3. `IndexDocumentEmbeddingJob` usa análise quando existir  
4. Feature tests upload allowlist vs `outros`  
5. Budget exceeded no job não quebra upload  

**Verify:** Feature tests Queue::assertPushed seletivo; DocumentosApiTest limite 10 MB.

### PR 3 — Tools + instruções SIG IA + resource

1. `DocumentosTool` expõe analysis  
2. Enfileira sob demanda se ausente  
3. Prompt SIG_IA atualizado  
4. `DocumentoResource.latest_analysis`  
5. Testes de tool envelope  

**Verify:** tool com analysis completed; tool com queued; SIG_IA tools registration tests verdes.

### PR 4 — Docs + polish

1. `AGENTS.md`  
2. `.env.example` / `.env.production.example` se existir  
3. Pint + PHPStan + Architecture suite  

**Verify:** `composer analyse`, `./vendor/bin/pint --test`, testes Feature/Unit da área.

---

## 9. Critérios de aceite

- [ ] Upload de PDF `matricula` com feature → analysis enfileirada e completa com summary + key_fields (mock de provider nos testes).
- [ ] Upload de PDF `outros` → **sem** auto job; `POST .../analysis` funciona sob demanda.
- [ ] Upload de PNG/KMZ → sem análise de conteúdo; sob demanda em non-PDF retorna erro claro.
- [ ] Upload nunca falha por queda do OpenCode Go.
- [ ] Limite de arquivo de documento = 10 MB.
- [ ] Chat `/ai/sig-ai` continua sem campo de arquivo; agente obtém conteúdo via GetDocuments.
- [ ] Tool não inventa conteúdo quando analysis empty/failed; reporta limitations.
- [ ] Telemetria registra provider/model/tokens/custo **sem** payload do PDF.
- [ ] Feature `documents.intelligence` desligada → API intelligence e search/analyze protegidos como hoje; auto-dispatch não roda.
- [ ] Chaves só em env; `.env.example` documenta `OPENCODE_GO_API_KEY` e `AI_DOCUMENT_*`.
- [ ] PHPStan 8 + Pint + Architecture verdes.

---

## 10. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| Responses API OpenCode Go diferente de OpenAI puro | Client isolado; contrato coberto por Http::fake; validar 1 smoke manual com chave real |
| PDF grande / muitas páginas | `AI_DOCUMENT_MAX_BYTES` / `MAX_PAGES`; limitation se exceder |
| Custo Luna + volume de uploads | Allowlist restrita; budget tenant; reservation dedicada |
| Chat “análise enfileirada” fricciona UX | Documentar; frontend de documentos já tem GET analysis; P1 futuro: SSE/notificação |
| Retenção 30 dias no provider Luna | Documentar em AGENTS; redaction; apenas feature paga |
| Vendor `laravel/ai` sem driver | Client HTTP dedicado — não bloquear no SDK do agente |

---

## 11. Smoke manual (pós-deploy / staging)

1. Configurar `OPENCODE_GO_API_KEY` e `AI_DOCUMENT_MODEL=gpt-5.6-luna`.  
2. Tenant com `documents.intelligence`.  
3. Upload matrícula PDF < 10 MB → aguardar job `ai` → GET analysis com summary.  
4. Upload tipo `outros` PDF → sem analysis auto → POST analysis → completed.  
5. Chat: “o que diz o documento {id}?” → resposta baseada no summary.  
6. Provider down (chave inválida) → analysis failed; documento permanece acessível.

---

## 12. Atualização de documentação obrigatória

No mesmo conjunto de mudanças (PR 4 ou junto ao PR que fechar a feature):

- `AGENTS.md` § IA: dual-model (chat vs document), OpenCode Go, allowlist, sem anexo no chat, auto-analysis.  
- `AGENTS.md` § uploads: limite 10 MB documentos terreno.  
- `.env.example` com todas as chaves novas (sem valores secretos).

---

## 13. Ordem de execução sugerida (agente)

1. PR1 fundações  
2. PR2 auto + 10 MB  
3. PR3 tools/chat por ID  
4. PR4 docs + qualidade  

Não instalar pacotes novos sem necessidade: preferir HTTP client do Laravel (`Http::`) + JSON schema no service. Se no futuro o SDK Laravel AI ganhar base URL OpenAI-compatible genérica, pode-se migrar o client sem mudar o contrato do Job.

---

**Fim do plano.** Pronto para execução quando solicitado; nenhuma implementação foi feita neste passo.
