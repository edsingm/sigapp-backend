<!doctype html>
<html lang="pt-BR" data-theme="{{ $config->get('ui.theme', 'dark') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>

    <script src="https://unpkg.com/@stoplight/elements@8.4.2/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements@8.4.2/styles.min.css">

    <style>
        :root {
            --primary-color: #0ea5e9;
        }

        .custom-header {
            background: #0f172a;
            border-bottom: 1px solid #1e2937;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-weight: 700;
            font-size: 1.3rem;
            text-decoration: none;
        }
    </style>
</head>
<body style="margin:0; height:100vh; overflow:hidden; background:var(--color-canvas)">

    <!-- Header Personalizado -->
    <div class="custom-header">
        <a href="/" class="logo">
            <img src="{{ asset('logo-developers.png') }}" alt="Logo" style="height: 38px;">
            Developers - Minha Empresa
        </a>

        <div>
            <a href="/docs/api.json" target="_blank" style="color:#94a3b8; margin-right:20px;">OpenAPI JSON</a>
            <a href="https://meudominio.com.br" target="_blank" style="color:#94a3b8;">Site</a>
        </div>
    </div>

    <!-- Stoplight Elements -->
    <elements-api
        id="docs"
        tryItCredentialsPolicy="{{ $config->get('ui.try_it_credentials_policy', 'include') }}"
        router="hash"
        @if($config->get('ui.hide_try_it')) hideTryIt="true" @endif
        @if($config->get('ui.hide_schemas')) hideSchemas="true" @endif
        @if($config->get('ui.logo')) logo="{{ $config->get('ui.logo') }}" @endif
        @if($config->get('ui.layout')) layout="{{ $config->get('ui.layout') }}" @endif
    />

    <script>
        (async () => {
            const docs = document.getElementById('docs');
            docs.apiDescriptionDocument = @json($spec);
        })();
    </script>

    <!-- Mantém o intercept de CSRF (importante para Sanctum) -->
    <script>
        const originalFetch = window.fetch;
        window.fetch = (url, options) => {
            // ... (cole aqui o código de fetch original que vinha na view)
            return originalFetch(url, options);
        };
    </script>

</body>
</html>