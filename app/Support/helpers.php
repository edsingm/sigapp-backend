<?php

use App\Support\UserContext;

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
