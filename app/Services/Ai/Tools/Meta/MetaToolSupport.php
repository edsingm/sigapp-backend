<?php

namespace App\Services\Ai\Tools\Meta;

use App\Services\Ai\Tools\AiToolResponse;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

trait MetaToolSupport
{
    /**
     * @param  list<string>  $drop
     */
    protected function forwardRequest(Request $request, array $drop = ['action']): Request
    {
        $args = $request->toArray();
        foreach ($drop as $key) {
            unset($args[$key]);
        }

        return new Request($args);
    }

    protected function action(Request $request, string $default = ''): string
    {
        return strtolower(trim((string) ($request['action'] ?? $default)));
    }

    protected function unknownAction(string $action, array $allowed): string
    {
        return AiToolResponse::validation(
            'Ação inválida: "'.($action !== '' ? $action : '(vazia)').'". '
            .'Use uma de: '.implode(', ', $allowed).'.'
        );
    }

    protected function call(Tool $tool, Request $request): string
    {
        return (string) $tool->handle($request);
    }
}
