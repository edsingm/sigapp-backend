<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title', 'Notificação SIG.APP')</title>
    <style>
        @media only screen and (max-width: 600px) {
            .email-wrapper { padding: 16px !important; }
            .email-content { padding: 24px 20px !important; }
            .email-button { display: block !important; width: 100% !important; padding: 14px 20px !important; font-size: 16px !important; }
            .email-header-logo { font-size: 20px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">

    <div class="email-wrapper" style="max-width: 600px; margin: 0 auto; padding: 24px 16px;">

        <div class="email-content" style="background-color: #ffffff; border-radius: 12px; padding: 32px 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">

            <div class="email-header" style="text-align: center; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid #e4e4e7;">
                <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #18181b; letter-spacing: -0.02em;">SIG.APP</h1>
                <p style="margin: 4px 0 0; font-size: 13px; color: #a1a1aa;">Sistema Integrado de Gestão</p>
            </div>

            @yield('content')

            <div style="border-top: 1px solid #e4e4e7; padding-top: 20px; margin-top: 28px;">
                <p style="margin: 0 0 4px; font-size: 14px; color: #52525b; line-height: 1.6;">
                    Atenciosamente,<br>
                    <strong style="color: #18181b;">Equipe SIG.APP</strong>
                </p>
                <p style="margin: 16px 0 0; font-size: 12px; color: #a1a1aa; line-height: 1.5;">
                    Se tiver dúvidas, responda este e-mail ou entre em contato pelo nosso suporte.
                </p>
            </div>

        </div>

        <div style="text-align: center; margin-top: 16px; padding: 0 16px;">
            <p style="margin: 0; font-size: 12px; color: #a1a1aa; line-height: 1.5;">
                &copy; {{ date('Y') }} SIG.APP. Todos os direitos reservados.
            </p>
        </div>

    </div>

</body>
</html>