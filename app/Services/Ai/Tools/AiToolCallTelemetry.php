<?php

namespace App\Services\Ai\Tools;

use Illuminate\Support\Collection;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;

/**
 * Monta telemetria de tool calls a partir dos eventos de stream do Laravel AI.
 */
final class AiToolCallTelemetry
{
    /**
     * @return list<array{
     *   tool: string,
     *   input: mixed,
     *   step: int,
     *   code: ?string,
     *   successful: ?bool,
     *   result_bytes: ?int,
     *   duration_ms: ?int,
     *   error: ?string
     * }>
     */
    public static function fromStreamEvents(Collection $events, AiDataRedactor $redactor): array
    {
        /** @var array<string, array<string, mixed>> $byId */
        $byId = [];
        $step = 0;

        foreach ($events as $event) {
            if ($event instanceof ToolCall) {
                $step++;
                $id = $event->toolCall->id;
                $byId[$id] = [
                    'tool' => $event->toolCall->name,
                    'input' => $redactor->redactPayload($event->toolCall->arguments),
                    'step' => $step,
                    'code' => null,
                    'successful' => null,
                    'result_bytes' => null,
                    'duration_ms' => null,
                    'error' => null,
                    '_started_at' => $event->timestamp,
                ];
            }
        }

        foreach ($events as $event) {
            if (! $event instanceof ToolResult) {
                continue;
            }

            $id = $event->toolResult->id;
            $resultRaw = $event->toolResult->result;
            $resultString = is_string($resultRaw)
                ? $resultRaw
                : (json_encode($resultRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

            $code = self::extractCode($resultString);

            if (! isset($byId[$id])) {
                $step++;
                $byId[$id] = [
                    'tool' => $event->toolResult->name,
                    'input' => $redactor->redactPayload($event->toolResult->arguments),
                    'step' => $step,
                    'code' => $code,
                    'successful' => $event->successful,
                    'result_bytes' => strlen($resultString),
                    'duration_ms' => null,
                    'error' => $event->error,
                ];

                continue;
            }

            $started = $byId[$id]['_started_at'] ?? null;
            $duration = is_int($started) ? max(0, $event->timestamp - $started) : null;

            $byId[$id]['code'] = $code;
            $byId[$id]['successful'] = $event->successful;
            $byId[$id]['result_bytes'] = strlen($resultString);
            $byId[$id]['duration_ms'] = $duration;
            $byId[$id]['error'] = $event->error;
            unset($byId[$id]['_started_at']);
        }

        return array_values(array_map(static function (array $row): array {
            unset($row['_started_at']);

            return $row;
        }, $byId));
    }

    public static function extractCode(?string $result): ?string
    {
        if ($result === null || $result === '') {
            return null;
        }

        $decoded = json_decode($result, true);
        if (! is_array($decoded)) {
            return null;
        }

        $code = $decoded['code'] ?? null;

        return is_string($code) ? $code : null;
    }

    /**
     * Agrega estatísticas de tools a partir de logs (campo tool_calls).
     *
     * @param  Collection<int, object|array<string, mixed>>  $logs
     * @return array{
     *   total_tool_calls: int,
     *   top_tools: array<string, int>,
     *   top_codes: array<string, int>,
     *   p95_tool_duration_ms: int,
     *   avg_result_bytes: int
     * }
     */
    public static function summarizeFromLogs(Collection $logs): array
    {
        $byTool = [];
        $byCode = [];
        $durations = [];
        $bytes = [];

        foreach ($logs as $log) {
            $calls = is_array($log) ? ($log['tool_calls'] ?? null) : ($log->tool_calls ?? null);
            if (! is_array($calls)) {
                continue;
            }

            foreach ($calls as $call) {
                if (! is_array($call)) {
                    continue;
                }

                $tool = (string) ($call['tool'] ?? 'unknown');
                $code = (string) ($call['code'] ?? 'UNKNOWN');
                $byTool[$tool] = ($byTool[$tool] ?? 0) + 1;
                $byCode[$code] = ($byCode[$code] ?? 0) + 1;

                if (isset($call['duration_ms']) && is_numeric($call['duration_ms'])) {
                    $durations[] = (int) $call['duration_ms'];
                }
                if (isset($call['result_bytes']) && is_numeric($call['result_bytes'])) {
                    $bytes[] = (int) $call['result_bytes'];
                }
            }
        }

        arsort($byTool);
        arsort($byCode);
        sort($durations);

        $p95 = 0;
        if ($durations !== []) {
            $index = (int) ceil(0.95 * count($durations)) - 1;
            $p95 = $durations[max(0, $index)] ?? 0;
        }

        return [
            'total_tool_calls' => array_sum($byTool),
            'top_tools' => array_slice($byTool, 0, 20, true),
            'top_codes' => array_slice($byCode, 0, 20, true),
            'p95_tool_duration_ms' => $p95,
            'avg_result_bytes' => $bytes === [] ? 0 : (int) round(array_sum($bytes) / count($bytes)),
        ];
    }
}
