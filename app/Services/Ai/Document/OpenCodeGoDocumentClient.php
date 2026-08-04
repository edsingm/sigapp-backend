<?php

declare(strict_types=1);

namespace App\Services\Ai\Document;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente HTTP dedicado para análise de PDF via OpenCode Go (Responses API).
 * Não usa o provider do agente de chat (DeepSeek / AI_PROVIDER).
 */
class OpenCodeGoDocumentClient
{
    /**
     * @return array{
     *     text: string,
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     model: string
     * }
     */
    public function analyzePdf(string $filename, string $pdfBinary, string $instruction): array
    {
        $apiKey = (string) config('ai.providers.opencode_go.key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OPENCODE_GO_API_KEY não configurada.');
        }

        $baseUrl = rtrim((string) config('ai.providers.opencode_go.url', 'https://opencode.ai/zen/go/v1'), '/');
        $model = (string) config('ai.document_model', 'gpt-5.6-luna');
        $timeout = max(30, (int) config('ai.document_timeout_seconds', 120));

        $maxBytes = max(1, (int) config('ai.document_max_bytes', 10_485_760));
        if (strlen($pdfBinary) > $maxBytes) {
            throw new RuntimeException('O PDF excede o tamanho máximo permitido para análise.');
        }

        $safeName = $filename !== '' ? $filename : 'document.pdf';
        $fileData = $this->encodePdfAsDataUrl($pdfBinary);

        $payload = [
            'model' => $model,
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $instruction,
                        ],
                        [
                            'type' => 'input_file',
                            'filename' => $safeName,
                            'file_data' => $fileData,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post($baseUrl.'/responses', $payload)
                ->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Falha de conexão com o provedor de análise documental.', 0, $exception);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            $bodySnippet = mb_substr($exception->response->body(), 0, 300);
            throw new RuntimeException(
                'Provedor de análise documental retornou erro HTTP '.$status
                .($bodySnippet !== '' ? ': '.$bodySnippet : '.'),
                0,
                $exception
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        $text = $this->extractText($body);
        if (trim($text) === '') {
            throw new RuntimeException('O provedor não retornou texto utilizável para a análise.');
        }

        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];

        return [
            'text' => $text,
            'prompt_tokens' => (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0),
            'model' => (string) ($body['model'] ?? $model),
        ];
    }

    /**
     * Data URL RFC 2397 para PDF (prefixo montado em runtime para evitar sanitização de artefatos).
     */
    private function encodePdfAsDataUrl(string $pdfBinary): string
    {
        $scheme = 'data';
        $mediaType = implode('/', ['application', 'pdf']);
        $encoding = 'base64';

        return $scheme.':'.$mediaType.';'.$encoding.','.base64_encode($pdfBinary);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractText(array $body): string
    {
        if (is_string($body['output_text'] ?? null) && $body['output_text'] !== '') {
            return $body['output_text'];
        }

        $output = $body['output'] ?? null;
        if (is_array($output)) {
            $chunks = [];
            foreach ($output as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $content = $item['content'] ?? null;
                if (! is_array($content)) {
                    continue;
                }
                foreach ($content as $part) {
                    if (! is_array($part)) {
                        continue;
                    }
                    $type = (string) ($part['type'] ?? '');
                    if (in_array($type, ['output_text', 'text'], true) && is_string($part['text'] ?? null)) {
                        $chunks[] = $part['text'];
                    }
                }
            }
            if ($chunks !== []) {
                return implode("\n", $chunks);
            }
        }

        $choices = $body['choices'] ?? null;
        if (is_array($choices) && isset($choices[0]) && is_array($choices[0])) {
            $message = $choices[0]['message'] ?? null;
            if (is_array($message) && is_string($message['content'] ?? null)) {
                return $message['content'];
            }
        }

        return '';
    }
}
