<?php

declare(strict_types=1);

namespace App\Services\Ai\Document;

use App\Exceptions\AiBudgetExceededException;
use App\Exceptions\DocumentAnalysisUnsupportedException;
use App\Models\Tenant\AiRequestLog;
use App\Models\Tenant\Documento;
use App\Services\Ai\Tools\AiTelemetryService;
use App\Services\Tenant\DocumentAnalysisEligibility;
use App\Services\Tenant\DocumentoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Orquestra leitura de PDF e extração estruturada via OpenCode Go.
 */
class DocumentUnderstandingService
{
    public function __construct(
        private readonly OpenCodeGoDocumentClient $client,
        private readonly DocumentAnalysisEligibility $eligibility,
        private readonly DocumentoService $documentoService,
        private readonly AiTelemetryService $telemetry,
    ) {}

    public function analyze(Documento $documento): DocumentAnalysisResult
    {
        if (! $this->eligibility->canAnalyzeOnDemand($documento)) {
            throw new DocumentAnalysisUnsupportedException;
        }

        $path = (string) ($documento->file_path ?? '');
        $disk = Storage::disk($this->documentoService->storageDisk());
        if ($path === '' || ! $disk->exists($path)) {
            throw new RuntimeException('Arquivo do documento não encontrado no storage.');
        }

        $binary = $disk->get($path);
        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Não foi possível ler o conteúdo do PDF.');
        }

        $maxBytes = max(1, (int) config('ai.document_max_bytes', 10_485_760));
        if (strlen($binary) > $maxBytes) {
            throw new RuntimeException('O PDF excede o tamanho máximo permitido para análise.');
        }

        $provider = (string) config('ai.document_provider', 'opencode_go');
        $model = (string) config('ai.document_model', 'gpt-5.6-luna');
        $startedAt = microtime(true);
        $reservation = $this->tryReserveBudget($provider, $model, $documento);

