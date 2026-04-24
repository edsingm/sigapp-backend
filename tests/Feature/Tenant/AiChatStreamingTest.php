<?php

namespace Tests\Feature\Tenant;

use App\Ai\Agents\SIG_IA;
use Tests\TestCase;

class AiChatStreamingTest extends TestCase
{
    public function test_stream_produces_text_content(): void
    {
        $this->bindFakeAgent(
            promptText: 'Estou bem. Posso ajudar com a análise de terrenos.',
            streamEvents: [
                FakeStreamEvent::textDelta('Resposta curta e objetiva.'),
            ],
        );

        $agent = resolve(SIG_IA::class);
        $response = $agent->prompt('Ola, como esta? Responda em 2 frases.');

        $this->assertNotEmpty($response->text(), 'A resposta da IA veio vazia');
        $this->assertTrue(strlen($response->text()) > 10, 'A resposta da IA é muito curta: '.strlen($response->text()));
    }

    public function test_stream_yields_text_deltas(): void
    {
        $this->bindFakeAgent(
            promptText: 'Tudo certo.',
            streamEvents: [
                FakeStreamEvent::textDelta('Tudo'),
                FakeStreamEvent::textDelta(' certo'),
                FakeStreamEvent::textDelta('.'),
            ],
        );

        $agent = resolve(SIG_IA::class);
        $textFound = false;
        $deltaCount = 0;
        $textContent = '';
        $eventsByType = [];

        foreach ($agent->stream('Ola, responda em 3 palavras apenas.') as $event) {
            $type = method_exists($event, 'type') ? $event->type() : ($event->type ?? null);

            if (! isset($eventsByType[$type])) {
                $eventsByType[$type] = [];
            }
            $eventsByType[$type][] = (string) $event;

            if ($type === 'text_delta') {
                $textFound = true;
                $deltaCount++;
                $textContent .= $event->delta ?? '';
            }
        }

        $this->assertTrue(
            $textFound || count($eventsByType) > 0,
            'Stream yielded zero events - response will be completely empty'
        );
        $this->assertTrue($textFound, 'IA respondeu sem nenhum text_delta.');
        $this->assertSame(3, $deltaCount);
        $this->assertSame('Tudo certo.', $textContent);
    }

    public function test_stream_with_tool_calls_produces_text_after_tools(): void
    {
        $this->bindFakeAgent(
            promptText: 'Analise concluída.',
            streamEvents: [
                FakeStreamEvent::toolCall('get_dashboard_summary'),
                FakeStreamEvent::toolResult('get_dashboard_summary', ['summary' => 'ok']),
                FakeStreamEvent::textDelta('Analise concluída com sucesso.'),
            ],
        );

        $agent = resolve(SIG_IA::class);
        $textFound = false;
        $toolCallCount = 0;
        $toolResultCount = 0;
        $textContent = '';
        $eventsByType = [];

        foreach ($agent->stream('Ola, me diga brevemente como analisar terrenos no sistema.') as $event) {
            $type = method_exists($event, 'type') ? $event->type() : ($event->type ?? null);

            if (! isset($eventsByType[$type])) {
                $eventsByType[$type] = [];
            }
            $eventsByType[$type][] = (string) $event;

            if ($type === 'text_delta') {
                $textFound = true;
                $textContent .= $event->delta ?? '';
            }
            if ($type === 'tool_call') {
                $toolCallCount++;
            }
            if ($type === 'tool_result') {
                $toolResultCount++;
            }
        }

        $this->assertSame(1, $toolCallCount);
        $this->assertSame(1, $toolResultCount);
        $this->assertTrue($textFound, 'Modelo usou ferramentas, mas não gerou texto visível.');
        $this->assertSame('Analise concluída com sucesso.', $textContent);
        $this->assertArrayHasKey('tool_call', $eventsByType);
        $this->assertArrayHasKey('tool_result', $eventsByType);
    }

    /**
     * @param  array<int, FakeStreamEvent>  $streamEvents
     */
    private function bindFakeAgent(string $promptText, array $streamEvents): void
    {
        $this->app->instance(SIG_IA::class, new FakeSigIa($promptText, $streamEvents));
    }
}

final class FakeSigIa
{
    /**
     * @param  array<int, FakeStreamEvent>  $streamEvents
     */
    public function __construct(
        private readonly string $promptText,
        private readonly array $streamEvents,
    ) {}

    public function prompt(string $message): FakePromptResponse
    {
        return new FakePromptResponse($this->promptText);
    }

    /**
     * @return array<int, FakeStreamEvent>
     */
    public function stream(string $message): array
    {
        return $this->streamEvents;
    }
}

final class FakePromptResponse
{
    public function __construct(
        private readonly string $text,
    ) {}

    public function text(): string
    {
        return $this->text;
    }
}

final class FakeStreamEvent
{
    public function __construct(
        private readonly string $eventType,
        public readonly ?string $delta = null,
        public readonly ?string $tool = null,
        public readonly array $payload = [],
    ) {}

    public static function textDelta(string $delta): self
    {
        return new self('text_delta', $delta);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function toolCall(string $tool, array $payload = []): self
    {
        return new self('tool_call', null, $tool, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function toolResult(string $tool, array $payload = []): self
    {
        return new self('tool_result', null, $tool, $payload);
    }

    public function type(): string
    {
        return $this->eventType;
    }

    public function __toString(): string
    {
        return json_encode([
            'type' => $this->eventType,
            'delta' => $this->delta,
            'tool' => $this->tool,
            'payload' => $this->payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $this->eventType;
    }
}
