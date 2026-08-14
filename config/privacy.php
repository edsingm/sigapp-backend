<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Canal do encarregado (DPO)
    |--------------------------------------------------------------------------
    |
    | Pedidos manuais entram por e-mail. Não existe formulário DSAR no site.
    |
    */

    'dpo_email' => env('PRIVACY_DPO_EMAIL', 'dpo@sigapp.com.br'),

    /*
    |--------------------------------------------------------------------------
    | Retenções (dias, salvo onde indicado)
    |--------------------------------------------------------------------------
    */

    'consent_log_retention_days' => (int) env('CONSENT_LOG_RETENTION_DAYS', 180),
    'cancelled_tenant_retention_days' => (int) env('PRIVACY_CANCELLED_TENANT_RETENTION_DAYS', 90),
    'soft_delete_retention_days' => (int) env('PRIVACY_SOFT_DELETE_RETENTION_DAYS', 90),
    'ai_log_retention_days' => (int) env('PRIVACY_AI_LOG_RETENTION_DAYS', 90),
    'export_ttl_hours' => (int) env('PRIVACY_EXPORT_TTL_HOURS', 24),
    'privacy_request_retention_years' => (int) env('PRIVACY_REQUEST_RETENTION_YEARS', 5),

    /*
    |--------------------------------------------------------------------------
    | Wipe automático de tenant cancelado
    |--------------------------------------------------------------------------
    |
    | Permanece false em produção até o dump tenant_portability (SIG-26 PR10)
    | estar verde. O comando de purge deve consultar esta flag.
    |
    */

    'auto_wipe_enabled' => (bool) env('PRIVACY_AUTO_WIPE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Avisos D60 / D83 antes do wipe
    |--------------------------------------------------------------------------
    |
    | Dias restantes em relação a wipe_scheduled_at. O comando diário envia
    | os e-mails mesmo com auto_wipe_enabled=false — só o drop fica travado.
    |
    */

    'wipe_first_notice_days_before' => 30,
    'wipe_final_notice_days_before' => 7,

    /*
    |--------------------------------------------------------------------------
    | Envelope da encryption_key (PR12)
    |--------------------------------------------------------------------------
    |
    | local: APP_KEY como KEK. kms: mesmo contrato hoje; TENANT_KEK_ARN
    | reserva o ARN para o driver AWS futuro.
    |
    */

    'tenant_kek_driver' => env('TENANT_KEK_DRIVER', 'local'),
    'tenant_kek_arn' => env('TENANT_KEK_ARN'),

    /*
    |--------------------------------------------------------------------------
    | Subprocessadores efetivamente usados pelo backend
    |--------------------------------------------------------------------------
    |
    | Inventário operacional (art. 37). ViaCEP e pixels de marketing não
    | aparecem aqui: o backend não os chama. Exposto depois em GET /privacy/me.
    |
    */

    'subprocessors' => [
        [
            'key' => 'stripe',
            'name' => 'Stripe',
            'purpose' => 'Pagamentos, assinaturas, portal de cobrança e webhooks',
            'data_categories' => ['billing_email', 'billing_name', 'tax_id', 'plan', 'payment_method'],
            'location' => 'Estados Unidos / Irlanda',
            'role' => 'operator',
        ],
        [
            'key' => 'resend',
            'name' => 'Resend',
            'purpose' => 'Entrega de e-mail transacional',
            'data_categories' => ['name', 'email'],
            'location' => 'Estados Unidos',
            'role' => 'operator',
        ],
        [
            'key' => 'object_storage',
            'name' => 'Object storage S3-compatível (AWS S3 ou Cloudflare R2)',
            'purpose' => 'Documentos, relatórios, exports e uploads do tenant',
            'data_categories' => ['files', 'exports'],
            'location' => 'conforme região do bucket',
            'role' => 'operator',
        ],
        [
            'key' => 'ai_chat',
            'name' => 'Provedor do agente SIG_IA (OpenRouter / DeepSeek / Gemini / OpenAI)',
            'purpose' => 'Chat, embeddings e narrativas — via laravel/ai',
            'data_categories' => ['prompts_redacted', 'tool_args_redacted'],
            'location' => 'fora do Brasil (varia por provedor)',
            'role' => 'operator',
        ],
        [
            'key' => 'opencode_go',
            'name' => 'OpenCode Go',
            'purpose' => 'Análise de conteúdo de PDF (DocumentUnderstandingService)',
            'data_categories' => ['pdf_bytes'],
            'location' => 'fora do Brasil',
            'role' => 'operator',
        ],
        [
            'key' => 'google_maps',
            'name' => 'Google Maps Platform',
            'purpose' => 'Geocoding, Places e Elevation quando o provider está ativo',
            'data_categories' => ['coordinates', 'address'],
            'location' => 'Estados Unidos',
            'role' => 'operator',
        ],
        [
            'key' => 'opentopography',
            'name' => 'OpenTopography',
            'purpose' => 'DEM para cálculo de declividade',
            'data_categories' => ['coordinates'],
            'location' => 'Estados Unidos',
            'role' => 'operator',
        ],
        [
            'key' => 'open_elevation',
            'name' => 'Open-Elevation',
            'purpose' => 'Elevação quando ELEVATION_PROVIDER=open-elevation',
            'data_categories' => ['coordinates'],
            'location' => 'varia',
            'role' => 'operator',
        ],
        [
            'key' => 'serper',
            'name' => 'Serper',
            'purpose' => 'Busca de mercado imobiliário nas tools de IA',
            'data_categories' => ['search_queries'],
            'location' => 'Estados Unidos',
            'role' => 'operator',
        ],
        [
            'key' => 'expo',
            'name' => 'Expo Push',
            'purpose' => 'Notificações push do app mobile',
            'data_categories' => ['device_token'],
            'location' => 'Estados Unidos',
            'role' => 'operator',
        ],
    ],

];