        try {
            $filename = basename($path) ?: 'document.pdf';
            $raw = $this->client->analyzePdf($filename, $binary, $this->analysisInstruction());
            $parsed = $this->parseModelText($raw['text']);

            $result = new DocumentAnalysisResult(
                extractedFields: $parsed['extracted_fields'],
                confidence: $parsed['confidence'],
                limitations: $parsed['limitations'],
                provider: $provider,
                model: $raw['model'] !== '' ? $raw['model'] : $model,
                promptTokens: $raw['prompt_tokens'],
                completionTokens: $raw['completion_tokens'],
            );

            if ($reservation instanceof AiRequestLog) {
                $this->telemetry->trySettleReservation($reservation, [
                    'user_id' => Auth::id(),
                    'provider' => $result->provider,
                    'model' => $result->model,
                    'prompt_tokens' => $result->promptTokens,
                    'completion_tokens' => $result->completionTokens,
                    'total_tokens' => $result->promptTokens + $result->completionTokens,
                    'estimated_cost_usd' => $this->telemetry->estimateCost(
                        $result->provider,
                        $result->model,
                        $result->promptTokens,
                        $result->completionTokens,
                    ),
                    'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                    'tool_calls_count' => 1,
                    'tool_calls' => [['tool' => 'document.analyze', 'documento_id' => $documento->id]],
                    'status' => 'success',
                ]);
            }

            return $result;
        } catch (Throwable $exception) {
            if ($reservation instanceof AiRequestLog) {
                $this->telemetry->tryFailReservation($reservation, [
                    'user_id' => Auth::id(),
                    'provider' => $provider,
                    'model' => $model,
                    'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                    'tool_calls_count' => 1,
                    'tool_calls' => [['tool' => 'document.analyze', 'documento_id' => $documento->id]],
                    'status' => 'error',
                    'error_message' => 'document_analysis_failed',
                ]);
            }

            throw $exception;
        }
    }

    private function tryReserveBudget(string $provider, string $model, Documento $documento): ?AiRequestLog
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        try {
            return $this->telemetry->reserveBudget([
                'user_id' => Auth::id(),
                'provider' => $provider,
                'model' => $model,
                'tool_calls_count' => 1,
                'tool_calls' => [['tool' => 'document.analyze', 'documento_id' => $documento->id]],
            ], (float) config('ai.document_budget_reservation_usd', 0.05));
        } catch (AiBudgetExceededException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Falha ao reservar orçamento de análise documental', [
                'documento_id' => $documento->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function analysisInstruction(): string
    {
        return <<<'PROMPT'
Você analisa documentos PDF do domínio imobiliário brasileiro (matrículas, escrituras, IPTU, contratos, laudos, etc.).

Regras:
- Responda EXCLUSIVAMENTE com um único objeto JSON válido (sem markdown, sem comentários).
- Idioma do texto em summary e observacoes: português brasileiro.
- Extraia SOMENTE o que estiver legível no PDF. Não invente dados.
- Campos ausentes devem ser null ou listas vazias.
- confidence: número de 0 a 1 (confiança global da extração).
- limitations: lista de strings com limitações (scan ruim, páginas cortadas, campos ilegíveis, etc.).

Schema JSON obrigatório:
{
  "summary": "2 a 5 frases objetivas",
  "key_fields": {
    "titulo_ou_tipo": null,
    "partes": [],
    "datas": [],
    "numeros_referencia": [],
    "valores": [],
    "local_ou_cartorio": null,
    "observacoes": null
  },
  "confidence": 0.0,
  "limitations": []
}
PROMPT;
    }

    /**
     * @return array{
     *     extracted_fields: array{
     *         summary: string,
     *         key_fields: array{
     *             titulo_ou_tipo: mixed,
     *             partes: list<mixed>,
     *             datas: list<mixed>,
     *             numeros_referencia: list<mixed>,
     *             valores: list<mixed>,
     *             local_ou_cartorio: mixed,
     *             observacoes: mixed
     *         }
     *     },
     *     confidence: float,
     *     limitations: list<string>
     * }
     */
    private function parseModelText(string $text): array
    {
        $defaults = DocumentAnalysisResult::emptyExtractedFields();
        $limitations = [];

        $json = $this->decodeJsonObject($text);
        if ($json === null) {
            return [
                'extracted_fields' => [
                    'summary' => mb_substr(trim($text), 0, 2000),
                    'key_fields' => $defaults['key_fields'],
                ],
                'confidence' => 0.2,
                'limitations' => ['Resposta do modelo não era JSON estruturado; summary usa texto bruto truncado.'],
            ];
        }

        $summary = is_string($json['summary'] ?? null) ? trim($json['summary']) : '';
        $keyFieldsRaw = is_array($json['key_fields'] ?? null) ? $json['key_fields'] : [];
        $keyFields = $defaults['key_fields'];
        foreach (array_keys($keyFields) as $key) {
            if (! array_key_exists($key, $keyFieldsRaw)) {
                continue;
            }
            $value = $keyFieldsRaw[$key];
            if (in_array($key, ['partes', 'datas', 'numeros_referencia', 'valores'], true)) {
                $keyFields[$key] = is_array($value) ? array_values($value) : [];
            } else {
                $keyFields[$key] = $value;
            }
        }

        $confidence = is_numeric($json['confidence'] ?? null)
            ? max(0.0, min(1.0, (float) $json['confidence']))
            : 0.5;

        if (is_array($json['limitations'] ?? null)) {
            foreach ($json['limitations'] as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $limitations[] = trim($item);
                }
            }
        }

        if ($summary === '') {
            $limitations[] = 'Modelo não produziu resumo textual.';
        }

        return [
            'extracted_fields' => [
                'summary' => $summary,
                'key_fields' => $keyFields,
            ],
            'confidence' => $confidence,
            'limitations' => $limitations,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $text): ?array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        $candidates = [$trimmed];
        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $candidates[] = $matches[0];
        }

        foreach ($candidates as $candidate) {
            try {
                $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                continue;
            }
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
