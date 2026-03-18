<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Manipula uma requisição de entrada.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Força o cabeçalho Accept para JSON apenas se não estiver configurado para algo específico
        if (!$request->headers->has('Accept') || $request->header('Accept') === '*/*') {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
