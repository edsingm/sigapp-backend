<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => 'openai',
    'agent_provider' => env('AI_PROVIDER', 'openrouter'),
    'default_for_images' => 'gemini',
    'default_for_audio' => 'gemini',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'openai',
    'default_for_reranking' => 'cohere',
    'embedding_provider' => env('AI_EMBEDDING_PROVIDER', 'openai'),
    'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'embedding_min_similarity' => (float) env('AI_EMBEDDING_MIN_SIMILARITY', 0.35),
    'fallback_provider' => env('AI_FALLBACK_PROVIDER', ''),
    'fallback_agent_model' => env('AI_FALLBACK_AGENT_MODEL', ''),
    'tenant_budget_default' => (float) env('AI_TENANT_BUDGET_DEFAULT', 10.00),
    'agent_budget_reservation_usd' => (float) env('AI_AGENT_BUDGET_RESERVATION_USD', 0.25),
    'embedding_budget_reservation_usd' => (float) env('AI_EMBEDDING_BUDGET_RESERVATION_USD', 0.001),
    'document_budget_reservation_usd' => (float) env('AI_DOCUMENT_BUDGET_RESERVATION_USD', 0.05),
    'budget_reservation_ttl_minutes' => (int) env('AI_BUDGET_RESERVATION_TTL_MINUTES', 15),
    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 30),
    'pdf_rate_limit_per_hour' => (int) env('AI_PDF_RATE_LIMIT_PER_HOUR', 10),
    'pdf_max_html_chars' => (int) env('AI_PDF_MAX_HTML_CHARS', 150000),
    'mercado_rate_limit_per_hour' => (int) env('AI_MERCADO_RATE_LIMIT_PER_HOUR', 10),

    /*
    |--------------------------------------------------------------------------
    | Document analysis (OpenCode Go / GPT-5.6 Luna)
    |--------------------------------------------------------------------------
    |
    | Used only by DocumentUnderstandingService — not by the SIG_IA chat agent.
    |
    */
    'document_provider' => env('AI_DOCUMENT_PROVIDER', 'opencode_go'),
    'document_model' => env('AI_DOCUMENT_MODEL', 'gpt-5.6-luna'),
    'document_timeout_seconds' => (int) env('AI_DOCUMENT_TIMEOUT_SECONDS', 120),
    'document_max_bytes' => (int) env('AI_DOCUMENT_MAX_BYTES', 10_485_760),
    'document_max_pages' => (int) env('AI_DOCUMENT_MAX_PAGES', 30),

    'prices_per_million_tokens' => [
        'deepseek' => [
            'input_cache_hit' => (float) env('AI_DEEPSEEK_INPUT_PRICE_PER_M', 0.0028),
            'input_cache_miss' => (float) env('AI_DEEPSEEK_INPUT_CACHE_MISS_PRICE_PER_M', 0.14),
            'output' => (float) env('AI_DEEPSEEK_OUTPUT_PRICE_PER_M', 0.28),
        ],
        'openrouter' => [
            'input' => (float) env('AI_OPENROUTER_INPUT_PRICE_PER_M', 0.00),
            'output' => (float) env('AI_OPENROUTER_OUTPUT_PRICE_PER_M', 0.00),
        ],
        'gemini' => [
            'input' => (float) env('AI_GEMINI_INPUT_PRICE_PER_M', 0.00),
            'output' => (float) env('AI_GEMINI_OUTPUT_PRICE_PER_M', 0.00),
        ],
        'anthropic' => [
            'input' => (float) env('AI_ANTHROPIC_INPUT_PRICE_PER_M', 3.00),
            'output' => (float) env('AI_ANTHROPIC_OUTPUT_PRICE_PER_M', 15.00),
        ],
        'openai' => [
            'input' => (float) env('AI_OPENAI_INPUT_PRICE_PER_M', 2.50),
            'output' => (float) env('AI_OPENAI_OUTPUT_PRICE_PER_M', 10.00),
        ],
        'opencode_go' => [
            'input' => (float) env('AI_OPENCODE_GO_INPUT_PRICE_PER_M', 0.20),
            'output' => (float) env('AI_OPENCODE_GO_OUTPUT_PRICE_PER_M', 1.20),
        ],
    ],

    'embedding_prices_per_million_tokens' => [
        'openai' => (float) env('AI_OPENAI_EMBEDDING_PRICE_PER_M', 0.02),
        'azure' => (float) env('AI_AZURE_EMBEDDING_PRICE_PER_M', 0.02),
        'gemini' => (float) env('AI_GEMINI_EMBEDDING_PRICE_PER_M', 0),
        'mistral' => (float) env('AI_MISTRAL_EMBEDDING_PRICE_PER_M', 0),
        'cohere' => (float) env('AI_COHERE_EMBEDDING_PRICE_PER_M', 0),
        'jina' => (float) env('AI_JINA_EMBEDDING_PRICE_PER_M', 0),
        'voyageai' => (float) env('AI_VOYAGEAI_EMBEDDING_PRICE_PER_M', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversations (laravel/ai 0.10+)
    |--------------------------------------------------------------------------
    |
    | Agent conversation storage. Tables live in the tenant schema; connection
    | follows the active tenancy connection when null.
    |
    */
    'conversations' => [
        'connection' => null,
        'generate_title' => true,
        'tables' => [
            'conversations' => 'agent_conversations',
            'messages' => 'agent_conversation_messages',
        ],
    ],

    'models' => [
        'deepseek' => [
            'agent' => env('AI_DEEPSEEK_AGENT_MODEL', 'deepseek-v4-flash'),
        ],
        'gemini' => [
            'agent' => env('AI_GEMINI_AGENT_MODEL', 'gemini-2.5-flash-native-audio-dialog'),
        ],
        'openrouter' => [
            'agent' => env('AI_OPENROUTER_AGENT_MODEL', 'z-ai/glm-4.5-air:free'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2025-04-01-preview'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
            'image_deployment' => env('AZURE_OPENAI_IMAGE_DEPLOYMENT', 'gpt-image-1'),
            'store' => env('AZURE_OPENAI_STORE', true),
        ],

        'bedrock' => [
            'driver' => 'bedrock',
            'region' => env('AWS_BEDROCK_REGION', 'us-east-1'),
            'key' => env('AWS_BEARER_TOKEN_BEDROCK'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'session_token' => env('AWS_SESSION_TOKEN'),
            'use_default_credential_provider' => env('AWS_USE_DEFAULT_CREDENTIALS', true),
            'assume_role' => [
                'arn' => env('AWS_BEDROCK_ASSUME_ROLE_ARN'),
                'session_name' => env('AWS_BEDROCK_ASSUME_ROLE_SESSION_NAME'),
                'duration_seconds' => env('AWS_BEDROCK_ASSUME_ROLE_DURATION_SECONDS'),
                'external_id' => env('AWS_BEDROCK_ASSUME_ROLE_EXTERNAL_ID'),
            ],
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            // Prefer legacy OLLAMA_BASE_URL; fall back to package OLLAMA_URL.
            'url' => env('OLLAMA_BASE_URL', env('OLLAMA_URL', 'http://localhost:11434')),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'store' => env('OPENAI_STORE', true),
        ],

        'openai-compatible' => [
            'driver' => 'openai-compatible',
            'url' => env('OPENAI_COMPATIBLE_URL'),
            'key' => env('OPENAI_COMPATIBLE_API_KEY'),
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
        ],

        // Document analysis client (SIGAPP) — not a laravel/ai agent driver.
        'opencode_go' => [
            'driver' => 'opencode_go',
            'key' => env('OPENCODE_GO_API_KEY'),
            'url' => env('OPENCODE_GO_BASE_URL', 'https://opencode.ai/zen/go/v1'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
        ],
    ],

];
