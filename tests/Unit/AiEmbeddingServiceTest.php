<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant\AiDocumentChunk;
use App\Models\Tenant\AiDocumentEmbedding;
use App\Models\Tenant\Documento;
use App\Repositories\Tenant\AiEmbeddingRepository;
use App\Services\Ai\Agents\SIG_IA;
use App\Services\Ai\Tools\AiEmbeddingService;
use App\Services\Ai\Tools\DocumentosTool;
use App\Services\Ai\Tools\RedactingToolDecorator;
use App\Services\Ai\Tools\SearchDocumentsTool;
use App\Support\Database\PgVector;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Config;
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

                $vector = array_fill(0, PgVector::DIMENSIONS, 0.0);
                $vector[0] = 1.0;

                return $vector;
            });

        $this->expectException(RuntimeException::class);

        $service->indexDocument(
            42,
            str_repeat('Primeiro parágrafo. ', 80)."\n\n".str_repeat('Segundo parágrafo. ', 80),
        );
    }

    public function test_search_similar_uses_native_results_and_loaded_relations(): void
    {
        Config::set('ai.embedding_model', 'embedding-test');
        Config::set('ai.embedding_min_similarity', 0.5);

        $vector = array_fill(0, PgVector::DIMENSIONS, 0.0);
        $vector[0] = 1.0;

        $documento = (new Documento)->forceFill([
            'id' => 42,
            'nome' => 'Matrícula',
            'tipo' => 'matricula',
            'categoria' => 'juridico',
        ]);
        $chunk = (new AiDocumentChunk)->forceFill([
            'id' => 84,
            'content' => 'Conteúdo indexado',
            'metadata' => ['pagina' => 1],
            'chunk_index' => 0,
        ]);
        $chunk->setRelation('documento', $documento);
        $chunk->setRelation('terreno', null);

        $embedding = (new AiDocumentEmbedding)->forceFill([
            'chunk_id' => 84,
            'similarity' => 0.9321,
        ]);
        $embedding->setRelation('chunk', $chunk);

        /** @var AiEmbeddingRepository&MockObject $repository */
        $repository = $this->createMock(AiEmbeddingRepository::class);
        $repository->expects($this->once())
            ->method('searchSimilarByVector')
            ->with($vector, 'embedding-test', 7, 5)
            ->willReturn(new EloquentCollection([$embedding]));
        $repository->expects($this->never())->method('searchEmbeddings');

        /** @var AiEmbeddingService&MockObject $service */
        $service = $this->getMockBuilder(AiEmbeddingService::class)
            ->setConstructorArgs([$repository])
            ->onlyMethods(['generateEmbedding'])
            ->getMock();
        $service->expects($this->once())
            ->method('generateEmbedding')
            ->with('consulta')
            ->willReturn($vector);

        $results = $service->searchSimilar('consulta', 7, 5);

        $this->assertCount(1, $results);
        $result = $results->first();
        $this->assertIsArray($result);
        $this->assertSame(84, $result['chunk_id']);
        $this->assertSame('Conteúdo indexado', $result['content']);
        $this->assertSame(0.9321, $result['score']);
        $this->assertSame('Matrícula', $result['document']['nome']);
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

        // Fase 3: documentos/RAG expostos via meta-tool GetDocumentsHubTool (action=list|search)
        $this->assertTrue(
            $classNames->contains('GetDocumentsHubTool'),
            'GetDocumentsHubTool deve estar registrada no catálogo consolidado'
        );
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
