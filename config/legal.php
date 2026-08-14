<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Documentos legais públicos
    |--------------------------------------------------------------------------
    |
    | Caminhos canônicos no site: /legal/*. Atualize `hash` quando o texto
    | publicado mudar de forma material. Signup ainda lê a chave
    | `signup_usage_contract` — não a remova nem a mova sem migrar o service.
    |
    */

    'document_keys' => [
        'signup_usage_contract',
        'privacy_policy',
        'cookies_policy',
        'lgpd',
    ],

    'signup_usage_contract' => [
        'key' => 'signup_usage_contract',
        'title' => 'Contrato de Utilização da Plataforma SIG.APP',
        'version' => 'v2026-02-25',
        'effective_at' => '2026-02-25T00:00:00-03:00',
        'url' => '/legal/termos-de-uso',
        // Hash do texto publicado; não recálcule só porque o path mudou de /juridico/.
        'hash' => '3e1c28b6d333a03aab2df664208aba977566aaab9eed322c25331358789105c2',
        'requires_acceptance' => true,
    ],

    'privacy_policy' => [
        'key' => 'privacy_policy',
        'title' => 'Política de Privacidade',
        'version' => 'v2026-08-14',
        'effective_at' => '2026-08-14T00:00:00-03:00',
        'url' => '/legal/privacidade',
        'hash' => '359db4cd864803242700f42c387e9a9f0d42fa7fe14fbd994c47b06682520f84',
        'requires_acceptance' => true,
    ],

    'cookies_policy' => [
        'key' => 'cookies_policy',
        'title' => 'Política de Cookies',
        'version' => 'v2026-08-14',
        'effective_at' => '2026-08-14T00:00:00-03:00',
        'url' => '/legal/cookies',
        'hash' => '64a8bd4980e2d8184fd414ef157401f248fd80b349486be141bd3164653df3b2',
        'requires_acceptance' => false,
    ],

    'lgpd' => [
        'key' => 'lgpd',
        'title' => 'LGPD — direitos do titular',
        'version' => 'v2026-08-14',
        'effective_at' => '2026-08-14T00:00:00-03:00',
        'url' => '/legal/lgpd',
        'hash' => 'a3f5e872fc86361cd97566f2f2ec208c0ff391b13f51e0ab4672fe733d232770',
        'requires_acceptance' => false,
    ],

];
