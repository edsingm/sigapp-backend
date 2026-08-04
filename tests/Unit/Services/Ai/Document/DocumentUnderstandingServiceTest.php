<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Document;

use App\Exceptions\DocumentAnalysisUnsupportedException;
use App\Models\Tenant\Documento;
use App\Services\Ai\Document\DocumentUnderstandingService;
use App\Services\Ai\Document\OpenCodeGoDocumentClient;
use App\Services\Ai\Tools\AiTelemetryService;
use App\Services\Tenant\DocumentAnalysisEligibility;
use App\Services\Tenant\DocumentoService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DocumentUnderstandingServiceTest extends TestCase
{
    public function test_rejects_non_pdf(): void
    {
        $documento = new Documento;
        $documento->file_path = 'documentos/foto.png';
        $documento->id = 1;

        /** @var OpenCodeGoDocumentClient&MockInterface $client */
        $client = Mockery::mock(OpenCodeGoDocumentClient::class);
        /** @var DocumentoService&MockInterface $documentoService */
        $documentoService = Mockery::mock(DocumentoService::class);
        /** @var AiTelemetryService&MockInterface $telemetry */
        $telemetry = Mockery::mock(AiTelemetryService::class);

        $service = new DocumentUnderstandingService(
            $client,
            new DocumentAnalysisEligibility,
            $documentoService,
            $telemetry,
        );

        $this->expectException(DocumentAnalysisUnsupportedException::class);
        $service->analyze($documento);
    }

    public function test_parses_json_from_provider_and_returns_result(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('documentos/matricula.pdf', '%PDF-1.4 content');

        $documento = new Documento;
        $documento->id = 42;
        $documento->file_path = 'documentos/matricula.pdf';
        $documento->tipo = 'matricula';

        /** @var OpenCodeGoDocumentClient&MockInterface $client */
        $client = Mockery::mock(OpenCodeGoDocumentClient::class);
        $client->shouldReceive('analyzePdf')
            ->once()
            ->andReturn([
                'text' => json_encode([
                    'summary' => 'Matrícula do imóvel com área de 1.000 m².',
                    'key_fields' => [
                        'titulo_ou_tipo' => 'Matrícula',
                        'partes' => ['João'],
                        'datas' => ['2020-01-01'],
                        'numeros_referencia' => ['12345'],
                        'valores' => [],
                        'local_ou_cartorio' => '1º RI',
                        'observacoes' => null,
                    ],
                    'confidence' => 0.88,
                    'limitations' => [],
                ], JSON_THROW_ON_ERROR),
                'prompt_tokens' => 50,
                'completion_tokens' => 30,
                'model' => 'gpt-5.6-luna',
            ]);

        /** @var DocumentoService&MockInterface $documentoService */
        $documentoService = Mockery::mock(DocumentoService::class);
        $documentoService->shouldReceive('storageDisk')->andReturn('s3');

        /** @var AiTelemetryService&MockInterface $telemetry */
        $telemetry = Mockery::mock(AiTelemetryService::class);
        $telemetry->shouldReceive('reserveBudget')->never();

        $service = new DocumentUnderstandingService(
            $client,
            new DocumentAnalysisEligibility,
            $documentoService,
            $telemetry,
        );

        $result = $service->analyze($documento);

        $this->assertSame('Matrícula do imóvel com área de 1.000 m².', $result->extractedFields['summary']);
        $this->assertSame('Matrícula', $result->extractedFields['key_fields']['titulo_ou_tipo']);
        $this->assertSame(['João'], $result->extractedFields['key_fields']['partes']);
        $this->assertEqualsWithDelta(0.88, $result->confidence, 0.001);
        $this->assertSame('opencode_go', $result->provider);
        $this->assertSame(50, $result->promptTokens);
    }
}
