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
    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 30),

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
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-10-21'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
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
            'url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
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
