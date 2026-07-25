<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant\Documento;
use App\Repositories\Tenant\AiEmbeddingRepository;
use App\Services\Ai\Agents\SIG_IA;
use App\Services\Ai\Tools\AiEmbeddingService;
use App\Services\Ai\Tools\DocumentosTool;
use App\Services\Ai\Tools\RedactingToolDecorator;
use App\Services\Ai\Tools\SearchDocumentsTool;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\ProviderTool;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Tests\TestCase;

class AiEmbeddingServiceTest extends TestCase
{
    public function test_chunk_text_returns_single_chunk_when_small(): void
    {
        $service = app(AiEmbeddingService::class);
        $chunks = $service->chunkText('Texto curto');

        $this->assertCount(1, $chunks);
        $this->assertEquals('Texto curto', $chunks[0]);
    }

    public function test_chunk_text_splits_long_text(): void
    {
        $service = app(AiEmbeddingService::class);
        // Cria texto com parágrafos longos
        $longText = str_repeat('Lorem ipsum. ', 500);
        $chunks = $service->chunkText($longText, 1500);

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(1500, mb_strlen($chunk));
        }
    }

    public function test_chunk_text_respects_paragraph_boundaries(): void
    {
        $service = app(AiEmbeddingService::class);
        $text = "Primeiro parágrafo.\n\nSegundo parágrafo.\n\nTerceiro parágrafo.";
        $chunks = $service->chunkText($text, 100);

        $this->assertGreaterThanOrEqual(1, count($chunks));
    }

    public function test_cosine_similarity_identical_vectors(): void
    {
        $service = app(AiEmbeddingService::class);
        $vector = [1.0, 0.0, 0.0, 1.0];

        $similarity = $service->cosineSimilarity($vector, $vector);

        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_orthogonal_vectors(): void
    {
        $service = app(AiEmbeddingService::class);
        $a = [1.0, 0.0];
        $b = [0.0, 1.0];

        $similarity = $service->cosineSimilarity($a, $b);

        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_empty_vectors(): void
    {
        $service = app(AiEmbeddingService::class);

        $this->assertEquals(0.0, $service->cosineSimilarity([], []));
    }

    public function test_failed_embedding_generation_does_not_replace_the_active_index(): void
    {
        $documento = (new Documento)->forceFill([
            'id' => 42,
            'terreno_id' => 7,
            'nome' => 'Matrícula',
            'tipo' => 'matricula',
            'categoria' => 'juridico',
        ]);

        /** @var AiEmbeddingRepository&MockObject $repository */
        $repository = $this->createMock(AiEmbeddingRepository::class);
        $repository->expects($this->once())->method('findDocumento')->with(42)->willReturn($documento);
        $repository->expects($this->never())->method('replaceDocumentIndex');

        $attempts = 0;
        /** @var AiEmbeddingService&MockObject $service */
        $service = $this->getMockBuilder(AiEmbeddingService::class)
            ->setConstructorArgs([$repository])
            ->onlyMethods(['generateEmbedding'])
            ->getMock();
        $service->expects($this->exactly(2))
            ->method('generateEmbedding')
            ->willReturnCallback(static function () use (&$attempts): array {
                $attempts++;
                if ($attempts === 2) {
                    throw new RuntimeException('Provider indisponível.');
                }

                return [0.1, 0.2];
            });

        $this->expectException(RuntimeException::class);

        $service->indexDocument(
            42,
            str_repeat('Primeiro parágrafo. ', 80)."\n\n".str_repeat('Segundo parágrafo. ', 80),
        );
    }

    public function test_sig_ia_registers_rag_tools(): void
    {
        $agent = new SIG_IA;
        $tools = collect($agent->tools());

        $classNames = $tools->map(
            fn (Tool|ProviderTool $tool) => class_basename(
                $tool instanceof RedactingToolDecorator ? $tool->inner() : $tool
            )
        );

        $this->assertTrue($classNames->contains('SearchDocumentsTool'), 'SearchDocumentsTool deve estar registrada');
        $this->assertTrue($classNames->contains('DocumentosTool'), 'DocumentosTool deve estar registrada');
    }

    public function test_search_documents_tool_has_description_and_schema(): void
    {
        $service = app(AiEmbeddingService::class);
        $tool = new SearchDocumentsTool($service);

        $this->assertNotEmpty($tool->description());
    }

    public function test_analyze_document_tool_has_description_and_schema(): void
    {
        $tool = new DocumentosTool;

        $this->assertNotEmpty($tool->description());
    }
}
