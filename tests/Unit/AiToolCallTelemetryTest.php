<?php

namespace Tests\Unit;

use App\Services\Ai\Tools\AiDataRedactor;
use App\Services\Ai\Tools\AiToolCallTelemetry;
use App\Services\Ai\Tools\AiToolResponse;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\ToolCall as ToolCallData;
use Laravel\Ai\Responses\Data\ToolResult as ToolResultData;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Tests\TestCase;

class AiToolCallTelemetryTest extends TestCase
{
    public function test_extract_code_from_envelope(): void
    {
        $json = AiToolResponse::ok(['x' => 1], 'ok');
        $this->assertSame(AiToolResponse::OK, AiToolCallTelemetry::extractCode($json));
        $this->assertSame(AiToolResponse::DENIED, AiToolCallTelemetry::extractCode(
            AiToolResponse::denied('nope')
        ));
        $this->assertNull(AiToolCallTelemetry::extractCode('not-json'));
    }

    public function test_from_stream_events_merges_call_and_result(): void
    {
        $redactor = app(AiDataRedactor::class);

        $call = new ToolCall(
            id: 'evt-1',
            toolCall: new ToolCallData(
                id: 'tool-1',
                name: 'SearchPortfolio',
                arguments: ['action' => 'list'],
            ),
            timestamp: 1000,
        );

        $result = new ToolResult(
            id: 'evt-2',
            toolResult: new ToolResultData(
                id: 'tool-1',
                name: 'SearchPortfolio',
                arguments: ['action' => 'list'],
                result: AiToolResponse::empty('nada'),
            ),
            successful: true,
            error: null,
            timestamp: 1450,
        );

        $rows = AiToolCallTelemetry::fromStreamEvents(collect([$call, $result]), $redactor);

        $this->assertCount(1, $rows);
        $this->assertSame('SearchPortfolio', $rows[0]['tool']);
        $this->assertSame(1, $rows[0]['step']);
        $this->assertSame(AiToolResponse::EMPTY, $rows[0]['code']);
        $this->assertTrue($rows[0]['successful']);
        $this->assertSame(450, $rows[0]['duration_ms']);
        $this->assertGreaterThan(0, $rows[0]['result_bytes']);
    }

    public function test_summarize_from_logs(): void
    {
        $logs = collect([
            (object) ['tool_calls' => [
                ['tool' => 'SearchPortfolio', 'code' => 'OK', 'duration_ms' => 10, 'result_bytes' => 100],
                ['tool' => 'GetTerreno', 'code' => 'DENIED', 'duration_ms' => 5, 'result_bytes' => 50],
            ]],
            (object) ['tool_calls' => [
                ['tool' => 'SearchPortfolio', 'code' => 'OK', 'duration_ms' => 20, 'result_bytes' => 200],
            ]],
        ]);

        $summary = AiToolCallTelemetry::summarizeFromLogs($logs);

        $this->assertSame(3, $summary['total_tool_calls']);
        $this->assertSame(2, $summary['top_tools']['SearchPortfolio']);
        $this->assertSame(2, $summary['top_codes']['OK']);
        $this->assertSame(1, $summary['top_codes']['DENIED']);
        $this->assertSame(20, $summary['p95_tool_duration_ms']);
        $this->assertSame(117, $summary['avg_result_bytes']);
    }
}
