<?php

namespace Tests\Unit;

use App\Services\Ai\Agents\SIG_IA;
use App\Services\Ai\Tools\AiToolResponse;
use App\Services\Ai\Tools\Meta\AnalyzePortfolioTool;
use App\Services\Ai\Tools\Meta\ExportPdfTool;
use App\Services\Ai\Tools\Meta\GetDocumentsHubTool;
use App\Services\Ai\Tools\Meta\GetTasksHubTool;
use App\Services\Ai\Tools\Meta\GetTerrenoProcessTool;
use App\Services\Ai\Tools\Meta\GetTerrenoTool;
use App\Services\Ai\Tools\Meta\MarketIntelTool;
use App\Services\Ai\Tools\Meta\SearchPortfolioTool;
use App\Services\Ai\Tools\RedactingToolDecorator;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class MetaToolsTest extends TestCase
{
    public function test_sig_ia_registers_exactly_eight_meta_tools(): void
    {
        $tools = collect((new SIG_IA)->tools());
        $this->assertCount(8, $tools);

        foreach ($tools as $tool) {
            $this->assertInstanceOf(Tool::class, $tool);
            $inner = $tool instanceof RedactingToolDecorator ? $tool->inner() : $tool;
            $this->assertStringStartsWith('App\\Services\\Ai\\Tools\\Meta\\', $inner::class);
        }
    }

    public function test_search_portfolio_rejects_unknown_action(): void
    {
        $payload = json_decode(
            (string) (new SearchPortfolioTool)->handle(new Request(['action' => 'nope'])),
            true
        );

        $this->assertSame(AiToolResponse::VALIDATION, $payload['code']);
        $this->assertStringContainsString('list', $payload['message']);
    }

    public function test_export_pdf_requires_auth_for_terreno_report(): void
    {
        $payload = json_decode(
            (string) app(ExportPdfTool::class)->handle(new Request([
                'action' => 'terreno_report',
                'terreno_id' => 1,
            ])),
            true
        );

        $this->assertSame(AiToolResponse::DENIED, $payload['code']);
    }

    public function test_meta_tools_expose_action_in_schema_when_expected(): void
    {
        $factory = new JsonSchemaTypeFactory;

        $withAction = [
            new SearchPortfolioTool,
            new GetTerrenoTool,
            new GetTerrenoProcessTool,
            new GetDocumentsHubTool,
            new AnalyzePortfolioTool,
            new MarketIntelTool,
            app(ExportPdfTool::class),
        ];

        foreach ($withAction as $tool) {
            $schema = $tool->schema($factory);
            $this->assertArrayHasKey('action', $schema, $tool::class.' deve expor action');
        }

        $tasksSchema = (new GetTasksHubTool)->schema($factory);
        $this->assertArrayNotHasKey('action', $tasksSchema);
        $this->assertArrayHasKey('terreno_id', $tasksSchema);
    }

    public function test_get_terreno_defaults_details_validation_without_id(): void
    {
        $payload = json_decode(
            (string) (new GetTerrenoTool)->handle(new Request(['action' => 'details'])),
            true
        );

        $this->assertSame(AiToolResponse::VALIDATION, $payload['code']);
        $this->assertStringContainsString('terreno_id', $payload['message']);
    }
}
