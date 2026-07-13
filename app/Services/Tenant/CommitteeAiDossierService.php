<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\ComiteAiDossier;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Terreno;
use App\Repositories\Tenant\CommitteeAiDossierRepository;
use App\Repositories\Tenant\CommitteeRepository;
use App\Services\Ai\Tools\AiDataRedactor;
use App\Services\Ai\Tools\AiProviderRouter;
use App\Services\Ai\Tools\AiTelemetryService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommitteeAiDossierService
{
    public const PROMPT_VERSION = 1;

    public function __construct(
        private readonly CommitteeAiDossierRepository $dossiers,
        private readonly CommitteeRepository $committee,
        private readonly AiProviderRouter $providerRouter,
        private readonly AiTelemetryService $telemetry,
        private readonly AiDataRedactor $redactor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(ComiteRevisao $review): array
    {
        $review = $this->loadReview($review);
        $dossier = $this->dossiers->findForReview($review->id);
        $currentHash = $this->inputHash($review);

        return $this->responsePayload($dossier, $currentHash);
    }

    public function createPending(ComiteRevisao $review, ?int $userId = null): ComiteAiDossier
    {
        $review = $this->loadReview($review);

        return $this->dossiers->upsertForReview($review, [
            'status' => 'pending',
            'prompt_version' => self::PROMPT_VERSION,
            'input_hash' => $this->inputHash($review),
            'generated_by' => $userId,
            'error_message' => null,
        ]);
    }

    public function generate(ComiteRevisao $review, ?int $userId = null): ComiteAiDossier
    {
        $review = $this->loadReview($review);
        $inputHash = $this->inputHash($review);
        $dossier = $this->dossiers->upsertForReview($review, [
            'status' => 'generating',
            'prompt_version' => self::PROMPT_VERSION,
            'input_hash' => $inputHash,
            'generated_by' => $userId,
            'error_message' => null,
        ]);

        $route = $this->providerRouter->getAgentWithFallback();
        $startedAt = microtime(true);

        try {
            $this->telemetry->ensureBudgetAvailable();
            $prompt = $this->redactor->redactPrompt($this->buildPrompt($review));
            $response = $route['agent']->prompt($prompt, provider: $route['providers']);
            $duration = (int) ((microtime(true) - $startedAt) * 1000);
            $provider = $response->meta->provider ?? $route['provider'];
            $model = $response->meta->model ?? $route['model'];
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;
            $cacheReadInputTokens = $response->usage->cacheReadInputTokens ?? 0;

            $this->providerRouter->recordAttempt($provider, $model, true);
            $this->telemetry->logRequest([
                'user_id' => $userId,
                'conversation_id' => null,
                'provider' => $provider,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
                'estimated_cost_usd' => $this->telemetry->estimateCost(
                    $provider,
                    $model,
                    $promptTokens,
                    $completionTokens,
                    $cacheReadInputTokens,
                ),
                'duration_ms' => $duration,
                'tool_calls_count' => $response->toolCalls->count(),
                'tool_calls' => $response->toolCalls->values()->toArray(),
                'status' => 'success',
                'ip_address' => request()?->ip(),
            ]);

            return $this->dossiers->upsertForReview($review, [
                'status' => 'ready',
                'prompt_version' => self::PROMPT_VERSION,
                'input_hash' => $inputHash,
                'sections' => $this->parseSections($response->text),
                'raw_response' => trim($response->text),
                'provider' => $provider,
                'model' => $model,
                'generated_by' => $userId,
                'generated_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $duration = (int) ((microtime(true) - $startedAt) * 1000);
            $this->providerRouter->recordAttempt(
                $route['provider'],
                $route['model'],
                false,
                $exception->getMessage(),
            );
            $this->telemetry->logRequest([
                'user_id' => $userId,
                'conversation_id' => null,
                'provider' => $route['provider'],
                'model' => $route['model'],
                'status' => 'error',
                'duration_ms' => $duration,
                'error_message' => $exception->getMessage(),
                'ip_address' => request()?->ip(),
            ]);

            $this->dossiers->upsertForReview($review, [
                'status' => 'error',
                'prompt_version' => self::PROMPT_VERSION,
                'input_hash' => $inputHash,
                'generated_by' => $userId,
                'error_message' => $exception->getMessage(),
            ]);

            Log::error('Falha ao gerar dossiê de IA do comitê', [
                'comite_revisao_id' => $review->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function parseSections(string $content): array
    {
        return [
            'pontos_apoio' => $this->extractSection($content, 'PONTOS_APOIO'),
            'concorrentes' => $this->extractSection($content, 'CONCORRENTES'),
            'infraestrutura' => $this->extractSection($content, 'INFRAESTRUTURA'),
            'juridico' => $this->extractSection($content, 'JURIDICO'),
        ];
    }

    private function extractSection(string $content, string $key): ?string
    {
        $pattern = '/\['.preg_quote($key, '/').'\]\s*([\s\S]*?)(?=\n\[[A-Z_]+\]|$)/i';

        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        $section = trim((string) ($matches[1] ?? ''));

        return $section !== '' ? $section : null;
    }

    private function loadReview(ComiteRevisao $review): ComiteRevisao
    {
        return $this->committee->loadDetail($review);
    }

    private function inputHash(ComiteRevisao $review): string
    {
        $terreno = $review->terreno;
        $viabilidade = $review->viabilidade;

        return hash('sha256', json_encode([
            'prompt_version' => self::PROMPT_VERSION,
            'review' => [
                'id' => $review->id,
                'updated_at' => $review->updated_at?->toISOString(),
                'required_departments' => $review->required_departments,
            ],
            'terreno' => $terreno instanceof Terreno ? [
                'id' => $terreno->id,
                'nome' => $terreno->nome,
                'endereco' => $terreno->endereco,
                'bairro' => $terreno->bairro,
                'cidade_code' => $terreno->cidade_code,
                'cidade' => $terreno->cidade?->city,
                'estado' => $terreno->estado,
                'updated_at' => $terreno->updated_at?->toISOString(),
            ] : null,
            'viabilidade' => $viabilidade ? [
                'id' => $viabilidade->id,
                'updated_at' => $viabilidade->updated_at?->toISOString(),
                'approval_status' => $viabilidade->approval_status,
            ] : null,
            'documentos' => $terreno instanceof Terreno
                ? $terreno->documentos
                    ->sortBy('id')
                    ->map(fn (Documento $documento): array => [
                        'id' => $documento->id,
                        'nome' => $documento->nome,
                        'tipo' => $documento->tipo,
                        'updated_at' => $documento->updated_at?->toISOString(),
                    ])
                    ->values()
                    ->all()
                : [],
        ], JSON_THROW_ON_ERROR));
    }

    private function buildPrompt(ComiteRevisao $review): string
    {
        $terreno = $review->terreno;
        $documentos = $terreno instanceof Terreno ? $terreno->documentos : collect();
        $docs = $documentos->isNotEmpty()
            ? $documentos
                ->take(12)
                ->map(fn (Documento $documento): string => sprintf(
                    '- ID %s: %s (%s)',
                    $documento->id,
                    $documento->nome ?? 'sem nome',
                    $documento->tipo ?? 'tipo não informado',
                ))
                ->implode("\n")
            : '- Nenhum documento anexado informado no dossiê atual.';

        $nome = $terreno instanceof Terreno ? $terreno->nome : "Terreno {$review->terreno_id}";
        $cidade = $terreno instanceof Terreno ? $terreno->cidade?->city : null;
        $estado = $terreno instanceof Terreno ? $terreno->estado : null;
        $endereco = $terreno instanceof Terreno
            ? collect([$terreno->endereco, $terreno->bairro, $cidade, $estado])
                ->filter()
                ->implode(' · ')
            : null;
        $contexto = collect([$endereco, $cidade, $estado])
            ->filter()
            ->unique()
            ->implode(' · ');
        $contextoLabel = $contexto !== '' ? $contexto : 'localização não informada';

        return <<<PROMPT
Você está apoiando a página de decisão de comitê do SIGAPP.

Analise o terreno ID {$review->terreno_id} ({$nome}) para preencher lacunas do dossiê.
Contexto: {$contextoLabel}.

Consulte os dados reais disponíveis no sistema e nas integrações da SIG IA quando necessário:
- entorno/pontos de apoio, vias, acessos e infraestrutura geográfica;
- concorrentes/empreendimentos similares na cidade ou região;
- documentos do terreno, matrícula, escritura, contratos e riscos jurídicos.

Documentos anexados conhecidos:
{$docs}

Responda em pt-BR, objetivo, sem nomes técnicos de ferramentas.
Use exatamente estes marcadores, nesta ordem:

[PONTOS_APOIO]
Liste pontos de apoio, transporte, comércio/serviços e observações do entorno. Se não houver dado, diga claramente se é falta de dado ou falha de consulta.

[CONCORRENTES]
Liste concorrentes/empreendimentos similares e ressalvas de cobertura. Nunca afirme que uma construtora não atua só porque não apareceu.

[INFRAESTRUTURA]
Sintetize vias, acesso, energia/água/esgoto/drenagem quando houver evidência; se depender de validação externa, diga quais pontos validar.

[JURIDICO]
Sintetize documentos, matrícula/escritura/contratos/proprietários/ações judiciais quando houver evidência. Diferencie documento ausente, conteúdo não extraído e risco identificado.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function responsePayload(?ComiteAiDossier $dossier, string $currentHash): array
    {
        if (! $dossier instanceof ComiteAiDossier) {
            return [
                'status' => 'missing',
                'stale' => false,
                'sections' => null,
                'raw_response' => null,
                'generated_at' => null,
                'error_message' => null,
            ];
        }

        return [
            'id' => $dossier->id,
            'status' => $dossier->status,
            'stale' => $dossier->status === 'ready' && $dossier->input_hash !== $currentHash,
            'sections' => $dossier->sections,
            'raw_response' => $dossier->raw_response,
            'provider' => $dossier->provider,
            'model' => $dossier->model,
            'generated_at' => $dossier->generated_at?->toIso8601String(),
            'error_message' => $dossier->error_message,
        ];
    }
}
