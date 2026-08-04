<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Document;

use App\Services\Ai\Document\OpenCodeGoDocumentClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenCodeGoDocumentClientTest extends TestCase
{
    public function test_sends_pdf_to_responses_endpoint_and_extracts_text(): void
    {
        config([
            'ai.providers.opencode_go.key' => 'test-key',
            'ai.providers.opencode_go.url' => 'https://opencode.ai/zen/go/v1',
            'ai.document_model' => 'gpt-5.6-luna',
            'ai.document_timeout_seconds' => 60,
            'ai.document_max_bytes' => 1_000_000,
        ]);

        Http::fake([
            'https://opencode.ai/zen/go/v1/responses' => Http::response([
                'model' => 'gpt-5.6-luna',
                'output_text' => '{"summary":"ok","key_fields":{},"confidence":0.9,"limitations":[]}',
                'usage' => [
                    'input_tokens' => 100,
                    'output_tokens' => 20,
                ],
            ], 200),
        ]);

        $client = new OpenCodeGoDocumentClient;
        $result = $client->analyzePdf('matricula.pdf', '%PDF-1.4 fake', 'instruction');

        $this->assertStringContainsString('summary', $result['text']);
        $this->assertSame(100, $result['prompt_tokens']);
        $this->assertSame(20, $result['completion_tokens']);
        $this->assertSame('gpt-5.6-luna', $result['model']);

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $content = $data['input'][0]['content'] ?? [];
            $filePart = null;
            foreach (is_array($content) ? $content : [] as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'input_file') {
                    $filePart = $part;
                    break;
                }
            }

            $fileData = is_array($filePart) ? (string) ($filePart['file_data'] ?? '') : '';

            return $request->url() === 'https://opencode.ai/zen/go/v1/responses'
                && ($data['model'] ?? null) === 'gpt-5.6-luna'
                && is_array($data['input'] ?? null)
                && str_starts_with($fileData, 'data:application/pdf;base64,')
                && ! str_contains($fileData, 'PDF attachment removed');
        });
    }

    public function test_requires_api_key(): void
    {
        config(['ai.providers.opencode_go.key' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPENCODE_GO_API_KEY');

        (new OpenCodeGoDocumentClient)->analyzePdf('a.pdf', 'x', 'y');
    }
}
