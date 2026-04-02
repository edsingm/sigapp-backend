<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Notificação SIG.APP')</title>
    <style>
        /*
         * CLASSES TAILWIND CSS
         * Estas classes serão convertidas em CSS Inline (style="") pelo inliner nativo do Laravel.
         */
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f3f4f6; margin: 0; padding: 0; }
        .bg-gray-100 { background-color: #f3f4f6; }
        .bg-white { background-color: #ffffff; }
        .bg-blue-600 { background-color: #2563eb; }
        .bg-red-600 { background-color: #dc2626; }
        .bg-yellow-500 { background-color: #eab308; }
        
        .text-white { color: #ffffff; }
        .text-gray-800 { color: #1f2937; }
        .text-gray-600 { color: #4b5563; }
        .text-gray-400 { color: #9ca3af; }
        .text-blue-600 { color: #2563eb; }
        .text-red-600 { color: #dc2626; }
        
        .max-w-xl { max-width: 36rem; }
        .mx-auto { margin-left: auto; margin-right: auto; }
        .w-full { width: 100%; }
        
        .p-8 { padding: 2rem; }
        .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
        .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        
        .mt-4 { margin-top: 1rem; }
        .mt-6 { margin-top: 1.5rem; }
        .mt-8 { margin-top: 2rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        
        .rounded-lg { border-radius: 0.5rem; }
        .rounded { border-radius: 0.25rem; }
        .shadow { box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px -1px rgba(0,0,0,0.1); }
        
        .text-2xl { font-size: 1.5rem; line-height: 2rem; }
        .text-xl { font-size: 1.25rem; line-height: 1.75rem; }
        .text-sm { font-size: 0.875rem; }
        
        .font-bold { font-weight: 700; }
        .font-semibold { font-weight: 600; }
        
        .text-center { text-align: center; }
        .inline-block { display: inline-block; }
        .no-underline { text-decoration: none; }
        .border-t { border-top-width: 1px; border-top-style: solid; border-top-color: #e5e7eb; }
        .pt-6 { padding-top: 1.5rem; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-xl mx-auto mt-8">
        <div class="bg-white rounded-lg shadow p-8">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">SIG.APP</h2>
            </div>
            
            @yield('content')

            <div class="border-t pt-6 mt-6">
                <p class="text-gray-600">
                    Abraços,<br>
                    <strong>Equipe SIG.APP</strong>
                </p>
            </div>
        </div>
        
        <div class="text-center mt-6 text-sm text-gray-400">
            &copy; {{ date('Y') }} SIG.APP. Todos os direitos reservados.
        </div>
    </div>
</body>
</html>