<?php

namespace App\Services\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Envolve qualquer Tool e aplica redação de PII no output antes de enviá-lo ao LLM.
 *
 * Campos como cpf, cnpj, email, telefone são mascarados no JSON retornado pela tool.
 * O framework identifica a tool pelo nome: forwarda class_basename() do inner tool
 * via name(), respeitando o ToolNameResolver do Laravel AI.
 */
class RedactingToolDecorator implements Tool
{
    public function __construct(
        private readonly Tool $inner,
        private readonly AiDataRedactor $redactor,
    ) {}

    public function name(): string
    {
        return is_callable([$this->inner, 'name'])
            ? $this->inner->name()
            : class_basename($this->inner);
    }

    public function description(): Stringable|string
    {
        return $this->inner->description();
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->inner->schema($schema);
    }

    public function handle(Request $request): Stringable|string
    {
        $output = (string) $this->inner->handle($request);

        $decoded = json_decode($output, true);

        if (is_array($decoded)) {
            return json_encode(
                $this->redactor->redactPayload($decoded),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ) ?: $output;
        }

        return $this->redactor->redactText($output);
    }
}
