@extends('emails.layouts.base')

@section('title', 'Atualização sobre sua assinatura - SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Olá, {{ $tenantName }}!</h2>

    <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px 20px; margin-bottom: 20px; border-radius: 4px;">
        <p style="margin: 0; font-size: 14px; font-weight: 600; color: #7f1d1d;">
            @if ($attemptCount > 0)
                Não conseguimos processar o pagamento após {{ $attemptCount }} {{ $attemptCount === 1 ? 'tentativa' : 'tentativas' }}.
            @else
                Identificamos uma pendência no pagamento da sua assinatura.
            @endif
        </p>
    </div>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Para evitar a suspensão da sua conta e continuar usando o SIG.APP sem interrupção, por favor, verifique seu método de pagamento e tente novamente.
    </p>

    @if ($invoiceUrl)
        <div style="text-align: center; margin: 24px 0;">
            <a href="{{ $invoiceUrl }}"
               style="display: inline-block; padding: 14px 32px; background-color: #dc2626; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                Atualizar Pagamento
            </a>
        </div>
    @endif

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Se precisar de ajuda, responda este e-mail ou entre em contato com nosso suporte.
    </p>
@endsection
