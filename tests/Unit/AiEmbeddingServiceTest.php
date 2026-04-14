<?php

namespace Tests\Unit;

use App\Ai\Agents\SIG_IA;
use App\Ai\Tools\AnalyzeDocumentTool;
use App\Ai\Tools\SearchDocumentsTool;
use App\Services\AiEmbeddingService;
use Tests\TestCase;

class AiEmbeddingServiceTest extends TestCase
{
    public function test_chunk_text_returns_single_chunk_when_small(): void
    {
        $service = new AiEmbeddingService;
        $chunks = $service->chunkText('Texto curto');

        $this->assertCount(1, $chunks);
        $this->assertEquals('Texto curto', $chunks[0]);
    }

    public function test_chunk_text_splits_long_text(): void
    {
        $service = new AiEmbeddingService;
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
        $service = new AiEmbeddingService;
        $text = "Primeiro parágrafo.\n\nSegundo parágrafo.\n\nTerceiro parágrafo.";
        $chunks = $service->chunkText($text, 100);

        $this->assertGreaterThanOrEqual(1, count($chunks));
    }

    public function test_cosine_similarity_identical_vectors(): void
    {
        $service = new AiEmbeddingService;
        $vector = [1.0, 0.0, 0.0, 1.0];

        $similarity = $service->cosineSimilarity($vector, $vector);

        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_orthogonal_vectors(): void
    {
        $service = new AiEmbeddingService;
        $a = [1.0, 0.0];
        $b = [0.0, 1.0];

        $similarity = $service->cosineSimilarity($a, $b);

        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_empty_vectors(): void
    {
        $service = new AiEmbeddingService;

        $this->assertEquals(0.0, $service->cosineSimilarity([], []));
    }

    public function test_sig_ia_registers_rag_tools(): void
    {
        $agent = new SIG_IA;
        $tools = collect($agent->tools());

        $classNames = $tools->map(fn ($t) => class_basename($t));

        $this->assertTrue($classNames->contains('SearchDocumentsTool'), 'SearchDocumentsTool deve estar registrada');
        $this->assertTrue($classNames->contains('AnalyzeDocumentTool'), 'AnalyzeDocumentTool deve estar registrada');
    }

    public function test_search_documents_tool_has_description_and_schema(): void
    {
        $service = new AiEmbeddingService;
        $tool = new SearchDocumentsTool($service);

        $this->assertNotEmpty($tool->description());
    }

    public function test_analyze_document_tool_has_description_and_schema(): void
    {
        $tool = new AnalyzeDocumentTool;

        $this->assertNotEmpty($tool->description());
    }
}
