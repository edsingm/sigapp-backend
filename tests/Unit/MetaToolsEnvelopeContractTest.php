<?php

namespace Tests\Unit;

use App\Services\Ai\Tools\AiToolResponse;
use App\Services\Ai\Tools\Meta\AnalyzePortfolioTool;
use App\Services\Ai\Tools\Meta\ExportPdfTool;
use App\Services\Ai\Tools\Meta\GetDocumentsHubTool;
use App\Services\Ai\Tools\Meta\GetTerrenoProcessTool;
use App\Services\Ai\Tools\Meta\GetTerrenoTool;
use App\Services\Ai\Tools\Meta\MarketIntelTool;
use App\Services\Ai\Tools\Meta\SearchPortfolioTool;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Contrato mínimo: meta-tools devolvem envelope JSON com code conhecido
 * em cenários de validação/deny (sem precisar de tenant/DB completo).
 */
class MetaToolsEnvelopeContractTest extends TestCase
{
    public function test_meta_tools_return_envelope_with_expected_codes(): void
    {
        $cases = [
            [new SearchPortfolioTool, ['action' => 'invalid'], AiToolResponse::VALIDATION],
            [new GetTerrenoTool, ['action' => 'details'], AiToolResponse::VALIDATION],
            [new GetTerrenoProcessTool, ['action' => 'nope'], AiToolResponse::VALIDATION],
            [new GetDocumentsHubTool, ['action' => 'weird'], AiToolResponse::VALIDATION],
            [new AnalyzePortfolioTool, ['action' => 'nope'], AiToolResponse::VALIDATION],
            [new MarketIntelTool, ['action' => 'nope'], AiToolResponse::VALIDATION],
            [app(ExportPdfTool::class), ['action' => 'terreno_report', 'terreno_id' => 1], AiToolResponse::DENIED],
        ];

        foreach ($cases as [$tool, $args, $expectedCode]) {
            $payload = json_decode((string) $tool->handle(new Request($args)), true);

            $this->assertIsArray($payload, $tool::class);
            $this->assertArrayHasKey('ok', $payload);
            $this->assertArrayHasKey('code', $payload);
            $this->assertArrayHasKey('message', $payload);
            $this->assertArrayHasKey('data', $payload);
            $this->assertSame($expectedCode, $payload['code'], $tool::class);
        }
    }
}
