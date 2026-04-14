<?php

namespace Tests\Feature\Tenant;

use App\Ai\Agents\SIG_IA;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Tests\TestCase;

class AiChatStreamingTest extends TestCase
{
    public function test_stream_produces_text_content(): void
    {
        $agent = resolve(SIG_IA::class);

        // Test non-streaming first to verify the model responds at all
        $response = $agent->prompt(
            'Ola, como esta? Responda em 2 frases.'
        );

        $this->assertNotEmpty($response->text(), 'A resposta da IA veio vazia');
        $this->assertTrue(strlen($response->text()) > 10, 'A resposta da IA é muito curta: ' . strlen($response->text()));
    }

    public function test_stream_yields_text_deltas(): void
    {
        $agent = resolve(SIG_IA::class);
        $textFound = false;
        $deltaCount = 0;
        $textContent = '';
        $eventsByType = [];

        $streamable = $agent->stream('Ola, responda em 3 palavras apenas.');

        foreach ($streamable as $event) {
            $type = method_exists($event, 'type') ? $event->type() : ($event->type ?? null);

            if (!isset($eventsByType[$type])) {
                $eventsByType[$type] = [];
            }
            $eventsByType[$type][] = (string) $event;

            if ($type === 'text_delta') {
                $textFound = true;
                $deltaCount++;
                if (isset($event->delta)) {
                    $textContent .= $event->delta;
                }
            }
        }

        // Log what we got for debugging
        $this->info('Event types received: ' . implode(', ', array_keys($eventsByType)));
        $this->info('Text delta count: ' . $deltaCount);
        $this->info('Text content: ' . substr($textContent, 0, 200));

        // The bug: models that only use reasoning + tool calls produce NO text_deltas
        // and then complete, leaving the frontend with nothing
        $this->assertTrue(
            $textFound || count($eventsByType) > 0,
            'Stream yielded zero events - response will be completely empty'
        );

        // This is the key assertion: verify text was actually produced
        // If this fails, it means the model replied with tool calls + reasoning but no visible text
        if (!$textFound) {
            $this->fail(
                'IA respondeu sem nenhum text_delta. Eventos recebidos: ' .
                implode(' (count: ' . implode(', ', array_map('count', $eventsByType)) . ')', array_keys($eventsByType)) .
                '. Isso confirma o bug - o modelo respondeu apenas com tool calls/reasoning sem texto visível.'
            );
        }
    }

    public function test_stream_with_tool_calls_produces_text_after_tools(): void
    {
        $agent = resolve(SIG_IA::class);
        $textFound = false;
        $toolCallCount = 0;
        $toolResultCount = 0;
        $textContent = '';
        $eventsByType = [];

        // Ask something that SHOULD trigger tool calls but still produce text explanation
        $streamable = $agent->stream(
            'Ola, me diga brevemente como analisar terrenos no sistema.'
        );

        foreach ($streamable as $event) {
            $type = method_exists($event, 'type') ? $event->type() : ($event->type ?? null);

            if (!isset($eventsByType[$type])) {
                $eventsByType[$type] = [];
            }
            $eventsByType[$type][] = (string) $event;

            if ($type === 'text_delta') {
                $textFound = true;
                if (isset($event->delta)) {
                    $textContent .= $event->delta;
                }
            }
            if ($type === 'tool_call') {
                $toolCallCount++;
            }
            if ($type === 'tool_result') {
                $toolResultCount++;
            }
        }

        $this->info('Tool calls: ' . $toolCallCount);
        $this->info('Tool results: ' . $toolResultCount);
        $this->info('Text content length: ' . strlen($textContent));

        if ($toolCallCount > 0 && !$textFound) {
            $this->fail(
                'BUG CONFIRMED: Modelo fez ' . $toolCallCount . ' tool call(s) mas não gerou nenhum texto visível. ' .
                'O modelo usou ferramentas mas não explicou o resultado para o usuário. ' .
                'Event types: ' . json_encode(array_keys($eventsByType))
            );
        }
    }
}
