<?php

use App\Services\LanguageService;
use App\Support\UserContext;
use Illuminate\Container\Container;

if (!function_exists('user')) {
    /**
     * Retorna o contexto do usuário autenticado na requisição atual.
     *
     * Uso básico:
     *   user()->name
     *   user()->getType()           // UserType::ADMIN | UserType::TENANT
     *   user()->getType()->value    // 'ADMIN' | 'TENANT'
     *
     * Retorna null se não houver usuário autenticado.
     */
    function user(): ?UserContext
    {
        $user = request()->user();

        if (!$user) {
            return null;
        }

        return new UserContext($user);
    }
}

if (!function_exists('language')) {
    /**
     * Retorna uma instância de LanguageService para o locale informado.
     * Se nenhum locale for passado, usa o locale atual da aplicação.
     *
     * Uso:
     *   language()->t('auth.login')
     *   language('en-us')->t('common.save')
     *   language()->t('validation.required', ['field' => 'nome'])
     */
    function language(?string $locale = null): LanguageService
    {
        $container = Container::getInstance();
        $resolvedLocale = $locale;

        if ($resolvedLocale === null) {
            if ($container instanceof Container && $container->bound('config')) {
                $resolvedLocale = (string) ($container->make('config')->get('app.locale') ?? 'pt_BR');
            } else {
                $resolvedLocale = 'pt_BR';
            }
        }

        return new LanguageService($resolvedLocale);
    }
}

if (!function_exists('ddApi')) {
    /**
     * Dump and die para contexto de API — retorna JSON em vez de HTML.
     *
     * Uso:
     *   ddApi($variavel);
     *   ddApi($var1, $var2, $var3);
     */
    function ddApi(mixed ...$vars): never
    {
        $output = count($vars) === 1 ? $vars[0] : $vars;

        $response = response()->json(
            ['__dd' => true, 'data' => $output],
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $response->send();
        exit(0);
    }
}
