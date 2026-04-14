<?php

namespace App\Http\Middleware;

use App\Services\AiTelemetryService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiTelemetryMiddleware
{
    public function __construct(
        protected AiTelemetryService $telemetryService
    ) {}

    /**
     * Manipula uma requisição de entrada e captura telemetria.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        return $next($request);
    }

    /**
     * Terminate middleware — executado após a resposta ser enviada.
     * Aqui registramos a telemetria sem impactar a latência da resposta.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Este middleware registra telemetria no terminate hook
        // para evitar impactar o tempo de resposta.
        // O log detalhado é feito pelo controller que tem acesso ao
        // $streamable->usage e $streamable->events após o stream.
    }
}
